@props(['active' => ''])

@php
    $navItem = fn (string $name) => $active === $name
        ? 'bg-emerald-50 font-medium text-emerald-800 dark:bg-slate-800 dark:text-white'
        : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800';
@endphp

<header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur dark:border-slate-800 dark:bg-slate-900/95">
    <div class="mx-auto max-w-screen-2xl px-4 sm:px-6">
        <div class="flex h-16 items-center gap-3">
            <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-2.5 font-semibold">
                @if(config('app.branding_logo'))
                    <span class="grid size-9 shrink-0 place-items-center overflow-hidden rounded-xl bg-white ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700"><img src="{{ config('app.branding_logo') }}" alt="" class="max-h-full max-w-full object-contain"></span>
                @else
                    <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-emerald-400 text-sm font-bold text-slate-950 shadow-sm shadow-emerald-500/20">{{ Str::upper(Str::substr(config('app.name', 'Monitoring Agent'), 0, 1)) }}</span>
                @endif
                <span class="truncate">{{ config('app.name', 'Monitoring Agent') }}</span>
            </a>

            <nav class="ml-4 hidden items-center gap-1 lg:flex" aria-label="Primary navigation">
                <a href="{{ route('dashboard') }}" class="rounded-lg px-3 py-2 text-sm {{ $navItem('dashboard') }}">Dashboard</a>
                <a href="{{ route('monitors.index') }}" class="rounded-lg px-3 py-2 text-sm {{ $navItem('monitor') }}">Servers</a>
                <a href="{{ route('cloud.index') }}" class="rounded-lg px-3 py-2 text-sm {{ $navItem('cloud') }}">Cloud</a>
                <a href="{{ route('website-monitors.index') }}" class="rounded-lg px-3 py-2 text-sm {{ $navItem('uptime') }}">Uptime</a>
                <a href="{{ route('browser-monitoring.index') }}" class="rounded-lg px-3 py-2 text-sm {{ $navItem('browser') }}">Browser</a>
                @if(auth()->user()->is_admin)
                    <a href="{{ route('settings.index') }}" class="rounded-lg px-3 py-2 text-sm {{ $navItem('settings') }}">Settings</a>
                @endif
            </nav>

            <div class="ml-auto flex items-center gap-2">
                <button type="button" data-theme-toggle class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800" aria-label="Switch color theme">
                    <span data-theme-light><span aria-hidden="true">☾</span><span class="ml-1 hidden xl:inline">Dark</span></span>
                    <span data-theme-dark class="hidden"><span aria-hidden="true">☀</span><span class="ml-1 hidden xl:inline">Light</span></span>
                </button>

                <details class="group relative hidden sm:block">
                    <summary class="flex cursor-pointer list-none items-center gap-2 rounded-full bg-slate-100 py-1.5 pl-1.5 pr-3 text-sm text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                        <span class="grid size-7 place-items-center rounded-full bg-emerald-500 text-xs font-medium text-white">{{ Str::upper(Str::substr(auth()->user()->name, 0, 1)) }}</span>
                        <span class="hidden max-w-28 truncate md:block">{{ auth()->user()->name }}</span>
                        <span class="text-slate-400 transition group-open:rotate-180">⌄</span>
                    </summary>
                    <div class="absolute right-0 z-50 mt-2 w-56 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                        <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-800"><p class="truncate text-sm font-medium">{{ auth()->user()->name }}</p><p class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</p></div>
                        <a href="{{ route('profile.edit') }}" class="block px-3 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800">Profile</a>
                        <a href="{{ route('team.index') }}" class="block px-3 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800">Team</a>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="block w-full px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950">Sign out</button></form>
                    </div>
                </details>

                <button type="button" data-mobile-menu-toggle aria-expanded="false" aria-controls="mobile-navigation" class="grid size-10 place-items-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 lg:hidden">
                    <span class="sr-only">Open navigation</span>
                    <span data-mobile-menu-open aria-hidden="true" class="text-xl">☰</span>
                    <span data-mobile-menu-close aria-hidden="true" class="hidden text-xl">×</span>
                </button>
            </div>
        </div>

        <div id="mobile-navigation" data-mobile-menu-panel class="hidden border-t border-slate-100 py-3 dark:border-slate-800 lg:hidden">
            <nav class="grid gap-1" aria-label="Mobile navigation">
                <a href="{{ route('dashboard') }}" class="rounded-xl px-3 py-2.5 text-sm {{ $navItem('dashboard') }}">Dashboard</a>
                <a href="{{ route('monitors.index') }}" class="rounded-xl px-3 py-2.5 text-sm {{ $navItem('monitor') }}">Server Monitoring</a>
                <a href="{{ route('cloud.index') }}" class="rounded-xl px-3 py-2.5 text-sm {{ $navItem('cloud') }}">AWS Cloud</a>
                <a href="{{ route('website-monitors.index') }}" class="rounded-xl px-3 py-2.5 text-sm {{ $navItem('uptime') }}">Website Uptime</a>
                <a href="{{ route('browser-monitoring.index') }}" class="rounded-xl px-3 py-2.5 text-sm {{ $navItem('browser') }}">Browser Monitoring</a>
                @if(auth()->user()->is_admin)
                    <a href="{{ route('settings.index') }}" class="rounded-xl px-3 py-2.5 text-sm {{ $navItem('settings') }}">Settings</a>
                @endif
            </nav>
            <div class="mt-3 border-t border-slate-100 pt-3 dark:border-slate-800 sm:hidden">
                <p class="px-3 text-sm font-medium">{{ auth()->user()->name }}</p>
                <p class="px-3 text-xs text-slate-500">{{ auth()->user()->email }}</p>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <a href="{{ route('profile.edit') }}" class="rounded-lg bg-slate-100 px-3 py-2 text-center text-sm dark:bg-slate-800">Profile</a>
                    <a href="{{ route('team.index') }}" class="rounded-lg bg-slate-100 px-3 py-2 text-center text-sm dark:bg-slate-800">Team</a>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="mt-2">@csrf<button class="w-full rounded-lg px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950">Sign out</button></form>
            </div>
        </div>
    </div>
</header>
