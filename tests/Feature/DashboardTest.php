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

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/dashboard/data')->assertRedirect(route('login'));
    }

    public function test_administrator_can_login_and_view_dashboard(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'correct-password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->get(route('dashboard'))->assertOk()->assertSee('Infrastructure overview');
    }

    public function test_dashboard_data_returns_agent_telemetry(): void
    {
        $user = User::factory()->create();
        $agent = Agent::query()->create(['id' => 'b7ebc999-12e0-4d91-8e2b-d42b4166d0f2', 'hostname' => 'web-01', 'last_seen_at' => now()]);
        $sampledAt = now()->startOfSecond();
        DB::table('system_stats')->insert(['agent_id' => $agent->id, 'cpu_usage' => 25, 'total_memory' => 16000, 'free_memory' => 6000, 'created_at' => $sampledAt]);
        DB::table('process_stats')->insert(['agent_id' => $agent->id, 'pid' => 123, 'process_name' => 'java', 'cpu_usage' => 10, 'memory_bytes' => 1000, 'start_time' => 1, 'created_at' => $sampledAt]);
        DB::table('disk_stats')->insert(['agent_id' => $agent->id, 'mount_point' => '/', 'total_bytes' => 1000, 'free_bytes' => 400, 'used_bytes' => 600, 'created_at' => $sampledAt]);

        $this->actingAs($user)->getJson(route('dashboard.data', ['agent_id' => $agent->id, 'range' => 1]))
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

        $this->actingAs($user)->getJson(route('dashboard.processes', ['agent_id' => $agent->id, 'at' => $sampledAt->addSecond()->toIso8601String()]))
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

        $this->actingAs($user)->getJson(route('dashboard.storage', ['agent_id' => $agent->id, 'at' => $sampledAt->addSecond()->toIso8601String()]))
            ->assertOk()->assertJsonPath('disks.0.mount_point', '/')
            ->assertJsonPath('disks.0.used_bytes', 750);
    }
}
