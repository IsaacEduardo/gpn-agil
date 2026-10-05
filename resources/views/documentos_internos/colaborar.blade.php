@extends('layouts.app')

@section('styles')
<style>
    /* Folha A4 oficial em volta do editor */
    .collab-paper-wrapper {
        background: #eef1f5;
        padding: 20px;
        border-radius: 8px;
        border: 1px solid #dee2e6;
        overflow-x: auto;
    }
    .collab-paper {
        width: 210mm;
        max-width: 100%;
        min-height: 297mm;
        margin: 0 auto;
        background: #fff;
        box-shadow: 0 1px 8px rgba(0, 0, 0, .15);
        padding: 18mm 20mm 12mm 25mm;
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
        font-family: 'Times New Roman', Times, serif;
        color: #000;
    }
    .collab-paper .doc-header { margin-bottom: 8mm; }
    .collab-paper__footer { margin-top: 8mm; }

    /* Editor Tiptap (transparente — a folha fornece o fundo/margens) */
    #collab-editor { flex: 1 1 auto; }
    #collab-editor .ProseMirror {
        min-height: 400px;
        padding: 0;
        background: transparent;
        border: none;
        font-family: 'Times New Roman', Times, serif;
        font-size: 12pt;
        line-height: 1.5;
        outline: none;
    }
    #collab-editor .ProseMirror table { border-collapse: collapse; width: 100%; }
    #collab-editor .ProseMirror td, #collab-editor .ProseMirror th { border: 1px solid #999; padding: 4px 8px; }
    #collab-editor .ProseMirror:focus { outline: none; }
    #collab-editor .ProseMirror p { margin: 0 0 .5rem; }
    /* O Tiptap põe um <p> em cada célula; sem margem, a tabela de referências fica compacta. */
    #collab-editor .ProseMirror td > p, #collab-editor .ProseMirror th > p { margin: 0; }
    #collab-editor .ProseMirror .campo-vazio { color: #b45309; background: #fef3c7; }

    /* Barra de "Guardar versão" sempre à vista no fundo do ecrã */
    .collab-barra-versao { position: sticky; bottom: 0; z-index: 1020; }

    /* Barra de ferramentas */
    #collab-toolbar .btn.active { background-color: #0d6efd; border-color: #0d6efd; color: #fff; }

    /* Cursor/etiqueta de colaboração (CollaborationCursor) */
    .collaboration-cursor__caret {
        position: relative;
        border-left: 1px solid #0d0d0d;
        border-right: 1px solid #0d0d0d;
        margin-left: -1px;
        margin-right: -1px;
        word-break: normal;
        pointer-events: none;
    }
    .collaboration-cursor__label {
        position: absolute;
        top: -1.4em;
        left: -1px;
        font-size: 11px;
        font-style: normal;
        font-weight: 600;
        line-height: normal;
        color: #fff;
        padding: 1px 6px;
        border-radius: 3px 3px 3px 0;
        white-space: nowrap;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0" id="collab-doc-titulo">{{ $documentoInterno->titulo }}</h5>
            <small class="text-muted">
                {{ $documentoInterno->numero_referencia ?: 'S/N' }} ·
                <span class="badge bg-{{ $documentoInterno->status->color() }}">{{ $documentoInterno->status->label() }}</span>
                · Versão <strong id="collab-versao-atual">v{{ $versaoAtual }}</strong>
                @if ($nivelAtual)
                    · O seu nível: <strong>{{ $nivelAtual->label() }}</strong>
                @endif
            </small>
        </div>
        <div class="text-end">
            <span class="text-muted small">Online (<span id="collab-presence-count">0</span>):</span>
            <span id="collab-presence"></span>
        </div>
    </div>

    <div id="collab-status" class="alert alert-secondary py-2">A ligar ao servidor de tempo real…</div>
    @if ($podeEditar)
        {{-- Alterações ainda por confirmar pelo servidor (collab/fila.js). --}}
        <div id="collab-por-gravar" class="small text-success mb-2" aria-live="polite">Tudo gravado</div>
    @endif

    <a id="collab-fallback-link" href="{{ route('documentos-internos.edit', $documentoInterno) }}"
       class="btn btn-outline-secondary btn-sm mb-2 d-none">
        Abrir editor clássico
    </a>

    <div class="row">
        <div class="col-lg-9">
            @php
                $collabConfig = [
                    'documentoId' => $documentoInterno->id,
                    'podeEditar' => $podeEditar,
                    'podeComentar' => $podeComentar,
                    'versao' => $versaoAtual,
                    'porGuardar' => $porGuardar,
                    'user' => [
                        'id' => auth()->id(),
                        'name' => auth()->user()->name,
                        'color' => $cor,
                    ],
                    'urls' => [
                        'state' => route('documentos-internos.collab.state', $documentoInterno),
                        'updates' => route('documentos-internos.collab.updates', $documentoInterno),
                        'sync' => route('documentos-internos.collab.sync', $documentoInterno),
                        'checkpoint' => route('documentos-internos.collab.checkpoint', $documentoInterno),
                        'compactar' => route('documentos-internos.collab.compactar', $documentoInterno),
                        'comentarios' => route('documentos-internos.collab.comentarios', $documentoInterno),
                        'titulo' => route('documentos-internos.collab.titulo', $documentoInterno),
                        'campos' => route('documentos-internos.collab.campos', $documentoInterno),
                    ],
                    // Mesmas classes/textos-guia do editor clássico (App\Support\CamposVinculados).
                    'campos' => \App\Support\CamposVinculados::paraJs(optional($dadosInstituicao ?? null)->cidade ?: 'Lubango'),
                ];
            @endphp
            @if ($podeEditar)
                <div class="mb-2">
                    <label for="collab-titulo" class="form-label small mb-1">Título / Assunto</label>
                    <input id="collab-titulo" type="text" class="form-control"
                           value="{{ $documentoInterno->titulo }}" maxlength="255"
                           placeholder="Assunto do documento">
                </div>

                <details class="mb-2" @if ($documentoInterno->temConteudoEstruturado()) open @endif>
                    <summary class="small text-muted mb-1">Dados do destinatário</summary>
                    <div class="row g-2">
                        @foreach (['destinatario_nome' => 'Nome', 'destinatario_cargo' => 'Cargo', 'destinatario_orgao' => 'Instituição/Órgão', 'destinatario_local' => 'Local'] as $campo => $rotulo)
                            <div class="col-md-6">
                                <label for="collab-{{ $campo }}" class="form-label small mb-0">{{ $rotulo }}</label>
                                <input id="collab-{{ $campo }}" type="text" class="form-control form-control-sm"
                                       value="{{ $documentoInterno->{$campo} }}" maxlength="255">
                            </div>
                        @endforeach
                    </div>
                </details>
            @endif

            @if ($podeEditar)
                <div id="collab-toolbar" class="btn-toolbar gap-1 mb-2" role="toolbar" aria-label="Formatação">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" data-cmd="undo" title="Desfazer"><i class="fas fa-undo"></i></button>
                        <button type="button" class="btn btn-outline-secondary" data-cmd="redo" title="Refazer"><i class="fas fa-redo"></i></button>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" data-cmd="paragraph" title="Parágrafo">¶</button>
                        <button type="button" class="btn btn-outline-secondary fw-bold" data-cmd="h1" title="Título 1">H1</button>
                        <button type="button" class="btn btn-outline-secondary fw-bold" data-cmd="h2" title="Título 2">H2</button>
                        <button type="button" class="btn btn-outline-secondary fw-bold" data-cmd="h3" title="Título 3">H3</button>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" data-cmd="bold" title="Negrito"><i class="fas fa-bold"></i></button>
                        <button type="button" class="btn btn-outline-secondary" data-cmd="italic" title="Itálico"><i class="fas fa-italic"></i></button>
                        <button type="button" class="btn btn-outline-secondary" data-cmd="underline" title="Sublinhado"><i class="fas fa-underline"></i></button>
                        <button type="button" class="btn btn-outline-secondary" data-cmd="strike" title="Rasurado"><i class="fas fa-strikethrough"></i></button>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" data-cmd="left" title="Alinhar à esquerda"><i class="fas fa-align-left"></i></button>
                        <button type="button" class="btn btn-outline-secondary" data-cmd="center" title="Centrar"><i class="fas fa-align-center"></i></button>
                        <button type="button" class="btn btn-outline-secondary" data-cmd="right" title="Alinhar à direita"><i class="fas fa-align-right"></i></button>
                        <button type="button" class="btn btn-outline-secondary" data-cmd="justify" title="Justificar"><i class="fas fa-align-justify"></i></button>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" data-cmd="bullet" title="Lista"><i class="fas fa-list-ul"></i></button>
                        <button type="button" class="btn btn-outline-secondary" data-cmd="ordered" title="Lista numerada"><i class="fas fa-list-ol"></i></button>
                        <button type="button" class="btn btn-outline-secondary" data-cmd="blockquote" title="Citação"><i class="fas fa-quote-right"></i></button>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" data-cmd="table" title="Inserir tabela 3×3"><i class="fas fa-table"></i></button>
                    </div>
                </div>
            @endif
            <div class="collab-paper-wrapper">
                <div class="collab-paper">
                    @include('partials.document-header', [
                        'gabineteNome' => \App\Support\CabecalhoDocumento::linhaGabinete($documentoInterno->gabineteEmissor()),
                    ])

                    <div id="collab-editor" data-config='@json($collabConfig)'></div>

                    @php
                        $rodapeImg = $dadosInstituicao->rodape_url ?? null;
                        if (! $rodapeImg) {
                            foreach (['rodape_estacionario.png', 'rodape_estacionario.jpg', 'Estacionariodoc.jpg', 'Estacionariodoc.jpeg', 'estacionario.png', 'estacionario.jpg'] as $cand) {
                                if (file_exists(public_path('images/'.$cand))) {
                                    $rodapeImg = asset('images/'.$cand);
                                    break;
                                }
                            }
                        }
                    @endphp
                    @if ($rodapeImg)
                        <div class="collab-paper__footer">
                            {{-- À largura do texto e na proporção da imagem, como no PDF: o
                                 max-height de 12mm achatava o estacionário. --}}
                            <img src="{{ $rodapeImg }}" alt="Rodapé Oficial"
                                 style="width: 100%; height: auto; display: block; margin: 0 auto;">
                        </div>
                    @endif
                </div>
            </div>

            @if ($podeEditar)
                {{-- Fixa no fundo do ecrã enquanto se percorre a folha: estava depois da A4 e o
                     resultado de "Guardar versão" não se via (collab/versoes.js). --}}
                <div class="card mt-3 shadow collab-barra-versao" id="collab-barra-versao">
                    <div class="card-body py-2">
                        <div class="row g-2 align-items-end">
                            <div class="col-auto">
                                <label class="form-label small mb-0">Tipo de alteração</label>
                                <select id="collab-change-type" class="form-select form-select-sm">
                                    <option value="patch">Correção (patch)</option>
                                    <option value="minor" selected>Melhoria (minor)</option>
                                    <option value="major">Revisão (major)</option>
                                </select>
                            </div>
                            <div class="col">
                                <label class="form-label small mb-0">Descrição da versão</label>
                                <input id="collab-change-log" type="text" class="form-control form-control-sm"
                                       placeholder="Ex.: Ajustado o parágrafo introdutório">
                            </div>
                            <div class="col-auto">
                                <button id="collab-checkpoint" type="button" class="btn btn-success btn-sm" @disabled(! $porGuardar)>
                                    <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                                    <i class="fas fa-save me-1"></i>
                                    <span id="collab-checkpoint-rotulo">{{ $porGuardar ? 'Guardar versão' : 'Sem alterações desde a v'.$versaoAtual }}</span>
                                </button>
                            </div>
                        </div>
                        <div class="form-text d-flex justify-content-between flex-wrap gap-2">
                            <span id="collab-versao-estado" aria-live="polite">
                                {{ $porGuardar ? 'Há alterações desde a v'.$versaoAtual.' por guardar numa versão.' : 'O documento está igual à v'.$versaoAtual.'.' }}
                            </span>
                            <span>As alterações gravam-se sozinhas; "Guardar versão" cria um ponto no histórico de revisões.</span>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-3">
            <div class="card">
                <div class="card-header py-2"><strong>Colaboradores</strong></div>
                <ul class="list-group list-group-flush" id="collab-list">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>{{ $documentoInterno->autor->name ?? 'Autor' }}</span>
                        <span class="badge bg-dark">Autor</span>
                    </li>
                    @foreach ($colaboradores as $colab)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>{{ $colab->user->name ?? '—' }}</span>
                            <span class="d-flex align-items-center gap-1">
                                <span class="badge bg-secondary">{{ $colab->nivel->label() }}</span>
                                @if ($podeAdministrar)
                                    <button class="btn btn-sm btn-outline-danger py-0 px-1 collab-remove"
                                            data-user="{{ $colab->user_id }}" title="Remover">&times;</button>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>

                @if ($podeAdministrar)
                    <div class="card-body py-2">
                        <label class="form-label small mb-1">Convidar (mesmo gabinete)</label>
                        <select id="collab-invite-user" class="form-select form-select-sm mb-1">
                            <option value="">Selecionar utilizador…</option>
                            @foreach ($candidatos as $cand)
                                <option value="{{ $cand->id }}">{{ $cand->name }}</option>
                            @endforeach
                        </select>
                        <select id="collab-invite-nivel" class="form-select form-select-sm mb-2">
                            @foreach ($niveis as $n)
                                <option value="{{ $n->value }}" @selected($n->value === 'editar')>{{ $n->label() }}</option>
                            @endforeach
                        </select>
                        <button id="collab-invite-btn" class="btn btn-primary btn-sm w-100">Convidar</button>
                        <div id="collab-invite-msg" class="form-text"></div>
                    </div>
                @endif
            </div>

            {{-- Comentários (collab/comentarios.js). Ancorados ao trecho seleccionado. --}}
            <div class="card mt-3" id="collab-comentarios">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <strong>Comentários</strong>
                    <span class="badge bg-secondary" id="collab-coment-count" title="Conversas por resolver">0</span>
                </div>
                @if ($podeComentar)
                    <div class="card-body py-2 border-bottom">
                        <div id="collab-coment-trecho" class="small text-muted fst-italic mb-1 d-none"></div>
                        <textarea id="collab-coment-texto" class="form-control form-control-sm mb-1" rows="2" maxlength="5000"
                                  placeholder="Seleccione um trecho no texto e escreva o comentário…"></textarea>
                        <button type="button" id="collab-coment-enviar" class="btn btn-sm btn-primary w-100">Comentar</button>
                    </div>
                @endif
                <div id="collab-coment-lista" class="list-group list-group-flush small"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
@vite('resources/js/collab.js')
@if ($podeAdministrar)
<script>
    // Gestão de colaboradores (endpoints JSON). Recarrega a lista ao concluir.
    document.addEventListener('DOMContentLoaded', function () {
        const http = window.axios;
        const inviteUrl = @json(route('documentos-internos.collab.convidar', $documentoInterno));
        const baseColab = @json(url('documentos-internos/'.$documentoInterno->id.'/collab/colaboradores'));

        const btn = document.getElementById('collab-invite-btn');
        if (btn) btn.addEventListener('click', function () {
            const userId = document.getElementById('collab-invite-user').value;
            const nivel = document.getElementById('collab-invite-nivel').value;
            const msg = document.getElementById('collab-invite-msg');
            if (!userId) { msg.textContent = 'Selecione um utilizador.'; return; }
            btn.disabled = true;
            http.post(inviteUrl, { user_id: userId, nivel: nivel })
                .then(() => window.location.reload())
                .catch((e) => { msg.textContent = e.response?.data?.message || 'Falha ao convidar.'; btn.disabled = false; });
        });

        document.querySelectorAll('.collab-remove').forEach(function (b) {
            b.addEventListener('click', function () {
                if (!confirm('Remover este colaborador?')) return;
                http.delete(baseColab + '/' + b.dataset.user)
                    .then(() => window.location.reload())
                    .catch(() => alert('Falha ao remover.'));
            });
        });
    });
</script>
@endif
@endsection
