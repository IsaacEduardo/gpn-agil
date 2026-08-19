<?php

namespace App\Models;

use App\Enums\StatusRequisicao;
use App\Enums\TipoRequisicao;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Casts\Attribute;

class Requisicao extends Model
{
    use HasFactory;

    protected $table = 'requisicoes';

    protected $fillable = [
        'tipo',
        'codigo_sequencial',
        'data_requisicao',
        'usuario_id',
        'gabinete_id',
        'status',
        'empresa_destinataria',
        'empresa_id',
        'observacoes',
        'motivo_rejeicao',
        // Visto do departamento
        'visto_departamento_status',
        'visto_departamento_por',
        'visto_departamento_data',
        'visto_departamento_observacao',
        'assinado_em',
        'assinado_por_user_id',
        'assinatura_hash',
    ];

    protected $casts = [
        'tipo' => TipoRequisicao::class,
        'data_requisicao' => 'date',
        'visto_departamento_data' => 'datetime',
        'assinado_em' => 'datetime',
    ];

    protected function status(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if ($value instanceof StatusRequisicao) {
                    return $value;
                }
                return StatusRequisicao::tryFromValue($value);
            },
            set: function ($value) {
                if ($value instanceof StatusRequisicao) {
                    return $value->value;
                }
                return StatusRequisicao::tryFromValue($value)?->value ?? $value;
            }
        );
    }

    // Constants for tipo values
    const TIPO_PRODUTO = TipoRequisicao::PRODUTO;

    const TIPO_OFICINA = TipoRequisicao::OFICINA;

    const TIPO_SERVICO = TipoRequisicao::SERVICO;

    const TIPO_PASSAGEM = TipoRequisicao::PASSAGEM;

    // Constants for status values
    const STATUS_PENDENTE = StatusRequisicao::PENDENTE;

    const STATUS_APROVADO = StatusRequisicao::APROVADO;

    const STATUS_REJEITADO = StatusRequisicao::REJEITADO;

    const STATUS_FINALIZADO = StatusRequisicao::FINALIZADO;

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Gabinete do criador, capturado no momento da criação (snapshot).
     * Fonte imutável para o cabeçalho do documento; ver RequisicaoObserver.
     */
    public function gabinete(): BelongsTo
    {
        return $this->belongsTo(Gabinete::class, 'gabinete_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function produtos()
    {
        return $this->hasMany(RequisicaoProduto::class);
    }

    public function oficina()
    {
        return $this->hasOne(RequisicaoOficina::class);
    }

    public function servicos()
    {
        return $this->hasOne(RequisicaoServico::class);
    }

    public function passagem()
    {
        return $this->hasOne(RequisicaoPassagem::class);
    }

    public function termos()
    {
        return $this->hasMany(TermoEntrega::class);
    }

    // Vínculo com o usuário que deu o visto de departamento
    public function vistoDepartamentoUsuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'visto_departamento_por');
    }

    public function assinadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assinado_por_user_id');
    }

    // Helper methods
    public function isProduto()
    {
        return $this->tipo === TipoRequisicao::PRODUTO;
    }

    public function isOficina()
    {
        return $this->tipo === TipoRequisicao::OFICINA;
    }

    public function isServico()
    {
        return $this->tipo === TipoRequisicao::SERVICO;
    }

    public function isPendente()
    {
        return $this->status === StatusRequisicao::PENDENTE;
    }

    // Helpers de visto do departamento
    public function vistoDepartamentoPendente(): bool
    {
        return $this->visto_departamento_status === 'pendente' || $this->visto_departamento_status === null;
    }

    public function vistoDepartamentoAprovado(): bool
    {
        return $this->visto_departamento_status === 'aprovado';
    }

    public function vistoDepartamentoRejeitado(): bool
    {
        return $this->visto_departamento_status === 'rejeitado';
    }

    public function isAprovado()
    {
        return $this->status === StatusRequisicao::APROVADO;
    }

    /**
     * Scope a query to only include requisicoes accessible by the user.
     */
    public function scopeVisibleToUser(Builder $query, User $user): void
    {
        // Se for admin ou tiver permissão de ver tudo, retorna sem filtros
        if ($user->isAdmin() || $user->hasPermission('requisicoes.listar_todas')) {
            return;
        }

        // Obtém departamentos do usuário
        $actorDeps = (method_exists($user, 'departamentos') && $user->departamentos)
            ? $user->departamentos->pluck('id')->all()
            : [];

        if (empty($actorDeps) && $user->departamento_id) {
            $actorDeps = [$user->departamento_id];
        }

        // Obtém gabinetes chefiados pelo usuário
        $headedGabIds = Gabinete::where('responsavel_id', $user->id)->pluck('id')->all();

        if (count($headedGabIds)) {
            $query->whereHas('usuario', function ($u) use ($headedGabIds) {
                $u->whereHas('departamento', function ($q) use ($headedGabIds) {
                    $q->whereIn('gabinete_id', $headedGabIds);
                })
                    ->orWhereHas('departamentos', function ($qq) use ($headedGabIds) {
                        $qq->whereIn('gabinete_id', $headedGabIds);
                    });
            });
        } elseif (count($actorDeps)) {
            // Se tiver permissão de visto de departamento, vê as do departamento
            if ($user->hasPermission('visto_departamento_requisicoes')) {
                $query->whereHas('usuario', function ($u) use ($actorDeps) {
                    $u->whereIn('departamento_id', $actorDeps)
                        ->orWhereHas('departamentos', function ($qq) use ($actorDeps) {
                            $qq->whereIn('id', $actorDeps);
                        });
                });
            } else {
                // Caso contrário, vê apenas as suas
                $query->where('usuario_id', $user->id);
            }
        } else {
            // Se não for chefe nem tiver departamento, vê apenas as suas
            $query->where('usuario_id', $user->id);
        }
    }

    /**
     * Alias for visibleToUser to prevent errors if accessibleBy is used
     */
    public function scopeAccessibleBy(Builder $query, User $user): void
    {
        $this->scopeVisibleToUser($query, $user);
    }

    /**
     * Scope for searching
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (empty($term)) {
            return;
        }
        $q = trim($term);
        $query->where(function ($sub) use ($q) {
            $sub->where('codigo_sequencial', 'like', "%{$q}%")
                ->orWhere('empresa_destinataria', 'like', "%{$q}%")
                ->orWhere('observacoes', 'like', "%{$q}%");
        });
    }

    /**
     * Scope for filtering by status
     */
    public function scopeByStatus(Builder $query, ?string $status): void
    {
        if (! empty($status)) {
            $query->where('status', $status);
        }
    }

    /**
     * Scope for filtering by type
     */
    public function scopeByTipo(Builder $query, ?string $tipo): void
    {
        if (! empty($tipo)) {
            $query->where('tipo', $tipo);
        }
    }
}
