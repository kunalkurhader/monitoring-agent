<?php

namespace Tests\Feature;

use App\Models\MailSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_encrypted_smtp_settings(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->patch(route('settings.mail.update'), [
            'host' => 'smtp.example.com', 'port' => 587, 'scheme' => null,
            'username' => 'mailer@example.com', 'password' => 'smtp-secret-value',
            'from_address' => 'monitoring@example.com', 'from_name' => 'Pulsewatch Alerts', 'is_enabled' => '1',
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
