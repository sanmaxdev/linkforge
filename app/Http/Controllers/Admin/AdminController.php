<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AbuseReport;
use App\Models\AuditLog;
use App\Models\Link;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Demo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    /** Distinct slice colours for the plan-distribution chart. */
    private const SLICE_COLORS = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#14b8a6', '#f97316', '#64748b', '#0ea5e9'];

    public function dashboard()
    {
        $active = Subscription::whereIn('status', ['active', 'trialing'])->with('plan')->get();
        $mrr = $active->sum(fn (Subscription $s) => match ($s->plan?->interval) {
            'month' => (float) $s->plan->price,
            'year' => (float) $s->plan->price / 12,
            default => 0.0,
        });

        // Plan distribution (users per plan, including the free/no-plan bucket).
        $planCounts = User::selectRaw('plan_id, COUNT(*) as c')->groupBy('plan_id')->pluck('c', 'plan_id');
        $plans = Plan::orderBy('sort')->get();
        $slices = [];
        $i = 0;
        foreach ($plans as $plan) {
            $count = (int) ($planCounts[$plan->id] ?? 0);
            if ($count > 0) {
                $slices[] = ['label' => $plan->name, 'value' => $count, 'color' => self::SLICE_COLORS[$i % count(self::SLICE_COLORS)]];
            }
            $i++;
        }
        if (($free = (int) ($planCounts[null] ?? 0)) > 0) {
            $slices[] = ['label' => 'Free (no plan)', 'value' => $free, 'color' => '#cbd5e1'];
        }

        return view('admin.dashboard', [
            'stats' => [
                'users' => User::count(),
                'links' => Link::count(),
                'clicks' => (int) Link::sum('clicks'),
                'mrr' => $mrr,
                'open_reports' => AbuseReport::where('status', 'open')->count(),
                'active_subs' => $active->count(),
            ],
            'currency' => config('linkforge.billing.currency', 'USD'),
            'userSeries' => $this->dailySeries(User::query(), 'created_at'),
            'clickSeries' => $this->dailySeries(DB::table('clicks'), 'created_at'),
            'planSlices' => $slices,
            'topLinks' => Link::with('user')->orderByDesc('clicks')->take(8)->get(),
            'recentUsers' => User::with('plan')->latest()->take(6)->get(),
        ]);
    }

    /**
     * Build a zero-filled 30-day daily count series shaped for the area-chart
     * partial: a list of ['day' => Y-m-d, 'clicks' => int].
     */
    private function dailySeries($query, string $column): array
    {
        $start = now()->subDays(29)->startOfDay();
        $counts = $query->where($column, '>=', $start)
            ->selectRaw("DATE({$column}) as d, COUNT(*) as c")
            ->groupBy('d')
            ->pluck('c', 'd');

        $series = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $series[] = ['day' => $day, 'clicks' => (int) ($counts[$day] ?? 0)];
        }

        return $series;
    }

    public function links(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $links = Link::with('user')
            ->when($q !== '', fn ($qq) => $qq->where(fn ($w) => $w->where('alias', 'like', "%{$q}%")->orWhere('long_url', 'like', "%{$q}%")))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.links', ['links' => $links, 'q' => $q]);
    }

    public function updateLink(Request $request, Link $link)
    {
        $action = $request->input('action');
        match ($action) {
            'block' => $link->update(['safety_status' => 'blocked', 'is_active' => false]),
            'unblock' => $link->update(['safety_status' => 'safe', 'is_active' => true]),
            'delete' => $link->delete(),
            default => null,
        };

        Link::forgetCache($link->domain_id, $link->alias);
        AuditLog::record("link.{$action}", "/{$link->alias}", $link);

        return back()->with('status', 'Link updated.');
    }

    public function reports()
    {
        return view('admin.reports', ['reports' => AbuseReport::with('link')->latest()->paginate(20)]);
    }

    public function updateReport(Request $request, AbuseReport $report)
    {
        if ($request->input('action') === 'block' && $report->link) {
            $report->link->update(['safety_status' => 'blocked', 'is_active' => false]);
            Link::forgetCache($report->link->domain_id, $report->link->alias);
            $report->update(['status' => 'actioned']);
            AuditLog::record('report.block', "Report #{$report->id}", $report);
        } elseif ($request->input('action') === 'dismiss') {
            $report->update(['status' => 'dismissed']);
            AuditLog::record('report.dismiss', "Report #{$report->id}", $report);
        }

        return back()->with('status', 'Report updated.');
    }

    public function audit()
    {
        return view('admin.audit', [
            'logs' => AuditLog::with('user')->latest()->paginate(40),
        ]);
    }

    /** Run a safe maintenance command from the admin panel (no shell/cron needed). */
    public function maintenance(Request $request)
    {
        if (Demo::enabled()) {
            return back()->with('error', 'Maintenance tools are disabled in demo mode.');
        }

        $actions = [
            'clear-cache' => 'Caches cleared',
            'run-rollup' => 'Analytics rollup finished',
            'run-queue' => 'Queued jobs processed',
        ];
        $action = (string) $request->input('action');
        abort_unless(isset($actions[$action]), 400);

        try {
            match ($action) {
                'clear-cache' => array_map(fn ($c) => Artisan::call($c), ['cache:clear', 'config:clear', 'view:clear', 'route:clear']),
                'run-rollup' => Artisan::call('clicks:rollup'),
                'run-queue' => Artisan::call('queue:work', ['--stop-when-empty' => true, '--max-time' => 20]),
            };
            AuditLog::record('maintenance.'.$action, $actions[$action]);

            return back()->with('status', $actions[$action].'.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed: '.Str::limit($e->getMessage(), 160));
        }
    }
}
