<x-admin-layout title="Users">
    <x-slot:header>Users</x-slot:header>

    <x-demo-lock>Editing, suspending or deleting accounts is disabled in the live demo.</x-demo-lock>

    @if (session('status'))<div class="mb-5 rounded-lg bg-brand-50 px-4 py-3 text-sm text-brand-700">{{ session('status') }}</div>@endif

    {{-- Filters --}}
    <form method="GET" class="mb-5 flex flex-wrap items-end gap-3">
        <div class="relative min-w-0 flex-1 sm:max-w-xs">
            <svg class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            <input name="q" value="{{ $filters['q'] }}" class="lf-input pl-9" placeholder="Search name or email...">
        </div>
        <select name="plan" class="lf-input !w-auto">
            <option value="">All plans</option>
            <option value="free" @selected($filters['plan'] === 'free')>Free (no plan)</option>
            @foreach ($plans as $p)
                <option value="{{ $p->id }}" @selected((string) $filters['plan'] === (string) $p->id)>{{ $p->name }}</option>
            @endforeach
        </select>
        <select name="status" class="lf-input !w-auto">
            <option value="">Any status</option>
            @foreach (['active', 'suspended', 'pending'] as $st)
                <option value="{{ $st }}" @selected($filters['status'] === $st)>{{ ucfirst($st) }}</option>
            @endforeach
        </select>
        <select name="role" class="lf-input !w-auto">
            <option value="">Any role</option>
            <option value="user" @selected($filters['role'] === 'user')>User</option>
            <option value="admin" @selected($filters['role'] === 'admin')>Admin</option>
        </select>
        <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-slate-700">Filter</button>
        <a href="{{ route('admin.users.export', request()->query()) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
            Export
        </a>
    </form>

    <div class="lf-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs tracking-wide text-slate-400 uppercase">
                    <tr>
                        <th class="px-5 py-3 font-medium">User</th>
                        <th class="px-5 py-3 font-medium">Plan</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                        <th class="px-5 py-3 font-medium">Usage</th>
                        <th class="px-5 py-3 font-medium">Joined</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($users as $u)
                        @php $sb = ['active' => 'bg-brand-50 text-brand-700', 'suspended' => 'bg-red-50 text-red-700', 'pending' => 'bg-amber-50 text-amber-700'][$u->status] ?? 'bg-slate-100 text-slate-500'; @endphp
                        <tr class="hover:bg-slate-50/50">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('admin.users.show', $u) }}" class="font-medium text-slate-900 hover:text-brand-700">{{ $u->name }}</a>
                                    @if ($u->role === 'admin')<span class="rounded-full bg-slate-900 px-2 py-0.5 text-[10px] font-medium text-white">admin</span>@endif
                                </div>
                                <div class="text-xs text-slate-400">{{ $u->email }}</div>
                            </td>
                            <td class="px-5 py-3 text-slate-600">{{ $u->plan?->name ?? 'Free' }}</td>
                            <td class="px-5 py-3"><span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $sb }}">{{ ucfirst($u->status) }}</span></td>
                            <td class="px-5 py-3 text-xs text-slate-500">{{ $u->links_count }} links · {{ $u->bio_pages_count }} bio · {{ $u->qr_codes_count }} QR</td>
                            <td class="px-5 py-3 text-slate-500">{{ $u->created_at?->format('M j, Y') }}</td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('admin.users.show', $u) }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-50">Manage</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-8 text-center text-sm text-slate-400">No users match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">{{ $users->links() }}</div>
</x-admin-layout>
