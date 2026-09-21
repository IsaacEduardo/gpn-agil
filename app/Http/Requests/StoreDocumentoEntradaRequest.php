<?php

namespace App\Http\Requests;

use App\Models\DocumentoEntrada;
use App\Models\DocumentoEspecie;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rules\File;
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

        $documentFile = [
            'file',
            File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(self::LIMITE_FICHEIRO_KB.'kb'),
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $value instanceof UploadedFile) {
                    return;
                }

                // `mimes` aceita uma imagem pelo conteúdo mesmo quando o nome é .pdf.
                // Para o arquivo institucional, a extensão PDF tem de corresponder a PDF real.
                if (strtolower($value->getClientOriginalExtension()) === 'pdf'
                    && $value->getMimeType() !== 'application/pdf') {
                    $fail('O ficheiro PDF enviado não contém um documento PDF válido.');
                }
            },
        ];

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
            'arquivo' => ['nullable', ...$documentFile],
            'anexos.*' => ['nullable', ...$documentFile],
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
            'arquivo.mimes' => 'O ficheiro anexado deve ser PDF, JPG ou PNG.',
            'arquivo.file' => 'O ficheiro anexado não foi recebido corretamente. Tente novamente.',
            // Sem estas, a mensagem do Laravel cai no nome técnico do campo e o
            // balconista lia "O campo anexos.0 deve ser um arquivo do tipo...".
            'anexos.*.max' => 'Cada anexo não pode exceder :max KB.',
            'anexos.*.mimes' => 'O ficheiro anexado deve ser PDF, JPG ou PNG.',
            'anexos.*.file' => 'O ficheiro anexado não foi recebido corretamente. Tente novamente.',
        ];
    }

    /**
     * Nomes legíveis para as mensagens que os interpolam.
     *
     * 'anexos.*' cobre qualquer índice: sem isto a validação do terceiro anexo
     * falava de "anexos.2".
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'arquivo' => 'ficheiro principal',
            'anexos' => 'anexos',
            'anexos.*' => 'ficheiro anexado',
            'classificacao_especie' => 'espécie do documento',
            'classificacao_ref_numero' => 'número de referência',
            'data_documento' => 'data do documento',
            'data_entrada' => 'data de entrada',
            'departamento_id' => 'departamento de destino',
            'procedencia_id' => 'procedência',
        ];
    }
}
