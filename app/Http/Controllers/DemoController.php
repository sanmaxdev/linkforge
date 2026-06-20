<?php

namespace App\Http\Controllers;

use App\Support\Demo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

/**
 * One-click sign-in for the public demo (no password). Only active in demo mode.
 */
class DemoController extends Controller
{
    public function login(Request $request, string $role)
    {
        abort_unless(Demo::enabled(), 404);

        $email = $role === 'admin' ? Demo::ADMIN_EMAIL : Demo::USER_EMAIL;
        $user = \App\Models\User::where('email', $email)->first();

        // Self-heal: if the hourly demo:reset hasn't seeded the accounts yet,
        // build the demo now so the one-click logins always work.
        if (! $user) {
            try {
                Artisan::call('demo:reset', ['--force' => true]);
            } catch (\Throwable $e) {
                // fall through to the 404 below
            }
            $user = \App\Models\User::where('email', $email)->first();
        }

        abort_unless($user, 404);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->route($role === 'admin' ? 'admin.dashboard' : 'dashboard');
    }
}
