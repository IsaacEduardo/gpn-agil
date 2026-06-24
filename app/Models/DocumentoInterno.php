<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentoInterno extends Model
{
    use \App\Traits\Auditable, HasFactory;

    protected $table = 'documento_internos';

    protected $fillable = [
        'numero_referencia',
        'titulo',
        'conteudo_final',
        'documento_especie_id',
        'modelo_documento_id',
        'documento_entrada_id',
        'criado_por',
        'departamento_id',
        'status',
        'destinatario_nome',
        'destinatario_cargo',
        'destinatario_orgao',
        'destinatario_local',
        'assinado_em',
        'assinado_por_user_id',
        'assinatura_hash',
        'bloqueado_edicao',
        'versao_atual',
        'versao_major',
        'versao_minor',
        'versao_patch',
        'pasta_id',
        'arquivado',
        'arquivado_em',
        'arquivado_por',
    ];

    protected $casts = [
        'status' => \App\Enums\DocumentoStatus::class,
        'assinado_em' => 'datetime',
        'bloqueado_edicao' => 'boolean',
        'versao_atual' => 'integer',
        'versao_major' => 'integer',
        'versao_minor' => 'integer',
        'versao_patch' => 'integer',
        'arquivado' => 'boolean',
        'arquivado_em' => 'datetime',
    ];

    public function getVersaoSemanticaAttribute(): string
    {
        return "{$this->versao_major}.{$this->versao_minor}.{$this->versao_patch}";
    }

    /**
     * Scope para filtrar documentos visíveis ao usuário.
     */
    public function scopeAccessibleBy($query, User $user)
    {
        if ($user->isAdmin()) {
            return $query; // Admin vê tudo
        }

        if ($user->isSuperChefeGabinete()) {
            $gabinete = $user->gabineteSuperGerenciado;
            if ($gabinete) {
                return $query->whereHas('departamento', function ($q) use ($gabinete) {
                    $q->where('gabinete_id', $gabinete->id);
                });
            }
        }

        if ($user->isChefeGabinete()) {
            $gabinete = $user->gabineteGerenciado;

            // Retorna docs onde o departamento pertence ao gabinete
            return $query->whereHas('departamento', function ($q) use ($gabinete) {
                $q->where('gabinete_id', $gabinete->id);
            });
        }

        // Pode ser delegado (se implementarmos permission 'gabinete.view_all')
        // Usa checkPermissionTo para evitar erro se a permissão não existir no DB (embora migration deva criar)
        try {
            if ($user->hasPermissionTo('gabinete.view_all')) {
                if ($user->departamento && $user->departamento->gabinete_id) {
                    return $query->whereHas('departamento', function ($q) use ($user) {
                        $q->where('gabinete_id', $user->departamento->gabinete_id);
                    });
                }
            }
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
            // Permissão não existe, ignorar
        }

        // Default: Apenas documentos do departamento do usuário
        return $query->where('departamento_id', $user->departamento_id);
    }

    public function pasta()
    {
        return $this->belongsTo(Pasta::class);
    }

    public function arquivador()
    {
        return $this->belongsTo(User::class, 'arquivado_por');
    }

    public function versoes()
    {
        return $this->hasMany(DocumentoVersao::class, 'documento_interno_id')->orderByDesc('versao');
    }

    public function especie()
    {
        return $this->belongsTo(DocumentoEspecie::class, 'documento_especie_id');
    }

    public function documentoEspecie()
    {
        return $this->belongsTo(DocumentoEspecie::class, 'documento_especie_id');
    }

    public function modelo()
    {
        return $this->belongsTo(ModeloDocumento::class, 'modelo_documento_id');
    }

    public function documentoEntrada()
    {
        return $this->belongsTo(DocumentoEntrada::class, 'documento_entrada_id');
    }

    public function autor()
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    public function departamento()
    {
        return $this->belongsTo(Departamento::class);
    }

    public function assinadoPor()
    {
        return $this->belongsTo(User::class, 'assinado_por_user_id');
    }

    public function metadata()
    {
        return $this->morphMany(FileMetadata::class, 'metadatable');
    }

    public function favoritadoPor()
    {
        return $this->belongsToMany(User::class, 'documento_interno_favoritos', 'documento_interno_id', 'user_id')
            ->withTimestamps();
    }
}
