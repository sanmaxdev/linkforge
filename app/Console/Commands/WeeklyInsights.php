<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Ai\AiCredits;
use App\Services\Ai\ClaudeClient;
use App\Services\Ai\InsightWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WeeklyInsights extends Command
{
    protected $signature = 'ai:weekly-insights';

    protected $description = 'Generate an AI weekly performance insight for each active account.';

    public function handle(ClaudeClient $claude, InsightWriter $writer, AiCredits $credits): int
    {
        if (! $claude->enabled()) {
            $this->warn('AI layer not configured (no provider key). Skipping.');

            return self::SUCCESS;
        }

        // The insight is metered like the other AI actions, so it isn't an unbounded
        // operator cost: it charges the user's AI credits. Set the cost to 0 in
        // Settings -> AI to make it free for everyone (operator-funded) instead.
        $cost = (int) config('linkforge.ai.cost.insight', 1);

        // Only active accounts: those whose links saw clicks in the last 14 days.
        $activeUserIds = DB::table('links')
            ->where('last_click_at', '>=', now()->subDays(14))
            ->distinct()
            ->pluck('user_id');

        $generated = 0;

        User::whereIn('id', $activeUserIds)->each(function (User $user) use ($writer, $credits, $cost, &$generated) {
            // Reserve the credit up front (atomic); skip accounts that are out of credits.
            if ($cost > 0 && ! $credits->charge($user, $cost)) {
                return;
            }

            try {
                if ($writer->generateFor($user) !== null) {
                    $generated++;
                } elseif ($cost > 0) {
                    $credits->refund($user, $cost); // nothing worth reporting this week
                }
            } catch (\Throwable $e) {
                if ($cost > 0) {
                    $credits->refund($user, $cost);
                }
                $this->error("User {$user->id}: ".$e->getMessage());
            }
        });

        $this->info("Generated {$generated} weekly insight(s).");

        return self::SUCCESS;
    }
}
