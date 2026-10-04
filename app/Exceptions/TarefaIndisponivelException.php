<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A tarefa deixou de estar disponível para quem a tentou assumir ou concluir —
 * tipicamente porque outro técnico do grupo em concorrência foi mais rápido.
 *
 * Não é um erro do sistema: responde com a mensagem ao utilizador, 409 em JSON
 * e de volta à página com aviso nos pedidos normais. Também não vai para o log de
 * erros (ShouldntReport): cada técnico que chegasse segundo a uma tarefa deixava lá
 * um "ERROR" que não era nada.
 */
class TarefaIndisponivelException extends RuntimeException implements ShouldntReport
{
    public function render(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage(), 'error' => $this->getMessage()], 409);
        }

        return back()->with('warning', $this->getMessage());
    }
}
