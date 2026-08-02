<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['aws_connection_id', 'finding_key', 'category', 'severity', 'confidence', 'title', 'recommendation', 'resource_id', 'resource_arn', 'resource_type', 'region', 'evidence', 'status', 'first_seen_at', 'last_seen_at', 'resolved_at'])]
class AwsOptimizationFinding extends Model
{
    protected function casts(): array
    {
        return ['evidence' => 'array', 'first_seen_at' => 'datetime', 'last_seen_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(AwsConnection::class, 'aws_connection_id');
    }
}
