<?php

namespace App\Services;

use App\Enums\DocumentoStatus;
use App\Enums\PastaTipo;
use App\Models\AuditLog;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoInterno;
use App\Models\Pasta;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Serviço canónico de arquivamento de documentos (entrada e interno).
 *
 * Unifica a regra de "arquivar" que antes estava espalhada e inconsistente entre
 * PastaController::arquivar, DocumentoWorkflowService::archive e ArchiveDocumentJob:
 *  - define arquivado/arquivado_em/arquivado_por + status + pasta_id;
 *  - valida a pasta de destino (acessível ao utilizador + compatível com o tipo);
 *  - regista auditoria com o ator real.
 *
 * Referência da regra original: app/Http/Controllers/PastaController.php::arquivar()
 * e app/Services/PastaService.php (pasta cronológica + isFolderCompatibleWithType).
 */
class ArchiveService
{
    public function __construct(private PastaService $pastaService) {}

    /**
     * Arquiva um documento na pasta indicada. Use 'auto'/null para arquivamento
     * cronológico automático (Correspondência > Ano > Mês).
     *
     * @param  string|int|null  $pastaId
     *
     * @throws ValidationException
     */
    public function archive(Model $documento, User $actor, string|int|null $pastaId = 'auto'): Pasta
    {
        [$tipo, $statusArquivado] = $this->resolveType($documento);

        $pasta = $this->resolveFolder($documento, $actor, $tipo, $pastaId);

        $documento->update([
            'pasta_id' => $pasta->id,
            'arquivado' => true,
            'arquivado_em' => now(),
            'arquivado_por' => $actor->id,
            'status' => $statusArquivado,
        ]);

        $this->audit($documento, $actor, $pasta, $tipo);

        return $pasta;
    }

    /**
     * @return array{0:string, 1:\App\Enums\DocumentoStatus|string}
     */
    private function resolveType(Model $documento): array
    {
        if ($documento instanceof DocumentoInterno) {
            return ['interno', DocumentoStatus::ARQUIVADO];
        }
        if ($documento instanceof DocumentoEntrada) {
            return ['entrada', 'arquivado'];
        }

        throw new \InvalidArgumentException('Tipo de documento não suportado para arquivamento.');
    }

    private function resolveFolder(Model $documento, User $actor, string $tipo, string|int|null $pastaId): Pasta
    {
        // Arquivamento automático (cronológico).
        if ($pastaId === null || $pastaId === '' || $pastaId === 'auto') {
            $departamento = $documento->departamento ?? $actor->departamento;
            if (! $departamento) {
                throw ValidationException::withMessages([
                    'destination_id' => 'Não foi possível determinar o departamento para arquivamento automático.',
                ]);
            }
            $baseType = $tipo === 'interno' ? PastaTipo::INTERNO : PastaTipo::ENTRADA;
            $base = $tipo === 'interno' ? ($documento->created_at ?? now()) : ($documento->data_entrada ?? now());
            $date = $base instanceof Carbon ? $base : Carbon::parse($base);

            return $this->pastaService->getOrCreateChronologicalFolder($departamento, $baseType, $date, $actor->id);
        }

        $pasta = Pasta::find((int) $pastaId);
        if (! $pasta) {
            throw ValidationException::withMessages(['destination_id' => 'Pasta de destino inexistente.']);
        }

        // Segurança: a pasta tem de estar no âmbito de acesso do utilizador
        // (mesmo departamento, pública, partilhada, ou gabinete/admin) — reutiliza Pasta::accessibleBy.
        $acessivel = Pasta::accessibleBy($actor)->whereKey($pasta->id)->exists();
        if (! $acessivel) {
            throw ValidationException::withMessages([
                'destination_id' => 'Você não tem permissão para arquivar nesta pasta.',
            ]);
        }

        // Compatibilidade entre o tipo da pasta e o tipo do documento.
        if (! $this->pastaService->isFolderCompatibleWithType($pasta, $tipo)) {
            throw ValidationException::withMessages([
                'destination_id' => 'A pasta selecionada não é compatível com este tipo de documento.',
            ]);
        }

        return $pasta;
    }

    private function audit(Model $documento, User $actor, Pasta $pasta, string $tipo): void
    {
        try {
            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'documento.arquivado',
                'auditable_type' => $documento::class,
                'auditable_id' => $documento->id,
                'old_values' => null,
                'new_values' => ['tipo' => $tipo, 'pasta_id' => $pasta->id, 'pasta' => $pasta->nome],
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);
        } catch (\Throwable $e) {
            // A auditoria nunca deve impedir o arquivamento.
        }
    }
}
