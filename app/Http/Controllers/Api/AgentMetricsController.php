<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentMetricsController extends Controller
{
    public function ping(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => ['required', 'uuid'],
            'hostname' => ['required', 'string', 'max:255'],
            'system.cpu_usage' => ['required', 'numeric', 'between:0,100'],
            'system.total_memory' => ['required', 'integer', 'min:0'],
            'system.free_memory' => ['required', 'integer', 'min:0'],
            'processes' => ['present', 'array', 'max:500'],
            'processes.*.pid' => ['required', 'integer', 'min:0'],
            'processes.*.process_name' => ['nullable', 'string', 'max:255'],
            'processes.*.command' => ['nullable', 'string'],
            'processes.*.user_name' => ['nullable', 'string', 'max:100'],
            'processes.*.cpu_usage' => ['required', 'numeric', 'min:0'],
            'processes.*.memory_bytes' => ['required', 'integer', 'min:0'],
            'processes.*.state' => ['nullable', 'string', 'max:50'],
            'processes.*.start_time' => ['required', 'integer', 'min:0'],
        ]);

        $receivedAt = now();

        DB::transaction(function () use ($validated, $receivedAt): void {
            Agent::query()->updateOrCreate(
                ['id' => $validated['agent_id']],
                ['hostname' => $validated['hostname'], 'last_seen_at' => $receivedAt],
            );

            DB::table('system_stats')->insert([
                'agent_id' => $validated['agent_id'],
                'cpu_usage' => $validated['system']['cpu_usage'],
                'total_memory' => $validated['system']['total_memory'],
                'free_memory' => $validated['system']['free_memory'],
                'created_at' => $receivedAt,
            ]);

            $processes = array_map(fn (array $process): array => [
                'agent_id' => $validated['agent_id'],
                'pid' => $process['pid'],
                'process_name' => $process['process_name'] ?? null,
                'command' => $process['command'] ?? null,
                'user_name' => $process['user_name'] ?? null,
                'cpu_usage' => $process['cpu_usage'],
                'memory_bytes' => $process['memory_bytes'],
                'state' => $process['state'] ?? null,
                'start_time' => $process['start_time'],
                'created_at' => $receivedAt,
            ], $validated['processes']);

            if ($processes !== []) {
                DB::table('process_stats')->insert($processes);
            }
        });

        return response()->json([
            'status' => 'accepted',
            'processes_received' => count($validated['processes']),
        ], 202);
    }

    public function storeDisks(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => ['required', 'uuid'],
            'hostname' => ['required', 'string', 'max:255'],
            'disks' => ['present', 'array', 'max:100'],
            'disks.*.device' => ['nullable', 'string', 'max:255'],
            'disks.*.mount_point' => ['required', 'string', 'max:1024'],
            'disks.*.file_system_type' => ['nullable', 'string', 'max:100'],
            'disks.*.total_bytes' => ['required', 'integer', 'min:0'],
            'disks.*.free_bytes' => ['required', 'integer', 'min:0'],
            'disks.*.used_bytes' => ['required', 'integer', 'min:0'],
        ]);

        $receivedAt = now();

        DB::transaction(function () use ($validated, $receivedAt): void {
            Agent::query()->updateOrCreate(
                ['id' => $validated['agent_id']],
                ['hostname' => $validated['hostname'], 'last_seen_at' => $receivedAt],
            );

            $disks = array_map(fn (array $disk): array => [
                'agent_id' => $validated['agent_id'],
                'device' => $disk['device'] ?? null,
                'mount_point' => $disk['mount_point'],
                'file_system_type' => $disk['file_system_type'] ?? null,
                'total_bytes' => $disk['total_bytes'],
                'free_bytes' => $disk['free_bytes'],
                'used_bytes' => max(0, $disk['total_bytes'] - $disk['free_bytes']),
                'created_at' => $receivedAt,
            ], $validated['disks']);

            if ($disks !== []) {
                DB::table('disk_stats')->insert($disks);
            }
        });

        return response()->json([
            'status' => 'accepted',
            'disks_received' => count($validated['disks']),
        ], 202);
    }
}
