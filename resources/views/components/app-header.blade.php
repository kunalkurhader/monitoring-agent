@props(['active' => ''])

<header class="border-b border-slate-200 bg-white/90 dark:border-slate-800 dark:bg-slate-900/80">
    <div class="mx-auto flex max-w-screen-2xl flex-wrap items-center gap-4 px-4 py-3 sm:px-6">
        <a href="{{ route('dashboard') }}" class="mr-2 flex items-center gap-2 font-semibold"><span class="grid size-8 place-items-center rounded-lg bg-emerald-400 text-slate-950">P</span>Pulsewatch</a>
        <nav class="flex items-center gap-1" aria-label="Primary navigation">
            <a href="{{ route('dashboard') }}" class="rounded-lg px-3 py-2 text-sm {{ $active === 'dashboard' ? 'bg-emerald-50 font-medium text-emerald-800 dark:bg-slate-800 dark:text-white' : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' }}">Dashboard</a>
            <a href="{{ route('monitors.index') }}" class="rounded-lg px-3 py-2 text-sm {{ $active === 'monitor' ? 'bg-emerald-50 font-medium text-emerald-800 dark:bg-slate-800 dark:text-white' : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' }}">Live Monitor</a>
            @if(auth()->user()->is_admin)
                <a href="{{ route('agents.install') }}" class="rounded-lg px-3 py-2 text-sm {{ $active === 'install' ? 'bg-emerald-50 font-medium text-emerald-800 dark:bg-slate-800 dark:text-white' : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' }}">Install Agent</a>
            @endif
        </nav>
        <div class="ml-auto flex items-center gap-2">
            <button type="button" data-theme-toggle class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800" aria-label="Switch color theme"><span data-theme-light>☾ Dark</span><span data-theme-dark class="hidden">☀ Light</span></button>
            <details class="group relative">
                <summary class="flex cursor-pointer list-none items-center gap-2 rounded-full bg-slate-100 py-1.5 pl-1.5 pr-3 text-sm text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"><span class="grid size-7 place-items-center rounded-full bg-emerald-500 text-xs font-medium text-white">{{ Str::upper(Str::substr(auth()->user()->name, 0, 1)) }}</span><span class="hidden max-w-28 truncate sm:block">{{ auth()->user()->name }}</span><span class="text-slate-400 transition group-open:rotate-180">⌄</span></summary>
                <div class="absolute right-0 z-50 mt-2 w-52 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                    <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-800"><p class="truncate text-sm font-medium">{{ auth()->user()->name }}</p><p class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</p></div>
                    <a href="{{ route('profile.edit') }}" class="block px-3 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800">Profile</a>
                    <a href="{{ route('team.index') }}" class="block px-3 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800">Team</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="block w-full px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950">Sign out</button></form>
                </div>
            </details>
        </div>
    </div>
</header>
