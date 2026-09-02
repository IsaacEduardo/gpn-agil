<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserCertificate extends Model
{
    use HasFactory;

    protected $table = 'user_certificates';

    protected $fillable = [
        'user_id',
        'encrypted_p12',
        'public_key',
        'issuer',
        'valid_from',
        'valid_to',
    ];

    protected $hidden = [
        'encrypted_p12',
    ];

    protected $casts = [
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
        'encrypted_p12' => 'encrypted', // Uses Laravel's built-in encryption casting
        // A senha do P12 NÃO é persistida: é fornecida pelo utilizador a cada assinatura.
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isValid(): bool
    {
        return now()->between($this->valid_from, $this->valid_to);
    }
}
