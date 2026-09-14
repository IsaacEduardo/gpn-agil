                
                <!-- Hero Document Identity & Metadata Card -->
                <div class="card shadow-sm border-0 mb-4 rounded-3">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary-subtle text-primary rounded-circle p-1.5"><i class="fas fa-file-alt"></i></span>
                            <h5 class="card-title mb-0 fw-bold text-dark fs-6">Informações do Documento</h5>
                        </div>
                        @if ($doc->procedencia)
                            <span class="badge bg-light text-dark border px-2.5 py-1 text-truncate" style="max-width: 320px;" title="{{ $doc->procedencia }}">
                                <i class="fas fa-building text-secondary me-1"></i> Origem: <strong>{{ $doc->procedencia }}</strong>
                            </span>
                        @endif
                    </div>
                    <div class="card-body p-4">
                        <!-- Hero Subject Banner -->
                        <div class="p-3 mb-3 rounded-3 bg-light border-start border-4 border-primary shadow-2xs">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-uppercase small text-muted fw-bold" style="font-size: 0.68rem; letter-spacing: 0.5px;">
                                    <i class="fas fa-align-left text-secondary me-1"></i> Assunto / Objeto do Documento
                                </span>
                                @if ($doc->tags->count())
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach ($doc->tags as $tag)
                                            <span class="badge bg-white text-secondary border rounded-pill" style="font-size: 0.7rem;"><i class="fas fa-tag text-muted me-1"></i>{{ $tag->nome }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <div class="fs-6 fw-bold text-dark lh-base">
                                {{ $doc->assunto }}
                            </div>
                        </div>


                        <!-- Metadata Grid (Structured Specifications) -->
                        <div class="doc-metadata-grid mb-3">
                            <div class="doc-meta-item">
                                <span class="doc-meta-label"><i class="far fa-calendar-alt text-secondary me-1"></i> Data de Entrada</span>
                                <div class="doc-meta-value">{{ optional($doc->data_entrada)->format('d/m/Y') }}</div>
                            </div>

                            <div class="doc-meta-item">
                                <span class="doc-meta-label"><i class="fas fa-layer-group text-secondary me-1"></i> Espécie & Ref. Nº</span>
                                <div class="doc-meta-value">
                                    {{ $doc->classificacao_especie ?? '—' }}
                                    @if ($doc->classificacao_ref_numero)
                                        • <span class="text-primary">{{ $doc->classificacao_ref_numero }}</span>
                                    @endif
                                </div>
                            </div>

                            <div class="doc-meta-item">
                                <span class="doc-meta-label"><i class="far fa-calendar-check text-secondary me-1"></i> Data do Documento</span>
                                <div class="doc-meta-value">{{ optional($doc->data_documento)->format('d/m/Y') ?? '—' }}</div>
                            </div>

                            <div class="doc-meta-item">
                                <span class="doc-meta-label"><i class="fas fa-map-marker-alt text-secondary me-1"></i> Departamento Atual</span>
                                <div class="doc-meta-value">{{ optional($doc->departamento)->nome ?? '—' }}</div>
                            </div>

                            <div class="doc-meta-item">
                                <span class="doc-meta-label"><i class="fas fa-user-edit text-secondary me-1"></i> Registrado por</span>
                                <div class="doc-meta-value">{{ optional($doc->usuario)->name ?? '—' }}</div>
                            </div>

                            @if ($doc->saida_gabinete_data || $doc->encaminhamento_orgao || $doc->encaminhamento_data)
                                <div class="doc-meta-item">
                                    <span class="doc-meta-label"><i class="fas fa-share-square text-secondary me-1"></i> Saída / Encaminhamento</span>
                                    <div class="doc-meta-value">
                                        @if ($doc->saida_gabinete_data)
                                            <div>Saída Gab: {{ optional($doc->saida_gabinete_data)->format('d/m/Y') }}</div>
                                        @endif
                                        @if ($doc->encaminhamento_orgao)
                                            <div>{{ $doc->encaminhamento_orgao }} @if($doc->encaminhamento_oficio_numero)— Of. {{ $doc->encaminhamento_oficio_numero }}@endif</div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- Observações -->
                        @if ($doc->observacoes)
                            <div class="p-2.5 rounded bg-light border text-muted small mb-3">
                                <strong class="text-secondary text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.5px;"><i class="fas fa-info-circle me-1"></i> Observações:</strong>
                                <div class="fst-italic mt-0.5">{{ $doc->observacoes }}</div>
                            </div>
                        @endif

                        <!-- Despacho / Parecer do Gabinete -->
                        @if ($doc->texto_despacho || $doc->data_despacho)
                            <div class="card border-primary border-opacity-25 shadow-2xs rounded-3 overflow-hidden mt-3">
                                <div class="card-header bg-primary text-white d-flex align-items-center justify-content-between py-2 px-3">
                                    <span class="fw-bold small"><i class="fas fa-file-signature me-1.5"></i> Despacho / Parecer do Gabinete</span>
                                    @if ($doc->data_despacho)
                                        <span class="badge bg-white text-primary fw-medium">{{ optional($doc->data_despacho)->format('d/m/Y H:i') }}</span>
                                    @endif
                                </div>
                                <div class="card-body p-3 bg-light bg-opacity-40">
                                    <div class="fw-medium text-dark mb-2" style="white-space: pre-line; line-height: 1.5;">
                                        {{ $doc->texto_despacho }}
                                    </div>
                                    <div class="d-flex flex-wrap align-items-center justify-content-between border-top pt-2 mt-2 small text-muted" style="font-size: 0.78rem;">
                                        <div>
                                            <i class="fas fa-user-check me-1 text-primary"></i> <strong>Despachado por:</strong> {{ optional($doc->despachadoPor)->name ?? 'Chefe de Gabinete' }}
                                        </div>
                                        <div>
                                            <i class="fas fa-building me-1 text-primary"></i> <strong>Destinatários:</strong>
                                            @forelse ($doc->departamentosDestino as $destDep)
                                                <span class="badge bg-primary-subtle text-primary rounded-pill ms-1">{{ $destDep->nome }}</span>
                                            @empty
                                                <span class="badge bg-secondary-subtle text-secondary rounded-pill ms-1">{{ optional($doc->departamento)->nome }}</span>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
