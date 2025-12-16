<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DocumentoEntradasExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    protected $documentos;

    public function __construct($documentos = null)
    {
        $this->documentos = $documentos instanceof Collection ? $documentos : collect($documentos ?? []);
    }

    public function collection()
    {
        return $this->documentos;
    }

    public function headings(): array
    {
        return [
            'Nº',
            'Ano',
            'Data Entrada',
            'Espécie',
            'Ref Nº',
            'Procedência',
            'Assunto',
            'Departamento',
            'Status',
            'Visto Departamento',
            'Visto Gabinete',
            'Saída Gabinete',
        ];
    }

    public function map($doc): array
    {
        return [
            sprintf('%03d', (int) $doc->numero_sequencial),
            (int) $doc->ano_referencia,
            optional($doc->data_entrada)->format('d/m/Y'),
            $doc->classificacao_especie,
            $doc->classificacao_ref_numero,
            $doc->procedencia,
            $doc->assunto,
            optional($doc->departamento)->nome,
            $doc->status,
            $doc->visto_departamento_status,
            $doc->visto_gabinete_status,
            optional($doc->saida_gabinete_data)->format('d/m/Y'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
