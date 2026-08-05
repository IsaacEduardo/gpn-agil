<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SolicitacaoAtribuicao extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'solicitacoes_atribuicao';

    protected $fillable = [
        'uuid',
        'numero_protocolo',
        'requerente_id',
        'lote_id',
        'finalidade_uso',
        'modalidade_atribuicao',
        'status',
        'documento_entrada_id',
        'documento_interno_termo_id',
        'data_solicitacao',
        'data_homologacao',
        'motivo_rejeicao',
        'created_by_user_id',
    ];

    protected $casts = [
        'data_solicitacao' => 'datetime',
        'data_homologacao' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->numero_protocolo)) {
                $ano = date('Y');
                $seq = static::whereYear('created_at', $ano)->count() + 1;
                $model->numero_protocolo = sprintf('SOL-%s/%05d', $ano, $seq);
            }
        });
    }

    public function requerente(): BelongsTo
    {
        return $this->belongsTo(Requerente::class, 'requerente_id');
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class, 'lote_id');
    }

    public function documentoEntrada(): BelongsTo
    {
        return $this->belongsTo(DocumentoEntrada::class, 'documento_entrada_id');
    }

    public function documentoTermo(): BelongsTo
    {
        return $this->belongsTo(DocumentoInterno::class, 'documento_interno_termo_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function analisesTecnicas(): HasMany
    {
        return $this->hasMany(AnaliseTecnica::class, 'solicitacao_atribuicao_id');
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'RASCUNHO' => 'bg-secondary',
            'SUBMETIDO' => 'bg-primary',
            'EM_TRIAGEM' => 'bg-info',
            'EM_VISTORIA' => 'bg-warning',
            'EM_ANALISE_JURIDICA' => 'bg-dark',
            'AGUARDANDO_HOMOLOGACAO' => 'bg-indigo',
            'APROVADO' => 'bg-success',
            'REJEITADO' => 'bg-danger',
            'CANCELADO' => 'bg-secondary',
            default => 'bg-light text-dark',
        };
    }
}
