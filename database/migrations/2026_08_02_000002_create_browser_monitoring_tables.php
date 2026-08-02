<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('browser_projects', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('site_url');
            $table->string('allowed_origin');
            $table->string('public_key', 80)->unique();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('browser_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('browser_project_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 40);
            $table->text('page_url');
            $table->text('message')->nullable();
            $table->text('source')->nullable();
            $table->unsignedInteger('line_number')->nullable();
            $table->unsignedInteger('column_number')->nullable();
            $table->json('metrics')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['browser_project_id', 'occurred_at'], 'browser_events_project_time_index');
            $table->index(['browser_project_id', 'event_type'], 'browser_events_project_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('browser_events');
        Schema::dropIfExists('browser_projects');
    }
};
