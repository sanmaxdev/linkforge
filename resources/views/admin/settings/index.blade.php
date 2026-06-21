<x-admin-layout title="Settings">
    <x-slot:header>Settings</x-slot:header>

    <x-demo-lock>Live demo: every setting is shown so you can explore the full admin, but changes are disabled here. Secret values (API keys, SMTP, payment keys) and server details are masked.</x-demo-lock>

    @if (session('status'))<div class="mb-5 rounded-lg bg-brand-50 px-4 py-3 text-sm text-brand-700">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="mb-5 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>@endif

    <div class="grid gap-6 lg:grid-cols-[200px_1fr]">
        {{-- Sub-nav --}}
        <nav class="flex gap-1 overflow-x-auto lg:flex-col">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('admin.settings', ['tab' => $key]) }}"
                   @class(['rounded-lg px-3.5 py-2 text-sm font-medium transition whitespace-nowrap', 'bg-brand-50 text-brand-700' => $tab === $key, 'text-slate-600 hover:bg-slate-100' => $tab !== $key])>
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        {{-- In demo, a disabled <fieldset> makes every control in the section read-only
             (DemoGuard also blocks the save server-side). Layout-neutral otherwise. --}}
        <fieldset @disabled(\App\Support\Demo::enabled()) class="max-w-2xl min-w-0">
            @include('admin.settings.partials.'.$tab)
        </fieldset>
    </div>
</x-admin-layout>
