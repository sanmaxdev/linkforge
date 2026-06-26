<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RollupClicks extends Command
{
    protected $signature = 'clicks:rollup';

    protected $description = 'Aggregate raw click events into the daily and dimension rollup tables.';

    public function handle(): int
    {
        $cursor = (int) Setting::get('clicks_rollup_cursor', 0);
        $maxId = (int) (DB::table('clicks')->max('id') ?? 0);

        if ($maxId <= $cursor) {
            $this->info('Nothing to roll up.');

            return self::SUCCESS;
        }

        // Only the (link, day) groups touched by new clicks need recomputing.
        $pairs = DB::table('clicks')
            ->where('id', '>', $cursor)
            ->where('id', '<=', $maxId)
            ->select('link_id', DB::raw('DATE(created_at) as day'))
            ->groupBy('link_id', DB::raw('DATE(created_at)'))
            ->get();

        foreach ($pairs as $pair) {
            $this->rollupDay((int) $pair->link_id, (string) $pair->day);
        }

        Setting::put('clicks_rollup_cursor', (string) $maxId);
        $this->info('Rolled up '.count($pairs)." link-day group(s) up to click #{$maxId}.");

        return self::SUCCESS;
    }

    /** Recompute one link's full daily aggregate from raw clicks (idempotent). */
    private function rollupDay(int $linkId, string $day): void
    {
        // Half-open range instead of whereDate() so the (link_id, created_at) index is used
        // (DATE(created_at) would wrap the column and force a scan).
        $start = $day.' 00:00:00';
        $end = Carbon::parse($day)->addDay()->format('Y-m-d').' 00:00:00';
        $base = DB::table('clicks')->where('link_id', $linkId)
            ->where('created_at', '>=', $start)->where('created_at', '<', $end);

        $agg = (clone $base)
            ->selectRaw('COUNT(*) c, COUNT(DISTINCT ip_hash) u, COALESCE(SUM(is_bot), 0) b')
            ->first();

        DB::table('stat_daily')->updateOrInsert(
            ['link_id' => $linkId, 'day' => $day],
            ['clicks' => (int) $agg->c, 'uniques' => (int) $agg->u, 'bots' => (int) $agg->b],
        );

        DB::table('stat_dimension')->where('link_id', $linkId)->where('day', $day)->delete();

        $dimensions = [
            'country' => 'country',
            'city' => 'city',
            'device' => 'device',
            'os' => 'os',
            'browser' => 'browser',
            'referer' => 'referer_host',
            'language' => 'language',
        ];

        foreach ($dimensions as $dimension => $column) {
            $rows = (clone $base)
                ->whereNotNull($column)
                ->selectRaw("$column as label, COUNT(*) as clicks")
                ->groupBy($column)
                ->get();

            foreach ($rows as $row) {
                DB::table('stat_dimension')->insert([
                    'link_id' => $linkId,
                    'day' => $day,
                    'dimension' => $dimension,
                    'label' => (string) $row->label,
                    'clicks' => (int) $row->clicks,
                ]);
            }
        }
    }
}
