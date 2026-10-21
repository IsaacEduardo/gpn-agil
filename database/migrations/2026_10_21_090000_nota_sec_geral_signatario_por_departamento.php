<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Nota (Secretaria Geral): as Notas do Expediente são assinadas pelo Secretário e datadas
 * pela Secretaria; as dos outros departamentos continuam com o Chefe de Departamento.
 * O cargo, o nome e a datação fixos do modelo passam a marcadores resolvidos pelo
 * departamento emissor (Departamento::signatarioDaNota).
 *
 * Só altera o modelo se ainda estiver como foi semeado; se um administrador o tiver
 * personalizado, deixa-o e regista um aviso.
 */
return new class extends Migration
{
    private const CODIGO = 'NOTA_SEC_GERAL';

    private const TROCAS = [
        '<div style="font-weight: bold;">O Chefe de Departamento</div>' => '<div style="font-weight: bold;">{{NOTA_CARGO_SIGNATARIO}}</div>',
        '{{CHEFE_DEPARTAMENTO_NOME}}' => '{{NOTA_NOME_SIGNATARIO}}',
        '{{DEPARTAMENTO_GABINETE_INSTITUICAO}}' => '{{NOTA_DATACAO}}',
    ];

    public function up(): void
    {
        $this->trocar(self::TROCAS);
    }

    public function down(): void
    {
        $this->trocar(array_flip(self::TROCAS));
    }

    private function trocar(array $trocas): void
    {
        $modelo = DB::table('modelo_documentos')->where('codigo', self::CODIGO)->first();
        if (! $modelo) {
            return;
        }

        $conteudo = (string) $modelo->conteudo;
        foreach (array_keys($trocas) as $marcador) {
            if (substr_count($conteudo, $marcador) !== 1) {
                Log::warning('Modelo '.self::CODIGO.' personalizado: assinatura e datação da Nota não actualizadas.');

                return;
            }
        }

        DB::table('modelo_documentos')->where('id', $modelo->id)->update([
            'conteudo' => strtr($conteudo, $trocas),
            'updated_at' => now(),
        ]);
    }
};
