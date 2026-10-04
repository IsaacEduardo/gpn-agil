<?php

namespace App\Services;

use App\Events\EventoColaborativo;
use App\Models\DocumentoComentario;
use App\Models\DocumentoInterno;
use App\Models\User;
use App\Notifications\ComentarioColaborativoNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Comentários da edição colaborativa (nível Comentar e acima).
 *
 * Ancoram-se ao trecho citado, não a uma marca no texto, para o HTML oficial (PDF,
 * assinatura) não levar rasto deles. Só existem enquanto o documento está em rascunho,
 * como a própria colaboração.
 */
class DocumentoComentarioService
{
    public function __construct(
        private DocumentoCollaborationService $colaboracao,
        private ActividadeColaborativaService $actividade,
    ) {}

    public function podeComentar(User $user, DocumentoInterno $doc): bool
    {
        $nivel = $this->colaboracao->nivelDe($user, $doc);

        return $nivel !== null && $nivel->podeComentar() && $this->colaboracao->sessaoAberta($doc);
    }

    /** Resolver ou reabrir: quem abriu a conversa, ou quem pode editar o documento. */
    public function podeResolver(User $user, DocumentoComentario $comentario): bool
    {
        $doc = $comentario->documento;

        return $this->colaboracao->sessaoAberta($doc)
            && ((int) $comentario->user_id === (int) $user->id || $this->colaboracao->podeEditar($user, $doc));
    }

    /**
     * Conversas do documento: comentários de topo com as respostas, abertos primeiro.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function conversas(DocumentoInterno $doc, User $user): Collection
    {
        return DocumentoComentario::with(['autor:id,name', 'resolvidoPor:id,name', 'respostas.autor:id,name'])
            ->where('documento_interno_id', $doc->id)
            ->whereNull('parent_id')
            ->orderByRaw('resolvido_em IS NOT NULL')
            ->orderBy('id')
            ->get()
            ->map(fn (DocumentoComentario $c) => [
                'id' => $c->id,
                'autor' => $c->autor?->name,
                'trecho' => $c->trecho,
                'texto' => $c->texto,
                'criado_em' => $c->created_at?->format('d/m/Y H:i'),
                'resolvido' => $c->resolvido_em !== null,
                'resolvido_por' => $c->resolvidoPor?->name,
                'pode_resolver' => $this->podeResolver($user, $c),
                'respostas' => $c->respostas->map(fn (DocumentoComentario $r) => [
                    'id' => $r->id,
                    'autor' => $r->autor?->name,
                    'texto' => $r->texto,
                    'criado_em' => $r->created_at?->format('d/m/Y H:i'),
                ])->values(),
            ]);
    }

    public function comentar(DocumentoInterno $doc, User $user, string $texto, ?string $trecho, ?int $paiId): DocumentoComentario
    {
        $pai = null;
        if ($paiId !== null) {
            // Resposta a uma resposta pende da conversa (um só nível).
            $pai = DocumentoComentario::where('documento_interno_id', $doc->id)->findOrFail($paiId);
            $pai = $pai->parent_id ? DocumentoComentario::findOrFail($pai->parent_id) : $pai;
        }

        $comentario = DocumentoComentario::create([
            'documento_interno_id' => $doc->id,
            'user_id' => $user->id,
            'parent_id' => $pai?->id,
            'trecho' => $pai ? null : ($trecho !== null ? trim($trecho) : null),
            'texto' => trim($texto),
        ]);

        // Uma resposta reabre a conversa resolvida: há assunto de novo.
        if ($pai && $pai->resolvido_em) {
            $pai->update(['resolvido_em' => null, 'resolvido_por' => null]);
        }

        $this->notificar($comentario, $pai);
        $this->colaboracao->transmitir($doc, EventoColaborativo::COMENTARIOS);
        $this->actividade->registar($doc, $user, ActividadeColaborativaService::COMENTARIO, [
            'comentario_id' => $comentario->id,
            'resposta' => $pai !== null,
            'trecho' => $pai ? $pai->trecho : $comentario->trecho,
            'texto' => \Illuminate\Support\Str::limit($comentario->texto, 300),
        ]);
        $this->colaboracao->registarEdicaoParaResumo($doc, $user);

        return $comentario;
    }

    public function resolver(DocumentoComentario $comentario, User $user, bool $resolvido): void
    {
        $comentario->update($resolvido
            ? ['resolvido_em' => now(), 'resolvido_por' => $user->id]
            : ['resolvido_em' => null, 'resolvido_por' => null]);

        $this->colaboracao->transmitir($comentario->documento, EventoColaborativo::COMENTARIOS);
        $this->actividade->registar(
            $comentario->documento,
            $user,
            $resolvido ? ActividadeColaborativaService::COMENTARIO_RESOLVIDO : ActividadeColaborativaService::COMENTARIO_REABERTO,
            ['comentario_id' => $comentario->id, 'texto' => \Illuminate\Support\Str::limit($comentario->texto, 300)],
        );
    }

    /**
     * Conversa nova: o autor do documento. Resposta: quem já escreveu nessa conversa.
     * Nunca quem acabou de escrever.
     */
    private function notificar(DocumentoComentario $comentario, ?DocumentoComentario $pai): void
    {
        $ids = $pai
            ? DocumentoComentario::where('id', $pai->id)->orWhere('parent_id', $pai->id)->pluck('user_id')
            : collect([$comentario->documento->criado_por]);

        $destinatarios = User::whereIn('id', $ids->filter()->unique()->reject(fn ($id) => (int) $id === (int) $comentario->user_id))->get();

        if ($destinatarios->isNotEmpty()) {
            Notification::send($destinatarios, new ComentarioColaborativoNotification($comentario));
        }
    }
}
