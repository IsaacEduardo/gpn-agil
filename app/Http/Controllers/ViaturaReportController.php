<?php

namespace App\Http\Controllers;

use App\Exports\ViaturasExport;
use App\Models\Viatura;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ViaturaReportController extends Controller
{
    public function exportPDF(Request $request)
    {
        // Aplicar os mesmos filtros da listagem
        $query = Viatura::query();

        // Filtro de busca por texto
        if ($request->has('search') && ! empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('identificacao', 'like', "%{$search}%")
                    ->orWhere('placa', 'like', "%{$search}%")
                    ->orWhere('modelo', 'like', "%{$search}%")
                    ->orWhere('marca', 'like', "%{$search}%");
            });
        }

        // Filtro por status operacional
        if ($request->has('status') && ! empty($request->status)) {
            $query->where('status_operacional', $request->status);
        }

        // Filtro por tipo
        if ($request->has('tipo') && ! empty($request->tipo)) {
            $query->where('tipo', $request->tipo);
        }

        // Filtro por ano
        if ($request->has('ano') && ! empty($request->ano)) {
            $query->where('ano', $request->ano);
        }

        // Ordenação
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');

        // Validar campo de ordenação para evitar SQL injection
        $allowedSortFields = ['created_at', 'placa', 'modelo', 'marca', 'ano', 'status_operacional', 'tipo'];
        if (! in_array($sortField, $allowedSortFields)) {
            $sortField = 'created_at';
        }

        $query->orderBy($sortField, $sortDirection);

        $viaturas = $query->get();

        // Criar instância do Dompdf diretamente
        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $html = view('viaturas.pdf', compact('viaturas'))->render();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->stream('relatorio-viaturas-'.date('Y-m-d').'.pdf');
    }

    public function exportExcel(Request $request)
    {
        // Aplicar os mesmos filtros da listagem
        $query = Viatura::query();

        // Filtro de busca por texto
        if ($request->has('search') && ! empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('identificacao', 'like', "%{$search}%")
                    ->orWhere('placa', 'like', "%{$search}%")
                    ->orWhere('modelo', 'like', "%{$search}%")
                    ->orWhere('marca', 'like', "%{$search}%");
            });
        }

        // Filtro por status operacional
        if ($request->has('status') && ! empty($request->status)) {
            $query->where('status_operacional', $request->status);
        }

        // Filtro por tipo
        if ($request->has('tipo') && ! empty($request->tipo)) {
            $query->where('tipo', $request->tipo);
        }

        // Filtro por ano
        if ($request->has('ano') && ! empty($request->ano)) {
            $query->where('ano', $request->ano);
        }

        // Ordenação
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');

        // Validar campo de ordenação para evitar SQL injection
        $allowedSortFields = ['created_at', 'placa', 'modelo', 'marca', 'ano', 'status_operacional', 'tipo'];
        if (! in_array($sortField, $allowedSortFields)) {
            $sortField = 'created_at';
        }

        $query->orderBy($sortField, $sortDirection);

        $viaturas = $query->get();

        return Excel::download(new ViaturasExport($viaturas), 'relatorio-viaturas-'.date('Y-m-d').'.xlsx');
    }
}
