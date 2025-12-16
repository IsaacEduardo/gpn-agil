<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequisicaoPassagemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'empresa_id' => 'required|exists:empresas,id',
            'beneficiario_nome' => 'required|string|max:255',
            'destino' => 'required|string|max:255',
            'ida_volta' => 'nullable|boolean',
            'data_partida' => 'required|date',
            'data_regresso' => 'nullable|date',
            'observacoes' => 'nullable|string',
        ];
    }
}
