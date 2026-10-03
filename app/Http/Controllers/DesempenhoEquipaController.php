<?php

namespace App\Http\Controllers;

use App\Services\DesempenhoEquipaService;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Relatórios › Desempenho da equipa: indicadores de execução das tarefas dos
 * documentos externos, por técnico.
 *
 * Aberto a qualquer utilizador autenticado porque o técnico vê os seus próprios
 * números; o âmbito (quem mais aparece) é decidido no DesempenhoEquipaService,
 * e pedir um departamento fora dele é recusado com 403 em vez de ignorado.
 */
class DesempenhoEquipaController extends Controller
{
    public function __construct(protected DesempenhoEquipaService $desempenho) {}

    public function index(Request $request)
    {
        $user = Auth::user();

        $filtros = $request->validate([
            'granularity' => ['nullable', 'string', 'in:'.implode(',', ReportService::GRANULARIDADES)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'departamento_id' => ['nullable', 'integer', 'exists:departamentos,id'],
        ]);

        $departamentoId = filled($filtros['departamento_id'] ?? null) ? (int) $filtros['departamento_id'] : null;
        abort_unless(
            $this->desempenho->departamentoPermitido($this->desempenho->escopo($user), $departamentoId),
            403,
            'Sem permissão para ver o desempenho deste departamento.'
        );

        return view('relatorios.desempenho', $this->desempenho->dados($filtros, $user));
    }
}
