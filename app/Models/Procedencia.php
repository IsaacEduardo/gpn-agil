<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Procedencia extends Model
{
    use HasFactory;

    protected $table = 'procedencias';

    protected $fillable = [
        'nome',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function documentosEntradas()
    {
        return $this->hasMany(DocumentoEntrada::class, 'procedencia_id');
    }
}
