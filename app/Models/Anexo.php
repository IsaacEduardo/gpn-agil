<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Anexo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'anexos';

    protected $fillable = [
        'anexavel_type',
        'anexavel_id',
        'nome_original',
        'caminho_arquivo',
        'mime_type',
        'tamanho_bytes',
        'descricao',
        'ordem',
        'user_id',
        'texto_extraido',
        'ocr_status',
        'ocr_processado_em',
        'ocr_erro',
        'ocr_tentativas',
        'ocr_metodo',
        'ocr_palavras_count',
    ];

    protected $casts = [
        'tamanho_bytes' => 'integer',
        'ordem' => 'integer',
        'ocr_processado_em' => 'datetime',
        'ocr_tentativas' => 'integer',
        'ocr_palavras_count' => 'integer',
    ];

    public function anexavel()
    {
        return $this->morphTo();
    }

    /**
     * Pesquisa no texto extraído por OCR.
     *
     * Em MySQL usa o índice FULLTEXT (migration
     * 2026_09_13_100000_add_fulltext_index_to_anexos_texto_extraido); nos
     * restantes drivers — incluindo o sqlite da suite de testes — mantém o LIKE.
     *
     * Único sítio onde esta escolha é feita: não espalhar ifs de driver pelas
     * queries que pesquisam documentos.
     *
     * Nota de comportamento: em MySQL a correspondência passa a ser por prefixo
     * de palavra ('licenciamento' encontra 'licenciamentos', mas 'cenciamento'
     * já não encontra 'licenciamento'). Termos demasiado curtos para o índice
     * (abaixo de innodb_ft_min_token_size) recaem no LIKE, para não
     * desaparecerem silenciosamente dos resultados.
     */
    public function scopePesquisarTextoExtraido($query, ?string $termo)
    {
        $termo = trim((string) $termo);
        if ($termo === '') {
            return $query;
        }

        if ($query->getConnection()->getDriverName() === 'mysql') {
            $booleano = self::termoBooleano($termo);

            if ($booleano !== '') {
                return $query->whereRaw(
                    'MATCH(texto_extraido) AGAINST (? IN BOOLEAN MODE)',
                    [$booleano]
                );
            }
        }

        return $query->where('texto_extraido', 'like', '%'.$termo.'%');
    }

    /**
     * Converte o termo de pesquisa em expressão BOOLEAN MODE segura: descarta
     * os operadores do MySQL (+ - * " ~ < > ( )) para não serem interpretados,
     * e devolve cada palavra com sufixo de prefixo.
     *
     * Devolve string vazia quando não resta nenhuma palavra com tamanho útil —
     * nesse caso o chamador recai no LIKE.
     */
    private static function termoBooleano(string $termo): string
    {
        $tokens = preg_split('/\s+/u', $termo, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $tokens = array_filter(array_map(
            fn ($token) => preg_replace('/[^\p{L}\p{N}_]/u', '', $token),
            $tokens
        ), fn ($token) => mb_strlen((string) $token) >= 3);

        return implode(' ', array_map(fn ($token) => $token.'*', $tokens));
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getUrlAttribute(): ?string
    {
        return $this->caminho_arquivo ? Storage::disk('public')->url($this->caminho_arquivo) : null;
    }

    public function isImage(): bool
    {
        return $this->mime_type ? str_starts_with($this->mime_type, 'image/') : false;
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function isOcrAplicavel(): bool
    {
        return $this->isPdf() || $this->isImage();
    }

    public function isOcrConcluido(): bool
    {
        return $this->ocr_status === 'CONCLUIDO';
    }

    public function isOcrProcessando(): bool
    {
        return $this->ocr_status === 'PROCESSANDO';
    }

    public function isOcrFalha(): bool
    {
        return $this->ocr_status === 'FALHA';
    }

    public function isOcrPendente(): bool
    {
        return $this->ocr_status === 'PENDENTE';
    }

    public function getOcrStatusBadgeAttribute(): array
    {
        return match ($this->ocr_status) {
            'CONCLUIDO' => [
                'label' => 'OCR Concluído',
                'class' => 'bg-success',
                'icon' => 'fas fa-check-circle',
            ],
            'PROCESSANDO' => [
                'label' => 'Em Processamento',
                'class' => 'bg-warning text-dark',
                'icon' => 'fas fa-spinner fa-spin',
            ],
            'FALHA' => [
                'label' => 'Falha no OCR',
                'class' => 'bg-danger',
                'icon' => 'fas fa-exclamation-triangle',
            ],
            'NAO_APLICAVEL' => [
                'label' => 'N/A',
                'class' => 'bg-secondary',
                'icon' => 'fas fa-minus-circle',
            ],
            default => [
                'label' => 'Pendente',
                'class' => 'bg-secondary',
                'icon' => 'fas fa-clock',
            ],
        };
    }
}
