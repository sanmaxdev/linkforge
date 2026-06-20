<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Redirecting') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.theme')
    @foreach ($pixels as $pixel)
        @include('redirect.partials.pixel', ['pixel' => $pixel])
    @endforeach
</head>
<body class="min-h-screen bg-slate-50">
    @php
        $ad = $ad ?? null;
        $skipSeconds = (int) ($skipSeconds ?? 0);
        // When an ad is shown, hold for at least 3s so it is actually viewable even if the
        // operator left the skip countdown at 0; no ad -> quick hand-off.
        $gate = $ad ? max($skipSeconds, 3) : 0;
    @endphp
    <div class="flex min-h-screen flex-col items-center justify-center gap-6 p-6 text-center">
        <x-application-logo size="h-10 w-10" />

        @if ($ad)
            <div class="w-full max-w-[760px] overflow-hidden rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                <p class="mb-2 text-[11px] font-medium uppercase tracking-wide text-slate-400">{{ __('Advertisement') }}</p>
                @if (! empty($ad['own']))
                    {{-- Member-supplied ad code: isolated in a sandboxed iframe so it cannot touch this domain. --}}
                    <iframe title="{{ __('Advertisement') }}" loading="lazy"
                            sandbox="allow-scripts allow-popups allow-popups-to-escape-sandbox allow-forms"
                            srcdoc="{{ '<!doctype html><meta charset=utf-8><body style=margin:0;display:flex;justify-content:center>'.$ad['code'] }}"
                            class="mx-auto block h-[260px] w-full border-0"></iframe>
                @elseif (! empty($ad['code']))
                    {{-- Operator ad code (admin-entered, trusted). --}}
                    <div class="flex min-h-[100px] items-center justify-center">{!! $ad['code'] !!}</div>
                @elseif (! empty($ad['image']))
                    <a href="{{ $ad['url'] ?: $target }}" target="_blank" rel="noopener sponsored">
                        <img src="{{ $ad['image'] }}" alt="{{ __('Advertisement') }}" class="mx-auto block max-w-full rounded-lg">
                    </a>
                @endif
            </div>
        @else
            <div class="h-8 w-8 animate-spin rounded-full border-2 border-slate-200 border-t-brand-600"></div>
        @endif

        <div>
            <p class="text-sm text-slate-500">{{ __('Taking you to your destination') }}</p>
            <a id="lf-continue" href="{{ $target }}"
               class="mt-3 inline-flex items-center gap-2 rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700 {{ $gate > 0 ? 'pointer-events-none opacity-50' : '' }}">
                <span id="lf-continue-label">{{ $gate > 0 ? __('Continue in :n', ['n' => $gate]) : __('Continue now') }}</span>
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </a>
        </div>
    </div>

    <script>
        (function () {
            var target = @json($target);
            var gate = {{ $gate }};
            var btn = document.getElementById('lf-continue');
            var label = document.getElementById('lf-continue-label');
            var nowText = @json(__('Continue now'));
            var inText = @json(__('Continue in :n'));

            function go() { window.location.href = target; }
            if (gate < 1) { setTimeout(go, 1500); return; }

            var left = gate;
            var timer = setInterval(function () {
                left -= 1;
                if (left > 0) { label.textContent = inText.replace(':n', left); return; }
                clearInterval(timer);
                label.textContent = nowText;
                btn.classList.remove('pointer-events-none', 'opacity-50');
                go();
            }, 1000);
        })();
    </script>
</body>
</html>
