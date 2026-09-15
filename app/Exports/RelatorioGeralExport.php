<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exportação XLSX/CSV do relatório geral do EDMS.
 *
 * O conteúdo vem de uma view Blade (relatorios.excel) para reaproveitar a
 * mesma estrutura de secções do PDF; aqui trata-se apenas da formatação da
 * folha de cálculo.
 */
class RelatorioGeralExport implements FromView, ShouldAutoSize, WithStyles, WithTitle
{
    /** Linha onde começa o cabeçalho da tabela detalhada (ver relatorios.excel). */
    private const LINHA_CABECALHO_TABELA = 9;

    /**
     * @param  array<string, mixed>  $data  Conjunto devolvido por ReportService::getReportData()
     */
    public function __construct(protected array $data) {}

    public function view(): View
    {
        return view('relatorios.excel', $this->data);
    }

    public function title(): string
    {
        // O Excel rejeita > 31 caracteres e os caracteres : \ / ? * [ ] no nome da folha.
        return 'Relatorio EDMS';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:H2')->getAlignment()->setWrapText(true);

        return [
            // Título do relatório.
            1 => [
                'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '0F172A']],
            ],
            // Subtítulo com o período.
            2 => [
                'font' => ['size' => 10, 'color' => ['rgb' => '475569']],
            ],
            // Faixa dos indicadores.
            4 => [
                'font' => ['bold' => true, 'color' => ['rgb' => '0F172A']],
            ],
            // Cabeçalho da tabela detalhada.
            self::LINHA_CABECALHO_TABELA => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => 'solid',
                    'startColor' => ['rgb' => '1F2937'],
                ],
            ],
        ];
    }
}
