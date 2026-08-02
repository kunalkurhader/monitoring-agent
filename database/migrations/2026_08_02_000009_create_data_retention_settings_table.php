<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_retention_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('retention_days')->default(30);
            $table->timestamp('last_pruned_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_retention_settings');
    }
};
