<?php

namespace App\Http\Controllers;

use App\Models\AwsConnection;
use App\Models\AwsOptimizationFinding;
use App\Models\AwsResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CloudDashboardController extends Controller
{
    public function index(): View
    {
        $connections = AwsConnection::query()->withCount('resources')->orderBy('name')->get();
        $resources = AwsResource::query()->with('connection')->orderBy('service')->orderBy('name')->get();

        return view('cloud.index', [
            'connections' => $connections,
            'resources' => $resources,
            'summary' => [
                'accounts' => $connections->count(),
                'resources' => $resources->count(),
                'instances' => $resources->where('service', 'ec2')->where('type', 'instance')->count(),
                'databases' => $resources->where('service', 'rds')->where('type', 'db-instance')->count(),
                'running' => $resources->where('service', 'ec2')->where('state', 'running')->count(),
                'stopped' => $resources->where('service', 'ec2')->where('state', 'stopped')->count(),
                'regions' => $resources->pluck('region')->unique()->count(),
                'findings' => AwsOptimizationFinding::query()->where('status', 'active')->count(),
            ],
        ]);
    }

    public function recommendations(): View
    {
        $findings = AwsOptimizationFinding::query()->where('status', 'active')->with('connection')->get()
            ->sortBy(fn (AwsOptimizationFinding $finding): int => array_search($finding->severity, ['critical', 'high', 'medium', 'low'], true) ?: 0)
            ->values();

        return view('cloud.recommendations', [
            'findings' => $findings,
            'summary' => [
                'total' => $findings->count(),
                'critical' => $findings->where('severity', 'critical')->count(),
                'high' => $findings->where('severity', 'high')->count(),
                'medium' => $findings->where('severity', 'medium')->count(),
                'low' => $findings->where('severity', 'low')->count(),
                'security_groups' => $findings->where('category', 'security-group')->count(),
                'elastic_ips' => $findings->where('category', 'elastic-ip')->count(),
            ],
        ]);
    }

    public function instances(): View
    {
        return view('cloud.instances', [
            'instances' => AwsResource::query()->where('service', 'ec2')->where('type', 'instance')
                ->with('connection')->orderBy('name')->get(),
        ]);
    }

    public function instanceData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'resource_id' => ['required', 'integer', 'exists:aws_resources,id'],
            'range' => ['nullable', 'integer', 'in:1,6,24,72,168'],
        ]);
        $hours = (int) ($validated['range'] ?? 1);
        $resource = AwsResource::query()->with('connection')->findOrFail($validated['resource_id']);
        abort_unless($resource->service === 'ec2' && $resource->type === 'instance', 404);

        $rows = DB::table('aws_metric_samples')
            ->where('aws_resource_id', $resource->id)
            ->where('namespace', 'AWS/EC2')
            ->where('sampled_at', '>=', now()->subHours($hours))
            ->orderBy('sampled_at')
            ->get(['metric_name', 'value', 'sampled_at']);

        $series = $rows->groupBy(fn ($row): string => (string) $row->sampled_at)
            ->map(function ($samples, string $time): array {
                $metrics = $samples->pluck('value', 'metric_name');

                return [
                    'time' => $time,
                    'cpu' => round((float) ($metrics['CPUUtilization'] ?? 0), 2),
                    'network_in' => (float) ($metrics['NetworkIn'] ?? 0),
                    'network_out' => (float) ($metrics['NetworkOut'] ?? 0),
                    'disk_read' => (float) ($metrics['DiskReadBytes'] ?? 0),
                    'disk_write' => (float) ($metrics['DiskWriteBytes'] ?? 0),
                    'status_failed' => (float) ($metrics['StatusCheckFailed'] ?? 0),
                ];
            })->values();
        $volumes = AwsResource::query()
            ->where('aws_connection_id', $resource->aws_connection_id)
            ->where('service', 'ec2')
            ->where('type', 'volume')
            ->where('region', $resource->region)
            ->orderBy('name')
            ->get()
            ->filter(fn (AwsResource $volume): bool => ($volume->metadata['instance_id'] ?? null) === $resource->resource_id)
            ->values();
        $agentRows = DB::table('aws_metric_samples')
            ->where('aws_resource_id', $resource->id)
            ->where('namespace', 'CWAgent')
            ->where('sampled_at', '>=', now()->subHours($hours))
            ->orderByDesc('sampled_at')
            ->get(['metric_name', 'dimensions_hash', 'dimensions', 'value', 'sampled_at']);
        $filesystems = $agentRows->whereIn('metric_name', ['disk_total', 'disk_used', 'disk_free', 'disk_used_percent', 'disk_inodes_free'])
            ->groupBy('dimensions_hash')
            ->map(function ($samples): array {
                $latest = $samples->unique('metric_name')->pluck('value', 'metric_name');
                $dimensions = json_decode((string) $samples->first()->dimensions, true) ?: [];

                return [
                    'path' => $dimensions['path'] ?? $dimensions['Path'] ?? 'Unknown mount',
                    'device' => $dimensions['device'] ?? $dimensions['Device'] ?? null,
                    'filesystem_type' => $dimensions['fstype'] ?? $dimensions['Filesystem'] ?? null,
                    'total_bytes' => (float) ($latest['disk_total'] ?? 0),
                    'used_bytes' => (float) ($latest['disk_used'] ?? 0),
                    'free_bytes' => (float) ($latest['disk_free'] ?? 0),
                    'used_percent' => (float) ($latest['disk_used_percent'] ?? 0),
                    'free_inodes' => isset($latest['disk_inodes_free']) ? (float) $latest['disk_inodes_free'] : null,
                    'sampled_at' => $samples->max('sampled_at'),
                ];
            })->sortBy('path')->values();
        $memoryUsedPercent = $agentRows->where('metric_name', 'mem_used_percent')->first()?->value;

        return response()->json([
            'resource' => $resource,
            'series' => $series,
            'current' => $series->last(),
            'volumes' => $volumes,
            'filesystems' => $filesystems,
            'memory_used_percent' => $memoryUsedPercent === null ? null : (float) $memoryUsedPercent,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function databases(): View
    {
        return view('cloud.databases', [
            'databases' => AwsResource::query()->where('service', 'rds')->where('type', 'db-instance')
                ->with('connection')->orderBy('name')->get(),
        ]);
    }

    public function databaseData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'resource_id' => ['required', 'integer', 'exists:aws_resources,id'],
            'range' => ['nullable', 'integer', 'in:1,6,24,72,168'],
        ]);
        $hours = (int) ($validated['range'] ?? 1);
        $resource = AwsResource::query()->with('connection')->findOrFail($validated['resource_id']);
        abort_unless($resource->service === 'rds' && $resource->type === 'db-instance', 404);
        $rows = DB::table('aws_metric_samples')->where('aws_resource_id', $resource->id)
            ->where('namespace', 'AWS/RDS')->where('sampled_at', '>=', now()->subHours($hours))
            ->orderBy('sampled_at')->get(['metric_name', 'value', 'sampled_at']);
        $series = $rows->groupBy(fn ($row): string => (string) $row->sampled_at)->map(function ($samples, string $time): array {
            $metrics = $samples->pluck('value', 'metric_name');

            return [
                'time' => $time,
                'cpu' => (float) ($metrics['CPUUtilization'] ?? 0),
                'free_memory' => (float) ($metrics['FreeableMemory'] ?? 0),
                'free_storage' => (float) ($metrics['FreeStorageSpace'] ?? 0),
                'connections' => (float) ($metrics['DatabaseConnections'] ?? 0),
                'read_latency_ms' => (float) ($metrics['ReadLatency'] ?? 0) * 1000,
                'write_latency_ms' => (float) ($metrics['WriteLatency'] ?? 0) * 1000,
                'read_iops' => (float) ($metrics['ReadIOPS'] ?? 0),
                'write_iops' => (float) ($metrics['WriteIOPS'] ?? 0),
                'disk_queue_depth' => (float) ($metrics['DiskQueueDepth'] ?? 0),
            ];
        })->values();
        $queryRows = DB::table('aws_database_query_samples')->where('aws_resource_id', $resource->id)
            ->where('window_ended_at', '>=', now()->subHours($hours))->orderByDesc('window_ended_at')->get();
        $latestWindow = $queryRows->max('window_ended_at');
        $queries = $latestWindow ? $queryRows->where('window_ended_at', $latestWindow)->sortByDesc('db_load')->take(20)->values() : collect();

        return response()->json([
            'resource' => $resource,
            'series' => $series,
            'current' => $series->last(),
            'queries' => $queries,
            'query_window_ended_at' => $latestWindow,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
