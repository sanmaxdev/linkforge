<x-app-layout title="Bulk &amp; import">
    <x-slot:header>Bulk &amp; import</x-slot:header>

    <div class="mb-5">
        <a href="{{ route('links.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-slate-700">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            Back to links
        </a>
    </div>

    @if ($summary = session('bulk'))
        @php $skipped = array_sum($summary['skipped']); @endphp
        <div class="mb-6 rounded-xl border border-brand-200 bg-brand-50 p-5">
            <div class="flex items-center gap-2 text-brand-800">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                <p class="font-semibold">{{ $summary['created'] }} {{ \Illuminate\Support\Str::plural('link', $summary['created']) }} created{{ $summary['renamed'] ? ', '.$summary['renamed'].' auto-renamed' : '' }}.</p>
            </div>
            @if ($skipped || $summary['truncated'])
                <ul class="mt-2 ml-7 list-disc text-sm text-brand-700/90">
                    @if ($summary['skipped']['invalid']) <li>{{ $summary['skipped']['invalid'] }} skipped — not a valid URL</li> @endif
                    @if ($summary['skipped']['duplicate']) <li>{{ $summary['skipped']['duplicate'] }} skipped — duplicate in the batch</li> @endif
                    @if ($summary['skipped']['unsafe']) <li>{{ $summary['skipped']['unsafe'] }} skipped — failed safety screening</li> @endif
                    @if ($summary['skipped']['limit']) <li>{{ $summary['skipped']['limit'] }} skipped — plan link limit reached</li> @endif
                    @if ($summary['truncated']) <li>Only the first {{ $maxRows }} rows were processed</li> @endif
                </ul>
            @endif
            @if ($summary['created'])
                <a href="{{ route('links.index') }}" class="mt-3 ml-7 inline-block text-sm font-semibold text-brand-700 hover:underline">View your links →</a>
            @endif
        </div>
    @endif

    @if (! is_null($remaining))
        <p class="mb-5 text-sm text-slate-500">Your plan allows <span class="font-semibold text-slate-700">{{ number_format($remaining) }}</span> more {{ \Illuminate\Support\Str::plural('link', $remaining) }}.</p>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Paste a list of URLs --}}
        <div class="lf-card p-6">
            <h3 class="text-base font-semibold text-slate-900">Bulk shorten</h3>
            <p class="mt-1 text-sm text-slate-500">Paste one URL per line. Each gets an auto-generated short link.</p>
            <form method="POST" action="{{ route('links.bulk.store') }}" class="mt-4 space-y-4">
                @csrf
                <textarea name="urls" rows="9" class="lf-input font-mono text-xs" placeholder="https://example.com/page-one&#10;https://example.com/page-two&#10;https://example.com/page-three" required>{{ old('urls') }}</textarea>
                @error('urls') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                @include('links.partials.bulk-meta')
                <button type="submit" class="lf-btn">Shorten all</button>
            </form>
        </div>

        {{-- Import a CSV / Bitly export --}}
        <div class="lf-card p-6">
            <h3 class="text-base font-semibold text-slate-900">Import from CSV</h3>
            <p class="mt-1 text-sm text-slate-500">Upload a CSV or a <span class="font-medium">Bitly export</span>. Columns are auto-detected.</p>
            <form method="POST" action="{{ route('links.import') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                <label class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-200 bg-slate-50/60 px-4 py-8 text-center transition hover:border-brand-300 hover:bg-brand-50/40">
                    <svg class="h-7 w-7 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
                    <span class="text-sm font-medium text-slate-600">Choose a .csv file</span>
                    <input type="file" name="file" accept=".csv,text/csv,text/plain" class="block w-full text-xs text-slate-500 file:mr-3 file:rounded-md file:border-0 file:bg-slate-200 file:px-3 file:py-1.5 file:text-xs file:font-medium" required>
                </label>
                @error('file') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                @include('links.partials.bulk-meta')
                <button type="submit" class="lf-btn">Import CSV</button>
            </form>
            <details class="mt-4 text-xs text-slate-500">
                <summary class="cursor-pointer font-medium text-slate-600 [&::-webkit-details-marker]:hidden">Expected format</summary>
                <p class="mt-2">First row may be a header. Recognised columns: <code class="rounded bg-slate-100 px-1">long url</code>, <code class="rounded bg-slate-100 px-1">alias</code> (or Bitly <code class="rounded bg-slate-100 px-1">bitlink</code>), <code class="rounded bg-slate-100 px-1">title</code>, <code class="rounded bg-slate-100 px-1">tags</code>. With no header, columns are read in that order.</p>
                <a class="mt-2 inline-block font-medium text-brand-600 hover:underline" download="linkforge-sample.csv"
                   href="data:text/csv;charset=utf-8,long%20url,alias,title,tags%0Ahttps://example.com/spring,spring,Spring%20landing,sale%0Ahttps://example.com/blog,,Blog%20post,content">Download sample CSV</a>
            </details>
        </div>
    </div>
</x-app-layout>
