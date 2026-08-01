<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboard', [
            'agents' => Agent::query()->orderBy('hostname')->get(['id', 'hostname', 'last_seen_at']),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => ['required', 'uuid', 'exists:agents,id'],
            'range' => ['nullable', 'integer', 'in:1,6,24'],
        ]);

        $hours = (int) ($validated['range'] ?? 1);
        $agent = Agent::query()->findOrFail($validated['agent_id']);
        $systemRows = DB::table('system_stats')
            ->where('agent_id', $agent->id)
            ->where('created_at', '>=', now()->subHours($hours))
            ->orderBy('created_at')
            ->get(['cpu_usage', 'total_memory', 'free_memory', 'created_at']);

        $latestProcessAt = DB::table('process_stats')->where('agent_id', $agent->id)->max('created_at');
        $processCount = $latestProcessAt
            ? DB::table('process_stats')->where('agent_id', $agent->id)->where('created_at', $latestProcessAt)->count()
            : 0;
        $processes = $latestProcessAt
            ? DB::table('process_stats')->where('agent_id', $agent->id)->where('created_at', $latestProcessAt)
                ->orderByDesc('cpu_usage')->limit(10)
                ->get(['pid', 'process_name', 'cpu_usage', 'memory_bytes', 'state'])
            : collect();

        $latestDiskAt = DB::table('disk_stats')->where('agent_id', $agent->id)->max('created_at');
        $disks = $latestDiskAt
            ? DB::table('disk_stats')->where('agent_id', $agent->id)->where('created_at', $latestDiskAt)
                ->orderBy('mount_point')->get(['device', 'mount_point', 'file_system_type', 'total_bytes', 'free_bytes', 'used_bytes'])
            : collect();

        return response()->json([
            'agent' => $agent,
            'series' => $this->aggregate($systemRows, 360),
            'current' => $this->current($systemRows, $processCount),
            'processes' => $processes,
            'disks' => $disks,
            'disk_synced_at' => $latestDiskAt,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    private function aggregate(Collection $rows, int $maxPoints): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        $size = max(1, (int) ceil($rows->count() / $maxPoints));

        return $rows->chunk($size)->map(function (Collection $chunk): array {
            $last = $chunk->last();

            return [
                'time' => $last->created_at,
                'cpu' => round((float) $chunk->avg('cpu_usage'), 2),
                'memory' => round((float) $chunk->avg(function ($row): float {
                    return $row->total_memory > 0
                        ? (($row->total_memory - $row->free_memory) / $row->total_memory) * 100
                        : 0;
                }), 2),
            ];
        })->values()->all();
    }

    private function current(Collection $systemRows, int $processCount): array
    {
        $latest = $systemRows->last();
        $total = (int) ($latest->total_memory ?? 0);
        $free = (int) ($latest->free_memory ?? 0);

        return [
            'cpu' => round((float) ($latest->cpu_usage ?? 0), 2),
            'total_memory' => $total,
            'free_memory' => $free,
            'used_memory' => max(0, $total - $free),
            'process_count' => $processCount,
        ];
    }
}
