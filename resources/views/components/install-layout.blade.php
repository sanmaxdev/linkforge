@props(['title' => 'Install', 'step' => 'welcome'])
@php
    $steps = ['welcome' => 'Requirements', 'database' => 'Database', 'account' => 'Admin', 'complete' => 'Done'];
    $currentIndex = array_search($step, array_keys($steps), true) ?: 0;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · {{ config('linkforge.name') }}</title>
    @vite(['resources/css/app.css'])
    @include('partials.favicon')
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <div class="mx-auto flex min-h-screen max-w-2xl flex-col px-4 py-10 sm:py-14">
        <div class="mb-8 flex items-center gap-2.5">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl text-white" style="background-image:linear-gradient(135deg,var(--color-brand-500),var(--color-brand-700))">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13.19 8.69a4.5 4.5 0 0 1 1.24 7.24l-4.5 4.5a4.5 4.5 0 0 1-6.36-6.36l1.75-1.76M10.81 15.31a4.5 4.5 0 0 1-1.24-7.24l4.5-4.5a4.5 4.5 0 0 1 6.36 6.36l-1.75 1.76"/></svg>
            </span>
            <span class="text-lg font-semibold tracking-tight">{{ config('linkforge.name') }}</span>
            <span class="rounded-full bg-slate-900 px-2 py-0.5 text-[11px] font-medium text-white">Installer</span>
        </div>

        {{-- Step indicator --}}
        <ol class="mb-8 flex flex-wrap items-center gap-x-2 gap-y-2 text-xs font-medium">
            @foreach ($steps as $key => $label)
                @php $i = $loop->index; $done = $i < $currentIndex; $active = $i === $currentIndex; @endphp
                <li class="flex items-center gap-2">
                    <span @class([
                        'flex h-6 w-6 items-center justify-center rounded-full text-[11px]',
                        'bg-brand-600 text-white' => $active,
                        'bg-brand-100 text-brand-700' => $done,
                        'bg-slate-200 text-slate-500' => ! $active && ! $done,
                    ])>
                        @if ($done)
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                        @else
                            {{ $i + 1 }}
                        @endif
                    </span>
                    <span @class(['hidden sm:block', 'text-slate-900' => $active, 'text-slate-400' => ! $active])>{{ $label }}</span>
                </li>
                @unless ($loop->last)<li aria-hidden="true" class="h-px w-3 bg-slate-300 sm:w-5"></li>@endunless
            @endforeach
        </ol>

        @if (session('error'))<div class="mb-5 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>@endif

        <div class="lf-card p-6 sm:p-8">
            {{ $slot }}
        </div>

        <p class="mt-6 text-center text-xs text-slate-400">{{ config('linkforge.name') }} v{{ config('linkforge.version') }}</p>
    </div>
</body>
</html>
