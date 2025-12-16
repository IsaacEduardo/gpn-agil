<?php

namespace App\Notifications;

use App\Models\Departamento;
use App\Models\DocumentoEncaminhamento;
use App\Models\DocumentoEntrada;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class DocumentoEncaminhadoDepartamento extends Notification implements ShouldQueue
{
    use Queueable;

    protected DocumentoEntrada $documento;

    protected DocumentoEncaminhamento $encaminhamento;

    protected User $actor;

    public function __construct(DocumentoEntrada $documento, DocumentoEncaminhamento $encaminhamento, User $actor)
    {
        $this->documento = $documento;
        $this->encaminhamento = $encaminhamento;
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
        $destino = Departamento::find($this->encaminhamento->destino_departamento_id);
        $origem = Departamento::find($this->encaminhamento->origem_departamento_id);

        return [
            'title' => 'Documento '.$numero.' encaminhado para '.($destino ? $destino->nome : 'departamento destino'),
            'acao' => 'encaminhado_interno',
            'documento_id' => $this->documento->id,
            'numero' => $numero,
            'assunto' => $this->documento->assunto,
            'procedencia' => $this->documento->procedencia,
            'origem_departamento' => $origem ? $origem->nome : null,
            'destino_departamento' => $destino ? $destino->nome : null,
            'encaminhado_em' => optional($this->encaminhamento->encaminhado_em)?->toDateTimeString(),
            'por' => $this->actor->name,
            'url' => route('documentos-entradas.show', $this->documento->id),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable) + [
            'broadcasted_at' => now()->toDateTimeString(),
        ]);
    }
}
