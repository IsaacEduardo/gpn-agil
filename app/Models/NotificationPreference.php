<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Preferência de subscrição de um utilizador para uma categoria/canal de notificação.
 */
class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'category',
        'channel',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
