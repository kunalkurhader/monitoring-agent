<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aws_metric_samples', function (Blueprint $table): void {
            $table->dropUnique('aws_metric_identity');
            $table->string('dimensions_hash', 64)->default('none')->after('metric_name');
            $table->json('dimensions')->nullable()->after('dimensions_hash');
            $table->unique(
                ['aws_resource_id', 'namespace', 'metric_name', 'dimensions_hash', 'sampled_at'],
                'aws_metric_dimension_identity',
            );
        });
    }

    public function down(): void
    {
        Schema::table('aws_metric_samples', function (Blueprint $table): void {
            $table->dropUnique('aws_metric_dimension_identity');
            $table->dropColumn(['dimensions_hash', 'dimensions']);
            $table->unique(
                ['aws_resource_id', 'namespace', 'metric_name', 'sampled_at'],
                'aws_metric_identity',
            );
        });
    }
};
