<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('monitors:check')->everyMinute()->withoutOverlapping();
Schedule::command('agents:cleanup-builds')->everyMinute()->withoutOverlapping();
Schedule::command('data:prune')->hourly()->withoutOverlapping();
