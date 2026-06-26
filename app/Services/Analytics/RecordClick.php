<?php

namespace App\Services\Analytics;

use App\Models\Webhook;
use Illuminate\Support\Facades\DB;

class RecordClick
{
    public function __construct(private GeoResolver $geo) {}

    /**
     * Persist a click event and bump the link's denormalized counter.
     * Runs AFTER the response is sent (see RedirectController), so it must
     * never throw into the redirect path.
     *
     * @param  array{link_id:int, ip:?string, ua:?string, referer:?string, language:?string}  $ctx
     */
    public function __invoke(array $ctx): void
    {
        try {
            $parsed = UaParser::parse($ctx['ua'] ?? null);
            $ip = (string) ($ctx['ip'] ?? '');
            $country = $this->geo->country($ip, $ctx['cf_country'] ?? null);
            $region = $this->geo->region($ip, $ctx['cf_region'] ?? null);
            $city = $this->geo->city($ip, $ctx['cf_city'] ?? null);
            $refererHost = ! empty($ctx['referer']) ? parse_url((string) $ctx['referer'], PHP_URL_HOST) : null;

            DB::table('clicks')->insert([
                'link_id' => $ctx['link_id'],
                'ip_hash' => $ip !== '' ? hash('sha256', $ip.config('app.key')) : null,
                'country' => $country,
                'region' => $region,
                'city' => $city,
                'device' => $parsed['device'],
                'os' => $parsed['os'],
                'browser' => $parsed['browser'],
                'referer_host' => $refererHost,
                'language' => $ctx['language'] ?? null,
                'is_bot' => $parsed['is_bot'],
                'created_at' => now(),
            ]);

            DB::table('links')->where('id', $ctx['link_id'])->update([
                'clicks' => DB::raw('clicks + 1'),
                'last_click_at' => now(),
            ]);

            // Notify subscribed webhooks of real (non-bot) clicks.
            if (! $parsed['is_bot'] && ! empty($ctx['user_id'])) {
                Webhook::fire((int) $ctx['user_id'], 'link.clicked', [
                    'id' => $ctx['link_id'],
                    'alias' => $ctx['alias'] ?? null,
                    'short_url' => $ctx['short_url'] ?? null,
                    'target' => $ctx['target'] ?? null,
                    'country' => $country,
                    'device' => $parsed['device'],
                    'referer' => $refererHost,
                ]);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
