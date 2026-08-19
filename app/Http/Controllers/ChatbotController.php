<?php

namespace App\Http\Controllers;

use App\Chatbot\Exceptions\EmbeddingException;
use App\Chatbot\Models\ChatbotConversation;
use App\Chatbot\Models\ChatbotMessage;
use App\Chatbot\Models\ChatbotQueryLog;
use App\Chatbot\Services\ChatbotService;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoInterno;
use App\Services\Ai\LlmException;
use App\Services\DocumentoPermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * API REST do chatbot (routes/api.php, auth:sanctum). Gere conversas, regista logs e
 * aplica autorização por documento. A recuperação respeita as permissões do utilizador.
 */
class ChatbotController extends Controller
{
    public function __construct(
        private ChatbotService $chatbot,
        private DocumentoPermissionService $permissions,
    ) {
        // Middleware (e não abort no construtor) para não rebentar comandos
        // que instanciam controllers, como `php artisan route:list`.
        $this->middleware(function ($request, $next) {
            abort_unless(config('chatbot.enabled'), 404, 'Chatbot desativado.');

            return $next($request);
        });
    }

    public function perguntar(Request $request)
    {
        $data = $this->validar($request);
        $this->throttle($request);

        $conversa = $this->resolverConversa($request, 'global', null, null);
        $inicio = microtime(true);

        try {
            $result = $this->chatbot->askGlobal($request->user(), $data['pergunta'], $this->historico($conversa));
        } catch (LlmException|EmbeddingException $e) {
            return response()->json(['erro' => $e->getMessage()], 503);
        }

        return $this->responder($request, $conversa, 'global', null, null, $data['pergunta'], $result, $inicio);
    }

    public function perguntarEntrada(Request $request, DocumentoEntrada $documento)
    {
        if (! $this->permissions->canViewDocument($request->user(), $documento)) {
            abort(403, 'Você não tem acesso a este documento.');
        }

        $data = $this->validar($request);
        $this->throttle($request);
        $conversa = $this->resolverConversa($request, 'documento', DocumentoEntrada::class, $documento->id);
        $inicio = microtime(true);

        try {
            $result = $this->chatbot->askAboutEntrada($documento, $data['pergunta'], $this->historico($conversa));
        } catch (LlmException|EmbeddingException $e) {
            return response()->json(['erro' => $e->getMessage()], 503);
        }

        return $this->responder($request, $conversa, 'documento', DocumentoEntrada::class, $documento->id, $data['pergunta'], $result, $inicio);
    }

    public function perguntarInterno(Request $request, DocumentoInterno $documentoInterno)
    {
        Gate::authorize('view', $documentoInterno);

        $data = $this->validar($request);
        $this->throttle($request);
        $conversa = $this->resolverConversa($request, 'documento', DocumentoInterno::class, $documentoInterno->id);
        $inicio = microtime(true);

        try {
            $result = $this->chatbot->askAboutInterno($documentoInterno, $data['pergunta'], $this->historico($conversa));
        } catch (LlmException|EmbeddingException $e) {
            return response()->json(['erro' => $e->getMessage()], 503);
        }

        return $this->responder($request, $conversa, 'documento', DocumentoInterno::class, $documentoInterno->id, $data['pergunta'], $result, $inicio);
    }

    public function conversas(Request $request)
    {
        return ChatbotConversation::where('user_id', $request->user()->id)
            ->latest()
            ->limit(50)
            ->get(['id', 'escopo', 'documentable_type', 'documentable_id', 'titulo', 'created_at']);
    }

    public function conversa(Request $request, ChatbotConversation $conversa)
    {
        abort_unless($conversa->user_id === $request->user()->id, 403);

        return $conversa->load('messages');
    }

    public function apagarConversa(Request $request, ChatbotConversation $conversa)
    {
        abort_unless($conversa->user_id === $request->user()->id, 403);
        $conversa->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * @return array{pergunta:string, conversation_id?:?int}
     */
    private function validar(Request $request): array
    {
        return $request->validate([
            'pergunta' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['sometimes', 'nullable', 'integer'],
        ]);
    }

    private function throttle(Request $request): void
    {
        $key = 'chatbot:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($key, (int) config('chatbot.rate_limit', 20))) {
            abort(429, 'Muitas perguntas em pouco tempo. Aguarde um momento.');
        }
        RateLimiter::hit($key, 60);
    }

    private function resolverConversa(Request $request, string $escopo, ?string $type, ?int $id): ChatbotConversation
    {
        $cid = $request->input('conversation_id');
        if ($cid) {
            $existing = ChatbotConversation::where('id', $cid)->where('user_id', $request->user()->id)->first();
            if ($existing) {
                return $existing;
            }
        }

        return ChatbotConversation::create([
            'user_id' => $request->user()->id,
            'escopo' => $escopo,
            'documentable_type' => $type,
            'documentable_id' => $id,
            'titulo' => Str::limit((string) $request->input('pergunta'), 60),
        ]);
    }

    /**
     * @return array<int, array{role:string, content:string}>
     */
    private function historico(ChatbotConversation $conversa): array
    {
        return $conversa->messages()
            ->get(['role', 'conteudo'])
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->conteudo])
            ->all();
    }

    private function responder(Request $request, ChatbotConversation $conversa, string $escopo, ?string $type, ?int $id, string $pergunta, array $result, float $inicio)
    {
        ChatbotMessage::create([
            'conversation_id' => $conversa->id,
            'role' => 'user',
            'conteudo' => $pergunta,
        ]);
        ChatbotMessage::create([
            'conversation_id' => $conversa->id,
            'role' => 'assistant',
            'conteudo' => $result['resposta'],
            'citacoes' => $result['fontes'] ?? [],
        ]);

        ChatbotQueryLog::create([
            'user_id' => $request->user()->id,
            'conversation_id' => $conversa->id,
            'pergunta' => mb_substr($pergunta, 0, 1000),
            'escopo' => $escopo,
            'documentable_type' => $type,
            'documentable_id' => $id,
            'modelo_llm' => config('chatbot.llm.model'),
            'modelo_embedding' => config('chatbot.embeddings.model'),
            'latencia_ms' => (int) round((microtime(true) - $inicio) * 1000),
            'chunks_usados' => $result['chunks_usados'] ?? 0,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'conversation_id' => $conversa->id,
            'resposta' => $result['resposta'],
            'fontes' => $result['fontes'] ?? [],
        ]);
    }
}
