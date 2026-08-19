<?php

namespace App\Http\Controllers;

use App\Enums\DocumentoStatus;
use App\Enums\StatusRequisicao;
use App\Models\DocumentoInterno;
use App\Models\Requisicao;
use App\Services\DocumentoWorkflowService;
use App\Services\RequisicaoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DepartamentoDashboardController extends Controller
{
    protected $workflowService;

    protected $requisicaoService;

    public function __construct(DocumentoWorkflowService $workflowService, RequisicaoService $requisicaoService)
    {
        $this->workflowService = $workflowService;
        $this->requisicaoService = $requisicaoService;
    }

    public function batchSignDocuments(Request $request)
    {
        $request->validate([
            'documento_ids' => 'required|array',
            'password' => 'required|string',
            'certificate_password' => 'nullable|string',
        ]);

        $user = Auth::user();

        try {
            $count = app(\App\Services\SignatureService::class)->batchSign(
                $request->documento_ids,
                DocumentoInterno::class,
                $user,
                $request->password,
                $request->input('certificate_password')
            );

            return back()->with('success', "{$count} documentos assinados com sucesso.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Senha do utilizador errada -> erro de validação no formulário
            if (array_key_exists('password', $e->errors())) {
                throw $e;
            }

            return back()->with('error', 'Falha ao assinar lote de documentos: '.collect($e->errors())->flatten()->implode(' '));
        } catch (\Exception $e) {
            return back()->with('error', 'Falha ao assinar lote de documentos: '.$e->getMessage());
        }
    }

    public function batchSignRequisicoes(Request $request)
    {
        $request->validate([
            'requisicao_ids' => 'required|array',
            'password' => 'required|string',
            'certificate_password' => 'nullable|string',
        ]);

        $user = Auth::user();

        try {
            $count = app(\App\Services\SignatureService::class)->batchSign(
                $request->requisicao_ids,
                Requisicao::class,
                $user,
                $request->password,
                $request->input('certificate_password')
            );

            return back()->with('success', "{$count} requisições assinadas com sucesso.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Senha do utilizador errada -> erro de validação no formulário
            if (array_key_exists('password', $e->errors())) {
                throw $e;
            }

            return back()->with('error', 'Falha ao assinar lote de requisições: '.collect($e->errors())->flatten()->implode(' '));
        } catch (\Exception $e) {
            return back()->with('error', 'Falha ao assinar lote de requisições: '.$e->getMessage());
        }
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        // Security Check: Role 'chefe_departamento' or Admin
        if (! $user->hasRole('chefe_departamento') && ! $user->hasRole('chefe-departamento') && ! $user->isAdmin()) {
            abort(403, 'Acesso restrito ao Chefe de Departamento.');
        }

        $departamento = $user->departamento;
        if (! $departamento && $user->isAdmin()) {
            $departamento = \App\Models\Departamento::first();
        }

        if (! $departamento) {
            return redirect()->route('home')->with('error', 'Você não está vinculado a um departamento.');
        }

        // --- KPIs e Analytics ---

        // 1. Documentos — contagens por status numa única query
        $docsPorStatus = DocumentoInterno::where('departamento_id', $departamento->id)
            ->select('status', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $statsDocs = [
            'total' => $docsPorStatus->sum(),
            // No workflow atual, o autor envia -> EM_ANALISE (Chefe/Gabinete aprova).
            'pendentes' => (int) $docsPorStatus->get(DocumentoStatus::RASCUNHO->value, 0),
            'para_aprovacao' => (int) $docsPorStatus->get(DocumentoStatus::EM_ANALISE->value, 0),
            'aprovados' => (int) $docsPorStatus->get(DocumentoStatus::APROVADO->value, 0),
        ];

        // 2. Requisições — contagens por status numa única query
        $reqsPorStatus = Requisicao::whereHas('usuario', function ($q) use ($departamento) {
            $q->where('departamento_id', $departamento->id);
        })
            ->select('status', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $statsReqs = [
            'total' => $reqsPorStatus->sum(),
            'pendentes' => (int) $reqsPorStatus->get(StatusRequisicao::PENDENTE->value, 0),
            'aprovadas' => (int) $reqsPorStatus->get(StatusRequisicao::APROVADO->value, 0),
            'rejeitadas' => (int) $reqsPorStatus->get(StatusRequisicao::REJEITADO->value, 0),
        ];

        // 3. Analytics — tempo médio até assinatura calculado no banco de dados
        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
        $avgExpr = $driver === 'sqlite'
            ? 'AVG(julianday(assinado_em) - julianday(created_at))'
            : 'AVG(DATEDIFF(assinado_em, created_at))';

        $avgTimeDocs = (float) (DocumentoInterno::where('departamento_id', $departamento->id)
            ->whereNotNull('assinado_em')
            ->selectRaw($avgExpr.' as avg_days')
            ->value('avg_days') ?? 0);

        // --- SLA Compliance — contagens em SQL (sla 'normal' = menos de 2 dias decorridos) ---
        $slaBase = \App\Models\DocumentoEntrada::where('departamento_id', $departamento->id)
            ->whereIn('status', ['registrado', 'recebido'])
            ->where('arquivado', false);

        $totalSlaDocs = (clone $slaBase)->count();
        $slaCutoff = now()->subDays(2);
        $compliantSlaDocs = (clone $slaBase)
            ->where(function ($q) use ($slaCutoff) {
                $q->where('data_entrada', '>', $slaCutoff)
                    ->orWhere(function ($qq) use ($slaCutoff) {
                        $qq->whereNull('data_entrada')->where('created_at', '>', $slaCutoff);
                    });
            })->count();
        $slaCompliance = $totalSlaDocs > 0 ? round(($compliantSlaDocs / $totalSlaDocs) * 100) : 100;

        // --- Logs de Atividade Recentes ---
        $recentLogs = \App\Models\AuditLog::where(function ($q) use ($departamento) {
            $q->whereHas('user', function ($qu) use ($departamento) {
                $qu->where('departamento_id', $departamento->id);
            })->orWhere(function ($qu) use ($departamento) {
                $qu->where('auditable_type', DocumentoInterno::class)
                    ->whereIn('auditable_id', DocumentoInterno::where('departamento_id', $departamento->id)->select('id'));
            })->orWhere(function ($qu) use ($departamento) {
                $qu->where('auditable_type', Requisicao::class)
                    ->whereIn('auditable_id', Requisicao::whereHas('usuario', fn ($qusr) => $qusr->where('departamento_id', $departamento->id))->select('id'));
            });
        })->with(['user', 'auditable'])->latest()->take(5)->get();

        // --- Listas para Ação ---

        // Docs para Aprovar (Em Análise)
        $docsParaAprovar = DocumentoInterno::where('departamento_id', $departamento->id)
            ->where('status', DocumentoStatus::EM_ANALISE)
            ->with(['autor', 'especie'])
            ->orderBy('created_at')
            ->paginate(10, ['*'], 'page_docs_aprov')
            ->withQueryString();

        // Requisições Pendentes (Status Pendente)
        $reqsPendentes = Requisicao::with(['usuario'])
            ->whereHas('usuario', function ($q) use ($departamento) {
                $q->where('departamento_id', $departamento->id);
            })
            ->where('status', StatusRequisicao::PENDENTE)
            ->orderBy('created_at')
            ->paginate(10, ['*'], 'page_reqs_pend')
            ->withQueryString();

        // Docs para Assinar (Aprovados e pendentes de assinatura)
        $signatureService = app(\App\Services\SignatureService::class);
        $user = Auth::user();

        $filteredDocsSign = DocumentoInterno::where('departamento_id', $departamento->id)
            ->where('status', DocumentoStatus::APROVADO)
            ->with(['especie', 'departamento.gabinete']) // canSign navega estas relações
            ->get()
            ->filter(function ($doc) use ($signatureService, $user) {
                return $signatureService->canSign($doc, $user);
            });
        $pageDocsSign = (int) $request->input('page_docs_sign', 1);
        $docsParaAssinar = new \Illuminate\Pagination\LengthAwarePaginator(
            $filteredDocsSign->forPage($pageDocsSign, 10)->values(),
            $filteredDocsSign->count(),
            10,
            $pageDocsSign,
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => 'page_docs_sign']
        );

        // Requisicoes para Assinar (Aprovadas)
        $filteredReqsSign = Requisicao::whereHas('usuario', function ($q) use ($departamento) {
            $q->where('departamento_id', $departamento->id);
        })
            ->where('status', StatusRequisicao::APROVADO)
            ->whereNull('assinado_em')
            ->with('usuario') // canSign consulta o departamento do requisitante
            ->get()
            ->filter(function ($req) use ($signatureService, $user) {
                return $signatureService->canSign($req, $user);
            });
        $pageReqsSign = (int) $request->input('page_reqs_sign', 1);
        $reqsParaAssinar = new \Illuminate\Pagination\LengthAwarePaginator(
            $filteredReqsSign->forPage($pageReqsSign, 10)->values(),
            $filteredReqsSign->count(),
            10,
            $pageReqsSign,
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => 'page_reqs_sign']
        );

        return view('departamento.dashboard', compact(
            'departamento',
            'statsDocs',
            'statsReqs',
            'avgTimeDocs',
            'docsParaAprovar',
            'reqsPendentes',
            'docsParaAssinar',
            'reqsParaAssinar',
            'slaCompliance',
            'recentLogs'
        ));
    }

    public function batchApproveDocuments(Request $request)
    {
        $request->validate(['documento_ids' => 'required|array']);

        $count = 0;
        foreach ($request->documento_ids as $id) {
            $doc = DocumentoInterno::find($id);
            if ($doc && Auth::user()->can('approve', $doc)) {
                try {
                    $this->workflowService->approve($doc, Auth::user());
                    $count++;
                } catch (\Exception $e) {
                }
            }
        }

        return back()->with('success', "{$count} documentos aprovados.");
    }

    public function batchApproveRequisicoes(Request $request)
    {
        $request->validate(['requisicao_ids' => 'required|array']);

        $count = 0;
        foreach ($request->requisicao_ids as $id) {
            $req = Requisicao::find($id);
            if ($req && Auth::user()->can('approve', $req)) {
                try {
                    $this->requisicaoService->aprovar($req, Auth::user());
                    $count++;
                } catch (\Exception $e) {
                }
            }
        }

        return back()->with('success', "{$count} requisições aprovadas.");
    }
}
