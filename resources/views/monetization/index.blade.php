<x-app-layout title="Monetization">
    <x-slot:header>Monetization</x-slot:header>

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
            <h3 class="mt-4 text-lg font-semibold text-slate-900">Run your own ads &amp; go ad-free</h3>
            <p class="mt-1.5 max-w-md text-sm text-slate-500">On a paid plan, our ads are removed from your links and you can place <span class="font-medium text-slate-700">your own ad-network code</span> (Google AdSense, etc.) on your links' interstitial pages, keeping 100% of the revenue.</p>
            <a href="{{ route('billing.index') }}" class="mt-6 inline-flex items-center gap-2 rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">View plans</a>
        </div>
    @else
        <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
            <form method="POST" action="{{ route('monetization.update') }}" class="lf-card p-6">
                @csrf @method('PUT')
                <h3 class="text-sm font-semibold text-slate-900">Your ad code</h3>
                <p class="mt-1.5 text-sm text-slate-500">Paste an ad-network snippet (e.g. Google AdSense). It runs on a short interstitial page when someone opens one of your links, so you earn from your own traffic. Leave blank to show no ad.</p>
                <textarea name="ad_code" rows="9" class="lf-input mt-4 font-mono text-xs" placeholder="&lt;script async src=&quot;https://pagead2.googlesyndication.com/...&quot;&gt;&lt;/script&gt;&#10;&lt;ins class=&quot;adsbygoogle&quot; ...&gt;&lt;/ins&gt;">{{ old('ad_code', $adCode) }}</textarea>
                @error('ad_code')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                <div class="mt-4 flex justify-end">
                    <button type="submit" class="lf-btn">Save ad code</button>
                </div>
            </form>

            <div class="lf-card h-fit p-6 text-sm text-slate-600">
                <h3 class="text-sm font-semibold text-slate-900">How it works</h3>
                <ul class="mt-3 space-y-2.5">
                    <li class="flex gap-2"><span class="text-brand-600">&bull;</span> Your plan removes the platform's ads from your links.</li>
                    <li class="flex gap-2"><span class="text-brand-600">&bull;</span> Your code is shown instead, on your links' interstitial page.</li>
                    <li class="flex gap-2"><span class="text-brand-600">&bull;</span> It runs sandboxed for safety, so most banner/display tags work; some interactive formats may not.</li>
                    <li class="flex gap-2"><span class="text-brand-600">&bull;</span> Direct links still redirect instantly when you have no ad code set.</li>
                </ul>
            </div>
        </div>
    @endif
</x-app-layout>
