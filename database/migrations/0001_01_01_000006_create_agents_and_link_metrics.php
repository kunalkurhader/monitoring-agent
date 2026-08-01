<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('hostname');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::table('system_stats', function (Blueprint $table) {
            $table->foreignUuid('agent_id')->nullable()->index()->constrained()->cascadeOnDelete();
        });

        Schema::table('process_stats', function (Blueprint $table) {
            $table->foreignUuid('agent_id')->nullable()->index()->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('process_stats', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_id');
        });

        Schema::table('system_stats', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_id');
        });

        Schema::dropIfExists('agents');
    }
};
