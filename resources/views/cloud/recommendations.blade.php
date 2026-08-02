<!doctype html>
<html lang="en" class="scheme-light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Cloud Recommendations · {{ config('app.name', 'Monitoring Agent') }}</title><script>if(localStorage.getItem('monitoring-agent-theme')==='dark'){document.documentElement.classList.add('dark')}</script>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100"><x-app-header active="cloud" />
<main class="mx-auto max-w-screen-2xl px-4 py-6 sm:px-6">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="text-sm font-medium text-emerald-600">Read-only advisor</p><h1 class="text-2xl font-semibold">Cloud Recommendations</h1><p class="mt-1 text-sm text-slate-500">Evidence-backed Security Group and Elastic IP findings. No AWS resources are modified.</p></div><a href="{{ route('cloud.index') }}" class="text-sm font-medium text-emerald-600">← Back to Cloud</a></div>
    <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-5"><section class="metric-card"><p>Active findings</p><strong>{{ $summary['total'] }}</strong><span>Across connected accounts</span></section><section class="metric-card"><p>Critical</p><strong>{{ $summary['critical'] }}</strong><span>Immediate review</span></section><section class="metric-card"><p>High</p><strong>{{ $summary['high'] }}</strong><span>Priority review</span></section><section class="metric-card"><p>Security Groups</p><strong>{{ $summary['security_groups'] }}</strong><span>Configuration findings</span></section><section class="metric-card"><p>Elastic IPs</p><strong>{{ $summary['elastic_ips'] }}</strong><span>Allocation findings</span></section></div>
    <div class="mt-5 space-y-3">
        @forelse($findings as $finding)
            @php
                $severityClass = match($finding->severity) {
                    'critical' => 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
                    'high' => 'bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-300',
                    'medium' => 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
                    default => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
                };
            @endphp
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start"><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $severityClass }}">{{ ucfirst($finding->severity) }}</span><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ ucfirst($finding->confidence) }} confidence</span><span class="text-xs text-slate-500">{{ $finding->connection->name }} · {{ $finding->region }}</span></div><h2 class="mt-3 text-base font-semibold">{{ $finding->title }}</h2><p class="mt-1 font-mono text-xs text-slate-500">{{ $finding->resource_id }}</p></div><span class="shrink-0 text-xs text-slate-500">Seen {{ $finding->last_seen_at->diffForHumans() }}</span></div>
                <div class="mt-4 rounded-xl bg-slate-50 p-4 dark:bg-slate-950"><p class="text-xs font-medium uppercase tracking-wide text-slate-500">Recommendation</p><p class="mt-1 text-sm">{{ $finding->recommendation }}</p></div>
                <details class="mt-3"><summary class="cursor-pointer text-sm font-medium text-emerald-600">View evidence</summary><pre class="mt-3 overflow-x-auto rounded-xl bg-slate-950 p-4 text-xs text-slate-200">{{ json_encode($finding->evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details>
            </article>
        @empty
            <section class="rounded-2xl border border-slate-200 bg-white p-10 text-center dark:border-slate-800 dark:bg-slate-900"><h2 class="text-xl font-semibold">No active recommendations</h2><p class="mt-2 text-sm text-slate-500">No Security Group or Elastic IP issues were found in the latest synchronized inventory.</p></section>
        @endforelse
    </div>
</main></body></html>
