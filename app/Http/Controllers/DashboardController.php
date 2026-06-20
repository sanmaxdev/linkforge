<?php

namespace App\Http\Controllers;

use App\Services\Ai\ClaudeClient;
use App\Services\Analytics\AnalyticsService;
use App\Services\Billing\PlanGate;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(private AnalyticsService $svc, private PlanGate $gate) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $linkIds = DB::table('links')->where('user_id', $user->id)->pluck('id');
        $scope = fn ($q) => $q->whereIn('link_id', $linkIds);

        $to = Carbon::today();
        $from = $to->copy()->subDays(29);

        // 30-day window (from the rollup tables).
        $series = $this->svc->series($scope, $from, $to);
        $window = $this->svc->totals($scope, $from, $to);

        // 7-day trend vs the previous 7 days.
        $last7 = $this->svc->totals($scope, $to->copy()->subDays(6), $to)['clicks'];
        $prev7 = $this->svc->totals($scope, $to->copy()->subDays(13), $to->copy()->subDays(7))['clicks'];
        $delta = $prev7 > 0 ? (int) round(($last7 - $prev7) / $prev7 * 100) : ($last7 > 0 ? null : 0);

        $linkAgg = $user->links()
            ->selectRaw('COUNT(*) total, COALESCE(SUM(is_active), 0) active, COALESCE(SUM(clicks), 0) clicks')
            ->first();

        $stats = [
            'total_links' => (int) $linkAgg->total,
            'active_links' => (int) $linkAgg->active,
            'total_clicks' => (int) $linkAgg->clicks,
            'qr_scans' => (int) $user->qrCodes()->sum('scans'),
            'clicks_30d' => $window['clicks'],
            'uniques_30d' => $window['uniques'],
        ];

        // Plan-usage meters (links / bio / QR / domains).
        $usage = collect([
            'max_links' => __('Links'),
            'max_bio' => __('Bio pages'),
            'max_qr' => __('QR codes'),
            'max_domains' => __('Custom domains'),
        ])->map(fn ($label, $key) => [
            'label' => $label,
            'used' => $this->gate->used($user, $key),
            'limit' => $this->gate->limit($user, $key),
            'percent' => $this->gate->percentUsed($user, $key),
        ])->values();

        return view('dashboard', [
            'stats' => $stats,
            'series' => $series,
            'clicksLast7' => $last7,
            'clicksDelta' => $delta,
            'topLinks' => $user->links()->where('clicks', '>', 0)->with('domain')->orderByDesc('clicks')->take(5)->get(),
            'recent' => $user->links()->with('domain')->latest()->take(5)->get(),
            'topCountries' => array_slice($this->svc->dimensionFull($scope, $from, $to, 'country'), 0, 6, true),
            'devices' => $this->svc->dimensions($scope, $from, $to)['device'] ?? [],
            'usage' => $usage,
            'plan' => $user->currentPlan(),
            'aiEnabled' => app(ClaudeClient::class)->enabled(),
            'aiCredits' => (int) $user->ai_credits,
            'insight' => data_get($user->settings, 'weekly_insight'),
        ]);
    }
}
