<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AwsConnection;
use App\Models\AwsOptimizationFinding;
use App\Models\AwsResource;
use App\Models\BrowserProject;
use App\Models\WebsiteMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FleetDashboardController extends Controller
{
    public function index(): View
    {
        return view('fleet-dashboard');
    }

    public function data(): JsonResponse
    {
        $monitors = Agent::query()->orderBy('hostname')->get()->map(function (Agent $agent): array {
            $system = DB::table('system_stats')->where('agent_id', $agent->id)->latest('created_at')->first();
            $diskAt = DB::table('disk_stats')->where('agent_id', $agent->id)->max('created_at');
            $disks = $diskAt
                ? DB::table('disk_stats')->where('agent_id', $agent->id)->where('created_at', $diskAt)->get()
                : collect();
            $memory = $system && $system->total_memory > 0
                ? (($system->total_memory - $system->free_memory) / $system->total_memory) * 100
                : null;
            $disk = $disks->max(fn ($item): float => $item->total_bytes > 0 ? ($item->used_bytes / $item->total_bytes) * 100 : 0);
            $secondsSinceSeen = $agent->last_seen_at ? (int) $agent->last_seen_at->diffInSeconds(now()) : null;
            $issues = $this->issues($agent, $system?->cpu_usage, $memory, $disk, $secondsSinceSeen, $system !== null);
            $status = collect($issues)->contains('severity', 'error') ? 'error'
                : (collect($issues)->contains('severity', 'warning') ? 'warning' : 'healthy');

            return [
                'id' => $agent->id,
                'hostname' => $agent->hostname,
                'status' => $status,
                'last_seen_at' => $agent->last_seen_at?->toIso8601String(),
                'cpu' => $system ? round((float) $system->cpu_usage, 1) : null,
                'memory' => $memory === null ? null : round($memory, 1),
                'disk' => $disk === null ? null : round($disk, 1),
                'issues' => $issues,
            ];
        });
        $browserMonitors = BrowserProject::query()->where('is_active', true)->orderBy('name')->get()->map(function (BrowserProject $project): array {
            $events = $project->events()->where('occurred_at', '>=', now()->subDay())->latest('occurred_at')->limit(10000)->get();
            $pageLoads = $events->whereIn('event_type', ['page_load', 'performance']);
            $requests = $events->whereIn('event_type', ['ajax', 'htmx']);
            $javascriptErrors = $events->whereIn('event_type', ['error', 'unhandled_rejection', 'resource_error']);
            $failedRequests = $requests->filter(fn ($event): bool => ($event->metrics['status'] ?? 0) === 0 || ($event->metrics['status'] ?? 0) >= 400);
            $averageLoad = $pageLoads->avg(fn ($event) => $event->metrics['load_time'] ?? null);
            $errorCount = $javascriptErrors->count() + $failedRequests->count();
            $status = $errorCount > 0 ? 'error' : ($averageLoad !== null && $averageLoad >= 2500 ? 'warning' : ($events->isEmpty() ? 'warning' : 'healthy'));

            return [
                'id' => $project->id,
                'name' => $project->name,
                'origin' => $project->allowed_origin,
                'status' => $status,
                'page_loads' => $pageLoads->count(),
                'requests' => $requests->count(),
                'errors' => $errorCount,
                'average_load' => $averageLoad === null ? null : round((float) $averageLoad),
                'last_seen_at' => $events->first()?->occurred_at?->toIso8601String(),
            ];
        });
        $websiteMonitors = WebsiteMonitor::query()->orderByDesc('is_active')->orderBy('name')->get()->map(function (WebsiteMonitor $monitor): array {
            $sslDays = $monitor->ssl_expires_at
                ? (int) now()->startOfDay()->diffInDays($monitor->ssl_expires_at->copy()->startOfDay(), false)
                : null;

            return [
                'id' => $monitor->id,
                'name' => $monitor->name,
                'url' => $monitor->url,
                'status' => ! $monitor->is_active ? 'paused' : ($monitor->is_up === true ? 'healthy' : ($monitor->is_up === false ? 'error' : 'warning')),
                'status_code' => $monitor->last_status_code,
                'response_ms' => $monitor->last_response_ms,
                'ssl_days' => $sslDays,
                'last_checked_at' => $monitor->last_checked_at?->toIso8601String(),
            ];
        });
        $cloudConnections = AwsConnection::query()->get();
        $cloudResources = AwsResource::query()->where('state', '!=', 'stale')
            ->whereIn('type', ['instance', 'db-instance', 'bucket'])->get();
        $cloudFindings = AwsOptimizationFinding::query()->where('status', 'active')->get();
        $ec2 = $cloudResources->where('service', 'ec2');
        $rds = $cloudResources->where('service', 'rds');
        $s3 = $cloudResources->where('service', 's3');
        $cloudAttention = $ec2->where('state', '!=', 'running')->count()
            + $rds->where('state', '!=', 'available')->count()
            + $s3->whereIn('state', ['public', 'unknown'])->count();
        $cloudStatus = $cloudConnections->isEmpty() ? 'empty'
            : ($cloudConnections->where('status', 'error')->isNotEmpty() || $cloudFindings->where('severity', 'critical')->isNotEmpty() ? 'error'
                : ($cloudFindings->isNotEmpty() || $cloudAttention > 0 ? 'warning' : 'healthy'));

        return response()->json([
            'summary' => [
                'total' => $monitors->count(),
                'healthy' => $monitors->where('status', 'healthy')->count(),
                'warnings' => $monitors->where('status', 'warning')->count(),
                'errors' => $monitors->where('status', 'error')->count(),
            ],
            'monitors' => $monitors->values(),
            'browser_summary' => [
                'total' => $browserMonitors->count(),
                'page_loads' => $browserMonitors->sum('page_loads'),
                'requests' => $browserMonitors->sum('requests'),
                'errors' => $browserMonitors->sum('errors'),
            ],
            'browser_monitors' => $browserMonitors->values(),
            'uptime_summary' => [
                'total' => $websiteMonitors->whereNotIn('status', ['paused'])->count(),
                'healthy' => $websiteMonitors->where('status', 'healthy')->count(),
                'unavailable' => $websiteMonitors->where('status', 'error')->count(),
                'pending' => $websiteMonitors->where('status', 'warning')->count(),
                'ssl_expiring' => $websiteMonitors->filter(fn (array $monitor): bool => $monitor['status'] !== 'paused' && $monitor['ssl_days'] !== null && $monitor['ssl_days'] <= 30)->count(),
            ],
            'uptime_monitors' => $websiteMonitors->values(),
            'cloud_summary' => [
                'status' => $cloudStatus,
                'accounts' => $cloudConnections->count(),
                'connection_errors' => $cloudConnections->where('status', 'error')->count(),
                'instances' => $ec2->count(),
                'running_instances' => $ec2->where('state', 'running')->count(),
                'databases' => $rds->count(),
                'available_databases' => $rds->where('state', 'available')->count(),
                'buckets' => $s3->count(),
                'private_buckets' => $s3->where('state', 'private')->count(),
                'public_buckets' => $s3->where('state', 'public')->count(),
                'findings' => $cloudFindings->count(),
                'critical_findings' => $cloudFindings->where('severity', 'critical')->count(),
                'high_findings' => $cloudFindings->where('severity', 'high')->count(),
                'last_synced_at' => $cloudConnections->max('last_synced_at')?->toIso8601String(),
            ],
            'cloud_services' => [
                ['service' => 'ec2', 'name' => 'EC2', 'total' => $ec2->count(), 'healthy' => $ec2->where('state', 'running')->count(), 'attention' => $ec2->where('state', '!=', 'running')->count(), 'healthy_label' => 'Running'],
                ['service' => 'rds', 'name' => 'RDS', 'total' => $rds->count(), 'healthy' => $rds->where('state', 'available')->count(), 'attention' => $rds->where('state', '!=', 'available')->count(), 'healthy_label' => 'Available'],
                ['service' => 's3', 'name' => 'S3', 'total' => $s3->count(), 'healthy' => $s3->where('state', 'private')->count(), 'attention' => $s3->whereIn('state', ['public', 'unknown'])->count(), 'healthy_label' => 'Private'],
            ],
            'server_time' => now()->toIso8601String(),
        ]);
    }

    private function issues(Agent $agent, mixed $cpu, ?float $memory, ?float $disk, ?int $secondsSinceSeen, bool $hasSystemData): array
    {
        $issues = [];
        if ($secondsSinceSeen === null || $secondsSinceSeen > 120) {
            $issues[] = ['severity' => 'error', 'message' => "{$agent->hostname} is offline or has not reported for more than two minutes."];
        } elseif ($secondsSinceSeen > 30) {
            $issues[] = ['severity' => 'warning', 'message' => "{$agent->hostname} has not reported for {$secondsSinceSeen} seconds."];
        }
        if (! $hasSystemData) {
            $issues[] = ['severity' => 'warning', 'message' => "{$agent->hostname} has not submitted system monitoring data."];
        }
        foreach ([['CPU', $cpu], ['RAM', $memory], ['Disk', $disk]] as [$label, $value]) {
            if ($value !== null && $value >= 90) {
                $issues[] = ['severity' => 'error', 'message' => "{$agent->hostname} {$label} utilization is ".round((float) $value, 1).'%.'];
            } elseif ($value !== null && $value >= 75) {
                $issues[] = ['severity' => 'warning', 'message' => "{$agent->hostname} {$label} utilization is ".round((float) $value, 1).'%.'];
            }
        }

        return $issues;
    }
}
