<?php

namespace Tests\Unit;

use App\Services\HtmlSanitizer;
use Tests\TestCase;

class SanitizerTest extends TestCase
{
    public function test_sanitizer_preserves_injected_date_line()
    {
        $sanitizer = new HtmlSanitizer;

        $html = '<div style="margin-bottom: 20px; font-family: \'Times New Roman\', serif; font-size: 12pt;">GABINETE DO GOVERNADOR, em Moçâmedes, aos 29 de Janeiro de 2026</div>';

        $result = $sanitizer->sanitize($html);

        $this->assertStringContainsString('GABINETE DO GOVERNADOR, em Moçâmedes, aos 29 de Janeiro de 2026', html_entity_decode($result, ENT_QUOTES, 'UTF-8'));
        $this->assertStringContainsString('style', $result);
    }
}
