<x-app-layout title="Developer">
    <x-slot:header>{{ __('Developer') }}</x-slot:header>

    <x-demo-lock>Creating API tokens and webhooks is disabled in the live demo.</x-demo-lock>

    @if (session('status'))
        <div class="mb-5 rounded-lg bg-brand-50 px-4 py-3 text-sm text-brand-700">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-5 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ session('error') }}</div>
    @endif

    {{-- Tabs --}}
    <div class="mb-6 border-b border-slate-200">
        <nav class="-mb-px flex gap-6" aria-label="Developer sections">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('developer.index', ['tab' => $key]) }}"
                   @class([
                       'whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition',
                       'border-brand-600 text-brand-700' => $tab === $key,
                       'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' => $tab !== $key,
                   ])>
                    {{ __($label) }}
                </a>
            @endforeach
        </nav>
    </div>

    @include('developer.partials.'.$tab)
</x-app-layout>
