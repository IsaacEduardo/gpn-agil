@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4 d-print-none">
            <div>
                <a href="{{ route('documentos-internos.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Voltar
                </a>
            </div>
            <div>
                @if ($documentoInterno->status->value === 'rascunho')
                    <a href="{{ route('documentos-internos.edit', $documentoInterno->id) }}" class="btn btn-primary me-2">
                        <i class="fas fa-edit me-2"></i>Editar
                    </a>

                    <form action="{{ route('documentos-internos.submit', $documentoInterno->id) }}" method="POST"
                        class="d-inline"
                        onsubmit="return confirm('Enviar documento para análise? Você não poderá editá-lo até que seja devolvido.');">
                        @csrf
                        <button type="submit" class="btn btn-warning me-2 text-dark">
                            <i class="fas fa-paper-plane me-2"></i>Enviar p/ Análise
                        </button>
                    </form>
                @endif

                @if ($documentoInterno->status->value === 'em_analise')
                    {{-- Simulação de permissão: Na prática usar @can('approve', $doc) ou verificar cargo --}}
                    <div class="btn-group me-2">
                        <form action="{{ route('documentos-internos.approve', $documentoInterno->id) }}" method="POST"
                            class="d-inline"
                            onsubmit="return confirm('Aprovar este documento? Ele ficará disponível para assinatura.');">
                            @csrf
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check me-2"></i>Aprovar
                            </button>
                        </form>
                        <button type="button" class="btn btn-danger ms-1" data-bs-toggle="modal"
                            data-bs-target="#rejectModal">
                            <i class="fas fa-times me-2"></i>Devolver
                        </button>
                    </div>
                @endif

                <a href="{{ route('documentos-internos.pdf', $documentoInterno->id) }}" class="btn btn-danger me-2">
                    <i class="fas fa-file-pdf me-2"></i>Baixar PDF
                </a>
                <button class="btn btn-dark" onclick="window.print()">
                    <i class="fas fa-print me-2"></i>Imprimir
                </button>

                @inject('signatureService', 'App\Services\SignatureService')
                @if (!$documentoInterno->assinado_em && $signatureService->canSign($documentoInterno, auth()->user()))
                    <button class="btn btn-success ms-2" data-bs-toggle="modal" data-bs-target="#signModal">
                        <i class="fas fa-file-signature me-2"></i>Assinar
                    </button>
                @endif

                @if ($documentoInterno->status === \App\Enums\DocumentoStatus::ASSINADO && !$documentoInterno->arquivado)
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

                <!-- Trilha de Auditoria -->
                <div class="card mt-4 d-print-none border-0 shadow-sm">
                    <div class="card-header bg-light fw-bold">
                        <ul class="nav nav-tabs card-header-tabs" id="docTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="audit-tab" data-bs-toggle="tab"
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
                            <!-- Auditoria Tab -->
                            <div class="tab-pane fade show active" id="audit" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-3">Data/Hora</th>
                                                <th>Usuário</th>
                                                <th>Ação</th>
                                                <th>IP</th>
                                                <th>Detalhes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($documentoInterno->audits as $audit)
                                                <tr>
                                                    <td class="ps-3 text-muted small">
                                                        {{ $audit->created_at->format('d/m/Y H:i:s') }}</td>
                                                    <td class="fw-medium">{{ $audit->user->name ?? 'Sistema' }}</td>
                                                    <td>
                                                        <span
                                                            class="badge bg-{{ match ($audit->action) {'create' => 'primary','update' => 'info','sign' => 'success','delete' => 'danger',default => 'secondary'} }}-subtle text-dark border">
                                                            {{ ucfirst($audit->action) }}
                                                        </span>
                                                    </td>
                                                    <td class="small text-muted">{{ $audit->ip_address }}</td>
                                                    <td class="small text-muted">
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
                                                    <td>{{ $versao->autor->name ?? 'Desconhecido' }}</td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#versionModal{{ $versao->id }}">
                                                            <i class="fas fa-eye"></i> Ver Conteúdo
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
                                                                                {!! \App\Support\Sanitizer::clean($versao->conteudo_final) !!}
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

            /* Hide System Elements */
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
            'ctxRoute' => route('assistente.interno', $documentoInterno),
            'ctxTitulo' => 'este documento interno',
        ])
    @endif
@endsection
