<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('linkforge.name') }}</title>
    @vite(['resources/css/app.css'])
    @include('partials.theme')
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-50 px-6">
    <div class="w-full max-w-md text-center">
        <x-application-logo size="h-14 w-14" class="mx-auto" />
        <p class="mt-6 text-5xl font-bold tracking-tight text-brand-600">@yield('code')</p>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">@yield('title')</h1>
        <p class="mt-3 text-sm leading-relaxed text-slate-500">@yield('message')</p>
        <a href="{{ url('/') }}" class="mt-8 inline-flex items-center justify-center gap-2 rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700">{{ __('Back to home') }}</a>
    </div>
</body>
</html>
