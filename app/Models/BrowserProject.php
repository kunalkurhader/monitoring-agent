<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'site_url', 'allowed_origin', 'public_key', 'created_by', 'is_active'])]
class BrowserProject extends Model
{
    public function events(): HasMany
    {
        return $this->hasMany(BrowserEvent::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
