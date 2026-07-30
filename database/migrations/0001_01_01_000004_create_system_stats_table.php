<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_stats', function (Blueprint $table) {
            $table->id();
            $table->double('cpu_usage')->nullable();
            $table->bigInteger('total_memory')->nullable();
            $table->bigInteger('free_memory')->nullable();
            $table->dateTime('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_stats');
    }
};
