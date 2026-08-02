<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['aws_connection_id', 'arn', 'resource_id', 'name', 'service', 'type', 'region', 'state', 'instance_type', 'availability_zone', 'tags', 'metadata', 'last_seen_at'])]
class AwsResource extends Model
{
    protected function casts(): array
    {
        return ['tags' => 'array', 'metadata' => 'array', 'last_seen_at' => 'datetime'];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(AwsConnection::class, 'aws_connection_id');
    }

    public function metricSamples(): HasMany
    {
        return $this->hasMany(AwsMetricSample::class);
    }

    public function databaseQuerySamples(): HasMany
    {
        return $this->hasMany(AwsDatabaseQuerySample::class);
    }
}
