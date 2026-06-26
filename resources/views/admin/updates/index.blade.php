<x-admin-layout title="Updates">
    <x-slot:header>Updates</x-slot:header>

    @if (session('status'))<div class="mb-5 rounded-lg bg-brand-50 px-4 py-3 text-sm text-brand-700">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="mb-5 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>@endif
    @error('package')<div class="mb-5 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div>@enderror

    <div class="grid max-w-3xl gap-6">

        {{-- Current version --}}
        <div class="lf-card flex items-center justify-between p-6">
            <div>
                <p class="text-xs font-medium tracking-wide text-slate-400 uppercase">Installed version</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900">v{{ $current }}</p>
            </div>
            @if ($pending)
                <span class="inline-flex items-center gap-2 rounded-full bg-amber-100 px-3 py-1.5 text-sm font-medium text-amber-700">Update pending</span>
            @else
                <span class="inline-flex items-center gap-2 rounded-full bg-brand-50 px-3 py-1.5 text-sm font-medium text-brand-700">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    Up to date
                </span>
            @endif
        </div>

        {{-- Pending update --}}
        @if ($pending)
            <div class="lf-card overflow-hidden border-2 border-amber-200 p-0">
                <div class="border-b border-amber-100 bg-amber-50 px-6 py-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">{{ $pending['name'] }}</h3>
                            <p class="mt-0.5 text-xs text-slate-500">Package version <span class="font-medium text-slate-700">v{{ $pending['version'] }}</span>, requires v{{ $pending['requires'] }} or newer</p>
                        </div>
                        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Ready to review</span>
                    </div>
                </div>

                <div class="space-y-5 p-6">
                    @if ($pending['notes'])
                        <div>
                            <p class="lf-label">Release notes</p>
                            <div class="rounded-lg bg-slate-50 px-4 py-3 text-sm whitespace-pre-line text-slate-600">{{ $pending['notes'] }}</div>
                        </div>
                    @endif

                    @if ($issues)
                        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3">
                            <p class="mb-1 text-sm font-semibold text-red-700">This package cannot be applied</p>
                            <ul class="list-disc space-y-0.5 pl-5 text-sm text-red-600">
                                @foreach ($issues as $issue)<li>{{ $issue }}</li>@endforeach
                            </ul>
                        </div>
                    @else
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            <p class="font-medium text-slate-700">Applying will:</p>
                            <ul class="mt-1 list-disc space-y-0.5 pl-5">
                                <li>Back up every file it replaces to <code class="text-xs">storage/app/update-backups</code></li>
                                <li>Copy in the package's files and run any new database migrations</li>
                                <li>Clear caches and switch the installed version to v{{ $pending['version'] }}</li>
                            </ul>
                            <p class="mt-2 text-xs text-slate-400">Tip: take a database + files backup first. The site may serve briefly inconsistent pages while files are written.</p>
                        </div>
                    @endif

                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <form method="POST" action="{{ route('admin.updates.discard') }}"
                              data-confirm="Discard this pending update package? You can upload it again later." data-confirm-ok="Discard">
                            @csrf
                            <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50">Discard</button>
                        </form>
                        <form method="POST" action="{{ route('admin.updates.apply') }}"
                              data-confirm="Apply update v{{ $pending['version'] }} now? Files will be replaced and migrations run." data-confirm-ok="Apply update">
                            @csrf
                            <button type="submit" @disabled($issues)
                                    @class([
                                        'rounded-lg px-5 py-2 text-sm font-semibold text-white shadow-sm transition',
                                        'bg-brand-600 hover:bg-brand-700' => ! $issues,
                                        'cursor-not-allowed bg-slate-300' => (bool) $issues,
                                    ])>Apply update</button>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        {{-- Upload --}}
        <div class="lf-card p-6">
            <h3 class="mb-1 text-sm font-semibold text-slate-900">{{ $pending ? 'Replace the pending package' : 'Upload an update package' }}</h3>
            <x-demo-lock>Uploading and applying updates is disabled in the live demo.</x-demo-lock>
            <p class="mb-2 text-xs text-slate-400">Download a release package, then select the update <code class="text-xs">.zip</code> here. It is inspected on upload, and you review and apply it on this screen.</p>
            @if (! empty($maxUpload))
                <p class="mb-4 text-xs text-slate-400">Max upload size on this server: <span class="font-medium text-slate-600">{{ number_format($maxUpload / 1048576, 0) }} MB</span>. Update packages are small and fit comfortably.</p>
            @endif
            <form method="POST" action="{{ route('admin.updates.upload') }}" enctype="multipart/form-data" data-update-form class="flex flex-wrap items-center gap-3">
                @csrf
                <input type="file" name="package" accept=".zip,application/zip" required @disabled(\App\Support\Demo::enabled())
                       class="block w-full max-w-sm cursor-pointer rounded-lg border border-slate-300 text-sm text-slate-600 file:mr-3 file:cursor-pointer file:border-0 file:bg-slate-100 file:px-4 file:py-2.5 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200 disabled:cursor-not-allowed disabled:opacity-50">
                <button type="submit" data-upload-btn @disabled(\App\Support\Demo::enabled()) class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50">Upload</button>
            </form>

            {{-- Upload progress (shown while the package transfers) --}}
            <div data-upload-progress class="mt-4 hidden">
                <div class="mb-1.5 flex items-center justify-between text-xs">
                    <span data-upload-label class="text-slate-500">Uploading…</span>
                    <span data-upload-pct class="font-semibold text-slate-700">0%</span>
                </div>
                <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                    <div data-upload-bar class="h-full rounded-full bg-brand-500 transition-[width] duration-150 ease-out" style="width:0%"></div>
                </div>
            </div>
            <div data-upload-error class="mt-3 hidden rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700"></div>
        </div>

        {{-- History --}}
        <div class="lf-card overflow-hidden p-0">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-sm font-semibold text-slate-900">Update history</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs tracking-wide text-slate-400 uppercase">
                        <tr>
                            <th class="px-6 py-3 font-medium">Version</th>
                            <th class="px-6 py-3 font-medium">Update</th>
                            <th class="px-6 py-3 font-medium">Applied by</th>
                            <th class="px-6 py-3 font-medium">When</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($history as $entry)
                            <tr>
                                <td class="px-6 py-3 font-mono text-xs text-slate-600">v{{ $entry->version }}</td>
                                <td class="px-6 py-3 text-slate-700">{{ $entry->name ?: '—' }}</td>
                                <td class="px-6 py-3 text-slate-500">{{ $entry->user?->name ?? 'System' }}</td>
                                <td class="px-6 py-3 whitespace-nowrap text-slate-500" title="{{ $entry->created_at }}">{{ $entry->created_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-10 text-center text-sm text-slate-400">No updates have been applied yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    {{-- Upload via XHR so the operator sees real transfer progress (large packages on slow links). --}}
    <script>
    (function () {
        var form = document.querySelector('[data-update-form]');
        if (!form || !window.FormData || !window.XMLHttpRequest) return;

        var input = form.querySelector('input[type=file]');
        var btn = form.querySelector('[data-upload-btn]');
        var wrap = document.querySelector('[data-upload-progress]');
        var bar = wrap.querySelector('[data-upload-bar]');
        var pctEl = wrap.querySelector('[data-upload-pct]');
        var label = wrap.querySelector('[data-upload-label]');
        var errBox = document.querySelector('[data-upload-error]');

        function fail(msg) {
            wrap.classList.add('hidden');
            btn.disabled = false;
            btn.classList.remove('opacity-60');
            errBox.textContent = msg;
            errBox.classList.remove('hidden');
        }

        form.addEventListener('submit', function (e) {
            if (!input.files || !input.files.length) return; // let native "required" handle the empty case
            e.preventDefault();

            errBox.classList.add('hidden');
            wrap.classList.remove('hidden');
            btn.disabled = true;
            btn.classList.add('opacity-60');
            bar.style.width = '0%';
            pctEl.textContent = '0%';
            label.textContent = 'Uploading ' + input.files[0].name;

            var xhr = new XMLHttpRequest();
            xhr.open('POST', form.action);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Accept', 'application/json');

            xhr.upload.addEventListener('progress', function (ev) {
                if (!ev.lengthComputable) return;
                var p = Math.round(ev.loaded / ev.total * 100);
                bar.style.width = p + '%';
                pctEl.textContent = p + '%';
                if (p >= 100) label.textContent = 'Processing package…';
            });
            xhr.addEventListener('load', function () {
                var res = {};
                try { res = JSON.parse(xhr.responseText); } catch (_) {}
                if (xhr.status >= 200 && xhr.status < 300) {
                    bar.style.width = '100%';
                    pctEl.textContent = '100%';
                    label.textContent = 'Done';
                    window.location = res.redirect || window.location.pathname;
                } else {
                    fail(res.message || 'Upload failed. The file may exceed this server\'s upload limit.');
                }
            });
            xhr.addEventListener('error', function () { fail('Upload failed. Check your connection and try again.'); });

            xhr.send(new FormData(form));
        });
    })();
    </script>
</x-admin-layout>
