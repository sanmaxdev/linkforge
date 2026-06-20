@props(['placement'])
@php
    // Renders an operator ad for the given placement, but ONLY to free users
    // (premium / ad-free plans never see platform ads). No-op when monetization
    // is off or nothing active is configured.
    $lfAd = null;
    if (\App\Models\Setting::get('ads_enabled') === '1' && auth()->check()
        && ! app(\App\Services\Billing\PlanGate::class)->allows(auth()->user(), 'ad_free')) {
        $lfAd = \App\Models\Advertisement::activeFor($placement);
        if ($lfAd) {
            app()->terminating(fn () => $lfAd->recordImpression());
        }
    }
@endphp
@if ($lfAd)
    <div {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl border border-slate-200 bg-white p-3']) }}>
        <p class="mb-2 text-[11px] font-medium uppercase tracking-wide text-slate-400">{{ __('Advertisement') }}</p>
        @if ($lfAd->code)
            <div class="flex items-center justify-center">{!! $lfAd->code !!}</div>
        @elseif ($lfAd->imageUrl())
            <a href="{{ $lfAd->target_url ?: '#' }}" target="_blank" rel="noopener sponsored">
                <img src="{{ $lfAd->imageUrl() }}" alt="Advertisement" class="mx-auto block max-w-full rounded-lg">
            </a>
        @endif
    </div>
@endif
