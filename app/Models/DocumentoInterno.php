<?php

namespace App\Models;

use App\Enums\DocumentoStatus;
use App\Events\EventoColaborativo;
use App\Services\DocumentoCollaborationService;
use App\Support\CamposVinculados;
use App\Support\Sanitizer;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class DocumentoInterno extends Model
{
    use Auditable, HasFactory;

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
        'gabinete_id',
        'status',
        'retencao_notificada_em',
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
        'status' => DocumentoStatus::class,
        'retencao_notificada_em' => 'datetime',
        'assinado_em' => 'datetime',
        'bloqueado_edicao' => 'boolean',
        'versao_atual' => 'integer',
        'versao_major' => 'integer',
        'versao_minor' => 'integer',
        'versao_patch' => 'integer',
        'arquivado' => 'boolean',
        'arquivado_em' => 'datetime',
        'revisao_classica' => 'integer',
    ];

    protected static function booted(): void
    {
        // Snapshot do gabinete emissor em todos os caminhos de criação/edição.
        static::saving(function (self $doc) {
            if ($doc->gabinete_id === null && $doc->departamento_id) {
                $doc->gabinete_id = Departamento::whereKey($doc->departamento_id)->value('gabinete_id');
            }
        });

        // Submetido, bloqueado ou assinado: as sessões colaborativas abertas passam a só
        // leitura de imediato (o servidor já recusa as gravações delas de qualquer forma).
        static::updated(function (self $doc) {
            if (! config('app.feature_collab') || ! $doc->wasChanged(['status', 'bloqueado_edicao', 'assinado_em'])) {
                return;
            }

            $colaboracao = app(DocumentoCollaborationService::class);
            if (! $colaboracao->sessaoAberta($doc)) {
                $colaboracao->transmitir($doc, EventoColaborativo::ENCERRADA, [
                    'message' => 'Este documento já não está em rascunho: a edição colaborativa terminou.',
                ]);
            }
        });
    }

    /**
     * Gabinete emissor: o snapshot gravado; para registos antigos, o gabinete do departamento.
     */
    public function gabineteEmissor(): ?Gabinete
    {
        if ($this->gabinete_id) {
            return $this->relationLoaded('gabinete') ? $this->gabinete : $this->gabinete()->first();
        }

        return $this->departamento?->gabinete;
    }

    public function gabineteEmissorId(): ?int
    {
        $id = $this->gabinete_id ?? $this->departamento?->gabinete_id;

        return $id !== null ? (int) $id : null;
    }

    /**
     * O conteúdo ainda pode mudar: rascunho ou em análise, sem bloqueio nem assinatura.
     * Vale para todos, incluindo o admin (que o Gate deixa passar nas policies);
     * quem pode editar em cada estado decide-o DocumentoInternoPolicy::update.
     */
    public function aceitaEdicao(): bool
    {
        return in_array($this->status, [DocumentoStatus::RASCUNHO, DocumentoStatus::EM_ANALISE], true)
            && ! $this->bloqueado_edicao
            && ! $this->assinado_em;
    }

    /** Emitido pelo próprio gabinete (Chefe de Gabinete / Secretário Geral), sem departamento. */
    public function emitidoPeloGabinete(): bool
    {
        return $this->departamento_id === null && $this->gabinete_id !== null;
    }

    /**
     * Documentos de um gabinete: pelo snapshot ou, em registos sem ele, pelo departamento.
     */
    public function scopeDoGabinete($query, $gabineteId)
    {
        return $query->where(function ($q) use ($gabineteId) {
            $q->where('gabinete_id', $gabineteId)
                ->orWhere(function ($q2) use ($gabineteId) {
                    $q2->whereNull('gabinete_id')
                        ->whereHas('departamento', fn ($d) => $d->where('gabinete_id', $gabineteId));
                });
        });
    }

    public function getVersaoSemanticaAttribute(): string
    {
        return "{$this->versao_major}.{$this->versao_minor}.{$this->versao_patch}";
    }

    /**
     * Marcadores que ligam o corpo a dados do documento (Assunto/destinatário e referência).
     * Um editor que os perca degrada o documento; por isso servem de critério de guarda.
     */
    public static function contarMarcadoresEstruturados(?string $html): int
    {
        return preg_match_all('/class\s*=\s*["\'][^"\']*(?<![\w-])(?:campo-vinculado|ref-nossa-referencia)(?![\w-])/i', (string) $html);
    }

    public function temConteudoEstruturado(): bool
    {
        return self::contarMarcadoresEstruturados($this->conteudo_final) > 0;
    }

    /**
     * HTML para mostrar, imprimir ou indexar. Os campos vazios (ex.: "[Cargo]") ficam
     * gravados, para reaparecerem quando forem preenchidos, mas nunca são apresentados.
     * O que é assinado/hasheado continua a ser o conteudo_final gravado.
     */
    public static function htmlParaApresentacao(?string $html): string
    {
        return Sanitizer::clean(CamposVinculados::limparVazios((string) $html));
    }

    public function conteudoParaApresentacao(): string
    {
        return self::htmlParaApresentacao($this->conteudo_final);
    }

    /**
     * HTML gravado sem os campos vazios, para extrair texto (indexação, assistente de IA).
     * Não passa pelo Sanitizer, que codificaria os acentos em entidades.
     */
    public function conteudoSemCamposVazios(): string
    {
        return CamposVinculados::limparVazios((string) $this->conteudo_final);
    }

    /**
     * Scope para filtrar documentos visíveis ao usuário.
     */
    public function scopeAccessibleBy($query, User $user)
    {
        if ($user->isAdmin()) {
            return $query; // Admin vê tudo
        }

        // Quem foi convidado para colaborar vê o documento enquanto for colaborador,
        // mesmo sendo de outro departamento do gabinete (o convite é por gabinete).
        return $query->where(function ($q) use ($user) {
            $q->where(fn ($base) => static::visibilidadePorFuncao($base, $user))
                ->orWhereHas('colaboradores', fn ($c) => $c->where('user_id', $user->id));
        });
    }

    /** Visibilidade pela função na estrutura (gabinete, departamento, autoria). */
    protected static function visibilidadePorFuncao($query, User $user)
    {
        if ($user->isSuperChefeGabinete()) {
            $gabinete = $user->gabineteSuperGerenciado;
            if ($gabinete) {
                return $query->doGabinete($gabinete->id);
            }
        }

        if ($user->isChefeGabinete()) {
            $gabinete = $user->gabineteGerenciado;

            // Docs do gabinete: dos seus departamentos e os emitidos pelo próprio gabinete
            return $query->doGabinete($gabinete->id);
        }

        // Pode ser delegado (se implementarmos permission 'gabinete.view_all')
        // Usa checkPermissionTo para evitar erro se a permissão não existir no DB (embora migration deva criar)
        try {
            if ($user->hasPermissionTo('gabinete.view_all')) {
                if ($user->departamento && $user->departamento->gabinete_id) {
                    return $query->doGabinete($user->departamento->gabinete_id);
                }
            }
        } catch (PermissionDoesNotExist $e) {
            // Permissão não existe, ignorar
        }

        // Chefe de Departamento: consulta todos os documentos elaborados no seu departamento
        if ($user->isChefeDepartamento()) {
            return $query->where('departamento_id', $user->departamento_id);
        }

        // Técnico: visualiza documentos do seu departamento, mas rascunhos são visíveis exclusivamente pelo seu próprio autor
        return $query->where('departamento_id', $user->departamento_id)
            ->where(function ($q) use ($user) {
                $q->where('status', '!=', DocumentoStatus::RASCUNHO->value)
                    ->orWhere('criado_por', $user->id)
                    ->orWhereHas('colaboradores', function ($c) use ($user) {
                        $c->where('user_id', $user->id);
                    });
            });
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

    public function vinculosOrigem()
    {
        return $this->hasMany(DocumentoVinculo::class, 'origem_id')->where('origem_tipo', 'INTERNO');
    }

    public function vinculosDestino()
    {
        return $this->hasMany(DocumentoVinculo::class, 'destino_id')->where('destino_tipo', 'INTERNO');
    }

    public function todosVinculos()
    {
        return DocumentoVinculo::paraDocumento('INTERNO', $this->id)->with('vinculadoPor')->get();
    }

    public function autor()
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    public function departamento()
    {
        return $this->belongsTo(Departamento::class);
    }

    /** Snapshot do gabinete emissor (ver gabineteEmissor()). */
    public function gabinete()
    {
        return $this->belongsTo(Gabinete::class);
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

    /**
     * Colaboradores convidados para edição em tempo real.
     */
    public function colaboradores()
    {
        return $this->hasMany(DocumentoColaborador::class, 'documento_interno_id');
    }

    /**
     * Log durável de updates Yjs (CRDT) da edição colaborativa.
     */
    public function comentarios()
    {
        return $this->hasMany(DocumentoComentario::class, 'documento_interno_id');
    }

    public function collabUpdates()
    {
        return $this->hasMany(DocumentoCollabUpdate::class, 'documento_interno_id');
    }

    /**
     * Anexos associados ao documento interno.
     */
    public function anexos()
    {
        return $this->morphMany(Anexo::class, 'anexavel')->orderBy('ordem');
    }
}
