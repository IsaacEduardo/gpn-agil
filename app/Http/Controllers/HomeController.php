<?php

namespace App\Http\Controllers;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoInterno;
use App\Models\Empresa;
use App\Models\Requisicao;
use App\Models\ReservaEspaco;
use App\Models\Viatura;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        // Saudações e contexto do utilizador
        $user = Auth::user();
        $isAdmin = $user->role && $user->role->name === 'admin';
        $departmentId = $user->departamento_id;

        // Base query para Requisições
        $requisicaoQuery = Requisicao::query();
        if (! $isAdmin) {
            if ($departmentId) {
                $requisicaoQuery->whereHas('usuario', function ($q) use ($departmentId) {
                    $q->where('departamento_id', $departmentId);
                });
            } else {
                // Se não tem departamento e não é admin, vê apenas as suas
                $requisicaoQuery->where('usuario_id', $user->id);
            }
        }

        // Base query para Reservas
        $reservaQuery = ReservaEspaco::query();
        if (! $isAdmin) {
            if ($departmentId) {
                $reservaQuery->whereHas('usuario', function ($q) use ($departmentId) {
                    $q->where('departamento_id', $departmentId);
                });
            } else {
                $reservaQuery->where('usuario_id', $user->id);
            }
        }

        // Base query para Documentos de Entrada
        $documentoQuery = DocumentoEntrada::query();
        if (! $isAdmin) {
            if ($departmentId) {
                // Documentos onde o departamento é o dono OU onde foi encaminhado para o departamento
                $documentoQuery->where(function ($q) use ($departmentId) {
                    $q->where('departamento_id', $departmentId)
                        ->orWhereHas('encaminhamentos', function ($sq) use ($departmentId) {
                            $sq->where('destino_departamento_id', $departmentId);
                        });
                });
            } else {
                // Se não tem departamento, vê apenas os que criou
                $documentoQuery->where('user_id', $user->id);
            }
        }

        // Base query para Documentos Internos
        $documentoInternoQuery = DocumentoInterno::query();
        if (! $isAdmin) {
            if ($departmentId) {
                $documentoInternoQuery->where('departamento_id', $departmentId);
            } else {
                $documentoInternoQuery->where('criado_por', $user->id);
            }
        }

        // Métricas principais
        $metrics = [
            'viaturas' => [
                'total' => Viatura::count(),
                'operacional' => Viatura::where('status_operacional', 'Operacional')->count(),
                'manutencao' => Viatura::where('status_operacional', 'Em manutenção')->count(),
                'inoperante' => Viatura::where('status_operacional', 'Inoperante')->count(),
            ],
            'documentos' => [
                'total' => (clone $documentoQuery)->count(),
                'registrado' => (clone $documentoQuery)->where('status', 'registrado')->count(),
                'encaminhado' => (clone $documentoQuery)->where('status', 'encaminhado')->count(),
                'recebido' => (clone $documentoQuery)->where('status', 'recebido')->count(),
                'respondido' => (clone $documentoQuery)->where('status', 'respondido')->count(),
                'arquivado' => (clone $documentoQuery)->where('status', 'arquivado')->count(),
            ],
            'documentos_internos' => [
                'total' => (clone $documentoInternoQuery)->count(),
                'rascunho' => (clone $documentoInternoQuery)->where('status', 'rascunho')->count(), // Ajustar status conforme uso real
                'assinado' => (clone $documentoInternoQuery)->whereNotNull('assinado_em')->count(),
            ],
            'requisicoes' => [
                'total' => (clone $requisicaoQuery)->count(),
                'pendente' => (clone $requisicaoQuery)->where('status', Requisicao::STATUS_PENDENTE)->count(),
                'aprovado' => (clone $requisicaoQuery)->where('status', Requisicao::STATUS_APROVADO)->count(),
                'rejeitado' => (clone $requisicaoQuery)->where('status', Requisicao::STATUS_REJEITADO)->count(),
                'finalizado' => (clone $requisicaoQuery)->where('status', Requisicao::STATUS_FINALIZADO)->count(),
                'produto' => (clone $requisicaoQuery)->where('tipo', Requisicao::TIPO_PRODUTO)->count(),
                'oficina' => (clone $requisicaoQuery)->where('tipo', Requisicao::TIPO_OFICINA)->count(),
                'servico' => (clone $requisicaoQuery)->where('tipo', Requisicao::TIPO_SERVICO)->count(),
                'passagem' => (clone $requisicaoQuery)->where('tipo', Requisicao::TIPO_PASSAGEM)->count(),
            ],
            'reservas' => [
                'total' => (clone $reservaQuery)->count(),
                'pendente' => (clone $reservaQuery)->where('status', ReservaEspaco::STATUS_PENDENTE)->count(),
                'aprovada' => (clone $reservaQuery)->where('status', ReservaEspaco::STATUS_APROVADA)->count(),
                'rejeitada' => (clone $reservaQuery)->where('status', ReservaEspaco::STATUS_REJEITADA)->count(),
                'cancelada' => (clone $reservaQuery)->where('status', ReservaEspaco::STATUS_CANCELADA)->count(),
            ],
            'empresas' => Empresa::count(),
            'departamentos' => Departamento::count(),
        ];

        // Estatísticas por Departamento (apenas para Admin)
        $deptStats = [];
        if ($isAdmin) {
            $deptStats = Departamento::select('id', 'sigla', 'nome')
                ->withCount(['documentosEntrada as total_entrada', 'documentosInternos as total_internos'])
                ->orderBy('sigla')
                ->paginate(8, ['*'], 'page_dept')
                ->withQueryString();
        }

        // Actividade recente
        $recentRequisicoes = (clone $requisicaoQuery)
            ->with(['usuario', 'empresa'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $recentIncomingDocs = (clone $documentoQuery)
            ->with(['departamento', 'ultimoEncaminhamento'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $recentInternalDocs = (clone $documentoInternoQuery)
            ->with(['autor', 'modelo'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $recentReservas = (clone $reservaQuery)
            ->with(['usuario'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $recentViaturas = Viatura::orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('home', compact(
            'metrics',
            'recentRequisicoes',
            'recentIncomingDocs',
            'recentInternalDocs',
            'recentReservas',
            'recentViaturas',
            'deptStats',
            'user'
        ));
    }
}
