<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Lote extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'codigo_lote',
        'matricula_cartoraria',
        'inscricao_imobiliaria',
        'municipio',
        'comuna',
        'bairro_distrito',
        'zona_setor',
        'area_m2',
        'perimetro_m',
        'zoneamento',
        'status',
        'latitude_centro',
        'longitude_centro',
        'geojson_geometria',
        'observacoes',
        'created_by_user_id',
    ];

    protected $casts = [
        'area_m2' => 'decimal:2',
        'perimetro_m' => 'decimal:2',
        'latitude_centro' => 'decimal:7',
        'longitude_centro' => 'decimal:7',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->codigo_lote)) {
                $ano = date('Y');
                $count = static::whereYear('created_at', $ano)->count() + 1;
                $model->codigo_lote = sprintf('LOTE-NAM-%s-%04d', $ano, $count);
            }
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function solicitacoes(): HasMany
    {
        return $this->hasMany(SolicitacaoAtribuicao::class, 'lote_id');
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'DISPONIVEL' => 'bg-success',
            'RESERVADO' => 'bg-info',
            'ATRIBUIDO' => 'bg-danger',
            'EM_LICITACAO' => 'bg-warning',
            'INDISPONIVEL' => 'bg-secondary',
            default => 'bg-dark',
        };
    }
}
