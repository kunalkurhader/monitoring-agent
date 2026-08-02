<?php

namespace App\Http\Controllers;

use App\Models\Agent;
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

        return response()->json([
            'summary' => [
                'total' => $monitors->count(),
                'healthy' => $monitors->where('status', 'healthy')->count(),
                'warnings' => $monitors->where('status', 'warning')->count(),
                'errors' => $monitors->where('status', 'error')->count(),
            ],
            'monitors' => $monitors->values(),
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
