<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_stats', function (Blueprint $table) {
            $table->id();
            $table->integer('pid')->nullable();
            $table->string('process_name')->nullable();
            $table->text('command')->nullable();
            $table->string('user_name', 100)->nullable();
            $table->double('cpu_usage')->nullable();
            $table->bigInteger('memory_bytes')->nullable();
            $table->string('state', 50)->nullable();
            $table->bigInteger('start_time')->nullable();
            $table->dateTime('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_stats');
    }
};
