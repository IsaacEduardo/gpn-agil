<?php

namespace App\Http\Controllers;

use App\Enums\DocumentoStatus;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\User;
use App\Services\DocumentoWorkflowService;
use App\Services\SignatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GabineteDashboardController extends Controller
{
    protected $workflowService;

    protected $signatureService;

    public function __construct(DocumentoWorkflowService $workflowService, SignatureService $signatureService)
    {
        $this->workflowService = $workflowService;
        $this->signatureService = $signatureService;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $isSuperChefe = $user->isSuperChefeGabinete();

        if (! $user->isChefeGabinete() && ! $isSuperChefe && ! $user->isAdmin() && ! $user->hasPermissionTo('gabinete.view_all')) {
            abort(403, 'Acesso restrito.');
        }

        $gabinete = null;
        if ($isSuperChefe) {
            $gabinete = $user->gabineteSuperGerenciado;
        } elseif ($user->isChefeGabinete()) {
            $gabinete = $user->gabineteGerenciado;
        }

        if (! $gabinete && $user->departamento) {
            $gabinete = $user->departamento->gabinete; // Fallback para assessores delegados
        }
        if (! $gabinete && $user->isAdmin()) {
            $gabinete = Gabinete::first();
        }

        if (! $gabinete) {
            abort(404, 'Gabinete não encontrado para este usuário.');
        }

        // KPIs
        $stats = [
            'total_docs' => DocumentoInterno::whereHas('departamento', fn ($q) => $q->where('gabinete_id', $gabinete->id))->count(),
            'em_analise' => DocumentoInterno::whereHas('departamento', fn ($q) => $q->where('gabinete_id', $gabinete->id))
                ->where('status', DocumentoStatus::EM_ANALISE)->count(),
            'assinados_hoje' => DocumentoInterno::whereHas('departamento', fn ($q) => $q->where('gabinete_id', $gabinete->id))
                ->where('status', DocumentoStatus::ASSINADO)
                ->whereDate('assinado_em', today())->count(),
        ];

        // Lista de Departamentos
        $departamentos = $gabinete->departamentos()->withCount(['documentosInternos as docs_pendentes' => function ($q) {
            $q->where('status', DocumentoStatus::EM_ANALISE);
        }])->get();

        // Distribuição de status para gráfico doughnut
        $statusDistrib = DocumentoInterno::whereHas('departamento', fn ($q) => $q->where('gabinete_id', $gabinete->id))
            ->select('status', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->mapWithKeys(function ($item) {
                $statusKey = $item->status instanceof DocumentoStatus ? $item->status->value : $item->status;

                return [$statusKey => $item->count];
            });

        // Logs de Atividade Recentes
        $recentLogs = \App\Models\AuditLog::where(function ($q) use ($gabinete) {
            $q->whereHas('user', function ($qu) use ($gabinete) {
                $qu->whereHas('departamento', fn ($qd) => $qd->where('gabinete_id', $gabinete->id));
            })->orWhere(function ($qu) use ($gabinete) {
                $qu->where('auditable_type', DocumentoInterno::class)
                    ->whereIn('auditable_id', DocumentoInterno::whereHas('departamento', fn ($qd) => $qd->where('gabinete_id', $gabinete->id))->pluck('id'));
            });
        })->with(['user', 'auditable'])->latest()->take(5)->get();

        // 1. Docs Para Assinar (APROVADO)
        $docsParaAssinar = DocumentoInterno::accessibleBy($user)
            ->where('status', DocumentoStatus::APROVADO)
            ->with(['especie', 'autor', 'departamento'])
            ->orderByDesc('updated_at')
            ->get();

        // 2. Docs Para Aprovar (EM_ANALISE)
        $docsParaAprovar = DocumentoInterno::accessibleBy($user)
            ->where('status', DocumentoStatus::EM_ANALISE)
            ->with(['especie', 'autor', 'departamento'])
            ->orderBy('created_at')
            ->get();

        // 3. Query Principal (Histórico / Todos)
        $query = DocumentoInterno::accessibleBy($user)
            ->with(['especie', 'autor', 'departamento']);

        // Filtros (apenas para a aba "Todos")
        if ($request->filled('departamento_id')) {
            $query->where('departamento_id', $request->departamento_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('titulo', 'like', "%{$search}%")
                    ->orWhere('numero_referencia', 'like', "%{$search}%");
            });
        }

        $documentos = $query->orderByDesc('created_at')->paginate(20)->withQueryString();
        $especies = DocumentoEspecie::where('ativo', true)->orderBy('nome')->get();

        return view('gabinete.dashboard', compact(
            'gabinete', 'stats', 'departamentos', 'documentos', 'especies',
            'docsParaAssinar', 'docsParaAprovar', 'statusDistrib', 'recentLogs'
        ));
    }

    public function batchSign(Request $request)
    {
        $request->validate([
            'documento_ids' => 'required|array',
            'documento_ids.*' => 'exists:documento_internos,id',
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
        $ids = $request->documento_ids;
        $successCount = 0;

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($ids, $user, $request, $certificatePassword, &$successCount) {
                foreach ($ids as $id) {
                    $doc = DocumentoInterno::findOrFail($id);

                    // Security Check: User must have access
                    if (! $user->can('view', $doc)) {
                        throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException("Você não tem permissão para acessar o documento ID {$id}.");
                    }

                    $this->signatureService->sign($doc, $user, $request->password, true, $certificatePassword);
                    $successCount++;
                }
            });

            return back()->with('success', "{$successCount} documentos assinados com sucesso.");
        } catch (\Exception $e) {
            return back()->with('error', 'Falha ao assinar lote de documentos: '.$e->getMessage());
        }
    }

    public function batchApprove(Request $request)
    {
        $request->validate([
            'documento_ids' => 'required|array',
            'documento_ids.*' => 'exists:documento_internos,id',
        ]);

        $user = Auth::user();
        $ids = $request->documento_ids;
        $successCount = 0;
        $failCount = 0;

        foreach ($ids as $id) {
            $doc = DocumentoInterno::find($id);

            // Security Check
            if (! $user->can('approve', $doc)) {
                $failCount++;

                continue;
            }

            try {
                $this->workflowService->approve($doc, $user);
                $successCount++;
            } catch (\Exception $e) {
                $failCount++;
            }
        }

        if ($successCount > 0) {
            return back()->with('success', "{$successCount} documentos aprovados com sucesso.");
        }

        return back()->with('error', 'Nenhum documento pôde ser aprovado.');
    }
}
