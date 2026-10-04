@extends('layouts.app')

@section('content')
    <div class="container">
        @if (! $documentoInterno->assinado_em
            && \App\Support\SeriesNumeracao::abreviaturaEspecie($documentoInterno->especie) === 'NOTA'
            && ! $documentoInterno->departamento?->chefeDesignado())
            <div class="alert alert-warning d-print-none">
                <i class="fas fa-user-slash me-1"></i>
                Este departamento não tem Chefe de Departamento designado: a Nota não pode ser assinada até ser designado na Gestão de Utilizadores.
            </div>
        @endif

        <div class="d-flex justify-content-between align-items-center mb-4 d-print-none">
            <div>
                <a href="{{ route('documentos-internos.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Voltar
                </a>
            </div>
            <div>
                @if (config('app.feature_assistente') && auth()->user()?->can('assistente.usar'))
                    <button type="button" class="btn btn-primary me-2 fw-semibold shadow-xs" onclick="window.gerarResumoIaDocWidget ? window.gerarResumoIaDocWidget({{ $documentoInterno->id }}, 'INTERNO') : null">
                        <i class="fas fa-brain me-1"></i> ✨ Resumir com IA
                    </button>
                @endif

                {{-- Rascunho: autor e chefia; em análise: só a chefia (DocumentoInternoPolicy::update).
                     O estado verifica-se aqui também porque o Gate deixa o admin passar em tudo. --}}
                @php
                    $statusDoc = $documentoInterno->status->value;
                    $podeAprovar = $statusDoc === 'em_analise' && auth()->user()->can('approve', $documentoInterno);
                    $podeDevolver = in_array($statusDoc, ['em_analise', 'aprovado'], true) && !$documentoInterno->assinado_em
                        && auth()->user()->can('reject', $documentoInterno);
                @endphp
                {{-- Colaboração: também para os convidados, que não passam no 'update' (autor e
                     chefia) e só chegavam ao editor pelo link da notificação do convite. --}}
                @php($nivelColab = config('app.feature_collab') && auth()->user()->can('collaborate', $documentoInterno)
                    ? app(\App\Services\DocumentoCollaborationService::class)->nivelDe(auth()->user(), $documentoInterno)
                    : null)
                @if ($nivelColab)
                    <a href="{{ route('documentos-internos.collab.editor', $documentoInterno) }}" class="btn btn-outline-primary me-2">
                        <i class="fas fa-users me-2"></i>{{ $nivelColab->podeEditar() ? 'Editar em colaboração' : 'Abrir em colaboração' }}
                    </a>
                @endif
                @if ($documentoInterno->aceitaEdicao() && auth()->user()->can('update', $documentoInterno))
                    <a href="{{ route('documentos-internos.edit', $documentoInterno->id) }}" class="btn btn-primary me-2">
                        <i class="fas fa-edit me-2"></i>Editar
                    </a>

                    @if ($documentoInterno->status->value === 'rascunho')
                        <form action="{{ route('documentos-internos.submit', $documentoInterno->id) }}" method="POST"
                            class="d-inline"
                            onsubmit="return confirm('Enviar documento para análise? A partir daí só a chefia o pode editar.');">
                            @csrf
                            <button type="submit" class="btn btn-warning me-2 text-dark">
                                <i class="fas fa-paper-plane me-2"></i>Enviar p/ Análise
                            </button>
                        </form>
                    @endif
                @endif

                @if ($podeAprovar || $podeDevolver)
                    <div class="btn-group me-2">
                        @if ($podeAprovar)
                            <form action="{{ route('documentos-internos.approve', $documentoInterno->id) }}" method="POST"
                                class="d-inline"
                                onsubmit="return confirm('Aprovar este documento? Ele ficará disponível para assinatura.');">
                                @csrf
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check me-2"></i>Aprovar
                                </button>
                            </form>
                        @endif
                        @if ($podeDevolver)
                            <button type="button" class="btn btn-danger ms-1" data-bs-toggle="modal"
                                data-bs-target="#rejectModal">
                                <i class="fas fa-times me-2"></i>Devolver
                            </button>
                        @endif
                    </div>
                @endif

                <a href="{{ route('documentos-internos.pdf', $documentoInterno->id) }}" class="btn btn-danger me-2">
                    <i class="fas fa-file-pdf me-2"></i>Baixar PDF
                </a>
                {{-- Imprime o PDF oficial, não esta página: sai igual ao descarregado,
                     sem o menu do sistema nem a data/endereço que o browser junta. --}}
                <button type="button" class="btn btn-dark" id="btnImprimirDocumento"
                    data-pdf-url="{{ route('documentos-internos.pdf', [$documentoInterno->id, 'imprimir' => 1]) }}">
                    <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                    <i class="fas fa-print me-2"></i>Imprimir
                </button>

                @inject('signatureService', 'App\Services\SignatureService')
                @if (!$documentoInterno->assinado_em && $signatureService->canSign($documentoInterno, auth()->user()))
                    <button class="btn btn-success ms-2" data-bs-toggle="modal" data-bs-target="#signModal">
                        <i class="fas fa-file-signature me-2"></i>Assinar
                    </button>
                @endif

                @if ($documentoInterno->status === \App\Enums\DocumentoStatus::ASSINADO && !$documentoInterno->arquivado && auth()->user()->can('archive', $documentoInterno))
                    <button class="btn btn-secondary ms-2" data-bs-toggle="modal" data-bs-target="#modalArquivarInterno">
                        <i class="fas fa-archive me-2"></i>Arquivar
                    </button>
                @elseif($documentoInterno->arquivado)
                    <span class="badge bg-secondary ms-2 p-2">
                        <i class="fas fa-archive me-1"></i> Arquivado
                    </span>
                @endif
            </div>
        </div>

        {{-- Reject Modal --}}
        <div class="modal fade" id="rejectModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('documentos-internos.reject', $documentoInterno->id) }}" method="POST">
                        @csrf
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title">Devolver Documento</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Descreva o motivo da devolução para que o autor possa corrigir:</p>
                            <div class="mb-3">
                                <label class="form-label">Motivo / Observações</label>
                                <textarea name="motivo" class="form-control" rows="3" required placeholder="Ex: Ajustar parágrafo 2..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-danger">Confirmar Devolução</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Arquivar Modal --}}
        <div class="modal fade" id="modalArquivarInterno" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('pastas.arquivar', $documentoInterno->id) }}" method="POST">
                        @csrf
                        <input type="hidden" name="tipo" value="interno">
                        <div class="modal-header">
                            <h5 class="modal-title">Arquivar Documento Interno</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Selecione a pasta onde deseja arquivar este documento.</p>
                            <div class="mb-3">
                                <label class="form-label">Pasta</label>
                                <select name="pasta_id" class="form-select" required>
                                    <option value="">Selecione uma pasta...</option>
                                    @inject('pastaService', 'App\Services\PastaService')
                                    @foreach ($pastaService->getFolderTreeOptions(auth()->user()) as $id => $nome)
                                        <option value="{{ $id }}">{{ $nome }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Arquivar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Sign Modal --}}
        <div class="modal fade" id="signModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('documentos-internos.sign', $documentoInterno->id) }}" method="POST">
                        @csrf
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title"><i class="fas fa-file-signature me-2"></i>Assinar Digitalmente</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Você está prestes a assinar este documento digitalmente. Esta ação é irrevogável e garantirá
                                a integridade do conteúdo.</p>
                            <div class="alert alert-info">
                                <small><i class="fas fa-info-circle"></i> Ao assinar, você declara estar de acordo com o
                                    conteúdo deste documento.</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Confirme sua senha</label>
                                <input type="password" name="password" class="form-control" required
                                    placeholder="Sua senha de login">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Senha do certificado digital</label>
                                <input type="password" name="certificate_password" class="form-control"
                                    autocomplete="off" placeholder="Preencha apenas se o seu certificado tiver senha">
                                <small class="text-muted">Não é armazenada — solicitada apenas no momento de assinar.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-success"><i
                                    class="fas fa-pen-nib me-2"></i>Assinar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-10">
                <!-- A4 Paper Container -->
                <div class="paper-container shadow-lg">
                    @include('documentos_internos.partials.paper')
                </div>

                @if ($documentoInterno->documentoEntrada)
                    <div class="alert alert-info mt-4 d-print-none">
                        <strong>Documento Relacionado:</strong>
                        Este documento é uma resposta/referência ao documento de entrada:
                        <a href="{{ route('documentos-entradas.show', $documentoInterno->documentoEntrada) }}">
                            {{ $documentoInterno->documentoEntrada->numero_sequencial }}/{{ $documentoInterno->documentoEntrada->ano_referencia }}
                            - {{ $documentoInterno->documentoEntrada->assunto }}
                        </a>
                    </div>
                @endif

                <!-- Trilha de Auditoria e Vínculos -->
                <div class="card mt-4 d-print-none border-0 shadow-sm">
                    <div class="card-header bg-light fw-bold">
                        <ul class="nav nav-tabs card-header-tabs" id="docTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="vinculos-tab" data-bs-toggle="tab"
                                    data-bs-target="#vinculosTabPane" type="button" role="tab"><i
                                        class="fas fa-link me-2"></i>Vínculos & Dossiê</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="audit-tab" data-bs-toggle="tab"
                                    data-bs-target="#audit" type="button" role="tab"><i
                                        class="fas fa-history me-2"></i>Auditoria</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="versions-tab" data-bs-toggle="tab"
                                    data-bs-target="#versions" type="button" role="tab"><i
                                        class="fas fa-code-branch me-2"></i>Versões
                                    Anteriores</button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body p-0">
                        <div class="tab-content" id="docTabsContent">
                            <!-- Vínculos Tab -->
                            <div class="tab-pane fade show active p-3 p-md-4" id="vinculosTabPane" role="tabpanel">
                                <x-documento-vinculos :documento="$documentoInterno" tipo="INTERNO" />
                            </div>

                            <!-- Auditoria Tab -->
                            <div class="tab-pane fade" id="audit" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-3">Data/Hora</th>
                                                <th>Usuário</th>
                                                <th>Ação</th>
                                                <th>IP</th>
                                                <th>O que fez</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($documentoInterno->audits as $audit)
                                                {{-- Quem fez o quê, em português (App\Support\DescricaoAuditoria). --}}
                                                @php($descricao = \App\Support\DescricaoAuditoria::de($audit))
                                                <tr>
                                                    <td class="ps-3 text-muted small">
                                                        {{ $audit->created_at->format('d/m/Y H:i:s') }}</td>
                                                    <td class="fw-medium">{{ $audit->user->name ?? 'Sistema' }}</td>
                                                    <td>
                                                        <span class="badge bg-{{ $descricao['cor'] }}-subtle text-dark border">
                                                            {{ $descricao['acao'] }}
                                                        </span>
                                                    </td>
                                                    <td class="small text-muted">{{ $audit->ip_address }}</td>
                                                    <td class="small">
                                                        {{ $descricao['detalhe'] }}
                                                        @if ($audit->action === 'sign' && isset($audit->new_values['hash']))
                                                            Hash: <span
                                                                class="font-monospace">{{ substr($audit->new_values['hash'], 0, 10) }}...</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="text-center py-3 text-muted">Nenhum registro
                                                        de auditoria encontrado.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Versions Tab -->
                            <div class="tab-pane fade" id="versions" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-3">Versão</th>
                                                <th>Data</th>
                                                <th>Editado Por</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($documentoInterno->versoes as $versao)
                                                <tr>
                                                    <td class="ps-3 fw-bold">v{{ $versao->versao }}</td>
                                                    <td class="text-muted small">
                                                        {{ $versao->created_at->format('d/m/Y H:i') }}</td>
                                                    <td>
                                                        {{ $versao->autor->name ?? 'Desconhecido' }}
                                                        {{-- Edição colaborativa: quem mais escreveu nesta versão. --}}
                                                        @if ($outros = $versao->nomesDosContribuidores())
                                                            <div class="small text-muted">com {{ implode(', ', $outros) }}</div>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#versionModal{{ $versao->id }}">
                                                            <i class="fas fa-eye"></i> Ver Conteúdo
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary ms-1 btn-diff-versao"
                                                            data-url="{{ route('documentos-internos.versoes.diff', [$documentoInterno->id, $versao->versao]) }}"
                                                            data-versao="{{ $versao->versao }}">
                                                            <i class="fas fa-code-compare"></i> O que mudou
                                                        </button>

                                                        @if ($documentoInterno->status->value === 'rascunho')
                                                            <form
                                                                action="{{ route('documentos-internos.restore', ['documentoInterno' => $documentoInterno->id, 'version' => $versao->versao]) }}"
                                                                method="POST" class="d-inline"
                                                                onsubmit="return confirm('Tem certeza? O conteúdo atual será salvo como uma nova versão antes de restaurar.');">
                                                                @csrf
                                                                <button type="submit"
                                                                    class="btn btn-sm btn-outline-warning ms-1">
                                                                    <i class="fas fa-history"></i> Restaurar
                                                                </button>
                                                            </form>
                                                        @endif

                                                        <!-- Modal -->
                                                        <div class="modal fade" id="versionModal{{ $versao->id }}"
                                                            tabindex="-1"
                                                            aria-labelledby="versionModalLabel{{ $versao->id }}"
                                                            aria-hidden="true">
                                                            <div class="modal-dialog modal-xl">
                                                                <div class="modal-content">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title"
                                                                            id="versionModalLabel{{ $versao->id }}">
                                                                            Versão {{ $versao->versao }} -
                                                                            {{ $versao->created_at->format('d/m/Y H:i') }}
                                                                        </h5>
                                                                        <button type="button" class="btn-close"
                                                                            data-bs-dismiss="modal"
                                                                            aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body bg-light">
                                                                        <div class="mx-auto bg-white p-5 shadow-sm"
                                                                            style="max-width: 210mm; min-height: 297mm; font-family: 'Times New Roman', serif;">
                                                                            <div class="paper-content">
                                                                                {!! \App\Models\DocumentoInterno::htmlParaApresentacao($versao->conteudo_final) !!}
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="4" class="text-center py-3 text-muted">Nenhuma versão
                                                        anterior encontrada. Esta é a versão inicial
                                                        (v{{ $documentoInterno->versao_atual }})
                                                        .</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                {{-- Diferenças de uma versão para a anterior (DocumentoVersaoController::diff). --}}
                                <div class="modal fade" id="modalDiffVersao" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-xl modal-dialog-scrollable">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="modalDiffVersaoTitulo">O que mudou</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="small text-muted mb-2">
                                                    <ins class="diff-legenda">texto acrescentado</ins> ·
                                                    <del class="diff-legenda">texto removido</del>
                                                </div>
                                                <div id="modalDiffVersaoCorpo" class="diff-versao" style="font-family: 'Times New Roman', serif;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Screen View - A4 Simulation */
        .paper-container {
            background: white;
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 20mm 20mm 35mm 30mm; /* Superior 2cm, Direita 2cm, Inferior 3.5cm, Esquerda 3cm */
            position: relative;
            box-sizing: border-box;
        }

        .paper-content {
            font-family: 'Times New Roman', Times, serif;
            /* Official Serif Font */
            font-size: 12pt;
            line-height: 1.5;
            color: #000;
        }

        .paper-content table {
            width: 100%;
            border-collapse: collapse;
        }

        /* <p> criado pelo editor colaborativo dentro das células. */
        .paper-content td > p,
        .paper-content th > p {
            margin: 0;
        }

        .paper-content img {
            max-width: 100%;
            height: auto;
        }

        /* Visual aid for page breaks on screen */
        .page-break {
            border-bottom: 2px dashed #ccc;
            margin: 20px 0;
            position: relative;
            display: block;
            height: 1px;
        }

        .page-break::after {
            content: 'Quebra de Página';
            position: absolute;
            right: 0;
            top: -20px;
            color: #999;
            font-size: 9pt;
            font-family: sans-serif;
        }

        .paper-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            padding: 0;
            z-index: 10;
        }

        body {
            background-color: #f0f2f5;
            /* Contrast background for paper effect */
        }

        /* Print View */
        @media print {
            @page {
                size: A4;
                margin: 20mm 20mm 35mm 30mm; /* Margens oficiais: Superior 2cm, Direita 2cm, Inferior 3.5cm, Esquerda 3cm */
            }

            html, body {
                background: none !important;
                margin: 0 !important;
                padding: 0 !important;
                min-height: auto !important;
                height: auto !important;
                overflow: visible !important;
            }

            /* Hide System Elements. gov-* é o layout actual: sem eles, um Ctrl+P
               imprimia o cabeçalho e o menu do sistema por cima do documento. */
            .gov-header,
            .gov-nav,
            .gov-footer,
            .sidebar,
            .topbar,
            footer,
            .d-print-none,
            .toast-container,
            #mobileSidebar,
            .d-lg-none {
                display: none !important;
            }

            /* Reset Containers */
            .main-wrapper,
            .container,
            .container-fluid,
            .row,
            .col-md-10,
            .content-area,
            main {
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                display: block !important;
                background: none !important;
                position: static !important;
                overflow: visible !important;
                min-height: auto !important;
                height: auto !important;
            }

            .paper-container {
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important; /* Margens são controladas pela @page */
                box-shadow: none !important;
                border: none !important;
                background: none !important;
                min-height: auto !important;
                height: auto !important;
                position: static !important;
            }

            .paper-footer {
                position: fixed !important;
                bottom: -30mm !important; /* Posicionado dentro da margem inferior de 35mm */
                left: 0 !important;
                width: 100% !important;
                height: 30mm !important;
                z-index: 9999 !important;
            }

            /* Ensure background graphics (like footer image) are printed */
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .page-break {
                border: none !important;
                margin: 0 !important;
                page-break-after: always !important;
                height: 0 !important;
            }

            .page-break::after {
                display: none !important;
            }
        }
    </style>

    @if (config('app.feature_assistente') && auth()->user()?->can('assistente.usar'))
        @include('assistente._documento', [
            'docId' => $documentoInterno->id,
            'docTipo' => 'INTERNO',
            'ctxRoute' => route('assistente.interno', $documentoInterno),
            'ctxTitulo' => 'este documento interno',
        ])
    @endif

    <style>
        .diff-versao ins, ins.diff-legenda { background: #d1fae5; color: #065f46; text-decoration: none; }
        .diff-versao del, del.diff-legenda { background: #fee2e2; color: #991b1b; }
    </style>
    <script>
        // Histórico: carrega as diferenças da versão para a anterior.
        document.querySelectorAll('.btn-diff-versao').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const corpo = document.getElementById('modalDiffVersaoCorpo');
                document.getElementById('modalDiffVersaoTitulo').textContent = 'O que mudou na v' + btn.dataset.versao;
                corpo.innerHTML = '<p class="text-muted">A comparar…</p>';
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDiffVersao')).show();
                fetch(btn.dataset.url, { headers: { Accept: 'application/json' } })
                    .then((r) => r.ok ? r.json() : Promise.reject())
                    // O HTML vem do servidor com todo o texto escapado (App\Support\DiffDocumento).
                    .then((d) => { corpo.innerHTML = d.html; })
                    .catch(() => { corpo.innerHTML = '<p class="text-danger">Não foi possível comparar as versões.</p>'; });
            });
        });

        // Imprimir manda à impressora o PDF oficial (o mesmo do "Baixar PDF"), sem o
        // descarregar: carrega-o num iframe invisível e abre o diálogo sobre ele —
        // a técnica da etiqueta do protocolo. A página web impressa levava o menu
        // do sistema e a data/endereço que o browser junta às páginas HTML.
        (function () {
            const botao = document.getElementById('btnImprimirDocumento');
            if (!botao) return;

            const url = botao.dataset.pdfUrl;
            const spinner = botao.querySelector('.spinner-border');
            let frame = null;
            let carregado = false;

            const ocupado = (sim) => {
                botao.disabled = sim;
                spinner.classList.toggle('d-none', !sim);
            };

            // Sem leitor de PDF no browser, ou se ele recusar imprimir a partir do
            // iframe, o PDF abre num separador e imprime-se de lá.
            const abrirNumSeparador = () => {
                ocupado(false);
                window.open(url, '_blank', 'noopener');
            };

            const imprimir = () => {
                try {
                    frame.contentWindow.focus();
                    frame.contentWindow.print();
                    ocupado(false);
                } catch (e) {
                    abrirNumSeparador();
                }
            };

            botao.addEventListener('click', function () {
                if (navigator.pdfViewerEnabled === false) {
                    abrirNumSeparador();
                    return;
                }

                if (frame && carregado) {
                    imprimir();
                    return;
                }

                ocupado(true);
                frame = document.createElement('iframe');
                frame.title = 'Documento em PDF para impressão';
                // Invisível mas desenhado: com display:none o leitor de PDF não carrega.
                frame.style.cssText = 'position:fixed; right:0; bottom:0; width:0; height:0; border:0; visibility:hidden;';
                frame.addEventListener('load', function () {
                    carregado = true;
                    // Folga para o leitor de PDF acabar de desenhar as páginas.
                    setTimeout(imprimir, 500);
                }, { once: true });
                frame.src = url;
                document.body.appendChild(frame);
            });
        })();
    </script>
@endsection
