<x-app-layout title="Dashboard">
    <x-slot:header>{{ __('Dashboard') }}</x-slot:header>

    <x-ad placement="dashboard" class="mb-6" />

    {{-- Header --}}
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-xl font-semibold text-slate-900">{{ __('Welcome back, :name', ['name' => explode(' ', trim(auth()->user()->name))[0]]) }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __("Here's what's happening with your links.") }}</p>
        </div>
        <div class="flex shrink-0 items-center gap-3">
            @if ($aiEnabled ?? false)
                <span class="inline-flex items-center gap-1.5 rounded-full border border-spark-200 bg-spark-50 px-3 py-1.5 text-xs font-semibold text-spark-700" title="{{ __('AI credits remaining') }}">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.9 4.8L18.7 9l-4.8 1.9L12 15.7 10.1 10.9 5.3 9l4.8-1.2L12 3z"/></svg>
                    {{ __(':count AI credits', ['count' => number_format($aiCredits)]) }}
                </span>
            @endif
            <a href="{{ route('links.create') }}" class="hidden shrink-0 items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 sm:inline-flex">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                {{ __('New link') }}
            </a>
        </div>
    </div>

    @if ($stats['total_links'] === 0)
        {{-- Empty state --}}
        <div class="lf-card flex flex-col items-center justify-center px-6 py-16 text-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-2xl text-white" style="background-image:linear-gradient(135deg,var(--color-brand-500),var(--color-brand-700))">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
            </span>
            <h3 class="mt-5 text-lg font-semibold text-slate-900">{{ __('Forge your first short link') }}</h3>
            <p class="mt-1.5 max-w-md text-sm text-slate-500">{{ __('Turn any long URL into a fast, branded, trackable link. Analytics, QR codes and safety scanning come built in.') }}</p>
            <a href="{{ route('links.create') }}" class="mt-6 inline-flex items-center justify-center gap-2 rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                {{ __('Create a short link') }}
            </a>
        </div>
    @else
        {{-- KPI cards --}}
        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            @php
                $cards = [
                    ['label' => __('Total clicks'), 'value' => $stats['total_clicks'], 'trend' => true, 'path' => 'M3 3v18h18M7 14l3-3 3 3 5-6'],
                    ['label' => __('Unique visitors'), 'value' => $stats['uniques_30d'], 'hint' => __('Last 30 days'), 'path' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75'],
                    ['label' => __('Total links'), 'value' => $stats['total_links'], 'hint' => __(':count active', ['count' => number_format($stats['active_links'])]), 'path' => 'M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757'],
                    ['label' => __('QR scans'), 'value' => $stats['qr_scans'], 'hint' => __('Dynamic QR codes'), 'path' => 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h.01M20 14v.01M14 20h6v-6'],
                ];
            @endphp
            @foreach ($cards as $c)
                <div class="lf-card p-5">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-500">{{ $c['label'] }}</span>
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $c['path'] }}"/></svg>
                        </span>
                    </div>
                    <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-900">{{ number_format($c['value']) }}</p>
                    @if (! empty($c['trend']))
                        <p class="mt-1.5 flex items-center gap-1.5 text-xs">
                            @if ($clicksDelta === null)
                                <span class="inline-flex items-center gap-0.5 rounded-full bg-brand-50 px-1.5 py-0.5 font-semibold text-brand-700">{{ __('New') }}</span>
                                <span class="text-slate-400">{{ __(':count in last 7 days', ['count' => number_format($clicksLast7)]) }}</span>
                            @elseif ($clicksDelta > 0)
                                <span class="inline-flex items-center gap-0.5 rounded-full bg-brand-50 px-1.5 py-0.5 font-semibold text-brand-700"><svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M9 7h8v8"/></svg>{{ $clicksDelta }}%</span>
                                <span class="text-slate-400">{{ __('vs previous 7 days') }}</span>
                            @elseif ($clicksDelta < 0)
                                <span class="inline-flex items-center gap-0.5 rounded-full bg-red-50 px-1.5 py-0.5 font-semibold text-red-600"><svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 7 7 17M15 17H7V9"/></svg>{{ abs($clicksDelta) }}%</span>
                                <span class="text-slate-400">{{ __('vs previous 7 days') }}</span>
                            @else
                                <span class="text-slate-400">{{ __('No change vs previous 7 days') }}</span>
                            @endif
                        </p>
                    @else
                        <p class="mt-1.5 text-xs text-slate-400">{{ $c['hint'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Clicks chart + plan usage --}}
        <div class="mt-5 grid gap-5 lg:grid-cols-3">
            <div class="lf-card p-5 lg:col-span-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-slate-900">{{ __('Clicks over time') }}</h3>
                    <span class="text-xs text-slate-400">{{ __(':clicks clicks · :uniques unique · last 30 days', ['clicks' => number_format($stats['clicks_30d']), 'uniques' => number_format($stats['uniques_30d'])]) }}</span>
                </div>
                <div class="mt-4">
                    @include('analytics.partials.area-chart', ['series' => $series])
                </div>
            </div>

            <div class="lf-card p-5">
                @php $isFree = ! $plan || (float) $plan->price <= 0; @endphp
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-900">{{ __('Plan usage') }}</h3>
                    <span class="rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">{{ $plan->name ?? __('Free') }}</span>
                </div>
                <div class="mt-4 space-y-3.5">
                    @foreach ($usage as $u)
                        @php
                            $unlimited = $u['limit'] === null;
                            $pct = (int) ($u['percent'] ?? 0);
                            $bar = $pct >= 100 ? 'bg-red-500' : ($pct >= 80 ? 'bg-amber-500' : 'bg-brand-500');
                        @endphp
                        <div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-600">{{ $u['label'] }}</span>
                                <span class="text-slate-400 tabular-nums">{{ number_format($u['used']) }} / {{ $unlimited ? '∞' : number_format($u['limit']) }}</span>
                            </div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full {{ $unlimited ? 'bg-brand-300' : $bar }}" style="width: {{ $unlimited ? 6 : min(100, max(3, $pct)) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <a href="{{ route('billing.index') }}" class="mt-5 inline-flex w-full items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold transition {{ $isFree ? 'bg-brand-600 text-white hover:bg-brand-700' : 'border border-slate-300 text-slate-700 hover:bg-slate-50' }}">
                    {{ $isFree ? __('Upgrade plan') : __('Manage plan') }}
                </a>
            </div>
        </div>

        {{-- Top links + geo/devices --}}
        @php
            $names = require resource_path('data/countries.php');
            $links = $topLinks->isNotEmpty() ? $topLinks : $recent;
            $linksTitle = $topLinks->isNotEmpty() ? __('Top performing links') : __('Recent links');
            $linkMax = $topLinks->isNotEmpty() ? max(1, (int) $topLinks->max('clicks')) : 1;
            $countryTotal = array_sum($topCountries);
            $countryMax = $topCountries ? max($topCountries) : 1;
            $deviceTotal = collect($devices)->sum('clicks');
        @endphp
        <div class="mt-5 grid items-start gap-5 lg:grid-cols-2">
            {{-- Links --}}
            <div class="lf-card p-5">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-900">{{ $linksTitle }}</h3>
                    <a href="{{ route('links.index') }}" class="text-sm font-medium text-brand-600 transition hover:text-brand-700">{{ __('View all') }}</a>
                </div>
                <div class="mt-4 space-y-3">
                    @foreach ($links as $link)
                        <div>
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <a href="{{ request()->getScheme().'://'.$link->shortUrl() }}" target="_blank" rel="noopener" class="min-w-0 flex-1 truncate font-medium text-brand-700 hover:underline">{{ $link->shortUrl() }}</a>
                                <span class="shrink-0 font-semibold tabular-nums text-slate-900">{{ number_format($link->clicks) }} <span class="font-normal text-slate-400">{{ __('clicks') }}</span></span>
                            </div>
                            <div class="truncate text-xs text-slate-400">{{ $link->long_url }}</div>
                            @if ($topLinks->isNotEmpty())
                                <div class="mt-1.5 h-1 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-brand-500" style="width: {{ max(3, round($link->clicks / $linkMax * 100)) }}%"></div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Geo + devices --}}
            <div class="space-y-5">
                <div class="lf-card p-5">
                    <h3 class="text-sm font-semibold text-slate-900">{{ __('Top countries') }}</h3>
                    @if (empty($topCountries))
                        <p class="mt-3 text-sm text-slate-400">{{ __('No location data in the last 30 days yet.') }}</p>
                    @else
                        <div class="mt-4 space-y-2.5">
                            @foreach ($topCountries as $code => $clicks)
                                @php $pct = $countryTotal > 0 ? round($clicks / $countryTotal * 100) : 0; @endphp
                                <div class="flex items-center gap-2.5 text-sm">
                                    <img src="{{ asset('vendor/flags/'.strtolower($code).'.svg') }}" alt="" loading="lazy" class="h-3.5 w-5 shrink-0 rounded-sm object-cover ring-1 ring-slate-200/70" onerror="this.style.visibility='hidden'">
                                    <span class="min-w-0 flex-1 truncate text-slate-600">{{ $names[$code] ?? $code }}</span>
                                    <div class="hidden h-1.5 w-20 overflow-hidden rounded-full bg-slate-100 sm:block">
                                        <div class="h-full rounded-full bg-brand-500" style="width: {{ max(6, round($clicks / $countryMax * 100)) }}%"></div>
                                    </div>
                                    <span class="shrink-0 tabular-nums text-slate-400">{{ number_format($clicks) }} ({{ $pct }}%)</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="lf-card p-5">
                    <h3 class="text-sm font-semibold text-slate-900">{{ __('Devices') }}</h3>
                    @if (empty($devices))
                        <p class="mt-3 text-sm text-slate-400">{{ __('No device data in the last 30 days yet.') }}</p>
                    @else
                        <div class="mt-4 space-y-2.5">
                            @foreach ($devices as $d)
                                @php $pct = $deviceTotal > 0 ? round($d['clicks'] / $deviceTotal * 100) : 0; @endphp
                                <div class="flex items-center gap-2.5 text-sm">
                                    <x-brand-icon type="device" :label="$d['label']" class="h-4 w-4 shrink-0 text-slate-400" />
                                    <span class="min-w-0 flex-1 truncate capitalize text-slate-600">{{ $d['label'] }}</span>
                                    <div class="hidden h-1.5 w-20 overflow-hidden rounded-full bg-slate-100 sm:block">
                                        <div class="h-full rounded-full bg-brand-400" style="width: {{ max(6, $pct) }}%"></div>
                                    </div>
                                    <span class="shrink-0 tabular-nums text-slate-400">{{ number_format($d['clicks']) }} ({{ $pct }}%)</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if (($aiEnabled ?? false) && $insight)
            {{-- AI weekly insight --}}
            <div class="lf-card mt-5 overflow-hidden">
                <div class="flex items-center gap-2.5 border-b border-slate-100 bg-gradient-to-r from-spark-50 to-transparent px-5 py-3.5">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-spark-100 text-spark-700">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.9 4.8L18.7 9l-4.8 1.9L12 15.7 10.1 10.9 5.3 9l4.8-1.2L12 3z"/><path d="M19 14l.8 2.2L22 17l-2.2.8L19 20l-.8-2.2L16 17l2.2-.8L19 14z"/></svg>
                    </span>
                    <h3 class="text-sm font-semibold text-slate-900">{{ __('Your weekly insight') }}</h3>
                    @if (! empty($insight['generated_at']))
                        <span class="ml-auto text-xs text-slate-400">{{ \Illuminate\Support\Carbon::parse($insight['generated_at'])->diffForHumans() }}</span>
                    @endif
                </div>
                <p class="px-5 py-4 text-sm leading-relaxed text-slate-700">{{ $insight['text'] }}</p>
            </div>
        @endif
    @endif
</x-app-layout>
