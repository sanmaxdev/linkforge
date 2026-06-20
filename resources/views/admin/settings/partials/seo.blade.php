<form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="space-y-6">
    @csrf @method('PUT')
    <input type="hidden" name="section" value="seo">

    <div class="lf-card p-6">
        <h3 class="mb-4 text-sm font-semibold text-slate-900">Meta</h3>
        <div>
            <label class="lf-label" for="seo_meta_description">Default meta description</label>
            <textarea id="seo_meta_description" name="seo_meta_description" rows="3" class="lf-input" placeholder="{{ config('linkforge.description') }}">{{ old('seo_meta_description', $s['seo_meta_description'] ?? '') }}</textarea>
            <p class="mt-1 text-xs text-slate-400">Used as the page meta description and the social/SEO fallback.</p>
        </div>
    </div>

    <div class="lf-card p-6">
        <h3 class="mb-1 text-sm font-semibold text-slate-900">Social sharing (Open Graph &amp; Twitter)</h3>
        <p class="mb-4 text-xs text-slate-400">How your site looks when shared on Facebook, LinkedIn, X/Twitter, WhatsApp and others. Blank fields fall back to your site name and meta description.</p>
        <div class="space-y-4">
            <div>
                <label class="lf-label" for="seo_og_title">Share title</label>
                <input id="seo_og_title" name="seo_og_title" value="{{ old('seo_og_title', $s['seo_og_title'] ?? '') }}" class="lf-input" placeholder="{{ trim(config('linkforge.name').' · '.config('linkforge.tagline'), ' ·') }}">
                @error('seo_og_title')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="lf-label" for="seo_og_description">Share description</label>
                <textarea id="seo_og_description" name="seo_og_description" rows="2" class="lf-input" placeholder="Falls back to the meta description above">{{ old('seo_og_description', $s['seo_og_description'] ?? '') }}</textarea>
                @error('seo_og_description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="lf-label">Share image</label>
                @php $ogImg = $s['seo_og_image'] ?? ''; @endphp
                @if ($ogImg)
                    <div class="mb-2 flex items-center gap-3">
                        <img src="{{ $ogImg }}" alt="" class="h-20 rounded-lg border border-slate-200 object-cover">
                        <label class="flex items-center gap-1.5 text-xs text-slate-500"><input type="checkbox" name="seo_og_image_clear" value="1" class="h-3.5 w-3.5 rounded border-slate-300"> Remove</label>
                    </div>
                @endif
                <input id="seo_og_image_file" name="seo_og_image_file" type="file" accept="image/*" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                <p class="mt-1 text-xs text-slate-400">Recommended 1200&times;630px (JPG/PNG/WebP, max 2&nbsp;MB).</p>
                @error('seo_og_image_file')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="lf-label" for="seo_twitter_handle">X / Twitter handle <span class="font-normal text-slate-400">(optional)</span></label>
                <input id="seo_twitter_handle" name="seo_twitter_handle" value="{{ old('seo_twitter_handle', $s['seo_twitter_handle'] ?? '') }}" class="lf-input" placeholder="@yourbrand">
                @error('seo_twitter_handle')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    <div class="lf-card p-6">
        <h3 class="mb-1 text-sm font-semibold text-slate-900">Analytics</h3>
        <p class="mb-4 text-xs text-slate-400">Tracking code is injected on the public site and dashboard (not the admin panel).</p>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="lf-label" for="seo_ga_id">Google Analytics ID</label>
                <input id="seo_ga_id" name="seo_ga_id" value="{{ old('seo_ga_id', $s['seo_ga_id'] ?? '') }}" class="lf-input font-mono text-xs" placeholder="G-XXXXXXXXXX">
                @error('seo_ga_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="lf-label" for="seo_gtm_id">Google Tag Manager ID</label>
                <input id="seo_gtm_id" name="seo_gtm_id" value="{{ old('seo_gtm_id', $s['seo_gtm_id'] ?? '') }}" class="lf-input font-mono text-xs" placeholder="GTM-XXXXXXX">
                @error('seo_gtm_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    <div class="flex justify-end">
        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">Save SEO</button>
    </div>
</form>
