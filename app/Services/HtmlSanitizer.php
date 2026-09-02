<?php

namespace App\Services;

use DOMDocument;
use DOMElement;

class HtmlSanitizer
{
    /**
     * Tags permitidas
     */
    protected array $allowedTags = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'ul', 'ol', 'li', 'span',
        'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'table',
        'thead', 'tbody', 'tr', 'td', 'th', 'a', 'hr', 'sub', 'sup',
    ];

    /**
     * Atributos permitidos
     */
    protected array $allowedAttributes = [
        'class', 'style', 'href', 'target', 'rel', 'align', 'valign', 'colspan', 'rowspan', 'title',
    ];

    /**
     * Protocolos seguros para URIs
     */
    protected array $allowedProtocols = [
        'http', 'https', 'mailto', 'tel',
    ];

    /**
     * Sanitize HTML content
     */
    public function sanitize(?string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // Desabilita erros de XML temporariamente (para HTML5 que o DOMDocument pode reclamar)
        libxml_use_internal_errors(true);

        $dom = new DOMDocument;
        // Envolve em container <div> para evitar aninhamento incorreto de elementos irmãos pelo DOMDocument
        $wrappedHtml = '<div>'.mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8').'</div>';
        $dom->loadHTML($wrappedHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $this->cleanNode($dom);

        libxml_clear_errors();

        $saved = trim($dom->saveHTML());
        if (str_starts_with($saved, '<div>') && str_ends_with($saved, '</div>')) {
            $saved = substr($saved, 5, -6);
        }

        return trim($saved);
    }

    protected function cleanNode($node)
    {
        // Se for um nó de elemento (tag)
        if ($node->nodeType === XML_ELEMENT_NODE && $node instanceof DOMElement) {
            $tagName = strtolower($node->nodeName);

            // Tags ativas e perigosas que devem ser removidas inteiramente (incluindo seu conteúdo)
            $dropEntirelyTags = ['script', 'style', 'iframe', 'object', 'embed', 'applet', 'noscript', 'meta', 'link', 'svg', 'canvas', 'audio', 'video', 'base'];
            if (in_array($tagName, $dropEntirelyTags, true)) {
                $node->parentNode->removeChild($node);
                return;
            }

            // Se a tag não for permitida, substitui pelo conteúdo textual/filhos (strip tag)
            if (! in_array($tagName, $this->allowedTags, true)) {
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
                $hasBlankTarget = false;

                foreach ($node->attributes as $attr) {
                    $attrName = strtolower($attr->name);

                    if (! in_array($attrName, $this->allowedAttributes, true)) {
                        $attributesToRemove[] = $attr->name;
                        continue;
                    }

                    // Validação estrita para href (evitar javascript:, data:, vbscript:)
                    if ($attrName === 'href') {
                        if (! $this->isSafeUrl($attr->value)) {
                            $attributesToRemove[] = $attr->name;
                        }
                    }

                    // Validação de target (evitar tabnabbing)
                    if ($attrName === 'target') {
                        if (strtolower(trim($attr->value)) === '_blank') {
                            $hasBlankTarget = true;
                        } else {
                            $attributesToRemove[] = $attr->name;
                        }
                    }

                    // Sanitização de estilos inline
                    if ($attrName === 'style') {
                        $cleanedStyle = $this->sanitizeInlineStyle($attr->value);
                        if (empty($cleanedStyle)) {
                            $attributesToRemove[] = $attr->name;
                        } else {
                            $attr->value = $cleanedStyle;
                        }
                    }
                }

                foreach ($attributesToRemove as $attrName) {
                    $node->removeAttribute($attrName);
                }

                // Força rel="noopener noreferrer" se target="_blank"
                if ($hasBlankTarget) {
                    $node->setAttribute('rel', 'noopener noreferrer');
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

    /**
     * Valida se uma URL é segura contra esquemas maliciosos de injeção XSS.
     */
    public function isSafeUrl(string $url): bool
    {
        // Remove caracteres de controle e espaços
        $cleanUrl = preg_replace('/[\x00-\x1F\x7F\s]+/u', '', $url);
        if (empty($cleanUrl)) {
            return false;
        }

        // Links âncora ou caminhos relativos no mesmo domínio
        if (str_starts_with($cleanUrl, '#') || str_starts_with($cleanUrl, '/')) {
            // Previne URLs de protocolo relativo (//evil.com)
            return ! str_starts_with($cleanUrl, '//');
        }

        // Verifica o protocolo
        $colonPos = strpos($cleanUrl, ':');
        if ($colonPos !== false) {
            $scheme = strtolower(substr($cleanUrl, 0, $colonPos));
            return in_array($scheme, $this->allowedProtocols, true);
        }

        // URL sem esquema explícito relativa
        return true;
    }

    /**
     * Remove propriedades e expressões de estilo inline perigosas ou que quebrem o layout A4.
     */
    public function sanitizeInlineStyle(string $style): string
    {
        // Rejeita qualquer CSS com vetores de injeção direta
        $lowered = strtolower($style);
        if (
            str_contains($lowered, 'expression(') ||
            str_contains($lowered, 'url(') ||
            str_contains($lowered, 'behavior') ||
            str_contains($lowered, '@import') ||
            str_contains($lowered, 'javascript:') ||
            str_contains($lowered, '-moz-binding')
        ) {
            return '';
        }

        $rules = explode(';', $style);
        $cleanRules = [];
        $disallowedProps = [
            'height', 'min-height', 'max-height',
            'position', 'top', 'bottom', 'left', 'right', 'z-index',
            'margin-top', 'margin-bottom',
        ];

        foreach ($rules as $rule) {
            $parts = explode(':', $rule, 2);
            if (count($parts) === 2) {
                $prop = strtolower(trim($parts[0]));
                $val = trim($parts[1]);

                if (in_array($prop, $disallowedProps, true) || empty($val)) {
                    continue;
                }

                // Permite apenas caracteres seguros nos valores de estilo
                if (preg_match('/^[a-zA-Z0-9#%.,\s\-_\'"()]+$/', $val)) {
                    $cleanRules[] = "{$prop}: {$val}";
                }
            }
        }

        return implode('; ', $cleanRules);
    }
}
