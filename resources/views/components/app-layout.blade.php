<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ ($title ?? null) ? $title.' · '.config('linkforge.name') : config('linkforge.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.theme')
    @include('partials.head-extra')
</head>
<body class="min-h-screen bg-slate-50">
    @if (session('impersonator_id'))
        <div class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 bg-amber-500 px-4 py-2 text-center text-sm font-medium text-white">
            <span>You are viewing the app as {{ auth()->user()->name }}.</span>
            <form method="POST" action="{{ route('impersonate.leave') }}">
                @csrf
                <button type="submit" class="rounded-md bg-white/20 px-2.5 py-0.5 text-xs font-semibold transition hover:bg-white/30">Return to admin</button>
            </form>
        </div>
    @endif

    {{-- Mobile drawer toggle (CSS-only, no JS dependency) --}}
    <input type="checkbox" id="lf-drawer" class="peer sr-only">
    <label for="lf-drawer" aria-hidden="true"
           class="fixed inset-0 z-30 hidden bg-slate-900/40 backdrop-blur-sm peer-checked:block lg:!hidden"></label>

    {{-- Sidebar --}}
    <aside class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col border-r border-slate-200 bg-white transition-transform duration-200 ease-out peer-checked:translate-x-0 lg:translate-x-0">
        <div class="flex h-16 items-center justify-between border-b border-slate-200 px-5">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5">
                <x-application-logo size="h-8 w-8" />
                <span class="text-base font-semibold tracking-tight text-slate-900">{{ config('linkforge.name') }}</span>
            </a>
            <label for="lf-drawer" class="cursor-pointer rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 lg:hidden">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </label>
        </div>

        <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
            <div>
                <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Menu') }}</p>
                <div class="space-y-1">
                    @php $is = fn ($r) => request()->routeIs($r); @endphp
                    <a href="{{ route('dashboard') }}" @class(['group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition','bg-brand-50 text-brand-700'=>$is('dashboard'),'text-slate-600 hover:bg-slate-100 hover:text-slate-900'=>!$is('dashboard')])>
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 12 9-9 9 9"/><path d="M5 10v10a1 1 0 0 0 1 1h3v-6h6v6h3a1 1 0 0 0 1-1V10"/></svg>
                        {{ __('Dashboard') }}
                    </a>
                    <a href="{{ route('links.index') }}" @class(['group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition','bg-brand-50 text-brand-700'=>$is('links.*'),'text-slate-600 hover:bg-slate-100 hover:text-slate-900'=>!$is('links.*')])>
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757"/><path d="M10.81 15.312a4.5 4.5 0 0 1-1.242-7.244l4.5-4.5a4.5 4.5 0 0 1 6.364 6.364l-1.757 1.757"/></svg>
                        {{ __('Links') }}
                    </a>
                    <a href="{{ route('analytics.index') }}" @class(['group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition','bg-brand-50 text-brand-700'=>$is('analytics.*'),'text-slate-600 hover:bg-slate-100 hover:text-slate-900'=>!$is('analytics.*')])>
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m7 14 3-3 3 3 5-6"/></svg>
                        {{ __('Analytics') }}
                    </a>
                    <a href="{{ route('qr.index') }}" @class(['group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition','bg-brand-50 text-brand-700'=>$is('qr.*'),'text-slate-600 hover:bg-slate-100 hover:text-slate-900'=>!$is('qr.*')])>
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><path d="M14 14h.01M20 14v.01M14 20h6v-6"/></svg>
                        {{ __('QR Codes') }}
                    </a>
                    <a href="{{ route('pixels.index') }}" @class(['group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition','bg-brand-50 text-brand-700'=>$is('pixels.*'),'text-slate-600 hover:bg-slate-100 hover:text-slate-900'=>!$is('pixels.*')])>
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3"/></svg>
                        {{ __('Pixels') }}
                    </a>
                    <a href="{{ route('bio.index') }}" @class(['group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition','bg-brand-50 text-brand-700'=>$is('bio.*'),'text-slate-600 hover:bg-slate-100 hover:text-slate-900'=>!$is('bio.*')])>
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M5 17.5a3.5 3.5 0 0 1 7 0M15 9h3M15 13h3"/></svg>
                        {{ __('Bio Pages') }}
                    </a>
                </div>
            </div>
            <div>
                <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Account') }}</p>
                <div class="space-y-1">
                    <a href="{{ route('domains.index') }}" @class(['group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition','bg-brand-50 text-brand-700'=>$is('domains.*'),'text-slate-600 hover:bg-slate-100 hover:text-slate-900'=>!$is('domains.*')])>
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>
                        {{ __('Custom domains') }}
                    </a>
                    <a href="{{ route('billing.index') }}" @class(['group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition','bg-brand-50 text-brand-700'=>$is('billing.*'),'text-slate-600 hover:bg-slate-100 hover:text-slate-900'=>!$is('billing.*')])>
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                        {{ __('Billing') }}
                    </a>
                    <a href="{{ route('monetization.index') }}" @class(['group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition','bg-brand-50 text-brand-700'=>$is('monetization.*'),'text-slate-600 hover:bg-slate-100 hover:text-slate-900'=>!$is('monetization.*')])>
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        {{ __('Monetization') }}
                    </a>
                    <a href="{{ route('developer.index') }}" @class(['group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition','bg-brand-50 text-brand-700'=>$is('developer.*')||$is('tokens.*')||$is('webhooks.*'),'text-slate-600 hover:bg-slate-100 hover:text-slate-900'=>!($is('developer.*')||$is('tokens.*')||$is('webhooks.*'))])>
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m16 18 6-6-6-6M8 6l-6 6 6 6"/></svg>
                        {{ __('Developer') }}
                    </a>
                    @php $answeredTickets = auth()->user()?->tickets()->where('status', 'answered')->count() ?? 0; @endphp
                    <a href="{{ route('support.index') }}" @class(['group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition','bg-brand-50 text-brand-700'=>$is('support.*'),'text-slate-600 hover:bg-slate-100 hover:text-slate-900'=>!$is('support.*')])>
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        {{ __('Support') }}
                        @if ($answeredTickets)<span class="ml-auto rounded-full bg-brand-600 px-2 py-0.5 text-[10px] font-semibold text-white">{{ $answeredTickets }}</span>@endif
                    </a>
                </div>
            </div>
        </nav>

        <x-ad placement="sidebar" class="mx-3 mb-1" />

        @php
            $currentPlan = auth()->user()?->currentPlan();
            $planName = $currentPlan?->name ?? 'Free';
            $isFreePlan = ! $currentPlan || (float) $currentPlan->price <= 0;
        @endphp
        <div class="border-t border-slate-200 p-3">
            <div class="rounded-xl border border-brand-100 bg-brand-50 p-4 dark:border-brand-500/25 dark:bg-brand-500/10">
                <p class="text-sm font-semibold text-brand-800 dark:text-brand-200">{{ __("You're on :plan", ['plan' => $planName]) }}</p>
                @if ($isFreePlan)
                    <p class="mt-1 text-xs text-brand-700/70 dark:text-brand-300/70">{{ __('Unlock branded domains, AI & retargeting.') }}</p>
                    <a href="{{ route('billing.index') }}" class="mt-3 inline-flex w-full items-center justify-center rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-brand-700">{{ __('Upgrade plan') }}</a>
                @else
                    <p class="mt-1 text-xs text-brand-700/70 dark:text-brand-300/70">{{ __('Thanks for being a :plan member.', ['plan' => $planName]) }}</p>
                    <a href="{{ route('billing.index') }}" class="mt-3 inline-flex w-full items-center justify-center rounded-lg border border-brand-300 bg-white px-3 py-2 text-xs font-semibold text-brand-700 transition hover:bg-brand-50 dark:border-brand-500/40 dark:text-brand-300 dark:hover:bg-brand-500/10">{{ __('Manage plan') }}</a>
                @endif
            </div>
        </div>
    </aside>

    {{-- Content --}}
    <div class="lg:pl-64">
        <header class="sticky top-0 z-20 flex h-16 items-center gap-4 border-b border-slate-200 bg-white/80 px-4 backdrop-blur sm:px-6 dark:bg-[#0f172a]/80">
            <label for="lf-drawer" class="cursor-pointer rounded-md p-2 text-slate-500 hover:bg-slate-100 lg:hidden">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </label>

            <div class="min-w-0 flex-1">
                @isset($header)
                    <h1 class="truncate text-lg font-semibold text-slate-900">{{ $header }}</h1>
                @endisset
            </div>

            @include('partials.locale-switcher')
            @include('partials.theme-toggle')

            {{-- User menu (native disclosure, no JS) --}}
            <details class="relative">
                <summary class="flex cursor-pointer list-none items-center gap-2 rounded-lg p-1.5 transition hover:bg-slate-100 [&::-webkit-details-marker]:hidden">
                    <span class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full text-xs font-semibold text-white"
                          style="background-image:linear-gradient(135deg,var(--color-brand-500),var(--color-brand-700))">
                        @if (auth()->user()?->avatarUrl())
                            <img src="{{ auth()->user()->avatarUrl() }}" alt="Avatar" class="h-full w-full object-cover">
                        @else
                            {{ auth()->user()?->initials() ?? 'U' }}
                        @endif
                    </span>
                    <span class="hidden text-sm font-medium text-slate-700 sm:block">{{ auth()->user()->name ?? 'User' }}</span>
                    <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                </summary>
                <div class="absolute right-0 mt-2 w-56 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                    <div class="border-b border-slate-100 px-4 py-3">
                        <p class="truncate text-sm font-medium text-slate-900">{{ auth()->user()->name ?? '' }}</p>
                        <p class="truncate text-xs text-slate-500">{{ auth()->user()->email ?? '' }}</p>
                    </div>
                    @if (auth()->user()?->isAdmin())
                        <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">{{ __('Admin panel') }}</a>
                    @endif
                    <a href="{{ route('account') }}" class="block px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">{{ __('Account settings') }}</a>
                    <a href="{{ route('billing.index') }}" class="block px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">{{ __('Billing') }}</a>
                    <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-100">
                        @csrf
                        <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50">{{ __('Log out') }}</button>
                    </form>
                </div>
            </details>
        </header>

        <main class="p-4 sm:p-6 lg:p-8">
            {{ $slot }}
        </main>
    </div>

    @include('partials.ad-popup')
    @include('partials.confirm-dialog')
</body>
</html>
