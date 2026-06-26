<?php

namespace App\Http\Controllers;

use App\Models\BioPage;
use App\Models\Campaign;
use App\Models\Link;
use App\Models\QrCode;
use App\Services\Ai\ClaudeClient;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\BioAnalytics;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function __construct(private AnalyticsService $svc) {}

    public function index(Request $request)
    {
        [$from, $to, $range] = $this->range($request);
        $user = $request->user();
        $source = in_array($request->query('source'), ['bio', 'qr'], true) ? $request->query('source') : 'links';

        $common = [
            'range' => $range, 'from' => $from, 'to' => $to,
            'aiEnabled' => app(ClaudeClient::class)->enabled(),
            'source' => $source,
            'exportUrl' => route('analytics.export', $this->exportParams($range, $from, $to) + ['source' => $source]),
        ];

        if ($source === 'bio') {
            $ids = $user->bioPages()->pluck('id');
            $scope = fn ($q) => $q->whereIn('bio_page_id', $ids);
            $bio = app(BioAnalytics::class);
            $totals = $bio->totals($scope, $from, $to);
            $countries = $bio->countries($scope, $from, $to);

            return view('analytics.index', $common + [
                'series' => $bio->series($scope, $from, $to),
                'totals' => ['clicks' => $totals['clicks'], 'uniques' => $totals['uniques'], 'bots' => $totals['bots']],
                'dims' => $bio->dimensions($scope, $from, $to),
                'countries' => $countries,
                'countryMax' => $countries ? max($countries) : 0,
                'totalsCards' => [
                    ['label' => 'Page views', 'value' => $totals['views']],
                    ['label' => 'Link clicks', 'value' => $totals['clicks']],
                    ['label' => 'Unique visitors', 'value' => $totals['uniques']],
                ],
                'seriesTitle' => 'Views over time',
            ]);
        }

        if ($source === 'qr') {
            $linkIds = $user->qrCodes()->whereNotNull('link_id')->pluck('link_id');
            $scope = fn ($q) => $q->whereIn('link_id', $linkIds);
            $p = $this->payload($scope, $from, $to, $range);

            return view('analytics.index', $common + $p + [
                'totalsCards' => [
                    ['label' => 'QR scans', 'value' => $p['totals']['clicks']],
                    ['label' => 'Unique scanners', 'value' => $p['totals']['uniques']],
                    ['label' => 'Bot scans', 'value' => $p['totals']['bots']],
                ],
                'seriesTitle' => 'Scans over time',
            ]);
        }

        $scope = $this->accountScope($user->id);

        return view('analytics.index', $common + $this->payload($scope, $from, $to, $range));
    }

    public function show(Request $request, Link $link)
    {
        abort_unless((int) $link->user_id === (int) $request->user()->id, 403);
        $link->load('domain');

        [$from, $to, $range] = $this->range($request);
        $scope = fn ($q) => $q->where('link_id', $link->id);

        return view('analytics.show', $this->payload($scope, $from, $to, $range) + [
            'link' => $link,
            'aiEnabled' => app(ClaudeClient::class)->enabled(),
            'exportUrl' => route('links.stats.export', ['link' => $link->id] + $this->exportParams($range, $from, $to)),
        ]);
    }

    /** Per-item analytics for a single QR code (scoped to its tracked link). */
    public function qrShow(Request $request, QrCode $qr)
    {
        abort_unless((int) $qr->user_id === (int) $request->user()->id, 403);
        [$from, $to, $range] = $this->range($request);

        $scope = $qr->link_id ? fn ($q) => $q->where('link_id', $qr->link_id) : fn ($q) => $q->whereRaw('1 = 0');
        $p = $this->payload($scope, $from, $to, $range);

        return view('analytics.item', $p + [
            'source' => null,
            'pageTitle' => 'QR analytics',
            'backUrl' => route('qr.index'),
            'itemTitle' => $qr->name ?: 'QR #'.$qr->id,
            'itemSubtitle' => ucfirst($qr->type).($qr->is_dynamic ? ' · dynamic' : ' · static'),
            'totalsCards' => [
                ['label' => 'QR scans', 'value' => $qr->link_id ? $p['totals']['clicks'] : (int) $qr->scans],
                ['label' => 'Unique scanners', 'value' => $p['totals']['uniques']],
                ['label' => 'Bot scans', 'value' => $p['totals']['bots']],
            ],
            'seriesTitle' => 'Scans over time',
            'notice' => $qr->link_id ? null : 'Static QR codes record a scan count only. Use a dynamic QR for scan-by-scan analytics.',
            'exportUrl' => route('qr.stats.export', ['qr' => $qr->id] + $this->exportParams($range, $from, $to)),
        ]);
    }

    /** Per-item analytics for a single bio page (scoped to its events). */
    public function bioShow(Request $request, BioPage $bioPage)
    {
        abort_unless((int) $bioPage->user_id === (int) $request->user()->id, 403);
        [$from, $to, $range] = $this->range($request);

        $bio = app(BioAnalytics::class);
        $scope = fn ($q) => $q->where('bio_page_id', $bioPage->id);
        $totals = $bio->totals($scope, $from, $to);
        $countries = $bio->countries($scope, $from, $to);

        return view('analytics.item', [
            'source' => null,
            'pageTitle' => 'Bio page analytics',
            'backUrl' => route('bio.index'),
            'itemTitle' => '@'.$bioPage->slug,
            'itemSubtitle' => $bioPage->is_published ? url('/'.$bioPage->slug) : 'Draft (not published)',
            'itemHref' => $bioPage->is_published ? url('/'.$bioPage->slug) : null,
            'series' => $bio->series($scope, $from, $to),
            'totals' => ['clicks' => $totals['clicks'], 'uniques' => $totals['uniques'], 'bots' => $totals['bots']],
            'dims' => $bio->dimensions($scope, $from, $to),
            'countries' => $countries,
            'countryMax' => $countries ? max($countries) : 0,
            'range' => $range, 'from' => $from, 'to' => $to,
            'totalsCards' => [
                ['label' => 'Page views', 'value' => $totals['views']],
                ['label' => 'Link clicks', 'value' => $totals['clicks']],
                ['label' => 'Unique visitors', 'value' => $totals['uniques']],
            ],
            'seriesTitle' => 'Views over time',
            'exportUrl' => route('bio.stats.export', ['bioPage' => $bioPage->id] + $this->exportParams($range, $from, $to)),
        ]);
    }

    /** Aggregated analytics across every link in a campaign. */
    public function campaignShow(Request $request, Campaign $campaign)
    {
        abort_unless((int) $campaign->user_id === (int) $request->user()->id, 403);
        [$from, $to, $range] = $this->range($request);

        $linkIds = $campaign->links()->pluck('id');
        $scope = $linkIds->isNotEmpty() ? fn ($q) => $q->whereIn('link_id', $linkIds) : fn ($q) => $q->whereRaw('1 = 0');
        $p = $this->payload($scope, $from, $to, $range);

        return view('analytics.item', $p + [
            'source' => null,
            'pageTitle' => 'Campaign analytics',
            'backUrl' => route('campaigns.index'),
            'itemTitle' => $campaign->name,
            'itemSubtitle' => $linkIds->count().' '.Str::plural('link', $linkIds->count()),
            'totalsCards' => [
                ['label' => 'Total clicks', 'value' => $p['totals']['clicks']],
                ['label' => 'Unique visitors', 'value' => $p['totals']['uniques']],
                ['label' => 'Links', 'value' => $linkIds->count()],
            ],
            'seriesTitle' => 'Clicks over time',
            'exportUrl' => route('campaigns.stats.export', ['campaign' => $campaign->id] + $this->exportParams($range, $from, $to)),
        ]);
    }

    public function exportCampaign(Request $request, Campaign $campaign): StreamedResponse
    {
        abort_unless((int) $campaign->user_id === (int) $request->user()->id, 403);
        [$from, $to] = $this->range($request);
        $linkIds = $campaign->links()->pluck('id');
        $scope = $linkIds->isNotEmpty() ? fn ($q) => $q->whereIn('link_id', $linkIds) : fn ($q) => $q->whereRaw('1 = 0');

        return $this->streamCsv($this->svc->series($scope, $from, $to), 'campaign-'.$campaign->id);
    }

    public function exportQr(Request $request, QrCode $qr): StreamedResponse
    {
        abort_unless((int) $qr->user_id === (int) $request->user()->id, 403);
        [$from, $to] = $this->range($request);
        $scope = $qr->link_id ? fn ($q) => $q->where('link_id', $qr->link_id) : fn ($q) => $q->whereRaw('1 = 0');

        return $this->streamCsv($this->svc->series($scope, $from, $to), 'qr-'.$qr->id);
    }

    public function exportBio(Request $request, BioPage $bioPage): StreamedResponse
    {
        abort_unless((int) $bioPage->user_id === (int) $request->user()->id, 403);
        [$from, $to] = $this->range($request);
        $series = app(BioAnalytics::class)->series(fn ($q) => $q->where('bio_page_id', $bioPage->id), $from, $to);

        return $this->streamCsv($series, 'bio-'.$bioPage->slug);
    }

    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $user = $request->user();
        $source = $request->query('source');

        if ($source === 'bio') {
            $ids = $user->bioPages()->pluck('id');
            $series = app(BioAnalytics::class)->series(fn ($q) => $q->whereIn('bio_page_id', $ids), $from, $to);

            return $this->streamCsv($series, 'bio-analytics');
        }

        $scope = $source === 'qr'
            ? fn ($q) => $q->whereIn('link_id', $user->qrCodes()->whereNotNull('link_id')->pluck('link_id'))
            : $this->accountScope($user->id);

        return $this->streamCsv($this->svc->series($scope, $from, $to), $source === 'qr' ? 'qr-analytics' : 'analytics');
    }

    public function exportLink(Request $request, Link $link): StreamedResponse
    {
        abort_unless((int) $link->user_id === (int) $request->user()->id, 403);
        [$from, $to] = $this->range($request);
        $scope = fn ($q) => $q->where('link_id', $link->id);

        return $this->streamCsv($this->svc->series($scope, $from, $to), 'link-'.$link->alias);
    }

    private function accountScope(int $userId): Closure
    {
        return fn ($q) => $q->whereIn('link_id', DB::table('links')->where('user_id', $userId)->select('id'));
    }

    /** Query params that reproduce the active range on the export endpoint. */
    private function exportParams(int|string $range, Carbon $from, Carbon $to): array
    {
        return $range === 'custom'
            ? ['from' => $from->toDateString(), 'to' => $to->toDateString()]
            : ['range' => $range];
    }

    private function payload(Closure $scope, Carbon $from, Carbon $to, int|string $range): array
    {
        $countries = $this->svc->dimensionFull($scope, $from, $to, 'country');
        $cities = $this->svc->dimensionFull($scope, $from, $to, 'city');

        return [
            'series' => $this->svc->series($scope, $from, $to),
            'totals' => $this->svc->totals($scope, $from, $to),
            'dims' => $this->svc->dimensions($scope, $from, $to),
            'countries' => $countries,
            'countryMax' => $countries ? max($countries) : 0,
            'cities' => array_slice($cities, 0, 12, true),
            'range' => $range,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * Resolve the active window. A custom from/to pair (capped at 366 days,
     * clamped to today) takes precedence over the 7/30/90 presets.
     *
     * @return array{0:Carbon,1:Carbon,2:int|string}
     */
    private function range(Request $request): array
    {
        $fromQ = (string) $request->query('from', '');
        $toQ = (string) $request->query('to', '');

        if ($fromQ !== '' && $toQ !== '') {
            try {
                $from = Carbon::parse($fromQ)->startOfDay();
                $to = Carbon::parse($toQ)->startOfDay();

                if ($from->gt($to)) {
                    [$from, $to] = [$to, $from];
                }
                if ($to->gt(Carbon::today())) {
                    $to = Carbon::today();
                }
                if ($from->lt($to->copy()->subDays(365))) {
                    $from = $to->copy()->subDays(365);
                }

                return [$from, $to, 'custom'];
            } catch (\Throwable) {
                // Invalid dates: fall through to the presets.
            }
        }

        $range = (int) $request->query('range', 30);
        if (! in_array($range, [7, 30, 90], true)) {
            $range = 30;
        }

        $to = Carbon::today();
        $from = $to->copy()->subDays($range - 1);

        return [$from, $to, $range];
    }

    private function streamCsv(array $series, string $name): StreamedResponse
    {
        $filename = $name.'-'.now()->toDateString().'.csv';

        return response()->streamDownload(function () use ($series) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['date', 'clicks', 'unique_visitors']);
            foreach ($series as $row) {
                fputcsv($out, [$row['day'], $row['clicks'], $row['uniques']]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
