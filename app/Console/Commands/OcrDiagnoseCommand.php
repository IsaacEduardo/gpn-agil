<?php

namespace App\Console\Commands;

use App\Services\Ocr\OcrService;
use Illuminate\Console\Command;

class OcrDiagnoseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ocr:diagnose';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audita e valida o ambiente do Tesseract OCR, pacotes de idiomas e conversores de PDF';

    /**
     * Execute the console command.
     */
    public function handle(OcrService $ocrService): int
    {
        $this->info('=====================================================');
        $this->info('   GPN-ÁGIL — Diagnóstico do Pipeline de OCR & PDF   ');
        $this->info('=====================================================');

        $diag = $ocrService->diagnose();

        $rows = [
            ['Status Geral', $diag['status'] === 'OK' ? '<info>OK</info>' : ($diag['status'] === 'WARN' ? '<comment>AVISO</comment>' : '<error>ERRO</error>')],
            ['Binário Tesseract', $diag['tesseract_binary'] ? "<info>{$diag['tesseract_binary']}</info>" : '<error>Não localizado</error>'],
            ['Versão do Tesseract', $diag['tesseract_version'] ?: '<comment>Indisponível</comment>'],
            ['Diretório Tessdata', $diag['tessdata_dir'] ?: '<comment>Padrão do Sistema</comment>'],
            ['Idiomas Instalados', ! empty($diag['languages']) ? implode(', ', $diag['languages']) : '<error>Nenhum</error>'],
            ['Suporte a Português (por)', $diag['has_portuguese'] ? '<info>SIM (Instalado)</info>' : '<error>NÃO (Instale por.traineddata)</error>'],
            ['Suporte a Inglês (eng)', $diag['has_english'] ? '<info>SIM (Instalado)</info>' : '<comment>NÃO</comment>'],
        ];

        $this->table(['Item', 'Resultado'], $rows);

        $this->newLine();
        $this->info('Utilitários Auxiliares de Conversão (PDF -> Imagem):');
        $convRows = [
            ['pdftoppm (Poppler)', $diag['converters']['pdftoppm'] ? '<info>Disponível</info>' : '<comment>Ausente</comment>'],
            ['Ghostscript (gs / gswin64c)', $diag['converters']['ghostscript'] ? '<info>Disponível</info>' : '<comment>Ausente</comment>'],
            ['ImageMagick (magick)', $diag['converters']['imagemagick'] ? '<info>Disponível</info>' : '<comment>Ausente</comment>'],
            ['Extensão PHP Imagick', $diag['converters']['imagick_extension'] ? '<info>Ativa</info>' : '<comment>Inativa</comment>'],
        ];
        $this->table(['Conversor', 'Status'], $convRows);

        if (! empty($diag['warnings'])) {
            $this->newLine();
            $this->warn('Avisos / Recomendações:');
            foreach ($diag['warnings'] as $warning) {
                $this->line(" - {$warning}");
            }
        }

        if (! empty($diag['error'])) {
            $this->newLine();
            $this->error("Erro reportado pelo Tesseract: {$diag['error']}");
        }

        return $diag['status'] === 'ERROR' ? Command::FAILURE : Command::SUCCESS;
    }
}
