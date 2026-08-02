<!doctype html>
<html lang="en" class="h-full"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Browser Monitoring · Pulsewatch</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-full bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100"><x-app-header active="browser" />
<main class="mx-auto max-w-screen-2xl px-4 py-6 sm:px-6">
    <div class="flex flex-wrap items-start justify-between gap-4"><div><p class="text-sm font-medium text-emerald-600">Application monitoring</p><h1 class="text-2xl font-semibold">Browser Monitoring</h1><p class="mt-1 text-sm text-slate-500">High-frequency page load, AJAX, HTMX, Web Vitals, and JavaScript telemetry for one Browser Agent.</p></div>@if(auth()->user()->is_admin)<a href="{{ route('settings.index') }}#browser-agent" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-900">Manage Browser Agents</a>@endif</div>
    @if(session('status'))<div class="mt-4 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
    @if($project)
        <form method="GET" class="mt-6 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><div class="flex flex-wrap items-end gap-3"><label class="min-w-64 flex-1 text-xs font-medium text-slate-500">Browser Agent<select name="project" class="setup-input mt-1">@foreach($projects as $item)<option value="{{ $item->id }}" @selected($project->is($item))>{{ $item->name }}</option>@endforeach</select></label><label class="text-xs font-medium text-slate-500">From date and time<input name="from" type="datetime-local" value="{{ $fromInput }}" required class="setup-input mt-1"></label><label class="text-xs font-medium text-slate-500">To date and time<input name="to" type="datetime-local" value="{{ $toInput }}" required class="setup-input mt-1"></label><button class="rounded-xl bg-emerald-500 px-5 py-3 font-medium text-white hover:bg-emerald-400">Search</button><a href="{{ route('browser-monitoring.index', ['project' => $project->id]) }}" class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-medium dark:border-slate-700">Last 24 hours</a></div><p class="mt-3 text-xs text-slate-500">Showing events from {{ $rangeLabel }} · {{ $matchingEventCount }} matching events</p></form>
        <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <x-browser-stat label="Main page loads" :value="$pageLoadCount" color="emerald" />
            <x-browser-stat label="Average page load" :value="$averageLoad === null ? '—' : number_format($averageLoad).' ms'" color="sky" />
            <x-browser-stat label="AJAX / HTMX" :value="$asyncRequestCount" color="violet" />
            <x-browser-stat label="Average async time" :value="$averageAsync === null ? '—' : number_format($averageAsync).' ms'" color="amber" />
            <x-browser-stat label="Request / JS errors" :value="$failedRequestCount + $browserErrorCount" :color="($failedRequestCount + $browserErrorCount) ? 'red' : 'emerald'" />
        </section>

        <section class="mt-5 rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-100 p-5 dark:border-slate-800"><h2 class="font-semibold">Main requests and linked activity</h2><p class="mt-1 text-sm text-slate-500"><span class="font-medium text-emerald-600">Main request</span> is a browser navigation or reload. <span class="font-medium text-violet-600">AJAX / HTMX</span> calls run inside that page without a reload.</p></div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($pageViews as $view)
                    @php($main = $view['main'])
                    <details class="group p-4 open:bg-slate-50/70 dark:open:bg-slate-950/40">
                        <summary class="grid cursor-pointer list-none items-center gap-3 md:grid-cols-[minmax(0,2fr)_120px_120px_120px_24px]">
                            <div class="min-w-0"><div class="flex items-center gap-2"><span class="rounded-md bg-emerald-100 px-2 py-1 text-[11px] font-semibold uppercase text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">Main {{ $main->message === 'reload' ? 'reload' : 'navigation' }}</span><span class="truncate text-sm font-medium">{{ $main->page_url }}</span></div><time class="mt-1 block text-xs text-slate-400">{{ $main->occurred_at->format('M j, H:i:s') }}</time></div>
                            <div><p class="text-xs text-slate-400">Load time</p><p class="font-semibold">{{ number_format($main->metrics['load_time'] ?? 0) }} ms</p></div>
                            <div><p class="text-xs text-slate-400">AJAX / HTMX</p><p class="font-semibold text-violet-600">{{ $view['requests']->count() }}</p></div>
                            <div><p class="text-xs text-slate-400">Errors</p><p class="font-semibold {{ ($view['failed_requests'] + $view['errors']->count()) ? 'text-red-500' : 'text-emerald-500' }}">{{ $view['failed_requests'] + $view['errors']->count() }}</p></div>
                            <span class="text-slate-400 transition group-open:rotate-180">⌄</span>
                        </summary>
                        <div class="mt-4 grid gap-3 border-l-2 border-slate-200 pl-4 dark:border-slate-700">
                            <div class="grid gap-2 text-xs sm:grid-cols-5"><span>TTFB <b>{{ number_format($main->metrics['ttfb'] ?? 0) }} ms</b></span><span>DOM ready <b>{{ number_format($main->metrics['dom_interactive'] ?? 0) }} ms</b></span><span>LCP <b>{{ number_format($main->metrics['lcp'] ?? 0) }} ms</b></span><span>INP <b>{{ number_format($main->metrics['inp'] ?? 0) }} ms</b></span><span>CLS <b>{{ $main->metrics['cls'] ?? 0 }}</b></span></div>
                            @foreach($view['requests'] as $async)
                                @php($status = $async->metrics['status'] ?? 0) @php($failed = $status === 0 || $status >= 400)
                                <div class="grid items-center gap-2 rounded-lg border p-3 text-sm sm:grid-cols-[75px_55px_minmax(0,1fr)_80px_80px] {{ $failed ? 'border-red-200 bg-red-50/60 dark:border-red-950 dark:bg-red-950/20' : 'border-slate-200 dark:border-slate-800' }}"><span class="rounded px-2 py-1 text-center text-[11px] font-semibold uppercase {{ $async->event_type === 'htmx' ? 'bg-fuchsia-100 text-fuchsia-700' : 'bg-violet-100 text-violet-700' }}">{{ $async->event_type }}</span><span class="font-mono text-xs">{{ $async->message }}</span><span class="truncate" title="{{ $async->source }}">{{ $async->source }}</span><span class="font-medium {{ $failed ? 'text-red-500' : 'text-emerald-600' }}">{{ $status ?: 'Network' }}</span><span>{{ number_format($async->metrics['duration'] ?? 0) }} ms</span></div>
                            @endforeach
                            @foreach($view['errors'] as $error)<div class="rounded-lg border border-red-200 bg-red-50/60 p-3 dark:border-red-950 dark:bg-red-950/20"><span class="text-[11px] font-semibold uppercase text-red-500">{{ str_replace('_', ' ', $error->event_type) }}</span><p class="mt-1 text-sm">{{ $error->message }}</p><p class="truncate text-xs text-slate-500">{{ $error->source }}</p></div>@endforeach
                            @if($view['requests']->isEmpty() && $view['errors']->isEmpty())<p class="text-sm text-slate-500">No AJAX, HTMX, or JavaScript activity was recorded during this page view.</p>@endif
                        </div>
                    </details>
                @empty<div class="p-8 text-center"><h3 class="font-semibold">No main requests received yet</h3><p class="mt-2 text-sm text-slate-500">Install the snippet and reload the monitored website.</p></div>@endforelse
            </div>
            @if($pageLoads && $pageLoads->hasPages())<div class="border-t border-slate-100 p-4 dark:border-slate-800">{{ $pageLoads->links() }}</div>@endif
        </section>

        <section class="mt-5 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"><h2 class="font-semibold">Slowest pages · selected range</h2><div class="mt-4 grid gap-3 lg:grid-cols-2">@forelse($slowPages as $page)<div><div class="flex justify-between gap-3 text-sm"><span class="truncate">{{ $page['url'] }}</span><span class="shrink-0 font-medium">{{ number_format($page['average']) }} ms</span></div><div class="mt-1 h-2 overflow-hidden rounded bg-slate-100 dark:bg-slate-800"><div class="h-full rounded bg-amber-400" style="width:{{ min(100,$page['average']/50) }}%"></div></div></div>@empty<p class="text-sm text-slate-500">No page performance data received in this range.</p>@endforelse</div></section>
    @else<div class="mt-6 rounded-xl border border-slate-200 bg-white p-8 text-center dark:border-slate-800 dark:bg-slate-900"><h2 class="text-lg font-semibold">No Browser Agents connected</h2><p class="mt-2 text-sm text-slate-500">Install a Browser Agent from Settings to begin receiving application telemetry.</p>@if(auth()->user()->is_admin)<a href="{{ route('settings.index') }}#browser-agent" class="mt-4 inline-block rounded-xl bg-emerald-500 px-5 py-3 font-medium text-white">Install Browser Agent</a>@endif</div>@endif
</main></body></html>
