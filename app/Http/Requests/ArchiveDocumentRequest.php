<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação do arquivamento por arrastar-e-soltar (lote, ambos os tipos).
 * A autorização fina é por documento (Policy 'archive') no controller, pois cada
 * documento pode ter um dono/departamento diferente.
 */
class ArchiveDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'document_type' => ['required', 'in:entrada,interno'],
            'document_ids' => ['required', 'array', 'min:1', 'max:100'],
            'document_ids.*' => ['integer'],
            // 'status' = arquivamento cronológico automático; 'folder' = pasta específica.
            'destination_type' => ['required', 'in:status,folder'],
            'destination_id' => ['nullable', 'required_if:destination_type,folder', 'integer', 'exists:pastas,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'document_ids.required' => 'Selecione pelo menos um documento para arquivar.',
            'document_ids.max' => 'Não é possível arquivar mais de 100 documentos de uma só vez.',
            'destination_id.required_if' => 'Indique a pasta de destino.',
            'destination_id.exists' => 'A pasta de destino não existe.',
        ];
    }

    /**
     * Pasta de destino normalizada: 'auto' (cronológico) ou o id da pasta.
     */
    public function destino(): string|int
    {
        return $this->input('destination_type') === 'folder'
            ? (int) $this->input('destination_id')
            : 'auto';
    }
}
