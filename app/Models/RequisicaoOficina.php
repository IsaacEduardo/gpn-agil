<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequisicaoOficina extends Model
{
    use HasFactory;

    protected $table = 'requisicao_oficina';

    protected $fillable = [
        'requisicao_id',
        'viatura_id',
        'tipo_servico',
        'quilometragem_atual',
        'descricao_problema',
        'servicos_solicitados',
        'urgencia',
    ];

    public function requisicao()
    {
        return $this->belongsTo(Requisicao::class);
    }

    public function viatura()
    {
        return $this->belongsTo(Viatura::class);
    }
}
