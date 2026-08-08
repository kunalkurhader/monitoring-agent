<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebsiteMonitor;
use App\Services\SslCertificateInspector;
use App\Services\WebsiteMonitorChecker;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class WebsiteMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_update_and_delete_a_website_monitor(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user)->post(route('website-monitors.store'), [
            'name' => 'Public website',
            'url' => 'https://example.com/health',
            'alert_email' => 'ops@example.com',
            'is_active' => '1',
            'check_ssl' => '1',
        ])->assertRedirect(route('settings.index').'#uptime-monitoring');

        $monitor = WebsiteMonitor::query()->firstOrFail();
        $this->assertTrue($monitor->is_active);
        $this->assertTrue($monitor->check_ssl);
        $this->actingAs($user)->put(route('website-monitors.update', $monitor), [
            'name' => 'Public website updated',
            'url' => 'https://example.com',
            'alert_email' => 'alerts@example.com',
            'is_active' => '0',
            'check_ssl' => '0',
        ])->assertRedirect(route('website-monitors.index'));
        $this->assertFalse($monitor->fresh()->is_active);
        $this->assertFalse($monitor->fresh()->check_ssl);

        $this->actingAs($user)->delete(route('website-monitors.destroy', $monitor))->assertRedirect();
        $this->assertDatabaseCount('website_monitors', 0);
    }

    public function test_uptime_alert_is_sent_once_per_outage_and_recovery(): void
    {
        Mail::fake();
        Http::fake(['*' => Http::sequence()
            ->push('Unavailable', 503)
            ->push('Unavailable', 503)
            ->push('OK', 200)]);
        $monitor = WebsiteMonitor::query()->create([
            'name' => 'API', 'url' => 'http://api.example.com', 'alert_email' => 'ops@example.com', 'is_active' => true,
        ]);
        $checker = app(WebsiteMonitorChecker::class);

        $checker->check($monitor);
        $checker->check($monitor->fresh());
        Mail::assertSentCount(1);
        $this->assertFalse($monitor->fresh()->is_up);

        $checker->check($monitor->fresh());
        Mail::assertSentCount(2);
        $this->assertTrue($monitor->fresh()->is_up);
    }

    public function test_administrator_can_run_a_manual_uptime_check(): void
    {
        Mail::fake();
        Http::fake(['*' => Http::response('OK', 200)]);
        $admin = User::factory()->create(['is_admin' => true]);
        $monitor = WebsiteMonitor::query()->create([
            'name' => 'Status page', 'url' => 'http://status.example.com', 'alert_email' => 'ops@example.com', 'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('website-monitors.index'))
            ->assertOk()
            ->assertSee(route('website-monitors.check', $monitor))
            ->assertSee('Check now');

        $this->actingAs($admin)->post(route('website-monitors.check', $monitor))
            ->assertRedirect(route('website-monitors.index'))
            ->assertSessionHas('status', 'Manual check complete: Status page is operational.');

        $monitor->refresh();
        $this->assertTrue($monitor->is_up);
        $this->assertSame(200, $monitor->last_status_code);
        $this->assertNotNull($monitor->last_checked_at);
    }

    public function test_member_cannot_run_a_manual_uptime_check(): void
    {
        $member = User::factory()->create(['is_admin' => false]);
        $monitor = WebsiteMonitor::query()->create([
            'name' => 'Status page', 'url' => 'http://status.example.com', 'alert_email' => 'ops@example.com', 'is_active' => true,
        ]);

        $this->actingAs($member)->post(route('website-monitors.check', $monitor))->assertForbidden();
    }

    public function test_ssl_milestone_alert_is_deduplicated(): void
    {
        Mail::fake();
        Http::fake(['*' => Http::response('OK', 200)]);
        $monitor = WebsiteMonitor::query()->create([
            'name' => 'Secure website', 'url' => 'https://secure.example.com', 'alert_email' => 'ops@example.com', 'is_active' => true,
        ]);
        $ssl = Mockery::mock(SslCertificateInspector::class);
        $ssl->shouldReceive('expiresAt')->twice()->andReturn(CarbonImmutable::now()->addDays(30));
        $checker = new WebsiteMonitorChecker($ssl);

        $checker->check($monitor);
        $checker->check($monitor->fresh());

        Mail::assertSentCount(1);
        $this->assertDatabaseCount('website_monitor_alerts', 1);
    }

    public function test_ssl_check_is_skipped_and_existing_ssl_data_is_cleared_when_disabled(): void
    {
        Mail::fake();
        Http::fake(['*' => Http::response('OK', 200)]);
        $monitor = WebsiteMonitor::query()->create([
            'name' => 'Secure website',
            'url' => 'https://secure.example.com',
            'alert_email' => 'ops@example.com',
            'is_active' => true,
            'check_ssl' => false,
            'ssl_expires_at' => now()->addDays(10),
            'ssl_checked_at' => now()->subDay(),
        ]);
        $ssl = Mockery::mock(SslCertificateInspector::class);
        $ssl->shouldNotReceive('expiresAt');

        (new WebsiteMonitorChecker($ssl))->check($monitor);

        $monitor->refresh();
        $this->assertTrue($monitor->is_up);
        $this->assertNull($monitor->ssl_expires_at);
        $this->assertNull($monitor->ssl_checked_at);
        Mail::assertNothingSent();
    }
}
