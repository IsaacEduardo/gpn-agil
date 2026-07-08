<?php

namespace App\Observers;

use App\Models\Requisicao;
use App\Models\User;
use App\Support\CabecalhoDocumento;

class RequisicaoObserver
{
    public function creating(Requisicao $requisicao): void
    {
        // Snapshot do gabinete do criador para o cabeçalho do documento.
        // Fixa o gabinete à data de emissão (imutável mesmo que o utilizador
        // seja transferido de departamento mais tarde).
        if (empty($requisicao->gabinete_id) && $requisicao->usuario_id) {
            $usuario = User::find($requisicao->usuario_id);
            $requisicao->gabinete_id = CabecalhoDocumento::gabineteDeUser($usuario)?->id;
        }

        if (empty($requisicao->codigo_sequencial)) {
            $mesAno = date('m/Y');
            
            // Determina o prefixo baseado no tipo
            $prefixo = $requisicao->tipo instanceof \App\Enums\TipoRequisicao 
                ? $requisicao->tipo->prefixo() 
                : $this->getPrefixoFromLegacy($requisicao->tipo);

            $ultimaRequisicao = Requisicao::where('tipo', $requisicao->tipo)
                ->whereMonth('created_at', date('m'))
                ->whereYear('created_at', date('Y'))
                ->orderBy('id', 'desc')
                ->first();

            $sequencial = $ultimaRequisicao 
                ? intval(substr($ultimaRequisicao->codigo_sequencial, -3)) + 1 
                : 1;

            $requisicao->codigo_sequencial = $prefixo . '-' . $mesAno . '-' . str_pad($sequencial, 3, '0', STR_PAD_LEFT);
        }
    }

    private function getPrefixoFromLegacy($tipo): string
    {
        return match ($tipo) {
            'produto' => 'PRO',
            'oficina' => 'OFI',
            'servico' => 'SER',
            'passagem' => 'PAS',
            default => 'REQ',
        };
    }
}
