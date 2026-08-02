<?php

namespace App\Console\Commands;

use App\Services\AgentJarBuilder;
use Illuminate\Console\Command;

class CleanupAgentBuilds extends Command
{
    protected $signature = 'agents:cleanup-builds';

    protected $description = 'Delete expired temporary Java agent builds';

    public function handle(AgentJarBuilder $builder): int
    {
        $this->info('Deleted '.$builder->deleteExpired().' expired agent build(s).');

        return self::SUCCESS;
    }
}
