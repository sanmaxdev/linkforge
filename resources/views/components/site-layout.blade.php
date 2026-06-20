@props([
    'title' => null,
    'metaDescription' => null,
    'ogTitle' => null,
    'ogDescription' => null,
    'ogImage' => null,
    'ogType' => null,
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · '.config('linkforge.name') : config('linkforge.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.theme')
    @include('partials.head-extra', [
        'metaDescription' => $metaDescription,
        'ogTitle' => $ogTitle ?? $title,
        'ogDescription' => $ogDescription,
        'ogImage' => $ogImage,
        'ogType' => $ogType,
    ])
</head>
<body class="flex min-h-screen flex-col bg-slate-50 text-slate-900 antialiased">
    <header class="sticky top-0 z-40 border-b border-slate-200/70 bg-white/80 backdrop-blur-md dark:bg-[#0f172a]/80">
        <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-2 px-5 sm:px-6">
            <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2.5">
                <x-application-logo size="h-8 w-8" />
                <span class="text-base font-semibold tracking-tight text-slate-900">{{ config('linkforge.name') }}</span>
            </a>
            <nav class="flex items-center gap-0.5 sm:gap-1">
                <a href="{{ route('home') }}#analytics" class="hidden rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:text-slate-900 md:inline-flex">Analytics</a>
                <a href="{{ route('home') }}#features" class="hidden rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:text-slate-900 md:inline-flex">Features</a>
                <a href="{{ route('home') }}#own" class="hidden rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:text-slate-900 md:inline-flex">Self-hosted</a>
                <a href="{{ route('blog.index') }}" @class(['rounded-lg px-2.5 py-2 text-sm font-medium transition sm:px-3', 'text-brand-700' => request()->routeIs('blog.*'), 'text-slate-600 hover:text-slate-900' => ! request()->routeIs('blog.*')])>Blog</a>
                <a href="{{ route('help.index') }}" @class(['rounded-lg px-2.5 py-2 text-sm font-medium transition sm:px-3', 'text-brand-700' => request()->routeIs('help.*'), 'text-slate-600 hover:text-slate-900' => ! request()->routeIs('help.*')])>Help</a>
            </nav>
            <div class="flex shrink-0 items-center gap-1.5">
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

    <main class="flex-1">{{ $slot }}</main>

    <footer class="border-t border-slate-200/70 bg-white">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-6 py-8 sm:flex-row">
            <div class="flex items-center gap-2.5">
                <x-application-logo size="h-7 w-7" />
                <span class="text-sm font-semibold text-slate-900">{{ config('linkforge.name') }}</span>
            </div>
            <nav class="flex items-center gap-5 text-sm text-slate-500">
                <a href="{{ route('blog.index') }}" class="hover:text-slate-800">Blog</a>
                <a href="{{ route('help.index') }}" class="hover:text-slate-800">Help</a>
                <a href="{{ route('register') }}" class="hover:text-slate-800">Get started</a>
            </nav>
            <p class="text-xs text-slate-400">&copy; {{ date('Y') }} {{ config('linkforge.name') }}.</p>
        </div>
    </footer>
</body>
</html>
