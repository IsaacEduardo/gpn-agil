{{--
    Percurso do Documento — substitui as 6 abas anteriores (Linha do Tempo,
    Tarefas, Histórico Interno, Histórico Externo, Vínculos, Auditoria).

    Quatro dessas abas eram subconjuntos da linha do tempo, que já agrega 9
    fontes. Passam a filtros sobre a mesma lista; as tabelas detalhadas — com as
    colunas extra e, sobretudo, com as AÇÕES (Receber, Concluir, Cancelar) —
    continuam acessíveis em "ver em tabela".
--}}
@php
    $eventos = collect($timelineEvents ?? []);

    // Cada chip mapeia para os 'tipo' que buildTimelineEvents produz.
    $filtros = [
        'tudo' => ['rotulo' => 'Tudo', 'icone' => 'fas fa-stream', 'tipos' => null, 'tabela' => null],
        'tramitacao' => [
            'rotulo' => 'Tramitação', 'icone' => 'fas fa-share',
            'tipos' => ['encaminhamento_envio', 'encaminhamento_recebido', 'despacho', 'visto_departamento', 'visto_gabinete', 'registo'],
            'tabela' => 'interno',
        ],
        'tarefas' => [
            'rotulo' => 'Tarefas', 'icone' => 'fas fa-tasks',
            'tipos' => ['tarefa_criada', 'tarefa_concluida'],
            'tabela' => 'tarefas',
        ],
        'externo' => [
            'rotulo' => 'Externo', 'icone' => 'fas fa-globe',
            'tipos' => ['encaminhamento_externo'],
            'tabela' => 'externo',
        ],
        'vinculos' => [
            'rotulo' => 'Vínculos', 'icone' => 'fas fa-link',
            'tipos' => ['vinculo'],
            'tabela' => 'vinculos',
        ],
    ];

    if ($canVerAuditoria ?? false) {
        $filtros['auditoria'] = [
            'rotulo' => 'Auditoria', 'icone' => 'fas fa-shield-alt',
            'tipos' => [], 'tabela' => 'auditoria',
        ];
    }

    $contagem = fn (?array $tipos) => $tipos === null
        ? $eventos->count()
        : $eventos->whereIn('tipo', $tipos)->count();
@endphp

<div class="card shadow-sm border-0 rounded-3" id="percursoDocumento">
    <div class="card-header bg-white py-3 px-3 border-bottom">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                {{-- Título mantido: fixa a identidade da secção e é asserido em testes. --}}
                <h2 class="h6 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                    <i class="fas fa-stream text-primary"></i>
                    <span>Linha do Tempo Cronológica Unificada</span>
                </h2>
                <p class="text-muted small mb-0 mt-1">
                    Visão consolidada de todas as etapas, validações, despachos, encaminhamentos e respostas.
                </p>
            </div>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">
                {{ $eventos->count() }} eventos
            </span>
        </div>

        <div class="percurso-chips d-flex flex-wrap gap-2" role="group" aria-label="Filtrar o percurso do documento">
            @foreach ($filtros as $chave => $f)
                <button type="button"
                        class="btn btn-sm percurso-chip {{ $chave === 'tudo' ? 'active' : '' }}"
                        data-filtro="{{ $chave }}"
                        aria-pressed="{{ $chave === 'tudo' ? 'true' : 'false' }}">
                    <i class="{{ $f['icone'] }} me-1"></i>{{ $f['rotulo'] }}
                    @php($n = $chave === 'auditoria' ? ($auditsTotal ?? 0) : $contagem($f['tipos']))
                    @if ($n > 0)
                        <span class="badge bg-body-secondary text-body-secondary rounded-pill ms-1">{{ $n }}</span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>

    <div class="card-body p-0">
        {{-- Linha do tempo densa: uma linha por evento, descrição ao expandir. --}}
        <div id="percursoLinhaTempo">
            @if ($eventos->count())
                <ol class="percurso-lista list-unstyled mb-0">
                    @foreach ($eventos as $i => $ev)
                        <li class="percurso-evento" data-tipo="{{ $ev['tipo'] }}">
                            <button type="button" class="percurso-evento-cabeca"
                                    aria-expanded="false" aria-controls="evento-detalhe-{{ $i }}">
                                <span class="percurso-marcador {{ $ev['badge_class'] }}" aria-hidden="true">
                                    <i class="{{ $ev['icone'] }}"></i>
                                </span>

                                <span class="percurso-titulo">
                                    {{ $ev['titulo'] }}
                                    <span class="percurso-meta">
                                        {{ $ev['autor'] }}@if ($ev['setor']) · {{ $ev['setor'] }}@endif
                                    </span>
                                </span>

                                <span class="percurso-selo badge {{ $ev['badge_class'] }} rounded-pill">
                                    {{ $ev['badge_text'] }}
                                </span>

                                <time class="percurso-data" datetime="{{ optional($ev['data'])->toIso8601String() }}"
                                      title="{{ optional($ev['data'])->diffForHumans() }}">
                                    {{ optional($ev['data'])->format('d/m/Y H:i') }}
                                </time>

                                <i class="fas fa-chevron-down percurso-seta" aria-hidden="true"></i>
                            </button>

                            <div class="percurso-evento-detalhe" id="evento-detalhe-{{ $i }}" hidden>
                                <div class="percurso-descricao">{{ $ev['descricao'] }}</div>
                                <div class="percurso-detalhe-meta">
                                    <span><i class="fas fa-user-circle me-1 text-primary"></i><strong>Ator:</strong> {{ $ev['autor'] }}</span>
                                    @if ($ev['setor'])
                                        <span><i class="fas fa-building me-1 text-secondary"></i><strong>Setor:</strong> {{ $ev['setor'] }}</span>
                                    @endif
                                    <span><i class="far fa-clock me-1 text-secondary"></i>{{ optional($ev['data'])->diffForHumans() }}</span>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @else
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-stream fa-2x mb-2 opacity-50"></i>
                    <p class="mb-0">Ainda não há eventos registados no percurso deste documento.</p>
                </div>
            @endif

            <p class="percurso-sem-resultados text-center text-muted py-5 mb-0" hidden>
                <i class="fas fa-filter fa-lg mb-2 d-block opacity-50"></i>
                Nenhum evento deste tipo no percurso.
            </p>
        </div>

        {{-- Tabelas detalhadas: mesmas colunas e mesmas ações de antes. --}}
        @include('documentos_entradas.partials.show._percurso-tabelas')
    </div>
</div>
