@extends('layouts.app')

@section('title', 'Detalhes do Documento')

@section('content')
    <div class="container-fluid px-4 py-4">
        <!-- Header Section -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h2 class="h3 fw-bold mb-0 text-dark">Documento Nº {{ $doc->numero_sequencial }}/{{ $doc->ano_referencia }}</h2>
                    <span class="badge bg-{{ $doc->status === 'arquivado' ? 'secondary' : 'primary' }} rounded-pill">{{ ucfirst($doc->status) }}</span>
                </div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('documentos-entradas.index') }}" class="text-decoration-none">Documentos</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Detalhes</li>
                    </ol>
                </nav>
            </div>
            
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('documentos-entradas.index') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Voltar
                </a>
                
                <div class="btn-group">
                    <a href="{{ route('documentos-entradas.edit', $doc) }}" class="btn btn-outline-primary">
                        <i class="fas fa-edit me-1"></i> Editar
                    </a>
                    <a href="{{ route('documentos-entradas.protocolo', $doc) }}" target="_blank" class="btn btn-outline-success" title="Imprimir Protocolo">
                        <i class="fas fa-print me-1"></i> Protocolo
                    </a>
                </div>

                <div class="dropdown">
                    <button class="btn btn-outline-dark dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-file-export me-1"></i> Exportar
                    </button>
                    <ul class="dropdown-menu shadow-sm border-0">
                        <li><a class="dropdown-item" href="{{ route('documentos-entradas.export.pdf', ['departamento_id' => $doc->departamento_id, 'ano' => $doc->ano_referencia]) }}"><i class="fas fa-file-pdf me-2 text-danger"></i>PDF</a></li>
                        <li><a class="dropdown-item" href="{{ route('documentos-entradas.export.excel', ['departamento_id' => $doc->departamento_id, 'ano' => $doc->ano_referencia]) }}"><i class="fas fa-file-excel me-2 text-success"></i>Excel</a></li>
                    </ul>
                </div>

                @if (!$doc->saida_gabinete_data && !$hasPendente)
                <div class="dropdown">
                    <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-paper-plane me-1"></i> Ações
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                        <li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modalEncaminharDocumento">Encaminhar Documento</button></li>
                        <li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modalSaidaGabinete">Registrar Saída de Gabinete</button></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><button class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#modalArquivarDocumento">Arquivar Documento</button></li>
                    </ul>
                </div>
                @endif
            </div>
        </div>

        <!-- Feedback Messages -->
        @php($currentDept = optional($doc->departamento)->nome)
        @php($currentStatus = ucfirst($doc->status))
        
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                <i class="fas fa-check-circle fs-4 me-3"></i>
                <div>
                    <div class="fw-semibold">{{ session('success') }}</div>
                    <div class="small opacity-75">Departamento atual: {{ $currentDept ?? '—' }} • Status: {{ $currentStatus }}</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        
        @if (session('info'))
            <div class="alert alert-info alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                <i class="fas fa-info-circle fs-4 me-3"></i>
                <div>
                    <div class="fw-semibold">{{ session('info') }}</div>
                    <div class="small opacity-75">Departamento atual: {{ $currentDept ?? '—' }} • Status: {{ $currentStatus }}</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('warning'))
            <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                <i class="fas fa-exclamation-triangle fs-4 me-3"></i>
                <div>
                    <div class="fw-semibold">{{ session('warning') }}</div>
                    <div class="small opacity-75">Verifique os dados antes de continuar.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('error') || session('danger'))
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                <i class="fas fa-times-circle fs-4 me-3"></i>
                <div>
                    <div class="fw-semibold">{{ session('error') ?? session('danger') }}</div>
                    <div class="small opacity-75">Se persistir, contate o administrador.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <div class="d-flex align-items-center mb-2">
                    <i class="fas fa-times-circle fs-4 me-3"></i>
                    <div class="fw-semibold">Ocorreram problemas com sua solicitação:</div>
                </div>
                <ul class="mb-0 small">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="row g-4">
            <!-- Left Column: Main Content -->
            <div class="col-lg-8">
                
                <!-- Metadata Card -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-transparent py-3 border-bottom">
                        <h5 class="card-title mb-0 fw-bold text-primary"><i class="fas fa-info-circle me-2"></i>Informações do Documento</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="text-muted small text-uppercase fw-semibold">Data de Entrada</label>
                                <div class="fw-medium text-dark"><i class="far fa-calendar-alt me-1 text-secondary"></i> {{ optional($doc->data_entrada)->format('d/m/Y') }}</div>
                            </div>
                            <div class="col-md-3">
                                <label class="text-muted small text-uppercase fw-semibold">Espécie</label>
                                <div class="fw-medium text-dark">{{ $doc->classificacao_especie ?? '—' }}</div>
                            </div>
                            <div class="col-md-3">
                                <label class="text-muted small text-uppercase fw-semibold">Ref. Nº</label>
                                <div class="fw-medium text-dark">{{ $doc->classificacao_ref_numero ?? '—' }}</div>
                            </div>
                            <div class="col-md-3">
                                <label class="text-muted small text-uppercase fw-semibold">Data do Documento</label>
                                <div class="fw-medium text-dark">{{ optional($doc->data_documento)->format('d/m/Y') ?? '—' }}</div>
                            </div>

                            <div class="col-md-12">
                                <label class="text-muted small text-uppercase fw-semibold">Procedência</label>
                                <div class="fw-medium text-dark">{{ $doc->procedencia ?? '—' }}</div>
                            </div>
                            
                            <div class="col-md-12">
                                <label class="text-muted small text-uppercase fw-semibold">Assunto</label>
                                <div class="p-3 bg-light rounded border-start border-4 border-primary">
                                    {{ $doc->assunto }}
                                </div>
                            </div>

                            @if ($doc->saida_gabinete_data || $doc->encaminhamento_orgao || $doc->encaminhamento_data)
                                <div class="col-md-6">
                                    <label class="text-muted small text-uppercase fw-semibold">Saída do Gabinete</label>
                                    <div class="fw-medium text-dark">{{ optional($doc->saida_gabinete_data)->format('d/m/Y') ?? '—' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="text-muted small text-uppercase fw-semibold">Encaminhamento</label>
                                    <div class="fw-medium text-dark">
                                        @if ($doc->encaminhamento_orgao)
                                            {{ $doc->encaminhamento_orgao }}
                                            @if ($doc->encaminhamento_oficio_numero)
                                                — Of. {{ $doc->encaminhamento_oficio_numero }}
                                            @endif
                                            @if ($doc->encaminhamento_data)
                                                ({{ optional($doc->encaminhamento_data)->format('d/m/Y') }})
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </div>
                                </div>
                            @endif

                            <div class="col-md-6">
                                <label class="text-muted small text-uppercase fw-semibold">Departamento Atual</label>
                                <div class="fw-medium text-dark">{{ optional($doc->departamento)->nome ?? '—' }}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="text-muted small text-uppercase fw-semibold">Registrado por</label>
                                <div class="fw-medium text-dark">{{ optional($doc->usuario)->name ?? '—' }}</div>
                            </div>

                            <div class="col-12">
                                <label class="text-muted small text-uppercase fw-semibold">Observações</label>
                                <div class="p-3 bg-light rounded text-muted fst-italic">{{ $doc->observacoes ?? 'Sem observações.' }}</div>
                            </div>
                            
                            @if ($doc->tags->count())
                            <div class="col-12">
                                <label class="text-muted small text-uppercase fw-semibold d-block mb-1">Tags</label>
                                <div>
                                    @foreach ($doc->tags as $tag)
                                        <span class="badge bg-light text-dark border me-1"><i class="fas fa-tag me-1 text-secondary"></i> {{ $tag->nome }}</span>
                                    @endforeach
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Tabs for Tasks & History -->
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white border-bottom-0 pb-0">
                        <ul class="nav nav-tabs card-header-tabs" id="docTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active fw-medium" id="tasks-tab" data-bs-toggle="tab" data-bs-target="#tasks" type="button" role="tab" aria-controls="tasks" aria-selected="true">
                                    <i class="fas fa-tasks me-2"></i>Tarefas
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-medium" id="internal-tab" data-bs-toggle="tab" data-bs-target="#internal" type="button" role="tab" aria-controls="internal" aria-selected="false">
                                    <i class="fas fa-history me-2"></i>Histórico Interno
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-medium" id="external-tab" data-bs-toggle="tab" data-bs-target="#external" type="button" role="tab" aria-controls="external" aria-selected="false">
                                    <i class="fas fa-globe me-2"></i>Histórico Externo
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-medium" id="relations-tab" data-bs-toggle="tab" data-bs-target="#relations" type="button" role="tab" aria-controls="relations" aria-selected="false">
                                    <i class="fas fa-link me-2"></i>Vínculos
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="docTabsContent">
                            <!-- Tasks Tab -->
                            <div class="tab-pane fade show active" id="tasks" role="tabpanel" aria-labelledby="tasks-tab">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="fw-bold mb-0">Lista de Tarefas</h6>
                                    @php($actor = Auth::user())
                                    @php($gab = optional($doc->departamento)->gabinete)
                                    @php($actorIsRespGab = $actor && $gab && (int) optional($gab)->responsavel_id === (int) $actor->id)
                                    @php($actorIsSuperChefe = $actor && $gab && $actor->isSuperChefeDoGabinete($gab))
                                    @php($actorIsChiefDep = $actor && $actor->role && $actor->role->name === 'chefe-departamento')
                                    @php($actorDeps = $actor && method_exists($actor, 'departamentos') && $actor->departamentos ? $actor->departamentos->pluck('id')->all() : [])
                                    @php($actorDeps = !count($actorDeps) && $actor && $actor->departamento_id ? [$actor->departamento_id] : $actorDeps)
                                    @php($canAssignTask = $actorIsRespGab || $actorIsSuperChefe || ($actorIsChiefDep && in_array((int) optional($doc->departamento)->id, $actorDeps)))
                                    @if ($canAssignTask)
                                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalDesignarTarefa">
                                            <i class="fas fa-plus me-1"></i> Nova Tarefa
                                        </button>
                                    @endif
                                </div>

                                @php($tarefas = $doc->tarefas)
                                @if ($tarefas && $tarefas->count())
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Título</th>
                                                    <th>Solicitante</th>
                                                    <th>Destino</th>
                                                    <th>Prazo</th>
                                                    <th>Status</th>
                                                    <th>Responsável</th>
                                                    <th>Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($tarefas as $t)
                                                    <tr class="tarefa-row" style="cursor: pointer;">
                                                        <td>
                                                            <a href="#" class="text-decoration-none btn-ver-tarefa-show"
                                                                data-titulo="{{ e($t->titulo) }}"
                                                                data-descricao="{{ e($t->descricao) }}"
                                                                data-status="{{ $t->status }}"
                                                                data-prazo="{{ optional($t->prazo_at)->format('d/m/Y') ?? '—' }}"
                                                                data-solicitante="{{ optional($t->assignedBy)->name ?? '—' }}"
                                                                data-destino="{{ $t->assignedToUser ? optional($t->assignedToUser)->name : (optional($t->assignedToDepartamento)->nome ?? '—') }}"
                                                                data-destino-tipo="{{ $t->assignedToUser ? 'user' : ($t->assignedToDepartamento ? 'dep' : '') }}"
                                                                data-criado="{{ $t->created_at->format('d/m/Y H:i') }}">
                                                                <span class="fw-semibold text-dark">{{ $t->titulo }}</span>
                                                                @if($t->descricao)
                                                                    <div class="small text-muted text-truncate" style="max-width: 250px;">{{ $t->descricao }}</div>
                                                                @endif
                                                            </a>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="bg-light rounded-circle p-1 me-2" title="Solicitante">
                                                                    <i class="fas fa-user-edit text-secondary small"></i>
                                                                </div>
                                                                {{ optional($t->assignedBy)->name ?? '—' }}
                                                            </div>
                                                        </td>
                                                        <td>
                                                            @if ($t->assignedToUser)
                                                                <i class="fas fa-user text-secondary me-1"></i> {{ optional($t->assignedToUser)->name }}
                                                            @elseif($t->assignedToDepartamento)
                                                                <i class="fas fa-building text-secondary me-1"></i> {{ optional($t->assignedToDepartamento)->nome }}
                                                            @else
                                                                —
                                                            @endif
                                                        </td>
                                                        <td>{{ optional($t->prazo_at)->format('d/m/Y') ?? '—' }}</td>
                                                        <td><span class="badge text-bg-{{ $t->status === 'concluido' ? 'success' : 'secondary' }}">{{ ucfirst($t->status) }}</span></td>
                                                        <td>
                                                            @if ($t->assignedToDepartamento)
                                                                {{ optional($t->responsavelAtual)->name ?? '—' }}
                                                            @elseif($t->assignedToUser)
                                                                {{ optional($t->assignedToUser)->name ?? '—' }}
                                                            @else
                                                                —
                                                            @endif
                                                        </td>
                                                        <td class="text-end">
                                                            {{-- Action Buttons Logic --}}
                                                            @php($canAction = false)
                                                            @php($canConcluir = false)
                                                            @php($canCancelar = false)
                                                            @if ($t->status === 'pendente')
                                                                @php($isAssignedUser = $t->assigned_to_user_id && (int) $t->assigned_to_user_id === (int) optional($actor)->id)
                                                                @php($canChiefDep = false)
                                                                @php($canRespGab = false)
                                                                @if ($t->assigned_to_user_id)
                                                                    @php($u = $t->assignedToUser)
                                                                    @php($canChiefDep = $actorIsChiefDep && (in_array((int) optional($u)->departamento_id, $actorDeps) || ($u && $u->departamentos()->whereIn('departamento_id', $actorDeps)->exists())))
                                                                    @php($gabDepIds = $gab ? \App\Models\Departamento::where('gabinete_id', $gab->id)->pluck('id')->all() : [])
                                                                    @php($canRespGab = $actorIsRespGab && (in_array((int) optional($u)->departamento_id, $gabDepIds) || ($u && $u->departamentos()->whereIn('departamento_id', $gabDepIds)->exists())))
                                                                @elseif($t->assigned_to_departamento_id)
                                                                    @php($d = $t->assignedToDepartamento)
                                                                    @php($canChiefDep = $actorIsChiefDep && in_array((int) optional($d)->id, $actorDeps))
                                                                    @php($canRespGab = $actorIsRespGab && $gab && (int) optional($d)->gabinete_id === (int) $gab->id)
                                                                @endif
                                                                @php($canConcluir = $isAssignedUser || $canChiefDep || $canRespGab)
                                                                @php($canCancelar = $canConcluir || (int) $t->assigned_by_id === (int) optional($actor)->id)
                                                            @endif

                                                            @if ($canConcluir)
                                                                <form action="{{ route('documentos-entradas.tarefas.concluir', [$doc, $t]) }}" method="POST" class="d-inline">
                                                                    @csrf
                                                                    @method('PATCH')
                                                                    @if ($t->assignedToDepartamento && $t->status === 'pendente')
                                                                        <select name="responsavel_user_id" class="form-select form-select-sm d-inline-block w-auto me-1" style="min-width: 130px; max-width: 200px;" title="Quem executou a tarefa?">
                                                                            <option value="">Quem executou?</option>
                                                                            @foreach ($t->assignedToDepartamento->usuarios ?? collect() as $opt)
                                                                                <option value="{{ $opt->id }}" {{ optional($t->responsavelAtual)->id === $opt->id ? 'selected' : '' }}>{{ $opt->name }}</option>
                                                                            @endforeach
                                                                        </select>
                                                                    @endif
                                                                    <button class="btn btn-success btn-sm" title="Concluir"><i class="fas fa-check"></i></button>
                                                                </form>
                                                            @endif
                                                            @if ($canCancelar)
                                                                <form action="{{ route('documentos-entradas.tarefas.cancelar', [$doc, $t]) }}" method="POST" class="d-inline" onsubmit="return confirm('Cancelar esta tarefa?');">
                                                                    @csrf
                                                                    @method('PATCH')
                                                                    <button class="btn btn-outline-danger btn-sm" title="Cancelar"><i class="fas fa-times"></i></button>
                                                                </form>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="text-center py-4 text-muted">
                                        <i class="fas fa-clipboard-list fa-2x mb-2 opacity-50"></i>
                                        <p>Nenhuma tarefa registrada para este documento.</p>
                                    </div>
                                @endif
                            </div>

                            <!-- Internal History Tab -->
                            <div class="tab-pane fade" id="internal" role="tabpanel" aria-labelledby="internal-tab">
                                @php($encs = $doc->encaminhamentos)
                                @if ($encs && $encs->count())
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Data</th>
                                                    <th>Origem</th>
                                                    <th>Destino</th>
                                                    <th>Status</th>
                                                    <th>Observação</th>
                                                    <th>Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($encs as $e)
                                                    @php($isPending = !$e->recebido_em)
                                                    @php($rowClass = $isPending && $loop->last ? 'table-warning' : '')
                                                    @php($st = $e->recebido_em ? 'recebido' : $e->status ?? 'encaminhado')
                                                    <tr class="{{ $rowClass }}">
                                                        <td>{{ optional($e->encaminhado_em)->format('d/m/Y H:i') }}</td>
                                                        <td>{{ optional($e->origemDepartamento)->nome ?? '—' }}</td>
                                                        <td>{{ optional($e->destinoDepartamento)->nome ?? '—' }}</td>
                                                        <td><span class="badge text-bg-{{ $st === 'recebido' ? 'success' : 'warning' }}">{{ ucfirst($st) }}</span></td>
                                                        <td>{{ $e->observacao ?? '—' }}</td>
                                                        <td class="text-end">
                                                            @php($canReceiveRow = !$e->recebido_em && in_array((int) $e->destino_departamento_id, $deps))
                                                            @if ($canReceiveRow && $loop->last)
                                                                <form id="receber-form-{{ $e->id }}" action="{{ route('documentos-entradas.encaminhamentos.receber', [$doc, $e]) }}" method="POST">
                                                                    @csrf
                                                                    @method('PATCH')
                                                                    <button type="button" class="btn btn-success btn-sm" data-action="open-receber"
                                                                        data-form-id="receber-form-{{ $e->id }}"
                                                                        data-origem="{{ optional($e->origemDepartamento)->nome ?? '—' }}"
                                                                        data-destino="{{ optional($e->destinoDepartamento)->nome ?? '—' }}"
                                                                        data-data="{{ optional($e->encaminhado_em)->format('d/m/Y H:i') }}"
                                                                        title="Confirmar recebimento"><i class="fas fa-inbox me-1"></i> Receber</button>
                                                                </form>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="text-center py-4 text-muted">
                                        <i class="fas fa-history fa-2x mb-2 opacity-50"></i>
                                        <p>Sem histórico de encaminhamentos internos.</p>
                                    </div>
                                @endif
                            </div>

                            <!-- External History Tab -->
                            <div class="tab-pane fade" id="external" role="tabpanel" aria-labelledby="external-tab">
                                @php($encsExt = $doc->encaminhamentosExternos)
                                @if ($encsExt && $encsExt->count())
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Enviado em</th>
                                                    <th>Origem (Gab.)</th>
                                                    <th>Destino (Gab.)</th>
                                                    <th>Ofício Nº</th>
                                                    <th>Status</th>
                                                    <th>Observação</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($encsExt as $e)
                                                    @php($st = $e->status ?? 'enviado')
                                                    <tr>
                                                        <td>{{ optional($e->enviado_em)->format('d/m/Y') }}</td>
                                                        <td>{{ optional($e->origemGabinete)->nome }}</td>
                                                        <td>{{ optional($e->destinoGabinete)->nome }}</td>
                                                        <td>{{ $e->oficio_numero ?? '—' }}</td>
                                                        <td><span class="badge text-bg-info">{{ ucfirst($st) }}</span></td>
                                                        <td>{{ $e->observacao ?? '—' }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="text-center py-4 text-muted">
                                        <i class="fas fa-share-square fa-2x mb-2 opacity-50"></i>
                                        <p>Sem histórico de encaminhamentos externos.</p>
                                    </div>
                                @endif
                            </div>

                            <!-- Relations Tab -->
                            <div class="tab-pane fade" id="relations" role="tabpanel" aria-labelledby="relations-tab">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="fw-bold mb-0">Documentos Relacionados</h6>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalRelacionarDocumento">
                                        <i class="fas fa-link me-1"></i> Vincular Novo
                                    </button>
                                </div>

                                {{-- Display Internal Documents --}}
                                @if ($doc->documentosInternos && $doc->documentosInternos->count())
                                    <h6 class="small text-muted text-uppercase fw-bold mb-2 ps-1">Respostas / Documentos Internos</h6>
                                    <div class="list-group mb-4">
                                        @foreach ($doc->documentosInternos as $interno)
                                            <div class="list-group-item list-group-item-action p-3 border-start border-4 border-info">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="d-flex gap-3 align-items-center">
                                                        <div class="text-center" style="min-width: 80px;">
                                                            <div class="bg-info bg-opacity-10 rounded p-2 text-info mb-1 d-inline-block">
                                                                 <i class="fas fa-file-signature fa-lg"></i>
                                                            </div>
                                                            <div class="d-block">
                                                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle rounded-pill" style="font-size: 0.65rem;">
                                                                    INTERNO
                                                                </span>
                                                            </div>
                                                        </div>

                                                        <div>
                                                            <h6 class="mb-1 fw-bold">
                                                                <a href="{{ route('documentos-internos.show', $interno->id) }}" class="text-decoration-none text-dark stretched-link">
                                                                    {{ $interno->titulo }}
                                                                </a>
                                                            </h6>
                                                            <p class="mb-1 text-dark small text-truncate" style="max-width: 500px;">
                                                                Ref: {{ $interno->numero_referencia ?? 'S/Ref' }}
                                                            </p>
                                                            <div class="small text-muted">
                                                                <span class="me-3"><i class="fas fa-user me-1"></i> {{ optional($interno->autor)->name }}</span>
                                                                <span class="me-3"><i class="fas fa-calendar-alt me-1"></i> {{ $interno->created_at->format('d/m/Y') }}</span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="z-2 position-relative ms-2">
                                                        <form action="{{ route('documentos-entradas.desrelacionar', [$doc, $interno->id]) }}?type=interno" method="POST" onsubmit="return confirm('Remover vínculo com este documento interno?');">
                                                            @csrf @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-outline-danger border-0" title="Desvincular">
                                                                <i class="fas fa-unlink"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Display External/Entry Documents --}}
                                @if ($relacionados && $relacionados->count())
                                    <h6 class="small text-muted text-uppercase fw-bold mb-2 ps-1">Outras Entradas Relacionadas</h6>
                                    <div class="list-group">
                                        @foreach ($relacionados as $rel)
                                            <div class="list-group-item list-group-item-action p-3">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="d-flex gap-3 align-items-center">
                                                        <div class="text-center" style="min-width: 80px;">
                                                            <div class="bg-light rounded p-2 text-secondary mb-1 d-inline-block">
                                                                <i class="fas fa-file-alt fa-lg"></i>
                                                            </div>
                                                            <div class="d-block">
                                                                <?php
                                                                    $tipoVinculo = $rel->pivot->tipo ?? 'relacionado';
                                                                    $badgeClass = 'bg-secondary-subtle text-secondary border-secondary-subtle';
                                                                    if ($tipoVinculo === 'resposta') {
                                                                        $badgeClass = 'bg-info-subtle text-info-emphasis border-info-subtle';
                                                                    } elseif ($tipoVinculo === 'anexo') {
                                                                        $badgeClass = 'bg-success-subtle text-success-emphasis border-success-subtle';
                                                                    } elseif ($tipoVinculo === 'origem') {
                                                                        $badgeClass = 'bg-warning-subtle text-warning-emphasis border-warning-subtle';
                                                                    }
                                                                ?>
                                                                <span class="badge {{ $badgeClass }} border rounded-pill text-uppercase" style="font-size: 0.65rem;">
                                                                    {{ ucfirst($tipoVinculo) }}
                                                                </span>
                                                            </div>
                                                        </div>

                                                        <div>
                                                            <h6 class="mb-1 fw-bold">
                                                                <a href="{{ route('documentos-entradas.show', $rel->id) }}" class="text-decoration-none text-dark stretched-link">
                                                                    {{ $rel->numero_sequencial }}/{{ $rel->ano_referencia }}
                                                                </a>
                                                            </h6>
                                                            <p class="mb-1 text-dark small text-truncate" style="max-width: 500px;">
                                                                {{ $rel->assunto }}
                                                            </p>
                                                            <div class="small text-muted">
                                                                <span class="me-3"><i class="fas fa-calendar-alt me-1"></i> {{ optional($rel->data_entrada)->format('d/m/Y') }}</span>
                                                                <span class="me-3"><i class="fas fa-tag me-1"></i> {{ $rel->classificacao_ref_numero ?? 'S/Ref' }}</span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="z-2 position-relative ms-2">
                                                        <form action="{{ route('documentos-entradas.desrelacionar', [$doc, $rel]) }}" method="POST" onsubmit="return confirm('Remover este vínculo?');">
                                                            @csrf @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-outline-danger border-0" title="Remover Vínculo">
                                                                 <i class="fas fa-unlink"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                @if ((!$relacionados || $relacionados->count() == 0) && (!$doc->documentosInternos || $doc->documentosInternos->count() == 0))
                                    <div class="text-center py-5">
                                        <div class="bg-light rounded-circle d-inline-flex p-3 mb-3">
                                            <i class="fas fa-link fa-2x text-muted opacity-50"></i>
                                        </div>
                                        <p class="text-muted small mb-0">Nenhum documento vinculado.</p>
                                        <button class="btn btn-link btn-sm text-decoration-none" data-bs-toggle="modal" data-bs-target="#modalRelacionarDocumento">
                                            Adicionar o primeiro vínculo
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Sidebar -->
            <div class="col-lg-4">
                
                <!-- Status & Visas -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-transparent py-3 border-bottom">
                        <h6 class="card-title mb-0 fw-bold text-dark">Status de Aprovação</h6>
                    </div>
                    <div class="card-body">
                        <!-- Department Visto -->
                        @php($actor = Auth::user())
                        @php($actorIsChief = $actor && $actor->role && $actor->role->name === 'chefe-departamento')
                        @php($canVisto = $actorIsChief && in_array((int) optional($doc->departamento)->id, $actorDeps))
                        @php($stDep = $doc->visto_departamento_status ?? 'pendente')
                        @php($depClass = $stDep === 'aprovado' ? 'success' : ($stDep === 'rejeitado' ? 'danger' : 'secondary'))
                        @php($depIcon = $stDep === 'aprovado' ? 'fa-check-circle' : ($stDep === 'rejeitado' ? 'fa-times-circle' : 'fa-hourglass-half'))
                        
                        <div class="mb-4">
                            <label class="small text-muted text-uppercase mb-2 fw-semibold">Chefe do Departamento</label>
                            <div class="d-flex align-items-center p-3 border rounded bg-{{ $depClass }} bg-opacity-10 border-{{ $depClass }}">
                                <i class="fas {{ $depIcon }} text-{{ $depClass }} fs-3 me-3"></i>
                                <div>
                                    <div class="fw-bold text-{{ $depClass }}">{{ ucfirst($stDep) }}</div>
                                    <div class="small text-muted">
                                        {{ optional($doc->vistoDepartamentoPor)->name ?? 'Aguardando' }}
                                        @if($doc->visto_departamento_data)
                                            • {{ $doc->visto_departamento_data->format('d/m H:i') }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                            
                            @if ($doc->visto_departamento_observacao)
                                <div class="mt-2 small text-danger"><i class="fas fa-comment me-1"></i> {{ $doc->visto_departamento_observacao }}</div>
                            @endif

                            @if ($canVisto && !$doc->saida_gabinete_data)
                                <div class="mt-2 d-flex gap-2">
                                    <form id="visto-dep-aprovar-form" action="{{ route('documentos-entradas.visto.aprovar', $doc) }}" method="POST" class="w-100">
                                        @csrf
                                        @method('PATCH')
                                        <button type="button" id="btnVistoDepartamentoAprovar" class="btn btn-success btn-sm w-100"><i class="fas fa-check me-1"></i> Aprovar</button>
                                    </form>
                                    <button type="button" class="btn btn-outline-danger btn-sm w-100" data-bs-toggle="modal" data-bs-target="#vistoRejeitarModal"><i class="fas fa-times me-1"></i> Rejeitar</button>
                                </div>
                            @endif
                        </div>

                        <!-- Gabinete Visto -->
                        @php($gab = optional($doc->departamento)->gabinete)
                        @php($actorIsRespGab = $actor && $gab && (int) optional($gab)->responsavel_id === (int) $actor->id)
                        @php($canVistoGabinete = $actorIsRespGab)
                        @php($stGab = $doc->visto_gabinete_status ?? 'pendente')
                        @php($gabClass = $stGab === 'aprovado' ? 'success' : ($stGab === 'rejeitado' ? 'danger' : 'secondary'))
                        @php($gabIcon = $stGab === 'aprovado' ? 'fa-check-circle' : ($stGab === 'rejeitado' ? 'fa-times-circle' : 'fa-hourglass-half'))

                        <div>
                            <label class="small text-muted text-uppercase mb-2 fw-semibold">Gabinete</label>
                            <div class="d-flex align-items-center p-3 border rounded bg-{{ $gabClass }} bg-opacity-10 border-{{ $gabClass }}">
                                <i class="fas {{ $gabIcon }} text-{{ $gabClass }} fs-3 me-3"></i>
                                <div>
                                    <div class="fw-bold text-{{ $gabClass }}">{{ ucfirst($stGab) }}</div>
                                    <div class="small text-muted">
                                        {{ optional($doc->vistoGabinetePor)->name ?? 'Aguardando' }}
                                        @if($doc->visto_gabinete_data)
                                            • {{ $doc->visto_gabinete_data->format('d/m H:i') }}
                                        @endif
                                    </div>
                                </div>
                            </div>

                            @if ($doc->visto_gabinete_observacao)
                                <div class="mt-2 small text-danger"><i class="fas fa-comment me-1"></i> {{ $doc->visto_gabinete_observacao }}</div>
                            @endif

                            @if ($canVistoGabinete && !$doc->saida_gabinete_data)
                                <div class="mt-2 d-flex gap-2">
                                    <form id="visto-gab-aprovar-form" action="{{ route('documentos-entradas.visto-gabinete.aprovar', $doc) }}" method="POST" class="w-100">
                                        @csrf
                                        @method('PATCH')
                                        <button type="button" id="btnVistoGabineteAprovar" class="btn btn-success btn-sm w-100"><i class="fas fa-check me-1"></i> Aprovar</button>
                                    </form>
                                    <button type="button" class="btn btn-outline-danger btn-sm w-100" data-bs-toggle="modal" data-bs-target="#vistoGabineteRejeitarModal"><i class="fas fa-times me-1"></i> Rejeitar</button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Attachments Card -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-transparent py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="card-title mb-0 fw-bold text-dark">Arquivos & Anexos</h6>
                        <span class="badge bg-secondary rounded-pill">{{ $doc->anexos->count() + ($doc->arquivo_caminho ? 1 : 0) }}</span>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <!-- Main File -->
                            @if ($doc->arquivo_caminho)
                                <li class="list-group-item d-flex justify-content-between align-items-center p-3">
                                    <div class="d-flex align-items-center overflow-hidden">
                                        <div class="bg-primary bg-opacity-10 p-2 rounded me-3 text-primary">
                                            <i class="fas fa-file-alt"></i>
                                        </div>
                                        <div class="text-truncate">
                                            <div class="fw-semibold text-truncate">Arquivo Principal</div>
                                            <div class="small text-muted">Visualizar documento digitalizado</div>
                                        </div>
                                    </div>
                                    <a href="{{ route('documentos-entradas.arquivo.download', $doc) }}" target="_blank" class="btn btn-sm btn-outline-primary ms-2"><i class="fas fa-eye"></i></a>
                                </li>
                            @endif

                            <!-- Attachments -->
                            @foreach ($doc->anexos as $an)
                                <li class="list-group-item d-flex justify-content-between align-items-center p-3">
                                    <div class="d-flex align-items-center overflow-hidden">
                                        <div class="bg-secondary bg-opacity-10 p-2 rounded me-3 text-secondary">
                                            <i class="fas fa-paperclip"></i>
                                        </div>
                                        <div class="text-truncate">
                                            <div class="fw-semibold text-truncate" title="{{ $an->nome_original }}">{{ $an->nome_original ?? basename($an->caminho_arquivo) }}</div>
                                            <div class="small text-muted">{{ number_format(($an->tamanho_bytes ?? 0) / 1024, 1) }} KB</div>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-1 ms-2">
                                        @if(in_array($an->mime_type, ['application/pdf']) || str_starts_with($an->mime_type, 'image/'))
                                            <button type="button" class="btn btn-sm btn-light text-secondary btn-ver-ocr" data-anexo-id="{{ $an->id }}" data-nome="{{ $an->nome_original }}" title="Ver Texto Extraído (OCR)">
                                                <i class="fas fa-file-alt text-success"></i>
                                            </button>
                                        @endif
                                        <a href="{{ route('documentos-entradas.anexos.download', [$doc, $an]) }}" target="_blank" class="btn btn-sm btn-light text-primary"><i class="fas fa-download"></i></a>
                                        <form action="{{ route('documentos-entradas.anexos.destroy', [$doc, $an]) }}" method="POST" onsubmit="return confirm('Remover este anexo?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-light text-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </li>
                            @endforeach
                            
                            @if(!$doc->arquivo_caminho && $doc->anexos->count() === 0)
                                <li class="list-group-item p-4 text-center text-muted">
                                    Nenhum arquivo anexado.
                                </li>
                            @endif
                        </ul>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Modals Section (Hidden) -->
    @include('documentos_entradas.partials.modals')

    <!-- Modal Detalhes da Tarefa (Show Page) -->
    <div class="modal fade" id="modalDetalheTarefaShow" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom-0 pb-0">
                    <div>
                        <h5 class="modal-title fw-bold text-dark" id="modalShowTarefaTitulo"></h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body pt-2">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge rounded-pill" id="modalShowTarefaStatus"></span>
                        <span class="badge bg-light text-dark border" id="modalShowTarefaPrazo"></span>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small text-uppercase fw-semibold d-block mb-1">Descrição</label>
                        <div class="p-3 bg-light rounded border-start border-4 border-primary" id="modalShowTarefaDescricao" style="white-space: pre-line;"></div>
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="text-muted small text-uppercase fw-semibold d-block mb-1">Solicitado por</label>
                            <div class="fw-medium text-dark" id="modalShowTarefaSolicitante"></div>
                        </div>
                        <div class="col-sm-6">
                            <label class="text-muted small text-uppercase fw-semibold d-block mb-1">Destino</label>
                            <div class="fw-medium text-dark" id="modalShowTarefaDestino"></div>
                        </div>
                        <div class="col-sm-6">
                            <label class="text-muted small text-uppercase fw-semibold d-block mb-1">Criado em</label>
                            <div class="fw-medium text-dark" id="modalShowTarefaCriado"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Visualizar OCR do Anexo -->
    <div class="modal fade" id="modalVerOcrAnexo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-success text-white border-bottom-0">
                    <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                        <i class="fas fa-file-alt"></i>
                        <span>Texto Extraído via OCR / Parser</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="small text-muted text-uppercase fw-semibold d-block mb-1">Arquivo Anexo</label>
                        <div class="fw-bold text-dark fs-5" id="modalOcrAnexoNome"></div>
                    </div>
                    <div class="position-relative">
                        <label class="small text-muted text-uppercase fw-semibold d-block mb-2">Conteúdo do Documento</label>
                        <div id="modalOcrLoading" class="text-center py-5 text-muted d-none">
                            <span class="spinner-border spinner-border-sm me-2 text-success"></span> A carregar o texto extraído...
                        </div>
                        <div id="modalOcrContentContainer" class="p-3 bg-light rounded border border-secondary border-opacity-10 position-relative" style="max-height: 50vh; overflow-y: auto;">
                            <button class="btn btn-sm btn-outline-secondary position-absolute top-0 end-0 m-2" id="btnCopiarOcr" title="Copiar Texto">
                                <i class="far fa-copy"></i> Copiar
                            </button>
                            <pre id="modalOcrAnexoConteudo" style="white-space: pre-wrap; font-family: inherit; font-size: 0.9rem; margin-top: 1.5rem;" class="text-dark mb-0"></pre>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Modal de encaminhamento interno
            const form = document.getElementById('form-encaminhar');
            const btn = document.getElementById('btnEncaminhar');
            const modalEl = document.getElementById('confirmEncaminharModal');
            if (form && btn && modalEl) {
                btn.addEventListener('click', function() {
                    // Lê o combobox pesquisável: input hidden (id) + campo de texto (nome legível).
                    const destinoHidden = form.querySelector('input[name="destino_departamento_id"]');
                    const destinoLabel = form.querySelector('.dep-combobox-input');
                    const destinoText = destinoHidden && destinoHidden.value
                        ? (destinoLabel && destinoLabel.value ? destinoLabel.value : destinoHidden.value)
                        : '—';
                    const obsField = form.querySelector('[name="observacao"]');
                    const observacao = (obsField && obsField.value) ? obsField.value : '—';
                    modalEl.querySelector('[data-field="destino"]').textContent = destinoText;
                    modalEl.querySelector('[data-field="observacao"]').textContent = observacao;
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();
                });
                modalEl.querySelector('[data-action="confirm"]').addEventListener('click', function() {
                    form.submit();
                });
            }

            // Novo: Modal de saída para outro gabinete
            const formSaida = document.getElementById('form-saida-gabinete');
            const btnSaida = document.getElementById('btnSaidaGabinete');
            const modalSaidaEl = document.getElementById('confirmSaidaGabineteModal');
            if (formSaida && btnSaida && modalSaidaEl) {
                btnSaida.addEventListener('click', function() {
                    const destSel = formSaida.querySelector('select[name="destino_gabinete_id"]');
                    const destText = destSel && destSel.value ? destSel.options[destSel.selectedIndex].text : '—';
                    const dataSaida = formSaida.querySelector('input[name="saida_gabinete_data"]').value || '—';
                    const oficio = formSaida.querySelector('input[name="encaminhamento_oficio_numero"]').value || '—';
                    
                    modalSaidaEl.querySelector('[data-field="destino_gabinete"]').textContent = destText;
                    modalSaidaEl.querySelector('[data-field="data_saida"]').textContent = dataSaida.split('-').reverse().join('/');
                    modalSaidaEl.querySelector('[data-field="oficio_numero"]').textContent = oficio;
                    const modal = new bootstrap.Modal(modalSaidaEl);
                    modal.show();
                });
                modalSaidaEl.querySelector('[data-action="confirm-saida"]').addEventListener('click', function() {
                    formSaida.submit();
                });
            }

            const btnVistoDep = document.getElementById('btnVistoDepartamentoAprovar');
            const vistoDepForm = document.getElementById('visto-dep-aprovar-form');
            const modalVistoDepEl = document.getElementById('confirmVistoDepAprovarModal');
            if (btnVistoDep && vistoDepForm && modalVistoDepEl) {
                btnVistoDep.addEventListener('click', function() {
                    const modal = new bootstrap.Modal(modalVistoDepEl);
                    modal.show();
                });
                modalVistoDepEl.querySelector('[data-action="confirm-visto-dep"]').addEventListener('click', function() {
                    vistoDepForm.submit();
                });
            }

            const btnVistoGab = document.getElementById('btnVistoGabineteAprovar');
            const vistoGabForm = document.getElementById('visto-gab-aprovar-form');
            const modalVistoGabEl = document.getElementById('confirmVistoGabAprovarModal');
            if (btnVistoGab && vistoGabForm && modalVistoGabEl) {
                btnVistoGab.addEventListener('click', function() {
                    const modal = new bootstrap.Modal(modalVistoGabEl);
                    modal.show();
                });
                modalVistoGabEl.querySelector('[data-action="confirm-visto-gab"]').addEventListener('click', function() {
                    vistoGabForm.submit();
                });
            }

            const modalRecEl = document.getElementById('confirmReceberEncaminhamentoModal');
            document.querySelectorAll('[data-action="open-receber"]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const formId = btn.getAttribute('data-form-id');
                    modalRecEl.setAttribute('data-form-id', formId);
                    modalRecEl.querySelector('[data-field="rec-origem"]').textContent = btn.getAttribute('data-origem') || '—';
                    modalRecEl.querySelector('[data-field="rec-destino"]').textContent = btn.getAttribute('data-destino') || '—';
                    modalRecEl.querySelector('[data-field="rec-data"]').textContent = btn.getAttribute('data-data') || '—';
                    const modal = new bootstrap.Modal(modalRecEl);
                    modal.show();
                });
            });
            if (modalRecEl) {
                modalRecEl.querySelector('[data-action="confirm-receber"]').addEventListener('click', function() {
                    const formId = modalRecEl.getAttribute('data-form-id');
                    const form = formId ? document.getElementById(formId) : null;
                    if (form) {
                        form.submit();
                    }
                });
            }
            const tarefaForm = document.getElementById('form-designar-tarefa');
            if (tarefaForm) {
                const tipoUsuario = tarefaForm.querySelector('#tipoUsuario');
                const tipoDepartamento = tarefaForm.querySelector('#tipoDepartamento');
                const destinoUsuario = tarefaForm.querySelector('[data-field="destino-usuario"]');
                const destinoDepartamento = tarefaForm.querySelector('[data-field="destino-departamento"]');
                const depSelect = destinoDepartamento ? destinoDepartamento.querySelector('select') : null;
                const userSelect = destinoUsuario.querySelector('select');
                const updateVisibility = function() {
                    const isDep = tipoDepartamento && tipoDepartamento.checked;
                    destinoUsuario.classList.toggle('d-none', isDep);
                    if (destinoDepartamento) destinoDepartamento.classList.toggle('d-none', !isDep);
                    const selectVisible = isDep ? depSelect : userSelect;
                    if (selectVisible && selectVisible.name !== 'destino_id') {
                        const otherSelect = isDep ? userSelect : depSelect;
                        if (otherSelect) otherSelect.removeAttribute('name');
                        selectVisible.setAttribute('name', 'destino_id');
                    }
                };
                if (tipoUsuario) tipoUsuario.addEventListener('change', updateVisibility);
                if (tipoDepartamento) tipoDepartamento.addEventListener('change', updateVisibility);
                updateVisibility();
                
                // New: Loading state for submit button
                tarefaForm.addEventListener('submit', function() {
                    const btn = document.getElementById('btnSubmitTarefa');
                    if (btn) {
                        btn.disabled = true;
                        btn.querySelector('.spinner-border').classList.remove('d-none');
                        btn.querySelector('.fa-plus').classList.add('d-none');
                    }
                });
            }
            // Modal Detalhes da Tarefa (clique no título)
            document.addEventListener('click', function(e) {
                const link = e.target.closest('.btn-ver-tarefa-show');
                if (!link) return;
                e.preventDefault();

                const d = link.dataset;
                document.getElementById('modalShowTarefaTitulo').textContent = d.titulo;

                // Description
                const descEl = document.getElementById('modalShowTarefaDescricao');
                descEl.textContent = d.descricao || 'Sem descrição detalhada.';
                if (!d.descricao) descEl.classList.add('text-muted', 'fst-italic');
                else descEl.classList.remove('text-muted', 'fst-italic');

                // Status badge
                const statusEl = document.getElementById('modalShowTarefaStatus');
                const statusMap = {
                    pendente: { bg: 'bg-warning', label: 'Pendente' },
                    concluida: { bg: 'bg-success', label: 'Concluída' },
                    concluido: { bg: 'bg-success', label: 'Concluído' },
                    cancelada: { bg: 'bg-danger', label: 'Cancelada' },
                };
                const st = statusMap[d.status] || { bg: 'bg-secondary', label: d.status };
                statusEl.className = 'badge rounded-pill ' + st.bg;
                statusEl.textContent = st.label;

                // Prazo
                const prazoEl = document.getElementById('modalShowTarefaPrazo');
                prazoEl.innerHTML = '<i class="far fa-calendar-alt me-1"></i> Prazo: ' + (d.prazo || '—');

                // Solicitante
                document.getElementById('modalShowTarefaSolicitante').textContent = d.solicitante;

                // Destino
                const destinoEl = document.getElementById('modalShowTarefaDestino');
                const iconClass = d.destinoTipo === 'user' ? 'fa-user' : (d.destinoTipo === 'dep' ? 'fa-building' : 'fa-minus');
                destinoEl.innerHTML = '<i class="fas ' + iconClass + ' text-secondary me-1"></i> ' + d.destino;

                // Criado em
                document.getElementById('modalShowTarefaCriado').textContent = d.criado;

                // Open modal
                const modal = new bootstrap.Modal(document.getElementById('modalDetalheTarefaShow'));
                modal.show();
            });

            // Clicar em qualquer parte da linha da tarefa (exceto controlos de ação) também abre o modal
            document.addEventListener('click', function(e) {
                if (e.target.closest('.btn-ver-tarefa-show')) return; // título já é tratado acima
                if (e.target.closest('a, button, select, input, label, form, [data-bs-toggle]')) return; // ignora ações
                const row = e.target.closest('tr.tarefa-row');
                if (!row) return;
                const link = row.querySelector('.btn-ver-tarefa-show');
                if (link) link.click();
            });

            // Modal de visualizar OCR do Anexo
            document.querySelectorAll('.btn-ver-ocr').forEach(function(button) {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const anexoId = this.getAttribute('data-anexo-id');
                    const nome = this.getAttribute('data-nome');
                    
                    document.getElementById('modalOcrAnexoNome').textContent = nome;
                    
                    const contentContainer = document.getElementById('modalOcrContentContainer');
                    const preEl = document.getElementById('modalOcrAnexoConteudo');
                    const loadingEl = document.getElementById('modalOcrLoading');
                    const btnCopiar = document.getElementById('btnCopiarOcr');
                    
                    contentContainer.classList.add('d-none');
                    loadingEl.classList.remove('d-none');
                    
                    const modal = new bootstrap.Modal(document.getElementById('modalVerOcrAnexo'));
                    modal.show();
                    
                    fetch(`/documentos-entradas/{{ $doc->id }}/anexos/${anexoId}/ocr`)
                        .then(response => response.json())
                        .then(data => {
                            loadingEl.classList.add('d-none');
                            contentContainer.classList.remove('d-none');
                            
                            if (data.texto_extraido && data.texto_extraido.trim() !== '') {
                                preEl.textContent = data.texto_extraido;
                                btnCopiar.classList.remove('d-none');
                            } else {
                                preEl.innerHTML = '<span class="text-muted italic"><i class="fas fa-info-circle me-1"></i> Não foi possível extrair nenhum texto deste anexo ou o processo de OCR ainda está a decorrer.</span>';
                                btnCopiar.classList.add('d-none');
                            }
                        })
                        .catch(err => {
                            loadingEl.classList.add('d-none');
                            contentContainer.classList.remove('d-none');
                            preEl.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i> Erro ao carregar o texto extraído.</span>';
                            btnCopiar.classList.add('d-none');
                        });
                });
            });

            // Copiar texto OCR
            const btnCopiar = document.getElementById('btnCopiarOcr');
            if (btnCopiar) {
                btnCopiar.addEventListener('click', function() {
                    const text = document.getElementById('modalOcrAnexoConteudo').textContent;
                    navigator.clipboard.writeText(text).then(() => {
                        const originalHTML = this.innerHTML;
                        this.innerHTML = '<i class="fas fa-check text-success"></i> Copiado!';
                        this.classList.remove('btn-outline-secondary');
                        this.classList.add('btn-outline-success');
                        setTimeout(() => {
                            this.innerHTML = originalHTML;
                            this.classList.remove('btn-outline-success');
                            this.classList.add('btn-outline-secondary');
                        }, 2000);
                    });
                });
            }
        });
    </script>

    @if (config('app.feature_assistente') && auth()->user()?->can('assistente.usar'))
        @include('assistente._documento', [
            'ctxRoute' => route('assistente.entrada', $doc),
            'ctxTitulo' => 'este documento de entrada',
        ])
    @endif
@endsection
