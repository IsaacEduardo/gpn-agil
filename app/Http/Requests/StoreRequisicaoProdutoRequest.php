<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequisicaoProdutoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $hasMultiple = $this->has('produtos') && is_array($this->input('produtos'));

        if ($hasMultiple) {
            return [
                'empresa_id' => 'required|exists:empresas,id',
                'observacoes' => 'nullable|string',
                'produtos' => 'required|array|min:1',
                'produtos.*.nome_produto' => 'required|string|max:255',
                'produtos.*.quantidade' => 'required|integer|min:1',
                'produtos.*.unidade_medida' => 'required|string|max:50',
                'produtos.*.finalidade' => 'nullable|string',
                'produtos.*.prioridade' => 'required|in:Baixa,Média,Alta,Urgente',
            ];
        }

        return [
            'empresa_id' => 'required|exists:empresas,id',
            'observacoes' => 'nullable|string',
            'nome_produto' => 'required_without:descricao|string|max:255',
            'descricao' => 'required_without:nome_produto|string|max:255',
            'quantidade' => 'required|integer|min:1',
            'unidade_medida' => 'required_without:unidade|string|max:50',
            'unidade' => 'required_without:unidade_medida|string|max:50',
            'finalidade' => 'nullable|string',
            'prioridade' => 'required|in:Baixa,Média,Alta,Urgente',
            'valor_unitario' => 'nullable|numeric|min:0',
        ];
    }
}
