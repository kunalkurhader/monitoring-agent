<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_stats', function (Blueprint $table): void {
            $table->index(['agent_id', 'created_at'], 'system_stats_agent_time_index');
        });
        Schema::table('process_stats', function (Blueprint $table): void {
            $table->index(['agent_id', 'created_at'], 'process_stats_agent_time_index');
        });
        Schema::table('disk_stats', function (Blueprint $table): void {
            $table->index(['agent_id', 'created_at'], 'disk_stats_agent_time_index');
        });
    }

    public function down(): void
    {
        Schema::table('system_stats', function (Blueprint $table): void {
            $table->dropIndex('system_stats_agent_time_index');
        });
        Schema::table('process_stats', function (Blueprint $table): void {
            $table->dropIndex('process_stats_agent_time_index');
        });
        Schema::table('disk_stats', function (Blueprint $table): void {
            $table->dropIndex('disk_stats_agent_time_index');
        });
    }
};
