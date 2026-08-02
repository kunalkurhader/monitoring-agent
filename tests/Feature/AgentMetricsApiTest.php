<?php

namespace Tests\Feature;

use App\Models\AgentApiToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentMetricsApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-agent-token';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.agent.token' => self::TOKEN]);
    }

    public function test_agent_api_requires_a_valid_bearer_token(): void
    {
        $this->getJson('/api/v1/agent/ping')->assertUnauthorized();

        $this->withToken('wrong-token')
            ->getJson('/api/v1/agent/ping')
            ->assertUnauthorized();

        $this->withToken(self::TOKEN)
            ->getJson('/api/v1/agent/ping')
            ->assertOk()
            ->assertJson(['status' => 'ok']);
    }

    public function test_agent_api_accepts_a_generated_database_token(): void
    {
        $plainTextToken = str_repeat('a', 64);
        AgentApiToken::query()->create([
            'name' => 'Test token',
            'token_hash' => hash('sha256', $plainTextToken),
        ]);

        $this->withToken($plainTextToken)
            ->getJson('/api/v1/agent/ping')
            ->assertOk()
            ->assertJson(['status' => 'ok']);
    }

    public function test_agent_can_submit_system_and_process_metrics(): void
    {
        $agentId = 'b7ebc999-12e0-4d91-8e2b-d42b4166d0f2';

        $response = $this->withToken(self::TOKEN)->postJson('/api/v1/agent/metrics', [
            'agent_id' => $agentId,
            'hostname' => 'web-01',
            'system' => [
                'cpu_usage' => 31.5,
                'total_memory' => 17179869184,
                'free_memory' => 8589934592,
            ],
            'processes' => [[
                'pid' => 1234,
                'process_name' => 'java',
                'command' => 'java -jar agent.jar',
                'user_name' => 'monitoring',
                'cpu_usage' => 4.25,
                'memory_bytes' => 104857600,
                'state' => 'RUNNING',
                'start_time' => 1785411000000,
            ]],
        ]);

        $response->assertAccepted()->assertJson([
            'status' => 'accepted',
            'processes_received' => 1,
        ]);

        $this->assertDatabaseHas('agents', [
            'id' => $agentId,
            'hostname' => 'web-01',
        ]);
        $this->assertDatabaseHas('system_stats', [
            'agent_id' => $agentId,
            'total_memory' => 17179869184,
        ]);
        $this->assertDatabaseHas('process_stats', [
            'agent_id' => $agentId,
            'pid' => 1234,
            'process_name' => 'java',
        ]);
    }

    public function test_invalid_metrics_are_rejected(): void
    {
        $this->withToken(self::TOKEN)
            ->postJson('/api/v1/agent/metrics', [
                'agent_id' => 'not-a-uuid',
                'hostname' => '',
                'system' => [],
                'processes' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['agent_id', 'hostname', 'system.cpu_usage']);
    }

    public function test_agent_can_submit_disk_metrics(): void
    {
        $agentId = 'b7ebc999-12e0-4d91-8e2b-d42b4166d0f2';

        $this->withToken(self::TOKEN)->postJson('/api/v1/agent/disks', [
            'agent_id' => $agentId,
            'hostname' => 'web-01',
            'disks' => [[
                'device' => '/dev/nvme0n1p1',
                'mount_point' => '/',
                'file_system_type' => 'ext4',
                'total_bytes' => 1000000000,
                'free_bytes' => 400000000,
                'used_bytes' => 600000000,
            ]],
        ])->assertAccepted()->assertJson([
            'status' => 'accepted',
            'disks_received' => 1,
        ]);

        $this->assertDatabaseHas('agents', [
            'id' => $agentId,
            'hostname' => 'web-01',
        ]);
        $this->assertDatabaseHas('disk_stats', [
            'agent_id' => $agentId,
            'mount_point' => '/',
            'total_bytes' => 1000000000,
            'free_bytes' => 400000000,
            'used_bytes' => 600000000,
        ]);
    }
}
