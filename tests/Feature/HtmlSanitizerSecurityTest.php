<?php

namespace Tests\Feature;

use App\Services\HtmlSanitizer;
use Tests\TestCase;

class HtmlSanitizerSecurityTest extends TestCase
{
    protected HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new HtmlSanitizer;
    }

    public function test_strips_script_tags()
    {
        $input = '<p>Texto legítimo <script>alert("XSS")</script></p>';
        $cleaned = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('<script>', $cleaned);
        $this->assertStringNotContainsString('alert("XSS")', $cleaned);
    }

    public function test_strips_javascript_pseudo_protocol_in_href()
    {
        $input = '<a href="javascript:alert(document.cookie)">Clique aqui</a>';
        $cleaned = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('javascript:', $cleaned);
        $this->assertStringNotContainsString('href', $cleaned);
    }

    public function test_strips_data_and_vbscript_uris()
    {
        $input = '<a href="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">Link</a>';
        $cleaned = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('data:', $cleaned);
    }

    public function test_forces_rel_noopener_noreferrer_on_blank_targets()
    {
        $input = '<a href="https://governo.gov.ao" target="_blank">Portal Oficial</a>';
        $cleaned = $this->sanitizer->sanitize($input);

        $this->assertStringContainsString('rel="noopener noreferrer"', $cleaned);
    }

    public function test_strips_malicious_inline_css_expressions()
    {
        $input = '<p style="color: red; width: expression(alert(1)); background: url(javascript:alert(1))">Alerta</p>';
        $cleaned = $this->sanitizer->sanitize($input);

        $this->assertStringNotContainsString('expression', $cleaned);
        $this->assertStringNotContainsString('javascript:', $cleaned);
        $this->assertStringNotContainsString('url(', $cleaned);
    }
}
