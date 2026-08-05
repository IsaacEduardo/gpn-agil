<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Requerente extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'tipo_pessoa',
        'nome_razao_social',
        'nif_bi',
        'email',
        'telefone',
        'telemovel_alternativo',
        'representante_nome',
        'representante_nif_bi',
        'endereco_completo',
        'municipio',
        'comuna',
        'bairro',
        'observacoes',
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

    public function solicitacoes(): HasMany
    {
        return $this->hasMany(SolicitacaoAtribuicao::class, 'requerente_id');
    }
}
