<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AnaliseTecnica extends Model
{
    use HasFactory;

    protected $table = 'analises_tecnicas';

    protected $fillable = [
        'uuid',
        'solicitacao_atribuicao_id',
        'tecnico_user_id',
        'data_vistoria',
        'parecer_tecnico',
        'viabilidade',
        'coordenadas_vistoria',
    ];

    protected $casts = [
        'data_vistoria' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function solicitacao(): BelongsTo
    {
        return $this->belongsTo(SolicitacaoAtribuicao::class, 'solicitacao_atribuicao_id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_user_id');
    }
}
