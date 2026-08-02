<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_jar_is_publicly_downloadable(): void
    {
        $this->get(route('agent.download'))
            ->assertOk()
            ->assertDownload('agent.jar');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/dashboard/data')->assertRedirect(route('login'));
        $this->get('/monitors')->assertRedirect(route('login'));
    }

    public function test_administrator_can_login_and_view_dashboard(): void
    {
        $user = User::factory()->create(['password' => 'correct-password', 'is_admin' => true]);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'correct-password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Health summary across all monitoring agents')
            ->assertSee('Live Monitor')
            ->assertSee('Install Agent')
            ->assertSee(route('agents.install'));
    }

    public function test_admin_can_generate_agent_token_and_member_cannot(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = User::factory()->create(['is_admin' => false]);

        $this->actingAs($admin)->get(route('agents.install'))->assertOk()->assertSee('Generate 64-character token');
        $response = $this->actingAs($admin)->postJson(route('agents.tokens.store'), ['name' => 'Production agents'])
            ->assertCreated();
        $token = $response->json('token');

        $this->assertGreaterThanOrEqual(56, strlen($token));
        $this->assertDatabaseHas('agent_api_tokens', [
            'name' => 'Production agents',
            'token_hash' => hash('sha256', $token),
        ]);
        $this->assertDatabaseMissing('agent_api_tokens', ['token_hash' => $token]);

        $this->actingAs($member)->get(route('agents.install'))->assertForbidden();
        $this->actingAs($member)->postJson(route('agents.tokens.store'), ['name' => 'Denied'])->assertForbidden();
    }

    public function test_live_monitor_data_returns_agent_telemetry(): void
    {
        $user = User::factory()->create();
        $agent = Agent::query()->create(['id' => 'b7ebc999-12e0-4d91-8e2b-d42b4166d0f2', 'hostname' => 'web-01', 'last_seen_at' => now()]);
        $sampledAt = now()->startOfSecond();
        DB::table('system_stats')->insert(['agent_id' => $agent->id, 'cpu_usage' => 25, 'total_memory' => 16000, 'free_memory' => 6000, 'created_at' => $sampledAt]);
        DB::table('process_stats')->insert(['agent_id' => $agent->id, 'pid' => 123, 'process_name' => 'java', 'cpu_usage' => 10, 'memory_bytes' => 1000, 'start_time' => 1, 'created_at' => $sampledAt]);
        DB::table('disk_stats')->insert(['agent_id' => $agent->id, 'mount_point' => '/', 'total_bytes' => 1000, 'free_bytes' => 400, 'used_bytes' => 600, 'created_at' => $sampledAt]);

        $this->actingAs($user)->getJson(route('monitors.data', ['agent_id' => $agent->id, 'range' => 1]))
            ->assertOk()->assertJsonPath('agent.hostname', 'web-01')
            ->assertJsonPath('current.cpu', 25)->assertJsonPath('current.used_memory', 10000)
            ->assertJsonPath('processes.0.process_name', 'java')->assertJsonPath('disks.0.used_bytes', 600)
            ->assertJsonPath('process_heatmap.rows.0.name', 'java');
    }

    public function test_process_snapshot_can_be_loaded_for_a_past_time(): void
    {
        $user = User::factory()->create();
        $agent = Agent::query()->create(['id' => 'a7ebc999-12e0-4d91-8e2b-d42b4166d0f2', 'hostname' => 'worker-01']);
        $sampledAt = now()->subHour()->startOfSecond();
        DB::table('process_stats')->insert(['agent_id' => $agent->id, 'pid' => 456, 'process_name' => 'queue-worker', 'command' => 'php artisan queue:work', 'user_name' => 'app', 'cpu_usage' => 12, 'memory_bytes' => 2048, 'state' => 'RUNNING', 'start_time' => 1000, 'created_at' => $sampledAt]);

        $this->actingAs($user)->getJson(route('monitors.processes', ['agent_id' => $agent->id, 'at' => $sampledAt->addSecond()->toIso8601String()]))
            ->assertOk()->assertJsonPath('processes.0.process_name', 'queue-worker')
            ->assertJsonPath('processes.0.command', 'php artisan queue:work')
            ->assertJsonPath('processes.0.user_name', 'app');
    }

    public function test_user_can_view_and_update_profile(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()->assertSee($user->email);
        $this->actingAs($user)->patch(route('profile.update'), ['name' => 'New Name'])
            ->assertRedirect()->assertSessionHas('status');

        $this->assertSame('New Name', $user->fresh()->name);
    }

    public function test_storage_snapshot_can_be_loaded_for_a_past_time(): void
    {
        $user = User::factory()->create();
        $agent = Agent::query()->create(['id' => 'c7ebc999-12e0-4d91-8e2b-d42b4166d0f2', 'hostname' => 'storage-01']);
        $sampledAt = now()->subHour()->startOfSecond();
        DB::table('disk_stats')->insert(['agent_id' => $agent->id, 'device' => '/dev/sda1', 'mount_point' => '/', 'file_system_type' => 'ext4', 'total_bytes' => 1000, 'free_bytes' => 250, 'used_bytes' => 750, 'created_at' => $sampledAt]);

        $this->actingAs($user)->getJson(route('monitors.storage', ['agent_id' => $agent->id, 'at' => $sampledAt->addSecond()->toIso8601String()]))
            ->assertOk()->assertJsonPath('disks.0.mount_point', '/')
            ->assertJsonPath('disks.0.used_bytes', 750);
    }

    public function test_live_monitor_handles_registered_agent_without_samples(): void
    {
        $user = User::factory()->create();
        $agent = Agent::query()->create(['id' => 'd7ebc999-12e0-4d91-8e2b-d42b4166d0f2', 'hostname' => 'empty-01']);

        $this->actingAs($user)->get(route('monitors.index'))->assertOk()->assertSee('No monitoring data found');
        $this->actingAs($user)->getJson(route('monitors.data', ['agent_id' => $agent->id, 'range' => 1]))
            ->assertOk()->assertJsonCount(0, 'series');
    }

    public function test_fleet_dashboard_summarizes_warnings_and_errors(): void
    {
        $user = User::factory()->create();
        $agent = Agent::query()->create(['id' => 'e7ebc999-12e0-4d91-8e2b-d42b4166d0f2', 'hostname' => 'critical-01', 'last_seen_at' => now()]);
        DB::table('system_stats')->insert(['agent_id' => $agent->id, 'cpu_usage' => 95, 'total_memory' => 1000, 'free_memory' => 100, 'created_at' => now()]);

        $this->actingAs($user)->getJson(route('dashboard.data'))
            ->assertOk()->assertJsonPath('summary.total', 1)
            ->assertJsonPath('summary.errors', 1)
            ->assertJsonPath('monitors.0.status', 'error');
    }
}
