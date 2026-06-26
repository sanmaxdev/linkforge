@if (\App\Support\Demo::enabled())
    @php $lfBuy = \App\Support\Demo::buyUrl(); @endphp

    {{-- Persistent demo bar --}}
    <div class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 bg-brand-600 px-4 py-2 text-center text-sm font-medium text-white">
        <span class="inline-flex items-center gap-1.5">
            <span class="h-2 w-2 animate-pulse rounded-full bg-white"></span>
            You're exploring a live demo of {{ config('linkforge.name') }} — sample data resets periodically.
        </span>
        <a href="{{ $lfBuy }}" target="_blank" rel="noopener" class="rounded-md bg-white px-3 py-0.5 text-xs font-semibold text-brand-700 transition hover:bg-brand-50">View source →</a>
    </div>

    {{-- One-time conversion popup --}}
    <div id="lf-demo-popup" class="fixed inset-0 z-[60] hidden items-center justify-center bg-slate-900/50 p-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 text-center shadow-2xl">
            <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-600">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M13.19 8.69a4.5 4.5 0 0 1 1.24 7.24l-4.5 4.5a4.5 4.5 0 0 1-6.36-6.36l1.75-1.76M10.81 15.31a4.5 4.5 0 0 1-1.24-7.24l4.5-4.5a4.5 4.5 0 0 1 6.36 6.36l-1.75 1.76"/></svg>
            </span>
            <h3 class="mt-4 text-lg font-bold text-slate-900">Like what you see?</h3>
            <p class="mt-2 text-sm text-slate-500">This is the live demo of {{ config('linkforge.name') }}. It's free and open source — self-host your own with branded domains, deep analytics, a QR studio, monetization, affiliates and more.</p>
            <div class="mt-5 flex flex-col gap-2">
                <a href="{{ $lfBuy }}" target="_blank" rel="noopener" class="lf-btn">View on GitHub →</a>
                <button type="button" data-demo-dismiss class="text-sm font-medium text-slate-400 hover:text-slate-600">Keep exploring</button>
            </div>
        </div>
    </div>
    <script>
        (function () {
            var pop = document.getElementById('lf-demo-popup'); if (!pop) return;
            function show() { pop.classList.remove('hidden'); pop.classList.add('flex'); }
            function hide() { pop.classList.add('hidden'); pop.classList.remove('flex'); try { sessionStorage.setItem('lf-demo-pop', '1'); } catch (e) {} }
            pop.querySelector('[data-demo-dismiss]').addEventListener('click', hide);
            pop.addEventListener('click', function (e) { if (e.target === pop) hide(); });
            try { if (!sessionStorage.getItem('lf-demo-pop')) setTimeout(show, 25000); } catch (e) {}
        })();
    </script>
@endif
