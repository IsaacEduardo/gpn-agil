<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        // Index no pivot para acelerar consultas por user_id (belongsToMany)
        if (Schema::hasTable('departamento_user')) {
            Schema::table('departamento_user', function (Blueprint $table) {
                // Adiciona índice simples em user_id para consultas do tipo where pivot.user_id = ?
                $table->index('user_id', 'departamento_user_user_id_index');
            });
        }

        // Índice composto para filtros combinados por role_id e departamento_id em users
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['role_id', 'departamento_id'], 'users_role_departamento_idx');
            });
        }

        // Índice (se necessário) em roles.name para buscas por nome de papel
        if (Schema::hasTable('roles') && $driver === 'mysql') {
            $existing = DB::select("SHOW INDEX FROM roles WHERE Column_name = 'name'");
            if (empty($existing)) {
                Schema::table('roles', function (Blueprint $table) {
                    $table->index('name', 'roles_name_index');
                });
            }
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (Schema::hasTable('departamento_user')) {
            Schema::table('departamento_user', function (Blueprint $table) {
                $table->dropIndex('departamento_user_user_id_index');
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex('users_role_departamento_idx');
            });
        }

        if (Schema::hasTable('roles') && $driver === 'mysql') {
            // Só remove o índice se foi criado com este nome
            $existing = DB::select("SHOW INDEX FROM roles WHERE Key_name = 'roles_name_index'");
            if (! empty($existing)) {
                Schema::table('roles', function (Blueprint $table) {
                    $table->dropIndex('roles_name_index');
                });
            }
        }
    }
};
