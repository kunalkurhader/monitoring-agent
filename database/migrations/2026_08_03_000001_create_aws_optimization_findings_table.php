<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aws_optimization_findings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('aws_connection_id')->constrained()->cascadeOnDelete();
            $table->string('finding_key', 128);
            $table->string('category', 64);
            $table->string('severity', 16);
            $table->string('confidence', 16);
            $table->string('title');
            $table->text('recommendation');
            $table->string('resource_id', 255);
            $table->string('resource_arn', 2048)->nullable();
            $table->string('resource_type', 128);
            $table->string('region', 64);
            $table->json('evidence')->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['aws_connection_id', 'finding_key'], 'aws_optimization_finding_identity');
            $table->index(['status', 'severity', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aws_optimization_findings');
    }
};
