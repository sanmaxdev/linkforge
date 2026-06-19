<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * An operator-owned ad unit. Shown to FREE users (on link interstitials and in
 * the dashboard) so the operator monetizes the free tier. Premium / ad-free
 * users never see these (they run their own ad code instead).
 */
class Advertisement extends Model
{
    public const PLACEMENTS = [
        'interstitial' => 'Link interstitial (redirect page)',
        'dashboard' => 'Dashboard banner (top)',
        'sidebar' => 'Sidebar (under the menu)',
        'popup' => 'Popup (dismissible, once per session)',
    ];

    protected $fillable = [
        'name', 'placement', 'code', 'image_path', 'target_url', 'is_active', 'impressions', 'clicks', 'sort',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** The active ad for a placement (lowest sort wins), cached briefly for the hot redirect path. */
    public static function activeFor(string $placement): ?self
    {
        return Cache::remember("lf:ad:{$placement}", 60, function () use ($placement) {
            return static::query()
                ->where('placement', $placement)
                ->where('is_active', true)
                ->orderBy('sort')
                ->orderByDesc('id')
                ->first();
        });
    }

    public static function forgetCache(): void
    {
        foreach (array_keys(self::PLACEMENTS) as $placement) {
            Cache::forget("lf:ad:{$placement}");
        }
    }

    /** Increment the impression counter without disturbing updated_at. */
    public function recordImpression(): void
    {
        static::withoutTimestamps(fn () => $this->newQuery()->whereKey($this->id)->increment('impressions'));
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? asset($this->image_path) : null;
    }
}
