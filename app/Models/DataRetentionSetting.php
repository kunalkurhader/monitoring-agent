<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['retention_days', 'last_pruned_at'])]
class DataRetentionSetting extends Model
{
    protected function casts(): array
    {
        return [
            'retention_days' => 'integer',
            'last_pruned_at' => 'datetime',
        ];
    }
}
