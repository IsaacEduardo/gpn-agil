<?php

namespace App\Support;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use Closure;
use Illuminate\Support\Str;

/**
 * Catálogo das séries de numeração (uma linha de `sequencias_documentos` por série e ano).
 *
 *   OF:{CODIGO}       ofícios do gabinete — livro único; N/CODIGO.SIGLA_DEP/ANO ou N/CODIGO/ANO
 *   OS:{CODIGO}       ordens de serviço do gabinete — "OS NN/CODIGO/ANO"
 *   NOTA:{CODIGO}     notas do gabinete (sempre de um departamento) — "NOTA N/CODIGO.SIGLA_DEP/ANO"
 *   INF:{CODIGO}      informações/pareceres do gabinete — "INFORMAÇÃO N/CODIGO[.SIGLA_DEP]/ANO"
 *   {SIGLA}/{ESP}     restantes espécies por emissor — SIGLA/ESP/NNN/ANO
 *   ENT               livro de documentos de entrada — numero_sequencial por ano
 *
 * A chave identifica a série; formato, "maior número já emitido" e detecção de duplicados
 * derivam dela, o que permite ao ecrã "Numeração" tratar qualquer série existente.
 */
final class SeriesNumeracao
{
    public const ENTRADAS = 'ENT';

    private function __construct(
        public readonly string $chave,
        public readonly string $rotulo,
        private readonly Closure $formatador,   // fn (int $n, int $ano): string
        private readonly Closure $maiorEmitido, // fn (int $ano): int
        private readonly Closure $existe,       // fn (string $referencia, int $ano, int $n): bool
    ) {}

    public function formatar(int $numero, int $ano): string
    {
        return ($this->formatador)($numero, $ano);
    }

    /** Maior número desta série já emitido no sistema (piso para o ecrã "Numeração"). */
    public function maiorEmitido(int $ano): int
    {
        return (int) ($this->maiorEmitido)($ano);
    }

    public function existe(string $referencia, int $ano, int $numero): bool
    {
        return (bool) ($this->existe)($referencia, $ano, $numero);
    }

    /** Identificações das séries com livro próprio (não se repetem no título impresso). */
    private const IDENTIFICACOES = ['INFORMAÇÃO', 'NOTA', 'OS'];

    /**
     * Referência sem a identificação da série, para títulos que já a dizem
     * ("INFORMAÇÃO Nº 12/SEC.GOV.PROV.HLA.DLP/2026", e não "INFORMAÇÃO Nº INFORMAÇÃO 12/…").
     */
    public static function semIdentificacao(string $referencia): string
    {
        return preg_replace('/^(?:'.implode('|', self::IDENTIFICACOES).') /u', '', $referencia) ?? $referencia;
    }

    /** Número como aparece no título/quadro: 2 dígitos nas Ordens de Serviço ("Nº 08"), sem zeros nas restantes. */
    public static function numeroParaTitulo(string $referencia, int $numero): string
    {
        return str_starts_with($referencia, 'OS ') ? sprintf('%02d', $numero) : (string) $numero;
    }

    /** Referência com o número em branco (pré-visualização), no formato real da série. */
    public function formatarProvisoria(int $ano): string
    {
        $sentinela = 987654321;

        return str_replace((string) $sentinela, '___', $this->formatar($sentinela, $ano));
    }

    // ---------------------------------------------------------------- fábricas

    public static function paraDocumento(DocumentoInterno $doc): self
    {
        $departamento = $doc->departamento;
        $gabinete = $doc->gabineteEmissor();
        $especie = self::abreviaturaEspecie($doc->especie);

        if ($codigo = self::codigoOficios($gabinete)) {
            if ($especie === 'OF') {
                return self::oficios($gabinete, $departamento);
            }
            if ($especie === 'OS') {
                return self::ordensServico($gabinete);
            }
            if ($especie === 'NOTA') {
                return self::notas($gabinete, $departamento);
            }
            if ($especie === 'INF') {
                return self::informacoes($gabinete, $departamento);
            }
        }

        return self::especie($departamento, $gabinete, $doc->especie);
    }

    public static function oficios(Gabinete $gabinete, ?Departamento $departamento = null): self
    {
        $codigo = self::codigoOficios($gabinete);
        $sigla = $departamento ? self::siglaDepartamento($departamento) : null;

        return self::oficiosPorCodigo($codigo, 'Ofícios — '.$gabinete->nome, $sigla);
    }

    public static function ordensServico(Gabinete $gabinete): self
    {
        return self::ordensServicoPorCodigo(self::codigoOficios($gabinete), 'Ordens de Serviço — '.$gabinete->nome);
    }

    public static function notas(Gabinete $gabinete, ?Departamento $departamento = null): self
    {
        $sigla = $departamento ? self::siglaDepartamento($departamento) : null;

        return self::notasPorCodigo(self::codigoOficios($gabinete), 'Notas — '.$gabinete->nome, $sigla);
    }

    public static function informacoes(Gabinete $gabinete, ?Departamento $departamento = null): self
    {
        $sigla = $departamento ? self::siglaDepartamento($departamento) : null;

        return self::informacoesPorCodigo(self::codigoOficios($gabinete), 'Informações/Pareceres — '.$gabinete->nome, $sigla);
    }

    public static function especie(?Departamento $departamento, ?Gabinete $gabinete, ?DocumentoEspecie $especie): self
    {
        $sigla = $departamento ? self::siglaDepartamento($departamento) : self::siglaGabinete($gabinete);
        $esp = self::abreviaturaEspecie($especie);
        $emissor = $departamento?->nome ?? ($gabinete?->nome ?? $sigla);

        return self::especiePorChave("{$sigla}/{$esp}", ($especie?->nome ?? $esp).' — '.$emissor);
    }

    public static function entradas(): self
    {
        return new self(
            self::ENTRADAS,
            'Livro de Documentos de Entrada',
            fn (int $n) => (string) $n,
            fn (int $ano) => (int) DocumentoEntrada::withTrashed()->where('ano_referencia', $ano)->max('numero_sequencial'),
            fn (string $ref, int $ano, int $n) => DocumentoEntrada::withTrashed()
                ->where('ano_referencia', $ano)->where('numero_sequencial', $n)->exists(),
        );
    }

    /** Reconstrói a série a partir da chave gravada (para listar séries existentes). */
    public static function daChave(string $chave, ?string $rotulo = null): self
    {
        if ($chave === self::ENTRADAS) {
            return self::entradas();
        }
        if (str_starts_with($chave, 'OF:')) {
            $codigo = substr($chave, 3);

            return self::oficiosPorCodigo($codigo, $rotulo ?? 'Ofícios — '.self::nomeDoGabinete($codigo), null);
        }
        if (str_starts_with($chave, 'INF:')) {
            $codigo = substr($chave, 4);

            return self::informacoesPorCodigo($codigo, $rotulo ?? 'Informações/Pareceres — '.self::nomeDoGabinete($codigo), null);
        }
        if (str_starts_with($chave, 'NOTA:')) {
            $codigo = substr($chave, 5);

            return self::notasPorCodigo($codigo, $rotulo ?? 'Notas — '.self::nomeDoGabinete($codigo), null);
        }
        if (str_starts_with($chave, 'OS:')) {
            $codigo = substr($chave, 3);

            return self::ordensServicoPorCodigo($codigo, $rotulo ?? 'Ordens de Serviço — '.self::nomeDoGabinete($codigo));
        }

        // "{SIGLA}/{ESP}" (séries criadas antes de haver rótulo): "ESP — SIGLA".
        [$sigla, $esp] = array_pad(explode('/', $chave, 2), 2, '');

        return self::especiePorChave($chave, $rotulo ?? trim("{$esp} — {$sigla}", ' —'));
    }

    private static function nomeDoGabinete(string $codigo): string
    {
        return Gabinete::where('codigo_oficios', $codigo)->value('nome') ?? $codigo;
    }

    // ------------------------------------------------------------ construtores

    private static function oficiosPorCodigo(string $codigo, string $rotulo, ?string $siglaDep): self
    {
        $regex = '#^(\d+)/'.preg_quote($codigo, '#').'(?:\.[^/]+)?/%d$#';

        return new self(
            "OF:{$codigo}",
            $rotulo,
            fn (int $n, int $ano) => $siglaDep !== null
                ? sprintf('%d/%s.%s/%d', $n, $codigo, $siglaDep, $ano)
                : sprintf('%d/%s/%d', $n, $codigo, $ano),
            fn (int $ano) => self::maiorReferencia("%/{$codigo}%/{$ano}", sprintf($regex, $ano)),
            fn (string $ref) => DocumentoInterno::where('numero_referencia', $ref)->exists(),
        );
    }

    private static function ordensServicoPorCodigo(string $codigo, string $rotulo): self
    {
        return new self(
            "OS:{$codigo}",
            $rotulo,
            fn (int $n, int $ano) => sprintf('OS %02d/%s/%d', $n, $codigo, $ano),
            fn (int $ano) => self::maiorReferencia(
                "OS %/{$codigo}/{$ano}",
                '#^OS (\d+)/'.preg_quote($codigo, '#').'/'.$ano.'$#'
            ),
            fn (string $ref) => DocumentoInterno::where('numero_referencia', $ref)->exists(),
        );
    }

    /**
     * Livro de notas do gabinete: formato do ofício com a identificação "NOTA" (a Nota 12 e o
     * Ofício 12 do mesmo departamento têm referências diferentes). Sem sigla de departamento
     * (só no ecrã "Numeração") o exemplo sai sem sufixo.
     */
    private static function notasPorCodigo(string $codigo, string $rotulo, ?string $siglaDep): self
    {
        return new self(
            "NOTA:{$codigo}",
            $rotulo,
            fn (int $n, int $ano) => $siglaDep !== null
                ? sprintf('NOTA %d/%s.%s/%d', $n, $codigo, $siglaDep, $ano)
                : sprintf('NOTA %d/%s/%d', $n, $codigo, $ano),
            fn (int $ano) => self::maiorReferencia(
                "NOTA %/{$codigo}%/{$ano}",
                '#^NOTA (\d+)/'.preg_quote($codigo, '#').'(?:\.[^/]+)?/'.$ano.'$#'
            ),
            fn (string $ref) => DocumentoInterno::where('numero_referencia', $ref)->exists(),
        );
    }

    /**
     * Livro de informações/pareceres do gabinete: formato do ofício com a identificação
     * "INFORMAÇÃO"; emitem os departamentos (com sufixo) e o próprio gabinete (sem sufixo).
     */
    private static function informacoesPorCodigo(string $codigo, string $rotulo, ?string $siglaDep): self
    {
        return new self(
            "INF:{$codigo}",
            $rotulo,
            fn (int $n, int $ano) => $siglaDep !== null
                ? sprintf('INFORMAÇÃO %d/%s.%s/%d', $n, $codigo, $siglaDep, $ano)
                : sprintf('INFORMAÇÃO %d/%s/%d', $n, $codigo, $ano),
            fn (int $ano) => self::maiorReferencia(
                "INFORMAÇÃO %/{$codigo}%/{$ano}",
                '#^INFORMAÇÃO (\d+)/'.preg_quote($codigo, '#').'(?:\.[^/]+)?/'.$ano.'$#u'
            ),
            fn (string $ref) => DocumentoInterno::where('numero_referencia', $ref)->exists(),
        );
    }

    private static function especiePorChave(string $chave, string $rotulo): self
    {
        return new self(
            $chave,
            $rotulo,
            fn (int $n, int $ano) => sprintf('%s/%03d/%d', $chave, $n, $ano),
            fn (int $ano) => self::maiorReferencia(
                "{$chave}/%/{$ano}",
                '#^'.preg_quote("{$chave}/", '#').'(\d+)/'.$ano.'$#'
            ),
            fn (string $ref) => DocumentoInterno::where('numero_referencia', $ref)->exists(),
        );
    }

    /**
     * O LIKE só pré-filtra (o "_" de siglas como SEC_GERAL é curinga no LIKE);
     * a expressão regular decide o que conta.
     */
    private static function maiorReferencia(string $like, string $regex): int
    {
        return (int) DocumentoInterno::where('numero_referencia', 'like', $like)
            ->pluck('numero_referencia')
            ->map(fn ($ref) => preg_match($regex, (string) $ref, $m) ? (int) $m[1] : 0)
            ->max();
    }

    // ----------------------------------------------------------------- siglas

    public static function siglaDepartamento(?Departamento $departamento): string
    {
        if (! $departamento) {
            return 'DEP';
        }

        return filled($departamento->sigla)
            ? mb_strtoupper(trim($departamento->sigla))
            : (substr(strtoupper(Str::slug($departamento->nome, '')), 0, 3) ?: 'DEP');
    }

    public static function siglaGabinete(?Gabinete $gabinete): string
    {
        if (! $gabinete) {
            return 'GAB';
        }

        return filled($gabinete->sigla)
            ? mb_strtoupper(trim($gabinete->sigla))
            : (substr(strtoupper(Str::slug($gabinete->nome, '')), 0, 3) ?: 'GAB');
    }

    public static function abreviaturaEspecie(?DocumentoEspecie $especie): string
    {
        $nome = $especie ? strtoupper(Str::slug($especie->nome, '')) : 'DOC';

        return match ($nome) {
            'MEMORANDO' => 'MEMO',
            'OFICIO' => 'OF',
            'DESPACHO' => 'DESP',
            'CIRCULAR' => 'CIRC',
            'NOTA' => 'NOTA',
            'ORDEMDESERVICO', 'ORDEM' => 'OS',
            'INFORMACAOPARECER', 'INFORMACAO' => 'INF',
            default => substr($nome, 0, 4),
        };
    }

    public static function codigoOficios(?Gabinete $gabinete): ?string
    {
        $codigo = $gabinete ? trim((string) $gabinete->codigo_oficios, " .\t\n\r\0\x0B") : '';

        return $codigo !== '' ? mb_strtoupper($codigo) : null;
    }
}
