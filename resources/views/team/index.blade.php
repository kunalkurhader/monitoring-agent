<!DOCTYPE html>
<html lang="en" class="scheme-light">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Team · Pulsewatch</title>
    <script>if(localStorage.getItem('pulsewatch-theme')==='dark'){document.documentElement.classList.add('dark');document.documentElement.classList.replace('scheme-light','scheme-dark')}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
<div class="mx-auto max-w-6xl px-5 py-8">
    <header class="flex flex-wrap items-center justify-between gap-4"><div><a href="{{ route('dashboard') }}" class="text-sm text-emerald-600 hover:underline">← Dashboard</a><h1 class="mt-2 text-3xl font-semibold">Team</h1><p class="mt-1 text-sm text-slate-500">Everyone can see accepted members and pending invitations.</p></div><button type="button" data-theme-toggle class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"><span data-theme-light>☾ Dark</span><span data-theme-dark class="hidden">☀ Light</span></button></header>

    @if(session('status'))<div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-200">{{ $errors->first() }}</div>@endif

    @if(auth()->user()->is_admin)
    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="font-semibold">Invite a co-worker</h2>
        <form method="POST" action="{{ route('team.invitations.store') }}" class="mt-4 grid gap-3 sm:grid-cols-[1fr_150px_auto]">@csrf
            <input type="email" name="email" value="{{ old('email') }}" placeholder="coworker@example.com" required class="setup-input">
            <select name="role" class="setup-input"><option value="member">Member</option><option value="admin">Administrator</option></select>
            <button class="rounded-xl bg-emerald-500 px-5 py-3 font-medium text-white hover:bg-emerald-400">Send invitation</button>
        </form>
        <p class="mt-2 text-xs text-slate-500">Invitation links expire after seven days. Administrators can invite people and manage roles.</p>
    </section>
    @endif

    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="font-semibold">Accepted members <span class="text-sm font-normal text-slate-500">({{ $members->count() }})</span></h2>
        <div class="mt-4 overflow-x-auto"><table class="w-full min-w-[650px] text-left text-sm"><thead class="text-xs text-slate-500"><tr><th class="py-2">Name</th><th>Email</th><th>Joined</th><th class="text-right">Role</th></tr></thead><tbody class="divide-y divide-slate-200 dark:divide-slate-800">
        @foreach($members as $member)<tr><td class="py-3 font-medium">{{ $member->name }}</td><td>{{ $member->email }}</td><td class="text-slate-500">{{ $member->created_at->format('M j, Y') }}</td><td class="text-right">
            @if(auth()->user()->is_admin)
            <form method="POST" action="{{ route('team.users.role', $member) }}" class="inline-flex items-center gap-2">@csrf @method('PATCH')<select name="role" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="member" @selected(!$member->is_admin)>Member</option><option value="admin" @selected($member->is_admin)>Admin</option></select><button class="text-xs text-emerald-600 hover:underline">Save</button></form>
            @else<span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs dark:bg-slate-800">{{ $member->is_admin ? 'Admin' : 'Member' }}</span>@endif
        </td></tr>@endforeach
        </tbody></table></div>
    </section>

    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="font-semibold">Pending invitations <span class="text-sm font-normal text-slate-500">({{ $invitations->count() }})</span></h2>
        <div class="mt-4 overflow-x-auto"><table class="w-full min-w-[650px] text-left text-sm"><thead class="text-xs text-slate-500"><tr><th class="py-2">Email</th><th>Invited by</th><th>Role</th><th>Sent</th><th class="text-right">Status</th></tr></thead><tbody class="divide-y divide-slate-200 dark:divide-slate-800">
        @forelse($invitations as $invitation)<tr><td class="py-3 font-medium">{{ $invitation->email }}</td><td>{{ $invitation->inviter?->name ?? 'Former member' }}</td><td>{{ ucfirst($invitation->role) }}</td><td class="text-slate-500">{{ $invitation->created_at->diffForHumans() }}</td><td class="text-right"><span class="rounded-full px-2.5 py-1 text-xs {{ $invitation->expires_at->isPast() ? 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-200' : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-200' }}">{{ $invitation->expires_at->isPast() ? 'Expired' : 'Pending' }}</span></td></tr>@empty<tr><td colspan="5" class="py-8 text-center text-slate-500">No pending invitations.</td></tr>@endforelse
        </tbody></table></div>
    </section>
</div></body></html>
