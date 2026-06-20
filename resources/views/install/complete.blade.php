<x-install-layout title="Done" step="complete">
    <div class="text-center">
        <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl text-white" style="background-image:linear-gradient(135deg,var(--color-brand-500),var(--color-brand-700))">
            <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
        </span>
        <h1 class="mt-5 text-xl font-semibold text-slate-900">{{ config('linkforge.name') }} is installed</h1>
        <p class="mx-auto mt-1.5 max-w-md text-sm text-slate-500">Your site is ready. Sign in with the admin account you just created to finish setting things up.</p>

        <a href="{{ route('login') }}" class="mt-6 inline-flex items-center justify-center rounded-lg bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700">Sign in to your dashboard</a>
    </div>

    <div class="mt-8 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
        <p class="font-medium text-slate-900">Recommended next steps</p>
        <ul class="mt-2 space-y-1.5">
            <li class="flex gap-2"><span class="text-brand-600">1.</span> Add a cron job: <code class="rounded bg-white px-1.5 py-0.5 font-mono text-xs">* * * * * php {{ base_path('artisan') }} schedule:run</code></li>
            <li class="flex gap-2"><span class="text-brand-600">2.</span> Configure mail, billing and branding under <span class="font-medium">Admin &rarr; Settings</span>.</li>
            <li class="flex gap-2"><span class="text-brand-600">3.</span> For security, you may delete the <span class="font-mono text-xs">/install</span> step. It is already sealed automatically.</li>
        </ul>
    </div>
</x-install-layout>
