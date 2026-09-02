<?php

namespace App\Http\Controllers;

use App\Chatbot\Exceptions\EmbeddingException;
use App\Chatbot\Services\ChatbotService;
use App\Models\AuditLog;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoInterno;
use App\Services\Ai\DocumentoAssistantService;
use App\Services\Ai\LlmException;
use App\Services\DocumentoPermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class AssistenteController extends Controller
{
    public function __construct(
        private DocumentoAssistantService $assistant,
        private DocumentoPermissionService $permissions,
        private ChatbotService $chatbot,
    ) {}

    /**
     * Página do assistente global (busca + pergunta sobre documentos acessíveis).
     */
    public function index()
    {
        return view('assistente.index', [
            'configurado' => $this->assistant->isAvailable(),
        ]);
    }

    /**
     * Pergunta global: RAG + Function Calling com DeepSeek respeitando o RBAC.
     */
    public function perguntarGlobal(Request $request)
    {
        $data = $this->validatePergunta($request);
        $this->throttle($request);

        try {
            $result = $this->assistant->askGlobal($request->user(), $data['pergunta'], $data['historico'] ?? []);
        } catch (LlmException|EmbeddingException $e) {
            return response()->json(['erro' => $e->getMessage()], 503);
        } catch (\Throwable $e) {
            Log::error('Assistente Global: erro inesperado: '.$e->getMessage());

            return response()->json(['erro' => 'Erro ao processar consulta com o assistente: '.$e->getMessage()], 500);
        }

        $this->logConsulta($request, 'assistente.global', null, null, $data['pergunta']);

        return response()->json($result);
    }

    /**
     * Pergunta sobre um documento de entrada específico.
     */
    public function perguntarEntrada(Request $request, DocumentoEntrada $documento)
    {
        if (! $this->permissions->canViewDocument($request->user(), $documento)) {
            abort(403, 'Você não tem acesso a este documento.');
        }

        $data = $this->validatePergunta($request);
        $this->throttle($request);

        try {
            $result = $this->assistant->askAboutEntrada($documento, $data['pergunta'], $data['historico'] ?? []);
        } catch (LlmException $e) {
            return response()->json(['erro' => $e->getMessage()], 503);
        }

        $this->logConsulta($request, 'assistente.entrada', DocumentoEntrada::class, $documento->id, $data['pergunta']);

        return response()->json($result);
    }

    /**
     * Pergunta sobre um documento interno específico.
     */
    public function perguntarInterno(Request $request, DocumentoInterno $documentoInterno)
    {
        Gate::authorize('view', $documentoInterno);

        $data = $this->validatePergunta($request);
        $this->throttle($request);

        try {
            $result = $this->assistant->askAboutInterno($documentoInterno, $data['pergunta'], $data['historico'] ?? []);
        } catch (LlmException $e) {
            return response()->json(['erro' => $e->getMessage()], 503);
        }

        $this->logConsulta($request, 'assistente.interno', DocumentoInterno::class, $documentoInterno->id, $data['pergunta']);

        return response()->json($result);
    }

    /**
     * Gera o Resumo Executivo em 3 blocos (Contexto, Histórico, Ação Pendente) para um documento.
     */
    public function resumirDocumento(Request $request)
    {
        $validated = $request->validate([
            'documento_id' => ['required', 'integer'],
            'tipo' => ['required', 'string', 'in:EXTERNO,INTERNO,externo,interno,entrada,ENTRADA'],
        ]);

        $this->throttle($request);

        try {
            $result = $this->assistant->summarizeDocument(
                $request->user(),
                (int) $validated['documento_id'],
                (string) $validated['tipo']
            );
        } catch (LlmException $e) {
            return response()->json(['success' => false, 'erro' => $e->getMessage()], 503);
        } catch (\Throwable $e) {
            Log::error('Erro ao resumir documento: '.$e->getMessage());

            return response()->json(['success' => false, 'erro' => 'Não foi possível gerar o resumo do documento: '.$e->getMessage()], 500);
        }

        $tipoDoc = in_array(strtoupper($validated['tipo']), ['INTERNO']) ? DocumentoInterno::class : DocumentoEntrada::class;

        $this->logConsulta(
            $request,
            'assistente.resumo',
            $tipoDoc,
            (int) $validated['documento_id'],
            'Solicitação de Resumo Executivo com IA'
        );

        return response()->json($result);
    }

    /**
     * @return array{pergunta:string, historico?:array}
     */
    private function validatePergunta(Request $request): array
    {
        return $request->validate([
            'pergunta' => ['required', 'string', 'max:2000'],
            'historico' => ['sometimes', 'array', 'max:20'],
            'historico.*.role' => ['required_with:historico', 'in:user,assistant'],
            'historico.*.content' => ['required_with:historico', 'string'],
        ]);
    }

    /**
     * Rate limit por utilizador para conter custo/abuso (30 perguntas/min).
     */
    private function throttle(Request $request): void
    {
        $key = 'assistente:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429, 'Muitas solicitações ao assistente em pouco tempo. Aguarde alguns instantes e tente novamente.');
        }
        RateLimiter::hit($key, 60);
    }

    /**
     * Auditoria de governança: regista quem perguntou o quê e sobre qual documento.
     */
    private function logConsulta(Request $request, string $action, ?string $type, ?int $id, string $pergunta): void
    {
        try {
            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => $action,
                'auditable_type' => $type,
                'auditable_id' => $id,
                'old_values' => null,
                'new_values' => ['pergunta' => mb_substr($pergunta, 0, 500)],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            // Auditoria não deve quebrar a resposta ao utilizador.
        }
    }
}
