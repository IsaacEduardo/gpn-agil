{{--
    Tabelas detalhadas do percurso. Cada uma corresponde a um chip e abre-se em
    "ver em tabela". Mantêm TODAS as colunas e TODAS as ações das abas
    anteriores — em particular o botão Receber do histórico interno e o
    concluir/cancelar das tarefas.
--}}
<div class="percurso-tabelas border-top">
                            <div class="percurso-tabela" data-tabela="interno" hidden>
        <div class="px-3 py-2 bg-body-tertiary border-bottom d-flex align-items-center justify-content-between">
            <h3 class="h6 fw-semibold mb-0 text-dark">Histórico Interno de Tramitação</h3>
            <button type="button" class="btn btn-sm btn-link text-decoration-none percurso-fechar-tabela">
                <i class="fas fa-xmark me-1"></i>Fechar
            </button>
        </div>
                                @php($encs = $doc->encaminhamentos)
                                @if ($encs && $encs->count())
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Data Envio</th>
                                                    <th>Origem</th>
                                                    <th>Destino</th>
                                                    <th>Status & Recebimento</th>
                                                    <th>Observação</th>
                                                    <th>Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($encs as $e)
                                                    @php($isPending = !$e->recebido_em)
                                                    @php($rowClass = $isPending && $loop->last ? 'table-warning' : '')
                                                    <tr class="{{ $rowClass }}">
                                                        <td>
                                                            <div class="fw-semibold">{{ optional($e->encaminhado_em)->format('d/m/Y H:i') }}</div>
                                                            <div class="small text-muted"><i class="fas fa-user-edit me-1"></i>{{ optional($e->usuario)->name ?? '—' }}</div>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-light text-dark border"><i class="fas fa-building text-secondary me-1"></i>{{ optional($e->origemDepartamento)->nome ?? '—' }}</span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-light text-dark border"><i class="fas fa-location-arrow text-primary me-1"></i>{{ optional($e->destinoDepartamento)->nome ?? '—' }}</span>
                                                        </td>
                                                        <td>
                                                            @if ($e->recebido_em)
                                                                <span class="badge text-bg-success mb-1"><i class="fas fa-check-double me-1"></i>Recebido</span>
                                                                <div class="small text-dark fw-medium">
                                                                    <i class="fas fa-user-check text-success me-1"></i>{{ optional($e->recebidoPor)->name ?? 'Utilizador' }}
                                                                </div>
                                                                <div class="small text-muted" style="font-size: 0.75rem;">
                                                                    <i class="far fa-clock me-1"></i>{{ $e->recebido_em->format('d/m/Y H:i') }}
                                                                </div>
                                                            @else
                                                                <span class="badge text-bg-warning"><i class="fas fa-hourglass-half me-1"></i>Pendente de Recebimento</span>
                                                                <div class="small text-muted mt-1" style="font-size: 0.75rem;">Aguardando confirmação no destino</div>
                                                            @endif
                                                        </td>
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
                                        <p class="mb-0">Sem histórico de encaminhamentos internos.</p>
                                    </div>
                                @endif
                            </div>
                            <div class="percurso-tabela" data-tabela="tarefas" hidden>
        <div class="px-3 py-2 bg-body-tertiary border-bottom d-flex align-items-center justify-content-between">
            <h3 class="h6 fw-semibold mb-0 text-dark">Tarefas do Documento</h3>
            <button type="button" class="btn btn-sm btn-link text-decoration-none percurso-fechar-tabela">
                <i class="fas fa-xmark me-1"></i>Fechar
            </button>
        </div>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="fw-bold mb-0 text-dark">Lista de Tarefas</h6>
                                    @php($actor = Auth::user())
                                    @php($gab = optional($doc->departamento)->gabinete)
                                    @if ($canAssignTask)
                                        <button type="button" class="btn btn-primary btn-sm doc-header-action-btn" data-bs-toggle="modal" data-bs-target="#modalDesignarTarefa">
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
                                                                data-parecer="{{ e($t->resposta) }}"
                                                                data-criado="{{ $t->created_at->format('d/m/Y H:i') }}">
                                                                <span class="fw-semibold text-dark">{{ $t->titulo }}</span>
                                                                @if($t->descricao)
                                                                    <div class="small text-muted text-truncate" style="max-width: 250px;">{{ $t->descricao }}</div>
                                                                @endif
                                                            </a>
                                                            {{-- O parecer é o produto da tarefa: deixa de ficar só na base. --}}
                                                            @if (filled($t->resposta))
                                                                <div class="small text-success-emphasis mt-1" style="max-width: 250px;">
                                                                    <i class="fas fa-comment-dots me-1"></i>{{ Str::limit($t->resposta, 90) }}
                                                                </div>
                                                            @endif
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
                                                        <td><span class="badge text-bg-{{ $t->status === 'concluida' ? 'success' : ($t->status === 'cancelada' ? 'danger' : 'secondary') }}">{{ ucfirst($t->status) }}</span></td>
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
                                                            @php($canConcluir = $t->can_concluir ?? false)
                                                            @php($canCancelar = $t->can_cancelar ?? false)

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
                                                                    <input type="text" name="observacao" class="form-control form-control-sm d-inline-block w-auto me-1"
                                                                           style="min-width: 160px; max-width: 220px;" required
                                                                           placeholder="Parecer / conclusão…" aria-label="Parecer do técnico">
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
                                        <p class="mb-0">Nenhuma tarefa registrada para este documento.</p>
                                    </div>
                                @endif
                            </div>
                            <div class="percurso-tabela" data-tabela="externo" hidden>
        <div class="px-3 py-2 bg-body-tertiary border-bottom d-flex align-items-center justify-content-between">
            <h3 class="h6 fw-semibold mb-0 text-dark">Histórico de Saídas Externas</h3>
            <button type="button" class="btn btn-sm btn-link text-decoration-none percurso-fechar-tabela">
                <i class="fas fa-xmark me-1"></i>Fechar
            </button>
        </div>
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
                                        <p class="mb-0">Sem histórico de encaminhamentos externos.</p>
                                    </div>
                                @endif
                            </div>
                            <div class="percurso-tabela" data-tabela="vinculos" hidden>
        <div class="px-3 py-2 bg-body-tertiary border-bottom d-flex align-items-center justify-content-between">
            <h3 class="h6 fw-semibold mb-0 text-dark">Vínculos e Dossiê</h3>
            <button type="button" class="btn btn-sm btn-link text-decoration-none percurso-fechar-tabela">
                <i class="fas fa-xmark me-1"></i>Fechar
            </button>
        </div>
                                <x-documento-vinculos :documento="$doc" tipo="EXTERNO" />
                            </div>
                            @if ($canVerAuditoria ?? false)
                            <div class="percurso-tabela" data-tabela="auditoria" hidden>
        <div class="px-3 py-2 bg-body-tertiary border-bottom d-flex align-items-center justify-content-between">
            <h3 class="h6 fw-semibold mb-0 text-dark">Trilha de Auditoria & Modificações</h3>
            <button type="button" class="btn btn-sm btn-link text-decoration-none percurso-fechar-tabela">
                <i class="fas fa-xmark me-1"></i>Fechar
            </button>
        </div>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <h6 class="fw-bold mb-1 text-dark"><i class="fas fa-shield-alt text-primary me-2"></i>Trilha de Auditoria & Modificações</h6>
                                        <p class="text-muted small mb-0">Registo de integridade e rastreabilidade de todas as alterações feitas neste documento.</p>
                                    </div>
                                    @php($mostrados = isset($audits) ? $audits->count() : 0)
                                    @php($total = $auditsTotal ?? $mostrados)
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill"
                                          @if ($total > $mostrados) title="A listagem mostra os mais recentes; os restantes {{ $total - $mostrados }} estão no registo central de auditoria." @endif>
                                        @if ($total > $mostrados)
                                            {{ $mostrados }} de {{ $total }} registos
                                        @else
                                            {{ $total }} registos
                                        @endif
                                    </span>
                                </div>

                                @if (isset($audits) && $audits->count())
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle table-sm border">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 150px;">Data / Hora</th>
                                                    <th style="width: 170px;">Utilizador</th>
                                                    <th style="width: 110px;">Operação</th>
                                                    <th>Campos Alterados</th>
                                                    <th style="width: 130px;">Endereço IP</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($audits as $audit)
                                                    <tr>
                                                        <td>
                                                            <div class="fw-semibold">{{ $audit->created_at->format('d/m/Y H:i:s') }}</div>
                                                            <div class="text-muted small" style="font-size: 0.72rem;">{{ $audit->created_at->diffForHumans() }}</div>
                                                        </td>
                                                        <td>
                                                            <div class="fw-medium text-dark"><i class="fas fa-user-circle text-secondary me-1"></i>{{ optional($audit->user)->name ?? 'Sistema' }}</div>
                                                        </td>
                                                        <td>
                                                            @php($act = strtolower($audit->action))
                                                            @php($actBadge = match($act) {
                                                                'create' => 'bg-success',
                                                                'update' => 'bg-primary',
                                                                'delete' => 'bg-danger',
                                                                default => 'bg-secondary'
                                                            })
                                                            <span class="badge {{ $actBadge }} text-uppercase" style="font-size: 0.7rem;">
                                                                {{ $act }}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            @if ($audit->action === 'update' && is_array($audit->new_values))
                                                                <div class="d-flex flex-column gap-1 small">
                                                                    @foreach ($audit->new_values as $key => $newVal)
                                                                        @php($oldVal = $audit->old_values[$key] ?? '—')
                                                                        @if ($oldVal != $newVal)
                                                                            <div class="p-1 rounded bg-light border" style="font-size: 0.78rem;">
                                                                                <strong class="text-secondary">{{ $key }}:</strong>
                                                                                <span class="text-danger text-decoration-line-through me-1">{{ is_array($oldVal) ? json_encode($oldVal) : ($oldVal ?: 'vazio') }}</span>
                                                                                <i class="fas fa-arrow-right text-muted small mx-1"></i>
                                                                                <span class="text-success fw-semibold">{{ is_array($newVal) ? json_encode($newVal) : ($newVal ?: 'vazio') }}</span>
                                                                            </div>
                                                                        @endif
                                                                    @endforeach
                                                                </div>
                                                            @elseif($audit->action === 'create' && is_array($audit->new_values))
                                                                <span class="small text-muted">Registo inicial de criação com {{ count($audit->new_values) }} atributos.</span>
                                                            @else
                                                                <span class="small text-muted">—</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            <span class="text-muted small" style="font-size: 0.75rem;"><i class="fas fa-network-wired me-1"></i>{{ $audit->ip_address ?: '—' }}</span>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="text-center py-4 text-muted">
                                        <i class="fas fa-shield-alt fa-2x mb-2 opacity-50"></i>
                                        <p class="mb-0">Nenhum log de auditoria registrado para este documento.</p>
                                    </div>
                                @endif
                            </div>
                            @endif
</div>
