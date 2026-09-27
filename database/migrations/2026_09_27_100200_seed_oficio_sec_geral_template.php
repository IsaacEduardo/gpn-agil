<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Modelo "Ofício (Secretaria Geral)", assinado pelo Chefe de Gabinete / Secretário Geral.
 *
 * Só o corpo: o cabeçalho (insígnia, República, Governo, Gabinete) e o rodapé
 * estacionário são colocados pelo papel (documentos_internos/partials/paper).
 * Estilos limitados ao que o HtmlSanitizer preserva (sem margin-top/bottom, height,
 * position) e tabela em vez de flex, por causa do Dompdf.
 */
return new class extends Migration
{
    private const CODIGO = 'OFICIO_SEC_GERAL';

    public function up(): void
    {
        $especieId = DB::table('documento_especies')->where('nome', 'Ofício')->value('id');
        if (! $especieId) {
            $especieId = DB::table('documento_especies')->insertGetId([
                'nome' => 'Ofício',
                'ativo' => true,
                'ordem' => (int) DB::table('documento_especies')->max('ordem') + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $userId = DB::table('users')->value('id') ?? 1;

        $modelo = [
            'nome' => 'Ofício (Secretaria Geral)',
            'codigo' => self::CODIGO,
            'documento_especie_id' => $especieId,
            'conteudo' => $this->conteudo(),
            'campos_dinamicos' => json_encode(['cargo_signatario' => 'text']),
            'ativo' => true,
            'user_id' => $userId,
            'gabinete_id' => null,
            'updated_at' => now(),
        ];

        $existente = DB::table('modelo_documentos')->where('codigo', self::CODIGO)->first();

        if ($existente) {
            DB::table('modelo_documentos')->where('id', $existente->id)->update($modelo);
        } else {
            DB::table('modelo_documentos')->insert($modelo + ['created_at' => now()]);
        }

        Cache::forget('documento_especies_names');
    }

    public function down(): void
    {
        DB::table('modelo_documentos')->where('codigo', self::CODIGO)->delete();
        Cache::forget('documento_especies_names');
    }

    private function conteudo(): string
    {
        return <<<'HTML'
<div style="font-family: 'Times New Roman', Times, serif; font-size: 12pt; line-height: 1.5; color: #111111;">

    <div style="margin: 10px 0 28px 55%; text-align: left;">
        Ao<br>
        {{DESTINATARIO_ORGAO}}<br>
        <span style="text-decoration: underline; text-transform: uppercase; padding-left: 60px;">{{DESTINATARIO_LOCAL}}</span>
    </div>

    <table style="width: 100%; border-collapse: collapse; margin: 0 0 26px 0; font-size: 11pt;">
        <tr>
            <td style="width: 20%; padding: 0 6px 2px 0;">Sua Referência</td>
            <td style="width: 20%; padding: 0 6px 2px 0;">Sua Comunicação</td>
            <td style="width: 40%; padding: 0 6px 2px 0;">Nossa Referência</td>
            <td style="width: 20%; padding: 0 0 2px 0;">Nossa Comunicação</td>
        </tr>
        <tr>
            <td style="padding: 2px 6px 0 0;">{{SUA_REFERENCIA}}</td>
            <td style="padding: 2px 6px 0 0;">{{SUA_COMUNICACAO}}</td>
            <td style="padding: 2px 6px 0 0; white-space: nowrap; font-weight: bold;">{{NOSSA_REFERENCIA}}</td>
            <td style="padding: 2px 0 0 0;">{{DATA_ATUAL}}</td>
        </tr>
    </table>

    <p style="margin: 0 0 16px 0;">Assunto: <strong style="text-transform: uppercase;">{{ASSUNTO}}</strong></p>

    <p style="margin: 0 0 12px 0;">Os nossos melhores cumprimentos.</p>

    <p style="margin: 0 0 12px 0; text-align: justify;">Para os devidos efeitos, [escreva aqui o corpo do ofício].</p>

    <p style="margin: 28px 0 30px 0; text-align: justify;"><strong>{{GABINETE_INSTITUICAO}}</strong>, no {{INSTITUICAO_LOCAL}}, aos {{DATA_EXTENSO}}.</p>

    <div style="text-align: center; margin: 0 auto; width: 320px;">
        <div style="font-weight: bold;">{{ cargo_signatario }}</div>
        <div style="padding-top: 45px; font-weight: bold;">{{ nome_signatario }}</div>
    </div>

</div>
HTML;
    }
};
