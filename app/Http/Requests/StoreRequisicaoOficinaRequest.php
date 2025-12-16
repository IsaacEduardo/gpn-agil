<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequisicaoOficinaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'viatura_id' => 'required|exists:viaturas,id',
            'descricao_problema' => 'required|string',
            'tipo_manutencao' => 'required|string|in:Preventiva,Corretiva,Revisão',
            'prioridade' => 'required|string|in:Baixa,Média,Alta,Urgente',
            'quilometragem_atual' => 'nullable|integer|min:0',
            'servicos_solicitados' => 'nullable|string',
            'observacoes' => 'nullable|string',
            'empresa_id' => 'required|exists:empresas,id',
            'data_requisicao' => 'nullable|date',
        ];
    }
}
