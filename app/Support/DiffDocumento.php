<?php

namespace App\Support;

/**
 * Diferenças de texto entre duas versões de um documento, para o histórico.
 *
 * Compara o TEXTO (não o HTML): parágrafo a parágrafo e, dentro de um parágrafo
 * alterado, palavra a palavra. Devolve HTML seguro (todo o texto é escapado) com
 * <ins> para o acrescentado e <del> para o removido.
 *
 * Feito à mão, sem dependência: o sebastian/diff do projeto só existe em
 * desenvolvimento (vem com o PHPUnit).
 */
class DiffDocumento
{
    /** Acima disto (palavras × palavras) um parágrafo alterado mostra-se inteiro removido/acrescentado. */
    private const MAX_CELULAS_PALAVRAS = 250000;

    public static function html(?string $antes, ?string $depois): string
    {
        $a = self::paragrafos($antes);
        $b = self::paragrafos($depois);

        if ($a === $b) {
            return '<p class="text-muted fst-italic mb-0">Sem diferenças no texto.</p>';
        }

        $saida = [];
        $operacoes = self::lcs($a, $b);
        $removidos = [];

        // Um parágrafo removido seguido de um acrescentado é o mesmo parágrafo alterado:
        // compara-se palavra a palavra.
        foreach ($operacoes as [$tipo, $texto]) {
            if ($tipo === '-') {
                $removidos[] = $texto;

                continue;
            }
            if ($tipo === '+' && $removidos) {
                $saida[] = '<p>'.self::palavras(array_shift($removidos), $texto).'</p>';

                continue;
            }
            foreach ($removidos as $r) {
                $saida[] = '<p><del>'.e($r).'</del></p>';
            }
            $removidos = [];
            $saida[] = $tipo === '+' ? '<p><ins>'.e($texto).'</ins></p>' : '<p>'.e($texto).'</p>';
        }
        foreach ($removidos as $r) {
            $saida[] = '<p><del>'.e($r).'</del></p>';
        }

        return implode("\n", $saida);
    }

    /** @return array<int, string> */
    private static function paragrafos(?string $html): array
    {
        $html = preg_replace('#<(br|/p|/div|/li|/h[1-6]|/tr|/blockquote)\b[^>]*>#i', "\n", (string) $html);
        $texto = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return array_values(array_filter(
            array_map(fn ($l) => trim(preg_replace('/[ \t\x{00A0}]+/u', ' ', $l)), explode("\n", $texto)),
            fn ($l) => $l !== ''
        ));
    }

    private static function palavras(string $antes, string $depois): string
    {
        $a = preg_split('/(\s+)/u', $antes, -1, PREG_SPLIT_NO_EMPTY);
        $b = preg_split('/(\s+)/u', $depois, -1, PREG_SPLIT_NO_EMPTY);

        if (count($a) * count($b) > self::MAX_CELULAS_PALAVRAS) {
            return '<del>'.e($antes).'</del> <ins>'.e($depois).'</ins>';
        }

        $partes = [];
        foreach (self::lcs($a, $b) as [$tipo, $palavra]) {
            $partes[] = match ($tipo) {
                '-' => '<del>'.e($palavra).'</del>',
                '+' => '<ins>'.e($palavra).'</ins>',
                default => e($palavra),
            };
        }

        return implode(' ', $partes);
    }

    /**
     * Maior subsequência comum, devolvida como operações [' '|'-'|'+', item].
     *
     * @param  array<int, string>  $a
     * @param  array<int, string>  $b
     * @return array<int, array{0: string, 1: string}>
     */
    private static function lcs(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);
        $t = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));

        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $t[$i][$j] = $a[$i] === $b[$j] ? $t[$i + 1][$j + 1] + 1 : max($t[$i + 1][$j], $t[$i][$j + 1]);
            }
        }

        $ops = [];
        $i = $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $ops[] = [' ', $a[$i]];
                $i++;
                $j++;
            } elseif ($t[$i + 1][$j] >= $t[$i][$j + 1]) {
                $ops[] = ['-', $a[$i++]];
            } else {
                $ops[] = ['+', $b[$j++]];
            }
        }
        while ($i < $n) {
            $ops[] = ['-', $a[$i++]];
        }
        while ($j < $m) {
            $ops[] = ['+', $b[$j++]];
        }

        return $ops;
    }
}
