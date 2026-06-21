<x-site-layout title="Help Center" metaDescription="Guides and answers for getting the most out of {{ config('linkforge.name') }}.">
    {{-- Hero --}}
    <section class="border-b border-slate-200/70 bg-gradient-to-b from-brand-50/50 to-white">
        <div class="mx-auto max-w-3xl px-6 py-16 text-center">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 text-xs font-medium text-brand-700">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01"/></svg>
                Help Center
            </span>
            <h1 class="mt-4 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">How can we help?</h1>
            <p class="mx-auto mt-3 max-w-xl text-slate-500">Guides and answers for getting the most out of {{ config('linkforge.name') }}.</p>
            <div class="relative mx-auto mt-7 max-w-lg">
                <svg class="pointer-events-none absolute top-1/2 left-4 h-5 w-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                <input id="help-search" type="text" placeholder="Search articles…" class="w-full rounded-xl border border-slate-200 bg-white py-3 pr-4 pl-11 text-sm text-slate-700 shadow-sm outline-none transition focus:border-brand-400 focus:ring-2 focus:ring-brand-500/20">
            </div>
        </div>
    </section>

    <div class="mx-auto max-w-6xl px-6 py-12">
        @if ($groups->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-200 px-6 py-20 text-center">
                <h2 class="text-lg font-semibold text-slate-900">No articles yet</h2>
                <p class="mt-1.5 text-sm text-slate-500">Check back soon.</p>
            </div>
        @else
            @php
                $icons = [
                    'Getting started' => 'M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09zM12 15l-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2zM9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5',
                    'Short links' => 'M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71',
                    'QR codes' => 'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM17 14h.01M14 17h.01M21 21h.01M21 17h.01M17 21h.01',
                    'Bio pages' => 'M5 2h14a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1zM12 18h.01',
                    'Custom domains' => 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20zM2 12h20M12 2a15 15 0 0 1 0 20 15 15 0 0 1 0-20',
                    'Analytics' => 'M3 3v18h18M7 16V9M12 16V5M17 16v-7',
                    'Marketing' => 'm3 11 18-5v12L3 14v-3zM11.6 16.8a3 3 0 1 1-5.8-1.6',
                    'Developers & API' => 'm16 18 6-6-6-6M8 6l-6 6 6 6',
                    'Account & security' => 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z',
                    'Billing & plans' => 'M2 7h20v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1zM2 7l1-3h18l1 3M6 15h4',
                ];
                $fallbackIcon = 'M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zM8 7h8M8 11h8M8 15h5';
            @endphp

            <div id="help-groups" class="gap-6 md:columns-2 xl:columns-3">
                @foreach ($groups as $category => $articles)
                    <div class="help-group mb-6 break-inside-avoid overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" data-category="{{ \Illuminate\Support\Str::lower($category) }}">
                        <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icons[$category] ?? $fallbackIcon }}"/></svg>
                            </span>
                            <div class="min-w-0">
                                <h2 class="font-semibold text-slate-900">{{ $category }}</h2>
                                <p class="text-xs text-slate-400">{{ $articles->count() }} {{ \Illuminate\Support\Str::plural('article', $articles->count()) }}</p>
                            </div>
                        </div>
                        <ul class="divide-y divide-slate-100">
                            @foreach ($articles as $article)
                                <li class="help-item" data-text="{{ \Illuminate\Support\Str::lower($article->title.' '.$article->excerpt) }}">
                                    <a href="{{ route('help.show', $article->slug) }}" class="group flex items-center justify-between gap-3 px-5 py-3 transition hover:bg-slate-50">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-slate-800 group-hover:text-brand-700">{{ $article->title }}</p>
                                            @if ($article->excerpt)<p class="truncate text-xs text-slate-400">{{ $article->excerpt }}</p>@endif
                                        </div>
                                        <svg class="h-4 w-4 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-brand-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
            <p id="help-empty" class="hidden py-12 text-center text-sm text-slate-400">No articles match your search.</p>

            {{-- Still need help --}}
            <div class="mt-10 rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                <h3 class="text-lg font-semibold text-slate-900">Still need a hand?</h3>
                <p class="mx-auto mt-1.5 max-w-md text-sm text-slate-500">Can't find what you're looking for? Our team is happy to help.</p>
                <a href="{{ route('support.index') }}" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    Contact support
                </a>
            </div>
        @endif
    </div>

    <script>
        (function () {
            var input = document.getElementById('help-search'); if (!input) return;
            var items = [].slice.call(document.querySelectorAll('.help-item'));
            var groups = [].slice.call(document.querySelectorAll('.help-group'));
            var empty = document.getElementById('help-empty');
            input.addEventListener('input', function () {
                var q = input.value.trim().toLowerCase(); var any = false;
                items.forEach(function (li) { var show = !q || li.dataset.text.indexOf(q) !== -1; li.style.display = show ? '' : 'none'; if (show) any = true; });
                groups.forEach(function (g) { var visible = g.querySelectorAll('.help-item:not([style*="none"])').length; g.style.display = visible ? '' : 'none'; });
                empty.classList.toggle('hidden', any);
            });
        })();
    </script>
</x-site-layout>
