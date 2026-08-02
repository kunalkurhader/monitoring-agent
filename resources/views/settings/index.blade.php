<!doctype html>
<html lang="en" class="scheme-light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>Settings · {{ config('app.name', 'Monitoring Agent') }}</title><script>if(localStorage.getItem('monitoring-agent-theme')==='dark'){document.documentElement.classList.add('dark')}</script>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100"><x-app-header active="settings" />
<main id="settings-page" class="mx-auto max-w-screen-2xl px-4 py-6 sm:px-6"><div><p class="text-sm font-medium text-emerald-600">Administration</p><h1 class="text-2xl font-semibold">Settings</h1><p class="mt-1 text-sm text-slate-500">Configure branding, monitoring agents, notifications, and data lifecycle.</p></div>
@if(session('status'))<div class="mt-4 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</div>@endif
<nav class="mt-6 flex gap-2 overflow-x-auto border-b border-slate-200 pb-3 dark:border-slate-800" role="tablist" aria-label="Settings sections"><button type="button" role="tab" data-settings-tab="branding" class="rounded-lg px-4 py-2 text-sm font-medium shadow-sm">Branding</button><button type="button" role="tab" data-settings-tab="uptime-monitoring" class="rounded-lg px-4 py-2 text-sm font-medium shadow-sm">Uptime Monitors</button><button type="button" role="tab" data-settings-tab="server-agent" class="rounded-lg px-4 py-2 text-sm font-medium shadow-sm">Server Agent</button><button type="button" role="tab" data-settings-tab="cloud" class="rounded-lg px-4 py-2 text-sm font-medium shadow-sm">Cloud</button><button type="button" role="tab" data-settings-tab="browser-agent" class="rounded-lg px-4 py-2 text-sm font-medium shadow-sm">Browser Agent</button><button type="button" role="tab" data-settings-tab="data-retention" class="rounded-lg px-4 py-2 text-sm font-medium shadow-sm">Data Retention</button><button type="button" role="tab" data-settings-tab="email-delivery" class="rounded-lg px-4 py-2 text-sm font-medium shadow-sm">Email Settings</button><button type="button" role="tab" data-settings-tab="danger-zone" class="rounded-lg px-4 py-2 text-sm font-medium text-red-600 shadow-sm dark:text-red-300">Danger Zone</button></nav>

<section id="branding" data-settings-panel="branding" role="tabpanel" class="pt-7">
    <div class="mb-5"><h2 class="text-xl font-semibold">Branding</h2><p class="mt-1 text-sm text-slate-500">Give this installation your organization’s identity. Changes apply across navigation, page titles, sign-in, and email invitations.</p></div>
    <form method="POST" action="{{ route('settings.branding.update') }}" enctype="multipart/form-data" class="grid overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:grid-cols-[0.8fr_1.2fr]">@csrf @method('PATCH')
        <div class="flex min-h-64 items-center justify-center bg-slate-100 p-8 dark:bg-slate-950">
            <div class="text-center">
                @if(config('app.branding_logo'))<img src="{{ config('app.branding_logo') }}" alt="Current logo" class="mx-auto max-h-24 max-w-56 object-contain">@else<span class="mx-auto grid size-20 place-items-center rounded-3xl bg-emerald-400 text-3xl font-bold text-slate-950 shadow-lg shadow-emerald-500/20">{{ Str::upper(Str::substr(config('app.name', 'Monitoring Agent'), 0, 1)) }}</span>@endif
                <p class="mt-5 text-xl font-semibold">{{ config('app.name', 'Monitoring Agent') }}</p><p class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-500">Live preview</p>
            </div>
        </div>
        <div class="p-6 sm:p-8">
            <label class="setup-label" for="site-name">Site name<input id="site-name" name="site_name" value="{{ old('site_name', $brandingSetting?->site_name ?? config('app.name', 'Monitoring Agent')) }}" required maxlength="80" class="setup-input mt-2" placeholder="Your monitoring platform"></label>
            <label class="setup-label mt-5 block" for="brand-logo">Logo <span class="font-normal text-slate-400">(optional)</span><input id="brand-logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" class="mt-2 block w-full rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:font-medium file:text-white dark:border-slate-700 dark:bg-slate-950 dark:file:bg-white dark:file:text-slate-900"><span class="mt-2 block text-xs font-normal text-slate-500">PNG, JPEG, or WebP up to 2 MB. A wide or square logo with a transparent background works best.</span></label>
            @if($brandingSetting?->logo_path)<label class="mt-4 flex items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm dark:border-slate-700"><input type="checkbox" name="remove_logo" value="1" class="size-4 rounded">Remove the current logo and use the letter icon</label>@endif
            @if($errors->branding->any())<div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">{{ $errors->branding->first() }}</div>@endif
            <button class="mt-6 rounded-xl bg-emerald-500 px-5 py-3 font-semibold text-white hover:bg-emerald-400">Save branding</button>
        </div>
    </form>
</section>

<section id="uptime-monitoring" data-settings-panel="uptime-monitoring" role="tabpanel" class="hidden pt-7">
    <div class="mb-5 flex flex-col justify-between gap-3 sm:flex-row sm:items-end"><div><h2 class="text-xl font-semibold">Uptime Monitors</h2><p class="mt-1 text-sm text-slate-500">Register websites for one-minute HTTP and SSL certificate checks.</p></div><a href="{{ route('website-monitors.index') }}" class="text-sm font-semibold text-emerald-600">Open uptime dashboard →</a></div>
    <div class="grid gap-6 xl:grid-cols-[0.8fr_1.2fr]">
        <form method="POST" action="{{ route('website-monitors.store') }}" class="chart-panel">@csrf
            <div class="panel-heading"><h3>Add website</h3><span>HTTP 200 expected</span></div>
            <div class="space-y-4"><label class="setup-label">Name<input name="name" value="{{ old('name') }}" required maxlength="255" class="setup-input mt-2" placeholder="Customer portal"></label><label class="setup-label">Website URL<input name="url" value="{{ old('url') }}" required type="url" class="setup-input mt-2" placeholder="https://example.com"></label><label class="setup-label">Alert recipient<input name="alert_email" value="{{ old('alert_email', auth()->user()->email) }}" required type="email" class="setup-input mt-2" placeholder="ops@example.com"></label><label class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm dark:border-slate-700"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="size-4">Start monitoring immediately</label></div>
            @if($errors->uptimeMonitor->any())<div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">{{ $errors->uptimeMonitor->first() }}</div>@endif
            <button class="mt-5 rounded-xl bg-emerald-500 px-5 py-3 font-semibold text-white hover:bg-emerald-400">Add uptime monitor</button>
        </form>
        <div class="chart-panel"><div class="panel-heading"><h3>Configured websites</h3><span>{{ $websiteMonitors->count() }} total</span></div><div class="space-y-3">@forelse($websiteMonitors as $monitor)<div class="flex flex-col justify-between gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700 sm:flex-row sm:items-center"><div class="min-w-0"><div class="flex items-center gap-2"><span class="size-2 rounded-full {{ !$monitor->is_active ? 'bg-slate-300' : ($monitor->is_up === true ? 'bg-emerald-500' : ($monitor->is_up === false ? 'bg-red-500' : 'bg-amber-400')) }}"></span><p class="truncate font-medium">{{ $monitor->name }}</p></div><p class="mt-1 truncate text-xs text-slate-500">{{ $monitor->url }} · {{ $monitor->alert_email }}</p></div><div class="flex gap-2"><a href="{{ route('website-monitors.edit', $monitor) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold dark:border-slate-700">Edit</a><form method="POST" action="{{ route('website-monitors.destroy', $monitor) }}" onsubmit="return confirm('Delete this monitor and its alert history?')">@csrf @method('DELETE')<button class="rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-600 dark:border-red-900 dark:text-red-300">Delete</button></form></div></div>@empty<p class="text-sm text-slate-500">No websites configured yet.</p>@endforelse</div></div>
    </div>
</section>

<section id="server-agent" data-settings-panel="server-agent" role="tabpanel" class="hidden pt-7"><div class="mb-4"><h2 class="text-xl font-semibold">Install Server Agent</h2><p class="mt-1 text-sm text-slate-500">Install the Java agent on a Linux server to collect CPU, RAM, disk, and process telemetry.</p></div>@include('dashboard.install-agent')</section>

<section id="cloud" data-settings-panel="cloud" role="tabpanel" class="hidden pt-7">
    <div class="mb-5"><p class="text-sm font-medium text-emerald-600">AWS integration</p><h2 class="text-xl font-semibold">Connect an AWS account</h2><p class="mt-1 max-w-3xl text-sm text-slate-500">Create a cross-account read-only role. Monitoring Agent uses STS temporary credentials; no AWS access key or secret is stored here.</p></div>
    <div class="grid gap-5 xl:grid-cols-[1.15fr_.85fr]">
        <div class="chart-panel">
            <div class="panel-heading"><h3>Role creation steps</h3><span>AWS IAM Console</span></div>
            <ol class="space-y-5 text-sm">
                <li class="flex gap-3"><span class="grid size-7 shrink-0 place-items-center rounded-full bg-emerald-100 font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">1</span><div><p class="font-medium">Create the monitoring permissions policy</p><p class="mt-1 text-slate-500">In the account to monitor, open IAM → Policies → Create policy → JSON. This read-only policy covers EC2, EBS, VPC, security groups, Elastic IPs, RDS, CloudWatch, and Performance Insights.</p><textarea readonly rows="11" class="setup-input mt-3 font-mono text-xs">{
  "Version": "2012-10-17",
  "Statement": [{
    "Effect": "Allow",
    "Action": ["ec2:Describe*", "rds:Describe*",
      "cloudwatch:GetMetricData",
      "cloudwatch:GetMetricStatistics", "cloudwatch:ListMetrics",
      "cloudwatch:DescribeAlarms", "tag:GetResources",
      "pi:DescribeDimensionKeys", "pi:GetResourceMetrics",
      "pi:GetDimensionKeyDetails", "pi:ListAvailableResourceMetrics"],
    "Resource": "*"
  }]
}</textarea><p class="mt-2 text-xs text-slate-500">Name it <span class="font-mono">MonitoringAgentReadOnly</span>.</p></div></li>
                <li class="flex gap-3"><span class="grid size-7 shrink-0 place-items-center rounded-full bg-emerald-100 font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">2</span><div class="min-w-0 flex-1"><p class="font-medium">Create the cross-account role</p><p class="mt-1 text-slate-500">Open IAM → Roles → Create role → Custom trust policy. Replace the principal placeholder if this installation has not configured <span class="font-mono">AWS_MONITORING_PRINCIPAL_ARN</span>.</p><textarea readonly rows="13" class="setup-input mt-3 font-mono text-xs">{
  "Version": "2012-10-17",
  "Statement": [{
    "Effect": "Allow",
    "Principal": {
      "AWS": "{{ config('services.aws_monitoring.principal_arn') ?: 'SET_AWS_MONITORING_PRINCIPAL_ARN' }}"
    },
    "Action": "sts:AssumeRole",
    "Condition": {"StringEquals": {
      "sts:ExternalId": "{{ $awsExternalId }}"
    }}
  }]
}</textarea></div></li>
                <li class="flex gap-3"><span class="grid size-7 shrink-0 place-items-center rounded-full bg-emerald-100 font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">3</span><div><p class="font-medium">Attach the policy and copy the role ARN</p><p class="mt-1 text-slate-500">Attach <span class="font-mono">MonitoringAgentReadOnly</span>, name the role <span class="font-mono">MonitoringAgentRole</span>, create it, then copy its ARN into the connection form.</p></div></li>
                <li class="flex gap-3"><span class="grid size-7 shrink-0 place-items-center rounded-full bg-emerald-100 font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">4</span><div class="min-w-0 flex-1"><p class="font-medium">Allow the Monitoring Agent identity to assume the role</p><p class="mt-1 text-slate-500">The IAM user or role configured as <span class="font-mono">AWS_MONITORING_PRINCIPAL_ARN</span> also needs an identity policy permitting <span class="font-mono">sts:AssumeRole</span> on the new role.</p><textarea readonly rows="10" class="setup-input mt-3 font-mono text-xs">{
  "Version": "2012-10-17",
  "Statement": [{
    "Effect": "Allow",
    "Action": "sts:AssumeRole",
    "Resource": "arn:aws:iam::TARGET_ACCOUNT_ID:role/MonitoringAgentRole"
  }]
}</textarea><p class="mt-2 text-xs text-slate-500">Configure the source identity through the standard AWS credential chain on this server; do not paste access keys into this form.</p></div></li>
                <li class="flex gap-3"><span class="grid size-7 shrink-0 place-items-center rounded-full bg-emerald-100 font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">5</span><div class="min-w-0 flex-1"><p class="font-medium">Enable OS memory and filesystem insights (optional)</p><p class="mt-1 text-slate-500">On each EC2 instance, install the Amazon CloudWatch Agent and attach <span class="font-mono">CloudWatchAgentServerPolicy</span> to its instance profile. Use this configuration to publish memory and per-filesystem total, used, and free space:</p><textarea readonly rows="18" class="setup-input mt-3 font-mono text-xs">{
  "agent": {"metrics_collection_interval": 60},
  "metrics": {
    "namespace": "CWAgent",
    "append_dimensions": {
      "InstanceId": "${aws:InstanceId}",
      "InstanceType": "${aws:InstanceType}",
      "AutoScalingGroupName": "${aws:AutoScalingGroupName}"
    },
    "metrics_collected": {
      "mem": {"measurement": ["mem_used_percent"]},
      "disk": {
        "resources": ["*"],
        "measurement": ["disk_total", "disk_used", "disk_free",
          "disk_used_percent", "disk_inodes_free"]
      }
    }
  }
}</textarea><p class="mt-2 text-xs text-slate-500">Without the CloudWatch Agent, EC2 monitoring still shows attached EBS capacity and I/O, but AWS does not expose filesystem free space from inside the guest OS.</p></div></li>
                <li class="flex gap-3"><span class="grid size-7 shrink-0 place-items-center rounded-full bg-emerald-100 font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">6</span><div><p class="font-medium">Enable RDS Performance Insights (optional)</p><p class="mt-1 text-slate-500">Enable Performance Insights on each supported RDS database to populate SQL load, calls, average latency, and query execution insights. Standard RDS health metrics remain available when it is disabled; exact query dimensions vary by engine.</p></div></li>
                <li class="flex gap-3"><span class="grid size-7 shrink-0 place-items-center rounded-full bg-emerald-100 font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">7</span><div><p class="font-medium">Save, schedule, and verify</p><p class="mt-1 text-slate-500">Enter the role ARN, regions, and fetch interval, then save. Ensure Laravel's scheduler runs every minute; the connection interval controls when AWS is actually queried. For an immediate first import, run <span class="font-mono">php artisan cloud:sync --force</span>.</p><p class="mt-2 text-xs text-slate-500">Security group and Elastic IP findings are recommendations only. Monitoring Agent never changes AWS resources.</p></div></li>
            </ol>
        </div>
        <form method="POST" action="{{ route('settings.cloud.update') }}" class="chart-panel self-start">@csrf @method('PATCH')
            <div class="panel-heading"><h3>Cloud connection</h3><span class="{{ $awsConnection?->status === 'connected' ? 'text-emerald-600' : 'text-amber-500' }}">{{ ucfirst($awsConnection?->status ?? 'not configured') }}</span></div>
            <div class="space-y-4">
                <label class="setup-label">Connection name<input name="name" value="{{ old('name', $awsConnection?->name ?? 'AWS Production') }}" required maxlength="255" class="setup-input mt-2" placeholder="AWS Production"></label>
                <label class="setup-label">Role ARN<input name="role_arn" value="{{ old('role_arn', $awsConnection?->role_arn) }}" required class="setup-input mt-2 font-mono text-xs" placeholder="arn:aws:iam::123456789012:role/MonitoringAgentRole"></label>
                <label class="setup-label">External ID<input name="external_id" value="{{ old('external_id', $awsExternalId) }}" required readonly class="setup-input mt-2 font-mono text-xs"></label>
                <label class="setup-label">AWS regions <span class="font-normal text-slate-400">(comma separated)</span><input name="regions" value="{{ old('regions', implode(', ', $awsConnection?->regions ?? ['ap-south-1'])) }}" class="setup-input mt-2" placeholder="ap-south-1, us-east-1"><span class="mt-1 block text-xs font-normal text-slate-500">Leave empty to discover all enabled regions.</span></label>
                <label class="setup-label">Fetch interval<select name="poll_interval_minutes" class="setup-input mt-2"><option value="1" @selected(old('poll_interval_minutes', $awsConnection?->poll_interval_minutes ?? 5) == 1)>Every minute</option><option value="5" @selected(old('poll_interval_minutes', $awsConnection?->poll_interval_minutes ?? 5) == 5)>Every 5 minutes (default)</option><option value="10" @selected(old('poll_interval_minutes', $awsConnection?->poll_interval_minutes ?? 5) == 10)>Every 10 minutes</option><option value="15" @selected(old('poll_interval_minutes', $awsConnection?->poll_interval_minutes ?? 5) == 15)>Every 15 minutes</option><option value="30" @selected(old('poll_interval_minutes', $awsConnection?->poll_interval_minutes ?? 5) == 30)>Every 30 minutes</option><option value="60" @selected(old('poll_interval_minutes', $awsConnection?->poll_interval_minutes ?? 5) == 60)>Every hour</option></select></label>
                <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm dark:border-slate-700"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $awsConnection?->is_active ?? true)) class="size-4 rounded">Fetch AWS inventory and metrics automatically</label>
            </div>
            @if($awsConnection?->last_synced_at)<p class="mt-4 text-xs text-slate-500">Last synchronized {{ $awsConnection->last_synced_at->diffForHumans() }}.</p>@endif
            @if($awsConnection?->last_error)<p class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">{{ $awsConnection->last_error }}</p>@endif
            @if($errors->cloud->any())<div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">{{ $errors->cloud->first() }}</div>@endif
            <button class="mt-5 rounded-xl bg-emerald-500 px-5 py-3 font-semibold text-white hover:bg-emerald-400">Save AWS connection</button>
        </form>
    </div>
</section>

<section id="browser-agent" data-settings-panel="browser-agent" role="tabpanel" class="hidden pt-7"><div class="mb-4"><h2 class="text-xl font-semibold">Install Browser Agent</h2><p class="mt-1 text-sm text-slate-500">Add the JavaScript agent to a website to collect page loads, AJAX/HTMX requests, Web Vitals, and frontend errors.</p></div>
<div class="grid gap-5 xl:grid-cols-2"><div class="chart-panel"><div class="panel-heading"><h3>Register website</h3><span>Exact origin validation</span></div><form method="POST" action="{{ route('browser-monitoring.store') }}" class="space-y-4">@csrf<label class="setup-label">Website name<input name="name" value="{{ old('name') }}" required maxlength="255" class="setup-input mt-2" placeholder="Customer portal"></label><label class="setup-label">Website URL<input name="site_url" value="{{ old('site_url') }}" required type="url" class="setup-input mt-2" placeholder="https://app.example.com"></label><button class="rounded-xl bg-emerald-500 px-5 py-3 font-medium text-white">Generate Browser Agent key</button></form></div>
<div class="chart-panel"><div class="panel-heading"><h3>Browser Agent snippets</h3><a href="{{ route('browser-monitoring.index') }}" class="text-xs text-emerald-600">Open Browser Monitoring</a></div><div class="max-h-96 space-y-4 overflow-auto">@forelse($browserProjects as $project)<article class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"><div class="flex justify-between gap-3"><div><p class="font-medium">{{ $project->name }}</p><p class="font-mono text-xs text-slate-500">{{ $project->allowed_origin }}</p></div><span class="text-xs {{ $project->is_active ? 'text-emerald-600' : 'text-slate-400' }}">{{ $project->is_active ? 'Active' : 'Disabled' }}</span></div><textarea readonly rows="4" class="setup-input mt-3 font-mono text-xs">&lt;script src="{{ url('/browser-monitor.js') }}" data-key="{{ $project->public_key }}" data-endpoint="{{ url('/api/v1/browser/events') }}" defer&gt;&lt;/script&gt;</textarea></article>@empty<p class="text-sm text-slate-500">Register a website to generate its installation snippet.</p>@endforelse</div></div></div></section>

<section id="data-retention" data-settings-panel="data-retention" role="tabpanel" class="hidden py-10">
    <div class="mb-5"><h2 class="text-xl font-semibold">Data Retention</h2><p class="mt-1 max-w-3xl text-sm text-slate-500">Control how long historical monitoring records remain in the database. Cleanup runs automatically every hour.</p></div>
    <div class="grid overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:grid-cols-[0.8fr_1.2fr]">
        <div class="bg-slate-100 p-6 dark:bg-slate-950 sm:p-8"><p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-600">Current policy</p><p class="mt-3 text-5xl font-semibold">{{ $retentionSetting->retention_days }}<span class="ml-2 text-xl font-normal text-slate-500">days</span></p><p class="mt-4 text-sm leading-6 text-slate-600 dark:text-slate-300">Records older than this window are permanently removed. The most recent cleanup was {{ $retentionSetting->last_pruned_at?->diffForHumans() ?? 'not run yet' }}.</p></div>
        <form method="POST" action="{{ route('settings.retention.update') }}" class="p-6 sm:p-8">@csrf @method('PATCH')
            <label class="setup-label" for="retention-days">Keep monitoring data for<input id="retention-days" name="retention_days" value="{{ old('retention_days', $retentionSetting->retention_days) }}" required type="number" min="1" max="3650" class="setup-input mt-2"><span class="mt-2 block text-xs font-normal text-slate-500">Enter a value from 1 to 3,650 days. The default is 30 days.</span></label>
            <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200"><strong>What is removed:</strong> server telemetry, process and disk samples, browser events, synchronized log content, and historical uptime alert records older than the selected period. Users, agents, monitor definitions, API tokens, and settings are preserved.</div>
            @if($errors->retention->any())<div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">{{ $errors->retention->first() }}</div>@endif
            <button class="mt-6 rounded-xl bg-emerald-500 px-5 py-3 font-semibold text-white hover:bg-emerald-400">Save retention policy</button>
        </form>
    </div>
</section>

<section id="email-delivery" class="scroll-mt-20 py-10"><div class="mb-4"><h2 class="text-xl font-semibold">Email Delivery</h2><p class="mt-1 text-sm text-slate-500">SMTP is used for team invitations and future notification emails. The password is encrypted in the database.</p></div><form method="POST" action="{{ route('settings.mail.update') }}" class="chart-panel">@csrf @method('PATCH')<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"><label class="setup-label">SMTP host<input name="host" value="{{ old('host',$mailSetting?->host) }}" required class="setup-input mt-2" placeholder="smtp.example.com"></label><label class="setup-label">Port<input name="port" value="{{ old('port',$mailSetting?->port ?? 587) }}" required type="number" min="1" max="65535" class="setup-input mt-2"></label><label class="setup-label">Connection security<select name="scheme" class="setup-input mt-2"><option value="">Automatic TLS</option><option value="smtp" @selected(old('scheme',$mailSetting?->scheme)==='smtp')>SMTP without implicit TLS</option><option value="smtps" @selected(old('scheme',$mailSetting?->scheme)==='smtps')>SMTPS (implicit TLS)</option></select></label><label class="setup-label">Username<input name="username" value="{{ old('username',$mailSetting?->username) }}" class="setup-input mt-2" autocomplete="off"></label><label class="setup-label">Password<input name="password" type="password" class="setup-input mt-2" autocomplete="new-password" placeholder="{{ $mailSetting && filled($mailSetting->getRawOriginal('password')) ? 'Saved — leave blank to keep' : '' }}"></label><label class="setup-label">From email<input name="from_address" value="{{ old('from_address',$mailSetting?->from_address) }}" required type="email" class="setup-input mt-2"></label><label class="setup-label">From name<input name="from_name" value="{{ old('from_name',$mailSetting?->from_name ?? config('app.name', 'Monitoring Agent')) }}" required class="setup-input mt-2"></label><label class="flex items-center gap-3 self-end rounded-xl border border-slate-200 p-3 dark:border-slate-700"><input type="hidden" name="is_enabled" value="0"><input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled',$mailSetting?->is_enabled ?? true)) class="size-4">Enable SMTP delivery</label></div><div class="mt-5"><button class="rounded-xl bg-emerald-500 px-5 py-3 font-medium text-white">Save email settings</button></div>@if($errors->any())<div class="mt-4 text-sm text-red-500">{{ $errors->first() }}</div>@endif</form></section>

<section id="danger-zone" data-settings-panel="danger-zone" role="tabpanel" class="hidden py-10">
    <div class="overflow-hidden rounded-3xl border border-red-200 bg-white shadow-sm dark:border-red-950 dark:bg-slate-900">
        <div class="border-b border-red-100 bg-gradient-to-r from-red-50 to-orange-50 px-6 py-6 dark:border-red-950 dark:from-red-950/40 dark:to-orange-950/20 sm:px-8">
            <div class="flex items-start gap-4">
                <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-red-600 text-xl font-bold text-white shadow-lg shadow-red-600/20" aria-hidden="true">!</span>
                <div><p class="text-xs font-bold uppercase tracking-[0.2em] text-red-600 dark:text-red-300">Danger zone</p><h2 class="mt-1 text-2xl font-semibold text-slate-950 dark:text-white">Factory reset {{ config('app.name', 'Monitoring Agent') }}</h2><p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">Return this installation to its first-run state. This is intended for transferring, rebuilding, or permanently retiring an installation.</p></div>
            </div>
        </div>

        <div class="grid gap-8 p-6 sm:p-8 lg:grid-cols-[0.85fr_1.15fr]">
            <div>
                <h3 class="font-semibold text-slate-900 dark:text-white">What will be erased</h3>
                <ul class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300">
                    <li class="flex gap-3"><span class="text-red-500" aria-hidden="true">×</span><span>Users, invitations, email settings, and login sessions</span></li>
                    <li class="flex gap-3"><span class="text-red-500" aria-hidden="true">×</span><span>Servers, agents, API tokens, telemetry, and collected logs</span></li>
                    <li class="flex gap-3"><span class="text-red-500" aria-hidden="true">×</span><span>Browser projects, events, queued jobs, and cached data</span></li>
                    <li class="flex gap-3"><span class="text-red-500" aria-hidden="true">×</span><span>Saved database credentials; the setup wizard will reopen</span></li>
                </ul>
                <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200"><strong>Before continuing:</strong> export or back up anything you need. Existing server and browser agents will stop authenticating immediately.</div>
            </div>

            <form method="POST" action="{{ route('settings.factory-reset') }}" data-factory-reset-form class="rounded-2xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-700 dark:bg-slate-950/50 sm:p-6">
                @csrf @method('DELETE')
                <div class="flex gap-3"><span class="grid size-7 shrink-0 place-items-center rounded-full bg-slate-900 text-xs font-bold text-white dark:bg-white dark:text-slate-900">1</span><label class="setup-label grow" for="reset-password">Confirm your identity<input id="reset-password" data-reset-password name="password" type="password" required autocomplete="current-password" class="setup-input mt-2" placeholder="Administrator password"></label></div>
                <div class="my-5 ml-3.5 h-5 border-l border-dashed border-slate-300 dark:border-slate-700"></div>
                <div class="flex gap-3"><span class="grid size-7 shrink-0 place-items-center rounded-full bg-slate-900 text-xs font-bold text-white dark:bg-white dark:text-slate-900">2</span><label class="setup-label grow" for="reset-confirmation">Type <strong>ERASE EVERYTHING</strong><input id="reset-confirmation" data-reset-confirmation name="confirmation" value="{{ old('confirmation') }}" required autocomplete="off" spellcheck="false" class="setup-input mt-2" placeholder="ERASE EVERYTHING"></label></div>

                @if($errors->factoryReset->any())<div class="mt-5 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">{{ $errors->factoryReset->first() }}</div>@endif

                <div class="mt-6 border-t border-slate-200 pt-5 dark:border-slate-700">
                    <p data-reset-ready class="mb-3 text-xs text-slate-500"></p>
                    <button data-reset-submit type="submit" disabled class="w-full rounded-xl bg-red-600 px-5 py-3 font-semibold text-white shadow-sm transition hover:bg-red-500 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-500 disabled:shadow-none dark:disabled:bg-slate-800 dark:disabled:text-slate-500">Erase everything and restart setup</button>
                </div>
            </form>
        </div>
    </div>
</section>
</main></body></html>
