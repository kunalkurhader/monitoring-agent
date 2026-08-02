<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['website_monitor_id', 'type', 'alert_key', 'sent_at'])]
class WebsiteMonitorAlert extends Model
{
    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(WebsiteMonitor::class, 'website_monitor_id');
    }
}
