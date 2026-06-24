<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModeloDespacho extends Model
{
    use HasFactory;

    protected $fillable = [
        'titulo',
        'texto',
        'user_id',
        'ativo',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }

    public function scopeGlobalOrUser($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->whereNull('user_id')->orWhere('user_id', $userId);
        });
    }
}
