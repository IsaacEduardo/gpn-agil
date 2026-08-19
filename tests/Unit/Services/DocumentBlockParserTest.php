<?php

namespace Tests\Unit\Services;

use App\Services\DocumentBlockParser;
use Tests\TestCase;

class DocumentBlockParserTest extends TestCase
{
    public function test_parses_json_blocks_to_clean_html()
    {
        $parser = new DocumentBlockParser();
        $json = json_encode([
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'heading',
                    'attrs' => ['level' => 1],
                    'content' => [
                        ['type' => 'text', 'text' => 'Título de Teste']
                    ]
                ],
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Parágrafo com '],
                        ['type' => 'text', 'text' => 'negrito', 'marks' => [['type' => 'bold']]]
                    ]
                ],
                [
                    'type' => 'pageBreak'
                ]
            ]
        ]);

        $html = $parser->parseBlocksToHtml($json);

        $this->assertStringContainsString('<h1>T&iacute;tulo de Teste</h1>', $html);
        $this->assertStringContainsString('<p>Par&aacute;grafo com <strong>negrito</strong></p>', $html);
        $this->assertStringContainsString('<div class="page-break"></div>', $html);
    }

    public function test_fallback_handles_raw_html()
    {
        $parser = new DocumentBlockParser();
        $rawHtml = '<p style="height: 100px; color: blue;">Texto Simples</p>';

        $html = $parser->parseBlocksToHtml($rawHtml);

        $this->assertStringNotContainsString('height:', $html);
        $this->assertStringContainsString('color: blue', $html);
    }
}
