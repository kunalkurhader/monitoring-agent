<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentBuildArtifact;
use App\Models\BrowserProject;
use App\Models\User;
use App\Models\WebsiteMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_temporary_agent_jar_is_downloadable_with_an_unexpired_token(): void
    {
        Storage::fake('local');
        $token = str_repeat('a', 64);
        $path = 'agent-builds/test.jar';
        Storage::disk('local')->put($path, 'temporary jar');
        AgentBuildArtifact::query()->create([
            'token_hash' => hash('sha256', $token),
            'path' => $path,
            'size_bytes' => 13,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->get(route('agent.download', $token))
            ->assertOk()
            ->assertDownload('agent.jar');

        $this->get(route('agent.download', str_repeat('b', 64)))->assertNotFound();
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
            ->assertSee('Combined health summary for servers, websites, and browser applications')
            ->assertSee('Server Monitoring')
            ->assertSee('Website Uptime')
            ->assertSee('Browser Monitoring')
            ->assertSee('Settings')
            ->assertSee(route('settings.index'));
    }

    public function test_admin_can_generate_agent_token_and_member_cannot(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = User::factory()->create(['is_admin' => false]);

        $this->actingAs($admin)->get(route('agents.install'))->assertRedirect(route('settings.index').'#server-agent');
        $this->actingAs($admin)->get(route('settings.index'))->assertOk()->assertSee('Generate 64-character token');
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
        $this->actingAs($member)->get(route('settings.index'))->assertForbidden();
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

    public function test_synchronized_logs_can_be_searched_by_file_and_date_time(): void
    {
        $user = User::factory()->create();
        $agent = Agent::query()->create(['id' => 'f7ebc999-12e0-4d91-8e2b-d42b4166d0f2', 'hostname' => 'logs-01']);
        $fileId = DB::table('agent_log_files')->insertGetId([
            'agent_id' => $agent->id, 'path_hash' => hash('sha256', '/var/log/app.log'), 'path' => '/var/log/app.log',
            'name' => 'app.log', 'last_offset' => 50, 'status' => 'ready', 'last_seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('agent_log_chunks')->insert([
            ['agent_log_file_id' => $fileId, 'start_offset' => 0, 'end_offset' => 20, 'line_count' => 1, 'content' => 'old log entry', 'captured_at' => now()->subDays(2), 'created_at' => now(), 'updated_at' => now()],
            ['agent_log_file_id' => $fileId, 'start_offset' => 20, 'end_offset' => 50, 'line_count' => 1, 'content' => 'current searchable error', 'captured_at' => now()->subHour(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($user)->getJson(route('monitors.logs', [
            'agent_id' => $agent->id, 'file_id' => $fileId, 'from' => now()->subHours(2)->toIso8601String(), 'to' => now()->toIso8601String(),
        ]))->assertOk()->assertJsonPath('files.0.path', '/var/log/app.log')
            ->assertJsonPath('chunks.0.content', 'current searchable error')->assertJsonPath('pagination.total', 1);
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

    public function test_fleet_dashboard_includes_browser_monitoring_summary(): void
    {
        $user = User::factory()->create();
        $project = BrowserProject::query()->create([
            'name' => 'Customer portal', 'site_url' => 'https://app.example.com', 'allowed_origin' => 'https://app.example.com',
            'public_key' => 'pw_'.str_repeat('c', 60), 'created_by' => $user->id,
        ]);
        $viewId = 'c61b33c0-42de-4e85-9fe9-7bd9c54618a1';
        DB::table('browser_events')->insert([
            ['browser_project_id' => $project->id, 'page_view_id' => $viewId, 'event_type' => 'page_load', 'page_url' => 'https://app.example.com/orders', 'message' => 'navigate', 'source' => null, 'metrics' => json_encode(['load_time' => 1200]), 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['browser_project_id' => $project->id, 'page_view_id' => $viewId, 'event_type' => 'ajax', 'page_url' => 'https://app.example.com/orders', 'message' => 'GET', 'source' => 'https://app.example.com/api/orders', 'metrics' => json_encode(['duration' => 300, 'status' => 500]), 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['browser_project_id' => $project->id, 'page_view_id' => $viewId, 'event_type' => 'error', 'page_url' => 'https://app.example.com/orders', 'message' => 'Undefined value', 'source' => null, 'metrics' => null, 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($user)->getJson(route('dashboard.data'))
            ->assertOk()->assertJsonPath('browser_summary.total', 1)
            ->assertJsonPath('browser_summary.page_loads', 1)
            ->assertJsonPath('browser_summary.requests', 1)
            ->assertJsonPath('browser_summary.errors', 2)
            ->assertJsonPath('browser_monitors.0.status', 'error')
            ->assertJsonPath('browser_monitors.0.average_load', 1200);
    }

    public function test_fleet_dashboard_includes_website_uptime_summary(): void
    {
        $user = User::factory()->create();
        WebsiteMonitor::query()->create([
            'name' => 'Healthy website', 'url' => 'https://healthy.example.com', 'alert_email' => 'ops@example.com',
            'is_active' => true, 'is_up' => true, 'last_status_code' => 200, 'last_response_ms' => 210,
            'last_checked_at' => now(), 'ssl_expires_at' => now()->addDays(15),
        ]);
        WebsiteMonitor::query()->create([
            'name' => 'Unavailable website', 'url' => 'https://down.example.com', 'alert_email' => 'ops@example.com',
            'is_active' => true, 'is_up' => false, 'last_status_code' => 503, 'last_checked_at' => now(),
        ]);

        $this->actingAs($user)->getJson(route('dashboard.data'))
            ->assertOk()
            ->assertJsonPath('uptime_summary.total', 2)
            ->assertJsonPath('uptime_summary.healthy', 1)
            ->assertJsonPath('uptime_summary.unavailable', 1)
            ->assertJsonPath('uptime_summary.ssl_expiring', 1)
            ->assertJsonPath('uptime_monitors.0.name', 'Healthy website')
            ->assertJsonPath('uptime_monitors.0.status_code', 200);
    }
}
