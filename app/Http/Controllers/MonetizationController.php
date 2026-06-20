<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Billing\PlanGate;
use Illuminate\Http\Request;

class MonetizationController extends Controller
{
    /** Number of ad slots a member can configure on their interstitial. */
    public const SLOTS = 3;

    public function index(Request $request)
    {
        $user = $request->user();

        return view('monetization.index', [
            'allowed' => app(PlanGate::class)->allows($user, 'ad_free'),
            'slots' => self::slotsFor($user),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        if (! app(PlanGate::class)->allows($user, 'ad_free')) {
            return back()->with('error', 'Running your own ads is available on paid plans.');
        }

        $data = $request->validate([
            'ad_slots' => ['nullable', 'array', 'max:'.self::SLOTS],
            'ad_slots.*' => ['nullable', 'string', 'max:20000'],
        ]);

        $slots = collect($data['ad_slots'] ?? [])
            ->map(fn ($c) => trim((string) $c))
            ->filter()
            ->take(self::SLOTS)
            ->values()
            ->all();

        $settings = $user->settings ?? [];
        $settings['ad_slots'] = $slots;
        unset($settings['ad_code']); // legacy single field, now folded into slots
        $user->update(['settings' => $settings]);

        return back()->with('status', 'Your ad slots were saved. They now show on your links.');
    }

    /**
     * A member's configured ad codes, back-compatible with the old single
     * `ad_code` setting and padded to SLOTS for the form.
     *
     * @return array<int, string>
     */
    public static function slotsFor(User $user): array
    {
        $slots = array_values(array_filter(array_map(
            fn ($c) => trim((string) $c),
            (array) data_get($user->settings, 'ad_slots', [])
        )));

        if (! $slots && ($legacy = trim((string) data_get($user->settings, 'ad_code', ''))) !== '') {
            $slots = [$legacy];
        }

        return array_pad($slots, self::SLOTS, '');
    }
}
