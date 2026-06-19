<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        // null limit = unlimited. ai_credits here seeds the user's monthly allowance.
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'price' => 0,
                'interval' => 'free',
                'sort' => 0,
                'limits' => [
                    'max_links' => 25, 'max_clicks' => 2000, 'max_domains' => 0,
                    'max_team' => 1, 'max_qr' => 5, 'max_bio' => 1, 'ai_credits' => 10,
                ],
                'features' => [
                    'custom_domains' => false, 'retargeting' => false, 'api' => false,
                    'deep_links' => false, 'team' => false, 'white_label' => false, 'ad_free' => false,
                ],
            ],
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'price' => 9,
                'interval' => 'month',
                'sort' => 1,
                'limits' => [
                    'max_links' => 1000, 'max_clicks' => 50000, 'max_domains' => 1,
                    'max_team' => 1, 'max_qr' => 50, 'max_bio' => 3, 'ai_credits' => 150,
                ],
                'features' => [
                    'custom_domains' => true, 'retargeting' => false, 'api' => true,
                    'deep_links' => false, 'team' => false, 'white_label' => false, 'ad_free' => true,
                ],
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'price' => 29,
                'interval' => 'month',
                'sort' => 2,
                'limits' => [
                    'max_links' => 10000, 'max_clicks' => 500000, 'max_domains' => 5,
                    'max_team' => 3, 'max_qr' => 1000, 'max_bio' => 25, 'ai_credits' => 1500,
                ],
                'features' => [
                    'custom_domains' => true, 'retargeting' => true, 'api' => true,
                    'deep_links' => true, 'team' => true, 'white_label' => false, 'ad_free' => true,
                ],
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'price' => 99,
                'interval' => 'month',
                'sort' => 3,
                'limits' => [
                    'max_links' => null, 'max_clicks' => null, 'max_domains' => 25,
                    'max_team' => 15, 'max_qr' => null, 'max_bio' => null, 'ai_credits' => 15000,
                ],
                'features' => [
                    'custom_domains' => true, 'retargeting' => true, 'api' => true,
                    'deep_links' => true, 'team' => true, 'white_label' => true, 'ad_free' => true,
                ],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
