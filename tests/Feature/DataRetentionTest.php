<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\DataRetentionSetting;
use App\Models\User;
use App\Models\WebsiteMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DataRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_the_retention_period(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->patch(route('settings.retention.update'), [
            'retention_days' => 45,
        ])->assertRedirect(route('settings.index').'#data-retention');

        $this->assertSame(45, DataRetentionSetting::query()->firstOrFail()->retention_days);
    }

    public function test_prune_command_deletes_only_expired_monitoring_records(): void
    {
        DataRetentionSetting::query()->create(['retention_days' => 30]);
        $agent = Agent::query()->create([
            'id' => 'b7ebc999-12e0-4d91-8e2b-d42b4166d0f2',
            'hostname' => 'retention-test',
        ]);
        DB::table('system_stats')->insert([
            ['agent_id' => $agent->id, 'cpu_usage' => 10, 'total_memory' => 100, 'free_memory' => 50, 'created_at' => now()->subDays(31)],
            ['agent_id' => $agent->id, 'cpu_usage' => 20, 'total_memory' => 100, 'free_memory' => 40, 'created_at' => now()->subDays(29)],
        ]);
        $monitor = WebsiteMonitor::query()->create([
            'name' => 'Retention website',
            'url' => 'https://example.com',
            'alert_email' => 'ops@example.com',
            'is_active' => true,
        ]);
        $monitor->alerts()->create(['type' => 'ssl_expiry', 'alert_key' => 'old', 'sent_at' => now()->subDays(31)]);
        $monitor->alerts()->create(['type' => 'ssl_expiry', 'alert_key' => 'new', 'sent_at' => now()->subDays(29)]);

        $this->artisan('data:prune')->assertSuccessful();

        $this->assertDatabaseCount('system_stats', 1);
        $this->assertDatabaseHas('system_stats', ['cpu_usage' => 20]);
        $this->assertDatabaseCount('website_monitor_alerts', 1);
        $this->assertDatabaseHas('website_monitor_alerts', ['alert_key' => 'new']);
        $this->assertNotNull(DataRetentionSetting::query()->firstOrFail()->last_pruned_at);
        $this->assertDatabaseHas('website_monitors', ['id' => $monitor->id]);
        $this->assertDatabaseHas('agents', ['id' => $agent->id]);
    }
}
