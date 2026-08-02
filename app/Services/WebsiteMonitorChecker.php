<?php

namespace App\Services;

use App\Mail\WebsiteMonitorAlertMail;
use App\Models\WebsiteMonitor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Throwable;

class WebsiteMonitorChecker
{
    public function __construct(private readonly SslCertificateInspector $ssl) {}

    public function check(WebsiteMonitor $monitor): void
    {
        $previouslyUp = $monitor->is_up;
        $started = hrtime(true);
        $status = null;
        $error = null;

        try {
            $response = Http::timeout(15)
                ->connectTimeout(8)
                ->withUserAgent(config('app.name', 'Monitoring Agent').'/UptimeMonitor')
                ->get($monitor->url);
            $status = $response->status();
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        $isUp = $status === 200;
        $monitor->update([
            'is_up' => $isUp,
            'last_status_code' => $status,
            'last_response_ms' => max(1, (int) round((hrtime(true) - $started) / 1_000_000)),
            'last_error' => $error,
            'last_checked_at' => now(),
            'outage_started_at' => $isUp ? null : ($monitor->outage_started_at ?? now()),
        ]);

        if (! $isUp && $monitor->outage_notified_at === null) {
            $this->send($monitor, 'Website unavailable', 'uptime_down', [
                'summary' => $status ? "The website returned HTTP {$status} instead of HTTP 200." : "The website could not be reached: {$error}",
            ]);
            $monitor->update(['outage_notified_at' => now()]);
        } elseif ($isUp && $previouslyUp === false && $monitor->outage_notified_at !== null) {
            $this->send($monitor, 'Website recovered', 'uptime_recovered', [
                'summary' => 'The website is responding with HTTP 200 again.',
            ]);
            $monitor->update(['outage_notified_at' => null]);
        } elseif ($isUp && $monitor->outage_notified_at !== null) {
            $monitor->update(['outage_notified_at' => null]);
        }

        if (strtolower((string) parse_url($monitor->url, PHP_URL_SCHEME)) === 'https') {
            $this->checkCertificate($monitor);
        } else {
            $monitor->update(['ssl_expires_at' => null, 'ssl_checked_at' => null]);
        }
    }

    private function checkCertificate(WebsiteMonitor $monitor): void
    {
        try {
            $expiry = $this->ssl->expiresAt($monitor->url);
            $monitor->update(['ssl_expires_at' => $expiry, 'ssl_checked_at' => now()]);
            $days = (int) now()->startOfDay()->diffInDays($expiry->startOfDay(), false);
            $threshold = match (true) {
                $days <= 0 => 0,
                $days <= 7 => 7,
                $days <= 15 => 15,
                $days <= 30 => 30,
                default => null,
            };

            if ($threshold === null) {
                return;
            }

            $alertKey = $expiry->format('Y-m-d').':'.$threshold;
            if ($monitor->alerts()->where('type', 'ssl_expiry')->where('alert_key', $alertKey)->exists()) {
                return;
            }

            $summary = $days > 0
                ? "The TLS certificate expires in {$days} day".($days === 1 ? '' : 's').'.'
                : ($days === 0 ? 'The TLS certificate expires today.' : 'The TLS certificate has expired.');
            $this->send($monitor, 'SSL certificate expiry warning', 'ssl_expiry', compact('summary', 'expiry', 'days'));
            $monitor->alerts()->create(['type' => 'ssl_expiry', 'alert_key' => $alertKey, 'sent_at' => now()]);
        } catch (Throwable $exception) {
            $monitor->update(['ssl_checked_at' => now(), 'last_error' => trim(($monitor->last_error ? $monitor->last_error.' ' : '').'SSL: '.$exception->getMessage())]);
        }
    }

    private function send(WebsiteMonitor $monitor, string $subject, string $type, array $details): void
    {
        Mail::to($monitor->alert_email)->send(new WebsiteMonitorAlertMail($monitor, $type, $details, $subject));
    }
}
