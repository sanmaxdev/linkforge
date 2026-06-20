<?php

namespace Database\Seeders;

use App\Models\Domain;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            SettingSeeder::class,
            AdvertisementSeeder::class,
        ]);

        // Default system short-domain (where shared / free-tier links live).
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';

        Domain::firstOrCreate(
            ['host' => $host],
            ['user_id' => null, 'is_default' => true, 'status' => 'active']
        );

        // System account that owns anonymous (guest) short links from the landing page.
        User::firstOrCreate(
            ['email' => 'guest@system.local'],
            [
                'name' => 'Guest',
                'password' => Hash::make(\Illuminate\Support\Str::random(40)),
                'role' => 'user',
                'status' => 'active',
                'plan_id' => Plan::where('slug', 'free')->value('id'),
            ]
        );

        // Admin account (change the password after first login).
        $business = Plan::where('slug', 'business')->first();

        User::firstOrCreate(
            ['email' => 'admin@linkforge.test'],
            [
                'name' => 'LinkForge Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'status' => 'active',
                'plan_id' => $business?->id,
                'ai_credits' => (int) ($business?->limit('ai_credits') ?? 0),
                'email_verified_at' => now(),
            ]
        );
    }
}
