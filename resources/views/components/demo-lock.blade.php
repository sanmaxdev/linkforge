@props(['message' => 'These actions are read-only in the live demo.'])

@if (\App\Support\Demo::enabled())
    <div {{ $attributes->merge(['class' => 'mb-4 flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3.5 py-2.5 text-sm font-medium text-amber-800']) }}>
        <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        {{ $slot->isEmpty() ? $message : $slot }}
    </div>
@endif
