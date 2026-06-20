<?php

namespace App\Services\Analytics;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Records and reports bio-page analytics (views + per-block clicks). Query
 * methods mirror AnalyticsService so the same report view renders bio data.
 */
class BioAnalytics
{
    public function record(int $pageId, ?int $blockId, string $type, Request $request): void
    {
        $parsed = UaParser::parse($request->userAgent());
        // Only trust Cloudflare headers when the operator confirmed they are behind CF.
        $cf = \App\Models\Setting::get('geo_cf_headers') === '1';
        $ip = $cf ? ($request->header('CF-Connecting-IP') ?: $request->ip()) : $request->ip();
        $cfCountry = $cf ? $request->header('CF-IPCountry') : null;
        $referer = $request->headers->get('referer');

        DB::table('bio_events')->insert([
            'bio_page_id' => $pageId,
            'block_id' => $blockId,
            'type' => $type,
            'ip_hash' => $ip ? hash('sha256', $ip.config('app.key')) : null,
            'country' => app(GeoResolver::class)->country($ip, $cfCountry),
            'device' => $parsed['device'],
            'browser' => $parsed['browser'],
            'referer_host' => $referer ? parse_url($referer, PHP_URL_HOST) : null,
            'is_bot' => $parsed['is_bot'],
            'created_at' => now(),
        ]);
    }

    private function base(Closure $scope, Carbon $from, Carbon $to)
    {
        $q = DB::table('bio_events')
            ->where('created_at', '>=', $from->copy()->startOfDay())
            ->where('created_at', '<=', $to->copy()->endOfDay());
        $scope($q);

        return $q;
    }

    /** @return array{views:int, clicks:int, uniques:int, bots:int} */
    public function totals(Closure $scope, Carbon $from, Carbon $to): array
    {
        return [
            'views' => (int) $this->base($scope, $from, $to)->where('type', 'view')->count(),
            'clicks' => (int) $this->base($scope, $from, $to)->where('type', 'click')->where('is_bot', false)->count(),
            'uniques' => (int) $this->base($scope, $from, $to)->where('type', 'view')->distinct()->count('ip_hash'),
            'bots' => (int) $this->base($scope, $from, $to)->where('type', 'view')->where('is_bot', true)->count(),
        ];
    }

    /** @return list<array{day:string, clicks:int, uniques:int}>  (clicks = views, gap-filled) */
    public function series(Closure $scope, Carbon $from, Carbon $to): array
    {
        $rows = $this->base($scope, $from, $to)->where('type', 'view')
            ->selectRaw('DATE(created_at) d, COUNT(*) views, COUNT(DISTINCT ip_hash) uniques')
            ->groupBy('d')->get()->keyBy('d');

        $out = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $row = $rows->get($d->toDateString());
            $out[] = ['day' => $d->toDateString(), 'clicks' => (int) ($row->views ?? 0), 'uniques' => (int) ($row->uniques ?? 0)];
        }

        return $out;
    }

    /** @return array<string, list<array{label:string, clicks:int}>>  (from view events) */
    public function dimensions(Closure $scope, Carbon $from, Carbon $to, int $per = 8): array
    {
        $out = [];
        foreach (['country' => 'country', 'device' => 'device', 'browser' => 'browser', 'referer' => 'referer_host'] as $key => $col) {
            $out[$key] = $this->base($scope, $from, $to)->where('type', 'view')->whereNotNull($col)
                ->selectRaw("$col label, COUNT(*) clicks")->groupBy($col)->orderByDesc('clicks')->limit($per)->get()
                ->map(fn ($r) => ['label' => (string) $r->label, 'clicks' => (int) $r->clicks])->all();
        }

        return $out;
    }

    /** @return array<string, int> country code => views */
    public function countries(Closure $scope, Carbon $from, Carbon $to): array
    {
        return $this->base($scope, $from, $to)->where('type', 'view')->whereNotNull('country')
            ->selectRaw('country, COUNT(*) c')->groupBy('country')->orderByDesc('c')
            ->pluck('c', 'country')->map(fn ($v) => (int) $v)->all();
    }
}
