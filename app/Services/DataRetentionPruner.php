<?php

namespace App\Services;

use App\Models\DataRetentionSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DataRetentionPruner
{
    public function prune(): array
    {
        if (! Schema::hasTable('data_retention_settings')) {
            return [];
        }

        $setting = DataRetentionSetting::query()->firstOrCreate([], ['retention_days' => 30]);
        $cutoff = now()->subDays($setting->retention_days);
        $tables = [
            'system_stats' => 'created_at',
            'process_stats' => 'created_at',
            'disk_stats' => 'created_at',
            'browser_events' => 'occurred_at',
            'agent_log_chunks' => 'captured_at',
            'website_monitor_alerts' => 'sent_at',
        ];
        $deleted = [];

        foreach ($tables as $table => $column) {
            if (Schema::hasTable($table)) {
                $deleted[$table] = DB::table($table)->where($column, '<', $cutoff)->delete();
            }
        }

        $setting->update(['last_pruned_at' => now()]);

        return $deleted;
    }
}
