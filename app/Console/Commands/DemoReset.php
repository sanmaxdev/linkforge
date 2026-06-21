<?php

namespace App\Console\Commands;

use App\Models\BioBlock;
use App\Models\BioPage;
use App\Models\Domain;
use App\Models\HelpArticle;
use App\Models\Plan;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use App\Services\Affiliate\ReferralService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Resets the public demo to a known, attractive state: recreates the two demo
 * accounts, a rich sample dataset (links, campaigns, pixels, affiliate) and
 * realistic click history (geo / cities / devices / referrers) so the analytics
 * dashboards are full. Clears anything visitors changed. Schedule it hourly
 * (routes/console.php). No-op unless demo mode is on.
 */
class DemoReset extends Command
{
    protected $signature = 'demo:reset {--force : Run even when demo mode is off}';

    protected $description = 'Reset the public demo accounts + sample data';

    /** Days of click history to synthesize. */
    private const HISTORY_DAYS = 45;

    public function handle(): int
    {
        if (! \App\Support\Demo::enabled() && ! $this->option('force')) {
            $this->warn('Demo mode is off; nothing to do. Use --force to seed anyway.');

            return self::SUCCESS;
        }

        $plan = Plan::where('slug', 'business')->first() ?? Plan::where('price', '>', 0)->orderByDesc('price')->first();
        $domainId = Domain::where('is_default', true)->value('id');

        $admin = User::updateOrCreate(['email' => \App\Support\Demo::ADMIN_EMAIL], [
            'name' => 'Demo Admin', 'role' => 'admin', 'status' => 'active',
            'password' => Hash::make('demo-'.Str::random(24)), 'plan_id' => $plan?->id,
        ]);
        $user = User::updateOrCreate(['email' => \App\Support\Demo::USER_EMAIL], [
            'name' => 'Demo User', 'role' => 'user', 'status' => 'active',
            'password' => Hash::make('demo-'.Str::random(24)), 'plan_id' => $plan?->id,
        ]);

        // Both accounts get links + analytics so the dashboards are populated
        // whichever one-click login a visitor uses.
        $this->seedAccount($admin, $domainId);
        $this->seedAccount($user, $domainId);

        // Affiliate showcase (customer account only).
        Setting::putMany([
            'guest_shorten' => '1',
            'affiliate_enabled' => '1', 'affiliate_commission_type' => 'percent',
            'affiliate_commission_value' => '25', 'affiliate_min_payout' => '50',
        ]);
        app(ReferralService::class)->codeFor($user);
        $user->forceFill(['referral_clicks' => 132])->save();
        $user->commissions()->delete();
        $user->commissions()->create(['amount' => 24.50, 'currency' => 'USD', 'status' => 'approved', 'note' => 'Sample commission']);
        $user->commissions()->create(['amount' => 12.00, 'currency' => 'USD', 'status' => 'pending', 'note' => 'Sample commission']);

        $this->seedContent($admin->id);

        // Aggregate the synthesized clicks into the analytics rollup tables.
        Artisan::call('clicks:rollup');

        Cache::flush();
        $this->info('Demo reset complete. Admin: '.\App\Support\Demo::ADMIN_EMAIL.' · User: '.\App\Support\Demo::USER_EMAIL);

        return self::SUCCESS;
    }

    /** Recreate one account's links, campaigns, pixels and click history. */
    private function seedAccount(User $account, ?int $domainId): void
    {
        // Clear prior data (links + their clicks + rollups) so nothing accumulates.
        $oldLinkIds = $account->links()->pluck('id');
        if ($oldLinkIds->isNotEmpty()) {
            DB::table('clicks')->whereIn('link_id', $oldLinkIds)->delete();
            DB::table('stat_daily')->whereIn('link_id', $oldLinkIds)->delete();
            DB::table('stat_dimension')->whereIn('link_id', $oldLinkIds)->delete();
        }
        $account->links()->delete();
        $account->campaigns()->delete();
        $account->pixels()->delete();
        $account->qrCodes()->delete();
        $bioIds = $account->bioPages()->pluck('id');
        if ($bioIds->isNotEmpty()) {
            BioBlock::whereIn('bio_page_id', $bioIds)->delete();
            $account->bioPages()->delete();
        }

        $spring = $account->campaigns()->create(['name' => 'Spring sale', 'color' => 'emerald']);
        $news = $account->campaigns()->create(['name' => 'Newsletter', 'color' => 'blue']);

        $links = [
            ['alias' => null, 'long_url' => 'https://example.com/spring-collection', 'title' => 'Spring landing', 'campaign_id' => $spring->id, 'tags' => ['sale', 'q2'], 'seed' => 150],
            ['alias' => null, 'long_url' => 'https://example.com/product-launch', 'title' => 'Product launch', 'campaign_id' => $spring->id, 'tags' => ['launch'], 'seed' => 100],
            ['alias' => null, 'long_url' => 'https://example.com/newsletter', 'title' => 'Newsletter signup', 'campaign_id' => $news->id, 'tags' => ['email'], 'seed' => 70],
            ['alias' => null, 'long_url' => 'https://example.com/get-the-app', 'title' => 'Get the app (deep link)', 'tags' => ['mobile'], 'seed' => 45, 'meta' => ['deep_link' => ['ios' => 'myapp://home', 'android' => 'myapp://home']]],
            ['alias' => null, 'long_url' => 'https://example.com/docs', 'title' => 'Documentation', 'seed' => 30],
            ['alias' => null, 'long_url' => 'https://example.com/promo', 'title' => 'Promo code', 'tags' => ['sale'], 'seed' => 18],
        ];

        foreach ($links as $l) {
            $seed = $l['seed'];
            unset($l['seed'], $l['alias']);
            $link = $account->links()->create(array_merge([
                'domain_id' => $domainId, 'alias' => Str::lower(Str::random(6)),
                'type' => 'direct', 'safety_status' => 'safe', 'is_active' => true, 'clicks' => $seed,
            ], $l));
            $this->seedClicks($link->id, $seed);
        }

        $account->pixels()->create(['provider' => 'facebook', 'pixel_id' => '1234567890', 'name' => 'Meta Pixel']);
        $account->pixels()->create(['provider' => 'google', 'pixel_id' => 'G-DEMO12345', 'name' => 'GA4']);

        $this->seedQrCodes($account);
        $this->seedBioPages($account);
    }

    /** A handful of styled sample QR codes for the QR studio. */
    private function seedQrCodes(User $account): void
    {
        $qrs = [
            ['name' => 'Spring sale poster', 'type' => 'url', 'content' => 'https://example.com/spring-collection', 'scans' => 412,
                'data' => ['url' => 'https://example.com/spring-collection'],
                'design' => ['fg' => '#065f46', 'bg' => '#ffffff', 'dotsType' => 'rounded', 'eyeFrameType' => 'extra-rounded', 'eyeBallType' => 'dot', 'gradient' => true, 'gradStart' => '#10b981', 'gradStop' => '#0f766e', 'gradType' => 'linear', 'gradRotation' => 45, 'margin' => 2, 'ecc' => 'M']],
            ['name' => 'Café Wi-Fi', 'type' => 'wifi', 'content' => 'WIFI:T:WPA;S:LinkForge Cafe;P:forge-guest;;', 'scans' => 138,
                'data' => ['ssid' => 'LinkForge Cafe', 'password' => 'forge-guest', 'encryption' => 'WPA'],
                'design' => ['fg' => '#0f172a', 'bg' => '#ffffff', 'dotsType' => 'classy', 'eyeFrameType' => 'square', 'eyeBallType' => 'square', 'gradient' => false, 'margin' => 2, 'ecc' => 'Q']],
            ['name' => 'Digital business card', 'type' => 'vcard', 'scans' => 96,
                'content' => "BEGIN:VCARD\nVERSION:3.0\nFN:Alex Rivera\nORG:LinkForge\nTITLE:Founder\nTEL:+1-555-0142\nEMAIL:alex@example.com\nURL:https://example.com\nEND:VCARD",
                'data' => ['name' => 'Alex Rivera', 'org' => 'LinkForge', 'title' => 'Founder', 'phone' => '+1-555-0142', 'email' => 'alex@example.com'],
                'design' => ['fg' => '#1e3a8a', 'bg' => '#ffffff', 'dotsType' => 'dots', 'eyeFrameType' => 'extra-rounded', 'eyeBallType' => 'dot', 'gradient' => true, 'gradStart' => '#2563eb', 'gradStop' => '#0ea5e9', 'gradType' => 'linear', 'gradRotation' => 90, 'margin' => 2, 'ecc' => 'M']],
            ['name' => 'Product launch', 'type' => 'url', 'content' => 'https://example.com/product-launch', 'scans' => 247,
                'data' => ['url' => 'https://example.com/product-launch'],
                'design' => ['fg' => '#9d174d', 'bg' => '#ffffff', 'dotsType' => 'extra-rounded', 'eyeFrameType' => 'extra-rounded', 'eyeBallType' => 'dot', 'gradient' => true, 'gradStart' => '#f97316', 'gradStop' => '#ec4899', 'gradType' => 'linear', 'gradRotation' => 135, 'margin' => 2, 'ecc' => 'M']],
        ];

        foreach ($qrs as $q) {
            $account->qrCodes()->create(array_merge(['is_dynamic' => false, 'format' => 'png'], $q));
        }
    }

    /** Two polished bio pages (a creator profile + a shop) with sample blocks. */
    private function seedBioPages(User $account): void
    {
        $admin = $account->role === 'admin';

        $creator = $account->bioPages()->create([
            'slug' => $admin ? 'nova-studio' : 'alex-rivera',
            'title' => $admin ? 'Nova Studio' : 'Alex Rivera',
            'is_published' => true, 'views' => $admin ? 1840 : 3120,
            'theme' => ['headerLayout' => 'banner', 'font' => 'jakarta', 'textColor' => '#ffffff',
                'bg' => ['type' => 'gradient', 'gradStart' => '#7c3aed', 'gradStop' => '#4f46e5', 'gradAngle' => 160],
                'button' => ['color' => '#ffffff', 'textColor' => '#4c1d95', 'style' => 'soft', 'shape' => 'rounded', 'shadow' => 'sm', 'frosted' => true]],
            'settings' => ['description' => $admin ? 'Design studio · branding & web' : 'Creator · tools that help you ship', 'verified' => true,
                'avatar' => ['display' => true, 'style' => 'circle'], 'social_position' => 'top'],
            'social_links' => [
                ['platform' => 'instagram', 'url' => 'https://instagram.com/linkforge'],
                ['platform' => 'x', 'url' => 'https://x.com/linkforge'],
                ['platform' => 'youtube', 'url' => 'https://youtube.com/@linkforge'],
            ],
        ]);
        $this->seedBlocks($creator, [
            ['heading', ['text' => 'Latest links']],
            ['link', ['label' => '🚀 New: our Spring collection', 'url' => 'https://example.com/spring-collection']],
            ['link', ['label' => '🎧 Listen to the podcast', 'url' => 'https://example.com/podcast']],
            ['featured', ['label' => 'Get the app', 'url' => 'https://example.com/get-the-app']],
            ['text', ['text' => 'Thanks for stopping by — tap any link above to dive in.']],
            ['newsletter', ['label' => 'Join the newsletter', 'text' => 'One useful email a week. No spam.', 'button' => 'Subscribe']],
        ]);

        $shop = $account->bioPages()->create([
            'slug' => $admin ? 'forge-shop' : 'spring-shop',
            'title' => $admin ? 'Forge Shop' : 'Spring Shop',
            'is_published' => true, 'views' => $admin ? 640 : 980,
            'theme' => ['headerLayout' => 'classic', 'font' => 'poppins', 'textColor' => '#ffffff',
                'bg' => ['type' => 'gradient', 'gradStart' => '#10b981', 'gradStop' => '#0f766e', 'gradAngle' => 160],
                'button' => ['color' => '#ffffff', 'textColor' => '#065f46', 'style' => 'soft', 'shape' => 'pill', 'shadow' => 'sm', 'frosted' => false]],
            'settings' => ['description' => 'Fresh drops every season', 'verified' => false,
                'avatar' => ['display' => true, 'style' => 'rounded'], 'social_position' => 'top'],
            'social_links' => [
                ['platform' => 'instagram', 'url' => 'https://instagram.com/linkforge'],
                ['platform' => 'tiktok', 'url' => 'https://tiktok.com/@linkforge'],
            ],
        ]);
        $this->seedBlocks($shop, [
            ['text', ['text' => 'Shop the latest — free shipping over $50.']],
            ['product', ['label' => 'Spring Tote Bag', 'price' => '$28', 'text' => 'Organic cotton, roomy and light.', 'url' => 'https://example.com/tote']],
            ['product', ['label' => 'Enamel Mug', 'price' => '$16', 'text' => 'Camp-style, dishwasher safe.', 'url' => 'https://example.com/mug']],
            ['link', ['label' => 'See the full catalogue', 'url' => 'https://example.com/shop']],
        ]);
    }

    /** @param  array<int, array{0:string, 1:array<string,mixed>}>  $blocks */
    private function seedBlocks(BioPage $page, array $blocks): void
    {
        foreach ($blocks as $i => [$type, $content]) {
            $page->blocks()->create(['type' => $type, 'content' => $content, 'sort' => $i, 'is_active' => true]);
        }
    }

    /** Insert $count synthetic click events for a link, spread over the history window. */
    private function seedClicks(int $linkId, int $count): void
    {
        $countries = ['US' => 30, 'IN' => 16, 'GB' => 12, 'DE' => 8, 'BR' => 6, 'CA' => 6, 'AU' => 5, 'FR' => 5, 'JP' => 4, 'NL' => 3, 'SG' => 3, 'AE' => 2];
        $cities = [
            'US' => ['New York', 'Los Angeles', 'Chicago', 'Seattle', 'Austin'], 'IN' => ['Mumbai', 'Delhi', 'Bengaluru', 'Hyderabad'],
            'GB' => ['London', 'Manchester', 'Bristol'], 'DE' => ['Berlin', 'Munich', 'Hamburg'], 'BR' => ['São Paulo', 'Rio de Janeiro'],
            'CA' => ['Toronto', 'Vancouver'], 'AU' => ['Sydney', 'Melbourne'], 'FR' => ['Paris', 'Lyon'],
            'JP' => ['Tokyo', 'Osaka'], 'NL' => ['Amsterdam'], 'SG' => ['Singapore'], 'AE' => ['Dubai'],
        ];
        // Values must match what UaParser produces (the clicks columns are constrained).
        $deviceWeights = ['desktop' => 52, 'mobile' => 40, 'tablet' => 8];
        $deviceOs = ['desktop' => ['Windows', 'macOS', 'Linux'], 'mobile' => ['iOS', 'Android'], 'tablet' => ['iOS', 'Android']];
        $browsers = ['Chrome' => 56, 'Safari' => 22, 'Firefox' => 9, 'Edge' => 9, 'Opera' => 4];
        $referers = ['google.com' => 32, '' => 26, 'twitter.com' => 12, 'facebook.com' => 10, 'linkedin.com' => 9, 'youtube.com' => 6, 'instagram.com' => 5];
        $langs = ['en-US' => 40, 'en-GB' => 14, 'es-ES' => 10, 'hi-IN' => 9, 'de-DE' => 8, 'pt-BR' => 7, 'fr-FR' => 7, 'ja-JP' => 5];

        // A pool of visitors so uniques are realistically below total clicks.
        $pool = [];
        for ($i = 0, $n = max(1, (int) round($count * 0.7)); $i < $n; $i++) {
            $pool[] = hash('sha256', $linkId.'-'.$i.'-'.Str::random(8));
        }

        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $country = $this->pick($countries);
            $device = $this->pick($deviceWeights);
            $os = $deviceOs[$device][array_rand($deviceOs[$device])];
            $ref = $this->pick($referers);
            $isBot = mt_rand(1, 100) <= 7;
            // Bias the timestamp toward recent days for a natural upward trend.
            $daysAgo = (int) round(self::HISTORY_DAYS * (mt_rand(0, 1000) / 1000) ** 1.7);

            $rows[] = [
                'link_id' => $linkId,
                'ip_hash' => $pool[array_rand($pool)],
                'country' => $country,
                'region' => null,
                'city' => $cities[$country][array_rand($cities[$country])],
                'device' => $isBot ? 'bot' : $device,
                'os' => $os,
                'browser' => $this->pick($browsers),
                'referer_host' => $ref === '' ? null : $ref,
                'language' => $this->pick($langs),
                'is_bot' => $isBot ? 1 : 0,
                'created_at' => Carbon::now()->subDays($daysAgo)->subMinutes(mt_rand(0, 1439)),
            ];
        }

        foreach (array_chunk($rows, 400) as $chunk) {
            DB::table('clicks')->insert($chunk);
        }
    }

    /** Weighted random key from [value => weight]. */
    private function pick(array $weighted): string
    {
        $r = mt_rand(1, array_sum($weighted));
        foreach ($weighted as $key => $weight) {
            $r -= $weight;
            if ($r <= 0) {
                return (string) $key;
            }
        }

        return (string) array_key_first($weighted);
    }

    /** Reset the blog + help center to a known sample set. */
    private function seedContent(int $authorId): void
    {
        Post::query()->delete();
        Post::create(['author_id' => $authorId, 'title' => 'Why you should own your URL shortener', 'slug' => 'own-your-shortener', 'status' => 'published', 'published_at' => now()->subDays(3), 'excerpt' => 'Stop renting your most important marketing asset.', 'body' => "## Own your links\n\nYour short links are infrastructure. Self-host and keep **100%** of your data.\n\n- No per-click fees\n- Custom domains\n- Unlimited links"]);
        Post::create(['author_id' => $authorId, 'title' => '5 link-marketing tips that actually work', 'slug' => 'link-marketing-tips', 'status' => 'published', 'published_at' => now()->subDay(), 'excerpt' => 'Practical tactics to get more clicks.', 'body' => "## Track everything\n\nUTM tags + pixels turn every link into a data source.\n\n## Brand your domain\n\nBranded links get more clicks."]);

        // The full starter Help Center library (25+ articles), reset to a clean set.
        HelpArticle::query()->delete();
        (new \Database\Seeders\HelpArticleSeeder)->run();
    }
}
