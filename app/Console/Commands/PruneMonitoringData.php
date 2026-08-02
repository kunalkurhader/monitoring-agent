<?php

namespace App\Console\Commands;

use App\Services\DataRetentionPruner;
use Illuminate\Console\Command;

class PruneMonitoringData extends Command
{
    protected $signature = 'data:prune';

    protected $description = 'Delete monitoring data older than the configured retention period';

    public function handle(DataRetentionPruner $pruner): int
    {
        $deleted = $pruner->prune();
        $this->info('Deleted '.array_sum($deleted).' expired monitoring record(s).');

        return self::SUCCESS;
    }
}
