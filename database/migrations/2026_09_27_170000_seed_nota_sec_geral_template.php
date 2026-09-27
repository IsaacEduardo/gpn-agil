<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Modelo "Nota (Secretaria Geral)": cópia do conteúdo actual do Ofício (Secretaria Geral),
 * com o bloco de assinatura do Chefe de Departamento designado para o departamento emissor.
 * Numeração: livro de notas do gabinete (NOTA N/CODIGO.SIGLA_DEP/ANO — SeriesNumeracao).
 */
return new class extends Migration
{
    private const CODIGO = 'NOTA_SEC_GERAL';

    private const ORIGEM = 'OFICIO_SEC_GERAL';

    /** Assinatura do ofício → assinatura da nota (e o texto-guia do corpo e a datação). */
    private const TROCAS = [
        '{{ cargo_signatario }}' => 'O Chefe de Departamento',
        '{{ nome_signatario }}' => '{{CHEFE_DEPARTAMENTO_NOME}}',
        '[escreva aqui o corpo do ofício]' => '[escreva aqui o corpo da nota]',
        // Datação pelo departamento emissor.
        '{{GABINETE_INSTITUICAO}}' => '{{DEPARTAMENTO_GABINETE_INSTITUICAO}}',
    ];

    public function up(): void
    {
        if (DB::table('modelo_documentos')->where('codigo', self::CODIGO)->exists()) {
            return;
        }

        $oficio = DB::table('modelo_documentos')->where('codigo', self::ORIGEM)->first();
        if (! $oficio) {
            Log::warning('Modelo '.self::CODIGO.' não criado: não existe o modelo '.self::ORIGEM.'.');

            return;
        }

        $conteudo = (string) $oficio->conteudo;
        foreach (array_keys(self::TROCAS) as $marcador) {
            if (substr_count($conteudo, $marcador) !== 1) {
                Log::warning('Modelo '.self::CODIGO.' não criado: o bloco de assinatura do '.self::ORIGEM.' foi personalizado.');

                return;
            }
        }

        $especieId = DB::table('documento_especies')->where('nome', 'Nota')->value('id');
        if (! $especieId) {
            $especieId = DB::table('documento_especies')->insertGetId([
                'nome' => 'Nota',
                'ativo' => true,
                'ordem' => (int) DB::table('documento_especies')->max('ordem') + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('modelo_documentos')->insert([
            'nome' => 'Nota (Secretaria Geral)',
            'codigo' => self::CODIGO,
            'documento_especie_id' => $especieId,
            'conteudo' => strtr($conteudo, self::TROCAS),
            'campos_dinamicos' => null,
            'ativo' => true,
            'user_id' => DB::table('users')->value('id') ?? 1,
            'gabinete_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::forget('documento_especies_names');
    }

    public function down(): void
    {
        DB::table('modelo_documentos')->where('codigo', self::CODIGO)->delete();
        Cache::forget('documento_especies_names');
    }
};
