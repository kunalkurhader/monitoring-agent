<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'url', 'alert_email', 'is_active', 'check_ssl', 'is_up', 'last_status_code', 'last_response_ms', 'last_error', 'last_checked_at', 'outage_started_at', 'outage_notified_at', 'ssl_expires_at', 'ssl_checked_at'])]
class WebsiteMonitor extends Model
{
    protected $attributes = [
        'check_ssl' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'check_ssl' => 'boolean',
            'is_up' => 'boolean',
            'last_checked_at' => 'datetime',
            'outage_started_at' => 'datetime',
            'outage_notified_at' => 'datetime',
            'ssl_expires_at' => 'datetime',
            'ssl_checked_at' => 'datetime',
        ];
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(WebsiteMonitorAlert::class);
    }
}
