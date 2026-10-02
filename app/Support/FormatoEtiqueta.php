<?php

namespace App\Support;

/**
 * Formatos do rolo de etiquetas do protocolo de entrada.
 *
 * A etiqueta sai em PDF com o tamanho exacto do rolo e é impressa pelo driver
 * do Windows — assim serve qualquer marca de térmica (Zebra, TSC, Godex,
 * Argox, Elgin). O driver de cada posto tem de ter o mesmo tamanho de papel,
 * em Paisagem; o PDF não consegue impor isso.
 *
 * Definição única: a validação do formulário, o <select> e a geração do PDF
 * leem daqui.
 */
final class FormatoEtiqueta
{
    public const PADRAO = '100x50';

    /** Distância mínima entre a borda do papel e qualquer conteúdo. */
    public const MARGEM_MM = 2.5;

    private const FORMATOS = [
        '100x50' => ['largura' => 100, 'altura' => 50, 'rotulo' => '100 × 50 mm (padrão)', 'compacto' => false],
        '100x60' => ['largura' => 100, 'altura' => 60, 'rotulo' => '100 × 60 mm', 'compacto' => false],
        '60x40' => ['largura' => 60, 'altura' => 40, 'rotulo' => '60 × 40 mm (compacta)', 'compacto' => true],
    ];

    private function __construct(
        public readonly string $chave,
        public readonly float $larguraMm,
        public readonly float $alturaMm,
        public readonly string $rotulo,
        public readonly bool $compacto,
    ) {}

    /** Valor inválido ou vazio cai para o padrão: a etiqueta tem sempre de sair. */
    public static function de(?string $chave): self
    {
        $chave = isset(self::FORMATOS[$chave ?? '']) ? $chave : self::PADRAO;
        $f = self::FORMATOS[$chave];

        return new self($chave, $f['largura'], $f['altura'], $f['rotulo'], $f['compacto']);
    }

    public static function daInstituicao(): self
    {
        return self::de(CabecalhoDocumento::instituicao()->etiqueta_formato);
    }

    /** @return array<string, string> chave => rótulo */
    public static function opcoes(): array
    {
        return array_map(fn (array $f) => $f['rotulo'], self::FORMATOS);
    }

    /** @return list<string> */
    public static function chaves(): array
    {
        return array_keys(self::FORMATOS);
    }

    /**
     * Tamanho do papel para o dompdf, em pontos. Largura maior do que a altura
     * e sem 'landscape': com 'landscape' o dompdf trocaria os eixos.
     *
     * @return array{0: int, 1: int, 2: float, 3: float}
     */
    public function tamanhoPapel(): array
    {
        return [0, 0, self::pt($this->larguraMm), self::pt($this->alturaMm)];
    }

    /** Lado do QR code: ≥ 18 mm, o mínimo seguro para leitura a 203 dpi. */
    public function ladoQrMm(): float
    {
        return match ($this->chave) {
            '100x60' => 26,
            '60x40' => 20,
            default => 22,
        };
    }

    public static function pt(float $mm): float
    {
        return round($mm * 72 / 25.4, 2);
    }
}
