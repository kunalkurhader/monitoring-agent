<?php

namespace App\Services;

use App\Models\AwsConnection;
use App\Models\AwsDatabaseQuerySample;
use App\Models\AwsMetricSample;
use App\Models\AwsResource;
use Aws\CloudWatch\CloudWatchClient;
use Aws\Credentials\CredentialProvider;
use Aws\Ec2\Ec2Client;
use Aws\PI\PIClient;
use Aws\Rds\RdsClient;
use Aws\Sts\StsClient;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Throwable;

class AwsCloudSyncService
{
    private const EC2_METRICS = [
        'CPUUtilization' => ['Average', 'Percent'],
        'NetworkIn' => ['Sum', 'Bytes'],
        'NetworkOut' => ['Sum', 'Bytes'],
        'DiskReadBytes' => ['Sum', 'Bytes'],
        'DiskWriteBytes' => ['Sum', 'Bytes'],
        'StatusCheckFailed' => ['Maximum', 'Count'],
    ];

    private const CW_AGENT_METRICS = [
        'disk_total' => ['Average', 'Bytes'],
        'disk_used' => ['Average', 'Bytes'],
        'disk_free' => ['Average', 'Bytes'],
        'disk_used_percent' => ['Average', 'Percent'],
        'disk_inodes_free' => ['Average', 'Count'],
        'mem_used_percent' => ['Average', 'Percent'],
    ];

    private const RDS_METRICS = [
        'CPUUtilization' => ['Average', 'Percent'],
        'FreeableMemory' => ['Average', 'Bytes'],
        'FreeStorageSpace' => ['Average', 'Bytes'],
        'DatabaseConnections' => ['Average', 'Count'],
        'ReadLatency' => ['Average', 'Seconds'],
        'WriteLatency' => ['Average', 'Seconds'],
        'ReadIOPS' => ['Average', 'Count/Second'],
        'WriteIOPS' => ['Average', 'Count/Second'],
        'DiskQueueDepth' => ['Average', 'Count'],
    ];

    public function __construct(private readonly AwsOptimizationAnalyzer $optimizationAnalyzer) {}

    public function sync(AwsConnection $connection): void
    {
        try {
            $credentials = $this->credentials($connection);
            $regions = $connection->regions ?: $this->discoverRegions($credentials);
            $accountId = $this->accountId($credentials);

            foreach ($regions as $region) {
                $this->syncRegion($connection, $credentials, $accountId, $region);
            }
            $this->optimizationAnalyzer->analyze($connection);

            $connection->update([
                'account_id' => $accountId,
                'status' => 'connected',
                'last_error' => null,
                'last_synced_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $connection->update([
                'status' => 'error',
                'last_error' => mb_substr($exception->getMessage(), 0, 4000),
            ]);

            throw $exception;
        }
    }

    private function credentials(AwsConnection $connection): callable
    {
        $sts = new StsClient([
            'version' => 'latest',
            'region' => config('services.aws_monitoring.region', 'us-east-1'),
        ]);

        return CredentialProvider::memoize(CredentialProvider::assumeRole([
            'client' => $sts,
            'assume_role_params' => [
                'RoleArn' => $connection->role_arn,
                'RoleSessionName' => 'monitoring-agent-'.$connection->id,
                'ExternalId' => $connection->external_id,
                'DurationSeconds' => 3600,
            ],
        ]));
    }

    private function accountId(callable $credentials): string
    {
        $sts = new StsClient([
            'version' => 'latest',
            'region' => config('services.aws_monitoring.region', 'us-east-1'),
            'credentials' => $credentials,
        ]);

        return (string) $sts->getCallerIdentity()['Account'];
    }

    private function discoverRegions(callable $credentials): array
    {
        $ec2 = new Ec2Client([
            'version' => 'latest',
            'region' => config('services.aws_monitoring.region', 'us-east-1'),
            'credentials' => $credentials,
        ]);

        return collect($ec2->describeRegions(['AllRegions' => false])['Regions'] ?? [])
            ->pluck('RegionName')->filter()->values()->all();
    }

    private function syncRegion(AwsConnection $connection, callable $credentials, string $accountId, string $region): void
    {
        $ec2 = new Ec2Client(['version' => 'latest', 'region' => $region, 'credentials' => $credentials]);
        $cloudWatch = new CloudWatchClient(['version' => 'latest', 'region' => $region, 'credentials' => $credentials]);
        $instanceIds = [];

        $this->syncNetworkInventory($ec2, $connection, $accountId, $region);

        foreach ($ec2->getPaginator('DescribeInstances') as $page) {
            foreach ($page['Reservations'] ?? [] as $reservation) {
                foreach ($reservation['Instances'] ?? [] as $instance) {
                    $tags = collect($instance['Tags'] ?? [])->pluck('Value', 'Key')->all();
                    $instanceId = (string) $instance['InstanceId'];
                    $instanceIds[] = $instanceId;
                    $resource = AwsResource::query()->updateOrCreate(
                        ['aws_connection_id' => $connection->id, 'resource_id' => $instanceId, 'region' => $region],
                        [
                            'arn' => "arn:aws:ec2:{$region}:{$accountId}:instance/{$instanceId}",
                            'name' => $tags['Name'] ?? $instanceId,
                            'service' => 'ec2',
                            'type' => 'instance',
                            'state' => Arr::get($instance, 'State.Name'),
                            'instance_type' => $instance['InstanceType'] ?? null,
                            'availability_zone' => Arr::get($instance, 'Placement.AvailabilityZone'),
                            'tags' => $tags,
                            'metadata' => [
                                'private_ip' => $instance['PrivateIpAddress'] ?? null,
                                'public_ip' => $instance['PublicIpAddress'] ?? null,
                                'platform' => $instance['PlatformDetails'] ?? null,
                                'vpc_id' => $instance['VpcId'] ?? null,
                                'subnet_id' => $instance['SubnetId'] ?? null,
                                'architecture' => $instance['Architecture'] ?? null,
                            ],
                            'last_seen_at' => now(),
                        ],
                    );

                    $this->syncMetrics($cloudWatch, $resource, $connection->poll_interval_minutes);
                    $this->syncCloudWatchAgentMetrics($cloudWatch, $resource, $connection->poll_interval_minutes);
                }
            }
        }

        $this->syncVolumes($ec2, $connection, $accountId, $region, $instanceIds);
        $this->syncDatabases($connection, $credentials, $region);
    }

    private function syncNetworkInventory(Ec2Client $ec2, AwsConnection $connection, string $accountId, string $region): void
    {
        AwsResource::query()->where('aws_connection_id', $connection->id)->where('region', $region)
            ->whereIn('type', ['security-group', 'elastic-ip'])->update(['state' => 'stale']);
        $groupAssociations = [];
        foreach ($ec2->getPaginator('DescribeNetworkInterfaces') as $page) {
            foreach ($page['NetworkInterfaces'] ?? [] as $interface) {
                foreach ($interface['Groups'] ?? [] as $group) {
                    $groupAssociations[$group['GroupId']][] = $interface['NetworkInterfaceId'];
                }
            }
        }

        foreach ($ec2->getPaginator('DescribeSecurityGroups') as $page) {
            foreach ($page['SecurityGroups'] ?? [] as $group) {
                $groupId = (string) $group['GroupId'];
                AwsResource::query()->updateOrCreate(
                    ['aws_connection_id' => $connection->id, 'resource_id' => $groupId, 'region' => $region],
                    [
                        'arn' => "arn:aws:ec2:{$region}:{$accountId}:security-group/{$groupId}",
                        'name' => $group['GroupName'] ?? $groupId,
                        'service' => 'ec2',
                        'type' => 'security-group',
                        'state' => 'active',
                        'tags' => collect($group['Tags'] ?? [])->pluck('Value', 'Key')->all(),
                        'metadata' => [
                            'group_name' => $group['GroupName'] ?? null,
                            'description' => $group['Description'] ?? null,
                            'vpc_id' => $group['VpcId'] ?? null,
                            'network_interface_ids' => array_values(array_unique($groupAssociations[$groupId] ?? [])),
                            'ingress_rules' => $this->normalizeSecurityGroupRules($group['IpPermissions'] ?? []),
                            'egress_rules' => $this->normalizeSecurityGroupRules($group['IpPermissionsEgress'] ?? []),
                        ],
                        'last_seen_at' => now(),
                    ],
                );
            }
        }

        foreach ($ec2->describeAddresses()['Addresses'] ?? [] as $address) {
            $allocationId = (string) ($address['AllocationId'] ?? $address['PublicIp']);
            AwsResource::query()->updateOrCreate(
                ['aws_connection_id' => $connection->id, 'resource_id' => $allocationId, 'region' => $region],
                [
                    'arn' => "arn:aws:ec2:{$region}:{$accountId}:elastic-ip/{$allocationId}",
                    'name' => $address['PublicIp'] ?? $allocationId,
                    'service' => 'ec2',
                    'type' => 'elastic-ip',
                    'state' => empty($address['AssociationId']) ? 'unassociated' : 'associated',
                    'metadata' => [
                        'public_ip' => $address['PublicIp'] ?? null,
                        'private_ip' => $address['PrivateIpAddress'] ?? null,
                        'association_id' => $address['AssociationId'] ?? null,
                        'network_interface_id' => $address['NetworkInterfaceId'] ?? null,
                        'instance_id' => $address['InstanceId'] ?? null,
                        'domain' => $address['Domain'] ?? null,
                        'network_border_group' => $address['NetworkBorderGroup'] ?? null,
                    ],
                    'last_seen_at' => now(),
                ],
            );
        }
    }

    private function normalizeSecurityGroupRules(array $permissions): array
    {
        return collect($permissions)->map(fn (array $rule): array => [
            'protocol' => (string) ($rule['IpProtocol'] ?? '-1'),
            'from_port' => $rule['FromPort'] ?? null,
            'to_port' => $rule['ToPort'] ?? null,
            'ipv4_ranges' => collect($rule['IpRanges'] ?? [])->pluck('CidrIp')->filter()->values()->all(),
            'ipv6_ranges' => collect($rule['Ipv6Ranges'] ?? [])->pluck('CidrIpv6')->filter()->values()->all(),
            'source_security_groups' => collect($rule['UserIdGroupPairs'] ?? [])->pluck('GroupId')->filter()->values()->all(),
            'prefix_lists' => collect($rule['PrefixListIds'] ?? [])->pluck('PrefixListId')->filter()->values()->all(),
        ])->values()->all();
    }

    private function syncDatabases(AwsConnection $connection, callable $credentials, string $region): void
    {
        $rds = new RdsClient(['version' => 'latest', 'region' => $region, 'credentials' => $credentials]);
        $cloudWatch = new CloudWatchClient(['version' => 'latest', 'region' => $region, 'credentials' => $credentials]);
        $performanceInsights = new PIClient(['version' => 'latest', 'region' => $region, 'credentials' => $credentials]);

        foreach ($rds->getPaginator('DescribeDBInstances') as $page) {
            foreach ($page['DBInstances'] ?? [] as $database) {
                $identifier = (string) $database['DBInstanceIdentifier'];
                $resource = AwsResource::query()->updateOrCreate(
                    ['aws_connection_id' => $connection->id, 'resource_id' => $identifier, 'region' => $region],
                    [
                        'arn' => $database['DBInstanceArn'],
                        'name' => $identifier,
                        'service' => 'rds',
                        'type' => 'db-instance',
                        'state' => $database['DBInstanceStatus'] ?? null,
                        'instance_type' => $database['DBInstanceClass'] ?? null,
                        'availability_zone' => $database['AvailabilityZone'] ?? null,
                        'tags' => collect($database['TagList'] ?? [])->pluck('Value', 'Key')->all(),
                        'metadata' => [
                            'dbi_resource_id' => $database['DbiResourceId'] ?? null,
                            'engine' => $database['Engine'] ?? null,
                            'engine_version' => $database['EngineVersion'] ?? null,
                            'endpoint' => Arr::get($database, 'Endpoint.Address'),
                            'port' => Arr::get($database, 'Endpoint.Port'),
                            'allocated_storage_gib' => $database['AllocatedStorage'] ?? null,
                            'storage_type' => $database['StorageType'] ?? null,
                            'multi_az' => $database['MultiAZ'] ?? false,
                            'performance_insights_enabled' => $database['PerformanceInsightsEnabled'] ?? false,
                            'backup_retention_days' => $database['BackupRetentionPeriod'] ?? null,
                        ],
                        'last_seen_at' => now(),
                    ],
                );

                $this->syncRdsMetrics($cloudWatch, $resource, $connection->poll_interval_minutes);
                if (($database['PerformanceInsightsEnabled'] ?? false) && ! empty($database['DbiResourceId'])) {
                    try {
                        $this->syncDatabaseQueries($performanceInsights, $resource, (string) $database['DbiResourceId']);
                    } catch (Throwable $exception) {
                        $resource->update(['metadata' => array_merge($resource->metadata ?? [], [
                            'performance_insights_error' => mb_substr($exception->getMessage(), 0, 1000),
                        ])]);
                    }
                }
            }
        }
    }

    private function syncRdsMetrics(CloudWatchClient $cloudWatch, AwsResource $resource, int $interval): void
    {
        $period = $interval === 1 ? 60 : max(300, $interval * 60);
        foreach (self::RDS_METRICS as $metricName => [$statistic, $unit]) {
            $result = $cloudWatch->getMetricStatistics([
                'Namespace' => 'AWS/RDS',
                'MetricName' => $metricName,
                'Dimensions' => [['Name' => 'DBInstanceIdentifier', 'Value' => $resource->resource_id]],
                'StartTime' => now()->subMinutes(max(15, $interval * 3)),
                'EndTime' => now(),
                'Period' => $period,
                'Statistics' => [$statistic],
            ]);
            foreach ($result['Datapoints'] ?? [] as $point) {
                AwsMetricSample::query()->updateOrCreate(
                    [
                        'aws_resource_id' => $resource->id,
                        'namespace' => 'AWS/RDS',
                        'metric_name' => $metricName,
                        'sampled_at' => $point['Timestamp'],
                    ],
                    ['unit' => $point['Unit'] ?? $unit, 'value' => (float) ($point[$statistic] ?? 0)],
                );
            }
        }
    }

    private function syncDatabaseQueries(PIClient $client, AwsResource $resource, string $identifier): void
    {
        $available = $client->listAvailableResourceMetrics([
            'ServiceType' => 'RDS',
            'Identifier' => $identifier,
            'MetricTypes' => ['db.sql_tokenized.stats'],
            'MaxResults' => 25,
        ])['Metrics'] ?? [];
        $latency = collect($available)->first(fn (array $metric): bool => str_contains(strtolower($metric['Metric'] ?? ''), 'latency'));
        $calls = collect($available)->first(fn (array $metric): bool => str_contains(strtolower($metric['Metric'] ?? ''), 'calls_per'));
        $additional = collect([$latency, $calls])->filter()->map(function (array $metric): string {
            return str_ends_with($metric['Metric'], '.avg') ? $metric['Metric'] : $metric['Metric'].'.avg';
        })->values()->all();
        $windowEndedAt = now()->startOfMinute();
        $windowStartedAt = $windowEndedAt->copy()->subMinutes(15);
        $parameters = [
            'ServiceType' => 'RDS',
            'Identifier' => $identifier,
            'StartTime' => $windowStartedAt,
            'EndTime' => $windowEndedAt,
            'Metric' => 'db.load.avg',
            'GroupBy' => ['Group' => 'db.sql_tokenized', 'Dimensions' => ['db.sql_tokenized.id', 'db.sql_tokenized.statement'], 'Limit' => 10],
            'MaxResults' => 10,
        ];
        if ($additional !== []) {
            $parameters['AdditionalMetrics'] = $additional;
        }
        $result = $client->describeDimensionKeys($parameters);

        foreach ($result['Keys'] ?? [] as $key) {
            $dimensions = $key['Dimensions'] ?? [];
            $additionalValues = $key['AdditionalMetrics'] ?? [];
            $latencyMetric = $latency ? (str_ends_with($latency['Metric'], '.avg') ? $latency['Metric'] : $latency['Metric'].'.avg') : null;
            $callsMetric = $calls ? (str_ends_with($calls['Metric'], '.avg') ? $calls['Metric'] : $calls['Metric'].'.avg') : null;
            $latencyValue = $latencyMetric ? ($additionalValues[$latencyMetric] ?? null) : null;
            AwsDatabaseQuerySample::query()->updateOrCreate(
                [
                    'aws_resource_id' => $resource->id,
                    'query_id' => $dimensions['db.sql_tokenized.id'] ?? hash('sha256', $dimensions['db.sql_tokenized.statement'] ?? 'unknown'),
                    'window_ended_at' => $windowEndedAt,
                ],
                [
                    'query_text' => $dimensions['db.sql_tokenized.statement'] ?? null,
                    'db_load' => (float) ($key['Total'] ?? 0),
                    'average_latency_ms' => $latencyValue === null ? null : $this->latencyMilliseconds((float) $latencyValue, (string) ($latency['Unit'] ?? '')),
                    'calls_per_second' => $callsMetric ? (float) ($additionalValues[$callsMetric] ?? 0) : null,
                    'window_started_at' => $windowStartedAt,
                ],
            );
        }
    }

    private function latencyMilliseconds(float $value, string $unit): float
    {
        $normalized = strtolower($unit);
        if (str_contains($normalized, 'micro')) {
            return $value / 1000;
        }
        if (str_contains($normalized, 'nano')) {
            return $value / 1_000_000;
        }
        if (str_contains($normalized, 'second') && ! str_contains($normalized, 'millisecond')) {
            return $value * 1000;
        }

        return $value;
    }

    private function syncVolumes(Ec2Client $ec2, AwsConnection $connection, string $accountId, string $region, array $instanceIds): void
    {
        if ($instanceIds === []) {
            return;
        }

        foreach ($ec2->getPaginator('DescribeVolumes', [
            'Filters' => [['Name' => 'attachment.instance-id', 'Values' => array_values(array_unique($instanceIds))]],
        ]) as $page) {
            foreach ($page['Volumes'] ?? [] as $volume) {
                $volumeId = (string) $volume['VolumeId'];
                $attachment = collect($volume['Attachments'] ?? [])->first();
                $tags = collect($volume['Tags'] ?? [])->pluck('Value', 'Key')->all();
                AwsResource::query()->updateOrCreate(
                    ['aws_connection_id' => $connection->id, 'resource_id' => $volumeId, 'region' => $region],
                    [
                        'arn' => "arn:aws:ec2:{$region}:{$accountId}:volume/{$volumeId}",
                        'name' => $tags['Name'] ?? $volumeId,
                        'service' => 'ec2',
                        'type' => 'volume',
                        'state' => $volume['State'] ?? null,
                        'availability_zone' => $volume['AvailabilityZone'] ?? null,
                        'tags' => $tags,
                        'metadata' => [
                            'instance_id' => $attachment['InstanceId'] ?? null,
                            'device' => $attachment['Device'] ?? null,
                            'attachment_state' => $attachment['State'] ?? null,
                            'delete_on_termination' => $attachment['DeleteOnTermination'] ?? null,
                            'size_gib' => $volume['Size'] ?? null,
                            'volume_type' => $volume['VolumeType'] ?? null,
                            'iops' => $volume['Iops'] ?? null,
                            'throughput_mibps' => $volume['Throughput'] ?? null,
                            'encrypted' => $volume['Encrypted'] ?? false,
                            'snapshot_id' => $volume['SnapshotId'] ?? null,
                        ],
                        'last_seen_at' => now(),
                    ],
                );
            }
        }
    }

    private function syncMetrics(CloudWatchClient $cloudWatch, AwsResource $resource, int $interval): void
    {
        $period = $interval === 1 ? 60 : max(300, $interval * 60);
        foreach (self::EC2_METRICS as $metricName => [$statistic, $unit]) {
            $result = $cloudWatch->getMetricStatistics([
                'Namespace' => 'AWS/EC2',
                'MetricName' => $metricName,
                'Dimensions' => [['Name' => 'InstanceId', 'Value' => $resource->resource_id]],
                'StartTime' => now()->subMinutes(max(15, $interval * 3)),
                'EndTime' => now(),
                'Period' => $period,
                'Statistics' => [$statistic],
            ]);

            foreach ($result['Datapoints'] ?? [] as $point) {
                AwsMetricSample::query()->updateOrCreate(
                    [
                        'aws_resource_id' => $resource->id,
                        'namespace' => 'AWS/EC2',
                        'metric_name' => $metricName,
                        'sampled_at' => $point['Timestamp'],
                    ],
                    ['unit' => $point['Unit'] ?? $unit, 'value' => (float) ($point[$statistic] ?? 0)],
                );
            }
        }
    }

    private function syncCloudWatchAgentMetrics(CloudWatchClient $cloudWatch, AwsResource $resource, int $interval): void
    {
        $definitions = Cache::remember(
            'aws:cwagent-metrics:'.$resource->aws_connection_id.':'.$resource->region.':'.$resource->resource_id,
            now()->addMinutes(30),
            function () use ($cloudWatch, $resource): array {
                $metrics = [];
                foreach ($cloudWatch->getPaginator('ListMetrics', [
                    'Namespace' => 'CWAgent',
                    'Dimensions' => [['Name' => 'InstanceId', 'Value' => $resource->resource_id]],
                ]) as $page) {
                    foreach ($page['Metrics'] ?? [] as $metric) {
                        if (! isset(self::CW_AGENT_METRICS[$metric['MetricName'] ?? ''])) {
                            continue;
                        }
                        $dimensions = collect($metric['Dimensions'] ?? [])
                            ->mapWithKeys(fn (array $dimension): array => [(string) $dimension['Name'] => (string) $dimension['Value']])
                            ->sortKeys()
                            ->all();
                        $metrics[] = ['name' => $metric['MetricName'], 'dimensions' => $dimensions];
                    }
                }

                return collect($metrics)->unique(fn (array $metric): string => $metric['name'].'|'.json_encode($metric['dimensions']))->values()->all();
            },
        );

        $period = $interval === 1 ? 60 : max(300, $interval * 60);
        foreach ($definitions as $definition) {
            [$statistic, $unit] = self::CW_AGENT_METRICS[$definition['name']];
            $result = $cloudWatch->getMetricStatistics([
                'Namespace' => 'CWAgent',
                'MetricName' => $definition['name'],
                'Dimensions' => collect($definition['dimensions'])
                    ->map(fn (string $value, string $name): array => ['Name' => $name, 'Value' => $value])
                    ->values()
                    ->all(),
                'StartTime' => now()->subMinutes(max(15, $interval * 3)),
                'EndTime' => now(),
                'Period' => $period,
                'Statistics' => [$statistic],
            ]);
            $dimensionsHash = hash('sha256', json_encode($definition['dimensions'], JSON_THROW_ON_ERROR));

            foreach ($result['Datapoints'] ?? [] as $point) {
                AwsMetricSample::query()->updateOrCreate(
                    [
                        'aws_resource_id' => $resource->id,
                        'namespace' => 'CWAgent',
                        'metric_name' => $definition['name'],
                        'dimensions_hash' => $dimensionsHash,
                        'sampled_at' => $point['Timestamp'],
                    ],
                    [
                        'dimensions' => $definition['dimensions'],
                        'unit' => $point['Unit'] ?? $unit,
                        'value' => (float) ($point[$statistic] ?? 0),
                    ],
                );
            }
        }
    }
}
