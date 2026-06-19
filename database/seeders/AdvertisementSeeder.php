<?php

namespace Database\Seeders;

use App\Models\Advertisement;
use Illuminate\Database\Seeder;

/**
 * Ships a sample ad per placement so operators can see the system working
 * immediately (flip the master switch on Admin > Advertisement). Replace the
 * placeholder code with a real ad-network snippet (AdSense, etc.).
 */
class AdvertisementSeeder extends Seeder
{
    public function run(): void
    {
        $banner = fn (string $label, int $w, int $h) => '<div style="width:'.$w.'px;max-width:100%;height:'.$h
            .'px;background:linear-gradient(135deg,#059669,#f59e0b);color:#fff;display:flex;align-items:center;'
            .'justify-content:center;border-radius:8px;font:600 16px/1.3 sans-serif;text-align:center;padding:8px">'.$label.'</div>';

        $samples = [
            ['Sample · Interstitial 728x90', 'interstitial', $banner('Your Ad Here &middot; 728&times;90<br><small>sample &mdash; edit in Admin &rsaquo; Advertisement</small>', 728, 90)],
            ['Sample · Dashboard 728x90', 'dashboard', $banner('Dashboard Ad &middot; 728&times;90 <small>(sample)</small>', 728, 90)],
            ['Sample · Sidebar 300x250', 'sidebar', $banner('Sidebar Ad<br>300&times;250 <small>(sample)</small>', 300, 250)],
            ['Sample · Popup 300x250', 'popup', $banner('Popup Ad<br>300&times;250 <small>(sample)</small>', 300, 250)],
        ];

        foreach ($samples as [$name, $placement, $code]) {
            Advertisement::firstOrCreate(['name' => $name], ['placement' => $placement, 'code' => $code, 'is_active' => true]);
        }

        Advertisement::forgetCache();
    }
}
