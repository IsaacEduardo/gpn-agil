<?php

namespace App\Services;

use App\Enums\DocumentoStatus;
use App\Enums\TipoDocumentoVinculo;
use App\Enums\TipoRelacaoDocumento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoInterno;
use App\Models\DocumentoVinculo;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentoVinculoService
{
    public function __construct(
        protected DocumentoPermissionService $permissionService
    ) {}

    /**
     * Lista todos os documentos vinculados (bidirecionalmente) com metadados completos
     */
    public function listarVinculos(string $tipo, int $id, ?User $user = null): array
    {
        $tipoUpper = strtoupper($tipo);
        $vinculos = DocumentoVinculo::paraDocumento($tipoUpper, $id)
            ->with(['vinculadoPor'])
            ->orderByDesc('created_at')
            ->get();

        $resultado = [];

        foreach ($vinculos as $vinculo) {
            $outro = $vinculo->getOutroDocumento($tipoUpper, $id);
            $doc = $outro['documento'];
            $outroTipo = $outro['tipo'];

            if (! $doc) {
                continue;
            }

            $tipoRelacaoEnum = is_string($vinculo->tipo_relacao)
                ? (TipoRelacaoDocumento::tryFrom($vinculo->tipo_relacao) ?? TipoRelacaoDocumento::COMPLEMENTAR)
                : $vinculo->tipo_relacao;

            $podeVisualizar = true;
            if ($user) {
                $podeVisualizar = $this->verificarPermissaoLeitura($user, $outroTipo, $doc);
            }

            $docData = $this->ocultarSeRestrito($podeVisualizar, $this->formatarDadosDocumento($outroTipo, $doc, $podeVisualizar));

            $resultado[] = [
                'id' => $vinculo->id,
                'tipo_relacao' => $tipoRelacaoEnum->value,
                'tipo_relacao_label' => $tipoRelacaoEnum->label(),
                'tipo_relacao_badge' => $tipoRelacaoEnum->badgeClass(),
                'tipo_relacao_icon' => $tipoRelacaoEnum->icon(),
                'tipo_relacao_descricao' => $tipoRelacaoEnum->description(),
                'justificativa' => $vinculo->justificativa,
                'vinculado_por' => $vinculo->vinculadoPor?->name ?? 'Sistema',
                'vinculado_em' => $vinculo->created_at ? $vinculo->created_at->format('d/m/Y H:i') : '—',
                'papel' => $outro['papel'], // 'ORIGEM' ou 'DESTINO'
                'documento' => $docData,
            ];
        }

        return $resultado;
    }

    /**
     * Associa um ou múltiplos documentos de destino a um documento de origem
     */
    public function vincular(
        string $origemTipo,
        int $origemId,
        array $destinos,
        string $tipoRelacao,
        ?string $justificativa,
        User $user
    ): array {
        $origemTipoUpper = strtoupper($origemTipo);
        $tipoRelacaoEnum = TipoRelacaoDocumento::tryFrom(strtoupper($tipoRelacao)) ?? TipoRelacaoDocumento::COMPLEMENTAR;

        // Validar existência da origem
        $origemDoc = $origemTipoUpper === 'EXTERNO'
            ? DocumentoEntrada::find($origemId)
            : DocumentoInterno::find($origemId);

        if (! $origemDoc) {
            throw ValidationException::withMessages([
                'origem_id' => 'Documento de origem não encontrado.',
            ]);
        }

        $criados = [];

        DB::transaction(function () use ($origemTipoUpper, $origemId, $destinos, $tipoRelacaoEnum, $justificativa, $user, &$criados) {
            foreach ($destinos as $dest) {
                $destTipoUpper = strtoupper($dest['tipo'] ?? 'EXTERNO');
                $destId = (int) ($dest['id'] ?? 0);

                if (! $destId) {
                    continue;
                }

                // Evitar auto-vínculo
                if ($origemTipoUpper === $destTipoUpper && $origemId === $destId) {
                    continue;
                }

                // Destino inexistente ou que o utilizador não pode ler: ignorado da
                // mesma forma, para não revelar a existência de documentos alheios.
                if (! $this->podeLerDocumento($user, $destTipoUpper, $destId)) {
                    continue;
                }

                // Evitar duplicidade em ambos os sentidos
                $jaExiste = DocumentoVinculo::entreDocumentos($origemTipoUpper, $origemId, $destTipoUpper, $destId)->exists();
                if ($jaExiste) {
                    continue;
                }

                $vinculo = DocumentoVinculo::create([
                    'origem_tipo' => $origemTipoUpper,
                    'origem_id' => $origemId,
                    'destino_tipo' => $destTipoUpper,
                    'destino_id' => $destId,
                    'tipo_relacao' => $tipoRelacaoEnum->value,
                    'vinculado_por_id' => $user->id,
                    'justificativa' => $justificativa,
                ]);

                // Manter sincronismo com documento_entrada_id se for criação de resposta interna
                if ($origemTipoUpper === 'EXTERNO' && $destTipoUpper === 'INTERNO') {
                    $docInterno = DocumentoInterno::find($destId);
                    if ($docInterno && ! $docInterno->documento_entrada_id) {
                        $docInterno->documento_entrada_id = $origemId;
                        $docInterno->saveQuietly();
                    }
                } elseif ($origemTipoUpper === 'INTERNO' && $destTipoUpper === 'EXTERNO') {
                    $docInterno = DocumentoInterno::find($origemId);
                    if ($docInterno && ! $docInterno->documento_entrada_id) {
                        $docInterno->documento_entrada_id = $destId;
                        $docInterno->saveQuietly();
                    }
                }

                $criados[] = $vinculo;
            }
        });

        return $criados;
    }

    /**
     * Remove uma relação de vínculo
     */
    public function desvincular(int $vinculoId, User $user): bool
    {
        $vinculo = DocumentoVinculo::findOrFail($vinculoId);

        // Basta aceder a um dos lados: o vínculo aparece em ambos os documentos e
        // quem gere um deles pode retirar uma associação errada.
        $origemTipoAcesso = is_string($vinculo->origem_tipo) ? $vinculo->origem_tipo : $vinculo->origem_tipo->value;
        $destinoTipoAcesso = is_string($vinculo->destino_tipo) ? $vinculo->destino_tipo : $vinculo->destino_tipo->value;
        if (! $this->podeLerDocumento($user, $origemTipoAcesso, (int) $vinculo->origem_id)
            && ! $this->podeLerDocumento($user, $destinoTipoAcesso, (int) $vinculo->destino_id)) {
            throw (new ModelNotFoundException)->setModel(DocumentoVinculo::class, [$vinculoId]);
        }

        // Se for um vínculo entre Entrada e Interno, limpar chave legada se correspondente
        $origemTipo = is_string($vinculo->origem_tipo) ? $vinculo->origem_tipo : $vinculo->origem_tipo->value;
        $destinoTipo = is_string($vinculo->destino_tipo) ? $vinculo->destino_tipo : $vinculo->destino_tipo->value;

        if ($origemTipo === 'EXTERNO' && $destinoTipo === 'INTERNO') {
            $docInterno = DocumentoInterno::find($vinculo->destino_id);
            if ($docInterno && (int) $docInterno->documento_entrada_id === (int) $vinculo->origem_id) {
                $docInterno->documento_entrada_id = null;
                $docInterno->saveQuietly();
            }
        } elseif ($origemTipo === 'INTERNO' && $destinoTipo === 'EXTERNO') {
            $docInterno = DocumentoInterno::find($vinculo->origem_id);
            if ($docInterno && (int) $docInterno->documento_entrada_id === (int) $vinculo->destino_id) {
                $docInterno->documento_entrada_id = null;
                $docInterno->saveQuietly();
            }
        }

        return (bool) $vinculo->delete();
    }

    /**
     * Busca rápida em tempo real de documentos para vincular via Modal
     */
    public function buscarDocumentosParaVincular(
        string $termo,
        string $currentTipo,
        int $currentId,
        User $user,
        ?string $filtroTipo = null
    ): array {
        $termo = trim($termo);
        $currentTipoUpper = strtoupper($currentTipo);
        $filtroTipoUpper = $filtroTipo ? strtoupper($filtroTipo) : null;

        $resultados = [];

        // 1. Buscar Documentos de Entrada
        if (! $filtroTipoUpper || $filtroTipoUpper === 'EXTERNO') {
            $queryEntradas = DocumentoEntrada::with(['departamento', 'usuario']);

            // Mesmo âmbito da pesquisa global: só entradas que o utilizador pode ver.
            app(DocumentoEntradaService::class)->applyVisibilityScope($queryEntradas, $user);

            if ($termo !== '') {
                $queryEntradas->where(function ($q) use ($termo) {
                    $q->where('numero_sequencial', 'like', "%{$termo}%")
                        ->orWhere('ano_referencia', 'like', "%{$termo}%")
                        ->orWhere('assunto', 'like', "%{$termo}%")
                        ->orWhere('procedencia', 'like', "%{$termo}%")
                        ->orWhere('classificacao_ref_numero', 'like', "%{$termo}%");
                });
            }

            if ($currentTipoUpper === 'EXTERNO') {
                $queryEntradas->where('id', '!=', $currentId);
            }

            $entradas = $queryEntradas->orderByDesc('created_at')->limit(15)->get();

            foreach ($entradas as $e) {
                $podeVer = $this->verificarPermissaoLeitura($user, 'EXTERNO', $e);
                $jaVinculado = DocumentoVinculo::entreDocumentos($currentTipoUpper, $currentId, 'EXTERNO', $e->id)->exists();

                $resultados[] = $this->ocultarSeRestrito($podeVer, [
                    'id' => $e->id,
                    'tipo' => 'EXTERNO',
                    'tipo_label' => 'Entrada Externa',
                    'tipo_badge' => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
                    'numero_identificador' => "{$e->numero_sequencial}/{$e->ano_referencia}",
                    'titulo' => $e->assunto,
                    'especie' => $e->classificacao_especie ?? 'Entrada',
                    'departamento' => $e->departamento->nome ?? 'Sem departamento',
                    'autor_ou_procedencia' => $e->procedencia ?? ($e->usuario->name ?? '—'),
                    'status' => $e->status,
                    'status_label' => ucfirst($e->status),
                    'status_badge' => $this->getStatusBadgeClass('EXTERNO', $e->status),
                    'data' => optional($e->data_entrada ?? $e->created_at)->format('d/m/Y'),
                    'pode_visualizar' => $podeVer,
                    'ja_vinculado' => $jaVinculado,
                ]);
            }
        }

        // 2. Buscar Documentos Internos
        if (! $filtroTipoUpper || $filtroTipoUpper === 'INTERNO') {
            $queryInternos = DocumentoInterno::with(['especie', 'departamento', 'autor']);

            if (! $user->isAdmin()) {
                $queryInternos->accessibleBy($user);
            }

            if ($termo !== '') {
                $queryInternos->where(function ($q) use ($termo) {
                    $q->where('numero_referencia', 'like', "%{$termo}%")
                        ->orWhere('titulo', 'like', "%{$termo}%")
                        ->orWhere('conteudo_final', 'like', "%{$termo}%");
                });
            }

            if ($currentTipoUpper === 'INTERNO') {
                $queryInternos->where('id', '!=', $currentId);
            }

            $internos = $queryInternos->orderByDesc('created_at')->limit(15)->get();

            foreach ($internos as $i) {
                $podeVer = $this->verificarPermissaoLeitura($user, 'INTERNO', $i);
                $jaVinculado = DocumentoVinculo::entreDocumentos($currentTipoUpper, $currentId, 'INTERNO', $i->id)->exists();

                $statusVal = is_string($i->status) ? $i->status : ($i->status?->value ?? 'rascunho');

                $resultados[] = $this->ocultarSeRestrito($podeVer, [
                    'id' => $i->id,
                    'tipo' => 'INTERNO',
                    'tipo_label' => 'Documento Interno',
                    'tipo_badge' => 'bg-primary-subtle text-primary border border-primary-subtle',
                    'numero_identificador' => $i->numero_referencia ?: '#' . $i->id,
                    'titulo' => $i->titulo,
                    'especie' => $i->especie->nome ?? 'Documento Interno',
                    'departamento' => $i->departamento->nome ?? 'Sem departamento',
                    'autor_ou_procedencia' => $i->autor->name ?? '—',
                    'status' => $statusVal,
                    'status_label' => is_object($i->status) && method_exists($i->status, 'label') ? $i->status->label() : ucfirst(str_replace('_', ' ', $statusVal)),
                    'status_badge' => $this->getStatusBadgeClass('INTERNO', $statusVal),
                    'data' => $i->created_at->format('d/m/Y'),
                    'pode_visualizar' => $podeVer,
                    'ja_vinculado' => $jaVinculado,
                ]);
            }
        }

        return $resultados;
    }

    /**
     * Formata os dados de exibição de um documento
     */
    protected function formatarDadosDocumento(string $tipo, $doc, bool $podeVisualizar): array
    {
        $tipoUpper = strtoupper($tipo);

        if ($tipoUpper === 'EXTERNO') {
            /** @var DocumentoEntrada $doc */
            return [
                'id' => $doc->id,
                'tipo' => 'EXTERNO',
                'tipo_label' => 'Documento Externo (Entrada)',
                'tipo_badge' => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
                'numero_identificador' => "{$doc->numero_sequencial}/{$doc->ano_referencia}",
                'titulo' => $doc->assunto,
                'especie' => $doc->classificacao_especie ?? 'Entrada',
                'departamento' => $doc->departamento->nome ?? '—',
                'autor_ou_procedencia' => $doc->procedencia ?? ($doc->usuario->name ?? '—'),
                'status' => $doc->status,
                'status_label' => ucfirst($doc->status),
                'status_badge' => $this->getStatusBadgeClass('EXTERNO', $doc->status),
                'data' => optional($doc->data_entrada ?? $doc->created_at)->format('d/m/Y'),
                'url_show' => route('documentos-entradas.show', $doc->id),
                'url_pdf' => \Illuminate\Support\Facades\Route::has('documentos-entradas.protocolo.pdf') ? route('documentos-entradas.protocolo.pdf', $doc->id) : null,
                'pode_visualizar' => $podeVisualizar,
            ];
        }

        /** @var DocumentoInterno $doc */
        $statusVal = is_string($doc->status) ? $doc->status : ($doc->status?->value ?? 'rascunho');
        $statusLabel = is_object($doc->status) && method_exists($doc->status, 'label')
            ? $doc->status->label()
            : ucfirst(str_replace('_', ' ', $statusVal));

        return [
            'id' => $doc->id,
            'tipo' => 'INTERNO',
            'tipo_label' => 'Documento Interno',
            'tipo_badge' => 'bg-primary-subtle text-primary border border-primary-subtle',
            'numero_identificador' => $doc->numero_referencia ?: '#' . $doc->id,
            'titulo' => $doc->titulo,
            'especie' => $doc->especie->nome ?? 'Documento',
            'departamento' => $doc->departamento->nome ?? '—',
            'autor_ou_procedencia' => $doc->autor->name ?? '—',
            'status' => $statusVal,
            'status_label' => $statusLabel,
            'status_badge' => $this->getStatusBadgeClass('INTERNO', $statusVal),
            'data' => $doc->created_at ? $doc->created_at->format('d/m/Y') : '—',
            'url_show' => route('documentos-internos.show', $doc->id),
            'url_pdf' => \Illuminate\Support\Facades\Route::has('documentos-internos.pdf') ? route('documentos-internos.pdf', $doc->id) : null,
            'pode_visualizar' => $podeVisualizar,
        ];
    }

    /**
     * Existe e o utilizador pode lê-lo. Inexistente e sem acesso dão o mesmo
     * resultado, para que a resposta não confirme a existência de documentos.
     */
    public function podeLerDocumento(User $user, string $tipo, int $id): bool
    {
        $tipoUpper = strtoupper($tipo);
        $doc = match ($tipoUpper) {
            'EXTERNO' => DocumentoEntrada::find($id),
            'INTERNO' => DocumentoInterno::find($id),
            default => null,
        };

        return $doc !== null && $this->verificarPermissaoLeitura($user, $tipoUpper, $doc);
    }

    /**
     * Documento sem acesso: fica só o tipo e o número, para o utilizador saber
     * que o vínculo existe sem ver assunto, autor, setor ou endereços.
     */
    protected function ocultarSeRestrito(bool $podeVisualizar, array $dados): array
    {
        if ($podeVisualizar) {
            return $dados;
        }

        return array_merge($dados, [
            'titulo' => 'Documento de acesso restrito',
            'especie' => '—',
            'departamento' => '—',
            'autor_ou_procedencia' => '—',
            'status' => null,
            'status_label' => 'Restrito',
            'status_badge' => 'bg-light text-dark border',
            'url_show' => null,
            'url_pdf' => null,
        ]);
    }

    /**
     * Verifica se o usuário autenticado possui permissão de leitura sobre o documento
     */
    protected function verificarPermissaoLeitura(User $user, string $tipo, $doc): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $tipoUpper = strtoupper($tipo);
        if ($tipoUpper === 'EXTERNO') {
            return $this->permissionService->canViewDocument($user, $doc);
        }

        return DocumentoInterno::accessibleBy($user)->where('documento_internos.id', $doc->id)->exists();
    }

    /**
     * Retorna a classe de badge para o status
     */
    protected function getStatusBadgeClass(string $tipo, ?string $status): string
    {
        $status = strtolower((string) $status);

        if ($tipo === 'EXTERNO') {
            return match ($status) {
                'pendente' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                'em_andamento', 'encaminhado' => 'bg-info-subtle text-info-emphasis border border-info-subtle',
                'tratado', 'concluido', 'finalizado' => 'bg-success-subtle text-success-emphasis border border-success-subtle',
                'arquivado' => 'bg-dark-subtle text-dark border border-dark-subtle',
                default => 'bg-light text-dark border',
            };
        }

        return match ($status) {
            'rascunho' => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
            'em_analise', 'pendente_tratamento' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
            'aprovado', 'assinado', 'finalizado' => 'bg-success-subtle text-success-emphasis border border-success-subtle',
            'arquivado' => 'bg-dark-subtle text-dark border border-dark-subtle',
            default => 'bg-light text-dark border',
        };
    }
}
