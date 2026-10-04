<?php

namespace App\Services;

use App\Enums\DocumentoStatus;
use App\Enums\NivelColaboracao;
use App\Events\EventoColaborativo;
use App\Jobs\EnviarResumoEdicoesColaborativas;
use App\Models\DocumentoColaborador;
use App\Models\DocumentoCollabUpdate;
use App\Models\DocumentoInterno;
use App\Models\DocumentoVersao;
use App\Models\User;
use App\Support\Sanitizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Núcleo da edição colaborativa em tempo real de Documentos Internos.
 *
 * Princípio: o Y.Doc (Yjs/CRDT) é a fonte viva durante a sessão, mas o artefacto persistido
 * canónico continua a ser `conteudo_final` (HTML). O autosave persiste HTML de forma silenciosa
 * (sem versão/auditoria/reindex); o versionamento e efeitos colaterais ocorrem no checkpoint.
 */
class DocumentoCollaborationService
{
    /**
     * Acima disto (base64) o update não segue na mensagem em tempo real: o Reverb
     * recusa mensagens maiores que max_message_size (10 000 bytes por omissão), e o
     * cliente vai buscá-lo ao servidor.
     */
    public const MAX_UPDATE_NA_MENSAGEM = 8000;

    /**
     * Linhas do log (sem contar snapshots) a partir das quais o servidor pede a um
     * cliente que compacte, sem criar versão. Sem isto o log só encolhia quando
     * alguém carregava em "Guardar versão", e cada abertura relia-o inteiro.
     */
    public const LIMITE_LOG = 500;

    /** Janela do resumo de edições enviado ao autor (uma notificação por janela). */
    public const JANELA_RESUMO_MINUTOS = 30;

    public function __construct(private DocumentoInternoService $documentoService) {}

    /**
     * Resolve o nível efetivo de colaboração de um utilizador para um documento.
     * Retorna null se não tiver qualquer acesso colaborativo.
     */
    public function nivelDe(User $user, DocumentoInterno $doc): ?NivelColaboracao
    {
        // Admin e autor administram o próprio documento.
        if ($user->isAdmin() || $doc->criado_por === $user->id) {
            return NivelColaboracao::ADMINISTRAR;
        }

        // Colaborador explicitamente convidado.
        $colab = $doc->colaboradores()->where('user_id', $user->id)->first();
        if ($colab) {
            return $colab->nivel;
        }

        // A chefia do documento pode editar — mesma regra de DocumentoInternoPolicy::update.
        if (app(DocumentoPermissionService::class)->chefiaDoDocumentoInterno($user, $doc)) {
            return NivelColaboracao::EDITAR;
        }

        return null;
    }

    public function podeColaborar(User $user, DocumentoInterno $doc): bool
    {
        return $this->nivelDe($user, $doc) !== null;
    }

    public function podeEditar(User $user, DocumentoInterno $doc): bool
    {
        $nivel = $this->nivelDe($user, $doc);

        return $nivel !== null && $nivel->podeEditar();
    }

    public function podeAdministrar(User $user, DocumentoInterno $doc): bool
    {
        $nivel = $this->nivelDe($user, $doc);

        return $nivel !== null && $nivel->podeAdministrar();
    }

    /**
     * O documento ainda aceita gravações colaborativas: rascunho, sem bloqueio e
     * sem assinatura. Fora disto, uma sessão que ficou aberta não pode reescrever o
     * conteúdo — antes continuava a gravar depois de submetido ou ASSINADO.
     */
    public function sessaoAberta(DocumentoInterno $doc): bool
    {
        $status = $doc->status instanceof DocumentoStatus ? $doc->status : DocumentoStatus::tryFrom((string) $doc->status);

        return $status === DocumentoStatus::RASCUNHO && ! $doc->bloqueado_edicao && ! $doc->assinado_em;
    }

    /**
     * Regra única de gravação (sync, seed, checkpoint, título, destinatário):
     * nível Editar E sessão aberta. Devolve null se pode, ou o motivo da recusa:
     * 'nivel' (sem permissão) ou 'encerrada' (o documento já não aceita edição).
     */
    public function motivoRecusaGravacao(User $user, DocumentoInterno $doc): ?string
    {
        if (! $this->podeEditar($user, $doc)) {
            return 'nivel';
        }

        return $this->sessaoAberta($doc) ? null : 'encerrada';
    }

    /**
     * Envia um aviso aos participantes. Sem servidor de tempo real a resposta ao
     * pedido não pode falhar: a alteração já está gravada, e quem abrir depois vê-a.
     */
    public function transmitir(DocumentoInterno $doc, string $tipo, array $dados = [], bool $excetoQuemEnviou = false): void
    {
        try {
            $evento = broadcast(new EventoColaborativo($doc->id, $tipo, $dados));
            if ($excetoQuemEnviou) {
                $evento->toOthers();
            }
            // O PendingBroadcast envia ao ser destruído; força-se aqui, dentro do
            // try, para uma falha do servidor de tempo real ser apanhada.
            unset($evento);
        } catch (\Throwable $e) {
            Log::warning('Edição colaborativa: aviso em tempo real não enviado.', [
                'documento_interno_id' => $doc->id,
                'tipo' => $tipo,
                'erro' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Estado inicial para o cliente: log de updates Yjs (base64, por ordem), os seus
     * ids (o cliente diz na compactação quais aplicou) e o HTML de fallback.
     *
     * @return array{updates: array<int, string>, ids: array<int, int>, html: string, hasState: bool}
     */
    public function estadoInicial(DocumentoInterno $doc, ?User $user = null): array
    {
        $linhas = DocumentoCollabUpdate::where('documento_interno_id', $doc->id)
            ->orderBy('id')
            ->get(['id', 'update']);
        $updates = $linhas->pluck('update')->all();

        // Primeira abertura colaborativa: o conteúdo actual fica no histórico antes de
        // qualquer edição, para ser sempre possível voltar atrás.
        if ($updates === [] && $user && $this->podeEditar($user, $doc)) {
            $this->documentoService->garantirVersaoDoConteudoActual(
                $doc, $user, 'Salvaguarda antes da edição colaborativa'
            );
        }

        return [
            'updates' => $updates,
            'ids' => $linhas->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'html' => $doc->conteudo_final ?? '',
            'hasState' => count($updates) > 0,
            // A sessão devolve-o em sync/checkpoint; se mudou, houve gravação clássica entretanto.
            'revisao_classica' => (int) $doc->revisao_classica,
        ];
    }

    public function registarUpdate(DocumentoInterno $doc, ?User $user, string $base64Update): DocumentoCollabUpdate
    {
        return DocumentoCollabUpdate::create([
            'documento_interno_id' => $doc->id,
            'user_id' => $user?->id,
            'update' => $base64Update,
            'is_snapshot' => false,
            'created_at' => now(),
        ]);
    }

    public static function chaveResumo(int $documentoId): string
    {
        return "collab_resumo:{$documentoId}";
    }

    /**
     * Junta quem edita ao resumo que o autor recebe no fim da janela. A primeira edição
     * da janela agenda o envio; as seguintes só acrescentam o nome. Por pessoa, só a
     * primeira edição da janela toca na cache com lock (o /sync corre a cada ~150 ms).
     */
    public function registarEdicaoParaResumo(DocumentoInterno $doc, User $user): void
    {
        if ((int) $doc->criado_por === (int) $user->id) {
            return;
        }

        $chave = self::chaveResumo($doc->id);
        $marca = "{$chave}:marcados:{$user->id}";
        $validade = now()->addMinutes(self::JANELA_RESUMO_MINUTOS + 10);
        if (! Cache::add($marca, true, now()->addMinutes(self::JANELA_RESUMO_MINUTOS))) {
            return;
        }

        try {
            Cache::lock("{$chave}:lock", 5)->block(2, function () use ($chave, $doc, $user, $validade) {
                $editores = Cache::get($chave, []);
                $novaJanela = $editores === [];
                $editores[$user->id] = $user->name;
                Cache::put($chave, $editores, $validade);

                if ($novaJanela) {
                    // Início da janela: o resumo conta o que cada um fez a partir daqui.
                    Cache::put("{$chave}:inicio", now()->toIso8601String(), $validade);
                    EnviarResumoEdicoesColaborativas::dispatch($doc->id)
                        ->onQueue('notifications')
                        ->delay(now()->addMinutes(self::JANELA_RESUMO_MINUTOS));
                }
            });
        } catch (\Throwable $e) {
            // O resumo é acessório: nunca impede a gravação.
            Log::info('Edição colaborativa: resumo de edições não registado.', ['erro' => $e->getMessage()]);
        }
    }

    /**
     * Retransmite aos outros participantes um update já gravado. Grande demais para
     * uma mensagem, segue só o id e o cliente vai buscar o estado ao servidor.
     */
    public function retransmitir(DocumentoInterno $doc, DocumentoCollabUpdate $linha): void
    {
        $dados = ['id' => $linha->id, 'user_id' => $linha->user_id];
        if (strlen($linha->update) <= self::MAX_UPDATE_NA_MENSAGEM) {
            $dados['update'] = $linha->update;
        }

        $this->transmitir($doc, EventoColaborativo::ALTERACAO, $dados, excetoQuemEnviou: true);
    }

    /**
     * Estado inicial (seed) enviado por quem abriu primeiro. Só é aceite com o log vazio:
     * dois seeds concorrentes duplicariam o conteúdo no CRDT.
     */
    public function registarSeed(DocumentoInterno $doc, User $user, string $base64Update): ?DocumentoCollabUpdate
    {
        return DB::transaction(function () use ($doc, $user, $base64Update) {
            DocumentoInterno::whereKey($doc->id)->lockForUpdate()->first();

            if (DocumentoCollabUpdate::where('documento_interno_id', $doc->id)->exists()) {
                return null;
            }

            return $this->registarUpdate($doc, $user, $base64Update);
        });
    }

    /**
     * Verdadeiro se a sessão abriu antes da última gravação no editor clássico.
     * Sem valor enviado (clientes antigos) não se bloqueia.
     */
    public function sessaoDesactualizada(DocumentoInterno $doc, ?int $revisaoDaSessao): bool
    {
        return $revisaoDaSessao !== null && $revisaoDaSessao !== (int) $doc->fresh()->revisao_classica;
    }

    /**
     * Verdadeiro se o HTML perderia marcadores estruturados face ao conteúdo gravado
     * (ex.: editor sem suporte para eles). Nesse caso o HTML não pode substituir o gravado.
     */
    public function degradaConteudo(DocumentoInterno $doc, string $html): bool
    {
        return DocumentoInterno::contarMarcadoresEstruturados($html)
            < DocumentoInterno::contarMarcadoresEstruturados($doc->conteudo_final);
    }

    /**
     * Autosave: persiste o HTML corrente SEM criar versão nem disparar reindex/auditoria
     * (saveQuietly). Garante durabilidade contínua durante a sessão.
     */
    public function autosave(DocumentoInterno $doc, string $html): void
    {
        $doc->conteudo_final = Sanitizer::clean($html);
        $doc->saveQuietly();
    }

    /**
     * Persiste o título/assunto sem criar versão (last-write-wins; baixa contenção).
     */
    public function salvarTitulo(DocumentoInterno $doc, string $titulo): void
    {
        $doc->titulo = $titulo;
        $doc->saveQuietly();
    }

    /**
     * Persiste os dados do destinatário sem criar versão (last-write-wins, como o título).
     */
    public function salvarDestinatario(DocumentoInterno $doc, array $campos): void
    {
        $permitidos = ['destinatario_nome', 'destinatario_cargo', 'destinatario_orgao', 'destinatario_local'];
        $doc->forceFill(array_intersect_key($campos, array_flip($permitidos)));
        $doc->saveQuietly();
    }

    /**
     * Checkpoint: materializa o HTML, cria uma versão (updateWithVersioning) e — quando fornecido
     * um snapshot Yjs completo — compacta o log num único registo. Dispara auditoria/reindex.
     */
    public function checkpoint(
        DocumentoInterno $doc,
        User $user,
        string $html,
        string $changeType = 'minor',
        ?string $changeLog = null,
        ?string $snapshotBase64 = null,
        ?string $titulo = null,
        array $idsAplicados = [],
    ): ?DocumentoInterno {
        return DB::transaction(function () use ($doc, $user, $html, $changeType, $changeLog, $snapshotBase64, $titulo, $idsAplicados) {
            // Com o documento bloqueado: dois cliques (ou dois colegas) ao mesmo tempo não
            // passam os dois pela verificação e criam duas versões iguais.
            $doc = DocumentoInterno::whereKey($doc->id)->lockForUpdate()->firstOrFail();
            $conteudo = Sanitizer::clean($html);
            $tituloFinal = $titulo ?: $doc->titulo;

            // Igual à última versão: não se cria outra. Antes cada clique criava uma versão
            // nova com o mesmo conteúdo, e a numeração subia sem nada ter mudado.
            if (! $this->difereDaUltimaVersao($doc, $conteudo, $tituloFinal)) {
                return null;
            }

            // A auditoria desta gravação diz que é uma versão da colaboração, com a
            // descrição dada (App\Support\DescricaoAuditoria).
            $doc->auditMotivo = trim(ActividadeColaborativaService::MARCA_VERSAO.' '.($changeLog ?? ''));

            // Quem escreveu desde a última versão, antes de a compactação apagar o rasto.
            $contribuidores = $this->contribuidores($doc, $snapshotBase64 !== null ? $idsAplicados : null)
                ->push($user->id)->unique()->values()->all();

            $doc = $this->documentoService->updateWithVersioning(
                $doc,
                [
                    'titulo' => $tituloFinal,
                    'conteudo_final' => $conteudo,
                ],
                $user,
                in_array($changeType, ['major', 'minor', 'patch'], true) ? $changeType : 'minor',
                $changeLog
            );

            DocumentoVersao::where('documento_interno_id', $doc->id)->latest('id')->first()
                ?->update(['contribuidores' => $contribuidores]);

            if ($snapshotBase64 !== null) {
                $doc->setAttribute('snapshot_id', $this->compactar($doc, $user, $snapshotBase64, $idsAplicados));
            }

            return $doc;
        });
    }

    public function ultimaVersao(DocumentoInterno $doc): ?DocumentoVersao
    {
        return DocumentoVersao::where('documento_interno_id', $doc->id)->latest('id')->first();
    }

    /** O conteúdo e o título diferem dos da última versão guardada (ou ainda não há nenhuma). */
    public function difereDaUltimaVersao(DocumentoInterno $doc, ?string $conteudo, ?string $titulo): bool
    {
        $ultima = $this->ultimaVersao($doc);

        return ! $ultima || (string) $ultima->conteudo_final !== (string) $conteudo || (string) $ultima->titulo !== (string) $titulo;
    }

    /**
     * Há alterações gravadas no documento (autosave) que ainda não estão numa versão.
     * Decide o estado inicial do botão "Guardar versão".
     */
    public function temAlteracoesPorGuardar(DocumentoInterno $doc): bool
    {
        return $this->difereDaUltimaVersao($doc, $doc->conteudo_final, $doc->titulo);
    }

    /**
     * Autores das linhas do log (sem snapshots): só as indicadas, ou todas.
     *
     * @param  array<int, int>|null  $ids
     */
    private function contribuidores(DocumentoInterno $doc, ?array $ids)
    {
        $query = DocumentoCollabUpdate::where('documento_interno_id', $doc->id)
            ->where('is_snapshot', false)
            ->whereNotNull('user_id');
        if ($ids !== null) {
            $query->whereIn('id', array_map('intval', $ids) ?: [0]);
        }

        return $query->distinct()->pluck('user_id')->map(fn ($id) => (int) $id);
    }

    /** O log passou do limite: o servidor pede a quem acabou de gravar que o compacte. */
    public function precisaCompactar(DocumentoInterno $doc): bool
    {
        return DocumentoCollabUpdate::where('documento_interno_id', $doc->id)
            ->where('is_snapshot', false)
            ->count() >= self::LIMITE_LOG;
    }

    /**
     * Substitui no log as linhas que o cliente diz já ter aplicado pelo snapshot dele.
     * Só essas: um update de um colega que ainda não lhe chegou continua no log e é
     * reaplicado por cima do snapshot (o Yjs é idempotente). Antes apagava-se o log
     * inteiro, e esse update perdia-se.
     *
     * @param  array<int, int>  $idsAplicados
     * @return int id da linha do snapshot
     */
    public function compactar(DocumentoInterno $doc, User $user, string $snapshotBase64, array $idsAplicados): int
    {
        return DB::transaction(function () use ($doc, $user, $snapshotBase64, $idsAplicados) {
            DocumentoInterno::whereKey($doc->id)->lockForUpdate()->first();

            $ids = array_values(array_unique(array_map('intval', $idsAplicados)));
            foreach (array_chunk($ids, 500) as $lote) {
                DocumentoCollabUpdate::where('documento_interno_id', $doc->id)->whereIn('id', $lote)->delete();
            }

            return DocumentoCollabUpdate::create([
                'documento_interno_id' => $doc->id,
                'user_id' => $user->id,
                'update' => $snapshotBase64,
                'is_snapshot' => true,
                'created_at' => now(),
            ])->id;
        });
    }

    /**
     * Convida (ou atualiza o nível de) um colaborador do MESMO gabinete do documento.
     *
     * @throws \InvalidArgumentException se o convidado não pertencer ao gabinete do documento.
     */
    public function convidar(DocumentoInterno $doc, User $inviter, User $invitee, NivelColaboracao $nivel): DocumentoColaborador
    {
        if (! $this->mesmoGabinete($doc, $invitee)) {
            throw new \InvalidArgumentException('Só é possível convidar utilizadores do mesmo gabinete do documento.');
        }

        return DocumentoColaborador::updateOrCreate(
            ['documento_interno_id' => $doc->id, 'user_id' => $invitee->id],
            ['nivel' => $nivel->value, 'convidado_por' => $inviter->id]
        );
    }

    public function definirNivel(DocumentoInterno $doc, User $invitee, NivelColaboracao $nivel): void
    {
        DocumentoColaborador::where('documento_interno_id', $doc->id)
            ->where('user_id', $invitee->id)
            ->update(['nivel' => $nivel->value]);

        // A página aberta dele foi montada com o nível antigo: recarrega.
        $this->transmitir($doc, EventoColaborativo::PERMISSOES, ['user_id' => $invitee->id]);
    }

    public function remover(DocumentoInterno $doc, User $invitee): void
    {
        DocumentoColaborador::where('documento_interno_id', $doc->id)
            ->where('user_id', $invitee->id)
            ->delete();

        $this->transmitir($doc, EventoColaborativo::PERMISSOES, ['user_id' => $invitee->id]);
    }

    /**
     * Verdadeiro se o utilizador pertence ao mesmo gabinete do documento (via departamento).
     */
    public function mesmoGabinete(DocumentoInterno $doc, User $user): bool
    {
        $docGab = $doc->gabineteEmissorId();
        $userGab = optional($user->departamento)->gabinete_id;

        return $docGab !== null && $userGab !== null && (int) $docGab === (int) $userGab;
    }

    /**
     * Cor estável (hex) por utilizador, para cursores/seleção identificados.
     */
    public function corDoUtilizador(int $userId): string
    {
        $paleta = ['#e6194B', '#3cb44b', '#4363d8', '#f58231', '#911eb4', '#008080', '#9A6324', '#800000', '#808000', '#000075'];

        return $paleta[$userId % count($paleta)];
    }
}
