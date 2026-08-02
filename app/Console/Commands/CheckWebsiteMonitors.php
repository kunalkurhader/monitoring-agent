<?php

namespace App\Console\Commands;

use App\Models\WebsiteMonitor;
use App\Services\WebsiteMonitorChecker;
use Illuminate\Console\Command;
use Throwable;

class CheckWebsiteMonitors extends Command
{
    protected $signature = 'monitors:check {--id= : Check only one website monitor}';

    protected $description = 'Check active website monitors and send uptime and SSL alerts';

    public function handle(WebsiteMonitorChecker $checker): int
    {
        $query = WebsiteMonitor::query()->where('is_active', true);
        if ($this->option('id')) {
            $query->whereKey($this->option('id'));
        }

        $failures = 0;
        $query->orderBy('id')->each(function (WebsiteMonitor $monitor) use ($checker, &$failures): void {
            try {
                $checker->check($monitor);
                $this->line("Checked {$monitor->name}");
            } catch (Throwable $exception) {
                $failures++;
                report($exception);
                $this->error("{$monitor->name}: {$exception->getMessage()}");
            }
        });

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
