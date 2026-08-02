<?php

namespace App\Console\Commands;

use App\Models\AwsConnection;
use App\Services\AwsCloudSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncAwsCloud extends Command
{
    protected $signature = 'cloud:sync {--force : Synchronize even when the polling interval has not elapsed}';

    protected $description = 'Synchronize AWS resource inventory and CloudWatch metrics';

    public function handle(AwsCloudSyncService $sync): int
    {
        $failed = false;
        AwsConnection::query()->where('is_active', true)->each(function (AwsConnection $connection) use ($sync, &$failed): void {
            if (! $this->option('force') && ! $connection->isDue()) {
                return;
            }

            try {
                $sync->sync($connection);
                $this->info("Synchronized {$connection->name}.");
            } catch (Throwable $exception) {
                $failed = true;
                $this->error("{$connection->name}: {$exception->getMessage()}");
            }
        });

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
