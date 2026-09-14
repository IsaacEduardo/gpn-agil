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
            $hasResposta = $doc->relationLoaded('vinculosOrigem') ? $doc->vinculosOrigem->where('tipo_relacao', 'RESPOSTA')->count() > 0 : false;
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
