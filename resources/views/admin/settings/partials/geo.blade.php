@php
    $provider = old('geoip_provider', $s['geoip_provider'] ?? 'dbip');
    $edition = old('geoip_edition', $s['geoip_edition'] ?? 'country');
    $updatedAt = $s['geoip_updated_at'] ?? null;
    $source = $s['geoip_source'] ?? null;
@endphp

{{-- Status + download --}}
<div class="lf-card p-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h3 class="text-sm font-semibold text-slate-900">GeoIP database</h3>
            <p class="mt-1 text-xs text-slate-400">Powers Countries, Cities and the world map in analytics. Country works out of the box; City is an optional download.</p>
        </div>
        @if ($geoDetected)
            <span class="inline-flex flex-none items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Active
            </span>
        @else
            <span class="inline-flex flex-none items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">Not installed</span>
        @endif
    </div>

    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
        <div>
            <dt class="text-xs text-slate-400">Current database</dt>
            <dd class="text-slate-700">{{ $source ?: ($geoDetected ? 'Bundled country database' : 'None') }}</dd>
        </div>
        <div>
            <dt class="text-xs text-slate-400">Last updated</dt>
            <dd class="text-slate-700">{{ $updatedAt ? \Illuminate\Support\Carbon::parse($updatedAt)->diffForHumans() : 'Bundled with the app' }}</dd>
        </div>
    </dl>

    <form method="POST" action="{{ route('admin.settings.geo.update') }}" class="mt-5"
          data-confirm="Download the {{ $edition === 'city' ? 'City (large, ~120 MB)' : 'Country' }} database now? This fetches it onto your server." data-confirm-ok="Download">
        @csrf
        <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
            Download / update database now
        </button>
    </form>
    <p class="mt-2 text-xs text-slate-400">Save your choices below first, then click download. It runs on this server and refreshes monthly via the scheduler.</p>
</div>

{{-- Settings --}}
<form method="POST" action="{{ route('admin.settings.update') }}" class="mt-6 space-y-6">
    @csrf @method('PUT')
    <input type="hidden" name="section" value="geo">

    <div class="lf-card p-6">
        <h3 class="mb-3 text-sm font-semibold text-slate-900">Detail level</h3>
        <div class="space-y-2.5">
            @foreach ($geoEditions as $key => $label)
                <label class="flex items-start gap-2.5 text-sm text-slate-600">
                    <input type="radio" name="geoip_edition" value="{{ $key }}" @checked($edition === $key)
                           class="mt-0.5 h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-500/30">
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </div>

    <div class="lf-card p-6">
        <h3 class="mb-3 text-sm font-semibold text-slate-900">Database provider</h3>
        <div class="space-y-2.5">
            @foreach ($geoProviders as $key => $label)
                <label class="flex items-start gap-2.5 text-sm text-slate-600">
                    <input type="radio" name="geoip_provider" value="{{ $key }}" @checked($provider === $key) data-geo-provider
                           class="mt-0.5 h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-500/30">
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>

        <div data-maxmind-fields class="mt-4 {{ $provider === 'maxmind' ? '' : 'hidden' }}">
            @include('admin.settings.partials.secret-field', ['field' => 'geoip_maxmind_key', 'label' => 'MaxMind license key', 'placeholder' => 'Your GeoLite2 license key'])
            <p class="mt-1 text-xs text-slate-400">Create a free key at <a href="https://www.maxmind.com/en/geolite2/signup" target="_blank" rel="noopener" class="font-medium text-brand-600 hover:text-brand-700">maxmind.com</a>.</p>
        </div>
    </div>

    <div class="lf-card border border-slate-200 bg-slate-50 p-5 text-xs text-slate-500">
        IP geolocation by <a href="https://db-ip.com" target="_blank" rel="noopener" class="font-medium text-brand-600 hover:text-brand-700">DB-IP</a> (CC-BY). No database at all? Put your site behind Cloudflare and country + city come from its visitor headers automatically.
    </div>

    <div class="flex justify-end">
        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">Save geo settings</button>
    </div>
</form>

<script>
(function () {
    var radios = document.querySelectorAll('[data-geo-provider]');
    var fields = document.querySelector('[data-maxmind-fields]');
    if (!radios.length || !fields) return;
    function sync() {
        var sel = document.querySelector('[data-geo-provider]:checked');
        fields.classList.toggle('hidden', !sel || sel.value !== 'maxmind');
    }
    radios.forEach(function (r) { r.addEventListener('change', sync); });
    sync();
})();
</script>
