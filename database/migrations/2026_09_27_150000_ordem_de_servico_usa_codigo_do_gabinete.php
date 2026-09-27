<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ordem de Serviço: o título deixa de ter o código escrito à mão (SEC.GER.GOV.PROV.HLA) e
 * passa a usar o código de ofícios do gabinete emissor ({{CODIGO_ORDEM_SERVICO}}), o mesmo
 * da referência "OS NN/CODIGO/ANO". O número vem da série OS:{código} (já não "6 + contagem").
 * Só altera o modelo se o texto ainda for o semeado.
 */
return new class extends Migration
{
    private const CODIGO = 'ORDEM_DE_SERVICO_SEC_GERAL';

    private const ANTES = '/SEC.GER.GOV.PROV.HLA/{{ ano_corrente }}';

    private const DEPOIS = '/{{CODIGO_ORDEM_SERVICO}}/{{ ano_corrente }}';

    public function up(): void
    {
        $this->trocar(self::ANTES, self::DEPOIS);
    }

    public function down(): void
    {
        $this->trocar(self::DEPOIS, self::ANTES);
    }

    private function trocar(string $de, string $para): void
    {
        $modelo = DB::table('modelo_documentos')->where('codigo', self::CODIGO)->first();
        if (! $modelo || str_contains((string) $modelo->conteudo, $para)) {
            return;
        }

        if (! str_contains((string) $modelo->conteudo, $de)) {
            Log::warning('Modelo '.self::CODIGO.' personalizado: título não actualizado para o código do gabinete.');

            return;
        }

        DB::table('modelo_documentos')->where('id', $modelo->id)->update([
            'conteudo' => str_replace($de, $para, $modelo->conteudo),
            'updated_at' => now(),
        ]);
    }
};
