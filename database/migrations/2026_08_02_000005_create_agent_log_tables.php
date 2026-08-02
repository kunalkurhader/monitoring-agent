<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_log_files', function (Blueprint $table): void {
            $table->id();
            $table->uuid('agent_id');
            $table->string('path_hash', 64);
            $table->text('path');
            $table->string('name');
            $table->string('file_key')->nullable();
            $table->unsignedBigInteger('last_offset')->default(0);
            $table->string('status', 30)->default('pending');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->foreign('agent_id')->references('id')->on('agents')->cascadeOnDelete();
            $table->unique(['agent_id', 'path_hash'], 'agent_log_files_agent_path_unique');
        });

        Schema::create('agent_log_chunks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_log_file_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('start_offset');
            $table->unsignedBigInteger('end_offset');
            $table->unsignedInteger('line_count')->default(0);
            $table->longText('content');
            $table->timestamp('captured_at');
            $table->timestamps();
            $table->unique(['agent_log_file_id', 'start_offset', 'end_offset'], 'agent_log_chunks_offset_unique');
            $table->index(['agent_log_file_id', 'captured_at'], 'agent_log_chunks_file_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_log_chunks');
        Schema::dropIfExists('agent_log_files');
    }
};
