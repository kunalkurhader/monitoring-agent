<?php

namespace Database\Seeders;

use App\Models\AwsConnection;
use App\Models\AwsResource;
use App\Services\AwsOptimizationAnalyzer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AwsCloudDemoSeeder extends Seeder
{
    private const ACCOUNT_ID = '123456789012';

    private const REGION = 'ap-south-1';

    public function run(AwsOptimizationAnalyzer $optimizationAnalyzer): void
    {
        $connection = AwsConnection::query()->updateOrCreate(
            ['role_arn' => 'arn:aws:iam::'.self::ACCOUNT_ID.':role/MonitoringAgentDemoRole'],
            [
                'name' => 'AWS Production Demo',
                'external_id' => 'demo-aws-cloud-connection',
                'regions' => [self::REGION],
                'poll_interval_minutes' => 5,
                'is_active' => false,
                'account_id' => self::ACCOUNT_ID,
                'status' => 'connected',
                'last_error' => null,
                'last_synced_at' => now(),
            ],
        );

        $connection->resources()->delete();
        $now = CarbonImmutable::now()->startOfMinute();

        $vpc = $this->resource($connection, [
            'arn' => $this->arn('ec2', 'vpc/vpc-0d3m0production'),
            'resource_id' => 'vpc-0d3m0production',
            'name' => 'production-vpc',
            'service' => 'ec2',
            'type' => 'vpc',
            'state' => 'available',
            'metadata' => ['cidr' => '10.20.0.0/16', 'is_default' => false],
        ]);

        $groups = [
            ['name' => 'web-production-asg', 'prefix' => 'web-prod', 'type' => 'm7i.large', 'base_cpu' => 32],
            ['name' => 'api-production-asg', 'prefix' => 'api-prod', 'type' => 'c7i.large', 'base_cpu' => 46],
            ['name' => 'worker-production-asg', 'prefix' => 'worker-prod', 'type' => 'm7i.xlarge', 'base_cpu' => 39],
        ];

        foreach ($groups as $groupIndex => $group) {
            $groupResource = $this->resource($connection, [
                'arn' => 'arn:aws:autoscaling:'.self::REGION.':'.self::ACCOUNT_ID.':autoScalingGroup:demo-'.$groupIndex.':autoScalingGroupName/'.$group['name'],
                'resource_id' => $group['name'],
                'name' => $group['name'],
                'service' => 'autoscaling',
                'type' => 'auto-scaling-group',
                'state' => 'active',
                'metadata' => ['desired_capacity' => 10, 'minimum_size' => 6, 'maximum_size' => 18, 'vpc_id' => $vpc->resource_id],
            ]);

            for ($instanceIndex = 1; $instanceIndex <= 10; $instanceIndex++) {
                $number = ($groupIndex * 10) + $instanceIndex;
                $instanceId = 'i-'.str_pad(dechex(0x1000000000000000 + $number), 17, '0', STR_PAD_LEFT);
                $zone = self::REGION.($instanceIndex % 3 === 0 ? 'c' : ($instanceIndex % 2 === 0 ? 'b' : 'a'));
                $resource = $this->resource($connection, [
                    'arn' => $this->arn('ec2', 'instance/'.$instanceId),
                    'resource_id' => $instanceId,
                    'name' => $group['prefix'].'-'.str_pad((string) $instanceIndex, 2, '0', STR_PAD_LEFT),
                    'service' => 'ec2',
                    'type' => 'instance',
                    'state' => $number === 28 ? 'stopped' : 'running',
                    'instance_type' => $group['type'],
                    'availability_zone' => $zone,
                    'tags' => ['Environment' => 'production', 'AutoScalingGroup' => $group['name'], 'Team' => $groupIndex === 0 ? 'web' : ($groupIndex === 1 ? 'platform' : 'data')],
                    'metadata' => [
                        'private_ip' => '10.20.'.($groupIndex + 1).'.'.($instanceIndex + 20),
                        'public_ip' => $groupIndex === 0 && $instanceIndex <= 2 ? '13.233.80.'.(40 + $instanceIndex) : null,
                        'platform' => 'Linux/UNIX',
                        'architecture' => 'x86_64',
                        'vpc_id' => $vpc->resource_id,
                        'auto_scaling_group_id' => $groupResource->id,
                    ],
                ]);
                $this->metrics($resource, $now, $group['base_cpu'], $number);
                $this->volume($connection, $resource, $number, 'root', '/dev/xvda', $groupIndex === 2 ? 120 : 80);
                $hasDataVolume = $groupIndex === 2 || $instanceIndex <= 3;
                if ($hasDataVolume) {
                    $this->volume($connection, $resource, $number, 'data', '/dev/xvdf', $groupIndex === 2 ? 500 : 200);
                }
                $this->filesystemMetrics($resource, $now, $number, $groupIndex === 2 ? 120 : 80, $hasDataVolume ? ($groupIndex === 2 ? 500 : 200) : null);
            }
        }

        $this->networkSecurityDemo($connection);

        foreach ([
            ['orders-primary', 'db.r7g.large', 'available', 'postgres', '15.5'],
            ['payments-primary', 'db.r7g.xlarge', 'available', 'postgres', '15.5'],
            ['analytics-reader', 'db.r7g.large', 'available', 'mysql', '8.0'],
            ['reporting-replica', 'db.m7g.large', 'maintenance', 'postgres', '14.11'],
        ] as $index => [$name, $class, $state, $engine, $version]) {
            $identifier = 'prod-'.$name;
            $database = $this->resource($connection, [
                'arn' => 'arn:aws:rds:'.self::REGION.':'.self::ACCOUNT_ID.':db:'.$identifier,
                'resource_id' => $identifier,
                'name' => $name,
                'service' => 'rds',
                'type' => 'db-instance',
                'state' => $state,
                'instance_type' => $class,
                'availability_zone' => self::REGION.($index % 2 ? 'b' : 'a'),
                'tags' => ['Environment' => 'production'],
                'metadata' => [
                    'dbi_resource_id' => 'db-DEMO'.str_pad((string) ($index + 1), 20, '0', STR_PAD_LEFT),
                    'engine' => $engine,
                    'engine_version' => $version,
                    'endpoint' => $identifier.'.demo.'.self::REGION.'.rds.amazonaws.com',
                    'port' => $engine === 'postgres' ? 5432 : 3306,
                    'allocated_storage_gib' => [500, 1000, 750, 400][$index],
                    'storage_type' => 'gp3',
                    'multi_az' => $index < 2,
                    'performance_insights_enabled' => true,
                    'backup_retention_days' => 14,
                    'vpc_id' => $vpc->resource_id,
                ],
            ]);
            $this->rdsMetrics($database, $now, $index);
            $this->databaseQueries($database, $now, $index, $engine);
        }

        $optimizationAnalyzer->analyze($connection);
    }

    private function resource(AwsConnection $connection, array $attributes): AwsResource
    {
        return AwsResource::query()->create(array_merge([
            'aws_connection_id' => $connection->id,
            'region' => self::REGION,
            'tags' => [],
            'metadata' => [],
            'last_seen_at' => now(),
        ], $attributes));
    }

    private function metrics(AwsResource $resource, CarbonImmutable $now, int $baseCpu, int $seed): void
    {
        $rows = [];
        for ($point = 287; $point >= 0; $point--) {
            $sampledAt = $now->subMinutes($point * 5);
            $wave = sin(($point + ($seed * 3)) / 11) * 13;
            $shortWave = sin(($point + $seed) / 3.7) * 5;
            $spike = ($point + $seed) % 73 < 3 ? 25 : 0;
            $cpu = min(97, max(3, $baseCpu + $wave + $shortWave + $spike));
            $networkIn = max(30_000, 2_400_000 + ($wave * 75_000) + (($seed % 7) * 210_000));
            $networkOut = max(20_000, 1_650_000 + ($shortWave * 90_000) + (($seed % 5) * 175_000));
            $failed = $seed === 17 && $point < 4 ? 1 : 0;

            foreach ([
                'CPUUtilization' => ['Percent', $cpu],
                'NetworkIn' => ['Bytes', $networkIn],
                'NetworkOut' => ['Bytes', $networkOut],
                'DiskReadBytes' => ['Bytes', 320_000 + abs($wave * 28_000)],
                'DiskWriteBytes' => ['Bytes', 510_000 + abs($shortWave * 42_000)],
                'StatusCheckFailed' => ['Count', $failed],
            ] as $metric => [$unit, $value]) {
                $rows[] = [
                    'aws_resource_id' => $resource->id,
                    'namespace' => 'AWS/EC2',
                    'metric_name' => $metric,
                    'unit' => $unit,
                    'value' => round($value, 2),
                    'sampled_at' => $sampledAt,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if (count($rows) >= 900) {
                DB::table('aws_metric_samples')->insert($rows);
                $rows = [];
            }
        }
        if ($rows !== []) {
            DB::table('aws_metric_samples')->insert($rows);
        }
    }

    private function volume(AwsConnection $connection, AwsResource $instance, int $number, string $purpose, string $device, int $sizeGiB): void
    {
        $suffix = $purpose === 'root' ? 1 : 2;
        $volumeId = 'vol-'.str_pad(dechex(0x2000000000000000 + ($number * 10) + $suffix), 17, '0', STR_PAD_LEFT);
        $this->resource($connection, [
            'arn' => $this->arn('ec2', 'volume/'.$volumeId),
            'resource_id' => $volumeId,
            'name' => $instance->name.'-'.$purpose,
            'service' => 'ec2',
            'type' => 'volume',
            'state' => 'in-use',
            'availability_zone' => $instance->availability_zone,
            'tags' => ['Environment' => 'production', 'Purpose' => $purpose],
            'metadata' => [
                'instance_id' => $instance->resource_id,
                'device' => $device,
                'attachment_state' => 'attached',
                'delete_on_termination' => $purpose === 'root',
                'size_gib' => $sizeGiB,
                'volume_type' => 'gp3',
                'iops' => $purpose === 'root' ? 3000 : 6000,
                'throughput_mibps' => $purpose === 'root' ? 125 : 250,
                'encrypted' => true,
                'snapshot_id' => $purpose === 'root' ? 'snap-demo-base-image' : null,
            ],
        ]);
    }

    private function filesystemMetrics(AwsResource $resource, CarbonImmutable $now, int $seed, int $rootGiB, ?int $dataGiB): void
    {
        $filesystems = [['path' => '/', 'device' => 'nvme0n1p1', 'fstype' => 'xfs', 'total' => $rootGiB * 1_073_741_824]];
        if ($dataGiB !== null) {
            $filesystems[] = ['path' => '/data', 'device' => 'nvme1n1', 'fstype' => 'xfs', 'total' => $dataGiB * 1_073_741_824];
        }
        $rows = [];
        for ($point = 287; $point >= 0; $point--) {
            $sampledAt = $now->subMinutes($point * 5);
            foreach ($filesystems as $index => $filesystem) {
                $dimensions = ['InstanceId' => $resource->resource_id, 'device' => $filesystem['device'], 'fstype' => $filesystem['fstype'], 'path' => $filesystem['path']];
                ksort($dimensions);
                $dimensionsHash = hash('sha256', json_encode($dimensions, JSON_THROW_ON_ERROR));
                $usedPercent = min(94, 42 + (($seed * 3 + $index * 11) % 31) + sin(($point + $seed) / 18) * 4 + ((287 - $point) / 500));
                $used = $filesystem['total'] * ($usedPercent / 100);
                foreach ([
                    'disk_total' => ['Bytes', $filesystem['total']],
                    'disk_used' => ['Bytes', $used],
                    'disk_free' => ['Bytes', $filesystem['total'] - $used],
                    'disk_used_percent' => ['Percent', $usedPercent],
                    'disk_inodes_free' => ['Count', max(100_000, 8_000_000 - ($seed * 47_000) - (($index + 1) * 125_000))],
                ] as $metric => [$unit, $value]) {
                    $rows[] = $this->agentMetricRow($resource, $metric, $dimensionsHash, $dimensions, $unit, $value, $sampledAt, $now);
                }
            }
            $memoryDimensions = ['InstanceId' => $resource->resource_id];
            $rows[] = $this->agentMetricRow(
                $resource,
                'mem_used_percent',
                hash('sha256', json_encode($memoryDimensions, JSON_THROW_ON_ERROR)),
                $memoryDimensions,
                'Percent',
                min(93, 48 + (($seed * 5) % 27) + sin(($point + $seed) / 15) * 5),
                $sampledAt,
                $now,
            );
            if (count($rows) >= 900) {
                DB::table('aws_metric_samples')->insert($rows);
                $rows = [];
            }
        }
        if ($rows !== []) {
            DB::table('aws_metric_samples')->insert($rows);
        }
    }

    private function agentMetricRow(AwsResource $resource, string $metric, string $dimensionsHash, array $dimensions, string $unit, float|int $value, CarbonImmutable $sampledAt, CarbonImmutable $now): array
    {
        return [
            'aws_resource_id' => $resource->id,
            'namespace' => 'CWAgent',
            'metric_name' => $metric,
            'dimensions_hash' => $dimensionsHash,
            'dimensions' => json_encode($dimensions, JSON_THROW_ON_ERROR),
            'unit' => $unit,
            'value' => round($value, 2),
            'sampled_at' => $sampledAt,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function rdsMetrics(AwsResource $resource, CarbonImmutable $now, int $index): void
    {
        $rows = [];
        $allocatedBytes = ($resource->metadata['allocated_storage_gib'] ?? 500) * 1_073_741_824;
        for ($point = 287; $point >= 0; $point--) {
            $sampledAt = $now->subMinutes($point * 5);
            $wave = sin(($point + $index * 17) / 13);
            $short = sin(($point + $index * 7) / 4.5);
            $values = [
                'CPUUtilization' => ['Percent', min(92, 28 + $index * 7 + $wave * 14 + ($point % 89 < 3 ? 22 : 0))],
                'FreeableMemory' => ['Bytes', (8 + $index * 4 + $wave) * 1_073_741_824],
                'FreeStorageSpace' => ['Bytes', $allocatedBytes * max(.12, .58 - $index * .07 - ((287 - $point) / 20000))],
                'DatabaseConnections' => ['Count', max(1, 42 + $index * 35 + $wave * 18 + $short * 7)],
                'ReadLatency' => ['Seconds', max(.0004, .003 + $index * .0012 + abs($short) * .002)],
                'WriteLatency' => ['Seconds', max(.0007, .005 + $index * .0015 + abs($wave) * .003)],
                'ReadIOPS' => ['Count/Second', max(1, 420 + $index * 160 + $wave * 180)],
                'WriteIOPS' => ['Count/Second', max(1, 260 + $index * 120 + $short * 110)],
                'DiskQueueDepth' => ['Count', max(0, .8 + $index * .4 + abs($short) * 1.5)],
            ];
            foreach ($values as $metric => [$unit, $value]) {
                $rows[] = ['aws_resource_id' => $resource->id, 'namespace' => 'AWS/RDS', 'metric_name' => $metric, 'unit' => $unit, 'value' => round($value, 4), 'sampled_at' => $sampledAt, 'created_at' => $now, 'updated_at' => $now];
            }
            if (count($rows) >= 900) {
                DB::table('aws_metric_samples')->insert($rows);
                $rows = [];
            }
        }
        if ($rows !== []) {
            DB::table('aws_metric_samples')->insert($rows);
        }
    }

    private function databaseQueries(AwsResource $resource, CarbonImmutable $now, int $databaseIndex, string $engine): void
    {
        $queries = $engine === 'mysql' ? [
            'SELECT orders.* FROM orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT ?',
            'UPDATE inventory SET available = available - ? WHERE sku = ?',
            'SELECT COUNT(*) FROM events WHERE created_at BETWEEN ? AND ?',
            'INSERT INTO audit_events (actor_id, action, payload) VALUES (?, ?, ?)',
            'SELECT p.* FROM products p JOIN categories c ON c.id = p.category_id WHERE c.slug = ?',
        ] : [
            'SELECT o.id, o.status, o.total FROM orders o WHERE o.customer_id = $1 ORDER BY o.created_at DESC LIMIT $2',
            'UPDATE payment_attempts SET status = $1, updated_at = NOW() WHERE id = $2',
            'SELECT COUNT(*) FROM transactions WHERE merchant_id = $1 AND created_at >= $2',
            'INSERT INTO outbox_events (aggregate_id, event_type, payload) VALUES ($1, $2, $3)',
            'SELECT i.* FROM invoices i JOIN accounts a ON a.id = i.account_id WHERE a.external_id = $1',
        ];
        $rows = [];
        for ($window = 95; $window >= 0; $window--) {
            $endedAt = $now->subMinutes($window * 15);
            foreach ($queries as $queryIndex => $query) {
                $load = max(.01, 2.8 - ($queryIndex * .42) + ($databaseIndex * .3) + sin(($window + $queryIndex) / 6) * .35);
                $rows[] = [
                    'aws_resource_id' => $resource->id,
                    'query_id' => 'sql-'.substr(hash('sha256', $resource->resource_id.$query), 0, 16),
                    'query_text' => $query,
                    'db_load' => round($load, 3),
                    'average_latency_ms' => round(8 + ($queryIndex * 19) + ($databaseIndex * 6) + abs(sin($window / 5)) * 12, 2),
                    'calls_per_second' => round(max(.1, 38 - ($queryIndex * 6) + ($databaseIndex * 4) + sin($window / 4) * 5), 2),
                    'window_started_at' => $endedAt->subMinutes(15),
                    'window_ended_at' => $endedAt,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        DB::table('aws_database_query_samples')->insert($rows);
    }

    private function arn(string $service, string $resource): string
    {
        return 'arn:aws:'.$service.':'.self::REGION.':'.self::ACCOUNT_ID.':'.$resource;
    }

    private function networkSecurityDemo(AwsConnection $connection): void
    {
        $securityGroups = [
            ['sg-00000000000000001', 'default', ['eni-web-01'], []],
            ['sg-00000000000000002', 'web-public', ['eni-web-01', 'eni-web-02'], [$this->rule('tcp', 80, 80, ['0.0.0.0/0']), $this->rule('tcp', 443, 443, ['0.0.0.0/0'])]],
            ['sg-00000000000000003', 'admin-open', ['eni-api-01'], [$this->rule('tcp', 22, 22, ['0.0.0.0/0', '::/0'])]],
            ['sg-00000000000000004', 'database-open', ['eni-database-01'], [$this->rule('tcp', 5432, 5432, ['0.0.0.0/0'])]],
            ['sg-00000000000000005', 'legacy-open-all', ['eni-worker-01'], [$this->rule('-1', null, null, ['0.0.0.0/0'])]],
            ['sg-00000000000000006', 'unused-experiment', [], [$this->rule('tcp', 8080, 8080, ['10.20.0.0/16'])]],
        ];
        foreach ($securityGroups as [$id, $name, $interfaces, $ingress]) {
            $this->resource($connection, [
                'arn' => $this->arn('ec2', 'security-group/'.$id), 'resource_id' => $id, 'name' => $name,
                'service' => 'ec2', 'type' => 'security-group', 'state' => 'active',
                'metadata' => [
                    'group_name' => $name, 'description' => 'Simulated '.$name.' security group',
                    'vpc_id' => 'vpc-0d3m0production', 'network_interface_ids' => $interfaces,
                    'ingress_rules' => $ingress, 'egress_rules' => [$this->rule('-1', null, null, ['0.0.0.0/0'])],
                ],
            ]);
        }

        $addresses = [
            ['eipalloc-00000000000000001', '13.233.80.41', 'eipassoc-web01', 'eni-web-01', null],
            ['eipalloc-00000000000000002', '13.233.80.42', 'eipassoc-admin01', 'eni-api-01', null],
            ['eipalloc-00000000000000003', '13.233.80.43', 'eipassoc-worker01', 'eni-worker-01', null],
            ['eipalloc-00000000000000004', '13.233.80.44', 'eipassoc-stopped', 'eni-worker-08', $this->demoInstanceId(28)],
            ['eipalloc-00000000000000005', '13.233.80.45', 'eipassoc-nat01', 'eni-nat-01', null],
            ['eipalloc-00000000000000006', '13.233.80.46', 'eipassoc-nat02', 'eni-nat-02', null],
            ['eipalloc-00000000000000007', '13.233.80.47', null, null, null],
            ['eipalloc-00000000000000008', '13.233.80.48', null, null, null],
        ];
        foreach ($addresses as [$id, $ip, $association, $interface, $instance]) {
            $this->resource($connection, [
                'arn' => $this->arn('ec2', 'elastic-ip/'.$id), 'resource_id' => $id, 'name' => $ip,
                'service' => 'ec2', 'type' => 'elastic-ip', 'state' => $association ? 'associated' : 'unassociated',
                'metadata' => ['public_ip' => $ip, 'association_id' => $association, 'network_interface_id' => $interface, 'instance_id' => $instance, 'domain' => 'vpc', 'network_border_group' => self::REGION],
            ]);
        }
    }

    private function rule(string $protocol, ?int $from, ?int $to, array $ipv4): array
    {
        return ['protocol' => $protocol, 'from_port' => $from, 'to_port' => $to, 'ipv4_ranges' => $ipv4, 'ipv6_ranges' => [], 'source_security_groups' => [], 'prefix_lists' => []];
    }

    private function demoInstanceId(int $number): string
    {
        return 'i-'.str_pad(dechex(0x1000000000000000 + $number), 17, '0', STR_PAD_LEFT);
    }
}
