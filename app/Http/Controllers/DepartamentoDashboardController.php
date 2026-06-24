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

        // 1. Validar Senha do Usuário uma única vez
        if (! \Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'password' => ['A senha informada está incorreta.'],
            ]);
        }

        // Senha do certificado P12 fornecida uma vez para todo o lote (mantida apenas em memória).
        $certificatePassword = $request->input('certificate_password');
        $signatureService = app(\App\Services\SignatureService::class);
        $count = 0;

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($request, $user, $signatureService, $certificatePassword, &$count) {
                foreach ($request->documento_ids as $id) {
                    $doc = DocumentoInterno::findOrFail($id);
                    $signatureService->sign($doc, $user, $request->password, true, $certificatePassword);
                    $count++;
                }
            });

            return back()->with('success', "{$count} documentos assinados com sucesso.");
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

        // 1. Validar Senha do Usuário uma única vez
        if (! \Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'password' => ['A senha informada está incorreta.'],
            ]);
        }

        // Senha do certificado P12 fornecida uma vez para todo o lote (mantida apenas em memória).
        $certificatePassword = $request->input('certificate_password');
        $signatureService = app(\App\Services\SignatureService::class);
        $count = 0;

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($request, $user, $signatureService, $certificatePassword, &$count) {
                foreach ($request->requisicao_ids as $id) {
                    $req = Requisicao::findOrFail($id);
                    $signatureService->sign($req, $user, $request->password, true, $certificatePassword);
                    $count++;
                }
            });

            return back()->with('success', "{$count} requisições assinadas com sucesso.");
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

        // 1. Documentos
        $docsQuery = DocumentoInterno::where('departamento_id', $departamento->id);

        $statsDocs = [
            'total' => (clone $docsQuery)->count(),
            'pendentes' => (clone $docsQuery)->where('status', DocumentoStatus::RASCUNHO)->count(), // Chefes revisam rascunhos da equipe? Ou aprovam?
            // Chefes aprovam o envio para o gabinete? Depende do workflow.
            // No workflow atual, o autor envia -> EM_ANALISE (Chefe/Gabinete aprova).
            // Se o chefe é do depto, ele deve ver os 'EM_ANALISE' do seu depto para aprovar.
            'para_aprovacao' => (clone $docsQuery)->where('status', DocumentoStatus::EM_ANALISE)->count(),
            'aprovados' => (clone $docsQuery)->where('status', DocumentoStatus::APROVADO)->count(),
        ];

        // 2. Requisições
        // Requisicoes onde o usuario pertence ao departamento
        $reqsQuery = Requisicao::whereHas('usuario', function ($q) use ($departamento) {
            $q->where('departamento_id', $departamento->id);
        });

        $statsReqs = [
            'total' => (clone $reqsQuery)->count(),
            'pendentes' => (clone $reqsQuery)->where('status', StatusRequisicao::PENDENTE)->count(),
            'aprovadas' => (clone $reqsQuery)->where('status', StatusRequisicao::APROVADO)->count(),
            'rejeitadas' => (clone $reqsQuery)->where('status', StatusRequisicao::REJEITADO)->count(),
        ];

        // 3. Analytics (Tempo médio, etc - Simulado/Calculado simples)
        // Calculo via PHP para compatibilidade com SQLite/MySQL
        $docsAprovados = DocumentoInterno::where('departamento_id', $departamento->id)
            ->where('status', DocumentoStatus::APROVADO)
            ->whereNotNull('assinado_em')
            ->get(['created_at', 'assinado_em']);

        $avgTimeDocs = 0;
        if ($docsAprovados->count() > 0) {
            $totalDays = $docsAprovados->sum(function ($doc) {
                return $doc->created_at->diffInDays($doc->assinado_em);
            });
            $avgTimeDocs = $totalDays / $docsAprovados->count();
        }

        // --- SLA Compliance ---
        $slaDocs = \App\Models\DocumentoEntrada::where('departamento_id', $departamento->id)
            ->whereIn('status', ['registrado', 'recebido'])
            ->where('arquivado', false)
            ->get();
        $totalSlaDocs = $slaDocs->count();
        $compliantSlaDocs = $slaDocs->filter(fn ($d) => $d->sla_status === 'normal')->count();
        $slaCompliance = $totalSlaDocs > 0 ? round(($compliantSlaDocs / $totalSlaDocs) * 100) : 100;

        // --- Logs de Atividade Recentes ---
        $recentLogs = \App\Models\AuditLog::where(function ($q) use ($departamento) {
            $q->whereHas('user', function ($qu) use ($departamento) {
                $qu->where('departamento_id', $departamento->id);
            })->orWhere(function ($qu) use ($departamento) {
                $qu->where('auditable_type', DocumentoInterno::class)
                    ->whereIn('auditable_id', DocumentoInterno::where('departamento_id', $departamento->id)->pluck('id'));
            })->orWhere(function ($qu) use ($departamento) {
                $qu->where('auditable_type', Requisicao::class)
                    ->whereIn('auditable_id', Requisicao::whereHas('usuario', fn ($qusr) => $qusr->where('departamento_id', $departamento->id))->pluck('id'));
            });
        })->with(['user', 'auditable'])->latest()->take(5)->get();

        // --- Listas para Ação ---

        // Docs para Aprovar (Em Análise)
        $docsParaAprovar = DocumentoInterno::where('departamento_id', $departamento->id)
            ->where('status', DocumentoStatus::EM_ANALISE)
            ->with(['autor', 'especie'])
            ->orderBy('created_at')
            ->get();

        // Requisições Pendentes (Status Pendente)
        $reqsPendentes = Requisicao::with(['usuario'])
            ->whereHas('usuario', function ($q) use ($departamento) {
                $q->where('departamento_id', $departamento->id);
            })
            ->where('status', StatusRequisicao::PENDENTE)
            ->orderBy('created_at')
            ->get();

        // Docs para Assinar (Aprovados e pendentes de assinatura)
        $signatureService = app(\App\Services\SignatureService::class);
        $user = Auth::user();

        $docsParaAssinar = DocumentoInterno::where('departamento_id', $departamento->id)
            ->where('status', DocumentoStatus::APROVADO)
            ->get()
            ->filter(function ($doc) use ($signatureService, $user) {
                return $signatureService->canSign($doc, $user);
            });

        // Requisicoes para Assinar (Aprovadas)
        $reqsParaAssinar = Requisicao::whereHas('usuario', function ($q) use ($departamento) {
            $q->where('departamento_id', $departamento->id);
        })
            ->where('status', StatusRequisicao::APROVADO)
            ->whereNull('assinado_em')
            ->get()
            ->filter(function ($req) use ($signatureService, $user) {
                return $signatureService->canSign($req, $user);
            });

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
