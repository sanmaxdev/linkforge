<form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
    @csrf @method('PUT')
    <input type="hidden" name="section" value="general">

    <div class="lf-card p-6">
        <h3 class="mb-4 text-sm font-semibold text-slate-900">Site identity</h3>
        <div class="space-y-4">
            <div>
                <label class="lf-label" for="site_name">Site name</label>
                <input id="site_name" name="site_name" value="{{ old('site_name', $s['site_name'] ?? config('linkforge.name')) }}" class="lf-input">
                @error('site_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="lf-label" for="site_tagline">Tagline</label>
                <input id="site_tagline" name="site_tagline" value="{{ old('site_tagline', $s['site_tagline'] ?? config('linkforge.tagline')) }}" class="lf-input">
            </div>
            <div>
                <label class="lf-label" for="site_description">Description</label>
                <textarea id="site_description" name="site_description" rows="3" class="lf-input">{{ old('site_description', $s['site_description'] ?? config('linkforge.description')) }}</textarea>
                <p class="mt-1 text-xs text-slate-400">Used for the marketing page and default social/SEO meta.</p>
            </div>
        </div>
    </div>

    <div class="lf-card p-6">
        <h3 class="mb-4 text-sm font-semibold text-slate-900">Access</h3>
        <label class="flex items-start gap-2.5 text-sm text-slate-600">
            <input type="checkbox" name="allow_registration" value="1" @checked(($s['allow_registration'] ?? '1') === '1')
                   class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500/30">
            <span>Allow new user registrations<br><span class="text-xs text-slate-400">When off, the sign-up page redirects to login.</span></span>
        </label>
        <label class="mt-4 flex items-start gap-2.5 text-sm text-slate-600">
            <input type="checkbox" name="guest_shorten" value="1" @checked(($s['guest_shorten'] ?? '1') === '1')
                   class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500/30">
            <span>Allow guests to shorten links on the homepage<br><span class="text-xs text-slate-400">Anonymous, rate-limited and safety-scanned. A great top-of-funnel hook.</span></span>
        </label>
    </div>

    <div class="lf-card p-6">
        <h3 class="mb-1 text-sm font-semibold text-slate-900">Maintenance mode</h3>
        <p class="mb-4 text-xs text-slate-400">Shows a maintenance notice on the marketing site and dashboard. Admins, short links, and bio pages keep working.</p>
        <label class="flex items-start gap-2.5 text-sm text-slate-600">
            <input type="checkbox" name="maintenance_mode" value="1" @checked(($s['maintenance_mode'] ?? '0') === '1')
                   class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500/30">
            <span>Enable maintenance mode</span>
        </label>
        <div class="mt-4">
            <label class="lf-label" for="maintenance_message">Notice</label>
            <input id="maintenance_message" name="maintenance_message" value="{{ old('maintenance_message', $s['maintenance_message'] ?? '') }}" class="lf-input" placeholder="We'll be back shortly.">
        </div>
    </div>

    <div class="flex justify-end">
        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">Save general</button>
    </div>
</form>
