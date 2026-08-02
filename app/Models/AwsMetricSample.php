<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['aws_resource_id', 'namespace', 'metric_name', 'dimensions_hash', 'dimensions', 'unit', 'value', 'sampled_at'])]
class AwsMetricSample extends Model
{
    protected function casts(): array
    {
        return ['dimensions' => 'array', 'value' => 'float', 'sampled_at' => 'datetime'];
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(AwsResource::class, 'aws_resource_id');
    }
}
