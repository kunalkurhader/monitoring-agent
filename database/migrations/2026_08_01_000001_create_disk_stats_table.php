<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disk_stats', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('agent_id')->index()->constrained()->cascadeOnDelete();
            $table->string('device')->nullable();
            $table->string('mount_point');
            $table->string('file_system_type')->nullable();
            $table->unsignedBigInteger('total_bytes');
            $table->unsignedBigInteger('free_bytes');
            $table->unsignedBigInteger('used_bytes');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disk_stats');
    }
};
