<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use App\Support\Installer;
use Database\Seeders\HelpArticleSeeder;
use Database\Seeders\PageSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Friendly first-run installer. Walks the operator through a requirements check,
 * database + .env setup (with migrations), and the admin account, then writes the
 * install lock. No shell or Composer access is assumed — every step runs in the browser.
 */
class InstallController extends Controller
{
    public function welcome()
    {
        return view('install.welcome', [
            'requirements' => Installer::requirements(),
            'writable' => Installer::writableChecks(),
            'ready' => Installer::ready(),
        ]);
    }

    public function database(Request $request)
    {
        return view('install.database', [
            'old' => [
                'site_name' => old('site_name', config('linkforge.name')),
                'app_url' => old('app_url', $request->getSchemeAndHttpHost()),
                'db_host' => old('db_host', '127.0.0.1'),
                'db_port' => old('db_port', '3306'),
                'db_database' => old('db_database', ''),
                'db_username' => old('db_username', ''),
            ],
        ]);
    }

    public function saveDatabase(Request $request)
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:120'],
            'app_url' => ['required', 'url', 'max:255'],
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'db_database' => ['required', 'string', 'max:255'],
            'db_username' => ['required', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:255'],
        ]);

        // Point the live connection at the supplied credentials and prove it works.
        config([
            'database.connections.mysql.host' => $data['db_host'],
            'database.connections.mysql.port' => (int) $data['db_port'],
            'database.connections.mysql.database' => $data['db_database'],
            'database.connections.mysql.username' => $data['db_username'],
            'database.connections.mysql.password' => (string) ($data['db_password'] ?? ''),
        ]);
        DB::purge('mysql');

        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Could not connect to the database: '.$e->getMessage());
        }

        Installer::writeEnv([
            'APP_NAME' => $data['site_name'],
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => rtrim($data['app_url'], '/'),
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $data['db_host'],
            'DB_PORT' => (string) $data['db_port'],
            'DB_DATABASE' => $data['db_database'],
            'DB_USERNAME' => $data['db_username'],
            'DB_PASSWORD' => (string) ($data['db_password'] ?? ''),
        ]);

        try {
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('db:seed', ['--class' => PlanSeeder::class, '--force' => true]);
            Artisan::call('db:seed', ['--class' => SettingSeeder::class, '--force' => true]);
            // Ship a ready-made Help Center (operators can edit or delete the articles).
            Artisan::call('db:seed', ['--class' => HelpArticleSeeder::class, '--force' => true]);
            // Ship ready-to-edit Terms / Privacy / Contact pages, linked in the footer.
            Artisan::call('db:seed', ['--class' => PageSeeder::class, '--force' => true]);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Database setup failed: '.$e->getMessage());
        }

        // System short-domain + persisted site name.
        $host = parse_url($data['app_url'], PHP_URL_HOST) ?: 'localhost';
        Domain::firstOrCreate(['host' => $host], ['user_id' => null, 'is_default' => true, 'status' => 'active']);
        Setting::put('site_name', $data['site_name']);
        Setting::flushCache();

        session(['install.db' => true]);

        return redirect()->route('install.account');
    }

    public function account()
    {
        if (! session('install.db')) {
            return redirect()->route('install.database');
        }

        return view('install.account');
    }

    public function saveAccount(Request $request)
    {
        if (! session('install.db')) {
            return redirect()->route('install.database');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $business = Plan::where('slug', 'business')->first();

        $user = User::updateOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['name'],
                'password' => $data['password'], // hashed by the model cast
                'role' => 'admin',
                'status' => 'active',
                'plan_id' => $business?->id,
                'ai_credits' => (int) ($business?->limit('ai_credits') ?? 0),
                'email_verified_at' => now(),
            ]
        );

        session(['install.admin' => $user->id]);

        return redirect()->route('install.complete');
    }

    public function complete()
    {
        if (! session('install.admin')) {
            return redirect()->route('install.account');
        }

        // Sealing the installer: the guard now blocks every /install route.
        Installer::markInstalled((string) config('linkforge.version'));
        session()->forget(['install.db', 'install.admin']);

        return view('install.complete');
    }
}
