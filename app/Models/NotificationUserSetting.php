<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Definições gerais de notificação de um utilizador (quiet hours + digest).
 */
class NotificationUserSetting extends Model
{
    protected $fillable = [
        'user_id',
        'quiet_hours_enabled',
        'quiet_start',
        'quiet_end',
        'timezone',
        'digest_enabled',
    ];

    protected $casts = [
        'quiet_hours_enabled' => 'boolean',
        'digest_enabled' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
