@php
    $appHost = $appHost ?? request()->getHost();
    $target = $s['custom_domain_target'] ?? '';
    $ip = $s['custom_domain_ip'] ?? '';
@endphp

<form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
    @csrf @method('PUT')
    <input type="hidden" name="section" value="domains">

    <div class="lf-card p-6">
        <h3 class="mb-1 text-sm font-semibold text-slate-900">Customer custom domains</h3>
        <p class="mb-4 text-xs text-slate-400">These values are shown to your users on their <span class="font-medium text-slate-600">Custom domains</span> page so they can point their own domain at your platform. Leave blank to auto-use this install's host and IP.</p>

        <div class="space-y-4">
            <div>
                <label class="lf-label" for="custom_domain_target">CNAME target</label>
                <input id="custom_domain_target" name="custom_domain_target" value="{{ old('custom_domain_target', $target) }}"
                       class="lf-input" placeholder="{{ $appHost }}">
                <p class="mt-1 text-xs text-slate-400">The hostname customers point a <span class="font-medium">subdomain</span> at (CNAME). Usually this install's host (<span class="font-mono">{{ $appHost }}</span>). You can set a dedicated host such as <span class="font-mono">cname.yourbrand.com</span>.</p>
                @error('custom_domain_target')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="lf-label" for="custom_domain_ip">Server IP (for root domains)</label>
                <input id="custom_domain_ip" name="custom_domain_ip" value="{{ old('custom_domain_ip', $ip) }}"
                       class="lf-input" placeholder="{{ $autoServerIp ?? '203.0.113.10' }}">
                <p class="mt-1 text-xs text-slate-400">The IP customers use for an <span class="font-medium">A record</span> when connecting a root domain (e.g. <span class="font-mono">brand.com</span>). @if ($autoServerIp)Detected: <span class="font-mono">{{ $autoServerIp }}</span>.@endif</p>
                @error('custom_domain_ip')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="mt-5 flex justify-end">
            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">Save domain settings</button>
        </div>
    </div>
</form>
