                
                <!-- Status de Aprovação (Vistos) - Compact & Clean -->
                <div class="card shadow-sm border-0 mb-4 rounded-3">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="card-title mb-0 fw-bold text-dark d-flex align-items-center gap-2 fs-6">
                            <i class="fas fa-clipboard-check text-primary"></i>
                            <span>Status de Aprovação</span>
                        </h6>
                    </div>
                    <div class="card-body p-3 d-flex flex-column gap-3">
                        <!-- Department Visto -->
                        @php($actor = Auth::user())
                        @php($stDep = $doc->visto_departamento_status ?? 'pendente')
                        @php($depBadge = match($stDep) {
                            'aprovado' => 'bg-success-subtle text-success border border-success-subtle',
                            'rejeitado' => 'bg-danger-subtle text-danger border border-danger-subtle',
                            default => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle'
                        })
                        @php($depIcon = match($stDep) {
                            'aprovado' => 'fa-check-circle text-success',
                            'rejeitado' => 'fa-times-circle text-danger',
                            default => 'fa-clock text-warning'
                        })
                        
                        <div class="approval-mini-card is-{{ $stDep }}">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold text-dark small text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.4px;">Chefe do Departamento</span>
                                <span class="badge {{ $depBadge }} rounded-pill text-uppercase px-2 py-0.5" style="font-size: 0.68rem;">
                                    <i class="fas {{ $depIcon }} me-1"></i> {{ ucfirst($stDep) }}
                                </span>
                            </div>
                            <div class="small text-muted">
                                {{ optional($doc->vistoDepartamentoPor)->name ?? 'Aguardando validação' }}
                                @if($doc->visto_departamento_data)
                                    • {{ $doc->visto_departamento_data->format('d/m H:i') }}
                                @endif
                            </div>
                            
                            @if ($doc->visto_departamento_observacao)
                                <div class="mt-1.5 small text-muted border-top pt-1" style="font-size: 0.76rem;">
                                    <i class="fas fa-comment-dots {{ $stDep === 'rejeitado' ? 'text-danger' : 'text-secondary' }} me-1"></i> {{ $doc->visto_departamento_observacao }}
                                </div>
                            @endif

                            @if ($canVisto && !$doc->saida_gabinete_data && $stDep === 'pendente')
                                <div class="mt-2.5 d-flex gap-2 pt-2 border-top">
                                    <form id="visto-dep-aprovar-form" action="{{ route('documentos-entradas.visto.aprovar', $doc) }}" method="POST" class="w-100">
                                        @csrf
                                        @method('PATCH')
                                        <button type="button" id="btnVistoDepartamentoAprovar" class="btn btn-success btn-sm w-100 doc-header-action-btn"><i class="fas fa-check"></i> <span>Aprovar</span></button>
                                    </form>
                                    <button type="button" class="btn btn-outline-danger btn-sm w-100 doc-header-action-btn" data-bs-toggle="modal" data-bs-target="#vistoRejeitarModal"><i class="fas fa-times"></i> <span>Rejeitar</span></button>
                                </div>
                            @endif
                        </div>

                        <!-- Gabinete Visto -->
                        @php($gab = optional($doc->departamento)->gabinete)
                        @php($stGab = $doc->visto_gabinete_status ?? 'pendente')
                        @php($gabBadge = match($stGab) {
                            'aprovado' => 'bg-success-subtle text-success border border-success-subtle',
                            'rejeitado' => 'bg-danger-subtle text-danger border border-danger-subtle',
                            default => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle'
                        })
                        @php($gabIcon = match($stGab) {
                            'aprovado' => 'fa-check-circle text-success',
                            'rejeitado' => 'fa-times-circle text-danger',
                            default => 'fa-clock text-warning'
                        })

                        <div class="approval-mini-card is-{{ $stGab }}">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold text-dark small text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.4px;">Gabinete</span>
                                <span class="badge {{ $gabBadge }} rounded-pill text-uppercase px-2 py-0.5" style="font-size: 0.68rem;">
                                    <i class="fas {{ $gabIcon }} me-1"></i> {{ ucfirst($stGab) }}
                                </span>
                            </div>
                            <div class="small text-muted">
                                {{ optional($doc->vistoGabinetePor)->name ?? 'Aguardando visto' }}
                                @if($doc->visto_gabinete_data)
                                    • {{ $doc->visto_gabinete_data->format('d/m H:i') }}
                                @endif
                            </div>

                            @if ($doc->visto_gabinete_observacao)
                                <div class="mt-1.5 small text-muted border-top pt-1" style="font-size: 0.76rem;">
                                    <i class="fas fa-comment-dots {{ $stGab === 'rejeitado' ? 'text-danger' : 'text-secondary' }} me-1"></i> {{ $doc->visto_gabinete_observacao }}
                                </div>
                            @endif

                            @if ($canVistoGabinete && !$doc->saida_gabinete_data && $stGab === 'pendente')
                                <div class="mt-2.5 d-flex gap-2 pt-2 border-top">
                                    <form id="visto-gab-aprovar-form" action="{{ route('documentos-entradas.visto-gabinete.aprovar', $doc) }}" method="POST" class="w-100">
                                        @csrf
                                        @method('PATCH')
                                        <button type="button" id="btnVistoGabineteAprovar" class="btn btn-success btn-sm w-100 doc-header-action-btn"><i class="fas fa-check"></i> <span>Aprovar</span></button>
                                    </form>
                                    <button type="button" class="btn btn-outline-danger btn-sm w-100 doc-header-action-btn" data-bs-toggle="modal" data-bs-target="#vistoGabineteRejeitarModal"><i class="fas fa-times"></i> <span>Rejeitar</span></button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                @if (config('app.feature_assistente') && auth()->user()?->can('assistente.usar'))
                    @include('assistente._automacao', [
                        'doc' => $doc,
                        'departamentos' => $departamentos
                    ])
                @endif
