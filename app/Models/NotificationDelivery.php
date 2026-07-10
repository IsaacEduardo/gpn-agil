<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registo de auditoria de entrega de uma notificação num canal específico.
 */
class NotificationDelivery extends Model
{
    public const UPDATED_AT = null; // apenas created_at (registo imutável)

    protected $fillable = [
        'notification_id',
        'notifiable_type',
        'notifiable_id',
        'notification_type',
        'event_type',
        'channel',
        'status',
        'response',
    ];

    public function notifiable()
    {
        return $this->morphTo();
    }
}
