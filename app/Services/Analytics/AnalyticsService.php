<?php

namespace App\Services\Analytics;

use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Reads the rollup tables (never raw clicks) so dashboards stay fast at scale.
 * The $scope closure constrains link_id (account-wide subquery, or a single link).
 */
class AnalyticsService
{
    private bool $rolledUp = false;

    /**
     * Bring the click rollups up to date on demand, so analytics shows data even
     * when the scheduled clicks:rollup task is not running (common on shared
     * hosting without a working cron). Runs at most once per request; the rollup
     * is incremental + idempotent, so it is a cheap no-op when nothing is new and
     * never blocks the read if it fails.
     */
    private function ensureFresh(): void
    {
        if ($this->rolledUp) {
            return;
        }
        $this->rolledUp = true;

        try {
            Artisan::call('clicks:rollup');
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** @return array{clicks:int, uniques:int, bots:int} */
    public function totals(Closure $scope, Carbon $from, Carbon $to): array
    {
        $this->ensureFresh();
        $q = DB::table('stat_daily')->whereBetween('day', [$from->toDateString(), $to->toDateString()]);
        $scope($q);

        $r = $q->selectRaw('COALESCE(SUM(clicks),0) c, COALESCE(SUM(uniques),0) u, COALESCE(SUM(bots),0) b')->first();

        return ['clicks' => (int) $r->c, 'uniques' => (int) $r->u, 'bots' => (int) $r->b];
    }

    /** @return list<array{day:string, clicks:int, uniques:int}>  (gap-filled) */
    public function series(Closure $scope, Carbon $from, Carbon $to): array
    {
        $this->ensureFresh();
        $q = DB::table('stat_daily')->whereBetween('day', [$from->toDateString(), $to->toDateString()]);
        $scope($q);

        $rows = $q->groupBy('day')
            ->selectRaw('day, SUM(clicks) clicks, SUM(uniques) uniques')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $out = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $key = $d->toDateString();
            $row = $rows->get($key);
            $out[] = [
                'day' => $key,
                'clicks' => (int) ($row->clicks ?? 0),
                'uniques' => (int) ($row->uniques ?? 0),
            ];
        }

        return $out;
    }

    /**
     * All rows for a single dimension as label => clicks, sorted desc.
     * Used for the country choropleth (every country, not just the top N).
     *
     * @return array<string, int>
     */
    public function dimensionFull(Closure $scope, Carbon $from, Carbon $to, string $dimension): array
    {
        $this->ensureFresh();
        $q = DB::table('stat_dimension')
            ->whereBetween('day', [$from->toDateString(), $to->toDateString()])
            ->where('dimension', $dimension);
        $scope($q);

        return $q->groupBy('label')
            ->selectRaw('label, SUM(clicks) clicks')
            ->orderByDesc('clicks')
            ->pluck('clicks', 'label')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /** @return array<string, list<array{label:string, clicks:int}>> */
    public function dimensions(Closure $scope, Carbon $from, Carbon $to, int $perDimension = 8): array
    {
        $this->ensureFresh();
        $q = DB::table('stat_dimension')->whereBetween('day', [$from->toDateString(), $to->toDateString()]);
        $scope($q);

        $rows = $q->groupBy('dimension', 'label')
            ->selectRaw('dimension, label, SUM(clicks) clicks')
            ->orderByDesc('clicks')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row->dimension][] = ['label' => (string) $row->label, 'clicks' => (int) $row->clicks];
        }

        foreach ($grouped as $dimension => $items) {
            $grouped[$dimension] = array_slice($items, 0, $perDimension);
        }

        return $grouped;
    }
}
