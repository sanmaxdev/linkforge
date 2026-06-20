<x-app-layout :title="__('Custom domains')">
    <x-slot:header>{{ __('Custom domains') }}</x-slot:header>

    <x-demo-lock>Adding, verifying or removing custom domains is disabled in the live demo.</x-demo-lock>

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
            <h3 class="mt-4 text-lg font-semibold text-slate-900">{{ __('Brand your links with your own domain') }}</h3>
            <p class="mt-1.5 max-w-sm text-sm text-slate-500">{{ __('Custom domains are available on paid plans. Upgrade to use links like') }} <span class="font-medium text-slate-700">go.yourbrand.com</span>.</p>
            <a href="{{ route('billing.index') }}" class="mt-6 inline-flex items-center gap-2 rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">{{ __('View plans') }}</a>
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
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badge }}">{{ __(ucfirst($d->status)) }}</span>
                            @if ($d->status !== 'active')
                                <form method="POST" action="{{ route('domains.verify', $d) }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">{{ __('Verify') }}</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('domains.destroy', $d) }}" data-confirm="{{ __('Remove this domain?') }}" data-confirm-ok="{{ __('Remove domain') }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="rounded-md p-1.5 text-slate-400 transition hover:bg-red-50 hover:text-red-600" title="{{ __('Remove') }}">
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
                <h3 class="text-sm font-semibold text-slate-900">{{ __('Add a domain') }}</h3>
                <form method="POST" action="{{ route('domains.store') }}" class="mt-4 flex flex-col gap-3 sm:flex-row">
                    @csrf
                    <input name="host" value="{{ old('host') }}" class="lf-input sm:flex-1" placeholder="go.yourbrand.com">
                    <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">{{ __('Add domain') }}</button>
                </form>
                @error('host') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        @else
            <p class="mb-6 text-sm text-slate-500">{{ __("You've reached your plan's custom-domain limit.") }} <a href="{{ route('billing.index') }}" class="font-medium text-brand-600 hover:text-brand-700">{{ __('Upgrade for more') }}</a>.</p>
        @endif

        <div class="lf-card p-6">
            <h3 class="text-sm font-semibold text-slate-900">{{ __('Connect your domain') }}</h3>
            <p class="mt-1.5 text-sm text-slate-500">{{ __('Add one of these records at your DNS provider, plus the TXT record, then click Verify. DNS changes can take a few minutes to a few hours.') }}</p>

            <div class="mt-4 space-y-3 text-sm">
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-slate-400">{{ __('If you are using a subdomain (e.g. go.yourbrand.com)') }}</span>
                        <span class="rounded bg-slate-200 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500">CNAME</span>
                    </div>
                    <p class="mt-1 font-mono text-slate-700">go &rarr; {{ $cnameTarget }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-slate-400">{{ __('If you are using a root domain (e.g. yourbrand.com)') }}</span>
                        <span class="rounded bg-slate-200 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500">A</span>
                    </div>
                    <p class="mt-1 font-mono text-slate-700">@ &rarr; {{ $serverIp ?? $cnameTarget }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <span class="text-xs font-medium text-slate-400">{{ __('TXT (required for verification in both cases)') }}</span>
                    <p class="mt-1 font-mono break-all text-slate-700">@ &rarr; {{ $token }}</p>
                </div>
            </div>

            <p class="mt-4 text-xs text-slate-400">{{ __('Once verified, your domain becomes available in the domain picker when you create or edit a link.') }}</p>
        </div>
    @endif
</x-app-layout>
