<?php

namespace Tests\Feature;

use App\Models\AwsConnection;
use App\Models\AwsMetricSample;
use App\Models\AwsOptimizationFinding;
use App\Models\AwsResource;
use App\Models\User;
use App\Services\AwsOptimizationAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AwsCloudMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_an_encrypted_aws_role_and_polling_interval(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $externalId = 'e7516a42-3ef0-4301-a9c1-ca01346fbe1c';

        $this->actingAs($admin)->patch(route('settings.cloud.update'), [
            'name' => 'Production AWS',
            'role_arn' => 'arn:aws:iam::123456789012:role/MonitoringAgentRole',
            'external_id' => $externalId,
            'regions' => 'ap-south-1, us-east-1',
            'poll_interval_minutes' => 5,
            'is_active' => '1',
        ])->assertRedirect(route('settings.index').'#cloud');

        $connection = AwsConnection::query()->firstOrFail();
        $this->assertSame($externalId, $connection->external_id);
        $this->assertNotSame($externalId, DB::table('aws_connections')->value('external_id'));
        $this->assertSame(['ap-south-1', 'us-east-1'], $connection->regions);
        $this->assertSame(5, $connection->poll_interval_minutes);
        $this->assertTrue($connection->isDue());
    }

    public function test_cloud_settings_reject_an_invalid_role_arn_or_interval(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->patch(route('settings.cloud.update'), [
            'name' => 'Bad AWS', 'role_arn' => 'not-an-arn', 'external_id' => 'external-value',
            'regions' => 'ap-south-1', 'poll_interval_minutes' => 3, 'is_active' => '1',
        ])->assertSessionHasErrors(['role_arn', 'poll_interval_minutes'], null, 'cloud');
    }

    public function test_cloud_overview_shows_counts_without_combined_graphs(): void
    {
        $user = User::factory()->create();
        $connection = $this->connection();
        $this->createAwsInstance($connection, 'i-running', 'running');
        $this->createAwsInstance($connection, 'i-stopped', 'stopped');

        $this->actingAs($user)->get(route('cloud.index'))->assertOk()
            ->assertSee('All resources')->assertSee('EC2 instances')->assertSee('Running')->assertSee('Stopped')
            ->assertDontSee('<canvas', false);
    }

    public function test_cloud_listing_shows_basic_metrics_security_context_and_s3_inventory(): void
    {
        $user = User::factory()->create();
        $connection = $this->connection();
        $instance = $this->createAwsInstance($connection, 'i-listing', 'running');
        $instance->update(['metadata' => [
            'private_ip' => '10.0.0.10', 'public_ip' => '203.0.113.20',
            'security_groups' => [['id' => 'sg-web', 'name' => 'web-public']],
        ]]);
        AwsResource::query()->create([
            'aws_connection_id' => $connection->id, 'arn' => 'arn:aws:ec2:ap-south-1:123456789012:security-group/sg-web',
            'resource_id' => 'sg-web', 'name' => 'web-public', 'service' => 'ec2', 'type' => 'security-group',
            'region' => 'ap-south-1', 'state' => 'active', 'metadata' => ['ingress_rules' => []],
        ]);
        AwsMetricSample::query()->create([
            'aws_resource_id' => $instance->id, 'namespace' => 'AWS/EC2', 'metric_name' => 'CPUUtilization',
            'unit' => 'Percent', 'value' => 42.5, 'sampled_at' => now()->subMinute(),
        ]);
        AwsResource::query()->create([
            'aws_connection_id' => $connection->id, 'arn' => 'arn:aws:s3:::public-assets',
            'resource_id' => 'public-assets', 'name' => 'public-assets', 'service' => 's3', 'type' => 'bucket',
            'region' => 'ap-south-1', 'state' => 'public',
            'metadata' => [
                'object_count' => 12345, 'total_size_bytes' => 5368709120, 'is_public' => true,
                'access_status' => 'public', 'all_public_access_blocked' => false,
                'public_access_block_enabled_count' => 0, 'public_access_block_inspection_complete' => true,
            ],
        ]);

        $this->actingAs($user)->get(route('cloud.index'))->assertOk()
            ->assertSee('42.5%')->assertSee('web-public')->assertSee('public-assets')
            ->assertSee('12,345')->assertSee('5.0 GiB')->assertSee('0/4 enabled');
    }

    public function test_cloud_inventory_can_filter_by_service_region_state_exposure_and_search(): void
    {
        $user = User::factory()->create();
        $connection = $this->connection();
        $this->createAwsInstance($connection, 'i-private-app', 'running');
        AwsResource::query()->create([
            'aws_connection_id' => $connection->id, 'arn' => 'arn:aws:s3:::public-assets',
            'resource_id' => 'public-assets', 'name' => 'public-assets', 'service' => 's3', 'type' => 'bucket',
            'region' => 'us-east-1', 'state' => 'public',
            'metadata' => ['object_count' => 500, 'is_public' => true, 'all_public_access_blocked' => false],
        ]);

        $this->actingAs($user)->get(route('cloud.index', [
            'service' => 's3', 'region' => 'us-east-1', 'state' => 'public',
            'exposure' => 'public', 'q' => 'assets',
        ]))->assertOk()->assertSee('public-assets')->assertDontSee('i-private-app')
            ->assertSee('1 matching EC2, RDS, and S3 resources')->assertSee('Clear filters');

        $this->actingAs($user)->get(route('cloud.index', ['service' => 'ec2', 'exposure' => 'private']))
            ->assertOk()->assertSee('i-private-app')->assertDontSee('public-assets');
    }

    public function test_instance_dashboard_returns_per_instance_metric_series(): void
    {
        $user = User::factory()->create();
        $resource = $this->createAwsInstance($this->connection(), 'i-0123456789', 'running');
        AwsResource::query()->create([
            'aws_connection_id' => $resource->aws_connection_id,
            'arn' => 'arn:aws:ec2:ap-south-1:123456789012:volume/vol-0123456789',
            'resource_id' => 'vol-0123456789', 'name' => 'orders-root', 'service' => 'ec2', 'type' => 'volume',
            'region' => 'ap-south-1', 'state' => 'in-use',
            'metadata' => ['instance_id' => $resource->resource_id, 'device' => '/dev/xvda', 'size_gib' => 100, 'volume_type' => 'gp3', 'encrypted' => true],
        ]);
        $sampledAt = now()->subMinutes(5)->startOfSecond();
        foreach (['CPUUtilization' => 42.5, 'NetworkIn' => 2048, 'NetworkOut' => 1024, 'DiskReadBytes' => 500, 'StatusCheckFailed' => 0] as $name => $value) {
            AwsMetricSample::query()->create([
                'aws_resource_id' => $resource->id, 'namespace' => 'AWS/EC2', 'metric_name' => $name,
                'unit' => $name === 'CPUUtilization' ? 'Percent' : 'Bytes', 'value' => $value, 'sampled_at' => $sampledAt,
            ]);
        }
        $dimensions = ['InstanceId' => $resource->resource_id, 'device' => 'nvme0n1p1', 'fstype' => 'xfs', 'path' => '/'];
        $dimensionsHash = hash('sha256', json_encode($dimensions));
        foreach (['disk_total' => 1000, 'disk_used' => 600, 'disk_free' => 400, 'disk_used_percent' => 60, 'disk_inodes_free' => 5000] as $name => $value) {
            AwsMetricSample::query()->create([
                'aws_resource_id' => $resource->id, 'namespace' => 'CWAgent', 'metric_name' => $name,
                'dimensions_hash' => $dimensionsHash, 'dimensions' => $dimensions, 'unit' => 'Bytes',
                'value' => $value, 'sampled_at' => $sampledAt,
            ]);
        }
        AwsMetricSample::query()->create([
            'aws_resource_id' => $resource->id, 'namespace' => 'CWAgent', 'metric_name' => 'mem_used_percent',
            'dimensions_hash' => hash('sha256', $resource->resource_id), 'dimensions' => ['InstanceId' => $resource->resource_id],
            'unit' => 'Percent', 'value' => 71.2, 'sampled_at' => $sampledAt,
        ]);

        $this->actingAs($user)->getJson(route('cloud.instances.data', ['resource_id' => $resource->id, 'range' => 1]))
            ->assertOk()->assertJsonPath('resource.resource_id', 'i-0123456789')
            ->assertJsonPath('series.0.cpu', 42.5)->assertJsonPath('series.0.network_in', 2048)
            ->assertJsonPath('series.0.status_failed', 0)
            ->assertJsonPath('volumes.0.resource_id', 'vol-0123456789')
            ->assertJsonPath('volumes.0.metadata.size_gib', 100)
            ->assertJsonPath('filesystems.0.path', '/')
            ->assertJsonPath('filesystems.0.free_bytes', 400)
            ->assertJsonPath('filesystems.0.used_percent', 60)
            ->assertJsonPath('memory_used_percent', 71.2);
    }

    public function test_rds_dashboard_returns_health_metrics_and_query_insights(): void
    {
        $user = User::factory()->create();
        $connection = $this->connection();
        $database = AwsResource::query()->create([
            'aws_connection_id' => $connection->id,
            'arn' => 'arn:aws:rds:ap-south-1:123456789012:db:orders-primary',
            'resource_id' => 'orders-primary', 'name' => 'orders-primary', 'service' => 'rds', 'type' => 'db-instance',
            'region' => 'ap-south-1', 'state' => 'available', 'instance_type' => 'db.r7g.large',
            'metadata' => ['engine' => 'postgres', 'engine_version' => '15.5', 'allocated_storage_gib' => 500, 'performance_insights_enabled' => true],
        ]);
        $sampledAt = now()->subMinutes(5)->startOfSecond();
        foreach (['CPUUtilization' => 35, 'FreeableMemory' => 8589934592, 'FreeStorageSpace' => 322122547200, 'DatabaseConnections' => 74, 'ReadLatency' => .004, 'WriteLatency' => .009, 'ReadIOPS' => 450, 'WriteIOPS' => 260] as $name => $value) {
            AwsMetricSample::query()->create(['aws_resource_id' => $database->id, 'namespace' => 'AWS/RDS', 'metric_name' => $name, 'unit' => 'Count', 'value' => $value, 'sampled_at' => $sampledAt]);
        }
        DB::table('aws_database_query_samples')->insert([
            'aws_resource_id' => $database->id, 'query_id' => 'sql-orders', 'query_text' => 'SELECT * FROM orders WHERE customer_id = $1',
            'db_load' => 2.4, 'average_latency_ms' => 18.5, 'calls_per_second' => 32.1,
            'window_started_at' => now()->subMinutes(20), 'window_ended_at' => now()->subMinutes(5), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($user)->get(route('cloud.index'))->assertOk()->assertSee('orders-primary')->assertSee('RDS databases');
        $this->actingAs($user)->getJson(route('cloud.databases.data', ['resource_id' => $database->id, 'range' => 1]))
            ->assertOk()->assertJsonPath('series.0.cpu', 35)->assertJsonPath('series.0.read_latency_ms', 4)
            ->assertJsonPath('queries.0.query_id', 'sql-orders')->assertJsonPath('queries.0.average_latency_ms', 18.5);
    }

    public function test_advisor_finds_public_security_group_and_unused_elastic_ip(): void
    {
        $user = User::factory()->create();
        $connection = $this->connection();
        AwsResource::query()->create([
            'aws_connection_id' => $connection->id, 'arn' => 'arn:aws:ec2:ap-south-1:123456789012:security-group/sg-risky',
            'resource_id' => 'sg-risky', 'name' => 'admin-open', 'service' => 'ec2', 'type' => 'security-group', 'region' => 'ap-south-1', 'state' => 'active',
            'metadata' => ['group_name' => 'admin-open', 'network_interface_ids' => ['eni-public'], 'ingress_rules' => [['protocol' => 'tcp', 'from_port' => 22, 'to_port' => 22, 'ipv4_ranges' => ['0.0.0.0/0'], 'ipv6_ranges' => []]]],
        ]);
        AwsResource::query()->create([
            'aws_connection_id' => $connection->id, 'arn' => 'arn:aws:ec2:ap-south-1:123456789012:elastic-ip/eipalloc-public',
            'resource_id' => 'eipalloc-public', 'name' => '203.0.113.10', 'service' => 'ec2', 'type' => 'elastic-ip', 'region' => 'ap-south-1', 'state' => 'associated',
            'metadata' => ['public_ip' => '203.0.113.10', 'association_id' => 'eipassoc-public', 'network_interface_id' => 'eni-public'],
        ]);
        AwsResource::query()->create([
            'aws_connection_id' => $connection->id, 'arn' => 'arn:aws:ec2:ap-south-1:123456789012:elastic-ip/eipalloc-unused',
            'resource_id' => 'eipalloc-unused', 'name' => '203.0.113.11', 'service' => 'ec2', 'type' => 'elastic-ip', 'region' => 'ap-south-1', 'state' => 'unassociated',
            'metadata' => ['public_ip' => '203.0.113.11', 'association_id' => null],
        ]);

        $this->app->make(AwsOptimizationAnalyzer::class)->analyze($connection);

        $this->assertDatabaseHas('aws_optimization_findings', ['resource_id' => 'sg-risky', 'severity' => 'critical', 'confidence' => 'high', 'status' => 'active']);
        $this->assertDatabaseHas('aws_optimization_findings', ['resource_id' => 'eipalloc-unused', 'category' => 'elastic-ip', 'status' => 'active']);
        $this->actingAs($user)->get(route('cloud.recommendations'))->assertOk()->assertSee('admin-open')->assertSee('203.0.113.11');

        AwsResource::query()->where('resource_id', 'sg-risky')->update(['metadata' => ['group_name' => 'admin-open', 'network_interface_ids' => ['eni-public'], 'ingress_rules' => []]]);
        $this->app->make(AwsOptimizationAnalyzer::class)->analyze($connection);
        $this->assertSame('resolved', AwsOptimizationFinding::query()->where('resource_id', 'sg-risky')->firstOrFail()->status);
    }

    public function test_advisor_links_public_rules_to_ec2_rds_and_s3_resources(): void
    {
        $connection = $this->connection();
        AwsResource::query()->create([
            'aws_connection_id' => $connection->id, 'arn' => 'arn:aws:ec2:ap-south-1:123456789012:security-group/sg-public',
            'resource_id' => 'sg-public', 'name' => 'public-admin-and-db', 'service' => 'ec2', 'type' => 'security-group',
            'region' => 'ap-south-1', 'state' => 'active', 'metadata' => [
                'group_name' => 'public-admin-and-db', 'network_interface_ids' => ['eni-public'],
                'ingress_rules' => [
                    ['protocol' => 'tcp', 'from_port' => 22, 'to_port' => 22, 'ipv4_ranges' => ['0.0.0.0/0'], 'ipv6_ranges' => []],
                    ['protocol' => 'tcp', 'from_port' => 5432, 'to_port' => 5432, 'ipv4_ranges' => ['0.0.0.0/0'], 'ipv6_ranges' => []],
                ],
            ],
        ]);
        AwsResource::query()->create([
            'aws_connection_id' => $connection->id, 'arn' => 'arn:aws:ec2:ap-south-1:123456789012:instance/i-public',
            'resource_id' => 'i-public', 'name' => 'public-bastion', 'service' => 'ec2', 'type' => 'instance',
            'region' => 'ap-south-1', 'state' => 'running', 'metadata' => [
                'public_ip' => '203.0.113.10', 'network_interface_ids' => ['eni-public'],
                'security_groups' => [['id' => 'sg-public', 'name' => 'public-admin-and-db']],
            ],
        ]);
        AwsResource::query()->create([
            'aws_connection_id' => $connection->id, 'arn' => 'arn:aws:rds:ap-south-1:123456789012:db:public-db',
            'resource_id' => 'public-db', 'name' => 'public-db', 'service' => 'rds', 'type' => 'db-instance',
            'region' => 'ap-south-1', 'state' => 'available', 'metadata' => [
                'engine' => 'postgres', 'endpoint' => 'public-db.example', 'port' => 5432, 'publicly_accessible' => true,
                'security_groups' => [['id' => 'sg-public', 'status' => 'active']],
            ],
        ]);
        AwsResource::query()->create([
            'aws_connection_id' => $connection->id, 'arn' => 'arn:aws:s3:::public-assets',
            'resource_id' => 'public-assets', 'name' => 'public-assets', 'service' => 's3', 'type' => 'bucket',
            'region' => 'ap-south-1', 'state' => 'public', 'metadata' => [
                'is_public' => true, 'policy_is_public' => true, 'acl_is_public' => false,
                'public_access_block' => ['BlockPublicPolicy' => false], 'object_count' => 250,
            ],
        ]);

        $this->app->make(AwsOptimizationAnalyzer::class)->analyze($connection);

        $this->assertDatabaseHas('aws_optimization_findings', ['resource_id' => 'i-public', 'category' => 'ec2-exposure', 'severity' => 'critical']);
        $this->assertDatabaseHas('aws_optimization_findings', ['resource_id' => 'public-db', 'category' => 'rds-exposure', 'severity' => 'critical']);
        $this->assertDatabaseHas('aws_optimization_findings', ['resource_id' => 'public-assets', 'category' => 's3', 'severity' => 'critical']);
    }

    private function connection(): AwsConnection
    {
        return AwsConnection::query()->create([
            'name' => 'AWS Production', 'role_arn' => 'arn:aws:iam::123456789012:role/MonitoringAgentRole',
            'external_id' => 'test-external-id', 'regions' => ['ap-south-1'], 'poll_interval_minutes' => 5,
            'is_active' => true,
        ]);
    }

    private function createAwsInstance(AwsConnection $connection, string $id, string $state): AwsResource
    {
        return AwsResource::query()->create([
            'aws_connection_id' => $connection->id, 'arn' => "arn:aws:ec2:ap-south-1:123456789012:instance/{$id}",
            'resource_id' => $id, 'name' => $id, 'service' => 'ec2', 'type' => 'instance', 'region' => 'ap-south-1',
            'state' => $state, 'instance_type' => 't3.small', 'metadata' => ['private_ip' => '10.0.0.10'],
        ]);
    }
}
