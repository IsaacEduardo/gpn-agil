<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequisicaoPassagem extends Model
{
    use HasFactory;

    protected $table = 'requisicao_passagens';

    protected $fillable = [
        'requisicao_id',
        'beneficiario_nome',
        'destino',
        'ida_volta',
        'data_partida',
        'data_regresso',
    ];

    protected $casts = [
        'ida_volta' => 'boolean',
        'data_partida' => 'date',
        'data_regresso' => 'date',
    ];

    public function requisicao()
    {
        return $this->belongsTo(Requisicao::class);
    }
}
