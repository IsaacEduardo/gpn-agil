<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentoTarefa extends Model
{
    use HasFactory;

    /** Cada técnico do grupo executa a sua tarefa (o comportamento de sempre). */
    public const MODO_TODOS = 'todos';

    /** O primeiro técnico do grupo a assumir fica com a tarefa. */
    public const MODO_CONCORRENCIA = 'concorrencia';

    /**
     * Tarefa de um grupo em concorrência que outro técnico assumiu. Não é
     * 'cancelada': ninguém a cancelou, e não pode contar como tal nos indicadores.
     */
    public const STATUS_RETIRADA = 'retirada';

    protected $table = 'documento_tarefas';

    protected $fillable = [
        'documento_entrada_id',
        'titulo',
        'descricao',
        'assigned_by_id',
        'assigned_to_user_id',
        'assigned_to_departamento_id',
        'responsavel_user_id',
        'prazo_at',
        'status',
        'resposta',
        'concluida_em',
        'grupo_tarefa_uuid',
        'modo_grupo',
        'assumida_em',
    ];

    protected $casts = [
        'prazo_at' => 'datetime',
        'concluida_em' => 'datetime',
        'assumida_em' => 'datetime',
    ];

    public function emConcorrencia(): bool
    {
        return $this->modo_grupo === self::MODO_CONCORRENCIA && $this->grupo_tarefa_uuid !== null;
    }

    /** Em concorrência, pendente e ainda sem dono: está à espera de quem a assuma. */
    public function aguardaQuemAssuma(): bool
    {
        return $this->emConcorrencia() && $this->status === 'pendente' && $this->assumida_em === null;
    }

    /** As tarefas do mesmo grupo, incluindo esta. */
    public function scopeDoGrupo($query, string $grupo)
    {
        return $query->where('grupo_tarefa_uuid', $grupo);
    }

    public function documento()
    {
        return $this->belongsTo(DocumentoEntrada::class, 'documento_entrada_id');
    }

    public function documentoEntrada()
    {
        return $this->belongsTo(DocumentoEntrada::class, 'documento_entrada_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }

    public function assignedToUser()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function assignedToDepartamento()
    {
        return $this->belongsTo(Departamento::class, 'assigned_to_departamento_id');
    }

    public function responsavelAtual()
    {
        return $this->belongsTo(User::class, 'responsavel_user_id');
    }
}
