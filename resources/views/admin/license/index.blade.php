<x-admin-layout title="License">
    <x-slot:header>License</x-slot:header>

    @if (session('status'))<div class="mb-5 rounded-lg bg-brand-50 px-4 py-3 text-sm text-brand-700">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="mb-5 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>@endif

    @php
        $status = $license['status'];
        [$label, $badgeClass] = match ($status) {
            'active' => ['Active', 'bg-brand-50 text-brand-700'],
            'invalid' => ['Invalid', 'bg-red-50 text-red-700'],
            'unverified' => ['Unverified', 'bg-amber-50 text-amber-700'],
            default => ['Not set', 'bg-slate-100 text-slate-600'],
        };
        $code = (string) $license['code'];
        $mask = $code !== '' ? str_repeat('•', max(0, strlen($code) - 4)).substr($code, -4) : '—';
    @endphp

    <div class="grid max-w-3xl gap-6">
        <div class="lf-card p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-medium tracking-wide text-slate-400 uppercase">License status</p>
                    <p class="mt-1"><span class="inline-flex rounded-full px-3 py-1 text-sm font-semibold {{ $badgeClass }}">{{ $label }}</span></p>
                </div>
                <form method="POST" action="{{ route('admin.license.recheck') }}">
                    @csrf
                    <button type="submit" @disabled(\App\Support\Demo::enabled())
                            class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50">Re-verify now</button>
                </form>
            </div>

            @if ($status === 'invalid')
                <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    The license server reports this purchase code is no longer valid — it may have been refunded, charged back, or revoked. Your site keeps working, but please enter a valid code or contact your seller.
                </div>
            @elseif ($status === 'unverified')
                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                    Your license has not been confirmed online yet. This is normal if the license server was unreachable when you installed; it re-checks automatically. Click <strong>Re-verify now</strong> to try again.
                </div>
            @endif

            <dl class="mt-5 grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-400">Purchase code</dt><dd class="font-mono text-slate-700">{{ $mask }}</dd></div>
                <div><dt class="text-slate-400">Last verified</dt><dd class="text-slate-700">{{ $license['verified_at'] ? \Illuminate\Support\Carbon::parse($license['verified_at'])->diffForHumans() : '—' }}</dd></div>
                <div><dt class="text-slate-400">Last checked</dt><dd class="text-slate-700">{{ $license['checked_at'] ? \Illuminate\Support\Carbon::parse($license['checked_at'])->diffForHumans() : '—' }}</dd></div>
            </dl>
        </div>

        <div class="lf-card p-6">
            <h3 class="text-sm font-semibold text-slate-900">Moving to a new domain?</h3>
            <p class="mt-1 text-sm text-slate-500">Each license is tied to a single domain. If you migrate your install, ask your seller to release the domain lock, then click <strong>Re-verify now</strong> from the new domain.</p>
        </div>
    </div>
</x-admin-layout>
