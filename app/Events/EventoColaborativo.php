<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Aviso do servidor aos participantes de uma sessão de edição colaborativa
 * (canal de presença documento.{id}). Imediato, não passa pela fila: é o
 * transporte das alterações em tempo real.
 *
 *  - ALTERACAO: um update Yjs aceite e gravado no log (id + update, ou só o id
 *    quando é grande demais para uma mensagem — o cliente vai buscá-lo).
 *  - ENCERRADA: o documento saiu de rascunho, ficou bloqueado ou foi assinado.
 *  - PERMISSOES: o nível de um participante mudou ou ele foi removido.
 *  - COMENTARIOS: um comentário foi criado, respondido, resolvido ou reaberto.
 *  - VERSAO: alguém guardou uma versão (os outros actualizam o número e o botão).
 *
 * As alterações passaram a ir pelo servidor (antes iam entre browsers, por client
 * events): só assim o nível de quem escreve é verificado antes de chegar aos outros.
 */
class EventoColaborativo implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public const ALTERACAO = 'collab.alteracao';

    public const ENCERRADA = 'collab.encerrada';

    public const PERMISSOES = 'collab.permissoes';

    public const COMENTARIOS = 'collab.comentarios';

    public const VERSAO = 'collab.versao';

    /**
     * @param  array<string, mixed>  $dados
     */
    public function __construct(
        public int $documentoId,
        public string $tipo,
        public array $dados = [],
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('documento.'.$this->documentoId);
    }

    public function broadcastAs(): string
    {
        return $this->tipo;
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->dados;
    }
}
