<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permissões do módulo de Relatórios & BI.
 *
 * Nota sobre os papéis: no GPN-AGIL só `admin` e `chefe-departamento` são
 * papéis Spatie. Chefe e super-chefe de gabinete não são papéis — são
 * derivados de gabinetes.responsavel_id / super_chefe_id (ver
 * User::isChefeGabinete()), pelo que o seu acesso é garantido por inerência
 * em RelatorioController e não por atribuição aqui.
 */
class RelatoriosPermissionSeeder extends Seeder
{
    /** Permissões criadas por este seeder. */
    public const PERMISSOES = [
        'relatorios.view',
        'relatorios.export',
    ];

    /** Papéis que recebem as duas permissões. */
    public const PAPEIS_COM_ACESSO = [
        'admin',
        'chefe-departamento',
    ];

    public function run(): void
    {
        foreach (self::PERMISSOES as $permissao) {
            Permission::findOrCreate($permissao, 'web');
        }

        foreach (self::PAPEIS_COM_ACESSO as $nome) {
            $papel = Role::where('name', $nome)->where('guard_name', 'web')->first();

            $papel?->givePermissionTo(self::PERMISSOES);
        }
    }
}
