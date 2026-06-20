<x-app-layout title="Links">
    <x-slot:header>Links</x-slot:header>

    @if (session('status'))
        <div class="mb-5 rounded-lg bg-brand-50 px-4 py-3 text-sm text-brand-700">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-5 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ session('error') }}</div>
    @endif

    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form method="GET" class="flex w-full flex-col gap-2 sm:max-w-lg sm:flex-row">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                <input name="q" value="{{ $q }}" class="lf-input pl-9" placeholder="Search links...">
            </div>
            @if ($campaigns->isNotEmpty())
                <select name="campaign" class="lf-input sm:w-44" onchange="this.form.submit()">
                    <option value="">All campaigns</option>
                    @foreach ($campaigns as $c)
                        <option value="{{ $c->id }}" @selected($campaignId === (int) $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            @endif
            @if ($tag !== '')<input type="hidden" name="tag" value="{{ $tag }}">@endif
        </form>
        <div class="flex shrink-0 items-center gap-2">
            <a href="{{ route('links.bulk') }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
                Bulk / import
            </a>
            <a href="{{ route('links.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                New link
            </a>
        </div>
    </div>

    @if ($tag !== '' || $campaignId)
        <div class="mb-4 flex flex-wrap items-center gap-2 text-xs">
            <span class="text-slate-400">Filtered by</span>
            @if ($campaignId)
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-600">{{ $campaigns->firstWhere('id', $campaignId)?->name ?? 'campaign' }}</span>
            @endif
            @if ($tag !== '')
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-600">#{{ $tag }}</span>
            @endif
            <a href="{{ route('links.index') }}" class="font-medium text-brand-600 hover:underline">Clear</a>
        </div>
    @endif

    @if ($links->isEmpty())
        <div class="lf-card flex flex-col items-center justify-center px-6 py-16 text-center">
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl text-white" style="background-image:linear-gradient(135deg,var(--color-brand-500),var(--color-brand-700))">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757"/><path d="M10.81 15.312a4.5 4.5 0 0 1-1.242-7.244l4.5-4.5a4.5 4.5 0 0 1 6.364 6.364l-1.757 1.757"/></svg>
            </span>
            @php $filtered = $q !== '' || $tag !== '' || $campaignId; @endphp
            <h3 class="mt-4 text-lg font-semibold text-slate-900">{{ $filtered ? 'No links match your filters' : 'No links yet' }}</h3>
            <p class="mt-1.5 max-w-sm text-sm text-slate-500">{{ $filtered ? 'Try a different search or clear the filters.' : 'Create your first short link to start tracking clicks.' }}</p>
            @if (! $filtered)
                <a href="{{ route('links.create') }}" class="mt-6 inline-flex items-center gap-2 rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">Create a short link</a>
            @endif
        </div>
    @else
        <div class="lf-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs tracking-wide text-slate-400 uppercase">
                        <tr>
                            <th class="px-5 py-3 font-medium">Short link</th>
                            <th class="px-5 py-3 font-medium">Destination</th>
                            <th class="px-5 py-3 font-medium">Clicks</th>
                            <th class="px-5 py-3 font-medium">Status</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($links as $link)
                            @php
                                $short = $link->shortUrl();
                                $url = request()->getScheme().'://'.$short;
                                if ($link->is_active && ! $link->isExpired() && ! $link->isOverLimit()) {
                                    $badge = ['Active', 'bg-brand-50 text-brand-700'];
                                } elseif ($link->isExpired()) {
                                    $badge = ['Expired', 'bg-slate-100 text-slate-500'];
                                } elseif (! $link->is_active) {
                                    $badge = ['Inactive', 'bg-slate-100 text-slate-500'];
                                } else {
                                    $badge = ['Limit reached', 'bg-amber-50 text-amber-700'];
                                }
                            @endphp
                            <tr class="hover:bg-slate-50/60">
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ $url }}" target="_blank" rel="noopener" class="font-medium text-brand-700 hover:underline">{{ $short }}</a>
                                        <button type="button" data-copy="{{ $url }}" class="text-slate-400 transition hover:text-slate-600" title="Copy link" aria-label="Copy link">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>
                                        </button>
                                    </div>
                                    @if ($link->title)
                                        <div class="mt-0.5 text-xs text-slate-400">{{ $link->title }}</div>
                                    @endif
                                    @if ($link->campaign || ! empty($link->tags))
                                        <div class="mt-1 flex flex-wrap items-center gap-1">
                                            @if ($link->campaign)
                                                <a href="{{ route('links.index', ['campaign' => $link->campaign->id]) }}" class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-medium text-brand-700 hover:bg-brand-100">
                                                    <span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span>{{ $link->campaign->name }}
                                                </a>
                                            @endif
                                            @foreach (($link->tags ?? []) as $t)
                                                <a href="{{ route('links.index', ['tag' => $t]) }}" class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-500 hover:bg-slate-200">#{{ $t }}</a>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="max-w-xs px-5 py-3.5">
                                    <span class="block truncate text-slate-500" title="{{ $link->long_url }}">{{ $link->long_url }}</span>
                                </td>
                                <td class="px-5 py-3.5 text-slate-700">{{ number_format($link->clicks) }}</td>
                                <td class="px-5 py-3.5">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badge[1] }}">{{ $badge[0] }}</span>
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <a href="{{ route('links.qr', $link) }}" class="rounded-md p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" title="QR code" aria-label="QR code">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3M21 14v.01M14 21h.01M17 21h4v-4"/></svg>
                                        </a>
                                        <a href="{{ route('links.stats', $link) }}" class="rounded-md p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" title="Analytics" aria-label="Analytics">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18M7 14l3-3 3 3 5-6"/></svg>
                                        </a>
                                        <a href="{{ route('links.edit', $link) }}" class="rounded-md p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" title="Edit" aria-label="Edit">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                                        </a>
                                        <form method="POST" action="{{ route('links.destroy', $link) }}" data-confirm="Delete this link? This cannot be undone." data-confirm-ok="Delete link">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-md p-1.5 text-slate-400 transition hover:bg-red-50 hover:text-red-600" title="Delete" aria-label="Delete">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-5">
            {{ $links->links() }}
        </div>
    @endif

    <script>
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-copy]');
            if (!btn) return;
            navigator.clipboard.writeText(btn.getAttribute('data-copy')).then(function () {
                btn.classList.add('text-brand-600');
                setTimeout(function () { btn.classList.remove('text-brand-600'); }, 1200);
            });
        });
    </script>
</x-app-layout>
