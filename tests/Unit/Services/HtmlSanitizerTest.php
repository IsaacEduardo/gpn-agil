<?php

namespace Tests\Unit\Services;

use App\Services\HtmlSanitizer;
use Tests\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_html_sanitizer_removes_problematic_inline_dimensions()
    {
        $sanitizer = new HtmlSanitizer();
        $inputHtml = '<p style="height: 500px; margin-top: 100px; color: red;">Texto com estilo inline</p>';

        $outputHtml = $sanitizer->sanitize($inputHtml);

        $this->assertStringNotContainsString('height:', $outputHtml);
        $this->assertStringNotContainsString('margin-top:', $outputHtml);
        $this->assertStringContainsString('color: red', $outputHtml);
    }
}
