        {{-- Workflow Stepper: Pipeline Visual de Tramitação --}}
        <?php
            // 1. Registo
            $step1Done = true;

            // 2. Validação / Vistos
            $vistoDepStatus = $doc->visto_departamento_status;
            $vistoGabStatus = $doc->visto_gabinete_status;
            $hasVistoRejeitado = ($vistoDepStatus === 'rejeitado' || $vistoGabStatus === 'rejeitado');
            $hasVistoAprovado = ($vistoDepStatus === 'aprovado' || $vistoGabStatus === 'aprovado');
            $hasDespacho = !empty($doc->texto_despacho) || !empty($doc->data_despacho) || $doc->status === 'tratado';

            if ($hasVistoRejeitado) {
                $step2State = 'rejected';
            } elseif ($hasVistoAprovado || $hasDespacho) {
                $step2State = 'completed';
            } else {
                $step2State = 'active';
            }

            // 3. Despacho / Diretriz
            if ($hasDespacho) {
                $step3State = 'completed';
            } elseif ($step2State === 'completed') {
                $step3State = 'active';
            } else {
                $step3State = 'pending';
            }

            // 4. Tramitação & Execução
            $hasEncaminhamento = $doc->encaminhamentos->count() > 0 || $doc->encaminhamentosExternos->count() > 0;
            $hasRecebimento = $doc->encaminhamentos->whereNotNull('recebido_em')->count() > 0;
            $hasTarefas = $doc->tarefas->count() > 0;
            $allTarefasDone = $hasTarefas && $doc->tarefas->whereIn('status', ['concluida', 'concluido'])->count() === $doc->tarefas->count();
            $isArquivado = $doc->arquivado || in_array($doc->status, ['arquivado', 'finalizado']);

            if ($isArquivado || ($hasRecebimento && ($hasTarefas ? $allTarefasDone : true))) {
                $step4State = 'completed';
            } elseif ($hasEncaminhamento || $hasTarefas || $step3State === 'completed') {
                $step4State = 'active';
            } else {
                $step4State = 'pending';
            }

            // 5. Conclusão & Arquivo
            $isRespostaCheck = fn ($v) => ($v->tipo_relacao instanceof \BackedEnum ? $v->tipo_relacao->value : $v->tipo_relacao) === 'RESPOSTA';
            $hasResposta = ($doc->relationLoaded('vinculosOrigem') && $doc->vinculosOrigem->contains($isRespostaCheck))
                || ($doc->relationLoaded('vinculosDestino') && $doc->vinculosDestino->contains($isRespostaCheck));
            if ($isArquivado) {
                $step5State = 'completed';
            } elseif ($hasResposta) {
                $step5State = 'completed';
            } elseif ($step4State === 'completed') {
                $step5State = 'active';
            } else {
                $step5State = 'pending';
            }
        ?>

{{--
    Faixa condensada. Antes era um bloco de cartões com ~190px de altura que
    repetia, em terceiro lugar, o estado já dado pelo badge do cabeçalho e pela
    linha do tempo. Passa a uma faixa fina de 5 marcos.
--}}
@php
    $marcos = [
        ['n' => 1, 'rotulo' => '1. Registo & Protocolo',   'estado' => 'completed',   'icone' => 'fas fa-file-import'],
        ['n' => 2, 'rotulo' => '2. Validação & Visto',     'estado' => $step2State,   'icone' => 'fas fa-user-check'],
        ['n' => 3, 'rotulo' => '3. Despacho / Diretriz',   'estado' => $step3State,   'icone' => 'fas fa-file-signature'],
        ['n' => 4, 'rotulo' => '4. Tramitação & Tarefas',  'estado' => $step4State,   'icone' => 'fas fa-shipping-fast'],
        ['n' => 5, 'rotulo' => '5. Resposta & Arquivo',    'estado' => $step5State,   'icone' => 'fas fa-archive'],
    ];

    $atual = collect($marcos)->firstWhere('estado', 'active') ?? collect($marcos)->last();
@endphp

<section class="doc-pipeline" aria-label="Fluxo do Documento (Pipeline de Tramitação)">
    <div class="d-flex align-items-baseline justify-content-between gap-2 mb-2">
        <h2 class="doc-pipeline-titulo mb-0">Fluxo do Documento (Pipeline de Tramitação)</h2>
        <span class="small text-muted">
            Status Atual: <strong class="text-dark">{{ $atual['rotulo'] }}</strong>
        </span>
    </div>

    <ol class="doc-pipeline-faixa list-unstyled mb-0">
        @foreach ($marcos as $m)
            <li class="doc-pipeline-marco is-{{ $m['estado'] }}"
                @if ($m['estado'] === 'active') aria-current="step" @endif
                title="{{ $m['rotulo'] }} — {{ match($m['estado']) {
                    'completed' => 'concluído',
                    'active' => 'em curso',
                    'rejected' => 'rejeitado',
                    default => 'pendente',
                } }}">
                <span class="doc-pipeline-bola">
                    @if ($m['estado'] === 'completed')
                        <i class="fas fa-check" aria-hidden="true"></i>
                    @elseif ($m['estado'] === 'rejected')
                        <i class="fas fa-xmark" aria-hidden="true"></i>
                    @else
                        <i class="{{ $m['icone'] }}" aria-hidden="true"></i>
                    @endif
                </span>
                <span class="doc-pipeline-rotulo">{{ $m['rotulo'] }}</span>
            </li>
        @endforeach
    </ol>
</section>
