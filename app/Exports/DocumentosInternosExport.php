<?php

namespace App\Exports;

use App\Models\DocumentoInterno;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DocumentosInternosExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    protected $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function query()
    {
        $query = DocumentoInterno::with(['especie', 'autor', 'departamento'])
            ->where('departamento_id', Auth::user()->departamento_id);

        // Apply filters (same logic as Controller)
        if (! empty($this->request->search)) {
            $search = $this->request->search;
            $query->where(function ($q) use ($search) {
                $q->where('titulo', 'like', "%{$search}%")
                    ->orWhere('numero_referencia', 'like', "%{$search}%");
            });
        }

        if (! empty($this->request->especie_id)) {
            $query->where('documento_especie_id', $this->request->especie_id);
        }

        if (! empty($this->request->status)) {
            $query->where('status', $this->request->status);
        }

        if (! empty($this->request->data_inicio)) {
            $query->whereDate('created_at', '>=', $this->request->data_inicio);
        }

        if (! empty($this->request->data_fim)) {
            $query->whereDate('created_at', '<=', $this->request->data_fim);
        }

        return $query->orderByDesc('created_at');
    }

    public function headings(): array
    {
        return [
            'Referência',
            'Título',
            'Espécie',
            'Departamento',
            'Autor',
            'Status',
            'Data de Criação',
            'Assinado Por',
            'Data Assinatura',
        ];
    }

    public function map($doc): array
    {
        return [
            $doc->numero_referencia,
            $doc->titulo,
            $doc->especie->nome ?? 'N/A',
            $doc->departamento->nome ?? 'N/A',
            $doc->autor->name ?? 'N/A',
            ucfirst($doc->status),
            $doc->created_at->format('d/m/Y H:i'),
            $doc->assinadoPor ? $doc->assinadoPor->name : '-',
            $doc->assinado_em ? $doc->assinado_em->format('d/m/Y H:i') : '-',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
