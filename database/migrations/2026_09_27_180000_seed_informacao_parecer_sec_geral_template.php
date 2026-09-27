<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Espécie "Informação/Parecer" e modelo "Informação/Parecer (Secretaria Geral)", assinado pelo
 * Chefe de Gabinete (Secretário Geral). Emitem os departamentos da Secretaria Geral e o próprio
 * gabinete; livro próprio (INF:{código}) — "INFORMAÇÃO N/CODIGO[.SIGLA_DEP]/ANO".
 *
 * O quadro Parecer/Despacho fica em branco para o superior preencher à mão; o sistema preenche
 * só o Nº (o mesmo do título) e a Data. A altura do quadro é dada por padding: o HtmlSanitizer
 * remove height/min-height/margin-top/margin-bottom.
 */
return new class extends Migration
{
    private const CODIGO = 'INFORMACAO_PARECER_SEC_GERAL';

    private const ESPECIE = 'Informação/Parecer';

    public function up(): void
    {
        $especieId = DB::table('documento_especies')->where('nome', self::ESPECIE)->value('id');
        if (! $especieId) {
            $especieId = DB::table('documento_especies')->insertGetId([
                'nome' => self::ESPECIE,
                'ativo' => true,
                'ordem' => (int) DB::table('documento_especies')->max('ordem') + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $modelo = [
            'nome' => 'Informação/Parecer (Secretaria Geral)',
            'codigo' => self::CODIGO,
            'documento_especie_id' => $especieId,
            'conteudo' => $this->conteudo(),
            'campos_dinamicos' => json_encode(['numero_processo' => 'text', 'cargo_signatario' => 'text']),
            'ativo' => true,
            'user_id' => DB::table('users')->value('id') ?? 1,
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

        // A espécie só é removida se nenhum documento a usar.
        $especieId = DB::table('documento_especies')->where('nome', self::ESPECIE)->value('id');
        if ($especieId && ! DB::table('documento_internos')->where('documento_especie_id', $especieId)->exists()
            && ! DB::table('modelo_documentos')->where('documento_especie_id', $especieId)->exists()) {
            DB::table('documento_especies')->where('id', $especieId)->delete();
        }

        Cache::forget('documento_especies_names');
    }

    private function conteudo(): string
    {
        return <<<'HTML'
<div style="font-family: 'Times New Roman', Times, serif; font-size: 12pt; line-height: 1.5; color: #111111;">

    <table style="width: 100%; border-collapse: collapse; border-bottom: 1.5px solid #111111; margin: 0 0 22px 0;">
        <tr>
            <td style="width: 50%; border-right: 1.5px solid #111111; padding: 95px 12px 115px 0; vertical-align: middle;">PARECER:</td>
            <td style="width: 50%; padding: 0 0 12px 14px; vertical-align: top;">
                <div style="margin: 0 0 0 45%; line-height: 1.4; padding: 0 0 60px 0;">
                    Nº <span style="text-decoration: underline;">{{NUMERO_DOCUMENTO}}</span><br>
                    Proc. {{ numero_processo }}<br>
                    Data: {{DATA_ATUAL}}
                </div>
                <div style="text-align: center; padding: 0 0 95px 0;">DESPACHO</div>
            </td>
        </tr>
    </table>

    <p style="margin: 0 0 24px 0; text-align: center; font-weight: bold;">INFORMAÇÃO Nº {{REFERENCIA_TITULO}}</p>

    <div style="margin: 0 0 24px 55%; text-align: left;">
        <div>À<br>
        {{DESTINATARIO_NOME}}<br>
        {{DESTINATARIO_CARGO}}<br>
        {{DESTINATARIO_ORGAO}}</div>
        <div style="padding-left: 60px;"><span style="text-decoration: underline; text-transform: uppercase;">{{DESTINATARIO_LOCAL}}</span></div>
    </div>

    <p style="margin: 0 0 6px 0;">Os nossos melhores e respeitosos cumprimentos:</p>

    <p style="margin: 0 0 6px 0;">Excelência;</p>

    <p style="margin: 0 0 12px 0; text-align: justify;">[escreva aqui o conteúdo da informação]</p>

    <p style="margin: 0 0 4px 0;">Pelo que;</p>

    <p style="margin: 0 0 12px 0;">Submetemos à consideração superior.</p>

    <p style="margin: 28px 0 30px 0; text-align: justify;"><strong>{{GABINETE_INSTITUICAO}}</strong>, no {{INSTITUICAO_LOCAL}}, aos {{DATA_EXTENSO}}.</p>

    <div style="text-align: center; margin: 0 auto; width: 320px;">
        <div style="font-weight: bold;">{{ cargo_signatario }}</div>
        <div style="padding-top: 45px; font-weight: bold;">{{ nome_signatario }}</div>
    </div>

</div>
HTML;
    }
};
