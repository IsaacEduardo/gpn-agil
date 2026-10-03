<?php

namespace App\Exceptions;

use Illuminate\Http\Request;
use RuntimeException;

/**
 * A tarefa deixou de estar disponível para quem a tentou assumir ou concluir —
 * tipicamente porque outro técnico do grupo em concorrência foi mais rápido.
 *
 * Não é um erro do sistema: responde com a mensagem ao utilizador, 409 em JSON
 * e de volta à página com aviso nos pedidos normais.
 */
class TarefaIndisponivelException extends RuntimeException
{
    public function render(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage(), 'error' => $this->getMessage()], 409);
        }

        return back()->with('warning', $this->getMessage());
    }
}
