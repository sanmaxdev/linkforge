<x-admin-layout :title="$ad->exists ? 'Edit ad' : 'Add ad'">
    <x-slot:header>{{ $ad->exists ? 'Edit ad' : 'Add ad' }}</x-slot:header>

    <form method="POST" action="{{ $ad->exists ? route('admin.ads.update', $ad) : route('admin.ads.store') }}" enctype="multipart/form-data" class="max-w-2xl space-y-6">
        @csrf
        @if ($ad->exists) @method('PUT') @endif

        <div class="lf-card space-y-4 p-6">
            <div>
                <label for="name" class="lf-label">Name</label>
                <input id="name" name="name" value="{{ old('name', $ad->name) }}" class="lf-input" placeholder="e.g. AdSense 728x90">
                @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="placement" class="lf-label">Placement</label>
                <select id="placement" name="placement" class="lf-input">
                    @foreach ($placements as $key => $label)
                        <option value="{{ $key }}" @selected(old('placement', $ad->placement) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-400">Where this shows for free users: <strong>Interstitial</strong> = the link redirect page; <strong>Dashboard</strong> = a banner atop their dashboard; <strong>Sidebar</strong> = under the menu; <strong>Popup</strong> = a dismissible modal (once per session). Premium / ad-free members never see these.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-[1fr_auto]">
                <div>
                    <label for="sort" class="lf-label">Priority</label>
                    <input id="sort" name="sort" type="number" min="0" value="{{ old('sort', $ad->sort ?? 0) }}" class="lf-input">
                    <p class="mt-1 text-xs text-slate-400">Lower number wins when several ads share a placement.</p>
                </div>
                <label class="flex items-center gap-2 self-end pb-3 text-sm text-slate-600">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $ad->is_active)) class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500/30">
                    Active
                </label>
            </div>
        </div>

        <div class="lf-card space-y-4 p-6">
            <h3 class="text-sm font-semibold text-slate-900">Ad content</h3>
            <p class="text-xs text-slate-400">Paste an ad-network snippet (Google AdSense, Media.net, a direct &lt;script&gt; or &lt;ins&gt;), <em>or</em> upload a banner image and set a click URL. If both are filled, the code is used.</p>
            <div>
                <label for="code" class="lf-label">Ad code (HTML / JavaScript)</label>
                <textarea id="code" name="code" rows="6" class="lf-input font-mono text-xs" placeholder="&lt;script async src=...&gt;&lt;/script&gt;&lt;ins class=&quot;adsbygoogle&quot; ...&gt;&lt;/ins&gt;">{{ old('code', $ad->code) }}</textarea>
                @error('code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="border-t border-slate-100 pt-4">
                <label class="lf-label">Banner image (optional)</label>
                @if ($ad->imageUrl())
                    <div class="mb-2 flex items-center gap-3">
                        <img src="{{ $ad->imageUrl() }}" alt="" class="h-12 rounded border border-slate-200">
                        <label class="flex items-center gap-1.5 text-xs text-slate-500"><input type="checkbox" name="image_clear" value="1" class="h-3.5 w-3.5 rounded border-slate-300"> Remove</label>
                    </div>
                @endif
                <input id="image" name="image" type="file" accept="image/*" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                @error('image')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="target_url" class="lf-label">Banner click URL</label>
                <input id="target_url" name="target_url" type="url" value="{{ old('target_url', $ad->target_url) }}" class="lf-input" placeholder="https://advertiser.example.com">
                @error('target_url')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.ads') }}" class="text-sm font-medium text-slate-500 hover:text-slate-700">Cancel</a>
            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">{{ $ad->exists ? 'Save ad' : 'Create ad' }}</button>
        </div>
    </form>
</x-admin-layout>
