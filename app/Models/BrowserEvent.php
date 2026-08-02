<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['browser_project_id', 'page_view_id', 'event_type', 'page_url', 'message', 'source', 'line_number', 'column_number', 'metrics', 'user_agent', 'occurred_at'])]
class BrowserEvent extends Model
{
    protected function casts(): array
    {
        return ['metrics' => 'array', 'occurred_at' => 'datetime'];
    }
}
