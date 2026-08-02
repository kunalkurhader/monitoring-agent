<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aws_connections', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('role_arn', 2048)->unique();
            $table->text('external_id');
            $table->json('regions')->nullable();
            $table->unsignedSmallInteger('poll_interval_minutes')->default(5);
            $table->boolean('is_active')->default(true);
            $table->string('account_id', 32)->nullable();
            $table->string('status')->default('pending');
            $table->text('last_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('aws_resources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('aws_connection_id')->constrained()->cascadeOnDelete();
            $table->string('arn', 2048);
            $table->string('resource_id', 255);
            $table->string('name')->nullable();
            $table->string('service', 64);
            $table->string('type', 128);
            $table->string('region', 64);
            $table->string('state', 64)->nullable();
            $table->string('instance_type', 128)->nullable();
            $table->string('availability_zone', 128)->nullable();
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['aws_connection_id', 'resource_id', 'region'], 'aws_resource_identity');
            $table->index(['service', 'type', 'state']);
        });

        Schema::create('aws_metric_samples', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('aws_resource_id')->constrained()->cascadeOnDelete();
            $table->string('namespace', 128);
            $table->string('metric_name', 128);
            $table->string('unit', 64)->nullable();
            $table->double('value');
            $table->timestamp('sampled_at');
            $table->timestamps();
            $table->unique(['aws_resource_id', 'namespace', 'metric_name', 'sampled_at'], 'aws_metric_identity');
            $table->index(['aws_resource_id', 'sampled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aws_metric_samples');
        Schema::dropIfExists('aws_resources');
        Schema::dropIfExists('aws_connections');
    }
};
