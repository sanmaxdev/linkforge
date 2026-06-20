@if (session('plain_token'))
    <div class="mb-6 rounded-xl border border-brand-200 bg-brand-50 p-4">
        <p class="text-sm font-medium text-brand-800">Copy your new token now. It will not be shown again.</p>
        <div class="mt-2 flex items-center gap-2">
            <code class="flex-1 truncate rounded-lg border border-brand-200 bg-white px-3 py-2 font-mono text-xs text-slate-700">{{ session('plain_token') }}</code>
            <button type="button" data-copy="{{ session('plain_token') }}" class="rounded-lg border border-brand-300 bg-white px-3 py-2 text-xs font-medium text-brand-700 hover:bg-brand-100">Copy</button>
        </div>
    </div>
@endif

@if (! $allowed)
    <div class="lf-card flex flex-col items-center justify-center px-6 py-16 text-center">
        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-600">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m16 18 6-6-6-6M8 6l-6 6 6 6"/></svg>
        </span>
        <h3 class="mt-4 text-lg font-semibold text-slate-900">Build on the {{ config('linkforge.name') }} API</h3>
        <p class="mt-1.5 max-w-sm text-sm text-slate-500">Programmatic link creation and analytics are available on the Starter plan and above.</p>
        <a href="{{ route('billing.index') }}" class="mt-6 inline-flex items-center gap-2 rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">View plans</a>
    </div>
@else
    <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
        <div>
            @if ($tokens->isEmpty())
                <div class="lf-card px-5 py-10 text-center text-sm text-slate-500">No tokens yet. Create one to start using the API.</div>
            @else
                <div class="lf-card divide-y divide-slate-100">
                    @foreach ($tokens as $t)
                        <div class="flex items-center justify-between gap-3 px-5 py-3.5">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-slate-900">{{ $t->name }}</p>
                                <p class="text-xs text-slate-400">Last used {{ $t->last_used_at?->diffForHumans() ?? 'never' }} · created {{ $t->created_at?->diffForHumans() }}</p>
                            </div>
                            <form method="POST" action="{{ route('tokens.destroy', $t->id) }}" data-confirm="Revoke this token?" data-confirm-ok="Revoke token">
                                @csrf @method('DELETE')
                                <button type="submit" class="rounded-md p-1.5 text-slate-400 transition hover:bg-red-50 hover:text-red-600" title="Revoke">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="lf-card mt-6 p-5">
                <h3 class="text-sm font-semibold text-slate-900">Using the API</h3>
                <pre class="mt-3 overflow-x-auto rounded-lg bg-slate-900 p-4 text-xs leading-relaxed text-slate-100"><code>curl {{ rtrim(config('app.url'), '/') }}/api/v1/links \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"long_url":"https://example.com/page"}'</code></pre>
            </div>
        </div>

        <div class="lf-card p-6">
            <h3 class="text-sm font-semibold text-slate-900">Create a token</h3>
            <form method="POST" action="{{ route('tokens.store') }}" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label for="name" class="lf-label">Token name</label>
                    <input id="name" name="name" value="{{ old('name') }}" class="lf-input" placeholder="e.g. Zapier integration">
                    @error('name') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="lf-btn">Create token</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-copy]');
            if (!btn) return;
            navigator.clipboard.writeText(btn.getAttribute('data-copy'));
            btn.textContent = 'Copied';
            setTimeout(() => { btn.textContent = 'Copy'; }, 1200);
        });
    </script>
@endif
