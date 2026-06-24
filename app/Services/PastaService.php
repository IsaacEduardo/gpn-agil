<?php

namespace App\Services;

use App\Enums\PastaTipo;
use App\Models\Departamento;
use App\Models\Pasta;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PastaService
{
    /**
     * Inicializa a estrutura padrão de pastas para um departamento.
     * Geralmente chamado ao criar um departamento ou via comando.
     */
    public function initializeDepartmentFolders(Departamento $departamento, ?int $userId = null): void
    {
        $userId = $userId ?? Auth::id();

        // Fallback se não houver usuário autenticado (ex: seeder ou console)
        if (! $userId) {
            $userId = User::where('departamento_id', $departamento->id)->first()?->id ?? 1;
        }

        DB::transaction(function () use ($departamento, $userId) {
            $year = date('Y');

            // 1. Entrada
            $entrada = $this->createSystemFolder('01. Correspondência Recebida', $departamento, null, PastaTipo::ENTRADA, 'Entradas de documentos externos e internos.', $userId);
            $this->createSystemFolder($year, $departamento, $entrada->id, PastaTipo::ENTRADA_ANO, '', $userId);

            // 2. Saída
            $saida = $this->createSystemFolder('02. Correspondência Expedida', $departamento, null, PastaTipo::SAIDA, 'Cópias de documentos enviados.', $userId);
            $this->createSystemFolder($year, $departamento, $saida->id, PastaTipo::SAIDA_ANO, '', $userId);

            // 3. Internos
            $interno = $this->createSystemFolder('03. Documentos Internos', $departamento, null, PastaTipo::INTERNO, 'Minutas, despachos e notas internas.', $userId);
            $this->createSystemFolder('Despachos', $departamento, $interno->id, PastaTipo::INTERNO_DESPACHOS, '', $userId);
            $this->createSystemFolder('Pareceres', $departamento, $interno->id, PastaTipo::INTERNO_PARECERES, '', $userId);
        });
    }

    protected function createSystemFolder(string $name, Departamento $dep, ?int $parentId, PastaTipo|string $type, string $desc = '', ?int $userId = null): Pasta
    {
        return Pasta::firstOrCreate(
            [
                'departamento_id' => $dep->id,
                'nome' => $name,
                'parent_id' => $parentId,
            ],
            [
                'descricao' => $desc,
                'is_system' => true,
                'type' => $type instanceof PastaTipo ? $type->value : $type,
                'gabinete_id' => $dep->gabinete_id,
                'created_by' => $userId,
            ]
        );
    }

    /**
     * Retorna a árvore de pastas para navegação (Breadcrumbs).
     */
    public function getBreadcrumbs(Pasta $pasta): array
    {
        $breadcrumbs = [];
        $current = $pasta;

        while ($current) {
            array_unshift($breadcrumbs, $current);
            $current = $current->parent;
        }

        return $breadcrumbs;
    }

    public function getRootFolders(User $user)
    {
        $query = Pasta::whereNull('parent_id')
            ->orderBy('nome');

        if ($user->departamento_id) {
            $query->where('departamento_id', $user->departamento_id);
        } else {
            // Se não tem departamento, vê apenas as que criou (pessoal)
            $query->where('created_by', $user->id);
        }

        return $query->get();
    }

    /**
     * Retorna um array formatado para uso em selects HTML (hierarquia linearizada).
     * Ex: [id => "Entradas > 2024 > Janeiro"]
     */
    public function getFolderTreeOptions(User $user): array
    {
        $roots = $this->getRootFolders($user);
        $options = [];

        foreach ($roots as $root) {
            $this->flattenTree($root, $options);
        }

        return $options;
    }

    private function flattenTree(Pasta $pasta, array &$options, string $prefix = '')
    {
        $currentName = $prefix ? $prefix.' > '.$pasta->nome : $pasta->nome;
        $options[$pasta->id] = $currentName;

        // Carregar filhos se não estiverem carregados
        if (! $pasta->relationLoaded('children')) {
            $pasta->load('children');
        }

        foreach ($pasta->children as $child) {
            $this->flattenTree($child, $options, $currentName);
        }
    }

    /**
     * Obtém ou cria a estrutura de pasta cronológica para arquivamento.
     * Ex: Correspondência Recebida > 2026 > 06 - Junho
     */
    public function getOrCreateChronologicalFolder(Departamento $departamento, PastaTipo $baseType, Carbon $date, ?int $userId = null): Pasta
    {
        $userId = $userId ?? Auth::id();

        // Fallback se não houver usuário logado (ex: seeders/commandos)
        if (! $userId) {
            $userId = User::where('departamento_id', $departamento->id)->first()?->id ?? 1;
        }

        $rootType = null;
        $rootName = null;
        $rootDesc = '';

        if ($baseType === PastaTipo::ENTRADA || $baseType === PastaTipo::ENTRADA_ANO || $baseType === PastaTipo::ENTRADA_MES) {
            $rootType = PastaTipo::ENTRADA;
            $rootName = '01. Correspondência Recebida';
            $rootDesc = 'Entradas de documentos externos e internos.';
        } elseif ($baseType === PastaTipo::SAIDA || $baseType === PastaTipo::SAIDA_ANO || $baseType === PastaTipo::SAIDA_MES) {
            $rootType = PastaTipo::SAIDA;
            $rootName = '02. Correspondência Expedida';
            $rootDesc = 'Cópias de documentos enviados.';
        } elseif ($baseType === PastaTipo::INTERNO) {
            $rootType = PastaTipo::INTERNO;
            $rootName = '03. Documentos Internos';
            $rootDesc = 'Minutas, despachos e notas internas.';
        } else {
            throw new \InvalidArgumentException('Tipo base inválido para arquivamento cronológico');
        }

        return DB::transaction(function () use ($departamento, $date, $userId, $rootType, $rootName, $rootDesc) {
            // 1. Obter ou criar pasta raiz
            $rootFolder = $this->createSystemFolder($rootName, $departamento, null, $rootType, $rootDesc, $userId);

            // 2. Obter ou criar pasta do Ano
            $year = $date->format('Y');
            $yearType = ($rootType === PastaTipo::ENTRADA) ? PastaTipo::ENTRADA_ANO : PastaTipo::SAIDA_ANO;
            $yearFolder = $this->createSystemFolder($year, $departamento, $rootFolder->id, $yearType, '', $userId);

            // 3. Obter ou criar pasta do Mês
            $monthNum = $date->format('m');
            $monthNames = [
                '01' => 'Janeiro',
                '02' => 'Fevereiro',
                '03' => 'Março',
                '04' => 'Abril',
                '05' => 'Maio',
                '06' => 'Junho',
                '07' => 'Julho',
                '08' => 'Agosto',
                '09' => 'Setembro',
                '10' => 'Outubro',
                '11' => 'Novembro',
                '12' => 'Dezembro',
            ];
            $monthName = $monthNames[$monthNum] ?? $date->format('F');
            $monthFolderName = "{$monthNum} - {$monthName}";
            $monthType = ($rootType === PastaTipo::ENTRADA) ? PastaTipo::ENTRADA_MES : PastaTipo::SAIDA_MES;

            return $this->createSystemFolder($monthFolderName, $departamento, $yearFolder->id, $monthType, '', $userId);
        });
    }

    /**
     * Valida se uma pasta é compatível com o tipo de documento.
     */
    public function isFolderCompatibleWithType(Pasta $pasta, string $documentType): bool
    {
        $folderType = $pasta->type;

        if ($documentType === 'entrada') {
            return in_array($folderType, [
                PastaTipo::ENTRADA->value,
                PastaTipo::ENTRADA_ANO->value,
                PastaTipo::ENTRADA_MES->value,
                'custom',
                'outro',
                'public',
            ]);
        }

        if ($documentType === 'interno') {
            return in_array($folderType, [
                PastaTipo::INTERNO->value,
                PastaTipo::INTERNO_DESPACHOS->value,
                PastaTipo::INTERNO_PARECERES->value,
                'custom',
                'outro',
                'public',
            ]);
        }

        return true;
    }
}
