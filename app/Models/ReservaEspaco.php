<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReservaEspaco extends Model
{
    use HasFactory;

    protected $table = 'reservas_espacos';

    protected $fillable = [
        'codigo_reserva',
        'tipo_espaco',
        'solicitante_nome',
        'solicitante_email',
        'solicitante_telefone',
        'evento_titulo',
        'evento_descricao',
        'data_evento',
        'hora_inicio',
        'hora_fim',
        'numero_participantes',
        'status',
        'observacoes',
        'motivo_rejeicao',
        'usuario_id',
        'aprovado_por',
        'data_aprovacao',
        // Visto do departamento
        'visto_departamento_status',
        'visto_departamento_por',
        'visto_departamento_data',
        'visto_departamento_observacao',
    ];

    protected $casts = [
        'data_evento' => 'date',
        'hora_inicio' => 'datetime:H:i',
        'hora_fim' => 'datetime:H:i',
        'data_aprovacao' => 'datetime',
        'visto_departamento_data' => 'datetime',
    ];

    // Constantes para tipos de espaço
    const TIPO_SALAO_NOBRE = 'salao_nobre';

    const TIPO_ANFITEATRO = 'anfiteatro';

    // Constantes para status
    const STATUS_PENDENTE = 'pendente';

    const STATUS_APROVADA = 'aprovada';

    const STATUS_REJEITADA = 'rejeitada';

    const STATUS_CANCELADA = 'cancelada';

    /**
     * Relacionamento com o usuário que criou a reserva
     */
    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Relacionamento com o usuário que aprovou a reserva
     */
    public function aprovador()
    {
        return $this->belongsTo(User::class, 'aprovado_por');
    }

    /**
     * Relacionamento com usuário que deu visto do departamento
     */
    public function vistoDepartamentoUsuario()
    {
        return $this->belongsTo(User::class, 'visto_departamento_por');
    }

    /**
     * Verificar se a reserva está pendente
     */
    public function isPendente()
    {
        return $this->status === self::STATUS_PENDENTE;
    }

    // Helpers de visto do departamento
    public function vistoDepartamentoPendente(): bool
    {
        return $this->visto_departamento_status === 'pendente' || $this->visto_departamento_status === null;
    }

    public function vistoDepartamentoAprovado(): bool
    {
        return $this->visto_departamento_status === 'aprovada' || $this->visto_departamento_status === 'aprovado';
    }

    public function vistoDepartamentoRejeitado(): bool
    {
        return $this->visto_departamento_status === 'rejeitada' || $this->visto_departamento_status === 'rejeitado';
    }

    /**
     * Verificar se a reserva está aprovada
     */
    public function isAprovada()
    {
        return $this->status === self::STATUS_APROVADA;
    }

    /**
     * Verificar se a reserva está rejeitada
     */
    public function isRejeitada()
    {
        return $this->status === self::STATUS_REJEITADA;
    }

    /**
     * Verificar se a reserva está cancelada
     */
    public function isCancelada()
    {
        return $this->status === self::STATUS_CANCELADA;
    }

    /**
     * Verificar se o espaço está disponível para uma data e horário específicos
     */
    public static function espacoDisponivel($tipoEspaco, $dataEvento, $horaInicio, $horaFim, $excludeId = null)
    {
        $query = self::where('tipo_espaco', $tipoEspaco)
            ->where('data_evento', $dataEvento)
            ->whereIn('status', [self::STATUS_PENDENTE, self::STATUS_APROVADA])
            ->where(function ($q) use ($horaInicio, $horaFim) {
                $q->whereBetween('hora_inicio', [$horaInicio, $horaFim])
                    ->orWhereBetween('hora_fim', [$horaInicio, $horaFim])
                    ->orWhere(function ($subQ) use ($horaInicio, $horaFim) {
                        $subQ->where('hora_inicio', '<=', $horaInicio)
                            ->where('hora_fim', '>=', $horaFim);
                    });
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->count() === 0;
    }

    /**
     * Gerar código único para a reserva
     */
    public static function gerarCodigoReserva($tipoEspaco)
    {
        $prefixo = $tipoEspaco === self::TIPO_SALAO_NOBRE ? 'SN' : 'ANF';
        $mesAno = date('m/Y');

        $ultimaReserva = self::where('tipo_espaco', $tipoEspaco)
            ->whereMonth('created_at', date('m'))
            ->whereYear('created_at', date('Y'))
            ->orderBy('id', 'desc')
            ->first();

        $sequencial = $ultimaReserva ? intval(substr($ultimaReserva->codigo_reserva, -3)) + 1 : 1;

        return $prefixo.'-'.$mesAno.'-'.str_pad($sequencial, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Obter nome amigável do tipo de espaço
     */
    public function getTipoEspacoNomeAttribute()
    {
        return match ($this->tipo_espaco) {
            self::TIPO_SALAO_NOBRE => 'Salão Nobre',
            self::TIPO_ANFITEATRO => 'Anfiteatro',
            default => 'Desconhecido'
        };
    }

    /**
     * Obter nome amigável do status
     */
    public function getStatusNomeAttribute()
    {
        return match ($this->status) {
            self::STATUS_PENDENTE => 'Pendente',
            self::STATUS_APROVADA => 'Aprovada',
            self::STATUS_REJEITADA => 'Rejeitada',
            self::STATUS_CANCELADA => 'Cancelada',
            default => 'Desconhecido'
        };
    }

    /**
     * Verificar se a reserva pode ser editada
     */
    public function podeSerEditada()
    {
        return $this->isPendente() && $this->data_evento >= now()->toDateString();
    }

    /**
     * Verificar se a reserva pode ser cancelada
     */
    public function podeSerCancelada()
    {
        return in_array($this->status, [self::STATUS_PENDENTE, self::STATUS_APROVADA])
            && $this->data_evento >= now()->toDateString();
    }
}
