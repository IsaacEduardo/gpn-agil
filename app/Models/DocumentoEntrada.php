<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentoEntrada extends Model
{
    use \App\Traits\Auditable, HasFactory, SoftDeletes;

    protected $table = 'documentos_entradas';

    protected $fillable = [
        'numero_sequencial',
        'ano_referencia',
        'data_entrada',
        'classificacao_especie',
        'classificacao_ref_numero',
        'data_documento',
        'procedencia',
        'assunto',
        'observacoes',
        'saida_gabinete_data',
        'encaminhamento_orgao',
        'encaminhamento_oficio_numero',
        'encaminhamento_data',
        'departamento_id',
        'user_id',
        'status',
        'arquivo_caminho',
        'visto_departamento_status',
        'visto_departamento_por',
        'visto_departamento_data',
        'visto_departamento_observacao',
        'visto_gabinete_status',
        'visto_gabinete_por',
        'visto_gabinete_data',
        'visto_gabinete_observacao',
        'pasta_id',
        'arquivado',
        'arquivado_em',
        'arquivado_por',
    ];

    protected $casts = [
        'data_entrada' => 'date',
        'data_documento' => 'date',
        'saida_gabinete_data' => 'date',
        'encaminhamento_data' => 'date',
        'visto_departamento_data' => 'datetime',
        'visto_gabinete_data' => 'datetime',
        'arquivado_em' => 'datetime',
        'arquivado' => 'boolean',
    ];

    public function scopeArquivados($query)
    {
        return $query->where('arquivado', true);
    }

    public function scopeNaoArquivados($query)
    {
        return $query->where('arquivado', false);
    }

    public function pasta()
    {
        return $this->belongsTo(Pasta::class);
    }

    public function arquivadoPor()
    {
        return $this->belongsTo(User::class, 'arquivado_por');
    }

    public function departamento()
    {
        return $this->belongsTo(Departamento::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function anexos()
    {
        return $this->morphMany(Anexo::class, 'anexavel')->orderBy('ordem');
    }

    public function encaminhamentos()
    {
        return $this->hasMany(\App\Models\DocumentoEncaminhamento::class, 'documento_entrada_id')
            ->orderBy('encaminhado_em');
    }

    public function ultimoEncaminhamento()
    {
        return $this->hasOne(\App\Models\DocumentoEncaminhamento::class, 'documento_entrada_id')
            ->latestOfMany('encaminhado_em');
    }

    public function encaminhamentosExternos()
    {
        return $this->hasMany(\App\Models\DocumentoEncaminhamentoExterno::class, 'documento_entrada_id')
            ->orderBy('enviado_em');
    }

    public function protocolo()
    {
        return $this->hasOne(\App\Models\DocumentoProtocolo::class, 'documento_entrada_id');
    }

    public function vistoDepartamentoPor()
    {
        return $this->belongsTo(User::class, 'visto_departamento_por');
    }

    public function vistoGabinetePor()
    {
        return $this->belongsTo(User::class, 'visto_gabinete_por');
    }

    public function tarefas()
    {
        return $this->hasMany(\App\Models\DocumentoTarefa::class, 'documento_entrada_id')->orderByDesc('created_at');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'documento_entrada_tag');
    }

    public function documentosRelacionados()
    {
        return $this->belongsToMany(DocumentoEntrada::class, 'documento_relacoes', 'documento_id', 'relacionado_id')
            ->withPivot('tipo')
            ->withTimestamps();
    }

    public function documentosRelacionadosInverso()
    {
        return $this->belongsToMany(DocumentoEntrada::class, 'documento_relacoes', 'relacionado_id', 'documento_id')
            ->withPivot('tipo')
            ->withTimestamps();
    }

    public function documentosInternos()
    {
        return $this->hasMany(DocumentoInterno::class, 'documento_entrada_id');
    }

    public function metadata()
    {
        return $this->morphMany(FileMetadata::class, 'metadatable');
    }

    public function getTodosRelacionadosAttribute()
    {
        return $this->documentosRelacionados->merge($this->documentosRelacionadosInverso);
    }

    public function getSlaStatusAttribute()
    {
        if ($this->arquivado || in_array($this->status, ['arquivado', 'cancelado', 'finalizado'])) {
            return 'normal';
        }

        $dataBase = $this->data_entrada ?? $this->created_at;
        if (! $dataBase) {
            return 'normal';
        }

        $dias = $dataBase->diffInDays(now());

        if ($dias >= 5) {
            return 'critical';
        }
        if ($dias >= 2) {
            return 'warning';
        }

        return 'normal';
    }

    public function getDiasDecorridosAttribute()
    {
        $dataBase = $this->data_entrada ?? $this->created_at;

        return $dataBase ? (int) $dataBase->diffInDays(now()) : 0;
    }
}
