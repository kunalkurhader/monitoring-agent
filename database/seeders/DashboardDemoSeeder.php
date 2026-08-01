<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DashboardDemoSeeder extends Seeder
{
    private const INTERVAL_SECONDS = 10;

    private const AGENTS = [
        ['id' => '11111111-1111-4111-8111-111111111111', 'hostname' => 'web-prod-01', 'memory' => 17_179_869_184, 'cpu_offset' => 4],
        ['id' => '22222222-2222-4222-8222-222222222222', 'hostname' => 'worker-prod-02', 'memory' => 34_359_738_368, 'cpu_offset' => 13],
        ['id' => '33333333-3333-4333-8333-333333333333', 'hostname' => 'database-prod-01', 'memory' => 68_719_476_736, 'cpu_offset' => 8],
    ];

    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'demo@pulsewatch.local'],
            ['name' => 'Demo Administrator', 'password' => Hash::make('PulsewatchDemo123!'), 'is_admin' => true, 'email_verified_at' => now()],
        );

        $end = CarbonImmutable::now()->startOfSecond();
        $start = $end->subDays(3);

        foreach (self::AGENTS as $agentIndex => $definition) {
            Agent::query()->updateOrCreate(
                ['id' => $definition['id']],
                ['hostname' => $definition['hostname'], 'last_seen_at' => $end],
            );

            DB::table('system_stats')->where('agent_id', $definition['id'])->delete();
            DB::table('process_stats')->where('agent_id', $definition['id'])->delete();
            DB::table('disk_stats')->where('agent_id', $definition['id'])->delete();

            $systemRows = [];
            $processRows = [];
            $diskRows = [];
            $sample = 0;

            for ($time = $start; $time <= $end; $time = $time->addSeconds(self::INTERVAL_SECONDS)) {
                $dailyWave = sin(($sample / 8640) * 2 * M_PI);
                $shortWave = sin(($sample / 180) * 2 * M_PI);
                $spike = $sample % (1500 + ($agentIndex * 130)) < 24 ? 35 : 0;
                $cpu = min(98, max(2, 24 + $definition['cpu_offset'] + ($dailyWave * 12) + ($shortWave * 8) + $spike));
                $memoryRatio = min(.92, max(.28, .48 + ($agentIndex * .09) + ($dailyWave * .06) + (($sample % 720) / 7200)));
                $usedMemory = (int) ($definition['memory'] * $memoryRatio);
                $timestamp = $time->toDateTimeString();

                $systemRows[] = [
                    'agent_id' => $definition['id'],
                    'cpu_usage' => round($cpu, 2),
                    'total_memory' => $definition['memory'],
                    'free_memory' => $definition['memory'] - $usedMemory,
                    'created_at' => $timestamp,
                ];

                foreach ($this->processes($agentIndex, $cpu, $sample) as $process) {
                    $processRows[] = array_merge($process, ['agent_id' => $definition['id'], 'created_at' => $timestamp]);
                }

                foreach ($this->disks($agentIndex, $sample) as $disk) {
                    $diskRows[] = array_merge($disk, ['agent_id' => $definition['id'], 'created_at' => $timestamp]);
                }

                if (count($systemRows) >= 500) {
                    DB::table('system_stats')->insert($systemRows);
                    DB::table('process_stats')->insert($processRows);
                    DB::table('disk_stats')->insert($diskRows);
                    $systemRows = $processRows = $diskRows = [];
                }

                $sample++;
            }

            if ($systemRows !== []) {
                DB::table('system_stats')->insert($systemRows);
                DB::table('process_stats')->insert($processRows);
                DB::table('disk_stats')->insert($diskRows);
            }
        }
    }

    private function processes(int $agentIndex, float $cpu, int $sample): array
    {
        $names = $agentIndex === 0
            ? ['nginx', 'php-fpm', 'redis-server', 'monitor-agent']
            : ($agentIndex === 1 ? ['java', 'queue-worker', 'redis-server', 'monitor-agent'] : ['postgres', 'wal-writer', 'backup-worker', 'monitor-agent']);

        return array_map(fn (string $name, int $index): array => [
            'pid' => 1000 + ($agentIndex * 100) + $index,
            'process_name' => $name,
            'command' => $name.' --production',
            'user_name' => $index === 3 ? 'monitoring' : 'app',
            'cpu_usage' => round(max(.1, ($cpu * (.48 - ($index * .09))) + sin(($sample + $index * 13) / 35) * 2), 2),
            'memory_bytes' => 90_000_000 + ($index * 180_000_000) + (($sample % 300) * 120_000),
            'state' => 'RUNNING',
            'start_time' => 1_785_560_000_000 + ($index * 1000),
        ], $names, array_keys($names));
    }

    private function disks(int $agentIndex, int $sample): array
    {
        $totalRoot = 214_748_364_800;
        $totalData = 536_870_912_000;
        $rootUsed = (int) ($totalRoot * (.52 + ($agentIndex * .08)) + ($sample * 90_000));
        $dataUsed = (int) ($totalData * (.31 + ($agentIndex * .12)) + ($sample * 180_000));

        return [
            ['device' => '/dev/nvme0n1p1', 'mount_point' => '/', 'file_system_type' => 'ext4', 'total_bytes' => $totalRoot, 'free_bytes' => $totalRoot - $rootUsed, 'used_bytes' => $rootUsed],
            ['device' => '/dev/nvme1n1p1', 'mount_point' => '/data', 'file_system_type' => 'xfs', 'total_bytes' => $totalData, 'free_bytes' => $totalData - $dataUsed, 'used_bytes' => $dataUsed],
        ];
    }
}
