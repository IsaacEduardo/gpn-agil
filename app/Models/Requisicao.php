<?php

namespace App\Models;

use App\Enums\StatusRequisicao;
use App\Enums\TipoRequisicao;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Requisicao extends Model
{
    use HasFactory;

    protected $table = 'requisicoes';

    protected $fillable = [
        'tipo',
        'codigo_sequencial',
        'data_requisicao',
        'usuario_id',
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
    ];

    protected $casts = [
        'tipo' => TipoRequisicao::class,
        'status' => StatusRequisicao::class,
        'data_requisicao' => 'date',
        'visto_departamento_data' => 'datetime',
    ];

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
    public function scopeAccessibleBy(Builder $query, User $user): void
    {
        // Se for admin ou tiver permissão de ver tudo, retorna sem filtros
        $isAdmin = $user->role && $user->role->name === 'admin';
        $canViewAny = $user->hasPermission('requisicoes.view_any');

        if ($isAdmin || $canViewAny) {
            return;
        }

        // Obtém departamentos do usuário
        $actorDeps = (method_exists($user, 'departamentos') && $user->departamentos)
            ? $user->departamentos->pluck('id')->all() 
            : [];
        
        if (! count($actorDeps) && $user->departamento_id) {
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
            $query->whereHas('usuario', function ($u) use ($actorDeps) {
                $u->whereIn('departamento_id', $actorDeps)
                    ->orWhereHas('departamentos', function ($qq) use ($actorDeps) {
                        $qq->whereIn('id', $actorDeps);
                    });
            });
        } else {
            // Se não for chefe nem tiver departamento, vê apenas as suas
            $query->where('usuario_id', $user->id);
        }
    }
}
