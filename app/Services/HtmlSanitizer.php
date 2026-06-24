<?php

namespace App\Services;

use DOMDocument;

class HtmlSanitizer
{
    /**
     * Tags permitidas
     */
    protected array $allowedTags = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'span',
        'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'table',
        'thead', 'tbody', 'tr', 'td', 'th',
    ];

    /**
     * Atributos permitidos
     */
    protected array $allowedAttributes = [
        'class', 'style', 'href', 'target', 'align', 'valign', 'colspan', 'rowspan',
    ];

    /**
     * Sanitize HTML content
     */
    public function sanitize(?string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // Desabilita erros de XML temporariamente (para HTML5 válido que o DOMDocument pode reclamar)
        libxml_use_internal_errors(true);

        $dom = new DOMDocument;
        // Hack para UTF-8
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $this->cleanNode($dom);

        libxml_clear_errors();

        return $dom->saveHTML();
    }

    protected function cleanNode($node)
    {
        // Se for um nó de elemento (tag)
        if ($node->nodeType === XML_ELEMENT_NODE) {
            // Se a tag não for permitida, substitui pelo conteúdo (strip tag)
            if (! in_array(strtolower($node->nodeName), $this->allowedTags)) {
                $fragment = $node->ownerDocument->createDocumentFragment();
                while ($node->childNodes->length > 0) {
                    $fragment->appendChild($node->childNodes->item(0));
                }
                $node->parentNode->replaceChild($fragment, $node);
                // Re-processa o fragmento inserido
                $this->cleanNode($fragment);

                return;
            }

            // Limpa atributos
            if ($node->hasAttributes()) {
                $attributesToRemove = [];
                foreach ($node->attributes as $attr) {
                    if (! in_array(strtolower($attr->name), $this->allowedAttributes)) {
                        $attributesToRemove[] = $attr->name;
                    }

                    // Validação extra para href (evitar javascript:)
                    if (strtolower($attr->name) === 'href') {
                        $value = strtolower(trim($attr->value));
                        if (strpos($value, 'javascript:') === 0 || strpos($value, 'vbscript:') === 0) {
                            $attributesToRemove[] = $attr->name;
                        }
                    }
                }
                foreach ($attributesToRemove as $attrName) {
                    $node->removeAttribute($attrName);
                }
            }
        }

        // Recursão para filhos
        if ($node->hasChildNodes()) {
            // Itera de trás para frente para evitar problemas ao remover nós
            for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
                $this->cleanNode($node->childNodes->item($i));
            }
        }
    }
}
