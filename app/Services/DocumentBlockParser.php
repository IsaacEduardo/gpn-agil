<?php

namespace App\Services;

class DocumentBlockParser
{
    /**
     * Converte uma estrutura em árvore de nós (AST / JSON) do editor rico
     * em marcação HTML limpa e normalizada para compilação do layout A4.
     *
     * @param array|string $jsonOrBlocks Dados JSON estruturados do documento.
     * @return string HTML normalizado gerado a partir da AST de blocos.
     */
    public function parseBlocksToHtml($jsonOrBlocks): string
    {
        if (is_string($jsonOrBlocks)) {
            $data = json_decode($jsonOrBlocks, true);
            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
                // Fallback: se não for JSON válido, trata como HTML sanitizado direto
                return (new HtmlSanitizer)->sanitize($jsonOrBlocks);
            }
        } else {
            $data = (array) $jsonOrBlocks;
        }

        if (empty($data)) {
            return '';
        }

        return (new HtmlSanitizer)->sanitize($this->renderBlock($data));
    }

    /**
     * Renderização recursiva de cada nó do bloco Prosemirror/AST.
     */
    protected function renderBlock(array $block): string
    {
        $type = $block['type'] ?? 'paragraph';
        $content = '';

        if (! empty($block['content']) && is_array($block['content'])) {
            foreach ($block['content'] as $child) {
                if (isset($child['type']) && $child['type'] === 'text') {
                    $text = htmlspecialchars($child['text'] ?? '', ENT_QUOTES, 'UTF-8');
                    if (! empty($child['marks']) && is_array($child['marks'])) {
                        foreach ($child['marks'] as $mark) {
                            $text = $this->applyMark($text, $mark);
                        }
                    }
                    $content .= $text;
                } else {
                    $content .= $this->renderBlock($child);
                }
            }
        }

        return match ($type) {
            'doc' => $content,
            'heading' => $this->renderHeading($block, $content),
            'bulletList' => "<ul>{$content}</ul>",
            'orderedList' => "<ol>{$content}</ol>",
            'listItem' => "<li>{$content}</li>",
            'blockquote' => "<blockquote>{$content}</blockquote>",
            'table' => "<table class=\"table\">{$content}</table>",
            'tableRow' => "<tr>{$content}</tr>",
            'tableHeader' => "<th>{$content}</th>",
            'tableCell' => "<td>{$content}</td>",
            'pageBreak' => '<div class="page-break"></div>',
            default => "<p>{$content}</p>",
        };
    }

    protected function renderHeading(array $block, string $content): string
    {
        $level = min(max((int) ($block['attrs']['level'] ?? 1), 1), 6);

        return "<h{$level}>{$content}</h{$level}>";
    }

    protected function applyMark(string $text, array $mark): string
    {
        $markType = $mark['type'] ?? '';

        return match ($markType) {
            'bold' => "<strong>{$text}</strong>",
            'italic' => "<em>{$text}</em>",
            'underline' => "<u>{$text}</u>",
            'strike' => "<del>{$text}</del>",
            default => $text,
        };
    }
}
