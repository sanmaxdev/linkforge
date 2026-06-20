@php
    $lfPopAd = null;
    if (\App\Models\Setting::get('ads_enabled') === '1' && auth()->check()
        && ! app(\App\Services\Billing\PlanGate::class)->allows(auth()->user(), 'ad_free')) {
        $lfPopAd = \App\Models\Advertisement::activeFor('popup');
        if ($lfPopAd) {
            app()->terminating(fn () => $lfPopAd->recordImpression());
        }
    }
@endphp

@if ($lfPopAd)
    <div id="lf-ad-popup" role="dialog" aria-modal="true" aria-label="{{ __('Advertisement') }}"
         class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
        <div class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-xl">
            <button type="button" data-lf-ad-close aria-label="{{ __('Close') }}" class="absolute right-3 top-3 rounded-md p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
            <p class="mb-2 text-[11px] font-medium uppercase tracking-wide text-slate-400">{{ __('Advertisement') }}</p>
            @if ($lfPopAd->code)
                <div class="flex items-center justify-center">{!! $lfPopAd->code !!}</div>
            @elseif ($lfPopAd->imageUrl())
                <a href="{{ $lfPopAd->target_url ?: '#' }}" target="_blank" rel="noopener sponsored">
                    <img src="{{ $lfPopAd->imageUrl() }}" alt="Advertisement" class="mx-auto block max-w-full rounded-lg">
                </a>
            @endif
        </div>
    </div>
    <script>
        (function () {
            var key = 'lf_ad_popup_seen';
            if (sessionStorage.getItem(key)) return;
            var el = document.getElementById('lf-ad-popup');
            if (!el) return;
            var closeBtn = el.querySelector('[data-lf-ad-close]');
            function close() {
                el.classList.add('hidden'); el.classList.remove('flex');
                document.removeEventListener('keydown', onKey);
                sessionStorage.setItem(key, '1');
            }
            function onKey(e) { if (e.key === 'Escape') close(); }
            setTimeout(function () {
                el.classList.remove('hidden'); el.classList.add('flex');
                document.addEventListener('keydown', onKey);
                if (closeBtn) closeBtn.focus();
            }, 1200);
            el.addEventListener('click', function (e) { if (e.target === el) close(); });
            closeBtn.addEventListener('click', close);
        })();
    </script>
@endif
