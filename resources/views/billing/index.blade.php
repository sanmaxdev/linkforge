<x-app-layout title="Billing & plans">
    <x-slot:header>Billing &amp; plans</x-slot:header>

    @if (session('status'))
        <div class="mb-6 flex items-start gap-2.5 rounded-xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm text-brand-700">
            <svg class="mt-0.5 h-4 w-4 flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
            {{ session('status') }}
        </div>
    @endif

    @php
        $user = auth()->user();
        $isFree = ! $current || (float) $current->price <= 0;
        $intervalSuffix = fn ($p) => match ($p->interval) { 'year' => '/yr', 'month' => '/mo', default => '' };
        $aiAllowance = (int) ($current?->limit('ai_credits') ?? 0);
        $aiLeft = (int) $user->ai_credits;
        $aiPct = $aiAllowance > 0 ? min(100, round($aiLeft / $aiAllowance * 100)) : 0;

        // Status pill + billing line derived from the active subscription.
        if ($subscription && $subscription->status === 'trialing' && $subscription->trial_ends_at) {
            $statusText = 'Free trial'; $statusTone = 'amber';
            $billingLine = 'Trial ends '.$subscription->trial_ends_at->format('M j, Y');
        } elseif ($subscription && $subscription->ends_at) {
            $statusText = 'Canceling'; $statusTone = 'amber';
            $billingLine = 'Access until '.$subscription->ends_at->format('M j, Y');
        } elseif ($subscription && $subscription->status === 'active') {
            $statusText = 'Active'; $statusTone = 'brand';
            $billingLine = $subscription->renews_at
                ? 'Renews '.$subscription->renews_at->format('M j, Y').($subscription->gateway ? ' via '.ucfirst($subscription->gateway) : '')
                : 'Active subscription';
        } elseif ($isFree) {
            $statusText = 'Free plan'; $statusTone = 'slate';
            $billingLine = 'No billing. Upgrade any time to unlock more.';
        } else {
            $statusText = 'Active'; $statusTone = 'brand';
            $billingLine = 'Your plan is active.';
        }
    @endphp

    {{-- Current plan hero --}}
    <div class="lf-card mb-8 overflow-hidden p-0">
        <div class="flex flex-wrap items-start justify-between gap-4 bg-gradient-to-br from-brand-600 to-brand-800 p-6 text-white sm:p-7">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-white/70">Current plan</p>
                <div class="mt-1 flex items-center gap-3">
                    <h2 class="text-2xl font-semibold">{{ $current?->name ?? 'Free' }}</h2>
                    <span @class([
                        'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                        'bg-white/20 text-white' => $statusTone === 'brand',
                        'bg-spark-400 text-slate-900' => $statusTone === 'amber',
                        'bg-white/15 text-white/90' => $statusTone === 'slate',
                    ])>{{ $statusText }}</span>
                </div>
                <p class="mt-2 text-sm text-white/75">{{ $billingLine }}</p>
            </div>
            <div class="text-right">
                <p class="text-3xl font-semibold leading-none">
                    {{ $isFree ? 'Free' : '$'.number_format($current->price, 0) }}
                </p>
                @unless ($isFree)<p class="mt-1 text-sm text-white/70">per {{ $current->interval === 'year' ? 'year' : 'month' }}</p>@endunless
            </div>
        </div>

        <div class="grid gap-5 p-6 sm:p-7 lg:grid-cols-[260px_1fr]">
            {{-- AI credits --}}
            <div class="flex flex-col justify-center rounded-xl border border-brand-100 bg-brand-50/60 p-5">
                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-brand-700/70">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v3M12 18v3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M3 12h3M18 12h3M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1"/></svg>
                    AI credits
                </div>
                <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($aiLeft) }}</p>
                <p class="text-xs text-slate-400">remaining{{ $aiAllowance ? ' of '.number_format($aiAllowance).' / month' : '' }}</p>
                @if ($aiAllowance)
                    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-brand-100">
                        <div class="h-full rounded-full bg-brand-500" style="width: {{ max(3, $aiPct) }}%"></div>
                    </div>
                @endif
            </div>

            {{-- Usage meters --}}
            <div class="grid gap-x-6 gap-y-5 sm:grid-cols-2">
                @foreach ($usage as $u)
                    @php
                        $unlimited = $u['limit'] === null;
                        $pct = (int) ($u['percent'] ?? 0);
                        $barColor = $pct >= 100 ? 'bg-red-500' : ($pct >= 80 ? 'bg-amber-500' : 'bg-brand-500');
                    @endphp
                    <div>
                        <div class="flex items-baseline justify-between text-sm">
                            <span class="font-medium text-slate-700">{{ $u['label'] }}</span>
                            <span class="text-slate-400">{{ number_format($u['used']) }} / {{ $unlimited ? '∞' : number_format($u['limit']) }}</span>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full {{ $unlimited ? 'bg-brand-300' : $barColor }}" style="width: {{ $unlimited ? 8 : min(100, max(3, $pct)) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Plans --}}
    <div class="mb-5 flex items-end justify-between">
        <div>
            <h3 class="text-lg font-semibold text-slate-900">Choose your plan</h3>
            <p class="mt-1 text-sm text-slate-500">Upgrade, downgrade or switch any time.</p>
        </div>
    </div>

    <div class="grid items-start gap-5 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($plans as $plan)
            @php
                $isCurrent = $current && $current->id === $plan->id;
                $popular = $plan->slug === 'pro';
                $planPrice = (float) $plan->price;
                $curPrice = (float) ($current->price ?? 0);
                $lim = fn ($k) => $plan->limit($k);
                $fmt = fn ($v) => $v === null ? 'Unlimited' : number_format($v);
                $dom = $lim('max_domains');
                $rows = [
                    ['ok' => true, 'label' => $fmt($lim('max_links')).' short links'],
                    ['ok' => true, 'label' => $fmt($lim('max_bio')).' bio pages'],
                    ['ok' => true, 'label' => $fmt($lim('max_qr')).' QR codes'],
                    ['ok' => $dom === null || $dom > 0, 'label' => ($dom === null ? 'Unlimited' : ($dom > 0 ? number_format($dom) : 'No')).' custom domains'],
                    ['ok' => true, 'label' => number_format($lim('ai_credits') ?? 0).' AI credits / month'],
                    ['ok' => $plan->allows('api'), 'label' => 'API access'],
                    ['ok' => $plan->allows('retargeting'), 'label' => 'Retargeting pixels'],
                    ['ok' => $plan->allows('deep_links'), 'label' => 'Deep links'],
                    ['ok' => $plan->allows('white_label'), 'label' => 'White-label branding'],
                ];
            @endphp

            <div @class([
                'relative flex flex-col rounded-2xl p-6 shadow-sm',
                'bg-gradient-to-b from-brand-700 to-brand-900 text-white shadow-lg ring-1 ring-brand-700 lg:-mt-3 lg:pb-8' => $popular,
                'border border-slate-200 bg-white' => ! $popular,
                'ring-2 ring-brand-500' => $isCurrent && ! $popular,
            ])>
                @if ($popular)
                    <span class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-spark-400 px-3 py-1 text-xs font-bold uppercase tracking-wide text-slate-900 shadow">Most popular</span>
                @endif

                <div class="flex items-center justify-between">
                    <p class="text-base font-semibold {{ $popular ? 'text-white' : 'text-slate-900' }}">{{ $plan->name }}</p>
                    @if ($isCurrent)
                        <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $popular ? 'bg-white/20 text-white' : 'bg-brand-50 text-brand-700' }}">Your plan</span>
                    @endif
                </div>

                <p class="mt-3 flex items-baseline gap-1">
                    <span class="text-4xl font-bold tracking-tight {{ $popular ? 'text-white' : 'text-slate-900' }}">${{ number_format($plan->price, 0) }}</span>
                    <span class="text-sm {{ $popular ? 'text-white/70' : 'text-slate-400' }}">{{ $intervalSuffix($plan) ?: '/mo' }}</span>
                </p>

                <ul class="mt-6 flex-1 space-y-2.5">
                    @foreach ($rows as $row)
                        @include('billing.partials.feature', ['ok' => $row['ok'], 'label' => $row['label'], 'dark' => $popular])
                    @endforeach
                </ul>

                @if ($isCurrent)
                    <div class="mt-6 inline-flex w-full items-center justify-center gap-1.5 rounded-lg px-4 py-2.5 text-sm font-semibold {{ $popular ? 'bg-white/15 text-white' : 'border border-slate-200 text-slate-400' }}">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                        Current plan
                    </div>
                @else
                    <form method="POST" action="{{ route('billing.subscribe', $plan) }}" class="mt-6"
                        @if ($planPrice < $curPrice) data-confirm="Switch to the {{ $plan->name }} plan? You may lose access to features and data above its limits." data-confirm-ok="Switch plan" @endif>
                        @csrf
                        <button type="submit" @class([
                            'w-full rounded-lg px-4 py-2.5 text-sm font-semibold transition',
                            'bg-white text-brand-700 hover:bg-white/90' => $popular,
                            'bg-brand-600 text-white hover:bg-brand-700' => ! $popular && $planPrice >= $curPrice,
                            'border border-slate-300 text-slate-700 hover:bg-slate-50' => ! $popular && $planPrice < $curPrice,
                        ])>
                            @if ($planPrice == 0) Switch to Free
                            @elseif ($planPrice > $curPrice) Upgrade to {{ $plan->name }}
                            @else Switch to {{ $plan->name }} @endif
                        </button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-6 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs text-slate-400">
        @if ($gateway === 'offline')
            <span class="inline-flex items-center gap-1.5">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4l2 2"/></svg>
                Plan changes apply instantly (manual billing).
            </span>
        @else
            <span class="inline-flex items-center gap-1.5">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                Secure checkout via {{ ucfirst($gateway) }}.
            </span>
        @endif
        <span>Cancel any time.</span>
    </div>
</x-app-layout>
