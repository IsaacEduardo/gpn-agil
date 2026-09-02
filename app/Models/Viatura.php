<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Viatura extends Model
{
    use HasFactory;

    protected $fillable = [
        'identificacao',
        'placa',
        'modelo',
        'marca',
        'ano',
        'tipo',
        'motor_numero',
        'cor',
        'status_operacional',
        'afetacao',
        'observacoes',
    ];

    public function fotos()
    {
        return $this->hasMany(ViaturaFoto::class);
    }

    public function requisicoes_oficina()
    {
        return $this->hasMany(ViaturaOficina::class);
    }

    public function setPlacaAttribute($value)
    {
        $this->attributes['placa'] = strtoupper($value);
    }
}
