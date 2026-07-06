<?php

namespace App\Services;

use App\Enums\DocumentoStatus;
use App\Models\AuditLog;
use App\Models\Departamento;
use App\Models\DocumentoEncaminhamento;
use App\Models\DocumentoEncaminhamentoExterno;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoProtocolo;
use App\Models\DocumentoTarefa;
use App\Models\Gabinete;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\DocumentoEncaminhadoDepartamento;
use App\Notifications\DocumentoEncaminhadoExterno;
use App\Notifications\SimpleBroadcastNotification;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class DocumentoEntradaService
{
    protected DocumentoPermissionService $permissionService;

    public function __construct(DocumentoPermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    /**
     * Restringe a query aos documentos de entrada visíveis pelo utilizador
     * (departamentos próprios, gabinetes responsáveis e histórico de
     * encaminhamentos). Admin vê tudo.
     */
    public function applyVisibilityScope($query, User $user): void
    {
        if ($this->permissionService->isAdmin($user)) {
            return;
        }

        $actorDeps = $this->permissionService->getUserDepartments($user);
        $actorGabIds = $this->permissionService->getUserResponsibleGabinetes($user);

        $cabinetDeps = [];
        if (count($actorGabIds)) {
            $cabinetDeps = Departamento::whereIn('gabinete_id', $actorGabIds)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }
        if ($user->hasPermissionTo('gabinete.view_all') && $user->departamento && $user->departamento->gabinete_id) {
            $userCabinetDeps = Departamento::where('gabinete_id', $user->departamento->gabinete_id)->pluck('id')->map(fn ($id) => (int) $id)->all();
            $cabinetDeps = array_merge($cabinetDeps, $userCabinetDeps);
        }

        if ($user->isSuperChefeGabinete() && $user->gabineteSuperGerenciado) {
            $superCabinetDeps = Departamento::where('gabinete_id', $user->gabineteSuperGerenciado->id)->pluck('id')->map(fn ($id) => (int) $id)->all();
            $cabinetDeps = array_merge($cabinetDeps, $superCabinetDeps);
        }

        $visibleDeps = array_values(array_unique(array_merge($actorDeps, $cabinetDeps)));

        if (count($visibleDeps)) {
            $query->where(function ($q) use ($visibleDeps) {
                $q->whereIn('departamento_id', $visibleDeps)
                    ->orWhereExists(function ($sub) use ($visibleDeps) {
                        $sub->selectRaw(1)
                            ->from('documento_encaminhamentos as de')
                            ->whereColumn('de.documento_entrada_id', 'documentos_entradas.id')
                            ->where(function ($subQ) use ($visibleDeps) {
                                $subQ->whereIn('de.destino_departamento_id', $visibleDeps)
                                    ->orWhereIn('de.origem_departamento_id', $visibleDeps);
                            });
                    });
            });
        } else {
            $query->whereNull('id');
        }
    }

    public function getFilteredDocumentsQuery(Request $request, ?User $user)
    {
        $query = DocumentoEntrada::select([
            'id',
            'numero_sequencial',
            'ano_referencia',
            'data_entrada',
            'classificacao_especie',
            'classificacao_ref_numero',
            'procedencia',
            'assunto',
            'saida_gabinete_data',
            'encaminhamento_orgao',
            'encaminhamento_oficio_numero',
            'encaminhamento_data',
            'departamento_id',
            'status',
            'visto_departamento_status',
            'visto_gabinete_status',
            'arquivo_caminho',
            'arquivado',
        ]);

        // Exclude archived documents by default
        if (! $request->has('incluir_arquivados') || ! $request->boolean('incluir_arquivados')) {
            $query->where('arquivado', false);
        }

        // Authorization Scopes
        if ($user) {
            $this->applyVisibilityScope($query, $user);
        }

        // Standard Filters
        if ($request->filled('search')) {
            $s = trim($request->input('search'));
            $query->where(function ($q) use ($s) {
                $q->where('assunto', 'like', "%$s%")
                    ->orWhere('procedencia', 'like', "%$s%")
                    ->orWhere('classificacao_especie', 'like', "%$s%")
                    ->orWhere('classificacao_ref_numero', 'like', "%$s%")
                    ->orWhereHas('tags', function ($t) use ($s) {
                        $t->where('nome', 'like', "%$s%");
                    })
                    ->orWhereHas('anexos', function ($a) use ($s) {
                        $a->where('texto_extraido', 'like', "%$s%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('departamento_id')) {
            $query->where('departamento_id', (int) $request->input('departamento_id'));
        }
        if ($request->filled('data_de')) {
            $query->whereDate('data_entrada', '>=', $request->input('data_de'));
        }
        if ($request->filled('data_ate')) {
            $query->whereDate('data_entrada', '<=', $request->input('data_ate'));
        }
        if ($request->filled('ano')) {
            $query->where('ano_referencia', (int) $request->input('ano'));
        }

        // Special Views ("Meus")
        if ($user && $meus = $request->input('meus')) {
            $deps = $this->permissionService->getUserDepartments($user);
            $actorGabIds = $this->permissionService->getUserResponsibleGabinetes($user);

            switch ($meus) {
                case 'pendentes_recebimento':
                    if (count($deps)) {
                        $query->whereExists(function ($sub) use ($deps) {
                            $sub->selectRaw(1)
                                ->from('documento_encaminhamentos as de')
                                ->whereColumn('de.documento_entrada_id', 'documentos_entradas.id')
                                ->whereNull('de.recebido_em')
                                ->whereIn('de.destino_departamento_id', $deps);
                        });
                    }
                    break;
                case 'visto_pendente':
                    if (count($deps)) {
                        $query->whereNull('visto_departamento_status')
                            ->where('status', DocumentoStatus::RECEBIDO->value)
                            ->whereIn('departamento_id', $deps);
                    }
                    break;
                case 'visto_aprovado':
                    if (count($deps)) {
                        $query->where('visto_departamento_status', 'aprovado')->whereIn('departamento_id', $deps);
                    }
                    break;
                case 'visto_rejeitado':
                    if (count($deps)) {
                        $query->where('visto_departamento_status', 'rejeitado')->whereIn('departamento_id', $deps);
                    }
                    break;
                case 'visto_gabinete_pendente':
                    if (count($actorGabIds)) {
                        $query->whereNull('visto_gabinete_status')
                            ->whereHas('departamento', function ($q) use ($actorGabIds) {
                                $q->whereIn('gabinete_id', $actorGabIds);
                            });
                    }
                    break;
                case 'visto_gabinete_aprovado':
                    if (count($actorGabIds)) {
                        $query->where('visto_gabinete_status', 'aprovado')
                            ->whereHas('departamento', function ($q) use ($actorGabIds) {
                                $q->whereIn('gabinete_id', $actorGabIds);
                            });
                    }
                    break;
                case 'visto_gabinete_rejeitado':
                    if (count($actorGabIds)) {
                        $query->where('visto_gabinete_status', 'rejeitado')
                            ->whereHas('departamento', function ($q) use ($actorGabIds) {
                                $q->whereIn('gabinete_id', $actorGabIds);
                            });
                    }
                    break;
                case 'tarefas_responsavel_pendente':
                    $query->whereExists(function ($sub) use ($user) {
                        $sub->selectRaw(1)
                            ->from('documento_tarefas as dt')
                            ->whereColumn('dt.documento_entrada_id', 'documentos_entradas.id')
                            ->where('dt.responsavel_user_id', $user->id)
                            ->where('dt.status', 'pendente');
                    });
                    break;
            }
        }

        // Sort
        $sort = $request->input('sort', 'data_entrada');
        $dir = $request->input('direction', 'desc');
        $allowedSort = ['data_entrada', 'numero_sequencial', 'ano_referencia'];
        if (! in_array($sort, $allowedSort)) {
            $sort = 'data_entrada';
        }
        if (! in_array($dir, ['asc', 'desc'])) {
            $dir = 'desc';
        }
        $query->orderBy($sort, $dir);

        return $query;
    }

    public function getFilteredDocuments(Request $request, ?User $user)
    {
        $query = $this->getFilteredDocumentsQuery($request, $user)
            ->with([
                'departamento:id,nome',
                'ultimoEncaminhamento',
                'ultimoEncaminhamento.origemDepartamento:id,nome',
                'ultimoEncaminhamento.destinoDepartamento:id,nome',
            ]);

        // Paginate
        $perPage = (int) $request->input('per_page', 10);
        if ($perPage < 5) {
            $perPage = 5;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        return $query->paginate($perPage)->appends($request->query());
    }

    public function createDocument(array $data, $mainFile = null, $attachments = [])
    {
        for ($attempts = 1; $attempts <= 3; $attempts++) {
            try {
                return DB::transaction(function () use ($data, $mainFile, $attachments) {
                    return $this->processCreation($data, $mainFile, $attachments);
                });
            } catch (QueryException $e) {
                $msg = strtolower($e->getMessage());
                $isDup = str_contains($msg, 'duplicate') || (int) $e->getCode() === 23000 || $e->errorInfo[1] == 1062;
                if ($isDup && $attempts < 3) {
                    usleep(200000); // Wait 200ms

                    continue;
                }
                throw $e;
            }
        }
    }

    protected function processCreation(array $data, $mainFile, $attachments)
    {
        $ano = (int) date('Y');

        $lastDoc = DocumentoEntrada::withTrashed()
            ->where('ano_referencia', $ano)
            ->orderBy('numero_sequencial', 'desc')
            ->lockForUpdate()
            ->first();

        $lastSeq = $lastDoc ? $lastDoc->numero_sequencial : 0;
        $seq = $lastSeq + 1;

        $doc = DocumentoEntrada::create([
            'numero_sequencial' => $seq,
            'ano_referencia' => $ano,
            'data_entrada' => now(),
            'classificacao_especie' => $data['classificacao_especie'] ?? null,
            'classificacao_ref_numero' => $data['classificacao_ref_numero'] ?? null,
            'data_documento' => $data['data_documento'] ?? null,
            'procedencia' => $data['procedencia'] ?? null,
            'assunto' => $data['assunto'],
            'observacoes' => $data['observacoes'] ?? null,
            'saida_gabinete_data' => $data['saida_gabinete_data'] ?? null,
            'encaminhamento_orgao' => $data['encaminhamento_orgao'] ?? null,
            'encaminhamento_oficio_numero' => $data['encaminhamento_oficio_numero'] ?? null,
            'encaminhamento_data' => null,
            'departamento_id' => $data['departamento_id'],
            'user_id' => Auth::id(),
            'status' => DocumentoStatus::REGISTRADO->value,
            'arquivo_caminho' => null,
        ]);

        $dep = Departamento::with('gabinete')->find((int) $doc->departamento_id);
        $gab = optional($dep)->gabinete;
        $depSlug = Str::slug(optional($dep)->nome ?? 'sem-departamento');
        $gabSlug = Str::slug(optional($gab)->sigla ?? (optional($gab)->nome ?? 'sem-gabinete'));
        $docsDisk = config('filesystems.docs_disk', 'public');

        if ($mainFile) {
            $base = 'documentos_entradas/'.$ano.'/'.$gabSlug.'/'.$depSlug.'/'.sprintf('%03d', $seq);
            $storedPath = $mainFile->store($base, $docsDisk);
            $doc->arquivo_caminho = $storedPath;
            $doc->save();
        }

        $codigoProt = sprintf('PRT-%d-%03d-%s', $ano, $seq, strtoupper(Str::random(6)));
        $urlConsulta = route('documentos-entradas.protocolo', ['documento' => $doc->id]);
        DocumentoProtocolo::create([
            'documento_entrada_id' => $doc->id,
            'codigo' => $codigoProt,
            'url_consulta' => $urlConsulta,
            'gerado_em' => now(),
        ]);

        if (! empty($data['tags'])) {
            $tagNames = array_filter(array_map('trim', explode(',', $data['tags'])));
            $tagIds = [];
            foreach ($tagNames as $tagName) {
                $tag = Tag::firstOrCreate(
                    ['slug' => Str::slug($tagName)],
                    ['nome' => $tagName]
                );
                $tagIds[] = $tag->id;
            }
            $doc->tags()->sync($tagIds);
        }

        if (! empty($attachments)) {
            $base = 'documentos_entradas/'.$ano.'/'.$gabSlug.'/'.$depSlug.'/'.sprintf('%03d', $seq).'/anexos';
            $ordem = 0;
            foreach ($attachments as $file) {
                $ordem++;
                $storedPath = $file->store($base, $docsDisk);
                $anexo = $doc->anexos()->create([
                    'nome_original' => $file->getClientOriginalName(),
                    'caminho_arquivo' => $storedPath,
                    'mime_type' => $file->getMimeType(),
                    'tamanho_bytes' => $file->getSize(),
                    'descricao' => null,
                    'ordem' => $ordem,
                    'user_id' => Auth::id(),
                ]);

                \App\Jobs\ProcessarOcrAnexo::dispatch($anexo->id);

                if (! $doc->arquivo_caminho && $ordem === 1) {
                    $doc->arquivo_caminho = $storedPath;
                    $doc->save();
                }
            }
        }

        return $doc;
    }

    public function createTask(DocumentoEntrada $documento, array $data, User $actor)
    {
        $tarefa = DocumentoTarefa::create([
            'documento_entrada_id' => $documento->id,
            'titulo' => $data['titulo'],
            'descricao' => $data['descricao'] ?? null,
            'assigned_by_id' => $actor->id,
            'assigned_to_user_id' => $data['assigned_to_user_id'] ?? null,
            'assigned_to_departamento_id' => $data['assigned_to_departamento_id'] ?? null,
            'prazo_at' => $data['prazo_at'] ?? null,
            'status' => 'pendente',
            'grupo_tarefa_uuid' => $data['grupo_tarefa_uuid'] ?? null,
        ]);

        $numero = sprintf('%03d/%d', $documento->numero_sequencial, $documento->ano_referencia);
        $url = route('documentos-entradas.show', $documento->id);

        if ($tarefa->assigned_to_user_id) {
            $u = User::find($tarefa->assigned_to_user_id);
            if ($u) {
                $title = 'Nova tarefa no documento '.$numero;
                $body = 'Destino: '.($u->name ?? 'usuário');
                $u->notify(new SimpleBroadcastNotification($title, $body, $url));
            }
        }

        if ($tarefa->assigned_to_departamento_id) {
            $dep = Departamento::find($tarefa->assigned_to_departamento_id);
            if ($dep) {
                $title = 'Nova tarefa no documento '.$numero;
                $body = 'Destino: '.($dep->nome ?? 'departamento');
                $targets = collect();
                if ($dep->chefe) {
                    $targets->push($dep->chefe);
                }
                $dep->usuarios()->chunk(100, function ($users) use ($targets) {
                    foreach ($users as $user) {
                        $targets->push($user);
                    }
                });
                if ($dep->gabinete && $dep->gabinete->responsavel) {
                    $targets->push($dep->gabinete->responsavel);
                }
                $targets = $targets->unique('id')->values();
                if ($targets->count()) {
                    Notification::send($targets, new SimpleBroadcastNotification($title, $body, $url));
                }
            }
        }

        event(new \App\Events\TaskAssigned($tarefa));

        return $tarefa;
    }

    public function completeTask(DocumentoTarefa $tarefa, User $actor, ?int $responsavelId = null)
    {
        if ($tarefa->assigned_to_departamento_id) {
            if ($responsavelId) {
                $tarefa->responsavel_user_id = $responsavelId;
            } else {
                $tarefa->responsavel_user_id = $actor->id;
            }
        }
        $tarefa->status = 'concluida';
        $tarefa->save();

        $documento = $tarefa->documento;
        $numero = sprintf('%03d/%d', $documento->numero_sequencial, $documento->ano_referencia);
        $url = route('documentos-entradas.show', $documento->id);
        $title = 'Tarefa concluída no documento '.$numero;
        $body = 'Concluída por '.($actor->name ?? 'usuário');

        $notify = collect();
        if ($tarefa->assigned_by_id) {
            $assigner = User::find($tarefa->assigned_by_id);
            if ($assigner) {
                $notify->push($assigner);
            }
        }
        if ($tarefa->assigned_to_user_id && $tarefa->assigned_to_user_id !== $actor->id) {
            $user = User::find($tarefa->assigned_to_user_id);
            if ($user) {
                $notify->push($user);
            }
        }

        $notify = $notify->unique('id')->values();
        if ($notify->count()) {
            Notification::send($notify, new SimpleBroadcastNotification($title, $body, $url));
        }

        return $tarefa;
    }

    public function cancelTask(DocumentoTarefa $tarefa, User $actor)
    {
        $tarefa->status = 'cancelada';
        $tarefa->save();

        $documento = $tarefa->documento;
        $numero = sprintf('%03d/%d', $documento->numero_sequencial, $documento->ano_referencia);
        $url = route('documentos-entradas.show', $documento->id);
        $title = 'Tarefa cancelada no documento '.$numero;
        $body = 'Cancelada por '.($actor->name ?? 'usuário');

        $notify = collect();
        if ($tarefa->assigned_by_id) {
            $assigner = User::find($tarefa->assigned_by_id);
            if ($assigner) {
                $notify->push($assigner);
            }
        }
        if ($tarefa->assigned_to_user_id && $tarefa->assigned_to_user_id !== $actor->id) {
            $user = User::find($tarefa->assigned_to_user_id);
            if ($user) {
                $notify->push($user);
            }
        }

        $notify = $notify->unique('id')->values();
        if ($notify->count()) {
            Notification::send($notify, new SimpleBroadcastNotification($title, $body, $url));
        }

        return $tarefa;
    }

    public function forwardDocument(DocumentoEntrada $documento, int $targetDepId, ?string $observation, User $actor)
    {
        $enc = DB::transaction(function () use ($documento, $targetDepId, $observation, $actor) {
            // Trava a linha do documento para serializar encaminhamentos concorrentes
            // (fecha a janela de corrida entre o guard do controller e este INSERT,
            // evitando duplicação por duplo-clique).
            $locked = DocumentoEntrada::whereKey($documento->id)->lockForUpdate()->first();

            // Re-verificação atómica: não pode haver encaminhamento pendente.
            $pendente = DocumentoEncaminhamento::where('documento_entrada_id', $documento->id)
                ->whereNull('recebido_em')
                ->lockForUpdate()
                ->exists();
            if ($pendente) {
                throw new \RuntimeException('Há encaminhamento pendente; aguarde o recebimento antes de criar um novo.');
            }

            $e = DocumentoEncaminhamento::create([
                'documento_entrada_id' => $locked->id,
                'origem_departamento_id' => $locked->departamento_id,
                'destino_departamento_id' => $targetDepId,
                'usuario_id' => $actor->id,
                'encaminhado_em' => now(),
                'status' => DocumentoStatus::ENCAMINHADO->value,
                'observacao' => $observation,
            ]);
            $locked->status = DocumentoStatus::ENCAMINHADO->value;
            $locked->encaminhamento_data = now();
            $locked->save();

            // Sincroniza a instância em memória recebida pelo chamador.
            $documento->status = $locked->status;
            $documento->encaminhamento_data = $locked->encaminhamento_data;

            $this->audit('documento.encaminhado', $documento, $actor, [
                'encaminhamento_id' => $e->id,
                'origem_departamento_id' => $e->origem_departamento_id,
                'destino_departamento_id' => $targetDepId,
            ]);

            return $e;
        });

        // Notify
        $usuariosDestino = User::where('id', '!=', $actor->id)
            ->where(function ($q) use ($targetDepId) {
                $q->where('departamento_id', $targetDepId)
                    ->orWhereHas('departamentos', function ($q2) use ($targetDepId) {
                        $q2->where('departamentos.id', $targetDepId);
                    });
            })
            ->get();

        $depDestino = Departamento::find($targetDepId);
        if ($depDestino && $depDestino->chefe) {
            $usuariosDestino->push($depDestino->chefe);
            $usuariosDestino = $usuariosDestino->unique('id')->values();
        }

        if ($usuariosDestino->count()) {
            Notification::send($usuariosDestino, new DocumentoEncaminhadoDepartamento($documento, $enc, $actor));
        }

        return $enc;
    }

    public function cancelForwarding(DocumentoEncaminhamento $encaminhamento, User $actor)
    {
        return DB::transaction(function () use ($encaminhamento, $actor) {
            $documento = $encaminhamento->documento;

            // Estado coerente com o histórico (sem inventar estados): se o documento
            // já foi recebido alguma vez (existe OUTRO encaminhamento recebido), volta
            // a RECEBIDO; caso contrário nunca saiu da origem → volta a REGISTRADO.
            $jaRecebidoAntes = DocumentoEncaminhamento::where('documento_entrada_id', $documento->id)
                ->where('id', '!=', $encaminhamento->id)
                ->whereNotNull('recebido_em')
                ->exists();
            $novoStatus = $jaRecebidoAntes
                ? DocumentoStatus::RECEBIDO->value
                : DocumentoStatus::REGISTRADO->value;

            $documento->status = $novoStatus;
            $documento->encaminhamento_data = null;
            $documento->save();

            // Auditoria ANTES do hard delete — preserva a evidência do encaminhamento cancelado.
            $this->audit('documento.encaminhamento_cancelado', $documento, $actor, [
                'encaminhamento_id' => $encaminhamento->id,
                'origem_departamento_id' => $encaminhamento->origem_departamento_id,
                'destino_departamento_id' => $encaminhamento->destino_departamento_id,
                'status_revertido' => $novoStatus,
            ]);

            // Remove o registro de encaminhamento
            $encaminhamento->delete();
        });
    }

    /**
     * Marca um encaminhamento como recebido e move o documento para o departamento
     * de destino. Idempotente: se o encaminhamento já foi recebido (corrida/duplo
     * clique), não repete a ação nem as notificações.
     *
     * @return bool  true se recebeu agora; false se já estava recebido.
     */
    public function receiveDocument(DocumentoEntrada $documento, DocumentoEncaminhamento $encaminhamento, User $actor): bool
    {
        $recebeu = DB::transaction(function () use ($documento, $encaminhamento, $actor) {
            // Trava e re-verifica dentro da transação (anti-duplo-clique/corrida).
            $locked = DocumentoEncaminhamento::whereKey($encaminhamento->id)->lockForUpdate()->first();
            if (! $locked || $locked->recebido_em) {
                return false;
            }

            $locked->recebido_em = now();
            $locked->recebido_por_id = $actor->id;
            $locked->status = DocumentoStatus::RECEBIDO->value;
            $locked->save();

            $docLocked = DocumentoEntrada::whereKey($documento->id)->lockForUpdate()->first();
            $docLocked->departamento_id = $locked->destino_departamento_id;
            $docLocked->status = DocumentoStatus::RECEBIDO->value;
            $docLocked->save();

            // Sincroniza as instâncias em memória do chamador.
            $encaminhamento->recebido_em = $locked->recebido_em;
            $encaminhamento->recebido_por_id = $locked->recebido_por_id;
            $encaminhamento->status = $locked->status;
            $documento->departamento_id = $docLocked->departamento_id;
            $documento->status = $docLocked->status;

            $this->audit('documento.recebido', $documento, $actor, [
                'encaminhamento_id' => $locked->id,
                'origem_departamento_id' => $locked->origem_departamento_id,
                'destino_departamento_id' => $locked->destino_departamento_id,
            ]);

            return true;
        });

        if (! $recebeu) {
            return false;
        }

        $depAtual = Departamento::find($documento->departamento_id);
        if ($depAtual && $depAtual->chefe && (int) $depAtual->chefe->id !== (int) $actor->id) {
            $numero = sprintf('%03d/%d', $documento->numero_sequencial, $documento->ano_referencia);
            $titulo = 'Documento '.$numero.' recebido no departamento '.($depAtual->nome ?? '');
            $depAtual->chefe->notify(new SimpleBroadcastNotification($titulo, 'Aguardando ação do chefe', route('documentos-entradas.show', $documento->id)));
        }

        return true;
    }

    public function receiveBatch(array $documentoIds, User $actor): array
    {
        $success = 0;
        $failed = 0;

        // Eager load dos encaminhamentos ainda não recebidos (ordenados do mais recente
        // para o mais antigo) para evitar uma query por documento (N+1) dentro do loop.
        $documentos = DocumentoEntrada::whereIn('id', $documentoIds)
            ->with(['encaminhamentos' => function ($q) {
                $q->whereNull('recebido_em')->orderByDesc('encaminhado_em');
            }])
            ->get();

        foreach ($documentos as $doc) {
            $enc = $doc->encaminhamentos->first();

            if (! $enc) {
                $failed++;

                continue;
            }

            if (! $this->permissionService->canReceiveInDepartment($actor, (int) $enc->destino_departamento_id)) {
                $failed++;

                continue;
            }

            try {
                if ($this->receiveDocument($doc, $enc, $actor)) {
                    $success++;
                } else {
                    // Já estava recebido (corrida) — não conta como sucesso novo.
                    $failed++;
                }
            } catch (\Exception $e) {
                $failed++;
            }
        }

        return ['success' => $success, 'failed' => $failed];
    }

    /**
     * Encaminha vários documentos para o mesmo departamento de destino, em lote.
     * Cada documento é autorizado individualmente (Policy 'encaminhar') e validado
     * (sem pendência, destino diferente do atual). Reutiliza forwardDocument().
     *
     * @param  int[]  $documentoIds
     * @return array{success:int, failed:int}
     */
    public function forwardBatch(array $documentoIds, int $targetDepId, ?string $observation, User $actor): array
    {
        $success = 0;
        $failed = 0;

        $documentos = DocumentoEntrada::whereIn('id', $documentoIds)
            ->with(['encaminhamentos' => fn ($q) => $q->whereNull('recebido_em')])
            ->get();

        foreach ($documentos as $doc) {
            // Autorização por documento (mesma regra do fluxo individual).
            if (! Gate::forUser($actor)->allows('encaminhar', $doc)) {
                $failed++;

                continue;
            }
            // Não encaminhar para o próprio departamento.
            if ((int) $doc->departamento_id === $targetDepId) {
                $failed++;

                continue;
            }
            // Não pode haver encaminhamento pendente.
            if ($doc->encaminhamentos->isNotEmpty()) {
                $failed++;

                continue;
            }

            try {
                $this->forwardDocument($doc, $targetDepId, $observation, $actor);
                $success++;
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        return ['success' => $success, 'failed' => $failed];
    }

    public function sendToGabinete(DocumentoEntrada $documento, int $targetGabId, string $date, ?string $oficio, ?string $observation, User $actor)
    {
        $gabineteDestino = Gabinete::find($targetGabId);

        $encExterno = DB::transaction(function () use ($documento, $targetGabId, $date, $oficio, $observation, $actor, $gabineteDestino) {
            $documento->saida_gabinete_data = Carbon::parse($date);
            $documento->encaminhamento_orgao = $gabineteDestino ? ($gabineteDestino->sigla ? ($gabineteDestino->nome.' ('.$gabineteDestino->sigla.')') : $gabineteDestino->nome) : null;
            $documento->encaminhamento_oficio_numero = $oficio ?? null;
            $documento->status = DocumentoStatus::ENCAMINHADO_EXTERNO->value;
            $documento->save();

            $ext = DocumentoEncaminhamentoExterno::create([
                'documento_entrada_id' => $documento->id,
                'origem_gabinete_id' => optional($documento->departamento)->gabinete_id,
                'destino_gabinete_id' => $targetGabId,
                'usuario_id' => $actor->id,
                'oficio_numero' => $oficio ?? null,
                'enviado_em' => Carbon::parse($date)->startOfDay(),
                'observacao' => $observation,
                'status' => 'enviado',
            ]);

            $this->audit('documento.saida_gabinete', $documento, $actor, [
                'encaminhamento_externo_id' => $ext->id,
                'origem_gabinete_id' => $ext->origem_gabinete_id,
                'destino_gabinete_id' => $targetGabId,
                'oficio_numero' => $oficio ?? null,
            ]);

            return $ext;
        });

        $depIds = Departamento::where('gabinete_id', $targetGabId)->pluck('id');
        $usuariosGabinete = User::where('id', '!=', $actor->id)
            ->where(function ($q) use ($depIds) {
                $q->whereIn('departamento_id', $depIds)
                    ->orWhereHas('departamentos', function ($q2) use ($depIds) {
                        $q2->whereIn('departamentos.id', $depIds);
                    });
            })
            ->get();

        if ($gabineteDestino && $gabineteDestino->responsavel) {
            $usuariosGabinete->push($gabineteDestino->responsavel);
            $usuariosGabinete = $usuariosGabinete->unique('id')->values();
        }

        if ($usuariosGabinete->count()) {
            Notification::send($usuariosGabinete, new DocumentoEncaminhadoExterno($documento, $encExterno, $actor));
        }

        // Confirmation to author
        $confirmTitle = 'Documento '.sprintf('%03d/%d', $documento->numero_sequencial, $documento->ano_referencia)
            .' enviado para '.($gabineteDestino ? ($gabineteDestino->sigla ? ($gabineteDestino->nome.' ('.$gabineteDestino->sigla.')') : $gabineteDestino->nome) : 'gabinete destino');
        $actor->notify(new SimpleBroadcastNotification($confirmTitle, 'Saída registrada com sucesso', route('documentos-entradas.show', $documento->id)));
    }

    public function registerVisto(DocumentoEntrada $documento, string $type, string $status, User $actor): void
    {
        // $type: 'departamento' or 'gabinete'
        // $status: 'aprovado' or 'rejeitado'

        $fieldStatus = "visto_{$type}_status";
        $fieldPor = "visto_{$type}_por";
        $fieldData = "visto_{$type}_data";

        $documento->$fieldStatus = $status;
        $documento->$fieldPor = $actor->id;
        $documento->$fieldData = now();
        $documento->save();
    }

    public function updateDocument(DocumentoEntrada $documento, array $data, $mainFile = null, $attachments = [])
    {
        return DB::transaction(function () use ($documento, $data, $mainFile, $attachments) {
            // Filter out nulls from data if necessary, or just fill
            // Ensure specific fields that are not in $data are not overwritten if not intended
            // But usually fill() is safe with validated data.

            // Explicitly handle nullable fields if they are passed as null
            $fillableData = [];
            foreach ($data as $key => $value) {
                $fillableData[$key] = $value;
            }

            $documento->fill($fillableData);

            if ($mainFile) {
                if ($documento->arquivo_caminho) {
                    \Illuminate\Support\Facades\Storage::disk(config('filesystems.docs_disk'))->delete($documento->arquivo_caminho);
                }
                $dep = Departamento::find((int) $documento->departamento_id);
                $gab = optional($dep)->gabinete;
                $depSlug = Str::slug(optional($dep)->nome ?? 'sem-departamento');
                $gabSlug = Str::slug(optional($gab)->sigla ?? (optional($gab)->nome ?? 'sem-gabinete'));
                $base = 'documentos_entradas/'.$documento->ano_referencia.'/'.$gabSlug.'/'.$depSlug.'/'.sprintf('%03d', $documento->numero_sequencial);
                $documento->arquivo_caminho = $mainFile->store($base, config('filesystems.docs_disk'));
            }

            if (! empty($attachments)) {
                $ordem = ($documento->anexos()->max('ordem') ?? 0);
                $dep = Departamento::find((int) $documento->departamento_id);
                $gab = optional($dep)->gabinete;
                $depSlug = Str::slug(optional($dep)->nome ?? 'sem-departamento');
                $gabSlug = Str::slug(optional($gab)->sigla ?? (optional($gab)->nome ?? 'sem-gabinete'));
                $base = 'documentos_entradas/'.$documento->ano_referencia.'/'.$gabSlug.'/'.$depSlug.'/'.sprintf('%03d', $documento->numero_sequencial).'/anexos';
                $docsDisk = config('filesystems.docs_disk');

                foreach ($attachments as $file) {
                    $ordem++;
                    $storedPath = $file->store($base, $docsDisk);
                    $anexo = $documento->anexos()->create([
                        'nome_original' => $file->getClientOriginalName(),
                        'caminho_arquivo' => $storedPath,
                        'mime_type' => $file->getMimeType(),
                        'tamanho_bytes' => $file->getSize(),
                        'descricao' => null,
                        'ordem' => $ordem,
                        'user_id' => Auth::id(),
                    ]);

                    \App\Jobs\ProcessarOcrAnexo::dispatch($anexo->id);
                }
            }

            $documento->save();

            return $documento;
        });
    }

    /**
     * Regista uma ação do fluxo de encaminhamento na trilha de auditoria central
     * (audit_logs), reutilizando o mesmo padrão do ArchiveService. Nunca bloqueia
     * a operação principal — falhas de auditoria são silenciadas.
     */
    private function audit(string $action, DocumentoEntrada $documento, User $actor, array $newValues = []): void
    {
        try {
            $status = $documento->status;
            if ($status instanceof \BackedEnum) {
                $status = $status->value;
            }

            AuditLog::create([
                'user_id' => $actor->id,
                'action' => $action,
                'auditable_type' => DocumentoEntrada::class,
                'auditable_id' => $documento->id,
                'old_values' => null,
                'new_values' => $newValues + ['status' => $status],
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);
        } catch (\Throwable $e) {
            // Auditoria nunca deve impedir o encaminhamento/recebimento.
        }
    }
}
