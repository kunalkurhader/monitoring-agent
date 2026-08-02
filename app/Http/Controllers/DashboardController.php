<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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
            'range' => ['nullable', 'integer', 'in:1,6,24,72'],
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
            'process_heatmap' => $this->processHeatmap($agent->id, $hours),
            'disks' => $disks,
            'disk_synced_at' => $latestDiskAt,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function processes(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => ['required', 'uuid', 'exists:agents,id'],
            'at' => ['required', 'date'],
        ]);

        $sampledAt = DB::table('process_stats')
            ->where('agent_id', $validated['agent_id'])
            ->where('created_at', '<=', $validated['at'])
            ->max('created_at');

        $processes = $sampledAt
            ? DB::table('process_stats')
                ->where('agent_id', $validated['agent_id'])
                ->where('created_at', $sampledAt)
                ->orderByDesc('cpu_usage')
                ->limit(500)
                ->get(['pid', 'process_name', 'command', 'user_name', 'cpu_usage', 'memory_bytes', 'state', 'start_time'])
            : collect();

        return response()->json([
            'sampled_at' => $sampledAt,
            'processes' => $processes,
        ]);
    }

    public function storage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => ['required', 'uuid', 'exists:agents,id'],
            'at' => ['required', 'date'],
        ]);

        $sampledAt = DB::table('disk_stats')
            ->where('agent_id', $validated['agent_id'])
            ->where('created_at', '<=', $validated['at'])
            ->max('created_at');

        $disks = $sampledAt
            ? DB::table('disk_stats')
                ->where('agent_id', $validated['agent_id'])
                ->where('created_at', $sampledAt)
                ->orderBy('mount_point')
                ->get(['device', 'mount_point', 'file_system_type', 'total_bytes', 'free_bytes', 'used_bytes'])
            : collect();

        return response()->json(['sampled_at' => $sampledAt, 'disks' => $disks]);
    }

    public function logs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => ['required', 'uuid', 'exists:agents,id'],
            'file_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $files = DB::table('agent_log_files')->where('agent_id', $validated['agent_id'])
            ->orderBy('path')->get(['id', 'name', 'path', 'status', 'last_offset', 'last_seen_at']);
        $fileId = isset($validated['file_id']) ? (int) $validated['file_id'] : (int) ($files->first()->id ?? 0);
        if ($fileId && ! $files->contains('id', $fileId)) {
            abort(404);
        }
        $from = isset($validated['from']) ? Carbon::parse($validated['from']) : now()->subDay();
        $to = isset($validated['to']) ? Carbon::parse($validated['to']) : now();
        $chunks = $fileId
            ? DB::table('agent_log_chunks')->where('agent_log_file_id', $fileId)
                ->whereBetween('captured_at', [$from, $to])->orderByDesc('captured_at')->paginate(25)
            : null;

        return response()->json([
            'files' => $files,
            'selected_file_id' => $fileId ?: null,
            'chunks' => $chunks?->items() ?? [],
            'pagination' => $chunks ? [
                'current_page' => $chunks->currentPage(), 'last_page' => $chunks->lastPage(), 'total' => $chunks->total(),
            ] : ['current_page' => 1, 'last_page' => 1, 'total' => 0],
        ]);
    }

    private function processHeatmap(string $agentId, int $hours): array
    {
        return Cache::remember(
            "dashboard:process-heatmap:{$agentId}:{$hours}",
            now()->addSeconds(30),
            fn (): array => $this->buildProcessHeatmap($agentId, $hours),
        );
    }

    private function buildProcessHeatmap(string $agentId, int $hours): array
    {
        $from = now()->subHours($hours);
        $names = DB::table('process_stats')
            ->where('agent_id', $agentId)
            ->where('created_at', '>=', $from)
            ->whereNotNull('process_name')
            ->select('process_name')
            ->groupBy('process_name')
            ->orderByRaw('AVG(cpu_usage) DESC')
            ->limit(6)
            ->pluck('process_name');

        if ($names->isEmpty()) {
            return ['labels' => [], 'rows' => []];
        }

        $bucketCount = 24;
        $start = $from->getTimestamp();
        $duration = max(1, now()->getTimestamp() - $start);
        $buckets = [];

        DB::table('process_stats')
            ->where('agent_id', $agentId)
            ->where('created_at', '>=', $from)
            ->whereIn('process_name', $names)
            ->orderBy('created_at')
            ->get(['process_name', 'cpu_usage', 'created_at'])
            ->each(function ($row) use (&$buckets, $start, $duration, $bucketCount): void {
                $timestamp = strtotime((string) $row->created_at);
                $index = min($bucketCount - 1, max(0, (int) floor((($timestamp - $start) / $duration) * $bucketCount)));
                $buckets[$row->process_name][$index][] = (float) $row->cpu_usage;
            });

        $labels = [];
        for ($index = 0; $index < $bucketCount; $index++) {
            $labels[] = $from->copy()->addSeconds((int) (($duration / $bucketCount) * $index))->toIso8601String();
        }

        return [
            'labels' => $labels,
            'rows' => $names->map(fn (string $name): array => [
                'name' => $name,
                'values' => array_map(function (int $index) use ($buckets, $name): float {
                    $values = $buckets[$name][$index] ?? [];

                    return $values === [] ? 0 : round(array_sum($values) / count($values), 2);
                }, range(0, $bucketCount - 1)),
            ])->values()->all(),
        ];
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
