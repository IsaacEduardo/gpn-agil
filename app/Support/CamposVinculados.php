<?php

namespace App\Support;

/**
 * Campos do formulário cujo valor aparece no corpo do documento (Assunto e destinatário).
 *
 * No corpo, cada valor vive dentro de um marcador:
 *     <span class="campo-vinculado campo-nome">RODRIGUES JAMBA</span>
 * Assim o formulário pode actualizar o editor a qualquer momento (JS) e o servidor
 * pode re-sincronizá-lo ao gravar, sem tocar no resto do texto.
 *
 * Usa classes e não data-*, porque o HtmlSanitizer só preserva class/style. Um campo
 * vazio nunca produz um span vazio (o TinyMCE remove-o): leva a classe `campo-vazio`
 * e um texto-guia, e é retirado ao gravar.
 */
final class CamposVinculados
{
    public const CLASSE_BASE = 'campo-vinculado';

    public const CLASSE_VAZIO = 'campo-vazio';

    /**
     * Campo do formulário => placeholder, classe do marcador e texto-guia quando vazio.
     */
    public const MAPA = [
        'titulo' => ['placeholder' => 'ASSUNTO', 'classe' => 'campo-assunto', 'guia' => '[Assunto]'],
        'destinatario_nome' => ['placeholder' => 'DESTINATARIO_NOME', 'classe' => 'campo-nome', 'guia' => '[Nome do destinatário]'],
        'destinatario_cargo' => ['placeholder' => 'DESTINATARIO_CARGO', 'classe' => 'campo-cargo', 'guia' => '[Cargo]'],
        'destinatario_orgao' => ['placeholder' => 'DESTINATARIO_ORGAO', 'classe' => 'campo-orgao', 'guia' => '[Instituição/Órgão]'],
        'destinatario_local' => ['placeholder' => 'DESTINATARIO_LOCAL', 'classe' => 'campo-local', 'guia' => '[Local]'],
    ];

    /**
     * Valores por campo, com o Local a cair na cidade da instituição quando vazio.
     *
     * @return array<string, string>
     */
    public static function valores(array $entrada, string $cidadePadrao): array
    {
        $valores = [];
        foreach (array_keys(self::MAPA) as $campo) {
            $valores[$campo] = trim((string) ($entrada[$campo] ?? ''));
        }

        if ($valores['destinatario_local'] === '') {
            $valores['destinatario_local'] = $cidadePadrao;
        }

        return $valores;
    }

    public static function marcador(string $campo, ?string $valor): string
    {
        $def = self::MAPA[$campo];
        $valor = trim((string) $valor);
        $vazio = $valor === '';

        $classes = self::CLASSE_BASE.' '.$def['classe'].($vazio ? ' '.self::CLASSE_VAZIO : '');

        return '<span class="'.$classes.'">'.e($vazio ? $def['guia'] : $valor).'</span>';
    }

    /**
     * Placeholders {{ASSUNTO}}, {{DESTINATARIO_*}} => marcadores.
     *
     * @param  array<string, string>  $valores  saída de valores()
     * @return array<string, string>
     */
    public static function placeholders(array $valores): array
    {
        $placeholders = [];
        foreach (self::MAPA as $campo => $def) {
            $marcador = self::marcador($campo, $valores[$campo] ?? '');
            $placeholders['{{'.$def['placeholder'].'}}'] = $marcador;
            $placeholders['{{ '.$def['placeholder'].' }}'] = $marcador;
        }

        return $placeholders;
    }

    /**
     * Repõe em cada marcador o valor do campo correspondente. Os campos do formulário
     * prevalecem sobre texto alterado à mão dentro do marcador.
     *
     * @param  array<string, string>  $valores  saída de valores()
     */
    public static function sincronizar(string $html, array $valores): string
    {
        foreach (self::MAPA as $campo => $def) {
            if (! array_key_exists($campo, $valores)) {
                continue;
            }

            $html = preg_replace_callback(
                self::padraoMarcador($def['classe']),
                fn () => self::marcador($campo, $valores[$campo]),
                $html
            ) ?? $html;
        }

        return $html;
    }

    /**
     * Retira os marcadores que ficaram vazios e o <br> que os segue, para que o
     * documento não fique com "[Cargo]" nem com linhas em branco.
     */
    public static function limparVazios(string $html): string
    {
        return preg_replace(self::padraoMarcador(self::CLASSE_VAZIO, '\s*(?:<br\s*\/?>)?'), '', $html) ?? $html;
    }

    /**
     * Dados para o JavaScript do formulário (mesmas classes e textos-guia).
     */
    public static function paraJs(string $cidadePadrao): array
    {
        $campos = [];
        foreach (self::MAPA as $campo => $def) {
            $campos[$campo] = ['classe' => $def['classe'], 'guia' => $def['guia']];
        }
        $campos['destinatario_local']['padrao'] = $cidadePadrao;

        return ['classeVazio' => self::CLASSE_VAZIO, 'campos' => $campos];
    }

    private static function padraoMarcador(string $classe, string $sufixo = ''): string
    {
        $classe = preg_quote($classe, '/');
        $base = preg_quote(self::CLASSE_BASE, '/');

        // Span com a classe base e a classe pedida (qualquer ordem, aspas simples ou duplas).
        return '/<span\b[^>]*\bclass\s*=\s*(["\'])(?=[^"\']*(?<![\w-])'.$base.'(?![\w-]))'
            .'(?=[^"\']*(?<![\w-])'.$classe.'(?![\w-]))[^"\']*\1[^>]*>.*?<\/span>'.$sufixo.'/is';
    }
}
