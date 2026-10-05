<?php

namespace App\Services;

use App\Models\DadosInstituicao;
use App\Models\Departamento;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Support\SeriesNumeracao;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Numeração dos documentos (internos e livro de entrada), sobre o catálogo SeriesNumeracao.
 *
 * O número é reservado numa linha de `sequencias_documentos` (série + ano) bloqueada com
 * lockForUpdate. Quem chama deve gravar o documento na mesma transacção, para que o
 * bloqueio só seja libertado depois de a referência existir. A 1 de Janeiro (hora de
 * Luanda) cada série recomeça em 1, por ser uma linha nova.
 *
 * Os livros do gabinete (Ofício, OS, Nota, Informação/Parecer) só são numerados a partir de
 * DadosInstituicao::numeracao_gabinete_desde; antes dessa data o documento grava-se sem
 * número (numero_referencia NULL) e com o número em branco, para preencher à mão.
 */
class NumeracaoDocumentoService
{
    /** Fuso oficial: o ano das séries muda à meia-noite de Luanda, não à de UTC. */
    public const FUSO = 'Africa/Luanda';

    public static function anoCorrente(): int
    {
        return (int) now(self::FUSO)->year;
    }

    /** Data (Luanda) a partir da qual os livros do gabinete são numerados; null = sempre. */
    public static function inicioNumeracaoGabinete(): ?CarbonImmutable
    {
        $data = rescue(fn () => DadosInstituicao::query()->value('numeracao_gabinete_desde'), null, false);

        // Só o dia conta (o cast devolve-o à meia-noite UTC): começa à meia-noite de Luanda.
        $dia = $data instanceof \DateTimeInterface ? $data->format('Y-m-d') : (string) $data;

        return $data ? CarbonImmutable::parse($dia, self::FUSO)->startOfDay() : null;
    }

    /** Se a série é numerada pelo sistema hoje (as restantes séries são-no sempre). */
    public function numeracaoAutomatica(SeriesNumeracao $serie): bool
    {
        if (! $serie->livroDoGabinete()) {
            return true;
        }

        $inicio = self::inicioNumeracaoGabinete();

        return $inicio === null || now(self::FUSO)->greaterThanOrEqualTo($inicio);
    }

    public function gerar(DocumentoInterno $doc): ?string
    {
        return $this->reservarParaDocumento($doc)['referencia'];
    }

    /**
     * Sem numeração automática (livro do gabinete antes da data de início) não se reserva
     * nada: referencia e numero vêm a null e `provisoria` traz o número em branco.
     *
     * @return array{referencia: ?string, numero: ?int, provisoria: string}
     */
    public function reservarParaDocumento(DocumentoInterno $doc): array
    {
        $serie = SeriesNumeracao::paraDocumento($doc);
        $ano = self::anoCorrente();
        $provisoria = $serie->formatarProvisoria($ano);

        if (! $this->numeracaoAutomatica($serie)) {
            return ['referencia' => null, 'numero' => null, 'provisoria' => $provisoria];
        }

        return $this->reservar($serie, $ano) + ['provisoria' => $provisoria];
    }

    /** Próximo numero_sequencial do livro de entrada (números de apagados não são reutilizados). */
    public function reservarEntrada(int $ano): int
    {
        return $this->reservar(SeriesNumeracao::entradas(), $ano)['numero'];
    }

    /**
     * @return array{referencia: string, numero: int}
     */
    public function reservar(SeriesNumeracao $serie, int $ano): array
    {
        return DB::transaction(function () use ($serie, $ano) {
            $linha = $this->linhaBloqueada($serie, $ano);

            $numero = (int) $linha->ultimo_numero;
            do {
                $numero++;
                $referencia = $serie->formatar($numero, $ano);
            } while ($serie->existe($referencia, $ano, $numero));

            DB::table('sequencias_documentos')->where('id', $linha->id)
                ->update(['ultimo_numero' => $numero, 'updated_at' => now()]);

            return ['referencia' => $referencia, 'numero' => $numero];
        });
    }

    /**
     * Ecrã "Numeração": último número emitido fora do sistema. Nunca abaixo do maior já
     * emitido no sistema (impede duplicados); pode corrigir-se para baixo até esse mínimo.
     *
     * @return array{anterior: int, novo: int}
     *
     * @throws ValidationException
     */
    public function definirUltimoNumero(SeriesNumeracao $serie, int $ano, int $valor): array
    {
        return DB::transaction(function () use ($serie, $ano, $valor) {
            $linha = $this->linhaBloqueada($serie, $ano);
            $minimo = $serie->maiorEmitido($ano);

            if ($valor < $minimo) {
                throw ValidationException::withMessages([
                    'ultimo_numero' => "O valor não pode ser inferior a {$minimo}, o maior número já emitido no sistema nesta série.",
                ]);
            }

            DB::table('sequencias_documentos')->where('id', $linha->id)
                ->update(['ultimo_numero' => $valor, 'updated_at' => now()]);

            return ['anterior' => (int) $linha->ultimo_numero, 'novo' => $valor];
        });
    }

    /** Linha da série/ano bloqueada; criada (a partir do maior emitido) se ainda não existir. */
    private function linhaBloqueada(SeriesNumeracao $serie, int $ano): object
    {
        $tabela = fn () => DB::table('sequencias_documentos')->where('chave', $serie->chave)->where('ano', $ano);

        if (! $tabela()->exists()) {
            // Arranca do maior número já emitido, para não colidir com referências antigas.
            DB::table('sequencias_documentos')->insertOrIgnore([
                'chave' => $serie->chave,
                'ano' => $ano,
                'rotulo' => $serie->rotulo,
                'ultimo_numero' => $serie->maiorEmitido($ano),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $linha = $tabela()->lockForUpdate()->first();

        if ($linha->rotulo === null) {
            DB::table('sequencias_documentos')->where('id', $linha->id)->update(['rotulo' => $serie->rotulo]);
        }

        return $linha;
    }

    // ------------------------------------------ referências e siglas (delegam no catálogo)

    /**
     * Referência mostrada antes de o número existir (pré-visualização),
     * no mesmo formato da definitiva e com o número em branco.
     */
    public function referenciaProvisoria(?Departamento $departamento, ?Gabinete $gabinete = null): string
    {
        $ano = self::anoCorrente();
        $gabinete ??= $departamento?->gabinete;
        $sigla = $this->siglaEmissor($departamento, $gabinete);
        $codigo = $this->codigoOficios($gabinete);

        if ($codigo !== null) {
            return $departamento ? "___/{$codigo}.{$sigla}/{$ano}" : "___/{$codigo}/{$ano}";
        }

        return "{$sigla}/OF/___/{$ano}";
    }

    /** Sigla de quem emite: o departamento ou, se emitido pelo gabinete, o gabinete. */
    public function siglaEmissor(?Departamento $departamento, ?Gabinete $gabinete): string
    {
        return $departamento ? $this->siglaDepartamento($departamento) : $this->siglaGabinete($gabinete);
    }

    public function siglaDepartamento(?Departamento $departamento): string
    {
        return SeriesNumeracao::siglaDepartamento($departamento);
    }

    public function siglaGabinete(?Gabinete $gabinete): string
    {
        return SeriesNumeracao::siglaGabinete($gabinete);
    }

    public function abreviaturaEspecie(?DocumentoEspecie $especie): string
    {
        return SeriesNumeracao::abreviaturaEspecie($especie);
    }

    public function codigoOficios(?Gabinete $gabinete): ?string
    {
        return SeriesNumeracao::codigoOficios($gabinete);
    }
}
