<?php

namespace App\Events;

use App\Models\DocumentoTarefa;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento disparado quando uma nova tarefa é criada (designada).
 */
class TaskAssigned
{
    use Dispatchable, SerializesModels;

    /**
     * Cria uma nova instância do evento.
     *
     * @param  DocumentoTarefa  $tarefa
     */
    public function __construct(public DocumentoTarefa $tarefa)
    {
    }
}
