<?php

namespace App\Notifications;

use App\Models\DocumentoEncaminhamentoExterno;
use App\Models\DocumentoEntrada;
use App\Models\Gabinete;
use App\Models\User;
use App\Notifications\Concerns\CanonicalPayload;
use App\Notifications\Concerns\QueuedRetryPolicy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class DocumentoEncaminhadoExterno extends Notification implements ShouldQueue
{
    use CanonicalPayload, Queueable, QueuedRetryPolicy;

    protected DocumentoEntrada $documento;

    protected DocumentoEncaminhamentoExterno $encaminhamentoExterno;

    protected User $actor;

    public function __construct(DocumentoEntrada $documento, DocumentoEncaminhamentoExterno $encaminhamentoExterno, User $actor)
    {
        $this->documento = $documento;
        $this->encaminhamentoExterno = $encaminhamentoExterno;
        $this->actor = $actor;
    }

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
        $origem = Gabinete::find($this->encaminhamentoExterno->origem_gabinete_id);
        $destino = Gabinete::find($this->encaminhamentoExterno->destino_gabinete_id);

        return $this->canonicalPayload(
            title: 'Documento '.$numero.' encaminhado para '.($destino ? ($destino->sigla ? $destino->nome.' ('.$destino->sigla.')' : $destino->nome) : 'gabinete destino'),
            url: route('documentos-entradas.show', $this->documento->id),
            body: 'Ofício '.($this->encaminhamentoExterno->oficio_numero ?: '—').' · por '.$this->actor->name,
            priority: 'normal',
            type: 'documento_encaminhado_externo',
            icon: 'fas fa-paper-plane',
            extra: [
                'acao' => 'encaminhado_externo', // BC
                'documento_id' => $this->documento->id,
                'numero' => $numero,
                'assunto' => $this->documento->assunto,
                'procedencia' => $this->documento->procedencia,
                'origem_gabinete' => $origem ? ($origem->sigla ? $origem->nome.' ('.$origem->sigla.')' : $origem->nome) : null,
                'destino_gabinete' => $destino ? ($destino->sigla ? $destino->nome.' ('.$destino->sigla.')' : $destino->nome) : null,
                'enviado_em' => optional($this->encaminhamentoExterno->enviado_em)?->toDateTimeString(),
                'por' => $this->actor->name,
                'oficio_numero' => $this->encaminhamentoExterno->oficio_numero,
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
