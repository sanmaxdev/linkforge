<?php

namespace App\Services\Billing;

use App\Models\User;

/**
 * Central place for plan limit + feature checks. Limits with a null value mean
 * unlimited. Usage is counted live from the owning relations.
 */
class PlanGate
{
    /** Numeric limit for a key (null = unlimited / not set). */
    public function limit(User $user, string $key): ?int
    {
        return $user->currentPlan()?->limit($key);
    }

    /** How many of a resource the user is currently using. */
    public function used(User $user, string $key): int
    {
        return match ($key) {
            'max_links' => $user->links()->count(),
            'max_domains' => $user->domains()->count(),
            'max_qr' => $user->qrCodes()->count(),
            'max_bio' => $user->bioPages()->count(),
            default => 0,
        };
    }

    /** Remaining headroom for a limit (null = unlimited). */
    public function remaining(User $user, string $key): ?int
    {
        $limit = $this->limit($user, $key);
        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $this->used($user, $key));
    }

    /** May the user create one more of this resource? */
    public function canCreate(User $user, string $key): bool
    {
        $remaining = $this->remaining($user, $key);

        return $remaining === null || $remaining > 0;
    }

    /** Is a feature flag enabled on the user's plan? */
    public function allows(User $user, string $feature): bool
    {
        return (bool) $user->currentPlan()?->allows($feature);
    }

    /** Percent of a limit used (0-100), or null for unlimited. */
    public function percentUsed(User $user, string $key): ?int
    {
        $limit = $this->limit($user, $key);
        if (! $limit) {
            return null;
        }

        return (int) min(100, round($this->used($user, $key) / $limit * 100));
    }
}
