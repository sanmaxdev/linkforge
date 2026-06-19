<?php

namespace App\Http\Controllers;

use App\Services\Billing\PlanGate;
use Illuminate\Http\Request;

class MonetizationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return view('monetization.index', [
            'allowed' => app(PlanGate::class)->allows($user, 'ad_free'),
            'adCode' => (string) data_get($user->settings, 'ad_code', ''),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        if (! app(PlanGate::class)->allows($user, 'ad_free')) {
            return back()->with('error', 'Running your own ads is available on paid plans.');
        }

        $data = $request->validate(['ad_code' => ['nullable', 'string', 'max:20000']]);

        $settings = $user->settings ?? [];
        $settings['ad_code'] = trim((string) ($data['ad_code'] ?? ''));
        $user->update(['settings' => $settings]);

        return back()->with('status', 'Your ad code was saved. It now shows on your links.');
    }
}
