<!DOCTYPE html>
<html lang="en" class="scheme-light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashboard · Pulsewatch</title>
    <script>
        if (localStorage.getItem('pulsewatch-theme') === 'dark') {
            document.documentElement.classList.add('dark');
            document.documentElement.classList.replace('scheme-light', 'scheme-dark');
        }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
<div class="min-h-screen lg:grid lg:grid-cols-[220px_1fr]">
    <aside class="border-b border-slate-200 bg-white px-5 py-5 dark:border-slate-800 dark:bg-slate-900/80 lg:border-b-0 lg:border-r">
        <div class="flex items-center gap-3 text-lg font-semibold"><span class="grid size-9 place-items-center rounded-xl bg-emerald-400 text-slate-950">P</span>Pulsewatch</div>
        <nav class="mt-8 flex gap-2 lg:grid">
            <a class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:bg-slate-800 dark:text-white" href="{{ route('dashboard') }}">Live monitor</a>
            <a class="rounded-lg px-3 py-2 text-sm text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800" href="{{ route('team.index') }}">Team</a>
        </nav>
    </aside>

    <main class="min-w-0">
        <header class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 bg-white/80 px-5 py-4 dark:border-slate-800 dark:bg-slate-900/60">
            <div>
                <h1 class="text-lg font-semibold">Infrastructure overview</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">High-frequency telemetry without page reloads</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" data-theme-toggle class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800" aria-label="Switch color theme"><span data-theme-light>☾ Dark</span><span data-theme-dark class="hidden">☀ Light</span></button>
                <details class="group relative">
                    <summary class="cursor-pointer list-none rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">Account <span class="ml-1 inline-block transition group-open:rotate-180">⌄</span></summary>
                    <div class="absolute right-0 z-50 mt-2 w-48 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                        <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-800"><p class="truncate text-sm font-medium">{{ auth()->user()->name }}</p><p class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</p></div>
                        <a href="{{ route('profile.edit') }}" class="block px-3 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800">Profile</a>
                        <a href="{{ route('team.index') }}" class="block px-3 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800">Team</a>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="block w-full px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950">Sign out</button></form>
                    </div>
                </details>
            </div>
        </header>

        <div id="monitor-dashboard" data-endpoint="{{ route('dashboard.data') }}" data-processes-endpoint="{{ route('dashboard.processes') }}" data-storage-endpoint="{{ route('dashboard.storage') }}" class="p-4 sm:p-6">
            @if ($agents->isEmpty())
                <div class="rounded-xl border border-slate-200 bg-white p-8 text-center dark:border-slate-800 dark:bg-slate-900"><h2 class="text-xl font-semibold">No agents connected</h2><p class="mt-2 text-slate-500 dark:text-slate-400">Configure and start an agent; it will appear here after its first metrics request.</p></div>
            @else
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div class="flex flex-wrap gap-3">
                        <label class="text-xs text-slate-400">Agent
                            <select id="agent-select" class="mt-1 block min-w-64 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                @foreach ($agents as $agent)<option value="{{ $agent->id }}">{{ $agent->hostname }} · {{ Str::limit($agent->id, 18) }}</option>@endforeach
                            </select>
                        </label>
                        <label class="text-xs text-slate-400">Time range
                            <select id="range-select" class="mt-1 block rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                <option value="1">Last hour</option><option value="6">Last 6 hours</option><option value="24">Last 24 hours</option><option value="72">Last 3 days</option>
                            </select>
                        </label>
                    </div>
                    <div class="flex items-center gap-2 pb-2 text-xs text-slate-400"><span id="status-dot" class="size-2 rounded-full bg-slate-600"></span><span id="connection-status">Loading telemetry…</span></div>
                </div>

                <nav class="mt-5 flex gap-1 border-b border-slate-200 dark:border-slate-800" aria-label="Dashboard sections">
                    <button type="button" data-dashboard-tab="overview" class="border-b-2 border-emerald-500 px-4 py-3 text-sm font-medium text-emerald-700 dark:text-emerald-300">Overview</button>
                    <button type="button" data-dashboard-tab="processes" class="border-b-2 border-transparent px-4 py-3 text-sm text-slate-500 hover:text-slate-800 dark:hover:text-slate-200">Processes</button>
                    <button type="button" data-dashboard-tab="storage" class="border-b-2 border-transparent px-4 py-3 text-sm text-slate-500 hover:text-slate-800 dark:hover:text-slate-200">Storage</button>
                </nav>

                <div data-dashboard-panel="overview">
                <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <section class="metric-card"><p>CPU now</p><strong id="cpu-now">—</strong><span id="cpu-peak">Peak —</span></section>
                    <section class="metric-card"><p>RAM used</p><strong id="memory-now">—</strong><span id="memory-total">of —</span></section>
                    <section class="metric-card"><p>Processes in latest sample</p><strong id="process-count">—</strong><span>Top 10 shown below</span></section>
                    <section class="metric-card"><p>Last sample</p><strong id="last-sample">—</strong><span>AJAX refresh every 5 seconds</span></section>
                </div>

                <div class="mt-4 grid gap-4 xl:grid-cols-2">
                    <section class="chart-panel"><div class="panel-heading"><h2>CPU utilization</h2><span>Percent</span></div><div class="h-64"><canvas id="cpu-chart" class="block size-full" aria-label="CPU usage time-series chart"></canvas></div></section>
                    <section class="chart-panel"><div class="panel-heading"><h2>RAM utilization</h2><span>Percent used</span></div><div class="h-64"><canvas id="memory-chart" class="block size-full" aria-label="RAM usage time-series chart"></canvas></div></section>
                </div>

                <div class="mt-4 grid gap-4 xl:grid-cols-[1.3fr_1fr]">
                    <section class="chart-panel overflow-hidden"><div class="panel-heading"><h2>Top processes</h2><span>Latest sample</span></div><div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="text-xs text-slate-500"><tr><th class="py-2">Process</th><th>PID</th><th class="text-right">CPU</th><th class="text-right">Memory</th><th class="text-right">State</th></tr></thead><tbody id="process-table" class="divide-y divide-slate-200 dark:divide-slate-800"></tbody></table></div></section>
                    <section class="chart-panel"><div class="panel-heading"><h2>Disk occupancy</h2><span id="disk-synced">Latest sample</span></div><div id="disk-list" class="space-y-5"></div></section>
                </div>

                <section class="chart-panel mt-4">
                    <div class="panel-heading"><h2>Process CPU heatmap</h2><span>Darker cells indicate higher average CPU</span></div>
                    <div id="process-heatmap" class="overflow-x-auto"></div>
                </section>
                </div>

                <section data-dashboard-panel="processes" class="mt-5 hidden space-y-4">
                    <div class="chart-panel">
                        <div class="panel-heading"><h2>Historical process snapshot</h2><span id="historical-process-time">Choose a point in time</span></div>
                        <label for="process-time-slider" class="mb-2 block text-xs text-slate-500">Move through the selected dashboard range</label>
                        <input id="process-time-slider" type="range" min="0" max="0" value="0" class="w-full accent-emerald-500">
                        <div class="mt-2 flex justify-between text-xs text-slate-500"><span id="process-range-start">—</span><span id="process-range-end">—</span></div>
                    </div>
                    <div class="chart-panel overflow-hidden">
                        <div class="panel-heading"><h2>Processes running at selected time</h2><span id="historical-process-count">0 processes</span></div>
                        <div class="overflow-x-auto"><table class="min-w-[980px] w-full text-left text-sm"><thead class="text-xs text-slate-500"><tr><th class="py-2">Process</th><th>PID</th><th>User</th><th>Command</th><th class="text-right">CPU</th><th class="text-right">Memory</th><th class="text-right">State</th><th class="text-right">Started</th></tr></thead><tbody id="historical-process-table" class="divide-y divide-slate-200 dark:divide-slate-800"></tbody></table></div>
                    </div>
                </section>

                <section data-dashboard-panel="storage" class="mt-5 hidden space-y-4">
                    <div class="chart-panel">
                        <div class="panel-heading"><h2>Historical storage snapshot</h2><span id="historical-storage-time">Choose a point in time</span></div>
                        <label for="storage-time-slider" class="mb-2 block text-xs text-slate-500">Move through the selected dashboard range</label>
                        <input id="storage-time-slider" type="range" min="0" max="0" value="0" class="w-full accent-emerald-500">
                        <div class="mt-2 flex justify-between text-xs text-slate-500"><span id="storage-range-start">—</span><span id="storage-range-end">—</span></div>
                    </div>
                    <div class="chart-panel">
                        <div class="panel-heading"><h2>Storage volumes</h2><span id="storage-synced">Selected sample</span></div>
                        <div id="storage-list" class="grid gap-4 md:grid-cols-2"></div>
                    </div>
                </section>

                <p id="dashboard-error" class="mt-4 hidden rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300"></p>
            @endif
        </div>
    </main>
</div>
</body>
</html>
