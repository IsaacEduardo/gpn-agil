<?php

namespace App\Services;

use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use App\Models\User;
use App\Services\Requisicao\RequisicaoContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RequisicaoService
{
    /**
     * Cria uma nova requisição com código sequencial gerado
     */
    public function createRequisicao(array $data, User $user): Requisicao
    {
        return DB::transaction(function () use ($data, $user) {
            $tipoRaw = $data['tipo'];

            // Mapeamento simples (poderia ser um Enum)
            $tipoMapeado = match ($tipoRaw) {
                'produto', 'oficina', 'servico', 'passagem' => $tipoRaw,
                default => 'produto',
            };

            $mesAno = date('m/Y');
            $tipoPrefix = strtoupper(substr($tipoRaw, 0, 3));

            // Busca última requisição para gerar sequencial
            $ultimaRequisicao = Requisicao::where('tipo', $tipoMapeado)
                ->whereMonth('data_requisicao', date('m'))
                ->whereYear('data_requisicao', date('Y'))
                ->orderBy('id', 'desc')
                ->first();

            $sequencial = $ultimaRequisicao ? intval(substr($ultimaRequisicao->codigo_sequencial, -3)) + 1 : 1;
            $codigoSequencial = $tipoPrefix.'-'.$mesAno.'-'.str_pad((string) $sequencial, 3, '0', STR_PAD_LEFT);

            return Requisicao::create([
                'tipo' => $tipoMapeado,
                'codigo_sequencial' => $codigoSequencial,
                'data_requisicao' => now(),
                'usuario_id' => $user->id,
                'status' => StatusRequisicao::PENDENTE,
                'empresa_destinataria' => $data['empresa_destinataria'],
                'observacoes' => $data['observacoes'] ?? null,
            ]);
        });
    }

    /**
     * Aprova uma requisição
     */
    public function aprovar(Requisicao $requisicao, User $user): void
    {
        if ($requisicao->status === StatusRequisicao::APROVADO) {
            throw new \DomainException('Esta requisição já foi aprovada.');
        }

        $requisicao->update([
            'status' => StatusRequisicao::APROVADO,
            'aprovado_por' => $user->id,
            'data_aprovacao' => now(),
        ]);
    }

    /**
     * Rejeita uma requisição
     */
    public function rejeitar(Requisicao $requisicao, User $user, string $motivo): void
    {
        if (in_array($requisicao->status, [StatusRequisicao::APROVADO, StatusRequisicao::REJEITADO])) {
            throw new \DomainException('Esta requisição já foi processada.');
        }

        $requisicao->update([
            'status' => StatusRequisicao::REJEITADO,
            'aprovado_por' => $user->id,
            'data_aprovacao' => now(),
            'motivo_rejeicao' => $motivo,
        ]);
    }

    /**
     * Atualiza uma requisição
     */
    public function update(Requisicao $requisicao, array $data): void
    {
        if ($requisicao->status === StatusRequisicao::APROVADO) {
            throw new \DomainException('Não é possível editar uma requisição já aprovada.');
        }

        $requisicao->update([
            'empresa_destinataria' => $data['empresa_destinataria'],
            'observacoes' => $data['observacoes'] ?? null,
        ]);
    }

    /**
     * Exclui uma requisição e seus relacionamentos
     */
    public function delete(Requisicao $requisicao): void
    {
        if ($requisicao->status === StatusRequisicao::APROVADO) {
            throw new \DomainException('Não é possível excluir uma requisição já aprovada.');
        }

        DB::transaction(function () use ($requisicao) {
            // Usa a estratégia para deletar relacionamentos específicos
            $strategy = RequisicaoContext::getStrategy($requisicao->tipo->value ?? $requisicao->tipo);
            $strategy->deleteRelated($requisicao);

            // Excluir termos de entrega e arquivos
            foreach ($requisicao->termos as $termo) {
                if ($termo->caminho_arquivo) {
                    Storage::disk('public')->delete($termo->caminho_arquivo);
                }
                $termo->delete();
            }

            $requisicao->delete();
        });
    }

    /**
     * Carrega relacionamentos baseados na estratégia
     */
    public function loadRelationships(Requisicao $requisicao): void
    {
        $strategy = RequisicaoContext::getStrategy($requisicao->tipo->value ?? $requisicao->tipo);
        $strategy->loadRelationships($requisicao);

        // Carregamentos comuns
        $requisicao->load('termos');
        $requisicao->loadMissing('usuario.departamentos');
    }

    /**
     * Assina digitalmente a requisição e atualiza seu estado
     */
    public function assinar(Requisicao $requisicao, User $user, string $password, ?string $certificatePassword = null): void
    {
        // 1. Validar Estado
        if ($requisicao->status !== StatusRequisicao::PENDENTE) {
            throw new \DomainException('Apenas requisições pendentes podem ser assinadas.');
        }

        DB::transaction(function () use ($requisicao, $user, $password, $certificatePassword) {
            // 2. Assinar (SignatureService)
            // Precisamos instanciar o serviço aqui ou injetá-lo.
            // Para simplicidade, vamos usar app() helper já que este método não é injetado no construtor atualmente
            $signatureService = app(\App\Services\SignatureService::class);
            $signatureService->sign($requisicao, $user, $password, false, $certificatePassword);

            // 3. Atualizar Estado e Visto
            $requisicao->update([
                'status' => StatusRequisicao::ASSINADO,
                'visto_departamento_status' => 'aprovado',
                'visto_departamento_por' => $user->id,
                'visto_departamento_data' => now(),
            ]);

            // 4. Notificar Solicitante
            if ($requisicao->usuario && $requisicao->usuario->id !== $user->id) {
                $requisicao->usuario->notify(new \App\Notifications\RequisicaoAssinada($requisicao, $user));
            }
        });
    }

    /**
     * Emite visto de aprovação do departamento
     */
    public function emitirVistoAprovado(Requisicao $requisicao, User $user, ?string $observacao = null): void
    {
        if ($requisicao->status !== StatusRequisicao::PENDENTE) {
            throw new \DomainException('Visto só pode ser emitido enquanto a requisição está pendente.');
        }

        if ($requisicao->vistoDepartamentoAprovado()) {
            throw new \DomainException('O visto do departamento já foi aprovado.');
        }

        $requisicao->update([
            'visto_departamento_status' => 'aprovado',
            'visto_departamento_por' => $user->id,
            'visto_departamento_data' => now(),
            'visto_departamento_observacao' => $observacao,
        ]);
    }

    /**
     * Emite visto de rejeição do departamento
     */
    public function emitirVistoRejeitado(Requisicao $requisicao, User $user, string $observacao): void
    {
        if ($requisicao->status !== StatusRequisicao::PENDENTE) {
            throw new \DomainException('Visto só pode ser emitido enquanto a requisição está pendente.');
        }

        $requisicao->update([
            'visto_departamento_status' => 'rejeitado',
            'visto_departamento_por' => $user->id,
            'visto_departamento_data' => now(),
            'visto_departamento_observacao' => $observacao,
        ]);
    }

    /**
     * Obtém a rota de redirecionamento para o tipo
     */
    public function getRedirectRoute(string $tipo): string
    {
        return RequisicaoContext::getStrategy($tipo)->getRedirectRoute();
    }
}
