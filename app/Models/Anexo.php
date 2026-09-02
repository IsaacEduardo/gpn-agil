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
