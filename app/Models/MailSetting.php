<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['host', 'port', 'scheme', 'username', 'password', 'from_address', 'from_name', 'is_enabled'])]
class MailSetting extends Model
{
    protected function casts(): array
    {
        return ['password' => 'encrypted', 'is_enabled' => 'boolean', 'port' => 'integer'];
    }
}
