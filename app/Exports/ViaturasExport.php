<?php

namespace App\Exports;

use App\Models\Viatura;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ViaturasExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    protected $viaturas;

    public function __construct($viaturas = null)
    {
        $this->viaturas = $viaturas ?? Viatura::all();
    }

    public function collection()
    {
        return $this->viaturas;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Identificação',
            'Matrícula',
            'Modelo',
            'Marca',
            'Ano',
            'Tipo',
            'Status Operacional',
            'Afetação',
        ];
    }

    public function map($viatura): array
    {
        return [
            $viatura->id,
            $viatura->identificacao,
            $viatura->placa,
            $viatura->modelo,
            $viatura->marca,
            $viatura->ano,
            $viatura->tipo,
            $viatura->status_operacional,
            $viatura->afetacao ?? 'Não definida',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
