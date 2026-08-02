<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aws_database_query_samples', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('aws_resource_id')->constrained()->cascadeOnDelete();
            $table->string('query_id', 512);
            $table->text('query_text')->nullable();
            $table->double('db_load')->default(0);
            $table->double('average_latency_ms')->nullable();
            $table->double('calls_per_second')->nullable();
            $table->timestamp('window_started_at');
            $table->timestamp('window_ended_at');
            $table->timestamps();
            $table->unique(['aws_resource_id', 'query_id', 'window_ended_at'], 'aws_database_query_identity');
            $table->index(['aws_resource_id', 'window_ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aws_database_query_samples');
    }
};
