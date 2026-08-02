<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['token_hash', 'path', 'size_bytes', 'expires_at', 'last_downloaded_at'])]
class AgentBuildArtifact extends Model
{
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'expires_at' => 'datetime',
            'last_downloaded_at' => 'datetime',
        ];
    }
}
