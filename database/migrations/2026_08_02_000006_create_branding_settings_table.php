<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branding_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('site_name');
            $table->string('logo_path')->nullable();
            $table->string('logo_mime', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branding_settings');
    }
};
