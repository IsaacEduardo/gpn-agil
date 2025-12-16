<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequisicaoProduto extends Model
{
    use HasFactory;

    protected $table = 'requisicao_produtos';

    protected $fillable = [
        'requisicao_id',
        'nome_produto',
        'quantidade',
        'unidade_medida',
        'prioridade',
        'finalidade',
    ];

    public function requisicao()
    {
        return $this->belongsTo(Requisicao::class);
    }
}
