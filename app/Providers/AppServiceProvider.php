<?php

namespace App\Providers;

use App\Models\MailSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        try {
            if (! Schema::hasTable('mail_settings')) {
                return;
            }
            $mail = MailSetting::query()->first();
            if (! $mail?->is_enabled) {
                return;
            }
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $mail->host,
                'mail.mailers.smtp.port' => $mail->port,
                'mail.mailers.smtp.scheme' => $mail->scheme,
                'mail.mailers.smtp.username' => $mail->username,
                'mail.mailers.smtp.password' => $mail->password,
                'mail.from.address' => $mail->from_address,
                'mail.from.name' => $mail->from_name,
            ]);
        } catch (Throwable) {
            // Setup and migrations must remain usable before settings exist.
        }
    }
}
