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
     * Pergunta global: recuperação restrita às permissões + resposta com citações.
     */
    public function perguntarGlobal(Request $request)
    {
        $data = $this->validatePergunta($request);
        $this->throttle($request);

        try {
            // Busca semântica (RAG) quando o chatbot está configurado; caso contrário, palavra-chave.
            $result = $this->chatbot->isAvailable()
                ? $this->chatbot->askGlobal($request->user(), $data['pergunta'], $data['historico'] ?? [])
                : $this->assistant->askGlobal($request->user(), $data['pergunta'], $data['historico'] ?? []);
        } catch (LlmException|EmbeddingException $e) {
            return response()->json(['erro' => $e->getMessage()], 503);
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
     * Rate limit por utilizador para conter custo/abuso (20 perguntas/min).
     */
    private function throttle(Request $request): void
    {
        $key = 'assistente:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Muitas perguntas em pouco tempo. Aguarde um momento e tente novamente.');
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
