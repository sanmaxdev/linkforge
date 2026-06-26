<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Generates a short, plain-language weekly performance insight for a user by
 * comparing the last 7 days against the prior 7 and highlighting top sources.
 * The model only ever sees the aggregated figures, never raw data.
 */
class InsightWriter
{
    public function __construct(
        private ClaudeClient $claude,
        private AnalyticsService $svc,
    ) {}

    /**
     * Build the comparison figures for a user. Returns null when there is no
     * activity worth reporting (so we don't spend an API call on dead accounts).
     *
     * @return array<string, mixed>|null
     */
    public function figures(User $user): ?array
    {
        $to = Carbon::today();
        $thisFrom = $to->copy()->subDays(6);
        $prevTo = $thisFrom->copy()->subDay();
        $prevFrom = $prevTo->copy()->subDays(6);

        $scope = fn ($q) => $q->whereIn('link_id', DB::table('links')->where('user_id', $user->id)->select('id'));

        $current = $this->svc->totals($scope, $thisFrom, $to);
        if ($current['clicks'] <= 0) {
            return null;
        }

        $previous = $this->svc->totals($scope, $prevFrom, $prevTo);
        $dims = $this->svc->dimensions($scope, $thisFrom, $to, 5);

        return [
            'this_week' => $current,
            'previous_week' => $previous,
            'top_countries' => $dims['country'] ?? [],
            'top_referrers' => $dims['referer'] ?? [],
            'top_devices' => $dims['device'] ?? [],
        ];
    }

    /** Generate the insight text from precomputed figures. */
    public function write(array $figures): string
    {
        $system = 'You write a brief weekly performance insight for a link-analytics dashboard. '
            .'Use 2 to 3 sentences. Compare this week to last week, call out the single most '
            .'notable change and the top traffic source. Be specific with numbers, plain and '
            .'encouraging. No preamble, no markdown, no em dashes.';

        return $this->claude->text($system, 'Figures (JSON): '.json_encode($figures), 400);
    }

    /**
     * Compute + generate + persist the insight onto the user's settings.
     * Returns the stored payload, or null if there was nothing to report.
     *
     * @return array<string, mixed>|null
     */
    public function generateFor(User $user): ?array
    {
        $figures = $this->figures($user);
        if ($figures === null) {
            return null;
        }

        $payload = [
            'text' => $this->write($figures),
            'clicks' => $figures['this_week']['clicks'],
            'generated_at' => now()->toIso8601String(),
        ];

        $settings = (array) ($user->settings ?? []);
        $settings['weekly_insight'] = $payload;
        $user->forceFill(['settings' => $settings])->save();

        return $payload;
    }
}
