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
                                    @php($actorIsChiefDep = $actor && $actor->role && $actor->role->name === 'chefe-departamento')
                                    @php($actorDeps = $actor && method_exists($actor, 'departamentos') && $actor->departamentos ? $actor->departamentos->pluck('id')->all() : [])
                                    @php($actorDeps = !count($actorDeps) && $actor && $actor->departamento_id ? [$actor->departamento_id] : $actorDeps)
                                    @php($canAssignTask = $actorIsRespGab || ($actorIsChiefDep && in_array((int) optional($doc->departamento)->id, $actorDeps)))
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
                                                    <tr>
                                                        <td>{{ $t->titulo }}</td>
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
    
    <!-- Modal Rejeitar Visto Departamento -->
    <div class="modal fade" id="vistoRejeitarModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('documentos-entradas.visto.rejeitar', $doc) }}" method="POST">
                    @csrf
                    @method('PATCH')
                    <div class="modal-header">
                        <h5 class="modal-title">Rejeitar visto</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-floating">
                            <textarea class="form-control" id="visto_departamento_observacao" name="visto_departamento_observacao" style="height: 120px" placeholder="Motivo da rejeição" required></textarea>
                            <label for="visto_departamento_observacao">Motivo/observação</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Rejeitar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Rejeitar Visto Gabinete -->
    <div class="modal fade" id="vistoGabineteRejeitarModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('documentos-entradas.visto-gabinete.rejeitar', $doc) }}" method="POST">
                    @csrf
                    @method('PATCH')
                    <div class="modal-header">
                        <h5 class="modal-title">Rejeitar visto do gabinete</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-floating">
                            <textarea class="form-control" id="visto_gabinete_observacao" name="visto_gabinete_observacao" style="height: 120px" placeholder="Motivo da rejeição" required></textarea>
                            <label for="visto_gabinete_observacao">Motivo/observação</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Rejeitar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Encaminhar Documento -->
    <div class="modal fade" id="modalEncaminharDocumento" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Encaminhar documento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="form-encaminhar" action="{{ route('documentos-entradas.encaminhar', $doc) }}" method="POST" class="row g-3">
                        @csrf
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Destino (Departamento)</label>
                            <select name="destino_departamento_id" class="form-select" required>
                                <option value="">Selecione...</option>
                                @foreach ($departamentos as $dep)
                                    <option value="{{ $dep->id }}">{{ $dep->nome }}</option>
                                @endforeach
                            </select>
                            @error('destino_departamento_id')
                                <div class="text-danger small">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Observação</label>
                            <input type="text" name="observacao" class="form-control" value="{{ old('observacao') }}">
                        </div>
                        <div class="col-12 text-end">
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" id="btnEncaminhar" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i> Encaminhar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Designar Tarefa -->
    <div class="modal fade" id="modalDesignarTarefa" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Designar tarefa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="form-designar-tarefa" action="{{ route('documentos-entradas.tarefas.store', $doc) }}" method="POST" class="row g-3">
                        @csrf
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Tipo de destino</label>
                            <div class="p-3 bg-light rounded border d-flex gap-3 align-items-center">
                                @php($actor = Auth::user())
                                @php($gab = optional($doc->departamento)->gabinete)
                                @php($actorIsRespGab = $actor && $gab && (int) optional($gab)->responsavel_id === (int) $actor->id)
                                @if ($actorIsRespGab)
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="tipo" id="tipoUsuario" value="usuario" checked>
                                        <label class="form-check-label" for="tipoUsuario"><i class="fas fa-user me-1"></i> Usuário Específico</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="tipo" id="tipoDepartamento" value="departamento">
                                        <label class="form-check-label" for="tipoDepartamento"><i class="fas fa-building me-1"></i> Departamento Inteiro</label>
                                    </div>
                                @else
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="tipo" id="tipoUsuario" value="usuario" checked>
                                        <label class="form-check-label" for="tipoUsuario"><i class="fas fa-user me-1"></i> Usuário do Departamento</label>
                                    </div>
                                @endif
                            </div>
                        </div>
                        
                        <div class="col-md-6" data-field="destino-usuario">
                            <label class="form-label fw-semibold">Usuário destino <span class="text-danger">*</span></label>
                            <select name="destino_id" class="form-select" required>
                                <option value="">Selecione um usuário...</option>
                                @php($listaUsuarios = $actorIsRespGab ? $gabUsuarios ?? collect() : $depUsuarios ?? collect())
                                @foreach ($listaUsuarios as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                            @error('destino_id')
                                <div class="text-danger small">{{ $message }}</div>
                            @enderror
                        </div>

                        @if ($actorIsRespGab)
                            <div class="col-md-6 d-none" data-field="destino-departamento">
                                <label class="form-label fw-semibold">Departamento destino <span class="text-danger">*</span></label>
                                <select class="form-select">
                                    <option value="">Selecione um departamento...</option>
                                    @foreach ($gabDepartamentos ?? collect() as $d)
                                        <option value="{{ $d->id }}">{{ $d->nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Prazo <span class="text-danger">*</span></label>
                            <input type="date" name="prazo_at" class="form-control" value="{{ old('prazo_at') }}" min="{{ date('Y-m-d') }}" required>
                            @error('prazo_at')
                                <div class="text-danger small">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Título da Tarefa <span class="text-danger">*</span></label>
                            <input type="text" name="titulo" class="form-control" placeholder="Ex: Analisar solicitação..." required>
                            @error('titulo')
                                <div class="text-danger small">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label fw-semibold">Descrição Detalhada</label>
                            <textarea name="descricao" class="form-control" rows="4" placeholder="Descreva o que precisa ser feito..."></textarea>
                            @error('descricao')
                                <div class="text-danger small">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 text-end pt-2 border-top">
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary" id="btnSubmitTarefa">
                                <span class="spinner-border spinner-border-sm d-none me-1" role="status" aria-hidden="true"></span>
                                <i class="fas fa-plus me-1"></i> Designar Tarefa
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Saída Gabinete -->
    <div class="modal fade" id="modalSaidaGabinete" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Saída para outro Gabinete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if ($doc->saida_gabinete_data)
                        <div class="alert alert-info">
                            Documento saiu em {{ optional($doc->saida_gabinete_data)->format('d/m/Y') }} para {{ $doc->encaminhamento_orgao ?? '—' }}.
                        </div>
                    @else
                        <form id="form-saida-gabinete" action="{{ route('documentos-entradas.saida-gabinete', $doc) }}" method="POST" class="row g-3">
                            @csrf
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Gabinete de destino</label>
                                <select name="destino_gabinete_id" class="form-select" required>
                                    <option value="">Selecione...</option>
                                    @foreach ($gabinetes as $gab)
                                        @php($isSame = optional($doc->departamento)->gabinete_id === $gab->id)
                                        <option value="{{ $gab->id }}" {{ $isSame ? 'disabled' : '' }}>
                                            {{ $gab->nome }} @if ($gab->sigla) ({{ $gab->sigla }}) @endif 
                                            @if ($isSame) — atual @endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('destino_gabinete_id')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Data da saída</label>
                                <input type="date" name="saida_gabinete_data" class="form-control" value="{{ old('saida_gabinete_data', now()->format('Y-m-d')) }}" required>
                                @error('saida_gabinete_data')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Ofício Nº</label>
                                <input type="text" name="encaminhamento_oficio_numero" class="form-control" value="{{ old('encaminhamento_oficio_numero') }}">
                                @error('encaminhamento_oficio_numero')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12">
                                <div class="alert alert-warning small mb-3">
                                    <i class="fas fa-exclamation-triangle me-1"></i> Após dar saída, encaminhamentos internos serão bloqueados.
                                </div>
                                <button type="button" id="btnSaidaGabinete" class="btn btn-primary"><i class="fas fa-share-square me-1"></i> Dar Saída</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modals -->
    <div class="modal fade" id="confirmVistoDepAprovarModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar visto do departamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Deseja realmente aprovar o visto para o documento <strong>{{ $doc->numero_sequencial }}/{{ $doc->ano_referencia }}</strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" data-action="confirm-visto-dep">Confirmar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmVistoGabAprovarModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar visto do gabinete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Deseja realmente aprovar o visto de gabinete para o documento <strong>{{ $doc->numero_sequencial }}/{{ $doc->ano_referencia }}</strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" data-action="confirm-visto-gab">Confirmar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmReceberEncaminhamentoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar recebimento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2"><strong>Origem:</strong> <span data-field="rec-origem"></span></div>
                    <div class="mb-2"><strong>Destino:</strong> <span data-field="rec-destino"></span></div>
                    <div class="mb-2"><strong>Encaminhado em:</strong> <span data-field="rec-data"></span></div>
                    <div class="mt-3 text-muted small">Ao confirmar, o documento será marcado como recebido.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" data-action="confirm-receber">Confirmar recebimento</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmEncaminharModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar encaminhamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2"><strong>Para:</strong> <span data-field="destino"></span></div>
                    <div class="mb-2"><strong>Observação:</strong> <span data-field="observacao"></span></div>
                    <div class="mt-3 text-muted small">Ao confirmar, o documento será encaminhado para o destino selecionado.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" data-action="confirm">Confirmar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmSaidaGabineteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar saída</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2"><strong>Destino:</strong> <span data-field="destino_gabinete"></span></div>
                    <div class="mb-2"><strong>Data:</strong> <span data-field="data_saida"></span></div>
                    <div class="mb-2"><strong>Ofício:</strong> <span data-field="oficio_numero"></span></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" data-action="confirm-saida">Confirmar saída</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalArquivarDocumento" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('documentos-entradas.arquivar', $doc->id) }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Arquivar Documento</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Selecione a pasta onde deseja arquivar este documento.</p>
                        <div class="mb-3">
                            <label class="form-label">Pasta</label>
                            <select name="pasta_id" class="form-select" required>
                                <option value="">Selecione uma pasta...</option>
                                @foreach($pastas as $pasta)
                                    <option value="{{ $pasta->id }}">
                                        @if($pasta->departamento)
                                            [{{ $pasta->departamento->gabinete->sigla ?? '?' }}/{{ $pasta->departamento->sigla ?? '?' }}] 
                                        @endif
                                        {{ $pasta->nome }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <p class="text-muted small">
                            <i class="fas fa-info-circle"></i> O documento será movido para a pasta selecionada e ficará disponível apenas na busca do arquivo.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Arquivar</button>
                    </div>
                </form>
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
                    const destinoSel = form.querySelector('select[name="destino_departamento_id"]');
                    const destinoText = destinoSel && destinoSel.value ? destinoSel.options[destinoSel.selectedIndex].text : '—';
                    const observacao = form.querySelector('input[name="observacao"]').value || '—';
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
        });
    </script>
@endsection
