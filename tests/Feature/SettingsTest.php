<?php

namespace Tests\Feature;

use App\Models\AgentBuildArtifact;
use App\Models\BrandingSetting;
use App\Models\MailSetting;
use App\Models\User;
use App\Services\AgentJarBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_request_a_temporary_agent_build(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $artifact = new AgentBuildArtifact([
            'path' => 'agent-builds/fresh.jar',
            'size_bytes' => 1024,
            'expires_at' => now()->addMinutes(10),
        ]);
        $builder = Mockery::mock(AgentJarBuilder::class);
        $builder->shouldReceive('build')->once()->andReturn([
            'artifact' => $artifact,
            'token' => str_repeat('x', 64),
        ]);
        $this->app->instance(AgentJarBuilder::class, $builder);

        $this->actingAs($admin)->postJson(route('agents.builds.store'))
            ->assertCreated()
            ->assertJsonPath('size_bytes', 1024)
            ->assertJsonPath('message', 'Fresh agent.jar built. This download expires in 10 minutes.');
    }

    public function test_expired_agent_builds_are_deleted(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('agent-builds/expired.jar', 'expired');
        AgentBuildArtifact::query()->create([
            'token_hash' => hash('sha256', 'expired-token'),
            'path' => 'agent-builds/expired.jar',
            'size_bytes' => 7,
            'expires_at' => now()->subSecond(),
        ]);

        $this->artisan('agents:cleanup-builds')->assertSuccessful();

        Storage::disk('local')->assertMissing('agent-builds/expired.jar');
        $this->assertDatabaseCount('agent_build_artifacts', 0);
    }

    public function test_admin_can_customize_site_name_and_logo(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->patch(route('settings.branding.update'), [
            'site_name' => 'Acme Observability',
            'logo' => UploadedFile::fake()->image('acme-logo.png', 320, 120),
        ])->assertRedirect(route('settings.index').'#branding');

        $branding = BrandingSetting::query()->firstOrFail();
        $this->assertSame('Acme Observability', $branding->site_name);
        Storage::disk('local')->assertExists($branding->logo_path);

        $this->get(route('branding.logo'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_branding_rejects_unsupported_logo_files(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->patch(route('settings.branding.update'), [
            'site_name' => 'Acme Observability',
            'logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
        ])->assertSessionHasErrors('logo', null, 'branding');

        $this->assertDatabaseCount('branding_settings', 0);
    }

    public function test_admin_can_save_encrypted_smtp_settings(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->patch(route('settings.mail.update'), [
            'host' => 'smtp.example.com', 'port' => 587, 'scheme' => null,
            'username' => 'mailer@example.com', 'password' => 'smtp-secret-value',
            'from_address' => 'monitoring@example.com', 'from_name' => 'Monitoring Agent Alerts', 'is_enabled' => '1',
        ])->assertRedirect(route('settings.index').'#email-delivery');

        $setting = MailSetting::query()->firstOrFail();
        $this->assertSame('smtp-secret-value', $setting->password);
        $this->assertNotSame('smtp-secret-value', DB::table('mail_settings')->value('password'));
        $this->actingAs($admin)->get(route('settings.index'))->assertOk()
            ->assertSee('Install Server Agent')->assertSee('Install Browser Agent')->assertSee('Email Delivery');
    }

    public function test_blank_password_preserves_existing_encrypted_password(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $setting = MailSetting::query()->create([
            'host' => 'smtp.old.test', 'port' => 465, 'scheme' => 'smtps', 'username' => 'old', 'password' => 'keep-this-secret',
            'from_address' => 'old@example.com', 'from_name' => 'Old sender', 'is_enabled' => true,
        ]);

        $this->actingAs($admin)->patch(route('settings.mail.update'), [
            'host' => 'smtp.new.test', 'port' => 587, 'scheme' => 'smtp', 'username' => 'new', 'password' => '',
            'from_address' => 'new@example.com', 'from_name' => 'New sender', 'is_enabled' => '1',
        ])->assertRedirect();

        $this->assertSame('keep-this-secret', $setting->fresh()->password);
    }
}
