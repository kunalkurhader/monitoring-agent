<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_monitors', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('url');
            $table->string('alert_email');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_up')->nullable();
            $table->unsignedSmallInteger('last_status_code')->nullable();
            $table->unsignedInteger('last_response_ms')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('outage_started_at')->nullable();
            $table->timestamp('outage_notified_at')->nullable();
            $table->timestamp('ssl_expires_at')->nullable();
            $table->timestamp('ssl_checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('website_monitor_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_monitor_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('alert_key');
            $table->timestamp('sent_at');
            $table->timestamps();
            $table->unique(['website_monitor_id', 'type', 'alert_key'], 'website_monitor_alert_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_monitor_alerts');
        Schema::dropIfExists('website_monitors');
    }
};
