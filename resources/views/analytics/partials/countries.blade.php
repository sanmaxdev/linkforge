@props(['countries' => [], 'countryMax' => 0, 'cities' => []])
@php
    $names = require resource_path('data/countries.php');
    $total = array_sum($countries);
    $top = array_slice($countries, 0, 8, true); // controller sorts desc
    $countryCount = count(array_filter($countries));

    $cityTotal = array_sum($cities);
    $cityMax = $cities ? max($cities) : 0;
    $topCities = array_slice($cities, 0, 8, true);
@endphp

<div class="mt-5 grid items-start gap-5 lg:grid-cols-[1fr_1.3fr]">
    {{-- Left: country + city breakdowns --}}
    <div class="space-y-5">
        <div class="lf-card p-5">
            <h3 class="text-sm font-semibold text-slate-900">Top countries</h3>
            @if (empty($top))
                <div class="mt-3 flex items-start gap-2 text-sm text-slate-400">
                    <svg class="mt-0.5 h-4 w-4 flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>
                    <span>No country data yet. Geo needs a source: put the site behind Cloudflare, or drop a GeoLite2 / DB-IP <code class="text-xs">.mmdb</code> file into <code class="text-xs">storage/app/geoip/</code> (no config needed).</span>
                </div>
            @else
                <div class="mt-4 space-y-2.5">
                    @foreach ($top as $code => $clicks)
                        @php $pct = $total > 0 ? round($clicks / $total * 100, 1) : 0; @endphp
                        <div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="flex min-w-0 items-center gap-2 text-slate-600">
                                    <img src="{{ asset('vendor/flags/'.strtolower($code).'.svg') }}" alt="" loading="lazy"
                                         class="h-3.5 w-5 shrink-0 rounded-sm object-cover ring-1 ring-slate-200/70"
                                         onerror="this.style.visibility='hidden'">
                                    <span class="truncate">{{ $names[$code] ?? $code }}</span>
                                </span>
                                <span class="shrink-0 pl-3 text-slate-400 tabular-nums">{{ number_format($clicks) }} ({{ $pct }}%)</span>
                            </div>
                            <div class="mt-1 h-1.5 rounded-full bg-slate-100">
                                <div class="h-1.5 rounded-full bg-brand-500" style="width: {{ max(3, round($clicks / max(1, $countryMax) * 100)) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="lf-card p-5">
            <h3 class="text-sm font-semibold text-slate-900">Top cities</h3>
            @if (empty($topCities))
                <div class="mt-3 flex items-start gap-2 text-sm text-slate-400">
                    <svg class="mt-0.5 h-4 w-4 flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-5.6-7-11a7 7 0 1 1 14 0c0 5.4-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                    <span>No city data yet. City detail appears when a city-level GeoIP source is available (Cloudflare visitor-location headers, or a GeoLite2-City database).</span>
                </div>
            @else
                <div class="mt-4 space-y-2.5">
                    @foreach ($topCities as $city => $clicks)
                        @php $pct = $cityTotal > 0 ? round($clicks / $cityTotal * 100, 1) : 0; @endphp
                        <div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="flex min-w-0 items-center gap-2 text-slate-600">
                                    <svg class="h-4 w-4 flex-none text-brand-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-5.6-7-11a7 7 0 1 1 14 0c0 5.4-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                                    <span class="truncate">{{ $city }}</span>
                                </span>
                                <span class="shrink-0 pl-3 text-slate-400 tabular-nums">{{ number_format($clicks) }} ({{ $pct }}%)</span>
                            </div>
                            <div class="mt-1 h-1.5 rounded-full bg-slate-100">
                                <div class="h-1.5 rounded-full bg-spark-500" style="width: {{ max(3, round($clicks / max(1, $cityMax) * 100)) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Right: world map (medium) --}}
    <div class="lf-card p-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-semibold text-slate-900">Clicks by country</h3>
            @if ($total > 0)
                <span class="text-xs text-slate-400">{{ number_format($countryCount) }} {{ \Illuminate\Support\Str::plural('country', $countryCount) }} &middot; {{ number_format($total) }} clicks</span>
            @endif
        </div>
        <div class="mt-4">
            @include('analytics.partials.world-map', ['countries' => $countries, 'max' => $countryMax])
        </div>
    </div>
</div>
