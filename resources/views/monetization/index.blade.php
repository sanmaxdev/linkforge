<x-app-layout :title="__('Monetization')">
    <x-slot:header>{{ __('Monetization') }}</x-slot:header>

    @if (session('status'))
        <div class="mb-5 rounded-lg bg-brand-50 px-4 py-3 text-sm text-brand-700">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-5 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ session('error') }}</div>
    @endif

    @if (! $allowed)
        <div class="lf-card flex flex-col items-center justify-center px-6 py-16 text-center">
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-600">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </span>
            <h3 class="mt-4 text-lg font-semibold text-slate-900">{{ __('Run your own ads and go ad-free') }}</h3>
            <p class="mt-1.5 max-w-md text-sm text-slate-500">{{ __('On a paid plan, our ads are removed from your links and you can place your own ad-network code on your links interstitial, keeping 100% of the revenue.') }}</p>
            <a href="{{ route('billing.index') }}" class="mt-6 inline-flex items-center gap-2 rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">{{ __('View plans') }}</a>
        </div>
    @else
        <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
            <form method="POST" action="{{ route('monetization.update') }}" class="lf-card p-6">
                @csrf @method('PUT')
                <h3 class="text-sm font-semibold text-slate-900">{{ __('Your ad slots') }}</h3>
                <p class="mt-1.5 text-sm text-slate-500">{{ __('Each slot runs on the short interstitial shown before your links open, stacked top to bottom. Paste a snippet from any ad network. Leave a slot blank to skip it.') }}</p>

                @php $labels = [__('Slot 1 · top'), __('Slot 2 · middle'), __('Slot 3 · bottom')]; @endphp
                <div class="mt-4 space-y-4">
                    @foreach ($slots as $i => $code)
                        <div>
                            <label class="lf-label" for="slot{{ $i }}">{{ $labels[$i] ?? __('Slot :n', ['n' => $i + 1]) }}</label>
                            <textarea id="slot{{ $i }}" name="ad_slots[]" rows="5" class="lf-input font-mono text-xs" placeholder="&lt;script&gt;…&lt;/script&gt; or &lt;a&gt;&lt;img&gt;…">{{ old('ad_slots.'.$i, $code) }}</textarea>
                        </div>
                    @endforeach
                    @error('ad_slots.*')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="mt-5 flex justify-end">
                    <button type="submit" class="lf-btn">{{ __('Save ad slots') }}</button>
                </div>
            </form>

            <div class="space-y-5">
                <div class="lf-card p-6 text-sm text-slate-600">
                    <h3 class="text-sm font-semibold text-slate-900">{{ __('How it works') }}</h3>
                    <ul class="mt-3 space-y-2.5">
                        <li class="flex gap-2"><span class="text-brand-600">&bull;</span> {{ __("Your plan removes the platform's ads from your links.") }}</li>
                        <li class="flex gap-2"><span class="text-brand-600">&bull;</span> {{ __('Your slots are shown instead, on your links interstitial page.') }}</li>
                        <li class="flex gap-2"><span class="text-brand-600">&bull;</span> {{ __('Each slot is sandboxed for safety, so script/banner tags from self-serve networks work.') }}</li>
                        <li class="flex gap-2"><span class="text-brand-600">&bull;</span> {{ __('Networks that require same-origin (e.g. Google AdSense, which also restricts interstitials) may not render in the sandbox.') }}</li>
                    </ul>
                </div>

                <div class="lf-card p-6 text-sm text-slate-600">
                    <h3 class="text-sm font-semibold text-slate-900">{{ __('Example formats') }}</h3>
                    <p class="mt-3 text-xs text-slate-400">{{ __('Script tag (Adsterra, PropellerAds, etc.):') }}</p>
                    <pre class="mt-1 overflow-x-auto rounded-lg bg-slate-100 p-2 text-[11px] text-slate-700">&lt;script src="//ad.network/tag.js"&gt;&lt;/script&gt;</pre>
                    <p class="mt-3 text-xs text-slate-400">{{ __('Direct image banner:') }}</p>
                    <pre class="mt-1 overflow-x-auto rounded-lg bg-slate-100 p-2 text-[11px] text-slate-700">&lt;a href="https://advertiser.com"&gt;
  &lt;img src="https://.../banner.png"&gt;
&lt;/a&gt;</pre>
                </div>
            </div>
        </div>
    @endif
</x-app-layout>
