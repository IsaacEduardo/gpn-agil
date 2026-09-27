<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ofício (Secretaria Geral): o bloco do destinatário passa a ter nome e cargo, uma linha
 * por campo (separadas por <br>, para que as linhas vazias sejam retiradas ao gravar).
 *
 * Só altera esse bloco e só se ainda estiver como foi semeado; se um administrador o
 * tiver personalizado, deixa-o e regista um aviso.
 */
return new class extends Migration
{
    private const CODIGO = 'OFICIO_SEC_GERAL';

    private const ANTES = "        Ao<br>\n"
        ."        {{DESTINATARIO_ORGAO}}<br>\n"
        ."        <span style=\"text-decoration: underline; text-transform: uppercase; padding-left: 60px;\">{{DESTINATARIO_LOCAL}}</span>\n";

    // - O recuo do Local passa para um <div>: o Dompdf ignora o padding de um span que
    //   contém outro span (o marcador do campo).
    // - As linhas "Ao … órgão" ficam no seu próprio <div>: um <div> que misturasse texto
    //   solto com o <div> do Local obrigaria o editor colaborativo a envolver o texto num
    //   <p>, cuja margem afastava o Local.
    private const DEPOIS = "        <div>Ao<br>\n"
        ."        {{DESTINATARIO_NOME}}<br>\n"
        ."        {{DESTINATARIO_CARGO}}<br>\n"
        ."        {{DESTINATARIO_ORGAO}}</div>\n"
        ."        <div style=\"padding-left: 60px;\"><span style=\"text-decoration: underline; text-transform: uppercase;\">{{DESTINATARIO_LOCAL}}</span></div>\n";

    public function up(): void
    {
        $this->trocar(self::ANTES, self::DEPOIS, '{{DESTINATARIO_NOME}}');
    }

    public function down(): void
    {
        $this->trocar(self::DEPOIS, self::ANTES, null);
    }

    private function trocar(string $de, string $para, ?string $jaAplicado): void
    {
        $modelo = DB::table('modelo_documentos')->where('codigo', self::CODIGO)->first();
        if (! $modelo) {
            return;
        }

        $conteudo = str_replace("\r\n", "\n", (string) $modelo->conteudo);

        if ($jaAplicado !== null && str_contains($conteudo, $jaAplicado)) {
            return;
        }

        if (! str_contains($conteudo, $de)) {
            Log::warning('Modelo '.self::CODIGO.' personalizado: bloco do destinatário não actualizado.');

            return;
        }

        DB::table('modelo_documentos')->where('id', $modelo->id)->update([
            'conteudo' => str_replace($de, $para, $conteudo),
            'updated_at' => now(),
        ]);
    }
};
