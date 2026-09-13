<?php

namespace App\Notifications;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\User;
use App\Notifications\Concerns\CanonicalPayload;
use App\Notifications\Concerns\QueuedRetryPolicy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Avisa a chefia do departamento de destino (e o responsável do gabinete) de que
 * entrou um documento novo no balcão. Sem isto o fluxo só arranca quando alguém
 * se lembra de abrir a listagem.
 */
class DocumentoEntradaRegistado extends Notification implements ShouldQueue
{
    use CanonicalPayload, Queueable, QueuedRetryPolicy;

    public function __construct(
        protected DocumentoEntrada $documento,
        protected ?User $actor = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function viaQueues(): array
    {
        return [
            'database' => 'notifications',
            'broadcast' => 'notifications',
        ];
    }

    public function toArray(object $notifiable): array
    {
        $numero = sprintf('%03d/%d', $this->documento->numero_sequencial, $this->documento->ano_referencia);
        $destino = Departamento::find($this->documento->departamento_id);
        $porQuem = $this->actor?->name;

        return $this->canonicalPayload(
            title: 'Documento '.$numero.' registado para '.($destino ? $destino->nome : 'o seu departamento'),
            url: route('documentos-entradas.show', $this->documento->id),
            body: 'Assunto: '.($this->documento->assunto ?: '—')
                .($this->documento->procedencia ? ' · Procedência: '.$this->documento->procedencia : '')
                .($porQuem ? ' · registado por '.$porQuem : ''),
            priority: 'normal',
            type: 'documento_registado',
            icon: 'fas fa-inbox',
            extra: [
                'acao' => 'documento_registado',
                'documento_id' => $this->documento->id,
                'numero' => $numero,
                'assunto' => $this->documento->assunto,
                'procedencia' => $this->documento->procedencia,
                'especie' => $this->documento->classificacao_especie,
                'destino_departamento' => $destino?->nome,
                'registado_em' => optional($this->documento->data_entrada)?->toDateTimeString(),
                'por' => $porQuem,
            ],
        );
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable) + [
            'broadcasted_at' => now()->toDateTimeString(),
        ]);
    }
}
