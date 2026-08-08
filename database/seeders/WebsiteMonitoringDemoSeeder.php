<?php

namespace Database\Seeders;

use App\Models\WebsiteMonitor;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class WebsiteMonitoringDemoSeeder extends Seeder
{
    public function run(): void
    {
        $now = CarbonImmutable::now()->startOfSecond();

        $monitors = [
            [
                'name' => 'Customer Portal',
                'url' => 'https://portal.demo.test',
                'is_active' => true,
                'check_ssl' => true,
                'is_up' => true,
                'last_status_code' => 200,
                'last_response_ms' => 184,
                'last_error' => null,
                'last_checked_at' => $now->subSeconds(18),
                'ssl_expires_at' => $now->addDays(76),
                'ssl_checked_at' => $now->subSeconds(18),
            ],
            [
                'name' => 'Payments API',
                'url' => 'https://payments.demo.test/health',
                'is_active' => true,
                'check_ssl' => true,
                'is_up' => false,
                'last_status_code' => 503,
                'last_response_ms' => 932,
                'last_error' => 'The upstream service returned HTTP 503.',
                'last_checked_at' => $now->subSeconds(41),
                'outage_started_at' => $now->subMinutes(17),
                'ssl_expires_at' => $now->addDays(12),
                'ssl_checked_at' => $now->subSeconds(41),
            ],
            [
                'name' => 'Internal Status Page',
                'url' => 'https://status.demo.test',
                'is_active' => true,
                'check_ssl' => false,
                'is_up' => true,
                'last_status_code' => 200,
                'last_response_ms' => 76,
                'last_error' => null,
                'last_checked_at' => $now->subMinute(),
                'ssl_expires_at' => null,
                'ssl_checked_at' => null,
            ],
            [
                'name' => 'Legacy Health Endpoint',
                'url' => 'http://legacy.demo.test/health',
                'is_active' => false,
                'check_ssl' => true,
                'is_up' => null,
                'last_status_code' => null,
                'last_response_ms' => null,
                'last_error' => null,
                'last_checked_at' => null,
                'ssl_expires_at' => null,
                'ssl_checked_at' => null,
            ],
        ];

        foreach ($monitors as $attributes) {
            $monitor = WebsiteMonitor::query()->updateOrCreate(
                ['url' => $attributes['url']],
                array_merge([
                    'alert_email' => 'demo-ops@monitoring-agent.local',
                    'outage_started_at' => null,
                    'outage_notified_at' => null,
                ], $attributes),
            );

            $monitor->alerts()->delete();
        }
    }
}
