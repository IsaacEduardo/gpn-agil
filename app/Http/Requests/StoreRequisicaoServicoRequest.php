<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequisicaoServicoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'empresa_id' => 'required|exists:empresas,id',
            'observacoes' => 'nullable|string',
            'tipo_servico' => 'required|string|max:255',
            'descricao_servico' => 'required|string',
            'local_execucao' => 'required|string|max:255',
            'data_prevista' => 'required|date',
            'prioridade' => 'required|in:baixa,media,alta,urgente',
        ];
    }
}
