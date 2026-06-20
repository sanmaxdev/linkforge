<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Opening app…') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.theme')
    @foreach ($pixels as $pixel)
        @include('redirect.partials.pixel', ['pixel' => $pixel])
    @endforeach
</head>
<body class="min-h-screen bg-slate-50">
    <div class="flex min-h-screen flex-col items-center justify-center gap-6 p-6 text-center">
        <x-application-logo size="h-10 w-10" />

        <div class="h-8 w-8 animate-spin rounded-full border-2 border-slate-200 border-t-brand-600"></div>

        <div>
            <p class="text-sm text-slate-500">{{ __('Opening the app…') }}</p>
            <div class="mt-4 flex flex-col items-center gap-2">
                <a id="lf-app" href="{{ $appUrl }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700">{{ __('Open in app') }}</a>
                <a id="lf-web" href="{{ $target }}" class="text-xs font-medium text-slate-400 hover:text-slate-600">{{ __('Continue to website') }}</a>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var appUrl = @json($appUrl);
            var target = @json($target);

            // Fall back to the website if the app didn't open (not installed).
            var fellBack = false;
            var timer = setTimeout(function () { fellBack = true; window.location.replace(target); }, 2200);

            // If the app opens, the page is backgrounded — cancel the web fallback.
            function cancel() { clearTimeout(timer); }
            document.addEventListener('visibilitychange', function () { if (document.hidden) cancel(); });
            window.addEventListener('pagehide', cancel);
            window.addEventListener('blur', cancel);

            // Attempt to launch the app.
            window.location.href = appUrl;
        })();
    </script>
</body>
</html>
