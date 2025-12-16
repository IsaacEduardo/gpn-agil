<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequisicaoServico extends Model
{
    use HasFactory;

    protected $table = 'requisicao_servicos';

    protected $fillable = [
        'requisicao_id',
        'tipo_servico',
        'local',
        'prioridade',
        'data_hora_desejada',
        'descricao',
    ];

    protected $casts = [
        'data_hora_desejada' => 'datetime',
    ];

    public function requisicao()
    {
        return $this->belongsTo(Requisicao::class);
    }
}
