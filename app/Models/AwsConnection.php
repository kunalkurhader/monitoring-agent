<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'role_arn', 'external_id', 'regions', 'poll_interval_minutes', 'is_active', 'account_id', 'status', 'last_error', 'last_synced_at'])]
class AwsConnection extends Model
{
    protected function casts(): array
    {
        return [
            'external_id' => 'encrypted',
            'regions' => 'array',
            'poll_interval_minutes' => 'integer',
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function resources(): HasMany
    {
        return $this->hasMany(AwsResource::class);
    }

    public function isDue(): bool
    {
        return ! $this->last_synced_at
            || $this->last_synced_at->lte(now()->subMinutes($this->poll_interval_minutes));
    }
}
