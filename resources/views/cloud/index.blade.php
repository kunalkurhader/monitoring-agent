<!doctype html>
<html lang="en" class="scheme-light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>AWS Cloud · {{ config('app.name', 'Monitoring Agent') }}</title><script>if(localStorage.getItem('monitoring-agent-theme')==='dark'){document.documentElement.classList.add('dark')}</script>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100"><x-app-header active="cloud" />
<main class="mx-auto max-w-screen-2xl px-4 py-6 sm:px-6">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="text-sm font-medium text-emerald-600">Cloud inventory</p><h1 class="text-2xl font-semibold">AWS Cloud</h1><p class="mt-1 text-sm text-slate-500">Inventory, current health, and public-exposure context in one view. Open a resource for historical telemetry.</p></div><div class="flex gap-2"><a href="{{ route('cloud.recommendations') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium dark:border-slate-700 dark:bg-slate-900">Recommendations @if(($summary['findings'] ?? 0) > 0)<span class="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700 dark:bg-amber-950 dark:text-amber-300">{{ $summary['findings'] }}</span>@endif</a>@if(auth()->user()->is_admin)<a href="{{ route('settings.index') }}#cloud" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium dark:border-slate-700 dark:bg-slate-900">Cloud settings</a>@endif</div></div>
    @if($connections->isEmpty())
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-10 text-center dark:border-slate-800 dark:bg-slate-900"><h2 class="text-xl font-semibold">No AWS account connected</h2><p class="mt-2 text-sm text-slate-500">Create the read-only role and add its ARN under Cloud settings.</p>@if(auth()->user()->is_admin)<a href="{{ route('settings.index') }}#cloud" class="mt-5 inline-block rounded-xl bg-emerald-500 px-5 py-3 font-medium text-white">Connect AWS</a>@endif</section>
    @else
        <form method="GET" action="{{ route('cloud.index') }}" class="chart-panel mt-6">
            <div class="panel-heading"><div><h2>Filter cloud resources</h2><p class="mt-1 text-xs text-slate-500">{{ $summary['filtered'] }} matching EC2, RDS, and S3 resources</p></div>@if(collect($filters)->filter()->isNotEmpty())<a href="{{ route('cloud.index') }}" class="text-sm font-medium text-emerald-600">Clear filters</a>@endif</div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <label class="text-xs font-medium text-slate-500">Search<input type="search" name="q" value="{{ $filters['q'] }}" class="setup-input mt-1" placeholder="Name, ID, IP, engine"></label>
                <label class="text-xs font-medium text-slate-500">AWS account<select name="account" class="setup-input mt-1"><option value="">All accounts</option>@foreach($connections as $connection)<option value="{{ $connection->id }}" @selected($filters['account'] === (string) $connection->id)>{{ $connection->name }}</option>@endforeach</select></label>
                <label class="text-xs font-medium text-slate-500">Service<select name="service" class="setup-input mt-1"><option value="">All services</option><option value="ec2" @selected($filters['service'] === 'ec2')>EC2</option><option value="rds" @selected($filters['service'] === 'rds')>RDS</option><option value="s3" @selected($filters['service'] === 's3')>S3</option></select></label>
                <label class="text-xs font-medium text-slate-500">Region<select name="region" class="setup-input mt-1"><option value="">All regions</option>@foreach($filterOptions['regions'] as $region)<option value="{{ $region }}" @selected($filters['region'] === $region)>{{ $region }}</option>@endforeach</select></label>
                <label class="text-xs font-medium text-slate-500">Status / access<select name="state" class="setup-input mt-1"><option value="">All states</option>@foreach($filterOptions['states'] as $state)<option value="{{ $state }}" @selected($filters['state'] === $state)>{{ ucfirst($state) }}</option>@endforeach</select></label>
                <label class="text-xs font-medium text-slate-500">Exposure<select name="exposure" class="setup-input mt-1"><option value="">Any exposure</option><option value="flagged" @selected($filters['exposure'] === 'flagged')>Flagged only</option><option value="public" @selected($filters['exposure'] === 'public')>Public resources</option><option value="private" @selected($filters['exposure'] === 'private')>Private resources</option></select></label>
            </div>
            <div class="mt-4 flex justify-end"><button class="rounded-xl bg-emerald-500 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-400">Apply filters</button></div>
        </form>
        <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8">
            <section class="metric-card"><p>AWS accounts</p><strong>{{ $summary['accounts'] }}</strong><span>Connected roles</span></section>
            <section class="metric-card"><p>All resources</p><strong>{{ $summary['resources'] }}</strong><span>Discovered inventory</span></section>
            <section class="metric-card"><p>EC2 instances</p><strong>{{ $summary['instances'] }}</strong><span>{{ $summary['running'] }} Running</span></section>
            <section class="metric-card"><p>RDS databases</p><strong>{{ $summary['databases'] }}</strong><span>DB instances</span></section>
            <section class="metric-card"><p>S3 buckets</p><strong>{{ $summary['buckets'] }}</strong><span>{{ $summary['public_buckets'] }} public</span></section>
            <section class="metric-card"><p>Stopped</p><strong>{{ $summary['stopped'] }}</strong><span>EC2 instances</span></section>
            <section class="metric-card"><p>Regions</p><strong>{{ $summary['regions'] }}</strong><span>With resources</span></section>
            <section class="metric-card"><p>Findings</p><strong>{{ $summary['findings'] }}</strong><span>Active recommendations</span></section>
        </div>

        @if(in_array($filters['service'], ['', 'ec2'], true))<section class="chart-panel mt-5 overflow-hidden">
            <div class="panel-heading"><h2>EC2 instances</h2><a href="{{ route('cloud.instances') }}" class="text-emerald-600">Open historical monitoring →</a></div>
            <div class="overflow-x-auto"><table class="min-w-[1180px] w-full text-left text-sm"><thead class="text-xs text-slate-500"><tr><th class="py-2">Instance</th><th>Region / type</th><th>Address</th><th>CPU</th><th>Network in / out</th><th>Security Groups</th><th>Exposure</th><th>Status</th><th></th></tr></thead><tbody class="divide-y divide-slate-200 dark:divide-slate-800">
            @forelse($resources->where('service','ec2')->where('type','instance') as $resource)
                @php
                    $stats = $listingMetrics[$resource->id] ?? [];
                    $groups = collect($resource->metadata['security_groups'] ?? [])->map(fn($group) => $securityGroupNames[$group['id'] ?? ''] ?? ($group['name'] ?? $group['id'] ?? null))->filter();
                    $exposure = $exposureFindings[$resource->resource_id] ?? null;
                @endphp
                <tr><td class="py-3"><p class="font-medium">{{ $resource->name }}</p><p class="font-mono text-xs text-slate-500">{{ $resource->resource_id }}</p></td><td>{{ $resource->region }}<p class="text-xs text-slate-500">{{ $resource->instance_type }} · {{ $resource->availability_zone }}</p></td><td><p class="font-mono text-xs">{{ $resource->metadata['private_ip'] ?? '—' }}</p><p class="font-mono text-xs text-slate-500">{{ $resource->metadata['public_ip'] ?? 'No public IP' }}</p></td><td class="font-medium">{{ isset($stats['CPUUtilization']) ? number_format($stats['CPUUtilization']['value'], 1).'%' : '—' }}</td><td class="text-xs">{{ isset($stats['NetworkIn']) ? number_format($stats['NetworkIn']['value'] / 1048576, 1).' MB' : '—' }} / {{ isset($stats['NetworkOut']) ? number_format($stats['NetworkOut']['value'] / 1048576, 1).' MB' : '—' }}</td><td><p class="max-w-56 truncate text-xs" title="{{ $groups->implode(', ') }}">{{ $groups->isNotEmpty() ? $groups->implode(', ') : '—' }}</p></td><td>@if($exposure)<span class="inline-flex rounded-full bg-red-100 px-2 py-1 text-xs text-red-700 dark:bg-red-950 dark:text-red-300">Public SSH</span>@elseif($resource->metadata['public_ip'] ?? null)<span class="inline-flex rounded-full bg-amber-100 px-2 py-1 text-xs text-amber-700 dark:bg-amber-950 dark:text-amber-300">Public IP</span>@else<span class="text-xs text-slate-500">Private</span>@endif</td><td><span class="inline-flex rounded-full px-2 py-1 text-xs {{ $resource->state === 'running' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">{{ $resource->state ?? 'unknown' }}</span></td><td><a href="{{ route('cloud.instances', ['resource_id' => $resource->id]) }}" class="font-medium text-emerald-600">Details</a></td></tr>
            @empty<tr><td colspan="9" class="py-8 text-center text-slate-500">No EC2 instances have been synchronized yet.</td></tr>@endforelse
            </tbody></table></div>
        </section>@endif

        @if(in_array($filters['service'], ['', 'rds'], true))<section class="chart-panel mt-5 overflow-hidden">
            <div class="panel-heading"><h2>RDS databases</h2><a href="{{ route('cloud.databases') }}" class="text-emerald-600">Open historical monitoring →</a></div>
            <div class="overflow-x-auto"><table class="min-w-[1120px] w-full text-left text-sm"><thead class="text-xs text-slate-500"><tr><th class="py-2">Database</th><th>Engine / class</th><th>CPU</th><th>Connections</th><th>Free storage</th><th>Security Groups</th><th>Exposure</th><th>Status</th><th></th></tr></thead><tbody class="divide-y divide-slate-200 dark:divide-slate-800">
            @forelse($resources->where('service','rds')->where('type','db-instance') as $resource)
                @php
                    $stats = $listingMetrics[$resource->id] ?? [];
                    $groups = collect($resource->metadata['security_groups'] ?? [])->map(fn($group) => $securityGroupNames[$group['id'] ?? ''] ?? ($group['id'] ?? null))->filter();
                    $exposure = $exposureFindings[$resource->resource_id] ?? null;
                @endphp
                <tr><td class="py-3"><p class="font-medium">{{ $resource->name }}</p><p class="text-xs text-slate-500">{{ $resource->region }} · {{ $resource->availability_zone }}</p></td><td>{{ $resource->metadata['engine'] ?? '—' }} {{ $resource->metadata['engine_version'] ?? '' }}<p class="text-xs text-slate-500">{{ $resource->instance_type }}</p></td><td class="font-medium">{{ isset($stats['CPUUtilization']) ? number_format($stats['CPUUtilization']['value'], 1).'%' : '—' }}</td><td>{{ isset($stats['DatabaseConnections']) ? number_format($stats['DatabaseConnections']['value']) : '—' }}</td><td>{{ isset($stats['FreeStorageSpace']) ? number_format($stats['FreeStorageSpace']['value'] / 1073741824, 1).' GiB' : '—' }}<p class="text-xs text-slate-500">of {{ $resource->metadata['allocated_storage_gib'] ?? '—' }} GiB</p></td><td><p class="max-w-56 truncate text-xs" title="{{ $groups->implode(', ') }}">{{ $groups->isNotEmpty() ? $groups->implode(', ') : '—' }}</p></td><td>@if($exposure)<span class="inline-flex rounded-full bg-red-100 px-2 py-1 text-xs text-red-700 dark:bg-red-950 dark:text-red-300">Public DB port</span>@elseif($resource->metadata['publicly_accessible'] ?? false)<span class="inline-flex rounded-full bg-amber-100 px-2 py-1 text-xs text-amber-700 dark:bg-amber-950 dark:text-amber-300">Public endpoint</span>@else<span class="text-xs text-slate-500">Private</span>@endif</td><td><span class="inline-flex rounded-full px-2 py-1 text-xs {{ $resource->state === 'available' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' }}">{{ $resource->state ?? 'unknown' }}</span></td><td><a href="{{ route('cloud.databases', ['resource_id' => $resource->id]) }}" class="font-medium text-emerald-600">Details</a></td></tr>
            @empty<tr><td colspan="9" class="py-8 text-center text-slate-500">No RDS databases have been synchronized yet.</td></tr>@endforelse
            </tbody></table></div>
        </section>@endif

        @if(in_array($filters['service'], ['', 's3'], true))<section class="chart-panel mt-5 overflow-hidden">
            <div class="panel-heading"><div><h2>S3 buckets</h2><p class="mt-1 text-xs text-slate-500">Current object keys and their combined size; versions and delete markers are excluded.</p></div><span>Public-access posture</span></div>
            <div class="overflow-x-auto"><table class="min-w-[1050px] w-full text-left text-sm"><thead class="text-xs text-slate-500"><tr><th class="py-2 pr-5">Bucket</th><th class="pr-5">Account</th><th class="pr-5">Region</th><th class="pr-5 text-right">Objects</th><th class="pr-5 text-right">Total size</th><th class="pr-5">Block Public Access</th><th class="pr-5">Effective access</th><th>Last checked</th></tr></thead><tbody class="divide-y divide-slate-200 dark:divide-slate-800">
            @forelse($resources->where('service','s3')->where('type','bucket') as $resource)
                @php
                    $sizeBytes = $resource->metadata['total_size_bytes'] ?? null;
                    $sizeLabel = 'Unavailable';
                    if (is_numeric($sizeBytes)) {
                        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];
                        $unitIndex = 0;
                        $sizeValue = (float) $sizeBytes;
                        while ($sizeValue >= 1024 && $unitIndex < count($units) - 1) {
                            $sizeValue /= 1024;
                            $unitIndex++;
                        }
                        $sizeLabel = number_format($sizeValue, $unitIndex === 0 ? 0 : 1).' '.$units[$unitIndex];
                    }
                    $blockCount = (int) ($resource->metadata['public_access_block_enabled_count'] ?? collect($resource->metadata['effective_public_access_block'] ?? [])->filter()->count());
                    $blockKnown = (bool) ($resource->metadata['public_access_block_inspection_complete'] ?? false);
                    $blockLabel = $blockKnown ? $blockCount.'/4 enabled' : ($blockCount > 0 ? 'At least '.$blockCount.'/4' : 'Unknown');
                    $accessStatus = $resource->metadata['access_status'] ?? $resource->state;
                @endphp
                <tr><td class="py-3 pr-5"><p class="font-medium">{{ $resource->name }}</p><p class="font-mono text-xs text-slate-500">{{ $resource->arn }}</p></td><td class="pr-5">{{ $resource->connection->name }}</td><td class="pr-5">{{ $resource->region }}</td><td class="pr-5 text-right font-medium">{{ is_numeric($resource->metadata['object_count'] ?? null) ? number_format($resource->metadata['object_count']) : 'Unavailable' }}</td><td class="pr-5 text-right font-medium">{{ $sizeLabel }}</td><td class="pr-5"><span class="inline-flex rounded-full px-2 py-1 text-xs {{ $blockCount === 4 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : ($blockKnown ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300') }}">{{ $blockLabel }}</span><p class="mt-1 text-xs text-slate-500">Bucket + account controls</p></td><td class="pr-5">@if($accessStatus === 'public')<span class="inline-flex rounded-full bg-red-100 px-2 py-1 text-xs text-red-700 dark:bg-red-950 dark:text-red-300">Public</span>@elseif($accessStatus === 'private')<span class="inline-flex rounded-full bg-emerald-100 px-2 py-1 text-xs text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">Private</span>@else<span class="inline-flex rounded-full bg-amber-100 px-2 py-1 text-xs text-amber-700 dark:bg-amber-950 dark:text-amber-300">Unknown</span><p class="mt-1 text-xs text-slate-500">Permission required</p>@endif</td><td class="text-xs text-slate-500">{{ $resource->last_seen_at?->diffForHumans() ?? '—' }}</td></tr>
            @empty<tr><td colspan="8" class="py-8 text-center text-slate-500">No S3 buckets have been synchronized yet.</td></tr>@endforelse
            </tbody></table></div>
        </section>@endif
    @endif
</main></body></html>
