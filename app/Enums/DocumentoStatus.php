<?php

namespace App\Enums;

enum DocumentoStatus: string
{
    case RASCUNHO = 'rascunho';
    case PENDENTE_TRATAMENTO = 'pendente_tratamento';
    case TRATADO = 'tratado';
    case EM_ANALISE = 'em_analise';
    case APROVADO = 'aprovado';
    case ASSINADO = 'assinado';
    case ARQUIVADO = 'arquivado';

    // Legacy/Existing statuses map to new ones or keep if needed for backward compatibility
    // but better to migrate data. For now, I'll keep them to avoid breaking existing data immediately
    case REGISTRADO = 'registrado';
    case ENCAMINHADO = 'encaminhado';
    case RECEBIDO = 'recebido';
    case ENCAMINHADO_EXTERNO = 'encaminhado_externo';
    case FINALIZADO = 'finalizado'; // Legacy support

    /**
     * Estados para onde este pode transitar, no fluxo do DOCUMENTO DE ENTRADA.
     *
     * Existe para que haja um único sítio onde a legalidade de uma mudança de
     * estado esteja escrita. O `status` era atribuído diretamente em oito sítios
     * espalhados por três classes, sem ninguém verificar nada: nada impedia um
     * salto ilegal, e o desarquivamento chegava a repor 'registrado' num
     * documento que já tinha sido tratado, fazendo-o reaparecer na fila de quem
     * espera despacho.
     *
     * A matriz é deliberadamente permissiva — descreve o que o fluxo real faz,
     * não um ideal — porque uma guarda que recusa um caminho legítimo é pior do
     * que não ter guarda nenhuma. O que ela impede são os saltos que perdem
     * história.
     *
     * Este enum é partilhado com o documento INTERNO, que tem fluxo próprio
     * (rascunho → em análise → aprovado → assinado); daí o nome explícito.
     *
     * @return array<int, self>
     */
    public function transicoesDeDocumentoEntrada(): array
    {
        // Saídas comuns a qualquer estado em curso: o documento pode sempre ser
        // arquivado, e pode sempre sair para outro gabinete.
        $saidas = [self::ARQUIVADO, self::ENCAMINHADO_EXTERNO];

        // Estado de nascença. REGISTRADO é o valor legado do mesmo estado e
        // continua vivo em dados antigos; os dois são intermutáveis enquanto o
        // legado não for aposentado, e cancelar um encaminhamento devolve o
        // documento aqui.
        $nascenca = [self::PENDENTE_TRATAMENTO, self::REGISTRADO];

        return match ($this) {
            self::PENDENTE_TRATAMENTO, self::REGISTRADO => [
                ...$nascenca, self::ENCAMINHADO, self::RECEBIDO, self::TRATADO, ...$saidas,
            ],

            // Entregue a um setor, à espera de recibo. Um segundo despacho para
            // outro destino mantém-no aqui; cancelar o encaminhamento devolve-o
            // ao estado de nascença.
            self::ENCAMINHADO => [
                ...$nascenca, self::ENCAMINHADO, self::RECEBIDO, self::TRATADO, ...$saidas,
            ],

            // Na posse do setor: pode seguir para outro, ou fechar.
            self::RECEBIDO => [
                self::RECEBIDO, self::ENCAMINHADO, self::TRATADO, ...$saidas,
            ],

            // Tratado pelo setor. Volta a RECEBIDO quando lhe é delegada nova
            // tarefa — um documento não pode constar como tratado enquanto tem
            // trabalho por fazer.
            self::TRATADO => [
                self::RECEBIDO, self::ENCAMINHADO, self::TRATADO, ...$saidas,
            ],

            // Saiu para outro gabinete; o percurso pode continuar lá.
            self::ENCAMINHADO_EXTERNO => [
                self::RECEBIDO, self::ENCAMINHADO, self::TRATADO, self::ARQUIVADO,
            ],

            // Desarquivar repõe o estado anterior ao arquivo, seja ele qual for.
            self::ARQUIVADO => [
                self::PENDENTE_TRATAMENTO, self::ENCAMINHADO, self::RECEBIDO,
                self::TRATADO, self::ENCAMINHADO_EXTERNO,
            ],

            // Legado: só serve para ser arquivado.
            self::FINALIZADO => [self::ARQUIVADO, self::TRATADO],

            // Estados do documento interno não participam neste fluxo.
            default => [],
        };
    }

    /**
     * A mudança para $novo é legítima no fluxo do documento de entrada?
     */
    public function podeTransitarDeEntradaPara(self $novo): bool
    {
        return in_array($novo, $this->transicoesDeDocumentoEntrada(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::RASCUNHO => 'Rascunho',
            self::PENDENTE_TRATAMENTO => 'Pendente de Tratamento',
            self::TRATADO => 'Tratado',
            self::EM_ANALISE => 'Em Análise',
            self::APROVADO => 'Aprovado',
            self::ASSINADO => 'Assinado',
            self::ARQUIVADO => 'Arquivado',
            self::REGISTRADO => 'Registrado',
            self::ENCAMINHADO => 'Encaminhado',
            self::RECEBIDO => 'Recebido',
            self::ENCAMINHADO_EXTERNO => 'Encaminhado Externo',
            self::FINALIZADO => 'Finalizado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::RASCUNHO => 'secondary',
            self::PENDENTE_TRATAMENTO => 'warning',
            self::TRATADO => 'info',
            self::EM_ANALISE => 'warning',
            self::APROVADO => 'primary',
            self::ASSINADO => 'success',
            self::ARQUIVADO => 'dark',
            self::REGISTRADO => 'info',
            self::ENCAMINHADO => 'warning',
            self::RECEBIDO => 'success',
            self::ENCAMINHADO_EXTERNO => 'info',
            self::FINALIZADO => 'success',
        };
    }
}
