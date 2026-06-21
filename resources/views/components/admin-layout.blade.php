<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ ($title ?? null) ? $title.' · Admin' : 'Admin' }} · {{ config('linkforge.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.favicon')
    @include('partials.theme')
</head>
<body class="min-h-screen bg-slate-50">
    @include('partials.demo-chrome')
    <input type="checkbox" id="lf-drawer" class="peer sr-only">
    <label for="lf-drawer" aria-hidden="true" class="fixed inset-0 z-30 hidden bg-slate-900/40 backdrop-blur-sm peer-checked:block lg:!hidden"></label>

    <aside class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col border-r border-slate-200 bg-white transition-transform duration-200 ease-out peer-checked:translate-x-0 lg:translate-x-0">
        <div class="flex h-16 items-center gap-2.5 border-b border-slate-200 px-5">
            <x-application-logo size="h-8 w-8" />
            <span class="text-base font-semibold tracking-tight text-slate-900">{{ config('linkforge.name') }}</span>
            <span class="rounded-full bg-slate-900 px-2 py-0.5 text-[11px] font-medium text-white">Admin</span>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-5">
            @php $is = fn ($r) => request()->routeIs($r); @endphp
            @php
                $openTickets = \App\Models\Ticket::where('status', 'open')->count();
                $groups = [
                    ['label' => null, 'items' => [
                        ['admin.dashboard', 'Dashboard', 'M3 12l9-9 9 9M5 10v10a1 1 0 0 0 1 1h3v-6h6v6h3a1 1 0 0 0 1-1V10'],
                    ]],
                    ['label' => 'Customers', 'items' => [
                        ['admin.users', 'Users', 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75'],
                        ['admin.broadcast', 'Broadcast', 'M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zM22 6l-10 7L2 6'],
                        ['admin.tickets', 'Support', 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z', $openTickets],
                        ['admin.reports', 'Abuse reports', 'M4 22V4a2 2 0 0 1 2-2h12l-2 5 2 5H6'],
                    ]],
                    ['label' => 'Content', 'items' => [
                        ['admin.links', 'Links', 'M13.19 8.69a4.5 4.5 0 0 1 1.24 7.24l-4.5 4.5a4.5 4.5 0 0 1-6.36-6.36l1.75-1.76M10.81 15.31a4.5 4.5 0 0 1-1.24-7.24l4.5-4.5a4.5 4.5 0 0 1 6.36 6.36l-1.75 1.76'],
                        ['admin.moderation', 'Content', 'M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zM8 7h8M8 11h8M8 15h5'],
                        ['admin.pages.index', 'Pages', 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M16 13H8M16 17H8M10 9H8', null, 'admin.pages.'],
                        ['admin.blog.index', 'Blog', 'M4 19.5A2.5 2.5 0 0 1 6.5 17H20M4 19.5A2.5 2.5 0 0 0 6.5 22H20V2H6.5A2.5 2.5 0 0 0 4 4.5z', null, 'admin.blog.'],
                        ['admin.help.index', 'Help center', 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20zM9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01', null, 'admin.help.'],
                    ]],
                    ['label' => 'Revenue', 'items' => [
                        ['admin.plans', 'Plans & pricing', 'M20.59 13.41 13.42 20.6a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82zM7 7h.01'],
                        ['admin.billing', 'Billing & revenue', 'M2 7h20v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1zM2 7l1-3h18l1 3M6 15h4'],
                        ['admin.affiliate', 'Affiliate', 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M19 8v6M22 11h-6'],
                        ['admin.ads', 'Advertisement', 'm3 11 18-5v12L3 14v-3zM11.6 16.8a3 3 0 1 1-5.8-1.6'],
                    ]],
                    ['label' => 'System', 'items' => [
                        ['admin.audit', 'Audit log', 'M9 11l3 3 8-8M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11'],
                        ['admin.languages', 'Languages', 'M2 12h20M12 2a15 15 0 0 1 0 20M12 2a15 15 0 0 0 0 20M12 2a9 9 0 1 0 0 20 9 9 0 0 0 0-20z'],
                        ['admin.updates', 'Updates', 'M21 12a9 9 0 1 1-3-6.7L21 8M21 3v5h-5'],
                        ['admin.settings', 'Settings', 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-2.82 1.17V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15H4.5a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 6 9.4l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 11 4.6V4.5a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 2.82 1.17l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 11H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z'],
                    ]],
                ];
            @endphp
            @foreach ($groups as $group)
                @if (! empty($group['label']))
                    <p class="px-3 pt-5 pb-1 text-[11px] font-semibold tracking-wide text-slate-400 uppercase">{{ $group['label'] }}</p>
                @endif
                @foreach ($group['items'] as $item)
                    @php [$route, $label, $path, $badge, $activePrefix] = array_pad($item, 5, null); $activePrefix = $activePrefix ?? $route; @endphp
                    <a href="{{ route($route) }}" @class(['group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition','bg-brand-50 text-brand-700'=>$is($activePrefix.'*'),'text-slate-600 hover:bg-slate-100 hover:text-slate-900'=>!$is($activePrefix.'*')])>
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $path }}"/></svg>
                        {{ $label }}
                        @if ($badge)<span class="ml-auto rounded-full bg-amber-500 px-2 py-0.5 text-[10px] font-semibold text-white">{{ $badge }}</span>@endif
                    </a>
                @endforeach
            @endforeach
        </nav>

        <div class="border-t border-slate-200 p-3">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                Back to app
            </a>
        </div>
    </aside>

    <div class="lg:pl-64">
        <header class="sticky top-0 z-20 flex h-16 items-center gap-4 border-b border-slate-200 bg-white/80 px-4 backdrop-blur sm:px-6 dark:bg-[#0f172a]/80">
            <label for="lf-drawer" class="cursor-pointer rounded-md p-2 text-slate-500 hover:bg-slate-100 lg:hidden">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </label>
            <div class="min-w-0 flex-1">
                @isset($header)<h1 class="truncate text-lg font-semibold text-slate-900">{{ $header }}</h1>@endisset
            </div>
            {{-- Link to the index FILE (not the /docs directory) and root-relative (host stripped):
                 the file is served statically on every install layout and the browser resolves it
                 against the real origin, immune to a stale APP_URL / proxy Host or a "docs" short link. --}}
            <a href="{{ parse_url(url('docs/index.html'), PHP_URL_PATH) }}" target="_blank" rel="noopener"
               class="flex h-9 items-center gap-1.5 rounded-lg px-2 text-sm font-medium text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
               title="Documentation (opens in a new tab)">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20M4 19.5A2.5 2.5 0 0 0 6.5 22H20V2H6.5A2.5 2.5 0 0 0 4 4.5z"/></svg>
                <span class="hidden sm:block">Docs</span>
            </a>
            @include('partials.locale-switcher')
            @include('partials.theme-toggle')
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm font-medium text-slate-500 transition hover:text-slate-700">Log out</button>
            </form>
        </header>

        <main class="p-4 sm:p-6 lg:p-8">
            {{ $slot }}
        </main>
    </div>

    @include('partials.confirm-dialog')
</body>
</html>
