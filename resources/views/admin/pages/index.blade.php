<x-admin-layout title="Pages">
    <x-slot:header>Pages</x-slot:header>

    <div class="mb-5 flex items-center justify-between gap-4">
        <p class="text-sm text-slate-500">Editable site pages (Terms, Privacy, Contact, or any custom page), rendered at <code class="rounded bg-slate-100 px-1 text-[11px]">/page/&lt;slug&gt;</code>.</p>
        <a href="{{ route('admin.pages.create') }}" class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
            New page
        </a>
    </div>

    @if (session('status'))<div class="mb-5 rounded-lg bg-brand-50 px-4 py-3 text-sm text-brand-700">{{ session('status') }}</div>@endif

    <div class="lf-card divide-y divide-slate-100">
        @forelse ($pages as $page)
            <div class="flex items-center justify-between gap-3 px-5 py-3.5">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="truncate font-medium text-slate-800">{{ $page->title }}</span>
                        <span class="rounded-full px-2 py-0.5 text-[11px] font-medium {{ $page->status === 'published' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ ucfirst($page->status) }}</span>
                        @if ($page->show_in_footer)<span class="rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-medium text-brand-700">Footer</span>@endif
                    </div>
                    <p class="mt-0.5 truncate font-mono text-xs text-slate-400">/page/{{ $page->slug }}</p>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    @if ($page->status === 'published')
                        <a href="{{ url('/page/'.$page->slug) }}" target="_blank" rel="noopener" class="rounded-md p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" title="View">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14 21 3"/></svg>
                        </a>
                    @endif
                    <a href="{{ route('admin.pages.edit', $page) }}" class="rounded-md p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" title="Edit">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                    </a>
                    <form method="POST" action="{{ route('admin.pages.destroy', $page) }}" data-confirm="Delete the page '{{ $page->title }}'?" data-confirm-ok="Delete page">
                        @csrf @method('DELETE')
                        <button type="submit" class="rounded-md p-1.5 text-slate-400 transition hover:bg-red-50 hover:text-red-600" title="Delete">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="px-5 py-10 text-center text-sm text-slate-400">No pages yet. <a href="{{ route('admin.pages.create') }}" class="text-brand-600 hover:underline">Create one</a>.</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $pages->links() }}</div>
</x-admin-layout>
