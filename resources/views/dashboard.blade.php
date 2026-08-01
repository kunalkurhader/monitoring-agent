<!DOCTYPE html>
<html lang="en" class="dark scheme-dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashboard · Pulsewatch</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
<div class="min-h-screen lg:grid lg:grid-cols-[220px_1fr]">
    <aside class="border-b border-slate-800 bg-slate-900/80 px-5 py-5 lg:border-b-0 lg:border-r">
        <div class="flex items-center gap-3 text-lg font-semibold"><span class="grid size-9 place-items-center rounded-xl bg-emerald-400 text-slate-950">P</span>Pulsewatch</div>
        <nav class="mt-8 flex gap-2 lg:grid">
            <a class="rounded-lg bg-slate-800 px-3 py-2 text-sm text-white" href="{{ route('dashboard') }}">Live monitor</a>
        </nav>
    </aside>

    <main class="min-w-0">
        <header class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-800 bg-slate-900/60 px-5 py-4">
            <div>
                <h1 class="text-lg font-semibold">Infrastructure overview</h1>
                <p class="text-xs text-slate-500">High-frequency telemetry without page reloads</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">Sign out</button></form>
        </header>

        <div id="monitor-dashboard" data-endpoint="{{ route('dashboard.data') }}" class="p-4 sm:p-6">
            @if ($agents->isEmpty())
                <div class="rounded-xl border border-slate-800 bg-slate-900 p-8 text-center"><h2 class="text-xl font-semibold">No agents connected</h2><p class="mt-2 text-slate-400">Configure and start an agent; it will appear here after its first metrics request.</p></div>
            @else
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div class="flex flex-wrap gap-3">
                        <label class="text-xs text-slate-400">Agent
                            <select id="agent-select" class="mt-1 block min-w-64 rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white">
                                @foreach ($agents as $agent)<option value="{{ $agent->id }}">{{ $agent->hostname }} · {{ Str::limit($agent->id, 18) }}</option>@endforeach
                            </select>
                        </label>
                        <label class="text-xs text-slate-400">Time range
                            <select id="range-select" class="mt-1 block rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white">
                                <option value="1">Last hour</option><option value="6">Last 6 hours</option><option value="24">Last 24 hours</option>
                            </select>
                        </label>
                    </div>
                    <div class="flex items-center gap-2 pb-2 text-xs text-slate-400"><span id="status-dot" class="size-2 rounded-full bg-slate-600"></span><span id="connection-status">Loading telemetry…</span></div>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <section class="metric-card"><p>CPU now</p><strong id="cpu-now">—</strong><span id="cpu-peak">Peak —</span></section>
                    <section class="metric-card"><p>RAM used</p><strong id="memory-now">—</strong><span id="memory-total">of —</span></section>
                    <section class="metric-card"><p>Processes in latest sample</p><strong id="process-count">—</strong><span>Top 10 shown below</span></section>
                    <section class="metric-card"><p>Last sample</p><strong id="last-sample">—</strong><span>AJAX refresh every 5 seconds</span></section>
                </div>

                <div class="mt-4 grid gap-4 xl:grid-cols-2">
                    <section class="chart-panel"><div class="panel-heading"><h2>CPU utilization</h2><span>Percent</span></div><div class="h-64"><canvas id="cpu-chart" aria-label="CPU usage time-series chart"></canvas></div></section>
                    <section class="chart-panel"><div class="panel-heading"><h2>RAM utilization</h2><span>Percent used</span></div><div class="h-64"><canvas id="memory-chart" aria-label="RAM usage time-series chart"></canvas></div></section>
                </div>

                <div class="mt-4 grid gap-4 xl:grid-cols-[1.3fr_1fr]">
                    <section class="chart-panel overflow-hidden"><div class="panel-heading"><h2>Top processes</h2><span>Latest sample</span></div><div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="text-xs text-slate-500"><tr><th class="py-2">Process</th><th>PID</th><th class="text-right">CPU</th><th class="text-right">Memory</th><th class="text-right">State</th></tr></thead><tbody id="process-table" class="divide-y divide-slate-800"></tbody></table></div></section>
                    <section class="chart-panel"><div class="panel-heading"><h2>Disk occupancy</h2><span id="disk-synced">Latest sample</span></div><div id="disk-list" class="space-y-5"></div></section>
                </div>

                <p id="dashboard-error" class="mt-4 hidden rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300"></p>
            @endif
        </div>
    </main>
</div>
</body>
</html>
