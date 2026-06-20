<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('linkforge.name') }} · {{ config('linkforge.tagline') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.theme')
    @include('partials.head-extra')
    <style>
        /* Theme-aware translucent sticky nav (fixes the opacity-variant dark-mode gap). */
        .lf-nav { background: color-mix(in srgb, var(--lf-surface) 80%, transparent); }

        /* Atmospheric hero: brand + spark glow, theme-aware via the brand CSS vars. */
        .lf-aura::before {
            content: ""; position: absolute; inset: 0; z-index: 0; pointer-events: none;
            background:
                radial-gradient(46rem 24rem at 50% -10%, color-mix(in srgb, var(--color-brand-500) 28%, transparent), transparent 70%),
                radial-gradient(34rem 22rem at 86% 6%, color-mix(in srgb, var(--color-spark-500) 16%, transparent), transparent 72%);
        }
        /* Faint engineering grid, fades out downward; adapts because slate-400 is remapped in dark. */
        .lf-grid {
            background-image:
                linear-gradient(to right, color-mix(in srgb, var(--color-slate-400) 14%, transparent) 1px, transparent 1px),
                linear-gradient(to bottom, color-mix(in srgb, var(--color-slate-400) 14%, transparent) 1px, transparent 1px);
            background-size: 58px 58px;
            -webkit-mask-image: radial-gradient(70% 55% at 50% 0%, #000 30%, transparent 78%);
                    mask-image: radial-gradient(70% 55% at 50% 0%, #000 30%, transparent 78%);
        }
        /* Brand-to-spark text accent (on-brand, not a generic purple gradient). */
        .lf-grad {
            background: linear-gradient(115deg, var(--color-brand-500), var(--color-spark-500));
            -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        .lf-mock { background: var(--lf-surface); }
        .lf-lift { transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease; }
        .lf-lift:hover { transform: translateY(-4px); box-shadow: 0 22px 48px -22px color-mix(in srgb, var(--color-brand-700) 45%, transparent); }

        /* Staggered intro on load. */
        @keyframes lf-rise { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
        .lf-rise { opacity: 0; animation: lf-rise .7s cubic-bezier(.2, .7, .2, 1) forwards; }
        @keyframes lf-draw { to { stroke-dashoffset: 0; } }
        .lf-spark-line { stroke-dasharray: 600; stroke-dashoffset: 600; animation: lf-draw 1.6s ease .3s forwards; }
        @media (prefers-reduced-motion: reduce) {
            .lf-rise, .lf-spark-line { animation: none; opacity: 1; stroke-dashoffset: 0; }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-600">

    {{-- ============================ NAV ============================ --}}
    <header class="lf-nav sticky top-0 z-40 border-b border-slate-200/70 backdrop-blur-md">
        <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-5 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                <x-application-logo size="h-8 w-8" />
                <span class="text-base font-semibold tracking-tight text-slate-900">{{ config('linkforge.name') }}</span>
            </a>

            <nav class="hidden items-center gap-1 md:flex">
                <a href="#analytics" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:text-slate-900">Analytics</a>
                <a href="#features" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:text-slate-900">Features</a>
                <a href="#own" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:text-slate-900">Self-hosted</a>
            </nav>

            <div class="flex items-center gap-1.5">
                @include('partials.theme-toggle')
                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="hidden rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:text-slate-900 sm:inline-flex">Sign in</a>
                    <a href="{{ route('register') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">Get started</a>
                @endauth
            </div>
        </div>
    </header>

    {{-- ============================ HERO ============================ --}}
    <section class="lf-aura relative overflow-hidden">
        <div class="lf-grid pointer-events-none absolute inset-0 z-0"></div>
        <div class="relative z-10 mx-auto max-w-6xl px-5 pt-16 pb-20 sm:px-6 sm:pt-24 lg:pb-28">
            <div class="mx-auto max-w-3xl text-center">
                <span class="lf-rise inline-flex items-center gap-2 rounded-full border border-brand-200 bg-brand-50 px-3.5 py-1.5 text-xs font-medium text-brand-700">
                    <span class="h-1.5 w-1.5 rounded-full" style="background: var(--color-spark-500)"></span>
                    AI-native · Safe by design · Self-hostable
                </span>
                <h1 class="lf-rise mt-6 text-4xl font-bold leading-[1.05] tracking-tight text-slate-900 sm:text-6xl" style="animation-delay:.06s">
                    Forge links that<br><span class="lf-grad">work harder.</span>
                </h1>
                <p class="lf-rise mx-auto mt-5 max-w-xl text-lg leading-relaxed text-slate-500" style="animation-delay:.12s">
                    {{ config('linkforge.description') }}
                </p>
                <div class="lf-rise mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row" style="animation-delay:.18s">
                    <a href="{{ route('register') }}" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-6 py-3.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 sm:w-auto">
                        Start forging for free
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                    <a href="#analytics" class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-6 py-3.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 sm:w-auto">
                        See it in action
                    </a>
                </div>

                @if (\App\Models\Setting::get('guest_shorten', '1') === '1')
                    {{-- Live anonymous shortener --}}
                    <div class="lf-rise mx-auto mt-12 max-w-xl" style="animation-delay:.24s">
                        <form id="lf-shorten" class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-lg shadow-slate-900/5">
                            <span class="grid h-9 w-9 shrink-0 place-items-center text-slate-400">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13.19 8.69a4.5 4.5 0 0 1 1.24 7.24l-4.5 4.5a4.5 4.5 0 0 1-6.36-6.36l1.75-1.76M10.81 15.31a4.5 4.5 0 0 1-1.24-7.24l4.5-4.5a4.5 4.5 0 0 1 6.36 6.36l-1.75 1.76"/></svg>
                            </span>
                            <input id="lf-url" name="long_url" type="url" required placeholder="Paste a long URL to shorten…"
                                   class="min-w-0 flex-1 bg-transparent px-1 text-sm text-slate-700 outline-none placeholder:text-slate-400">
                            <button type="submit" class="shrink-0 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:opacity-60">Shorten</button>
                        </form>
                        <p id="lf-shorten-error" class="mt-2 hidden text-left text-sm text-red-600"></p>
                        <div id="lf-shorten-result" class="mt-3 hidden items-center gap-2 rounded-xl border border-brand-200 bg-brand-50 p-2">
                            <input id="lf-shorten-out" readonly class="min-w-0 flex-1 bg-transparent px-2 text-sm font-semibold text-brand-800 outline-none">
                            <a id="lf-shorten-open" target="_blank" rel="noopener" class="shrink-0 rounded-lg border border-brand-300 px-3 py-2 text-xs font-semibold text-brand-700">Open</a>
                            <button type="button" id="lf-shorten-copy" class="shrink-0 rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white">Copy</button>
                        </div>
                        <p class="mt-3 text-xs text-slate-400"><a href="{{ route('register') }}" class="font-medium text-brand-600 hover:text-brand-700">Sign up free</a> to brand, track and manage your links.</p>
                    </div>
                    <script>
                        (function () {
                            var form = document.getElementById('lf-shorten'); if (!form) return;
                            var url = document.getElementById('lf-url'), out = document.getElementById('lf-shorten-out'),
                                res = document.getElementById('lf-shorten-result'), err = document.getElementById('lf-shorten-error'),
                                open = document.getElementById('lf-shorten-open'), copy = document.getElementById('lf-shorten-copy'),
                                btn = form.querySelector('button[type=submit]'),
                                token = document.querySelector('meta[name=csrf-token]').content;
                            form.addEventListener('submit', function (e) {
                                e.preventDefault(); err.classList.add('hidden'); res.classList.add('hidden'); res.classList.remove('flex'); btn.disabled = true;
                                fetch(@json(route('guest.shorten')), {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                                    body: JSON.stringify({ long_url: url.value }),
                                }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                                  .then(function (x) {
                                      if (!x.ok) { throw new Error(x.j.error || 'Could not shorten that link.'); }
                                      out.value = x.j.short_url; open.href = x.j.short_url; res.classList.remove('hidden'); res.classList.add('flex');
                                  })
                                  .catch(function (e) { err.textContent = e.message; err.classList.remove('hidden'); })
                                  .finally(function () { btn.disabled = false; });
                            });
                            copy.addEventListener('click', function () { navigator.clipboard.writeText(out.value); copy.textContent = 'Copied'; setTimeout(function () { copy.textContent = 'Copy'; }, 1200); });
                        })();
                    </script>
                @else
                    <a href="{{ route('register') }}" class="lf-rise mx-auto mt-12 inline-flex items-center gap-2 rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-700" style="animation-delay:.24s">Get started free</a>
                    <p class="lf-rise mt-3 text-xs text-slate-400" style="animation-delay:.28s">Free to start. No credit card. Installs on your own cPanel host in minutes.</p>
                @endif
            </div>

            {{-- Hero product preview --}}
            <div class="lf-rise relative mx-auto mt-16 max-w-4xl" style="animation-delay:.34s">
                <div class="lf-mock overflow-hidden rounded-2xl border border-slate-200 shadow-2xl shadow-slate-900/10">
                    <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-3">
                        <span class="h-2.5 w-2.5 rounded-full bg-red-400/70"></span>
                        <span class="h-2.5 w-2.5 rounded-full bg-spark-400/80"></span>
                        <span class="h-2.5 w-2.5 rounded-full bg-brand-400/80"></span>
                        <span class="ml-3 text-xs font-medium text-slate-400">{{ config('linkforge.name') }} · Dashboard</span>
                        <span class="ml-auto inline-flex items-center gap-1.5 text-xs font-medium text-brand-600">
                            <span class="h-1.5 w-1.5 animate-pulse rounded-full" style="background: var(--color-brand-500)"></span> Live
                        </span>
                    </div>
                    <div class="grid gap-4 p-4 sm:p-6 lg:grid-cols-3">
                        {{-- KPIs + chart --}}
                        <div class="lg:col-span-2">
                            <div class="grid grid-cols-3 gap-3">
                                @foreach ([['Total clicks','48,920','+18%'],['Unique','31,204','+12%'],['QR scans','6,118','+27%']] as $kpi)
                                    <div class="rounded-xl border border-slate-100 p-3">
                                        <p class="text-[11px] font-medium text-slate-400">{{ $kpi[0] }}</p>
                                        <p class="mt-1 text-lg font-bold tracking-tight text-slate-900">{{ $kpi[1] }}</p>
                                        <p class="text-[11px] font-semibold text-brand-600">{{ $kpi[2] }}</p>
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-4 rounded-xl border border-slate-100 p-3">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-semibold text-slate-700">Clicks over time</p>
                                    <p class="text-[11px] text-slate-400">Last 30 days</p>
                                </div>
                                <svg viewBox="0 0 320 96" class="mt-2 w-full" preserveAspectRatio="none" aria-hidden="true">
                                    <defs>
                                        <linearGradient id="lf-area" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="0%" stop-color="var(--color-brand-500)" stop-opacity="0.35"/>
                                            <stop offset="100%" stop-color="var(--color-brand-500)" stop-opacity="0"/>
                                        </linearGradient>
                                    </defs>
                                    <path d="M0,78 C24,70 40,72 60,58 C84,42 96,50 120,40 C146,29 156,44 180,32 C206,19 220,30 240,18 C262,6 282,22 300,12 L320,16 L320,96 L0,96 Z" fill="url(#lf-area)"/>
                                    <path class="lf-spark-line" d="M0,78 C24,70 40,72 60,58 C84,42 96,50 120,40 C146,29 156,44 180,32 C206,19 220,30 240,18 C262,6 282,22 300,12 L320,16" fill="none" stroke="var(--color-brand-500)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                        </div>
                        {{-- Top countries --}}
                        <div class="rounded-xl border border-slate-100 p-3">
                            <p class="text-xs font-semibold text-slate-700">Top countries</p>
                            <div class="mt-3 space-y-3">
                                @foreach ([['United States',82],['Germany',64],['India',49],['Brazil',37],['Japan',24]] as $c)
                                    <div>
                                        <div class="flex items-center justify-between text-[11px] text-slate-500">
                                            <span>{{ $c[0] }}</span><span class="tabular-nums">{{ $c[1] }}%</span>
                                        </div>
                                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                            <div class="h-full rounded-full" style="width: {{ $c[1] }}%; background: var(--color-brand-500)"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================ TRUST STRIP ============================ --}}
    <section class="border-y border-slate-200/70 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-center gap-x-8 gap-y-3 px-6 py-5 text-center text-xs font-medium text-slate-400">
            <span class="text-slate-500">Runs on hosting you already have</span>
            <span class="hidden h-3.5 w-px bg-slate-200 sm:block"></span>
            <span>cPanel ready</span>
            <span>PHP 8.2+</span>
            <span>MySQL / MariaDB</span>
            <span>No SSH required</span>
            <span>No monthly link tax</span>
        </div>
    </section>

    {{-- ============================ ANALYTICS ============================ --}}
    <section id="analytics" class="scroll-mt-20 py-20 sm:py-28">
        <div class="mx-auto grid max-w-6xl items-center gap-12 px-5 sm:px-6 lg:grid-cols-2 lg:gap-16">
            <div>
                <span class="text-xs font-semibold tracking-widest text-brand-600 uppercase">Deep analytics</span>
                <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">Know exactly what's working.</h2>
                <p class="mt-4 text-base leading-relaxed text-slate-500">
                    Every click, mapped. Drill from country down to city, split by device, browser, OS and referrer, and watch it update in real time. Privacy-first, with no third-party trackers and full CSV export.
                </p>
                <ul class="mt-6 space-y-3">
                    @foreach (['Geo down to the city, on an interactive world map', 'Device, browser, OS and referrer breakdowns', 'Real-time rollups that stay fast at any scale', 'GDPR-friendly. Your data never leaves your server'] as $point)
                        <li class="flex items-start gap-3 text-sm text-slate-600">
                            <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-600">
                                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                            </span>
                            {{ $point }}
                        </li>
                    @endforeach
                </ul>
            </div>
            {{-- Mock: world dots + metrics --}}
            <div class="lf-card lf-lift p-5 sm:p-6">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-slate-900">Audience by location</p>
                    <span class="rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">Real-time</span>
                </div>
                <svg viewBox="0 0 400 180" class="mt-4 w-full" aria-hidden="true">
                    <rect width="400" height="180" rx="12" fill="color-mix(in srgb, var(--color-brand-500) 6%, transparent)"/>
                    @php
                        $dots = [[70,60,5],[110,52,3],[150,70,4],[190,58,6],[230,66,3],[120,100,4],[200,108,5],[280,80,4],[300,110,3],[90,120,3],[250,52,3],[330,70,5],[170,120,3],[60,90,3],[340,120,4]];
                    @endphp
                    @foreach ($dots as $d)
                        <circle cx="{{ $d[0] }}" cy="{{ $d[1] }}" r="{{ $d[2] }}" fill="var(--color-brand-500)" opacity="0.35"/>
                        <circle cx="{{ $d[0] }}" cy="{{ $d[1] }}" r="{{ $d[2] / 2 }}" fill="var(--color-brand-600)"/>
                    @endforeach
                </svg>
                <div class="mt-4 grid grid-cols-3 gap-3">
                    @foreach ([['Countries','92'],['Cities','1,480'],['Avg. redirect','41ms']] as $s)
                        <div class="rounded-lg border border-slate-100 p-2.5 text-center">
                            <p class="text-base font-bold tracking-tight text-slate-900">{{ $s[1] }}</p>
                            <p class="text-[11px] text-slate-400">{{ $s[0] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ============================ QR STUDIO ============================ --}}
    <section class="border-y border-slate-200/70 bg-white py-20 sm:py-28">
        <div class="mx-auto grid max-w-6xl items-center gap-12 px-5 sm:px-6 lg:grid-cols-2 lg:gap-16">
            {{-- Mock: styled QR --}}
            <div class="order-last lg:order-first">
                <div class="lf-card lf-lift mx-auto max-w-sm p-6">
                    <div class="rounded-2xl p-5" style="background: color-mix(in srgb, var(--color-brand-500) 8%, transparent)">
                        <svg viewBox="0 0 100 100" class="mx-auto h-44 w-44" aria-hidden="true" fill="var(--color-brand-700)">
                            {{-- finder squares --}}
                            <path d="M8 8h22v22H8z" fill="none" stroke="var(--color-brand-700)" stroke-width="6"/>
                            <rect x="16" y="16" width="6" height="6" rx="1.5"/>
                            <path d="M70 8h22v22H70z" fill="none" stroke="var(--color-brand-700)" stroke-width="6"/>
                            <rect x="78" y="16" width="6" height="6" rx="1.5"/>
                            <path d="M8 70h22v22H8z" fill="none" stroke="var(--color-brand-700)" stroke-width="6"/>
                            <rect x="16" y="78" width="6" height="6" rx="1.5"/>
                            @php $mods = [[40,10],[48,10],[40,18],[56,18],[64,10],[40,26],[48,26],[64,26],[10,40],[18,40],[26,40],[40,40],[48,48],[56,40],[64,48],[72,40],[84,40],[10,48],[26,48],[34,56],[42,64],[50,56],[58,64],[66,56],[74,64],[82,56],[90,64],[44,72],[52,80],[60,72],[68,80],[76,72],[84,84],[44,84],[40,90],[68,90]]; @endphp
                            @foreach ($mods as $m)<rect x="{{ $m[0] }}" y="{{ $m[1] }}" width="6" height="6" rx="1.5"/>@endforeach
                        </svg>
                    </div>
                    <div class="mt-4 flex items-center justify-center gap-2">
                        @foreach (['PNG','SVG','PDF','JPG'] as $fmt)
                            <span class="rounded-md border border-slate-200 px-2.5 py-1 text-[11px] font-semibold text-slate-500">{{ $fmt }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
            <div>
                <span class="text-xs font-semibold tracking-widest text-brand-600 uppercase">QR studio</span>
                <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">Codes worth scanning.</h2>
                <p class="mt-4 text-base leading-relaxed text-slate-500">
                    A full design studio for QR codes. Pick shapes, gradients and your logo, then export anywhere. Make them dynamic so every scan is tracked and the destination can change after you print.
                </p>
                <ul class="mt-6 space-y-3">
                    @foreach (['Custom dot and eye shapes, gradients and logo embed', 'Dynamic codes you can repoint without reprinting', 'Export to PNG, SVG, PDF and JPG', 'Bulk-generate from a CSV in one pass'] as $point)
                        <li class="flex items-start gap-3 text-sm text-slate-600">
                            <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-600">
                                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                            </span>
                            {{ $point }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- ============================ BIO + AI ============================ --}}
    <section class="py-20 sm:py-28">
        <div class="mx-auto grid max-w-6xl items-center gap-12 px-5 sm:px-6 lg:grid-cols-2 lg:gap-16">
            <div>
                <span class="text-xs font-semibold tracking-widest text-brand-600 uppercase">Link in bio · AI assistant</span>
                <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">A landing page in every link.</h2>
                <p class="mt-4 text-base leading-relaxed text-slate-500">
                    Build a beautiful bio page with blocks, themes and lead capture, no separate tool needed. Then let the built-in AI suggest brandable aliases, draft your bio and answer questions about your own link data in plain language.
                </p>
                <ul class="mt-6 space-y-3">
                    @foreach (['Drag-and-build bio pages with 30+ block types', 'Capture subscribers and messages right on the page', 'AI alias suggestions and bio drafting', 'Ask your links: natural-language analytics'] as $point)
                        <li class="flex items-start gap-3 text-sm text-slate-600">
                            <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-600">
                                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                            </span>
                            {{ $point }}
                        </li>
                    @endforeach
                </ul>
            </div>
            {{-- Mock: phone bio --}}
            <div class="flex justify-center">
                <div class="lf-mock w-64 rounded-[2rem] border-4 border-slate-200 p-4 shadow-2xl shadow-slate-900/10">
                    <div class="mx-auto h-1.5 w-16 rounded-full bg-slate-200"></div>
                    <div class="mt-5 flex flex-col items-center text-center">
                        <span class="grid h-16 w-16 place-items-center rounded-full text-xl font-bold text-white" style="background-image:linear-gradient(135deg,var(--color-brand-500),var(--color-brand-700))">{{ strtoupper(substr(config('linkforge.name'),0,1)) }}</span>
                        <p class="mt-3 flex items-center gap-1 text-sm font-semibold text-slate-900">
                            {{ config('linkforge.name') }}
                            <svg class="h-3.5 w-3.5 text-brand-500" viewBox="0 0 24 24" fill="currentColor"><path d="m12 2 2.4 2.1 3.1-.5 1 3 2.8 1.4-1 3 1 3-2.8 1.4-1 3-3.1-.5L12 22l-2.4-2.1-3.1.5-1-3L2.7 16.5l1-3-1-3 2.8-1.4 1-3 3.1.5z"/><path d="m9 12 2 2 4-4" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </p>
                        <p class="mt-0.5 text-[11px] text-slate-400">Designer and maker</p>
                    </div>
                    <div class="mt-5 space-y-2.5">
                        @foreach (['Latest project','Newsletter','Book a call'] as $i => $btn)
                            <div class="rounded-xl py-2.5 text-center text-xs font-semibold {{ $i === 0 ? 'text-white' : 'border border-slate-200 text-slate-700' }}" @if($i === 0) style="background: var(--color-brand-600)" @endif>{{ $btn }}</div>
                        @endforeach
                    </div>
                    <div class="mt-4 flex justify-center gap-3 text-slate-300">
                        @foreach ([1,2,3] as $x)<span class="h-6 w-6 rounded-full bg-slate-100"></span>@endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================ FEATURE GRID ============================ --}}
    <section id="features" class="scroll-mt-20 border-t border-slate-200/70 bg-white py-20 sm:py-28">
        <div class="mx-auto max-w-6xl px-5 sm:px-6">
            <div class="mx-auto max-w-2xl text-center">
                <span class="text-xs font-semibold tracking-widest text-brand-600 uppercase">Everything included</span>
                <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">One platform, no add-ons.</h2>
                <p class="mt-4 text-base text-slate-500">Every feature ships in the box. No tiered paywalls on the core tools, no surprise upsells.</p>
            </div>
            <div class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @php
                    $features = [
                        ['t' => 'Branded short links', 'd' => 'Custom domains and memorable aliases with sub-50ms redirects.', 'p' => 'M13.19 8.69a4.5 4.5 0 0 1 1.24 7.24l-4.5 4.5a4.5 4.5 0 0 1-6.36-6.36l1.75-1.76M10.81 15.31a4.5 4.5 0 0 1-1.24-7.24l4.5-4.5a4.5 4.5 0 0 1 6.36 6.36l-1.75 1.76'],
                        ['t' => 'Smart routing', 'd' => 'Send visitors to different URLs by geo, device, language, time or A/B weight.', 'p' => 'M6 3v12M6 21a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM6 3a3 3 0 1 0 0 6 3 3 0 0 0 0-6zM18 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM18 9c0 6-6 6-6 12'],
                        ['t' => 'Never blacklisted', 'd' => 'Multi-source threat scanning blocks abuse and keeps your domain trusted.', 'p' => 'M12 3l8 4v5c0 5-3.4 7.7-8 9-4.6-1.3-8-4-8-9V7z'],
                        ['t' => 'API & webhooks', 'd' => 'A clean REST API with tokens and signed webhooks for every event.', 'p' => 'M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1'],
                        ['t' => 'Custom domains', 'd' => 'Bring unlimited branded domains with simple DNS verification.', 'p' => 'M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18M3 12h18M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18z'],
                        ['t' => 'Secure by default', 'd' => 'Two-factor auth, passkeys, Google sign-in and hardened headers built in.', 'p' => 'M12 3l8 4v5c0 5-3.4 7.7-8 9-4.6-1.3-8-4-8-9V7zM9.5 12l2 2 3.5-4'],
                    ];
                @endphp
                @foreach ($features as $f)
                    <div class="lf-card lf-lift p-6">
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                            <svg class="h-5.5 w-5.5" style="height:1.375rem;width:1.375rem" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $f['p'] }}"/></svg>
                        </span>
                        <h3 class="mt-4 text-base font-semibold text-slate-900">{{ $f['t'] }}</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-slate-500">{{ $f['d'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============================ OWN IT (dark band) ============================ --}}
    <section id="own" class="scroll-mt-20 bg-slate-900 py-20 sm:py-28">
        <div class="mx-auto max-w-6xl px-5 text-center sm:px-6">
            <span class="text-xs font-semibold tracking-widest uppercase" style="color: var(--color-brand-400)">Own it, end to end</span>
            <h2 class="mx-auto mt-3 max-w-2xl text-3xl font-bold tracking-tight text-white sm:text-4xl">Your platform. Your server. Your data.</h2>
            <p class="mx-auto mt-4 max-w-2xl text-base text-white/70">
                No monthly per-link rental, no vendor lock-in, no shipping your audience to someone else's cloud. Clean, readable code with nothing encrypted or obfuscated. Pay once, host anywhere, extend freely.
            </p>
            <div class="mx-auto mt-12 grid max-w-4xl gap-5 sm:grid-cols-3">
                @php
                    $own = [
                        ['t' => 'Pay once', 'd' => 'A one-time license, not a forever subscription.'],
                        ['t' => 'Readable code', 'd' => 'No ionCube, no obfuscation. It is yours to audit and extend.'],
                        ['t' => 'One-click updates', 'd' => 'A built-in updater keeps you current, on your schedule.'],
                    ];
                @endphp
                @foreach ($own as $o)
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-6 text-left">
                        <h3 class="text-base font-semibold text-white">{{ $o['t'] }}</h3>
                        <p class="mt-1.5 text-sm text-white/70">{{ $o['d'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============================ FINAL CTA ============================ --}}
    <section class="relative overflow-hidden py-20 sm:py-28">
        <div class="absolute inset-0 -z-10" style="background-image:radial-gradient(40rem 20rem at 50% 120%, color-mix(in srgb, var(--color-brand-500) 22%, transparent), transparent 70%)"></div>
        <div class="mx-auto max-w-2xl px-6 text-center">
            <h2 class="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">Start forging smarter links today.</h2>
            <p class="mt-4 text-base text-slate-500">Spin up your own link platform in minutes. Free to start, yours to keep.</p>
            <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <a href="{{ route('register') }}" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-7 py-3.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 sm:w-auto">
                    Create your free account
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
                <a href="{{ route('login') }}" class="inline-flex w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-7 py-3.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 sm:w-auto">Sign in</a>
            </div>
        </div>
    </section>

    {{-- ============================ FOOTER ============================ --}}
    <footer class="border-t border-slate-200/70 bg-white">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-6 py-8 sm:flex-row">
            <div class="flex items-center gap-2.5">
                <x-application-logo size="h-7 w-7" />
                <span class="text-sm font-semibold text-slate-900">{{ config('linkforge.name') }}</span>
            </div>
            <p class="text-xs text-slate-400">&copy; {{ date('Y') }} {{ config('linkforge.name') }}. Built to be owned.</p>
        </div>
    </footer>
</body>
</html>
