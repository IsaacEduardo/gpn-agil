<?php

namespace App\Http\Controllers;

use App\Exports\DocumentoEntradasExport;
use App\Http\Requests\StoreDocumentoEntradaRequest;
use App\Services\DocumentoEntradaService;
use App\Models\Anexo;
use App\Models\Departamento;
use App\Models\DocumentoEncaminhamento;
use App\Models\DocumentoEncaminhamentoExterno;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoProtocolo;
use App\Models\DocumentoTarefa;
use App\Models\Gabinete;
use App\Models\Pasta;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\DocumentoEncaminhadoDepartamento;
use App\Notifications\DocumentoEncaminhadoExterno;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class DocumentoEntradaController extends Controller
{
    protected $documentoService;

    public function __construct(DocumentoEntradaService $documentoService)
    {
        $this->documentoService = $documentoService;
    }

    public function index(Request $request)
    {
        $documentos = $this->documentoService->getFilteredDocuments($request, Auth::user());
        $departamentos = Departamento::select(['id', 'nome'])->orderBy('nome')->get();

        if (Auth::check()) {
            $actor = Auth::user();
            $isAdmin = $actor && $actor->role && $actor->role->name === 'admin';
            $actorIsChiefDep = $actor && $actor->role && $actor->role->name === 'chefe-departamento';
            $actorDeps = (method_exists($actor, 'departamentos') && $actor->departamentos)
                ? $actor->departamentos->pluck('id')->all() : [];
            if (! count($actorDeps) && $actor && $actor->departamento_id) {
                $actorDeps = [$actor->departamento_id];
            }

            foreach ($documentos as $doc) {
                $enc = $doc->ultimoEncaminhamento;
                $canReceive = false;
                if ($enc && ! $enc->recebido_em) {
                    $canReceive = $isAdmin || in_array((int) $enc->destino_departamento_id, $actorDeps);
                }
                $doc->setAttribute('can_receive', $canReceive);
                $doc->setAttribute('is_chefe', $actorIsChiefDep && in_array((int) $doc->departamento_id, $actorDeps));
            }
        }

        return view('documentos_entradas.index', compact('documentos', 'departamentos'));
    }

    public function create()
    {
        $departamentos = Cache::remember('departamentos_list', 600, function () {
            return Departamento::select('id', 'nome')->orderBy('nome')->get();
        });

        $especies = Cache::remember('documento_especies_names', 600, function () {
            return DocumentoEspecie::where('ativo', true)->orderBy('ordem')->pluck('nome')->all();
        });

        $userDepartamentoId = Auth::user()->departamento_id;

        return view('documentos_entradas.create', compact('departamentos', 'especies', 'userDepartamentoId'));
    }

    public function store(StoreDocumentoEntradaRequest $request)
    {
        $validated = $request->validated();
        $this->documentoService->createDocument(
            $validated, 
            $request->file('arquivo'), 
            $request->file('anexos')
        );

        return redirect()->route('documentos-entradas.index')->with('success', 'Documento registrado com sucesso.');
    }

    public function show(DocumentoEntrada $documentos_entrada)
    {
        $doc = DocumentoEntrada::select([
            'id',
            'numero_sequencial',
            'ano_referencia',
            'data_entrada',
            'classificacao_especie',
            'classificacao_ref_numero',
            'data_documento',
            'procedencia',
            'assunto',
            'observacoes',
            'saida_gabinete_data',
            'encaminhamento_orgao',
            'encaminhamento_oficio_numero',
            'encaminhamento_data',
            'departamento_id',
            'user_id',
            'status',
            'arquivo_caminho',
            'visto_departamento_status',
            'visto_departamento_por',
            'visto_departamento_data',
            'visto_departamento_observacao',
            'visto_gabinete_status',
            'visto_gabinete_por',
            'visto_gabinete_data',
            'visto_gabinete_observacao',
        ])->with([
            'departamento:id,nome,gabinete_id',
            'departamento.gabinete:id,nome,sigla,responsavel_id',
            'usuario:id,name',
            'vistoDepartamentoPor:id,name',
            'vistoGabinetePor:id,name',
            'anexos',
            'encaminhamentos' => function ($q) {
                $q->orderBy('encaminhado_em', 'asc');
            },
            'encaminhamentos.origemDepartamento:id,nome',
            'encaminhamentos.destinoDepartamento:id,nome',
            'encaminhamentos.usuario:id,name',
            'encaminhamentos.recebidoPor:id,name',
            'encaminhamentosExternos' => function ($q) {
                $q->orderBy('enviado_em', 'asc');
            },
            'encaminhamentosExternos.origemGabinete:id,nome,sigla',
            'encaminhamentosExternos.destinoGabinete:id,nome,sigla',
            'encaminhamentosExternos.usuario:id,name',
            'tarefas' => function ($q) {
                $q->orderBy('created_at', 'desc');
            },
            'tarefas.assignedBy:id,name',
            'tarefas.assignedToUser:id,name',
            'tarefas.assignedToDepartamento:id,nome',
            'tarefas.assignedToDepartamento.usuarios:id,name,departamento_id',
            'tarefas.responsavelAtual:id,name',
        ])->findOrFail($documentos_entrada->id);

        $departamentos = Departamento::select(['id', 'nome'])->orderBy('nome')->get();
        $gabinetes = Gabinete::select(['id', 'nome', 'sigla'])->orderBy('nome')->get();

        $actor = Auth::user();
        $gabUsuarios = collect();
        $gabDepartamentos = collect();
        $depUsuarios = collect();

        $gab = optional($doc->departamento)->gabinete;
        if ($actor && $gab && (int) optional($gab)->responsavel_id === (int) $actor->id) {
            $depIds = Departamento::where('gabinete_id', $gab->id)->pluck('id');
            $gabUsuarios = User::where(function ($q) use ($depIds) {
                $q->whereIn('departamento_id', $depIds)
                    ->orWhereHas('departamentos', function ($q2) use ($depIds) {
                        $q2->whereIn('departamentos.id', $depIds);
                    });
            })->orderBy('name')->get(['id', 'name']);
            $gabDepartamentos = Departamento::whereIn('id', $depIds)->orderBy('nome')->get(['id', 'nome']);
        }

        if ($doc->departamento_id) {
            $depId = (int) $doc->departamento_id;
            $depUsuarios = User::where(function ($q) use ($depId) {
                $q->where('departamento_id', $depId)
                    ->orWhereHas('departamentos', function ($q2) use ($depId) {
                        $q2->where('departamentos.id', $depId);
                    });
            })->orderBy('name')->get(['id', 'name']);
        }

        $hasPendente = $doc->encaminhamentos()->whereNull('recebido_em')->exists();

        $deps = ($actor && method_exists($actor, 'departamentos') && $actor->departamentos) ? $actor->departamentos->pluck('id')->all() : [];
        if (! count($deps) && $actor && $actor->departamento_id) {
            $deps = [$actor->departamento_id];
        }

        $pastas = Pasta::with('departamento.gabinete')
            ->where('departamento_id', $actor->departamento_id)
            ->orWhere('created_by', $actor->id)
            ->orderBy('nome')
            ->get();

        return view('documentos_entradas.show', compact('doc', 'departamentos', 'gabinetes', 'gabUsuarios', 'gabDepartamentos', 'depUsuarios', 'hasPendente', 'deps', 'pastas'));
    }

    public function tarefasStore(Request $request, DocumentoEntrada $documento)
    {
        $validated = $request->validate([
            'tipo' => ['required', 'in:usuario,departamento'],
            'destino_id' => ['required', 'integer'],
            'titulo' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'prazo_at' => ['nullable', 'date'],
        ]);

        $actor = Auth::user();
        $gab = optional($documento->departamento)->gabinete;
        $isGabResp = $actor && $gab && (int) optional($gab)->responsavel_id === (int) $actor->id;
        $isChiefDep = $actor && $actor->role && $actor->role->name === 'chefe-departamento';
        $actorDeps = ($actor && method_exists($actor, 'departamentos') && $actor->departamentos) ? $actor->departamentos->pluck('id')->all() : [];
        if (! count($actorDeps) && $actor && $actor->departamento_id) {
            $actorDeps = [$actor->departamento_id];
        }

        if (! $isGabResp && ! ($isChiefDep && in_array((int) $documento->departamento_id, $actorDeps))) {
            return back()->with('danger', 'Você não tem permissão para designar tarefa neste documento.');
        }

        $tipo = $validated['tipo'];
        $destinoId = (int) $validated['destino_id'];

        $assignedToUserId = null;
        $assignedToDepId = null;

        if ($tipo === 'usuario') {
            $user = User::find($destinoId);
            if (! $user) {
                return back()->withErrors(['destino_id' => 'Usuário não encontrado.']);
            }
            if ($isGabResp) {
                $depIds = Departamento::where('gabinete_id', optional($documento->departamento)->gabinete_id)->pluck('id')->all();
                $belongs = in_array((int) $user->departamento_id, $depIds) || $user->departamentos()->whereIn('departamento_id', $depIds)->exists();
                if (! $belongs) {
                    return back()->withErrors(['destino_id' => 'Selecione usuário do seu gabinete.']);
                }
            } else {
                $depId = (int) $documento->departamento_id;
                $belongs = ((int) $user->departamento_id === $depId) || $user->departamentos()->where('departamento_id', $depId)->exists();
                if (! $belongs) {
                    return back()->withErrors(['destino_id' => 'Selecione usuário do seu departamento.']);
                }
            }
            $assignedToUserId = $user->id;
        } else {
            if (! $isGabResp) {
                return back()->withErrors(['tipo' => 'Apenas responsável do gabinete pode designar ao departamento.']);
            }
            $dep = Departamento::find($destinoId);
            if (! $dep) {
                return back()->withErrors(['destino_id' => 'Departamento não encontrado.']);
            }
            if ((int) optional($documento->departamento)->gabinete_id !== (int) $dep->gabinete_id) {
                return back()->withErrors(['destino_id' => 'Selecione departamento do seu gabinete.']);
            }
            $assignedToDepId = $dep->id;
        }

        $tarefa = DocumentoTarefa::create([
            'documento_entrada_id' => $documento->id,
            'titulo' => $validated['titulo'],
            'descricao' => $validated['descricao'] ?? null,
            'assigned_by_id' => Auth::id(),
            'assigned_to_user_id' => $assignedToUserId,
            'assigned_to_departamento_id' => $assignedToDepId,
            'prazo_at' => $validated['prazo_at'] ?? null,
            'status' => 'pendente',
        ]);
        $numero = sprintf('%03d/%d', $documento->numero_sequencial, $documento->ano_referencia);
        $url = route('documentos-entradas.show', $documento->id);
        if ($assignedToUserId) {
            $u = User::find($assignedToUserId);
            if ($u) {
                $title = 'Nova tarefa no documento '.$numero;
                $body = 'Destino: '.($u->name ?? 'usuário');
                Notification::sendNow($u, new \App\Notifications\SimpleBroadcastNotification($title, $body, $url));
            }
        }
        if ($assignedToDepId) {
            $dep = Departamento::find($assignedToDepId);
            if ($dep) {
                $title = 'Nova tarefa no documento '.$numero;
                $body = 'Destino: '.($dep->nome ?? 'departamento');
                $targets = collect();
                if ($dep->chefe) {
                    $targets->push($dep->chefe);
                }
                $depUsers = $dep->usuarios()->get();
                foreach ($depUsers as $du) {
                    $targets->push($du);
                }
                if ($dep->gabinete && $dep->gabinete->responsavel) {
                    $targets->push($dep->gabinete->responsavel);
                }
                $targets = $targets->unique('id')->values();
                if ($targets->count()) {
                    Notification::sendNow($targets, new \App\Notifications\SimpleBroadcastNotification($title, $body, $url));
                }
            }
        }

        return redirect()->route('documentos-entradas.show', $documento)->with('success', 'Tarefa designada com sucesso.');
    }

    public function tarefasConcluir(Request $request, DocumentoEntrada $documento, DocumentoTarefa $tarefa)
    {
        if ((int) $tarefa->documento_entrada_id !== (int) $documento->id) {
            abort(404);
        }
        if ($tarefa->status !== 'pendente') {
            return back()->with('info', 'Tarefa já atualizada.');
        }
        $actor = Auth::user();
        $gab = optional($documento->departamento)->gabinete;
        $isGabResp = $actor && $gab && (int) optional($gab)->responsavel_id === (int) $actor->id;
        $isChiefDep = $actor && $actor->role && $actor->role->name === 'chefe-departamento';
        $actorDeps = ($actor && method_exists($actor, 'departamentos') && $actor->departamentos) ? $actor->departamentos->pluck('id')->all() : [];
        if (! count($actorDeps) && $actor && $actor->departamento_id) {
            $actorDeps = [$actor->departamento_id];
        }
        $can = false;
        if ($tarefa->assigned_to_user_id) {
            if ((int) $tarefa->assigned_to_user_id === (int) $actor->id) {
                $can = true;
            } else {
                $user = User::find($tarefa->assigned_to_user_id);
                if ($isChiefDep) {
                    $belongs = in_array((int) optional($user)->departamento_id, $actorDeps) || ($user && $user->departamentos()->whereIn('departamento_id', $actorDeps)->exists());
                    if ($belongs) {
                        $can = true;
                    }
                }
                if ($isGabResp) {
                    $depIds = Departamento::where('gabinete_id', optional($documento->departamento)->gabinete_id)->pluck('id')->all();
                    $belongs = in_array((int) optional($user)->departamento_id, $depIds) || ($user && $user->departamentos()->whereIn('departamento_id', $depIds)->exists());
                    if ($belongs) {
                        $can = true;
                    }
                }
            }
        } elseif ($tarefa->assigned_to_departamento_id) {
            $dep = Departamento::find($tarefa->assigned_to_departamento_id);
            if ($isChiefDep && in_array((int) optional($dep)->id, $actorDeps)) {
                $can = true;
            }
            if ($isGabResp && (int) optional($dep)->gabinete_id === (int) optional($gab)->id) {
                $can = true;
            }
        }
        if (! $can) {
            return back()->with('danger', 'Sem permissão para concluir esta tarefa.');
        }

        if ($tarefa->assigned_to_departamento_id) {
            $respId = $request->input('responsavel_user_id');
            if ($respId) {
                $user = User::find((int) $respId);
                $dep = Departamento::find($tarefa->assigned_to_departamento_id);
                $belongs = $user && (((int) $user->departamento_id === (int) $dep->id) || $user->departamentos()->where('departamento_id', $dep->id)->exists());
                if (! $belongs) {
                    return back()->withErrors(['responsavel_user_id' => 'Selecione um responsável pertencente ao departamento destino.']);
                }
                $tarefa->responsavel_user_id = (int) $respId;
            } else {
                $dep = Departamento::find($tarefa->assigned_to_departamento_id);
                $belongs = $actor && (((int) $actor->departamento_id === (int) optional($dep)->id) || ($actor->departamentos()->where('departamento_id', optional($dep)->id)->exists()));
                if ($belongs) {
                    $tarefa->responsavel_user_id = $actor->id;
                } else {
                    return back()->withErrors(['responsavel_user_id' => 'Informe o responsável atual do departamento para concluir.']);
                }
            }
        }
        $tarefa->status = 'concluida';
        $tarefa->save();
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
        if ($tarefa->assigned_to_user_id) {
            $user = User::find($tarefa->assigned_to_user_id);
            if ($user && (int) $user->id !== (int) $actor->id) {
                $notify->push($user);
            }
        }
        if ($tarefa->assigned_to_departamento_id) {
            $dep = Departamento::find($tarefa->assigned_to_departamento_id);
            if ($dep) {
                if ($dep->chefe) {
                    $notify->push($dep->chefe);
                }
                $depUsers = $dep->usuarios()->get();
                foreach ($depUsers as $du) {
                    $notify->push($du);
                }
            }
        }
        $notify = $notify->unique('id')->values();
        if ($notify->count()) {
            Notification::sendNow($notify, new \App\Notifications\SimpleBroadcastNotification($title, $body, $url));
        }

        return back()->with('success', 'Tarefa marcada como concluída.');
    }

    public function tarefasCancelar(DocumentoEntrada $documento, DocumentoTarefa $tarefa)
    {
        if ((int) $tarefa->documento_entrada_id !== (int) $documento->id) {
            abort(404);
        }
        if ($tarefa->status !== 'pendente') {
            return back()->with('info', 'Tarefa já atualizada.');
        }
        $actor = Auth::user();
        $gab = optional($documento->departamento)->gabinete;
        $isGabResp = $actor && $gab && (int) optional($gab)->responsavel_id === (int) $actor->id;
        $isChiefDep = $actor && $actor->role && $actor->role->name === 'chefe-departamento';
        $actorDeps = ($actor && method_exists($actor, 'departamentos') && $actor->departamentos) ? $actor->departamentos->pluck('id')->all() : [];
        if (! count($actorDeps) && $actor && $actor->departamento_id) {
            $actorDeps = [$actor->departamento_id];
        }
        $can = false;
        if ((int) $tarefa->assigned_by_id === (int) $actor->id) {
            $can = true;
        } else {
            if ($tarefa->assigned_to_user_id) {
                if ((int) $tarefa->assigned_to_user_id === (int) $actor->id) {
                    $can = true;
                } else {
                    $user = User::find($tarefa->assigned_to_user_id);
                    if ($isChiefDep) {
                        $belongs = in_array((int) optional($user)->departamento_id, $actorDeps) || ($user && $user->departamentos()->whereIn('departamento_id', $actorDeps)->exists());
                        if ($belongs) {
                            $can = true;
                        }
                    }
                    if ($isGabResp) {
                        $depIds = Departamento::where('gabinete_id', optional($documento->departamento)->gabinete_id)->pluck('id')->all();
                        $belongs = in_array((int) optional($user)->departamento_id, $depIds) || ($user && $user->departamentos()->whereIn('departamento_id', $depIds)->exists());
                        if ($belongs) {
                            $can = true;
                        }
                    }
                }
            } elseif ($tarefa->assigned_to_departamento_id) {
                $dep = Departamento::find($tarefa->assigned_to_departamento_id);
                if ($isChiefDep && in_array((int) optional($dep)->id, $actorDeps)) {
                    $can = true;
                }
                if ($isGabResp && (int) optional($dep)->gabinete_id === (int) optional($gab)->id) {
                    $can = true;
                }
            }
        }
        if (! $can) {
            return back()->with('danger', 'Sem permissão para cancelar esta tarefa.');
        }
        $tarefa->status = 'cancelada';
        $tarefa->save();
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
        if ($tarefa->assigned_to_user_id) {
            $user = User::find($tarefa->assigned_to_user_id);
            if ($user && (int) $user->id !== (int) $actor->id) {
                $notify->push($user);
            }
        }
        if ($tarefa->assigned_to_departamento_id) {
            $dep = Departamento::find($tarefa->assigned_to_departamento_id);
            if ($dep) {
                if ($dep->chefe) {
                    $notify->push($dep->chefe);
                }
                $depUsers = $dep->usuarios()->get();
                foreach ($depUsers as $du) {
                    $notify->push($du);
                }
                if ($dep->gabinete && $dep->gabinete->responsavel) {
                    $notify->push($dep->gabinete->responsavel);
                }
            }
        }
        $notify = $notify->unique('id')->values();
        if ($notify->count()) {
            Notification::sendNow($notify, new \App\Notifications\SimpleBroadcastNotification($title, $body, $url));
        }

        return back()->with('success', 'Tarefa cancelada com sucesso.');
    }

    public function protocolo(DocumentoEntrada $documento)
    {
        $documento->load(['departamento', 'usuario', 'protocolo']);

        if (! $documento->protocolo) {
            $codigo = strtoupper(Str::random(10));
            $consultaUrl = route('documentos-entradas.protocolo', $documento);

            $protocolo = DocumentoProtocolo::create([
                'documento_entrada_id' => $documento->id,
                'codigo' => $codigo,
                'url_consulta' => $consultaUrl,
                'gerado_em' => Carbon::now(),
            ]);

            $documento->setRelation('protocolo', $protocolo);
        }

        $consultaUrl = $documento->protocolo->url_consulta ?? route('documentos-entradas.protocolo', $documento);
        $protocolo = $documento->protocolo;

        return view('documentos_entradas.protocolo', compact('documento', 'protocolo', 'consultaUrl'));
    }

    public function protocoloPdf(DocumentoEntrada $documento)
    {
        $documento->load(['departamento', 'usuario', 'protocolo']);

        if (! $documento->protocolo) {
            $codigo = strtoupper(Str::random(10));
            $consultaUrl = route('documentos-entradas.protocolo', $documento);

            $protocolo = DocumentoProtocolo::create([
                'documento_entrada_id' => $documento->id,
                'codigo' => $codigo,
                'url_consulta' => $consultaUrl,
                'gerado_em' => Carbon::now(),
            ]);

            $documento->setRelation('protocolo', $protocolo);
        }

        $consultaUrl = $documento->protocolo->url_consulta ?? route('documentos-entradas.protocolo', $documento);
        $protocolo = $documento->protocolo;

        $options = new Options;
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);

        $html = view('documentos_entradas.protocolo_pdf', compact('documento', 'protocolo', 'consultaUrl'))->render();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A5', 'landscape');
        $dompdf->render();

        $filename = sprintf('protocolo_%03d_%d.pdf', $documento->numero_sequencial, $documento->ano_referencia);

        return response($dompdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$filename.'"');
    }

    public function exportPDF(Request $request)
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
        ])->with([
            'departamento:id,nome',
            'ultimoEncaminhamento',
            'ultimoEncaminhamento.origemDepartamento:id,nome',
            'ultimoEncaminhamento.destinoDepartamento:id,nome',
        ]);

        if (Auth::check()) {
            $actor = Auth::user();
            $isAdmin = $actor->role && $actor->role->name === 'admin';
            if (! $isAdmin) {
                $actorDeps = (method_exists($actor, 'departamentos') && $actor->departamentos)
                    ? $actor->departamentos->pluck('id')->all() : [];
                if (! count($actorDeps) && $actor->departamento_id) {
                    $actorDeps = [$actor->departamento_id];
                }
                if (count($actorDeps)) {
                    $query->where(function ($q) use ($actorDeps) {
                        $q->whereIn('departamento_id', $actorDeps)
                            ->orWhereExists(function ($sub) use ($actorDeps) {
                                $sub->selectRaw(1)
                                    ->from('documento_encaminhamentos as de')
                                    ->whereColumn('de.documento_entrada_id', 'documentos_entradas.id')
                                    ->whereNull('de.recebido_em')
                                    ->whereIn('de.destino_departamento_id', $actorDeps);
                            });
                    });
                }
            }
        }

        if ($request->filled('search')) {
            $s = trim($request->input('search'));
            $query->where(function ($q) use ($s) {
                $q->where('assunto', 'like', "%$s%")
                    ->orWhere('procedencia', 'like', "%$s%")
                    ->orWhere('classificacao_especie', 'like', "%$s%")
                    ->orWhere('classificacao_ref_numero', 'like', "%$s%");
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

        $documentos = $query->get();

        $filtersSummary = [];
        if ($request->filled('departamento_id')) {
            $depId = (int) $request->input('departamento_id');
            $depName = optional(Departamento::find($depId))->nome;
            if ($depName) {
                $filtersSummary['Departamento'] = $depName;
            }
        }
        if ($request->filled('data_de') || $request->filled('data_ate')) {
            $de = $request->input('data_de');
            $ate = $request->input('data_ate');
            $filtersSummary['Período'] = trim(($de ? ('de '.$de) : '').($ate ? (' até '.$ate) : ''));
        }
        if ($request->filled('ano')) {
            $filtersSummary['Ano'] = (int) $request->input('ano');
        }
        if ($request->filled('status')) {
            $filtersSummary['Status'] = $request->input('status');
        }
        if ($request->filled('search')) {
            $filtersSummary['Busca'] = $request->input('search');
        }
        $labels = ['data_entrada' => 'Data de entrada', 'numero_sequencial' => 'Número', 'ano_referencia' => 'Ano'];
        $filtersSummary['Ordenação'] = ($labels[$sort] ?? $sort).' '.$dir;

        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);
        $html = view('documentos_entradas.pdf', compact('documentos', 'filtersSummary'))->render();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->stream('relatorio-documentos-entradas-'.date('Y-m-d').'.pdf');
    }

    public function exportExcel(Request $request)
    {
        $query = DocumentoEntrada::select([
            'id',
            'numero_sequencial',
            'ano_referencia',
            'data_entrada',
            'classificacao_especie',
            'classificacao_ref_numero',
            'data_documento',
            'procedencia',
            'assunto',
            'departamento_id',
            'status',
            'visto_departamento_status',
            'visto_gabinete_status',
        ])->with(['departamento:id,nome']);

        if (Auth::check()) {
            $actor = Auth::user();
            $isAdmin = $actor->role && $actor->role->name === 'admin';
            if (! $isAdmin) {
                $actorDeps = (method_exists($actor, 'departamentos') && $actor->departamentos)
                    ? $actor->departamentos->pluck('id')->all() : [];
                if (! count($actorDeps) && $actor->departamento_id) {
                    $actorDeps = [$actor->departamento_id];
                }
                if (count($actorDeps)) {
                    $query->where(function ($q) use ($actorDeps) {
                        $q->whereIn('departamento_id', $actorDeps)
                            ->orWhereExists(function ($sub) use ($actorDeps) {
                                $sub->selectRaw(1)
                                    ->from('documento_encaminhamentos as de')
                                    ->whereColumn('de.documento_entrada_id', 'documentos_entradas.id')
                                    ->whereNull('de.recebido_em')
                                    ->whereIn('de.destino_departamento_id', $actorDeps);
                            });
                    });
                }
            }
        }

        if ($request->filled('search')) {
            $s = trim($request->input('search'));
            $query->where(function ($q) use ($s) {
                $q->where('assunto', 'like', "%$s%")
                    ->orWhere('procedencia', 'like', "%$s%")
                    ->orWhere('classificacao_especie', 'like', "%$s%")
                    ->orWhere('classificacao_ref_numero', 'like', "%$s%");
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

        $documentos = $query->get();

        return Excel::download(new DocumentoEntradasExport($documentos), 'relatorio-documentos-entradas-'.date('Y-m-d').'.xlsx');
    }

    public function marcarProtocoloImpresso(DocumentoEntrada $documento, Request $request)
    {
        $documento->load('protocolo');
        if (! $documento->protocolo) {
            return response()->json(['message' => 'Protocolo não encontrado'], 404);
        }

        $documento->protocolo->impresso_em = Carbon::now();
        $documento->protocolo->save();

        return response()->json([
            'message' => 'Protocolo marcado como impresso',
            'impresso_em' => $documento->protocolo->impresso_em,
        ]);
    }

    public function edit(DocumentoEntrada $documentos_entrada)
    {
        $departamentos = Cache::remember('departamentos_list', 600, function () {
            return Departamento::select('id', 'nome')->orderBy('nome')->get();
        });

        $especies = Cache::remember('documento_especies_names', 600, function () {
            return DocumentoEspecie::where('ativo', true)->orderBy('ordem')->pluck('nome')->all();
        });

        return view('documentos_entradas.edit', ['doc' => $documentos_entrada, 'departamentos' => $departamentos, 'especies' => $especies]);
    }

    public function update(Request $request, DocumentoEntrada $documentos_entrada)
    {
        $validated = $request->validate([
            // 'data_entrada' removed: não editável manualmente
            'classificacao_especie' => ['nullable', 'string', 'max:100'],
            'classificacao_ref_numero' => ['nullable', 'string', 'max:100'],
            'data_documento' => ['nullable', 'date'],
            'procedencia' => ['nullable', 'string', 'max:255'],
            'assunto' => ['required', 'string', 'max:500'],
            'observacoes' => ['nullable', 'string'],
            'saida_gabinete_data' => ['nullable', 'date'],
            'encaminhamento_orgao' => ['nullable', 'string', 'max:255'],
            'encaminhamento_oficio_numero' => ['nullable', 'string', 'max:100'],
            // 'encaminhamento_data' removed: definida ao encaminhar
            'departamento_id' => ['required', 'exists:departamentos,id'],
            // 'status' removed: não é manual
            'arquivo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
            'anexos.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        // Não altera ano_referencia nem data_entrada manualmente
        $documentos_entrada->fill([
            'classificacao_especie' => $validated['classificacao_especie'] ?? null,
            'classificacao_ref_numero' => $validated['classificacao_ref_numero'] ?? null,
            'data_documento' => $validated['data_documento'] ?? null,
            'procedencia' => $validated['procedencia'] ?? null,
            'assunto' => $validated['assunto'],
            'observacoes' => $validated['observacoes'] ?? null,
            'saida_gabinete_data' => $validated['saida_gabinete_data'] ?? null,
            'encaminhamento_orgao' => $validated['encaminhamento_orgao'] ?? null,
            'encaminhamento_oficio_numero' => $validated['encaminhamento_oficio_numero'] ?? null,
            // 'encaminhamento_data' não é preenchido aqui
            'departamento_id' => $validated['departamento_id'],
        ]);

        if ($request->hasFile('arquivo')) {
            if ($documentos_entrada->arquivo_caminho) {
                $docsDiskOld = config('filesystems.docs_disk', 'public');
                Storage::disk($docsDiskOld)->delete($documentos_entrada->arquivo_caminho);
            }
            $dep = Departamento::find((int) $documentos_entrada->departamento_id);
            $gab = optional($dep)->gabinete;
            $depSlug = Str::slug(optional($dep)->nome ?? 'sem-departamento');
            $gabSlug = Str::slug(optional($gab)->sigla ?? (optional($gab)->nome ?? 'sem-gabinete'));
            $base = 'documentos_entradas/'.$documentos_entrada->ano_referencia.'/'.$gabSlug.'/'.$depSlug.'/'.sprintf('%03d', $documentos_entrada->numero_sequencial);
            $docsDisk = config('filesystems.docs_disk', 'public');
            $documentos_entrada->arquivo_caminho = $request->file('arquivo')->store($base, $docsDisk);
        }

        // Novos anexos múltiplos
        if ($request->hasFile('anexos')) {
            $ordem = ($documentos_entrada->anexos()->max('ordem') ?? 0);
            foreach ($request->file('anexos') as $file) {
                $ordem++;
                $dep = Departamento::find((int) $documentos_entrada->departamento_id);
                $gab = optional($dep)->gabinete;
                $depSlug = Str::slug(optional($dep)->nome ?? 'sem-departamento');
                $gabSlug = Str::slug(optional($gab)->sigla ?? (optional($gab)->nome ?? 'sem-gabinete'));
                $base = 'documentos_entradas/'.$documentos_entrada->ano_referencia.'/'.$gabSlug.'/'.$depSlug.'/'.sprintf('%03d', $documentos_entrada->numero_sequencial).'/anexos';
                $docsDisk = config('filesystems.docs_disk', 'public');
                $storedPath = $file->store($base, $docsDisk);
                $documentos_entrada->anexos()->create([
                    'nome_original' => $file->getClientOriginalName(),
                    'caminho_arquivo' => $storedPath,
                    'mime_type' => $file->getMimeType(),
                    'tamanho_bytes' => $file->getSize(),
                    'descricao' => null,
                    'ordem' => $ordem,
                    'user_id' => Auth::id(),
                ]);
            }
        }

        $documentos_entrada->save();

        return redirect()->route('documentos-entradas.show', $documentos_entrada)->with('success', 'Documento atualizado com sucesso.');
    }

    public function destroyAnexo(DocumentoEntrada $documento, Anexo $anexo)
    {
        if ($anexo->anexavel_type !== DocumentoEntrada::class || (int) $anexo->anexavel_id !== (int) $documento->id) {
            abort(403, 'Anexo não pertence ao documento informado.');
        }
        if ($anexo->caminho_arquivo) {
            Storage::disk('public')->delete($anexo->caminho_arquivo);
        }
        $anexo->delete();

        return back()->with('success', 'Anexo removido com sucesso.');
    }

    public function encaminhar(Request $request, DocumentoEntrada $documento)
    {
        $validated = $request->validate([
            'destino_departamento_id' => ['required', 'exists:departamentos,id'],
            'observacao' => ['nullable', 'string'],
        ]);
        $this->authorize('encaminhar', $documento);
        $actor = Auth::user();

        // Impedir encaminhamento se houver pendente
        if ($documento->encaminhamentos()->whereNull('recebido_em')->exists()) {
            return back()->withErrors(['destino_departamento_id' => 'Há encaminhamento pendente; aguarde o recebimento antes de criar um novo.']);
        }

        $origemId = $documento->departamento_id;
        $destinoId = (int) $validated['destino_departamento_id'];
        if ($origemId === $destinoId) {
            return back()->withErrors(['destino_departamento_id' => 'Selecione um departamento diferente do atual.']);
        }

        $enc = DB::transaction(function () use ($documento, $request) {
            $e = DocumentoEncaminhamento::create([
                'documento_entrada_id' => $documento->id,
                'origem_departamento_id' => $documento->departamento_id,
                'destino_departamento_id' => (int) $request->input('destino_departamento_id'),
                'usuario_id' => Auth::id(),
                'encaminhado_em' => now(),
                'status' => 'encaminhado',
                'observacao' => $request->input('observacao'),
            ]);
            $documento->status = 'encaminhado';
            $documento->encaminhamento_data = now();
            $documento->save();

            return $e;
        });

        // Notificar usuários do departamento de destino
        $destinoId = (int) $validated['destino_departamento_id'];
        $usuariosDestino = User::where('id', '!=', Auth::id())
            ->where(function ($q) use ($destinoId) {
                $q->where('departamento_id', $destinoId)
                    ->orWhereHas('departamentos', function ($q2) use ($destinoId) {
                        $q2->where('departamentos.id', $destinoId);
                    });
            })
            ->get();

        $depDestino = Departamento::find($destinoId);
        if ($depDestino && $depDestino->chefe) {
            $usuariosDestino->push($depDestino->chefe);
            $usuariosDestino = $usuariosDestino->unique('id')->values();
        }

        if ($usuariosDestino->count()) {
            Notification::send($usuariosDestino, new DocumentoEncaminhadoDepartamento($documento, $enc, $actor));
        }

        return redirect()->route('documentos-entradas.show', $documento)->with('success', 'Documento encaminhado com sucesso.');
    }

    public function receberEncaminhamento(DocumentoEntrada $documento, DocumentoEncaminhamento $encaminhamento)
    {
        if ((int) $encaminhamento->documento_entrada_id !== (int) $documento->id) {
            abort(404);
        }
        if ($encaminhamento->recebido_em) {
            return back()->with('info', 'Encaminhamento já marcado como recebido.');
        }

        $this->authorize('receber', [$documento, (int) $encaminhamento->destino_departamento_id]);

        DB::transaction(function () use ($documento, $encaminhamento) {
            $encaminhamento->recebido_em = now();
            $encaminhamento->recebido_por_id = Auth::id();
            $encaminhamento->status = 'recebido';
            $encaminhamento->save();

            $documento->departamento_id = $encaminhamento->destino_departamento_id;
            $documento->status = 'recebido';
            $documento->save();
        });

        $depAtual = Departamento::find($documento->departamento_id);
        if ($depAtual && $depAtual->chefe && (int) $depAtual->chefe->id !== (int) Auth::id()) {
            $numero = sprintf('%03d/%d', $documento->numero_sequencial, $documento->ano_referencia);
            $titulo = 'Documento '.$numero.' recebido no departamento '.($depAtual->nome ?? '');
            $depAtual->chefe->notify(new \App\Notifications\SimpleBroadcastNotification($titulo, 'Aguardando ação do chefe', route('documentos-entradas.show', $documento->id)));
        }

        return back()->with('success', 'Documento marcado como recebido.');
    }

    public function saidaGabinete(Request $request, DocumentoEntrada $documento)
    {
        $validated = $request->validate([
            'destino_gabinete_id' => ['required', 'exists:gabinetes,id'],
            'saida_gabinete_data' => ['required', 'date'],
            'encaminhamento_oficio_numero' => ['nullable', 'string', 'max:100'],
        ]);
        $this->authorize('saidaGabinete', $documento);
        $actor = Auth::user();

        $docGabineteId = optional($documento->departamento)->gabinete_id;

        if ($documento->saida_gabinete_data) {
            return back()->withErrors(['destino_gabinete_id' => 'Documento já possui saída de gabinete registrada.']);
        }

        if ($documento->encaminhamentos()->whereNull('recebido_em')->exists()) {
            return back()->withErrors(['destino_gabinete_id' => 'Há encaminhamento interno pendente; receba antes de dar saída.']);
        }

        $destinoId = (int) $validated['destino_gabinete_id'];
        if ($docGabineteId && $destinoId === (int) $docGabineteId) {
            return back()->withErrors(['destino_gabinete_id' => 'Selecione um gabinete diferente do atual.']);
        }

        $gabineteDestino = Gabinete::find($destinoId);

        $encExterno = DB::transaction(function () use ($documento, $validated, $request, $destinoId, $gabineteDestino) {
            $documento->saida_gabinete_data = Carbon::parse($validated['saida_gabinete_data']);
            $documento->encaminhamento_orgao = $gabineteDestino ? ($gabineteDestino->sigla ? ($gabineteDestino->nome.' ('.$gabineteDestino->sigla.')') : $gabineteDestino->nome) : null;
            $documento->encaminhamento_oficio_numero = $validated['encaminhamento_oficio_numero'] ?? null;
            $documento->status = 'encaminhado_externo';
            $documento->save();

            return DocumentoEncaminhamentoExterno::create([
                'documento_entrada_id' => $documento->id,
                'origem_gabinete_id' => optional($documento->departamento)->gabinete_id,
                'destino_gabinete_id' => $destinoId,
                'usuario_id' => Auth::id(),
                'oficio_numero' => $validated['encaminhamento_oficio_numero'] ?? null,
                'enviado_em' => Carbon::parse($validated['saida_gabinete_data'])->startOfDay(),
                'observacao' => $request->input('observacao'),
                'status' => 'enviado',
            ]);
        });

        // Notificar gabinete de destino: todos usuários de departamentos desse gabinete e o responsável
        $depIds = Departamento::where('gabinete_id', $destinoId)->pluck('id');
        $usuariosGabinete = User::where('id', '!=', Auth::id())
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

        // Confirmação ao autor
        $confirmTitle = 'Documento '.sprintf('%03d/%d', $documento->numero_sequencial, $documento->ano_referencia)
            .' enviado para '.($gabineteDestino ? ($gabineteDestino->sigla ? ($gabineteDestino->nome.' ('.$gabineteDestino->sigla.')') : $gabineteDestino->nome) : 'gabinete destino');
        Auth::user()->notify(new \App\Notifications\SimpleBroadcastNotification($confirmTitle, 'Saída registrada com sucesso', route('documentos-entradas.show', $documento->id)));

        return redirect()->route('documentos-entradas.show', $documento)
            ->with('success', 'Saída do gabinete registrada com sucesso.');
    }

    public function vistoAprovar(DocumentoEntrada $documento)
    {
        $this->authorize('vistoAprovar', $documento);
        $documento->visto_departamento_status = 'aprovado';
        $documento->visto_departamento_por = Auth::id();
        $documento->visto_departamento_data = now();
        $documento->save();
        $titulo = 'Visto do chefe aprovado para documento '.sprintf('%03d/%d', $documento->numero_sequencial, $documento->ano_referencia);
        optional($documento->usuario)->notify(new \App\Notifications\SimpleBroadcastNotification($titulo, 'Aprovado pelo chefe', route('documentos-entradas.show', $documento->id)));

        return back()->with('success', 'Visto do chefe registrado como aprovado.');
    }

    public function vistoRejeitar(DocumentoEntrada $documento, Request $request)
    {
        $this->authorize('vistoRejeitar', $documento);
        $request->validate([
            'visto_departamento_observacao' => ['required', 'string'],
        ]);
        $documento->visto_departamento_status = 'rejeitado';
        $documento->visto_departamento_por = Auth::id();
        $documento->visto_departamento_data = now();
        $documento->visto_departamento_observacao = $request->input('visto_departamento_observacao');
        $documento->save();
        $titulo = 'Visto do chefe rejeitado para documento '.sprintf('%03d/%d', $documento->numero_sequencial, $documento->ano_referencia);
        optional($documento->usuario)->notify(new \App\Notifications\SimpleBroadcastNotification($titulo, 'Rejeitado pelo chefe', route('documentos-entradas.show', $documento->id)));

        return back()->with('success', 'Visto do chefe registrado como rejeitado.');
    }

    public function vistoGabineteAprovar(DocumentoEntrada $documento)
    {
        $this->authorize('vistoGabineteAprovar', $documento);
        $documento->visto_gabinete_status = 'aprovado';
        $documento->visto_gabinete_por = Auth::id();
        $documento->visto_gabinete_data = now();
        $documento->save();
        $titulo = 'Visto do gabinete aprovado para documento '.sprintf('%03d/%d', $documento->numero_sequencial, $documento->ano_referencia);
        optional($documento->usuario)->notify(new \App\Notifications\SimpleBroadcastNotification($titulo, 'Aprovado no gabinete', route('documentos-entradas.show', $documento->id)));

        return back()->with('success', 'Visto do gabinete registrado como aprovado.');
    }

    public function vistoGabineteRejeitar(DocumentoEntrada $documento, Request $request)
    {
        $this->authorize('vistoGabineteRejeitar', $documento);
        $request->validate([
            'visto_gabinete_observacao' => ['required', 'string'],
        ]);
        $documento->visto_gabinete_status = 'rejeitado';
        $documento->visto_gabinete_por = Auth::id();
        $documento->visto_gabinete_data = now();
        $documento->visto_gabinete_observacao = $request->input('visto_gabinete_observacao');
        $documento->save();
        $titulo = 'Visto do gabinete rejeitado para documento '.sprintf('%03d/%d', $documento->numero_sequencial, $documento->ano_referencia);
        optional($documento->usuario)->notify(new \App\Notifications\SimpleBroadcastNotification($titulo, 'Rejeitado no gabinete', route('documentos-entradas.show', $documento->id)));

        return back()->with('success', 'Visto do gabinete registrado como rejeitado.');
    }

    public function downloadArquivo(DocumentoEntrada $documento)
    {
        $actor = Auth::user();
        $isAdmin = $actor && $actor->role && $actor->role->name === 'admin';
        $deps = ($actor && method_exists($actor, 'departamentos') && $actor->departamentos) ? $actor->departamentos->pluck('id')->all() : [];
        if (! count($deps) && $actor && $actor->departamento_id) {
            $deps = [$actor->departamento_id];
        }
        $gab = optional($documento->departamento)->gabinete;
        $isGabResp = $gab && (int) optional($gab)->responsavel_id === (int) optional($actor)->id;
        $can = $isAdmin || in_array((int) $documento->departamento_id, $deps) || $isGabResp;
        
        // Allow if user's department has handled the document in the past
        if (! $can && count($deps)) {
            $hasHistory = DocumentoEncaminhamento::where('documento_entrada_id', $documento->id)
                ->where(function($q) use ($deps) {
                    $q->whereIn('origem_departamento_id', $deps)
                      ->orWhereIn('destino_departamento_id', $deps);
                })->exists();
            if ($hasHistory) {
                $can = true;
            }
        }

        if (! $can) {
            abort(403);
        }
        if (! $documento->arquivo_caminho) {
            abort(404);
        }
        $docsDisk = config('filesystems.docs_disk', 'public');
        $path = $documento->arquivo_caminho;
        if ($path && Storage::disk($docsDisk)->exists($path)) {
            return Storage::disk($docsDisk)->download($path);
        }
        if ($path && Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->download($path);
        }
        abort(404);
    }

    public function downloadAnexo(DocumentoEntrada $documento, Anexo $anexo)
    {
        if ($anexo->anexavel_type !== DocumentoEntrada::class || (int) $anexo->anexavel_id !== (int) $documento->id) {
            abort(404);
        }
        $actor = Auth::user();
        $isAdmin = $actor && $actor->role && $actor->role->name === 'admin';
        $deps = ($actor && method_exists($actor, 'departamentos') && $actor->departamentos) ? $actor->departamentos->pluck('id')->all() : [];
        if (! count($deps) && $actor && $actor->departamento_id) {
            $deps = [$actor->departamento_id];
        }
        $gab = optional($documento->departamento)->gabinete;
        $isGabResp = $gab && (int) optional($gab)->responsavel_id === (int) optional($actor)->id;
        $can = $isAdmin || in_array((int) $documento->departamento_id, $deps) || $isGabResp;

        // Allow if user's department has handled the document in the past
        if (! $can && count($deps)) {
            $hasHistory = DocumentoEncaminhamento::where('documento_entrada_id', $documento->id)
                ->where(function($q) use ($deps) {
                    $q->whereIn('origem_departamento_id', $deps)
                      ->orWhereIn('destino_departamento_id', $deps);
                })->exists();
            if ($hasHistory) {
                $can = true;
            }
        }

        if (! $can) {
            abort(403);
        }
        if (! $anexo->caminho_arquivo) {
            abort(404);
        }
        $docsDisk = config('filesystems.docs_disk', 'public');
        $name = $anexo->nome_original ?: basename($anexo->caminho_arquivo);
        $path = $anexo->caminho_arquivo;
        if ($path && Storage::disk($docsDisk)->exists($path)) {
            return Storage::disk($docsDisk)->download($path, $name);
        }
        if ($path && Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->download($path, $name);
        }
        abort(404);
    }

    public function destroy(DocumentoEntrada $documentos_entrada)
    {
        $actor = Auth::user();
        $isAdmin = $actor && $actor->role && $actor->role->name === 'admin';
        
        // Permite excluir se for admin ou se o usuário for o criador do documento
        if (!$isAdmin && $documentos_entrada->user_id !== $actor->id) {
             abort(403, 'Você não tem permissão para excluir este documento.');
        }

        $documentos_entrada->delete();

        return redirect()->route('documentos-entradas.index')
            ->with('success', 'Documento excluído com sucesso.');
    }
}
