<?php

namespace App\Services;

use App\Enums\DocumentoStatus;
use App\Events\TaskAssigned;
use App\Jobs\ProcessarOcrAnexo;
use App\Models\AuditLog;
use App\Models\Departamento;
use App\Models\DocumentoEncaminhamento;
use App\Models\DocumentoEncaminhamentoExterno;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoProtocolo;
use App\Models\DocumentoTarefa;
use App\Models\Gabinete;
use App\Models\Procedencia;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\DocumentoEncaminhadoDepartamento;
use App\Notifications\DocumentoEncaminhadoExterno;
use App\Notifications\DocumentoEntradaRegistado;
use App\Notifications\SimpleBroadcastNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
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
                    })
                    ->orWhereExists(function ($sub) use ($visibleDeps) {
                        $sub->selectRaw(1)
                            ->from('documento_entrada_departamentos_destino as ded')
                            ->whereColumn('ded.documento_entrada_id', 'documentos_entradas.id')
                            ->whereIn('ded.departamento_id', $visibleDeps);
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

        $query->distinct();

        // Authorization Scopes
        if ($user) {
            $this->applyVisibilityScope($query, $user);
        }

        // Standard Filters
        if ($request->filled('search')) {
            $s = trim($request->input('search'));

            $numSearch = null;
            $anoSearch = null;
            if (str_contains($s, '/')) {
                $parts = explode('/', $s);
                $cleanNum = preg_replace('/[^0-9]/', '', $parts[0]);
                $cleanAno = isset($parts[1]) ? preg_replace('/[^0-9]/', '', $parts[1]) : '';
                if ($cleanNum !== '') {
                    $numSearch = (int) $cleanNum;
                }
                if ($cleanAno !== '') {
                    $anoSearch = (int) $cleanAno;
                }
            } elseif (is_numeric($s)) {
                $numSearch = (int) $s;
            }

            $driver = DB::connection()->getDriverName();
            if ($driver === 'sqlite') {
                $concatExpr1 = "numero_sequencial || '/' || ano_referencia";
                $concatExpr2 = "printf('%03d', numero_sequencial) || '/' || ano_referencia";
            } elseif ($driver === 'pgsql') {
                $concatExpr1 = "CONCAT(numero_sequencial, '/', ano_referencia)";
                $concatExpr2 = "CONCAT(LPAD(numero_sequencial::text, 3, '0'), '/', ano_referencia)";
            } else {
                $concatExpr1 = "CONCAT(numero_sequencial, '/', ano_referencia)";
                $concatExpr2 = "CONCAT(LPAD(numero_sequencial, 3, '0'), '/', ano_referencia)";
            }

            $query->where(function ($q) use ($s, $numSearch, $anoSearch, $concatExpr1, $concatExpr2) {
                if ($numSearch !== null && $anoSearch !== null) {
                    $q->orWhere(function ($qNumAno) use ($numSearch, $anoSearch) {
                        $qNumAno->where('numero_sequencial', $numSearch)
                            ->where('ano_referencia', $anoSearch);
                    });
                } elseif ($numSearch !== null) {
                    $q->orWhere('numero_sequencial', $numSearch)
                        ->orWhere('ano_referencia', $numSearch);
                }

                $q->orWhere(DB::raw($concatExpr1), 'like', "%$s%")
                    ->orWhere(DB::raw($concatExpr2), 'like', "%$s%")
                    ->orWhere('assunto', 'like', "%$s%")
                    ->orWhere('procedencia', 'like', "%$s%")
                    ->orWhere('classificacao_especie', 'like', "%$s%")
                    ->orWhere('classificacao_ref_numero', 'like', "%$s%")
                    ->orWhereHas('protocolo', function ($p) use ($s) {
                        $p->where('codigo', 'like', "%$s%");
                    })
                    ->orWhereHas('tags', function ($t) use ($s) {
                        $t->where('nome', 'like', "%$s%");
                    })
                    ->orWhereHas('anexos', function ($a) use ($s) {
                        $a->pesquisarTextoExtraido($s);
                    });
            });
        }

        // Role-Based Workflow Tab Filter
        if ($user) {
            $profile = $this->permissionService->getUserWorkflowProfile($user);
            $activeTab = $request->input('tab') ?: $this->getDefaultTabForProfile($profile);
            $this->applyRoleTabFilter($query, $activeTab, $user, $profile);
        } elseif ($request->filled('tab') && $request->input('tab') !== 'todos') {
            switch ($request->input('tab')) {
                case 'carecer_tratamento':
                    $query->whereIn('status', [DocumentoStatus::PENDENTE_TRATAMENTO->value, DocumentoStatus::REGISTRADO->value]);
                    break;
                case 'tratados':
                    $query->where('status', DocumentoStatus::TRATADO->value);
                    break;
                case 'encaminhados':
                    $query->whereIn('status', [DocumentoStatus::ENCAMINHADO->value, DocumentoStatus::RECEBIDO->value]);
                    break;
            }
        } elseif ($request->filled('status')) {
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
        $this->applySpecialViewFilters($query, $request, $user);

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
                'departamento:id,nome,gabinete_id',
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
                $documento = DB::transaction(function () use ($data, $mainFile, $attachments) {
                    return $this->processCreation($data, $mainFile, $attachments);
                });

                // Fora da transação: o destino tem de saber que entrou trabalho.
                $this->notificarRegisto($documento, Auth::user());

                return $documento;
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

        $procedenciaId = $data['procedencia_id'] ?? null;
        $procedenciaNome = $data['procedencia'] ?? null;

        if ($procedenciaId && ! $procedenciaNome) {
            $pObj = Procedencia::find($procedenciaId);
            if ($pObj) {
                $procedenciaNome = $pObj->nome;
            }
        } elseif ($procedenciaNome && ! $procedenciaId) {
            $pObj = Procedencia::whereRaw('LOWER(TRIM(nome)) = ?', [mb_strtolower(trim($procedenciaNome))])->first();
            if ($pObj) {
                $procedenciaId = $pObj->id;
            }
        }

        $lastSeq = $lastDoc ? $lastDoc->numero_sequencial : 0;
        $seq = $lastSeq + 1;

        $doc = DocumentoEntrada::create([
            'numero_sequencial' => $seq,
            'ano_referencia' => $ano,
            // Data real de receção ao balcão; só recai em hoje se não for indicada.
            'data_entrada' => ! empty($data['data_entrada']) ? Carbon::parse($data['data_entrada']) : now(),
            'classificacao_especie' => $data['classificacao_especie'] ?? null,
            'classificacao_ref_numero' => $data['classificacao_ref_numero'] ?? null,
            'data_documento' => $data['data_documento'] ?? null,
            'procedencia' => $procedenciaNome,
            'procedencia_id' => $procedenciaId,
            'assunto' => $data['assunto'],
            'observacoes' => $data['observacoes'] ?? null,
            'saida_gabinete_data' => $data['saida_gabinete_data'] ?? null,
            'encaminhamento_orgao' => $data['encaminhamento_orgao'] ?? null,
            'encaminhamento_oficio_numero' => $data['encaminhamento_oficio_numero'] ?? null,
            'encaminhamento_data' => null,
            'departamento_id' => $data['departamento_id'],
            'user_id' => Auth::id(),
            'status' => DocumentoStatus::PENDENTE_TRATAMENTO->value,
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
        // Caminho relativo por desenho: um URL absoluto gravaria aqui o host
        // da máquina que fez o registo e ficaria preso a ele para sempre. O
        // endereço do QR é montado ao imprimir, a partir do código.
        $urlConsulta = route('protocolo.publico', ['codigo' => $codigoProt], absolute: false);
        DocumentoProtocolo::create([
            'documento_entrada_id' => $doc->id,
            'codigo' => $codigoProt,
            'url_consulta' => $urlConsulta,
            'gerado_em' => now(),
        ]);

        if (array_key_exists('tags', $data)) {
            $this->sincronizarTags($doc, $data['tags']);
        }

        if (! empty($attachments)) {
            $base = 'documentos_entradas/'.$ano.'/'.$gabSlug.'/'.$depSlug.'/'.sprintf('%03d', $seq).'/anexos';
            $ordem = 0;
            foreach ($attachments as $file) {
                $ordem++;
                $storedPath = $file->store($base, $docsDisk);
                $mime = $file->getMimeType();
                $isOcr = ($mime === 'application/pdf' || str_starts_with((string) $mime, 'image/'));

                $anexo = $doc->anexos()->create([
                    'nome_original' => $file->getClientOriginalName(),
                    'caminho_arquivo' => $storedPath,
                    'mime_type' => $mime,
                    'tamanho_bytes' => $file->getSize(),
                    'descricao' => null,
                    'ordem' => $ordem,
                    'user_id' => Auth::id(),
                    'ocr_status' => $isOcr ? 'PENDENTE' : 'NAO_APLICAVEL',
                ]);

                if ($isOcr) {
                    ProcessarOcrAnexo::dispatch($anexo->id);
                }

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
        return DB::transaction(function () use ($documento, $data, $actor) {
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

            // Regra de negócio (documentos externos): ao delegar, a chefia dá
            // o visto do departamento. O documento NÃO fica tratado aqui — o
            // trabalho ainda nem começou; fica-o quando a última tarefa é
            // concluída (ver fecharSeSemTarefasPendentes).
            if ($this->permissionService->canManageTasks($actor, $documento) || $this->permissionService->isAdmin($actor)) {
                if (empty($documento->texto_despacho)) {
                    $documento->texto_despacho = 'Documento aprovado via delegação de tarefa: '.$data['titulo'];
                }
                $documento->despachado_por_id = $actor->id;
                $documento->data_despacho = now();
                $documento->visto_gabinete_status = 'aprovado';
                $documento->visto_gabinete_por = $actor->id;
                $documento->visto_gabinete_data = now();

                if (! empty($data['assigned_to_departamento_id'])) {
                    $documento->departamentosDestino()->syncWithoutDetaching([(int) $data['assigned_to_departamento_id']]);
                }

                $documento->save();

                $this->audit('documento.aprovado_via_delegacao', $documento, $actor, [
                    'tarefa_id' => $tarefa->id,
                    'tarefa_titulo' => $tarefa->titulo,
                    'mensagem' => "Documento Externo aprovado automaticamente por via de delegação de tarefa por {$actor->name}",
                ]);
            }

            event(new TaskAssigned($tarefa));

            return $tarefa;
        });
    }

    /**
     * Nº mínimo de documentos de uma procedência para haver base de sugestão,
     * e fração do histórico que tem de ter ido ao mesmo departamento.
     *
     * Sem estes limiares a funcionalidade sugeriria a partir de um único caso —
     * e uma sugestão errada a cada registo treina o balcão a ignorá-la.
     */
    private const MIN_HISTORICO_SUGESTAO = 3;

    private const FRACAO_DOMINANCIA_SUGESTAO = 0.6;

    /**
     * Departamento para onde os documentos desta procedência costumam ir.
     *
     * @return int|null null quando não há histórico suficiente ou quando ele
     *                  está dividido entre setores.
     */
    public function departamentoSugeridoPara(?int $procedenciaId): ?int
    {
        if (! $procedenciaId) {
            return null;
        }

        $historico = DocumentoEntrada::query()
            ->where('procedencia_id', $procedenciaId)
            ->whereNotNull('departamento_id')
            ->selectRaw('departamento_id, COUNT(*) AS total')
            ->groupBy('departamento_id')
            ->orderByDesc('total')
            ->get();

        $total = (int) $historico->sum('total');
        if ($total < self::MIN_HISTORICO_SUGESTAO) {
            return null;
        }

        $dominante = $historico->first();
        if (! $dominante || ($dominante->total / $total) < self::FRACAO_DOMINANCIA_SUGESTAO) {
            return null;
        }

        return (int) $dominante->departamento_id;
    }

    /**
     * Mapa procedência -> departamento sugerido, para o formulário de registo
     * poder reagir sem ida ao servidor a cada mudança de procedência.
     *
     * @return array<int, int>
     */
    public function sugestoesDeDestino(): array
    {
        $historico = DocumentoEntrada::query()
            ->whereNotNull('procedencia_id')
            ->whereNotNull('departamento_id')
            ->selectRaw('procedencia_id, departamento_id, COUNT(*) AS total')
            ->groupBy('procedencia_id', 'departamento_id')
            ->get()
            ->groupBy('procedencia_id');

        $sugestoes = [];
        foreach ($historico as $procedenciaId => $linhas) {
            $total = (int) $linhas->sum('total');
            if ($total < self::MIN_HISTORICO_SUGESTAO) {
                continue;
            }

            $dominante = $linhas->sortByDesc('total')->first();
            if (($dominante->total / $total) >= self::FRACAO_DOMINANCIA_SUGESTAO) {
                $sugestoes[(int) $procedenciaId] = (int) $dominante->departamento_id;
            }
        }

        return $sugestoes;
    }

    /**
     * Procura um registo que aparente ser o mesmo documento físico: mesma
     * procedência, mesmo número de referência e mesma data do documento.
     *
     * Sem número de referência não há critério fiável de comparação — dois
     * ofícios do mesmo remetente no mesmo dia podem ser documentos distintos —
     * pelo que nesse caso não se assinala nada.
     */
    public function procurarPossivelDuplicado(array $data): ?DocumentoEntrada
    {
        $referencia = trim((string) ($data['classificacao_ref_numero'] ?? ''));
        if ($referencia === '') {
            return null;
        }

        $procedenciaId = $data['procedencia_id'] ?? null;
        $procedenciaNome = trim((string) ($data['procedencia'] ?? ''));
        if (! $procedenciaId && $procedenciaNome === '') {
            return null;
        }

        return DocumentoEntrada::query()
            ->where('classificacao_ref_numero', $referencia)
            ->when(
                $procedenciaId,
                fn ($q) => $q->where('procedencia_id', $procedenciaId),
                fn ($q) => $q->whereRaw('LOWER(TRIM(procedencia)) = ?', [mb_strtolower($procedenciaNome)]),
            )
            ->when(
                ! empty($data['data_documento']),
                fn ($q) => $q->whereDate('data_documento', $data['data_documento']),
                fn ($q) => $q->whereNull('data_documento'),
            )
            ->latest('id')
            ->first();
    }

    /**
     * Avisa a chefia do departamento de destino e o responsável do gabinete de
     * que entrou um documento novo.
     *
     * Corre fora da transação e engole qualquer falha: uma notificação nunca
     * pode fazer falhar o registo. Em particular, deixar escapar uma
     * QueryException aqui faria o ciclo de retentativa de numeração repetir a
     * criação e gerar um documento duplicado — daí o catch a \Throwable.
     */
    private function notificarRegisto(DocumentoEntrada $documento, ?User $actor): void
    {
        try {
            $dep = Departamento::with(['chefe', 'gabinete.responsavel'])->find($documento->departamento_id);
            if (! $dep) {
                return;
            }

            $destinatarios = collect([
                $dep->chefe,
                $dep->responsavel_id ? User::find($dep->responsavel_id) : null,
                optional($dep->gabinete)->responsavel,
            ])->filter();

            if ($actor) {
                $destinatarios = $destinatarios->reject(fn ($u) => (int) $u->id === (int) $actor->id);
            }

            $destinatarios = $destinatarios->unique('id')->values();
            if ($destinatarios->isEmpty()) {
                return;
            }

            Notification::send($destinatarios, new DocumentoEntradaRegistado($documento, $actor));
        } catch (\Throwable $e) {
            Log::warning('Falha ao notificar o registo do documento de entrada.', [
                'documento_id' => $documento->id,
                'erro' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Departamentos para onde este documento pode ser despachado: os do seu
     * próprio gabinete. Despachar para fora concederia visibilidade via
     * departamentosDestino — a saída inter-gabinete tem caminho próprio
     * (sendToGabinete).
     *
     * Documentos cujo departamento não tem gabinete definido (dados legados)
     * não são restringidos, para não bloquear o despacho; a causa corrige-se
     * com `php artisan departamentos:fix-null-gabinete`.
     */
    public function departamentosDestinoPermitidos(DocumentoEntrada $documento)
    {
        $gabId = optional($documento->departamento)->gabinete_id;

        $query = Departamento::query()->orderBy('nome');
        if ($gabId) {
            $query->where('gabinete_id', $gabId);
        }

        return $query->get(['id', 'nome', 'sigla', 'gabinete_id']);
    }

    /**
     * Regra única de quem pode receber uma tarefa num documento de entrada.
     * Partilhada pelo DocumentoEntradaTarefaController e pelo quickAction — antes
     * só o primeiro a aplicava, pelo que o segundo aceitava qualquer utilizador.
     *
     * @return string|null mensagem de erro, ou null se o destinatário é válido.
     */
    public function validarDestinatarioTarefa(DocumentoEntrada $documento, User $destino, User $actor): ?string
    {
        $docGabId = optional($documento->departamento)->gabinete_id;

        if ($actor->isSuperChefeDoGabinete($docGabId)) {
            $gabinete = $documento->departamento ? $documento->departamento->gabinete : null;
            $isChefeGab = $gabinete && (int) $gabinete->responsavel_id === (int) $destino->id;
            $isChefeDep = Departamento::where('gabinete_id', $docGabId)
                ->where('responsavel_id', $destino->id)
                ->exists();

            return ($isChefeGab || $isChefeDep)
                ? null
                : 'O Super Chefe só pode delegar tarefas ao Chefe de Gabinete ou aos Chefes de Departamento do respetivo gabinete.';
        }

        if ($this->permissionService->isGabineteResponsavel($actor, $docGabId)) {
            $destinoDep = $destino->departamento;

            return ($destinoDep && (int) $destinoDep->gabinete_id === (int) $docGabId)
                ? null
                : 'Selecione usuário do seu gabinete.';
        }

        // Chefe de departamento: o destinatário tem de pertencer ao departamento
        // onde o documento se encontra.
        $depId = (int) $documento->departamento_id;
        $pertence = ((int) $destino->departamento_id === $depId)
            || $destino->departamentos()->where('departamento_id', $depId)->exists();

        return $pertence ? null : 'Selecione usuário do seu departamento.';
    }

    /**
     * Conclui uma tarefa e guarda o parecer do técnico.
     *
     * O parecer é a razão de ser da tarefa. Havia dois caminhos para concluir e
     * só um o gravava: o modal da listagem pedia o texto como obrigatório e
     * deitava-o fora, porque nem o controlador o lia nem este método o
     * escrevia. Passa a existir uma só implementação, e é esta.
     *
     * @param  string|null  $resposta  Parecer do técnico; um valor vazio não
     *                                 apaga um parecer já registado.
     */
    public function completeTask(DocumentoTarefa $tarefa, User $actor, ?int $responsavelId = null, ?string $resposta = null)
    {
        if ($tarefa->assigned_to_departamento_id) {
            if ($responsavelId) {
                $tarefa->responsavel_user_id = $responsavelId;
            } else {
                $tarefa->responsavel_user_id = $actor->id;
            }
        }

        if (filled($resposta)) {
            $tarefa->resposta = trim($resposta);
        }

        $tarefa->status = 'concluida';
        // A linha do tempo precisa da data real de conclusão: o updated_at
        // deslocar-se-ia com qualquer alteração posterior à tarefa.
        $tarefa->concluida_em = $tarefa->concluida_em ?? now();
        $tarefa->save();

        $documento = $tarefa->documento;

        // Feita a última tarefa, o documento está tratado pelo departamento.
        // Antes esta marca era posta na delegação, o que dava por concluído o
        // que ainda não tinha começado.
        $this->fecharSeSemTarefasPendentes($documento, $actor);
        $numero = sprintf('%03d/%d', $documento->numero_sequencial, $documento->ano_referencia);
        $url = route('documentos-entradas.show', $documento->id);
        $title = 'Tarefa concluída no documento '.$numero;
        $body = 'Concluída por '.($actor->name ?? 'usuário');
        $type = 'tarefa_concluida';

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
            Notification::send($notify, new SimpleBroadcastNotification($title, $body, $url, 'normal', $type));
        }

        return $tarefa;
    }

    /**
     * Marca o documento como TRATADO quando já não há tarefas por fazer.
     *
     * Não mexe em documentos arquivados nem faz recuar estados posteriores:
     * limita-se a fechar o ciclo do departamento.
     */
    private function fecharSeSemTarefasPendentes(?DocumentoEntrada $documento, User $actor): void
    {
        if (! $documento || $documento->arquivado) {
            return;
        }

        $aindaPendentes = DocumentoTarefa::where('documento_entrada_id', $documento->id)
            ->where('status', 'pendente')
            ->exists();

        if ($aindaPendentes || $documento->status === DocumentoStatus::TRATADO->value) {
            return;
        }

        $documento->status = DocumentoStatus::TRATADO->value;
        $documento->save();

        $this->audit('documento.tratado_pelo_departamento', $documento, $actor, [
            'origem' => 'conclusao_de_tarefas',
        ]);
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
        $type = 'tarefa_cancelada';

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
            Notification::send($notify, new SimpleBroadcastNotification($title, $body, $url, 'normal', $type));
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

        $this->notificarDestino($documento, $enc, $actor);

        return $enc;
    }

    /**
     * Avisa o departamento de destino de que recebeu um documento.
     *
     * Partilhado pelo encaminhamento avulso (forwardDocument) e pelo despacho
     * (despacharDocumento): desde que o despacho passou a entregar o documento
     * de imediato, é ele o momento em que o departamento tem de ser avisado.
     * Chamar sempre FORA da transação — uma falha de notificação não pode
     * desfazer um encaminhamento já consumado.
     */
    private function notificarDestino(DocumentoEntrada $documento, DocumentoEncaminhamento $enc, User $actor): void
    {
        $targetDepId = (int) $enc->destino_departamento_id;

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
     * @return bool true se recebeu agora; false se já estava recebido.
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
            $depAtual->chefe->notify(new SimpleBroadcastNotification($titulo, 'Aguardando ação do chefe', route('documentos-entradas.show', $documento->id), 'normal', 'documento_recebido'));
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
        $actor->notify(new SimpleBroadcastNotification($confirmTitle, 'Saída registrada com sucesso', route('documentos-entradas.show', $documento->id), 'normal', 'documento_enviado'));
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

            // Sincroniza procedencia_id e texto da procedência
            if (! empty($data['procedencia_id'])) {
                $pObj = Procedencia::find($data['procedencia_id']);
                if ($pObj) {
                    $data['procedencia'] = $pObj->nome;
                }
            } elseif (! empty($data['procedencia'])) {
                $pObj = Procedencia::whereRaw('LOWER(TRIM(nome)) = ?', [mb_strtolower(trim($data['procedencia']))])->first();
                if ($pObj) {
                    $data['procedencia_id'] = $pObj->id;
                }
            }

            // As tags não são coluna: saem do lote antes do fill() e são
            // sincronizadas à parte. Ficavam por gravar na edição — o campo
            // existia no formulário, o utilizador corrigia-o e o registo
            // respondia "atualizado com sucesso" sem ter mudado nada.
            // Distingue-se "não veio no pedido" de "veio vazio": o campo vazio é
            // como se apaga uma tag mal escrita, pelo que não pode ser tratado
            // como ausência.
            $sincronizarTags = array_key_exists('tags', $data);
            $tags = $sincronizarTags ? $data['tags'] : null;
            unset($data['tags']);

            // Explicitly handle nullable fields if they are passed as null
            $fillableData = [];
            foreach ($data as $key => $value) {
                $fillableData[$key] = $value;
            }

            $documento->fill($fillableData);

            if ($mainFile) {
                if ($documento->arquivo_caminho) {
                    Storage::disk(config('filesystems.docs_disk'))->delete($documento->arquivo_caminho);
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
                    $mime = $file->getMimeType();
                    $isOcr = ($mime === 'application/pdf' || str_starts_with((string) $mime, 'image/'));

                    $anexo = $documento->anexos()->create([
                        'nome_original' => $file->getClientOriginalName(),
                        'caminho_arquivo' => $storedPath,
                        'mime_type' => $mime,
                        'tamanho_bytes' => $file->getSize(),
                        'descricao' => null,
                        'ordem' => $ordem,
                        'user_id' => Auth::id(),
                        'ocr_status' => $isOcr ? 'PENDENTE' : 'NAO_APLICAVEL',
                    ]);

                    if ($isOcr) {
                        ProcessarOcrAnexo::dispatch($anexo->id);
                    }
                }
            }

            $documento->save();

            if ($sincronizarTags) {
                $this->sincronizarTags($documento, $tags);
            }

            return $documento;
        });
    }

    /**
     * Sincroniza as palavras-chave do documento a partir da lista separada por
     * vírgulas que vem do formulário.
     *
     * Fonte única para o registo e para a edição: a lógica vivia só no
     * createDocument, pelo que corrigir tags nunca chegou a funcionar.
     * Uma lista vazia limpa as tags — é como se desfaz uma tag mal escrita.
     */
    private function sincronizarTags(DocumentoEntrada $documento, ?string $tags): void
    {
        $tagNames = array_filter(array_map('trim', explode(',', (string) $tags)));

        $tagIds = [];
        foreach ($tagNames as $tagName) {
            $tag = Tag::firstOrCreate(
                ['slug' => Str::slug($tagName)],
                ['nome' => $tagName]
            );
            $tagIds[] = $tag->id;
        }

        $documento->tags()->sync($tagIds);
    }

    /**
     * Despacha o documento e entrega-o, no mesmo ato, aos departamentos indicados.
     *
     * Regra de negócio: não há duas fases. Antes o despacho parava em TRATADO e
     * a distribuição ficava a cargo do expediente, num segundo gesto manual —
     * com o efeito de documentos que já tinham saído continuarem a figurar como
     * "prontos a encaminhar". Quem despacha escolhe os destinos, logo o
     * encaminhamento é parte do mesmo ato e o documento fica ENCAMINHADO.
     *
     * Esta é a fonte única do despacho: usada pelo modal do detalhe, pelo painel
     * rápido da gaveta e pelo despacho em lote.
     */
    public function despacharDocumento(DocumentoEntrada $documento, string $textoDespacho, array $departamentosIds, User $actor): DocumentoEntrada
    {
        $destinos = array_values(array_unique(array_map('intval', $departamentosIds)));

        $encaminhamentos = DB::transaction(function () use ($documento, $textoDespacho, $destinos, $actor) {
            $texto = trim($textoDespacho);
            $agora = now();

            $documento->texto_despacho = $texto;
            $documento->despachado_por_id = $actor->id;
            $documento->data_despacho = $agora;

            // Quem despacha viu e aprovou: o visto do gabinete faz parte do ato.
            // Ficava por gravar nesta via, embora a delegação já o fizesse.
            $documento->visto_gabinete_status = 'aprovado';
            $documento->visto_gabinete_por = $actor->id;
            $documento->visto_gabinete_data = $agora;

            $documento->status = DocumentoStatus::ENCAMINHADO->value;
            $documento->encaminhamento_data = $agora;
            $documento->save();

            $documento->departamentosDestino()->sync($destinos);

            // Destinos que ainda têm um encaminhamento por receber não levam
            // outro: um segundo despacho para o mesmo sítio antes da receção
            // duplicaria a entrega, que foi exatamente o defeito corrigido aqui.
            $porReceber = DocumentoEncaminhamento::where('documento_entrada_id', $documento->id)
                ->whereNull('recebido_em')
                ->pluck('destino_departamento_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $criados = [];
            foreach ($destinos as $destinoId) {
                if (in_array($destinoId, $porReceber, true)) {
                    continue;
                }

                $criados[] = DocumentoEncaminhamento::create([
                    'documento_entrada_id' => $documento->id,
                    'origem_departamento_id' => $documento->departamento_id,
                    'destino_departamento_id' => $destinoId,
                    'usuario_id' => $actor->id,
                    'encaminhado_em' => $agora,
                    'status' => DocumentoStatus::ENCAMINHADO->value,
                    'observacao' => $texto,
                ]);
            }

            $this->audit('documento.despachado', $documento, $actor, [
                'despacho' => $texto,
                'departamentos_destino' => $destinos,
                'encaminhamentos_criados' => count($criados),
            ]);

            return $criados;
        });

        foreach ($encaminhamentos as $enc) {
            $this->notificarDestino($documento, $enc, $actor);
        }

        return $documento;
    }

    /**
     * Despacha vários documentos com o mesmo texto e os mesmos destinos.
     *
     * O P3 restringe os destinos ao gabinete DE CADA documento, pelo que uma
     * lista de destinos comum só é legítima dentro de um gabinete. Uma seleção
     * que atravesse gabinetes é recusada em bloco — falhar em silêncio metade
     * do lote seria pior do que recusar.
     *
     * @param  int[]  $documentoIds
     * @param  int[]  $departamentosIds
     * @return array{despachados:int, ignorados:int}
     *
     * @throws \RuntimeException quando a seleção é inválida como um todo.
     */
    public function despacharBatch(array $documentoIds, string $textoDespacho, array $departamentosIds, User $actor): array
    {
        $documentos = DocumentoEntrada::with('departamento')
            ->whereIn('id', $documentoIds)
            ->where('arquivado', false)
            ->get();

        if ($documentos->isEmpty()) {
            throw new \RuntimeException('Nenhum documento elegível na seleção.');
        }

        $gabinetes = $documentos->map(fn ($d) => optional($d->departamento)->gabinete_id)->unique();
        if ($gabinetes->count() > 1) {
            throw new \RuntimeException(
                'A seleção abrange documentos de gabinetes diferentes. '
                .'Despache um gabinete de cada vez: os departamentos de destino não são os mesmos.'
            );
        }

        // Os destinos têm de pertencer ao gabinete dos documentos.
        $permitidos = $this->departamentosDestinoPermitidos($documentos->first())->pluck('id')->all();
        $foraDoGabinete = array_diff($departamentosIds, $permitidos);
        if ($foraDoGabinete) {
            throw new \RuntimeException('Só é possível despachar para departamentos do gabinete destes documentos.');
        }

        $despachados = 0;
        $ignorados = 0;

        foreach ($documentos as $documento) {
            if (! $this->permissionService->canDespachar($actor, $documento)) {
                $ignorados++;

                continue;
            }

            try {
                $this->despacharDocumento($documento, $textoDespacho, $departamentosIds, $actor);
                $despachados++;
            } catch (\Throwable $e) {
                $ignorados++;
            }
        }

        if ($despachados === 0) {
            throw new \RuntimeException('Nenhum documento pôde ser despachado. Verifique as permissões sobre o gabinete.');
        }

        return ['despachados' => $despachados, 'ignorados' => $ignorados];
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

    public function getDefaultTabForProfile(string $profile): string
    {
        return match ($profile) {
            'gabinete' => 'carecer_tratamento',
            // O expediente já não distribui documentos (o despacho entrega-os
            // logo), por isso 'tratados' abria sempre numa lista vazia. O seu
            // trabalho é o registo e o que aguarda despacho.
            'expediente' => 'carecer_tratamento',
            'chefe_departamento' => 'novos_departamento',
            'tecnico' => 'atribuidos_mim',
            default => 'todos',
        };
    }

    public function applyRoleTabFilter($query, string $tabKey, ?User $user, string $profile)
    {
        if ($tabKey === 'todos' || $tabKey === 'todos_registrados' || $tabKey === 'todos_departamento') {
            return;
        }

        $userDeps = $user ? $this->permissionService->getUserDepartments($user) : [];

        switch ($tabKey) {
            case 'carecer_tratamento':
                $query->whereIn('status', [DocumentoStatus::PENDENTE_TRATAMENTO->value, DocumentoStatus::REGISTRADO->value]);
                break;
            case 'tratados':
                $query->where('status', DocumentoStatus::TRATADO->value);
                break;
            case 'encaminhados':
                $query->whereIn('status', [DocumentoStatus::ENCAMINHADO->value, DocumentoStatus::RECEBIDO->value]);
                break;
            case 'novos_departamento':
                $query->whereIn('status', [DocumentoStatus::ENCAMINHADO->value, DocumentoStatus::RECEBIDO->value, DocumentoStatus::TRATADO->value])
                    ->whereDoesntHave('tarefas', function ($t) {
                        $t->where('status', 'pendente');
                    });
                break;
            case 'delegados':
                $query->whereHas('tarefas', function ($t) {
                    $t->where('status', 'pendente');
                });
                break;
                // 'em_andamento' saiu dos filtros: nada no sistema escreve esse
                // estado (as tarefas vão de 'pendente' a 'concluida'), e concluir()
                // recusa tudo o que não esteja em 'pendente'. Ficaria um separador
                // a prometer uma fase que não existe.
            case 'atribuidos_mim':
                if ($user) {
                    $query->whereHas('tarefas', function ($t) use ($user) {
                        $t->where('assigned_to_user_id', $user->id)
                            ->where('status', 'pendente');
                    });
                }
                break;
            case 'em_execucao':
                // "Do Meu Setor": o que o técnico pode assumir — tarefas do seu
                // departamento ainda sem dono. Antes este separador incluía
                // também as próprias tarefas, pelo que era um superconjunto de
                // "Atribuídos a Mim" e os dois contadores diziam o mesmo.
                if ($user && count($userDeps)) {
                    $query->whereHas('tarefas', function ($t) use ($userDeps) {
                        $t->whereIn('assigned_to_departamento_id', $userDeps)
                            ->whereNull('assigned_to_user_id')
                            ->where('status', 'pendente');
                    });
                } elseif ($user) {
                    // Sem departamento não há fila de setor para mostrar.
                    $query->whereRaw('1 = 0');
                }
                break;
            case 'concluidos':
                if ($user && $profile === 'tecnico') {
                    $query->whereHas('tarefas', function ($t) use ($user) {
                        $t->where('assigned_to_user_id', $user->id)->where('status', 'concluida');
                    });
                } else {
                    $query->whereHas('tarefas', function ($t) {
                        $t->where('status', 'concluida');
                    })->whereDoesntHave('tarefas', function ($t) {
                        $t->where('status', 'pendente');
                    });
                }
                break;
        }
    }

    private function applySpecialViewFilters(Builder $query, Request $request, ?User $user): void
    {
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
            }
        }
    }

    public function getRoleWorkflowTabs(?User $user, Request $request): array
    {
        $profile = $user ? $this->permissionService->getUserWorkflowProfile($user) : 'gabinete';
        $activeTab = $request->input('tab') ?: $this->getDefaultTabForProfile($profile);

        $baseQuery = DocumentoEntrada::query()->where('arquivado', false);
        if ($user) {
            $this->applyVisibilityScope($baseQuery, $user);
        }
        $this->applySpecialViewFilters($baseQuery, $request, $user);

        $tabsConfig = match ($profile) {
            'gabinete' => [
                ['key' => 'carecer_tratamento', 'label' => 'A Carecer de Tratamento', 'icon' => 'far fa-clock', 'badge_type' => 'warning'],
                // TRATADO deixou de significar "pronto a encaminhar": o despacho
                // já entrega o documento. Agora só lá chegam os documentos que a
                // chefia do departamento tratou (ver criarTarefa).
                ['key' => 'tratados', 'label' => 'Tratados pelos Departamentos', 'icon' => 'far fa-check-circle', 'badge_type' => 'info'],
                ['key' => 'encaminhados', 'label' => 'Encaminhados', 'icon' => 'far fa-paper-plane', 'badge_type' => 'neutral'],
                ['key' => 'todos', 'label' => 'Todos do Gabinete', 'icon' => 'fas fa-layer-group', 'badge_type' => 'neutral'],
            ],
            'expediente' => [
                ['key' => 'carecer_tratamento', 'label' => 'Aguardando Despacho', 'icon' => 'far fa-clock', 'badge_type' => 'warning'],
                ['key' => 'encaminhados', 'label' => 'Encaminhados / Distribuídos', 'icon' => 'far fa-paper-plane', 'badge_type' => 'neutral'],
                ['key' => 'tratados', 'label' => 'Tratados pelos Departamentos', 'icon' => 'far fa-check-circle', 'badge_type' => 'info'],
                ['key' => 'todos', 'label' => 'Todos Registrados', 'icon' => 'fas fa-layer-group', 'badge_type' => 'neutral'],
            ],
            'chefe_departamento' => [
                ['key' => 'novos_departamento', 'label' => 'Novos no Departamento', 'icon' => 'far fa-folder-open', 'badge_type' => 'warning'],
                ['key' => 'delegados', 'label' => 'Delegados / Em Andamento', 'icon' => 'fas fa-tasks', 'badge_type' => 'info'],
                ['key' => 'concluidos', 'label' => 'Concluídos', 'icon' => 'far fa-check-square', 'badge_type' => 'neutral'],
                ['key' => 'todos_departamento', 'label' => 'Todos do Departamento', 'icon' => 'fas fa-building', 'badge_type' => 'neutral'],
            ],
            'tecnico' => [
                ['key' => 'atribuidos_mim', 'label' => 'Atribuídos a Mim', 'icon' => 'fas fa-user-check', 'badge_type' => 'warning'],
                ['key' => 'em_execucao', 'label' => 'Do Meu Setor', 'icon' => 'fas fa-users', 'badge_type' => 'info'],
                ['key' => 'concluidos', 'label' => 'Concluídos', 'icon' => 'far fa-check-circle', 'badge_type' => 'neutral'],
            ],
            default => [
                ['key' => 'todos', 'label' => 'Todos os Documentos', 'icon' => 'fas fa-layer-group', 'badge_type' => 'neutral'],
            ],
        };

        $tabs = [];
        foreach ($tabsConfig as $cfg) {
            $q = clone $baseQuery;
            $this->applyRoleTabFilter($q, $cfg['key'], $user, $profile);
            $count = $q->count();

            $badgeClass = match ($cfg['badge_type']) {
                'warning' => $count > 0 ? 'tab-badge-warning' : 'tab-badge-neutral opacity-50',
                'info' => $count > 0 ? 'tab-badge-info' : 'tab-badge-neutral opacity-50',
                default => 'tab-badge-neutral'.($count == 0 ? ' opacity-50' : ''),
            };

            $tabs[] = [
                'key' => $cfg['key'],
                'label' => $cfg['label'],
                'icon' => $cfg['icon'],
                'count' => $count,
                'badge_class' => $badgeClass,
                'is_active' => ($cfg['key'] === $activeTab),
            ];
        }

        return $tabs;
    }
}
