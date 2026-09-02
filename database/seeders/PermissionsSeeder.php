<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Define permissions by module
        $permissions = [
            // 1. Gestão de Documentos (Entradas)
            'documentos_entrada.listar',
            'documentos_entrada.criar',
            'documentos_entrada.editar',
            'documentos_entrada.encaminhar',
            'documentos_entrada.arquivar',

            // 2. Gestão de Documentos (Internos)
            'documentos_interno.listar',
            'documentos_interno.criar',
            'documentos_interno.editar',
            'documentos_interno.assinar',

            // 3. Logística e Frota
            'viaturas.listar',
            'viaturas.gerir',
            'viaturas.relatorios',

            // 4. Requisições (Central de Pedidos)
            'requisicoes.listar',
            'requisicoes.listar_todas',
            'requisicoes.criar',
            'requisicoes.aprovar',
            'requisicoes.atender',

            // 5. Administração do Sistema
            'usuarios.gerir',
            'departamentos.gerir',
            'permissoes.gerir',
            'configuracoes.editar',

            // 6. Reservas
            'reservas.listar',
            'reservas.listar_todas', // Ver todas as reservas (admin/gestor)
            'reservas.criar',
            'reservas.editar',       // Editar qualquer reserva
            'reservas.eliminar',     // Excluir qualquer reserva
            'reservas.cancelar',     // Cancelar qualquer reserva
            'reservas.aprovar',

            // 8. Credenciais e Termos
            'credenciais.listar',
            'credenciais.criar',
            'credenciais.editar',
            'credenciais.eliminar',
            'termos_entrega.listar',
            'termos_entrega.criar',
            'termos_entrega.editar',
            'termos_entrega.eliminar',

            // 9. Feedbacks do Sistema
            'visualizar_feedbacks',
            'visualizar_relatorios',
            'excluir_feedbacks',

            // 7. Painel
            'dashboard.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create Roles and assign existing permissions

        // Admin: All permissions
        $roleAdmin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $roleAdmin->givePermissionTo(Permission::all());

        // Chefe de Departamento: Approval and Management within scope
        $roleChefe = Role::firstOrCreate(['name' => 'chefe-departamento', 'guard_name' => 'web']);
        $roleChefe->givePermissionTo([
            'dashboard.view',
            'documentos_entrada.listar',
            'documentos_entrada.encaminhar',
            'documentos_interno.listar',
            'documentos_interno.criar',
            'documentos_interno.editar',
            'documentos_interno.assinar',
            'requisicoes.listar',
            'requisicoes.listar_todas',
            'requisicoes.criar',
            'requisicoes.aprovar',
            'viaturas.listar',
            'reservas.listar',
            'reservas.criar',
            'reservas.aprovar',
        ]);

        // User (Funcionário / Técnico)
        $roleUser = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $roleUser->givePermissionTo([
            'dashboard.view',
            'viaturas.listar',
            'credenciais.listar',
            'credenciais.criar',
        ]);

        $roleTecnico = Role::firstOrCreate(['name' => 'tecnico', 'guard_name' => 'web']);
        $roleTecnico->givePermissionTo([
            'dashboard.view',
            'viaturas.listar',
            'credenciais.listar',
            'credenciais.criar',
        ]);

        // Logística: Fleet and Request Fulfillment
        $roleLogistica = Role::firstOrCreate(['name' => 'gestor-logistica', 'guard_name' => 'web']);
        $roleLogistica->givePermissionTo([
            'dashboard.view',
            'viaturas.listar',
            'viaturas.gerir',
            'viaturas.relatorios',
            'requisicoes.listar',
            'requisicoes.atender',
            'credenciais.listar',
            'credenciais.criar',
            'credenciais.editar',
            'credenciais.eliminar',
            'termos_entrega.listar',
            'termos_entrega.criar',
            'termos_entrega.editar',
            'termos_entrega.eliminar',
        ]);
    }
}
