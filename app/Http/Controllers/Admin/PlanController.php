<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    /** Plan limits: key => human label. null value = unlimited. */
    public const LIMITS = [
        'max_links' => 'Links',
        'max_clicks' => 'Tracked clicks',
        'max_domains' => 'Custom domains',
        'max_team' => 'Team members',
        'max_qr' => 'QR codes',
        'max_bio' => 'Bio pages',
        'ai_credits' => 'AI credits / month',
    ];

    /** Plan feature flags: key => human label. */
    public const FEATURES = [
        'custom_domains' => 'Custom domains',
        'retargeting' => 'Retargeting pixels',
        'api' => 'API access',
        'deep_links' => 'Deep links',
        'team' => 'Team / workspaces',
        'white_label' => 'White label (hide branding)',
        'ad_free' => 'Ad-free + own ad code on links',
    ];

    public const INTERVALS = ['free' => 'Free', 'month' => 'Monthly', 'year' => 'Yearly', 'lifetime' => 'Lifetime'];

    public function index()
    {
        return view('admin.plans.index', [
            'plans' => Plan::withCount('users')->orderBy('sort')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.plans.form', [
            'plan' => new Plan([
                'currency' => config('linkforge.billing.currency', 'USD'),
                'interval' => 'month',
                'is_active' => true,
                'limits' => array_fill_keys(array_keys(self::LIMITS), 0), // start capped, not unlimited
                'features' => [],
                'sort' => (int) Plan::max('sort') + 1,
            ]),
            'limits' => self::LIMITS,
            'features' => self::FEATURES,
            'intervals' => self::INTERVALS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatePlan($request, null);
        $plan = Plan::create($data);
        AuditLog::record('plan.create', "Created plan {$plan->name}", $plan);

        return redirect()->route('admin.plans')->with('status', 'Plan created.');
    }

    public function edit(Plan $plan)
    {
        return view('admin.plans.form', [
            'plan' => $plan,
            'limits' => self::LIMITS,
            'features' => self::FEATURES,
            'intervals' => self::INTERVALS,
        ]);
    }

    public function update(Request $request, Plan $plan)
    {
        $plan->update($this->validatePlan($request, $plan->id));
        AuditLog::record('plan.update', "Updated plan {$plan->name}", $plan);

        return redirect()->route('admin.plans')->with('status', 'Plan saved.');
    }

    public function destroy(Plan $plan)
    {
        if ($plan->slug === 'free') {
            return back()->with('error', 'The free plan cannot be deleted; it is the default fallback.');
        }
        if ($plan->users()->exists()) {
            return back()->with('error', 'This plan has active users. Move them to another plan first.');
        }

        AuditLog::record('plan.delete', "Deleted plan {$plan->name}", $plan);
        $plan->delete();

        return back()->with('status', 'Plan deleted.');
    }

    /** @return array<string, mixed> */
    private function validatePlan(Request $request, ?int $ignoreId): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'slug' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9\-]+$/', Rule::unique('plans', 'slug')->ignore($ignoreId)],
            'price' => ['required', 'numeric', 'min:0', 'max:999999'],
            'currency' => ['required', 'string', 'size:3'],
            'interval' => ['required', Rule::in(array_keys(self::INTERVALS))],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'limits' => ['array'],
            'limits.*' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'unlimited' => ['array'],
            'features' => ['array'],
        ]);

        // Build the limits map: an "unlimited" toggle stores null; otherwise the integer.
        $unlimited = (array) $request->input('unlimited', []);
        $limits = [];
        foreach (self::LIMITS as $key => $_) {
            $limits[$key] = ! empty($unlimited[$key]) ? null : (int) ($validated['limits'][$key] ?? 0);
        }

        // Features: each flag is present (truthy) or absent.
        $features = [];
        $submitted = (array) $request->input('features', []);
        foreach (self::FEATURES as $key => $_) {
            $features[$key] = ! empty($submitted[$key]);
        }

        return [
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'price' => $validated['price'],
            'currency' => strtoupper($validated['currency']),
            'interval' => $validated['interval'],
            'limits' => $limits,
            'features' => $features,
            'is_active' => $request->boolean('is_active'),
            'sort' => (int) ($validated['sort'] ?? 0),
        ];
    }
}
