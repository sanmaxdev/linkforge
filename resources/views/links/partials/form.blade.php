@php $link = $link ?? null; @endphp
<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if (($method ?? 'POST') !== 'POST')
        @method($method)
    @endif

    <div>
        <label for="long_url" class="lf-label">Destination URL</label>
        <input id="long_url" name="long_url" type="url" required
               value="{{ old('long_url', $link?->long_url ?? '') }}"
               class="lf-input" placeholder="https://example.com/your/long/page">
        @error('long_url') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    @php
        $domains = $domains ?? collect();
        $currentDomainId = (int) old('domain_id', $link?->domain_id ?? ($domain->id ?? 0));
    @endphp
    <div>
        <label for="alias" class="lf-label">Short link</label>
        <div class="flex">
            @if ($domains->count() > 1)
                <select name="domain_id" aria-label="Domain"
                        class="max-w-[12rem] shrink-0 truncate rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 py-2.5 pl-3 pr-8 text-sm text-slate-600 transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25 focus:outline-none">
                    @foreach ($domains as $d)
                        <option value="{{ $d->id }}" @selected($currentDomainId === (int) $d->id)>{{ $d->host }}/</option>
                    @endforeach
                </select>
            @else
                <span class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-500">{{ $domain->host ?? request()->getHost() }}/</span>
            @endif
            <input id="alias" name="alias" type="text"
                   value="{{ old('alias', $link?->alias ?? '') }}"
                   placeholder="{{ $suggestion ?? 'auto-generated' }}"
                   class="block w-full rounded-r-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 transition placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25 focus:outline-none">
        </div>
        @error('alias') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
        @error('domain_id') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
        <p class="mt-1.5 text-xs text-slate-400">@if ($domains->count() > 1)Pick a domain, then leave the code blank to auto-generate.@else Leave blank to auto-generate a short code.@endif</p>

        @if ($aiEnabled ?? false)
            <div class="mt-3" data-ai-alias data-ai-url="{{ route('ai.alias') }}">
                <button type="button" data-ai-trigger
                        class="inline-flex items-center gap-1.5 rounded-lg border border-spark-300 bg-spark-50 px-3 py-1.5 text-xs font-semibold text-spark-700 transition hover:bg-spark-100 disabled:cursor-not-allowed disabled:opacity-60">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.9 4.8L18.7 9l-4.8 1.9L12 15.7 10.1 10.9 5.3 9l4.8-1.2L12 3z"/><path d="M19 14l.8 2.2L22 17l-2.2.8L19 20l-.8-2.2L16 17l2.2-.8L19 14z"/></svg>
                    <span data-ai-label>Suggest with AI</span>
                </button>
                <p data-ai-error class="mt-2 hidden text-xs text-red-600"></p>
                <div data-ai-chips class="mt-2 flex flex-wrap gap-2"></div>
            </div>

            <script>
                (function () {
                    var root = document.querySelector('[data-ai-alias]');
                    if (!root || root.dataset.aiBound) return;
                    root.dataset.aiBound = '1';

                    var btn = root.querySelector('[data-ai-trigger]');
                    var label = root.querySelector('[data-ai-label]');
                    var chips = root.querySelector('[data-ai-chips]');
                    var err = root.querySelector('[data-ai-error]');
                    var meta = document.querySelector('meta[name="csrf-token"]');
                    var token = meta ? meta.getAttribute('content') : '';

                    function showError(msg) { err.textContent = msg; err.classList.remove('hidden'); }

                    btn.addEventListener('click', function () {
                        var urlField = document.getElementById('long_url');
                        var titleField = document.getElementById('title');
                        var url = urlField ? urlField.value.trim() : '';
                        err.classList.add('hidden');
                        chips.innerHTML = '';

                        if (!url) { showError('Enter a destination URL first.'); return; }

                        btn.disabled = true;
                        label.textContent = 'Generating';

                        fetch(root.dataset.aiUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': token,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({ long_url: url, title: titleField ? titleField.value : '' })
                        }).then(function (r) {
                            return r.json().then(function (d) { return { ok: r.ok, body: d }; });
                        }).then(function (res) {
                            if (!res.ok) { throw new Error(res.body.message || 'Could not suggest aliases.'); }
                            var list = res.body.suggestions || [];
                            if (!list.length) { showError('No suggestions available. Try a clearer URL or add a title.'); return; }
                            list.forEach(function (s) {
                                var chip = document.createElement('button');
                                chip.type = 'button';
                                chip.className = 'inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-700 transition hover:border-brand-400 hover:bg-brand-50 hover:text-brand-700';
                                chip.textContent = s;
                                chip.addEventListener('click', function () {
                                    var aliasField = document.getElementById('alias');
                                    if (aliasField) { aliasField.value = s; aliasField.focus(); }
                                });
                                chips.appendChild(chip);
                            });
                        }).catch(function (e) {
                            showError(e.message || 'The AI service is unavailable right now.');
                        }).finally(function () {
                            btn.disabled = false;
                            label.textContent = 'Suggest with AI';
                        });
                    });
                })();
            </script>
        @endif
    </div>

    <div>
        <label for="title" class="lf-label">Title <span class="font-normal text-slate-400">(optional)</span></label>
        <input id="title" name="title" type="text" value="{{ old('title', $link?->title ?? '') }}"
               class="lf-input" placeholder="Spring campaign">
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="campaign_id" class="lf-label">Campaign <span class="font-normal text-slate-400">(optional)</span></label>
            <select id="campaign_id" name="campaign_id" class="lf-input">
                <option value="">No campaign</option>
                @foreach (($campaigns ?? []) as $c)
                    <option value="{{ $c->id }}" @selected((int) old('campaign_id', $link?->campaign_id) === (int) $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="tags" class="lf-label">Tags <span class="font-normal text-slate-400">(comma-separated)</span></label>
            <input id="tags" name="tags" type="text" value="{{ old('tags', $link ? implode(', ', $link->tags ?? []) : '') }}"
                   class="lf-input" placeholder="sale, q2, newsletter">
        </div>
    </div>

    @if (! empty($canDeepLink))
        <details class="rounded-xl border border-slate-200 bg-slate-50/60 p-4" @if($link && $link->hasDeepLinks()) open @endif>
            <summary class="cursor-pointer text-sm font-medium text-slate-700 [&::-webkit-details-marker]:hidden">Mobile deep links</summary>
            <p class="mt-2 text-xs text-slate-400">Open a native app on mobile instead of the browser. Enter the app's URI scheme; visitors without the app fall through to your destination URL.</p>
            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="lf-label" for="deep_link_ios">iOS app URL</label>
                    <input id="deep_link_ios" name="deep_link_ios" value="{{ old('deep_link_ios', data_get($link?->meta, 'deep_link.ios', '')) }}" class="lf-input" placeholder="myapp://path">
                </div>
                <div>
                    <label class="lf-label" for="deep_link_android">Android app URL</label>
                    <input id="deep_link_android" name="deep_link_android" value="{{ old('deep_link_android', data_get($link?->meta, 'deep_link.android', '')) }}" class="lf-input" placeholder="myapp://path or intent://…">
                </div>
            </div>
        </details>
    @endif

    <details class="rounded-xl border border-slate-200 bg-slate-50/60 p-4" @if($link && ($link->password || $link->expires_at || $link->click_limit)) open @endif>
        <summary class="cursor-pointer text-sm font-medium text-slate-700 [&::-webkit-details-marker]:hidden">Advanced options</summary>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="type" class="lf-label">Link type</label>
                <select id="type" name="type" class="lf-input">
                    @foreach (['direct' => 'Direct redirect', 'splash' => 'Splash page', 'overlay' => 'CTA overlay', 'frame' => 'Frame', 'cta' => 'Call to action'] as $val => $lbl)
                        <option value="{{ $val }}" @selected(old('type', $link?->type ?? 'direct') === $val)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="click_limit" class="lf-label">Click limit</label>
                <input id="click_limit" name="click_limit" type="number" min="1"
                       value="{{ old('click_limit', $link?->click_limit ?? '') }}" class="lf-input" placeholder="Unlimited">
            </div>
            <div>
                <label for="expires_at" class="lf-label">Expires at</label>
                <input id="expires_at" name="expires_at" type="datetime-local"
                       value="{{ old('expires_at', optional($link)->expires_at?->format('Y-m-d\TH:i')) }}" class="lf-input">
            </div>
            <div>
                <label for="password" class="lf-label">Password</label>
                <input id="password" name="password" type="text" value=""
                       class="lf-input" placeholder="{{ $link && $link->password ? 'Set. Leave blank to keep.' : 'No password' }}">
            </div>
        </div>
        @if ($link)
            <label class="mt-4 flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $link->is_active))
                       class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500/30">
                Link is active
            </label>
        @endif
    </details>

    @include('links.partials.rules')

    @php
        $params = $link?->params ?? [];
        $utmKeys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
        $customLines = collect($params)->reject(fn ($v, $k) => in_array($k, $utmKeys, true))
            ->map(fn ($v, $k) => $k.'='.$v)->values()->implode("\n");
    @endphp
    <details class="rounded-xl border border-slate-200 bg-slate-50/60 p-4" @if (! empty($params)) open @endif>
        <summary class="cursor-pointer text-sm font-medium text-slate-700 [&::-webkit-details-marker]:hidden">UTM &amp; tracking parameters</summary>
        <p class="mt-2 text-xs text-slate-400">Appended to the destination URL on redirect. Any parameters already on the target are kept.</p>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            @foreach (['source' => 'Campaign source', 'medium' => 'Campaign medium', 'campaign' => 'Campaign name', 'term' => 'Term', 'content' => 'Content'] as $k => $lbl)
                <div>
                    <label for="utm_{{ $k }}" class="lf-label">utm_{{ $k }} <span class="font-normal text-slate-400">({{ $lbl }})</span></label>
                    <input id="utm_{{ $k }}" name="utm_{{ $k }}" type="text" value="{{ old('utm_'.$k, $params['utm_'.$k] ?? '') }}"
                           class="lf-input" placeholder="{{ $k === 'source' ? 'newsletter' : ($k === 'medium' ? 'email' : '') }}">
                </div>
            @endforeach
        </div>
        <div class="mt-3">
            <label for="custom_params" class="lf-label">Custom parameters <span class="font-normal text-slate-400">(one key=value per line)</span></label>
            <textarea id="custom_params" name="custom_params" rows="3" class="lf-input font-mono text-xs" placeholder="ref=partner&#10;aff=abc123">{{ old('custom_params', $customLines) }}</textarea>
        </div>
    </details>

    @if (! empty($pixels) && count($pixels))
        <details class="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
            <summary class="cursor-pointer text-sm font-medium text-slate-700 [&::-webkit-details-marker]:hidden">Retargeting pixels</summary>
            <p class="mt-2 text-xs text-slate-400">Fire these pixels when visitors pass through this link. Attaching one shows a brief branded splash (so the pixel can load) before redirecting, even on direct links.</p>
            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                @foreach ($pixels as $px)
                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                        <input type="checkbox" name="pixels[]" value="{{ $px->id }}" @checked(in_array($px->id, $attachedPixelIds ?? []))
                               class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500/30">
                        <span class="font-medium text-slate-700 capitalize">{{ $px->provider }}</span>
                        <span class="truncate text-slate-400">{{ $px->name ?: $px->pixel_id }}</span>
                    </label>
                @endforeach
            </div>
        </details>
    @endif

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('links.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-700">Cancel</a>
        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">{{ $submitLabel }}</button>
    </div>
</form>
