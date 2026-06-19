<x-admin-layout title="Advertisement">
    <x-slot:header>Advertisement</x-slot:header>

    @if (session('status'))<div class="mb-5 rounded-lg bg-brand-50 px-4 py-3 text-sm text-brand-700">{{ session('status') }}</div>@endif

    {{-- Master monetization switch --}}
    <form method="POST" action="{{ route('admin.ads.settings') }}" class="lf-card mb-6 flex flex-wrap items-end gap-x-8 gap-y-4 p-5">
        @csrf
        <label class="flex items-center gap-2.5 text-sm font-medium text-slate-700">
            <input type="checkbox" name="ads_enabled" value="1" @checked($adsEnabled) class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500/30">
            Enable link monetization
        </label>
        <div>
            <label for="ads_skip_seconds" class="lf-label">Skip countdown (seconds)</label>
            <input id="ads_skip_seconds" name="ads_skip_seconds" type="number" min="0" max="60" value="{{ $skipSeconds }}" class="lf-input w-28">
        </div>
        <button type="submit" class="ml-auto rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700">Save</button>
    </form>

    <div class="mb-5 flex items-center justify-between gap-3">
        <p class="text-sm text-slate-500">Ads you place here are shown to <span class="font-medium text-slate-700">free</span> users (on their link interstitials and dashboard). Paid / ad-free plans never see them.</p>
        <a href="{{ route('admin.ads.create') }}" class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
            Add Ad
        </a>
    </div>

    @if ($ads->isEmpty())
        <div class="lf-card flex flex-col items-center justify-center px-6 py-16 text-center">
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-600">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="m3 11 18-5v12L3 14v-3zM11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>
            </span>
            <h3 class="mt-4 text-lg font-semibold text-slate-900">No ads yet</h3>
            <p class="mt-1.5 max-w-sm text-sm text-slate-500">Add an ad-network snippet (AdSense, etc.) or a banner image to start monetizing your free users' traffic.</p>
        </div>
    @else
        <div class="lf-card overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                        <th class="px-5 py-3">Name</th>
                        <th class="px-5 py-3">Placement</th>
                        <th class="px-5 py-3 text-right">Impressions</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($ads as $ad)
                        <tr>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2.5">
                                    <span class="h-2 w-2 shrink-0 rounded-full {{ $ad->is_active ? 'bg-emerald-500' : 'bg-slate-300' }}" title="{{ $ad->is_active ? 'Active' : 'Disabled' }}"></span>
                                    <span class="font-medium text-slate-900">{{ $ad->name }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-slate-600">{{ $placements[$ad->placement] ?? $ad->placement }}</td>
                            <td class="px-5 py-3.5 text-right font-medium text-slate-700">{{ number_format($ad->impressions) }}</td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-end gap-1.5">
                                    <form method="POST" action="{{ route('admin.ads.toggle', $ad) }}">
                                        @csrf
                                        <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-50">{{ $ad->is_active ? 'Disable' : 'Enable' }}</button>
                                    </form>
                                    <a href="{{ route('admin.ads.edit', $ad) }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-50">Edit</a>
                                    <form method="POST" action="{{ route('admin.ads.destroy', $ad) }}" data-confirm="Delete this ad?" data-confirm-ok="Delete ad">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="rounded-md p-1.5 text-slate-400 transition hover:bg-red-50 hover:text-red-600" title="Delete">
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
    @endif
</x-admin-layout>
