<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TermoEntrega extends Model
{
    use HasFactory;

    protected $table = 'termos_entrega';

    protected $fillable = [
        'requisicao_id',
        'tipo',
        'caminho_arquivo',
        'beneficiario_nome',
        'beneficiario_documento',
        'beneficiario_documento_emitido_em',
        'beneficiario_documento_emitido_local',
        'beneficiario_setor',
        'item_descricao',
        'quantidade',
        'unidade',
        'observacoes',
        'viatura_id',
    ];

    // As relações com requisições deixam de ser necessárias para o fluxo autônomo.
}
