<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['aws_resource_id', 'query_id', 'query_text', 'db_load', 'average_latency_ms', 'calls_per_second', 'window_started_at', 'window_ended_at'])]
class AwsDatabaseQuerySample extends Model
{
    protected function casts(): array
    {
        return [
            'db_load' => 'float',
            'average_latency_ms' => 'float',
            'calls_per_second' => 'float',
            'window_started_at' => 'datetime',
            'window_ended_at' => 'datetime',
        ];
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(AwsResource::class, 'aws_resource_id');
    }
}
