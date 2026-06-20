<x-app-layout title="Account settings">
    <x-slot:header>Account settings</x-slot:header>

    <x-demo-lock>Changing the password or email and deleting the account are disabled in the live demo.</x-demo-lock>

    @if (session('status'))<div class="mb-5 rounded-lg bg-brand-50 px-4 py-3 text-sm text-brand-700">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="mb-5 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>@endif

    @php $tabs = ['profile' => 'Profile', 'security' => 'Security']; @endphp

    <div class="grid gap-6 lg:grid-cols-[200px_1fr]">
        <nav class="flex gap-1 overflow-x-auto lg:flex-col">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('account', ['tab' => $key]) }}"
                   @class(['rounded-lg px-3.5 py-2 text-sm font-medium transition whitespace-nowrap', 'bg-brand-50 text-brand-700' => $tab === $key, 'text-slate-600 hover:bg-slate-100' => $tab !== $key])>
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <div class="max-w-2xl">
            @include('account.partials.'.$tab)
        </div>
    </div>
</x-app-layout>
