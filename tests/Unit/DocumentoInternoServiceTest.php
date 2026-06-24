<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\DocumentoInternoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase; // Use TestCase to have application context (helpers like now())

class DocumentoInternoServiceTest extends TestCase
{
    // use RefreshDatabase; // Not needed if we mock User/Models

    public function test_processar_template_injects_date_line_before_signature()
    {
        $service = new DocumentoInternoService;

        // Mock User
        $user = new User;
        $user->name = 'João Teste';
        // We need department/cabinet relations or they will be null.
        // For unit test without DB, we can mock or just accept defaults.
        // Service handles nulls: 'GOVERNO PROVINCIAL DO NAMIBE'

        $template = '
        <p>Conteúdo do documento.</p>
        <div style="text-align: center;">
            _____________________________________________
            <br>
            {{RESPONSAVEL_NOME}}
        </div>';

        $result = $service->processarTemplate($template, null, $user);

        // Expected Date String (default cabinet)
        $dateString = \Carbon\Carbon::now()->translatedFormat('d \d\e F \d\e Y');
        $expectedLine = 'GOVERNO PROVINCIAL DO NAMIBE, em Moçâmedes, aos '.$dateString;

        $this->assertStringContainsString($expectedLine, $result);

        // Check position: Date line should be before the underscores
        $posDate = strpos($result, $expectedLine);
        $posUnderscore = strpos($result, '_____');

        $this->assertNotFalse($posDate, 'Date line not found');
        $this->assertNotFalse($posUnderscore, 'Underscores not found');
        $this->assertLessThan($posUnderscore, $posDate, 'Date line should be before underscores');
    }

    public function test_processar_template_does_not_inject_if_placeholder_exists()
    {
        $service = new DocumentoInternoService;
        $user = new User;
        $user->name = 'João';

        $template = '
        <p>Conteúdo.</p>
        {{RODAPE_INSTITUCIONAL_DATA}}
        <br>
        ___________________';

        $result = $service->processarTemplate($template, null, $user);

        // Should contain the date (replaced placeholder)
        $dateString = \Carbon\Carbon::now()->translatedFormat('d \d\e F \d\e Y');
        $this->assertStringContainsString($dateString, $result);

        // Should NOT inject another one (count should be 1)
        $this->assertEquals(1, substr_count($result, $dateString));
    }
}
