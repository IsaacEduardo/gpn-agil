<?php

namespace App\Http\Requests;

use App\Models\DocumentoEntrada;
use App\Models\DocumentoEspecie;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class StoreDocumentoEntradaRequest extends FormRequest
{
    /**
     * Limite único para o ficheiro principal e para os anexos, em KB.
     *
     * Havia três valores em desacordo: 2 MB no 'arquivo', 5 MB nos anexos e
     * "Máx 10MB" anunciado no componente de upload. Fica alinhado pelo que a
     * interface promete — e dentro dos 25 MB documentados para produção.
     */
    public const LIMITE_FICHEIRO_KB = 10240;

    public function authorize()
    {
        return $this->user()->can('create', DocumentoEntrada::class);
    }

    public function rules()
    {
        $speciesNames = Cache::remember('documento_especies_names', 600, function () {
            return DocumentoEspecie::where('ativo', true)->orderBy('ordem')->pluck('nome')->all();
        });

        return [
            // A espécie era required no HTML e nullable aqui — e é a chave da
            // tabela de retenção (RetentionSchedule).
            'classificacao_especie' => ['required', 'string', 'max:100', Rule::in($speciesNames)],
            'classificacao_ref_numero' => ['nullable', 'string', 'max:100'],
            'data_documento' => ['nullable', 'date'],
            // Correspondência recebida em atraso tem de poder ser registada com a
            // data real: era sempre now(), o que contaminava o SLA.
            'data_entrada' => ['nullable', 'date', 'before_or_equal:today'],
            'procedencia' => ['nullable', 'string', 'max:255'],
            'procedencia_id' => ['nullable', 'exists:procedencias,id'],
            'assunto' => ['required', 'string', 'max:500'],
            'observacoes' => ['nullable', 'string'],
            'saida_gabinete_data' => ['nullable', 'date'],
            'encaminhamento_orgao' => ['nullable', 'string', 'max:255'],
            'encaminhamento_oficio_numero' => ['nullable', 'string', 'max:100'],
            'departamento_id' => ['required', 'exists:departamentos,id'],
            'arquivo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::LIMITE_FICHEIRO_KB],
            'anexos.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::LIMITE_FICHEIRO_KB],
            'tags' => ['nullable', 'string'],
            'confirmar_duplicado' => ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'classificacao_especie.required' => 'Selecione a espécie do documento.',
            'data_entrada.before_or_equal' => 'A data de entrada não pode ser posterior a hoje.',
            'arquivo.max' => 'O ficheiro não pode exceder :max KB.',
            'anexos.*.max' => 'Cada anexo não pode exceder :max KB.',
        ];
    }
}
