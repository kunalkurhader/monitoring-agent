<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('browser_events', function (Blueprint $table): void {
            $table->uuid('page_view_id')->nullable()->after('browser_project_id');
            $table->index(['browser_project_id', 'page_view_id'], 'browser_events_project_view_index');
        });
    }

    public function down(): void
    {
        Schema::table('browser_events', function (Blueprint $table): void {
            $table->dropIndex('browser_events_project_view_index');
            $table->dropColumn('page_view_id');
        });
    }
};
