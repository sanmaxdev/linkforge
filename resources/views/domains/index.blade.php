<x-app-layout title="Custom domains">
    <x-slot:header>Custom domains</x-slot:header>

    @if (session('status'))
        <div class="mb-5 rounded-lg bg-brand-50 px-4 py-3 text-sm text-brand-700">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-5 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ session('error') }}</div>
    @endif

    @if (! $allowed)
        <div class="lf-card flex flex-col items-center justify-center px-6 py-16 text-center">
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-600">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>
            </span>
            <h3 class="mt-4 text-lg font-semibold text-slate-900">Brand your links with your own domain</h3>
            <p class="mt-1.5 max-w-sm text-sm text-slate-500">Custom domains are available on paid plans. Upgrade to use links like <span class="font-medium text-slate-700">go.yourbrand.com</span>.</p>
            <a href="{{ route('billing.index') }}" class="mt-6 inline-flex items-center gap-2 rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">View plans</a>
        </div>
    @else
        @unless ($domains->isEmpty())
            <div class="lf-card mb-6 divide-y divide-slate-100">
                @foreach ($domains as $d)
                    <div class="flex items-center justify-between gap-3 px-5 py-3.5">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-slate-900">{{ $d->host }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            @php $badge = ['active' => 'bg-brand-50 text-brand-700', 'pending' => 'bg-amber-50 text-amber-700', 'blocked' => 'bg-red-50 text-red-700'][$d->status] ?? 'bg-slate-100 text-slate-500'; @endphp
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badge }}">{{ ucfirst($d->status) }}</span>
                            @if ($d->status !== 'active')
                                <form method="POST" action="{{ route('domains.verify', $d) }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Verify</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('domains.destroy', $d) }}" data-confirm="Remove this domain?" data-confirm-ok="Remove domain">
                                @csrf @method('DELETE')
                                <button type="submit" class="rounded-md p-1.5 text-slate-400 transition hover:bg-red-50 hover:text-red-600" title="Remove">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endunless

        @if ($canAdd)
            <div class="lf-card mb-6 p-6">
                <h3 class="text-sm font-semibold text-slate-900">Add a domain</h3>
                <form method="POST" action="{{ route('domains.store') }}" class="mt-4 flex flex-col gap-3 sm:flex-row">
                    @csrf
                    <input name="host" value="{{ old('host') }}" class="lf-input sm:flex-1" placeholder="go.yourbrand.com">
                    <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">Add domain</button>
                </form>
                @error('host') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        @else
            <p class="mb-6 text-sm text-slate-500">You've reached your plan's custom-domain limit. <a href="{{ route('billing.index') }}" class="font-medium text-brand-600 hover:text-brand-700">Upgrade for more</a>.</p>
        @endif

        <div class="lf-card p-6">
            <h3 class="text-sm font-semibold text-slate-900">Connect your domain</h3>
            <p class="mt-1.5 text-sm text-slate-500">Two steps: point the domain at this server (DNS), then add it to your hosting account so the server serves it from this app. Then click Verify.</p>

            {{-- Step 1: DNS --}}
            <div class="mt-5">
                <p class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-brand-100 text-[11px] font-bold text-brand-700">1</span>
                    Point the domain here (DNS provider)
                </p>
                <p class="mt-1.5 text-xs text-slate-400">Use a <span class="font-medium">CNAME</span> for a subdomain (e.g. <span class="font-mono">go.yourbrand.com</span>), or an <span class="font-medium">A record</span> to this server's IP for a root domain (e.g. <span class="font-mono">yourbrand.com</span>). Add the TXT record in both cases.</p>
                <div class="mt-3 space-y-2 text-sm">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <span class="text-xs font-medium text-slate-400">Subdomain &middot; CNAME</span>
                        <p class="font-mono text-slate-700">go &rarr; {{ $appHost }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <span class="text-xs font-medium text-slate-400">Root domain &middot; A record</span>
                        <p class="font-mono text-slate-700">@ &rarr; {{ $serverIp ?? 'your server IP (from your hosting panel)' }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <span class="text-xs font-medium text-slate-400">TXT (for verification)</span>
                        <p class="font-mono break-all text-slate-700">@ &rarr; {{ $token }}</p>
                    </div>
                </div>
            </div>

            {{-- Step 2: Hosting --}}
            <div class="mt-6">
                <p class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-brand-100 text-[11px] font-bold text-brand-700">2</span>
                    Serve it from this app (hosting control panel)
                </p>
                <p class="mt-1.5 text-xs text-slate-400">In cPanel (or your panel), add the domain as an <span class="font-medium text-slate-600">Alias</span> of this site, or as an <span class="font-medium text-slate-600">Addon domain</span> whose <span class="font-medium text-slate-600">Document Root</span> is this app's public folder below. Without this step the domain shows your host's own 404 page. Then enable SSL for it (AutoSSL / Let's Encrypt).</p>
                <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">
                    <span class="text-xs font-medium text-slate-400">Document root for the alias / addon domain</span>
                    <p class="font-mono break-all text-slate-700">{{ $docRoot }}</p>
                </div>
            </div>
        </div>
    @endif
</x-app-layout>
