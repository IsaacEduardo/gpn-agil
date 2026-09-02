<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardApiController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Retorna as estatísticas e listas do dashboard no formato JSON para o usuário autenticado
     */
    public function estatisticas(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Não autenticado.'], 401);
        }

        $forceFresh = $request->boolean('fresh', false);
        $data = $this->dashboardService->obterDadosDashboard($user, $forceFresh);

        return response()->json([
            'success' => true,
            'timestamp' => now()->toIso8601String(),
            'data' => $data,
        ]);
    }
}
