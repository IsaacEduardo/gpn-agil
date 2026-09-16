<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A coluna url_consulta guardava um URL absoluto, fixado no dia do registo.
 * Isso deixava lá duas coisas erradas: o host da máquina que fez o registo
 * (127.0.0.1, em desenvolvimento) e, nos protocolos anteriores à página
 * pública, a rota interna, que exige login.
 *
 * O QR já não lê esta coluna — é derivado do código no momento de imprimir.
 * Esta migração existe para a coluna deixar de enganar quem a leia: passa a
 * guardar o caminho relativo, que é verdadeiro em qualquer servidor.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('documento_protocolos')
            ->select('id', 'codigo')
            ->orderBy('id')
            ->chunk(500, function ($protocolos) {
                foreach ($protocolos as $protocolo) {
                    DB::table('documento_protocolos')
                        ->where('id', $protocolo->id)
                        ->update(['url_consulta' => '/protocolo/'.$protocolo->codigo]);
                }
            });
    }

    /**
     * Sem reversão: o valor anterior era o host de uma máquina de então, que
     * não vale a pena — nem é possível — reconstruir.
     */
    public function down(): void {}
};
