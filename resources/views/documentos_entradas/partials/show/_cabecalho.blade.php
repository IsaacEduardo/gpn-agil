{{--
    Cabeçalho compacto e fixo. Antes eram 8 botões/menus com o mesmo peso, em
    que "Despachar" competia com "Exportar". Passa a UMA ação primária,
    escolhida pelo estado do documento, e tudo o resto no menu ⋯.
--}}
@php
    $statusClass = match ($doc->status) {
        'registrado' => 'bg-info-subtle text-info border border-info-subtle',
        'pendente_tratamento' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
        'tratado' => 'bg-primary-subtle text-primary border border-primary-subtle',
        'recebido' => 'bg-success-subtle text-success border border-success-subtle',
        'arquivado', 'finalizado' => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
        default => 'bg-light text-dark border',
    };

    $podeDespachar = ($canDespachar ?? false) && in_array($doc->status, ['pendente_tratamento', 'registrado'], true);
    $podeEncaminharTratado = ($canEncaminharTratado ?? false) && $doc->status === 'tratado';
    $urlResposta = route('documentos-internos.create', ['documento_entrada_id' => $doc->id, 'tipo_relacao' => 'RESPOSTA']);

    $sla = $doc->sla_status;
@endphp

<div class="doc-topbar">
    <div class="d-flex align-items-center gap-2 flex-grow-1 min-w-0">
        <a href="{{ route('documentos-entradas.index') }}" class="btn btn-sm btn-link text-secondary px-1"
           title="Voltar à listagem de documentos" aria-label="Voltar à listagem de documentos">
            <i class="fas fa-arrow-left"></i>
        </a>

        <div class="min-w-0">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <h1 class="doc-topbar-numero mb-0">Nº {{ $doc->numero_sequencial }}/{{ $doc->ano_referencia }}</h1>

                <span class="badge {{ $statusClass }} rounded-pill fw-semibold text-uppercase doc-topbar-badge">
                    {{ str_replace('_', ' ', $doc->status) }}
                </span>

                @if ($sla === 'critical')
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill doc-topbar-badge"
                          title="{{ $doc->dias_decorridos }} dias decorridos, prazo de {{ $doc->prazo_tratamento_dias }} dias">
                        <i class="fas fa-exclamation-triangle me-1"></i>Prazo excedido
                    </span>
                @elseif ($sla === 'warning')
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill doc-topbar-badge"
                          title="{{ $doc->dias_decorridos }} de {{ $doc->prazo_tratamento_dias }} dias">
                        <i class="fas fa-clock me-1"></i>Perto do prazo
                    </span>
                @endif
            </div>

            <p class="doc-topbar-assunto mb-0" title="{{ $doc->assunto }}">
                <span class="text-muted">{{ $doc->classificacao_especie ?? 'Documento externo' }}@if ($doc->classificacao_ref_numero) · Ref. {{ $doc->classificacao_ref_numero }}@endif ·</span>
                {{ $doc->assunto }}
            </p>
        </div>
    </div>

    <div class="d-flex align-items-center gap-2 flex-shrink-0">
        {{-- Ação primária: a do momento do documento. --}}
        @if ($podeDespachar)
            <button class="btn btn-primary btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalDespacho{{ $doc->id }}">
                <i class="fas fa-file-signature me-1"></i> Despachar
            </button>
        @elseif ($podeEncaminharTratado)
            <form action="{{ route('documentos-entradas.encaminhar-tratado', $doc) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm fw-semibold"
                        onclick="return confirm('Confirma o encaminhamento deste documento tratado para os departamentos selecionados?')">
                    <i class="fas fa-paper-plane me-1"></i> Encaminhar p/ Destinos
                </button>
            </form>
        @else
            <a href="{{ $urlResposta }}" class="btn btn-outline-primary btn-sm fw-semibold">
                <i class="fas fa-reply me-1"></i> Elaborar Resposta / Parecer
            </a>
        @endif

        {{-- Tudo o resto. --}}
        <div class="dropdown">
            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="dropdown"
                    aria-expanded="false" aria-label="Mais ações sobre o documento" title="Mais ações">
                <i class="fas fa-ellipsis-vertical"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="min-width: 17rem;">
                @can('update', $doc)
                    <li>
                        <a href="{{ route('documentos-entradas.edit', $doc) }}" class="dropdown-item py-2">
                            <i class="fas fa-edit me-2 text-primary"></i> Editar Documento
                        </a>
                    </li>
                @endcan

                @if ($podeDespachar || $podeEncaminharTratado)
                    <li>
                        <a href="{{ $urlResposta }}" class="dropdown-item py-2">
                            <i class="fas fa-reply me-2 text-secondary"></i> Elaborar Resposta / Parecer
                        </a>
                    </li>
                @endif

                @if (! $doc->saida_gabinete_data && ! $hasPendente)
                    <li>
                        <button class="dropdown-item py-2" data-bs-toggle="modal" data-bs-target="#modalEncaminharDocumento">
                            <i class="fas fa-paper-plane me-2 text-secondary"></i> Encaminhar Documento
                        </button>
                    </li>
                    <li>
                        <button class="dropdown-item py-2" data-bs-toggle="modal" data-bs-target="#modalSaidaGabinete">
                            <i class="fas fa-sign-out-alt me-2 text-secondary"></i> Registrar Saída de Gabinete
                        </button>
                    </li>
                @endif

                <li><hr class="dropdown-divider my-1"></li>
                <li><h6 class="dropdown-header text-uppercase small fw-bold">Protocolo</h6></li>
                <li>
                    <a class="dropdown-item py-2 fw-semibold" target="_blank" rel="noopener"
                       href="{{ route('documentos-entradas.protocolo.etiqueta', [$doc, 'auto_print' => 1]) }}">
                        <i class="fas fa-barcode me-2 text-success"></i> Imprimir Etiqueta Adesiva (100x50mm)
                    </a>
                </li>
                <li>
                    <a class="dropdown-item py-2" target="_blank" rel="noopener"
                       href="{{ route('documentos-entradas.protocolo.pdf', $doc) }}">
                        <i class="fas fa-file-pdf me-2 text-primary"></i> Imprimir Comprovativo A4
                    </a>
                </li>
                <li>
                    <a class="dropdown-item py-2" target="_blank" rel="noopener"
                       href="{{ route('documentos-entradas.protocolo', $doc) }}">
                        <i class="fas fa-eye me-2 text-secondary"></i> Visualizar Protocolo Completo
                    </a>
                </li>

                <li><hr class="dropdown-divider my-1"></li>
                <li><h6 class="dropdown-header text-uppercase small fw-bold">Exportar</h6></li>
                <li>
                    <a class="dropdown-item py-2" href="{{ route('documentos-entradas.export.pdf', ['departamento_id' => $doc->departamento_id, 'ano' => $doc->ano_referencia]) }}">
                        <i class="fas fa-file-pdf me-2 text-danger"></i> Relatório PDF
                    </a>
                </li>
                <li>
                    <a class="dropdown-item py-2" href="{{ route('documentos-entradas.export.excel', ['departamento_id' => $doc->departamento_id, 'ano' => $doc->ano_referencia]) }}">
                        <i class="fas fa-file-excel me-2 text-success"></i> Planilha Excel
                    </a>
                </li>

                @if (config('app.feature_assistente') && auth()->user()?->can('assistente.usar'))
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <button type="button" class="dropdown-item py-2"
                                onclick="window.gerarResumoIaDocWidget ? window.gerarResumoIaDocWidget({{ $doc->id }}, 'ENTRADA') : null">
                            <i class="fas fa-brain me-2 text-primary"></i> ✨ Resumir com IA
                        </button>
                    </li>
                @endif

                @if (! $doc->arquivado)
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <button class="dropdown-item py-2 text-danger" data-bs-toggle="modal" data-bs-target="#modalArquivarDocumento">
                            <i class="fas fa-archive me-2"></i> Arquivar Documento
                        </button>
                    </li>
                @endif
            </ul>
        </div>
    </div>
</div>
