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

    <form id="geo-dl-form" method="POST" action="{{ route('admin.settings.geo.update') }}" class="mt-5"
          data-start="{{ route('admin.settings.geo.download.start') }}"
          data-chunk="{{ route('admin.settings.geo.download.chunk') }}"
          data-finish="{{ route('admin.settings.geo.download.finish') }}">
        @csrf
        <button type="submit" data-geo-dl-btn class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:opacity-60">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
            Download / update database now
        </button>
    </form>

    <div data-geo-dl-progress class="mt-4 hidden">
        <div class="flex items-center justify-between text-xs text-slate-500">
            <span data-geo-dl-label>Starting...</span>
            <span data-geo-dl-pct>0%</span>
        </div>
        <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-slate-100">
            <div data-geo-dl-bar class="h-full w-0 rounded-full bg-brand-600 transition-all duration-300"></div>
        </div>
    </div>
    <p data-geo-dl-error class="mt-2 hidden text-xs font-medium text-red-600"></p>

    <p class="mt-2 text-xs text-slate-400">Save your choices below first, then click download. The large City database streams in chunks (so it works even on strict shared hosts) and refreshes monthly via the scheduler.</p>
</div>

<script>
(function () {
    var form = document.getElementById('geo-dl-form');
    if (!form || !window.fetch) return; // no-JS: the form posts normally to the one-shot route
    var btn = form.querySelector('[data-geo-dl-btn]');
    var token = form.querySelector('input[name=_token]').value;
    var box = form.parentNode.querySelector('[data-geo-dl-progress]');
    var bar = form.parentNode.querySelector('[data-geo-dl-bar]');
    var pct = form.parentNode.querySelector('[data-geo-dl-pct]');
    var label = form.parentNode.querySelector('[data-geo-dl-label]');
    var err = form.parentNode.querySelector('[data-geo-dl-error]');

    function post(url) {
        return fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        }).then(function (r) {
            return r.json().then(function (j) {
                if (!r.ok || j.error) throw new Error(j.error || ('Request failed (HTTP ' + r.status + ')'));
                return j;
            });
        });
    }
    function mb(b) { return (b / 1048576).toFixed(1) + ' MB'; }
    function setPct(p) { box.classList.remove('hidden'); bar.style.width = p + '%'; pct.textContent = p + '%'; }
    function fail(m) { err.textContent = m; err.classList.remove('hidden'); label.textContent = 'Failed'; btn.disabled = false; }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        err.classList.add('hidden');
        btn.disabled = true;
        setPct(0);
        label.textContent = 'Starting...';

        post(form.dataset.start).then(function (j) {
            if (j.finished) { setPct(100); label.textContent = 'Done'; location.reload(); return; }
            var total = j.total || 0;

            (function step() {
                post(form.dataset.chunk).then(function (c) {
                    total = c.total || total;
                    var p = total ? Math.min(99, Math.round((c.received / total) * 100)) : 0;
                    label.textContent = 'Downloading ' + mb(c.received) + ' / ' + mb(total);
                    setPct(p);
                    if (c.done) {
                        label.textContent = 'Installing...';
                        post(form.dataset.finish)
                            .then(function () { setPct(100); label.textContent = 'Done'; location.reload(); })
                            .catch(function (e) { fail(e.message); });
                    } else {
                        step();
                    }
                }).catch(function (e) { fail(e.message); });
            })();
        }).catch(function (e) { fail(e.message); });
    });
})();
</script>

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

    <div class="lf-card p-6">
        <h3 class="mb-1 text-sm font-semibold text-slate-900">Cloudflare</h3>
        <label class="flex items-start gap-2.5 text-sm text-slate-600">
            <input type="checkbox" name="geo_cf_headers" value="1" @checked(($s['geo_cf_headers'] ?? '0') === '1')
                   class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500/30">
            <span>This site is behind Cloudflare &mdash; use its visitor-location headers for country/city.<br>
                <span class="text-xs text-slate-400">Leave OFF unless you are actually behind Cloudflare. When off, geo comes from the database above using the real visitor IP. Turning it on without Cloudflare (or without locking your origin to Cloudflare) lets visitors spoof their country.</span></span>
        </label>
    </div>

    <div class="lf-card border border-slate-200 bg-slate-50 p-5 text-xs text-slate-500">
        IP geolocation by <a href="https://db-ip.com" target="_blank" rel="noopener" class="font-medium text-brand-600 hover:text-brand-700">DB-IP</a> (CC-BY 4.0).
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
