<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $teams = config('permission.teams');

        // 1. Adapt 'permissions' table
        if (Schema::hasTable('permissions')) {
            Schema::table('permissions', function (Blueprint $table) {
                if (! Schema::hasColumn('permissions', 'guard_name')) {
                    $table->string('guard_name')->default('web')->after('name');
                }
            });
        } else {
            // Fallback if not exists (unlikely based on check)
            Schema::create('permissions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });
        }

        // 2. Adapt 'roles' table
        if (Schema::hasTable('roles')) {
            Schema::table('roles', function (Blueprint $table) {
                if (! Schema::hasColumn('roles', 'guard_name')) {
                    $table->string('guard_name')->default('web')->after('name');
                }
            });
        } else {
            Schema::create('roles', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });
        }

        // 3. Create 'model_has_permissions'
        if (! Schema::hasTable('model_has_permissions')) {
            Schema::create('model_has_permissions', function (Blueprint $table) use ($tableNames, $columnNames, $teams) {
                $table->unsignedBigInteger('permission_id');
                $table->string('model_type');
                $table->unsignedBigInteger($columnNames['model_morph_key']);
                $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_model_id_model_type_index');

                $table->foreign('permission_id')
                    ->references('id')
                    ->on($tableNames['permissions'])
                    ->onDelete('cascade');

                if ($teams) {
                    $table->unsignedBigInteger($columnNames['team_foreign_key']);
                    $table->index($columnNames['team_foreign_key'], 'model_has_permissions_team_foreign_key_index');

                    $table->primary(['permission_id', $columnNames['model_morph_key'], 'model_type', $columnNames['team_foreign_key']],
                        'model_has_permissions_permission_model_type_primary');
                } else {
                    $table->primary(['permission_id', $columnNames['model_morph_key'], 'model_type'],
                        'model_has_permissions_permission_model_type_primary');
                }
            });
        }

        // 4. Create 'model_has_roles'
        if (! Schema::hasTable('model_has_roles')) {
            Schema::create('model_has_roles', function (Blueprint $table) use ($tableNames, $columnNames, $teams) {
                $table->unsignedBigInteger('role_id');
                $table->string('model_type');
                $table->unsignedBigInteger($columnNames['model_morph_key']);
                $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_roles_model_id_model_type_index');

                $table->foreign('role_id')
                    ->references('id')
                    ->on($tableNames['roles'])
                    ->onDelete('cascade');

                if ($teams) {
                    $table->unsignedBigInteger($columnNames['team_foreign_key']);
                    $table->index($columnNames['team_foreign_key'], 'model_has_roles_team_foreign_key_index');

                    $table->primary(['role_id', $columnNames['model_morph_key'], 'model_type', $columnNames['team_foreign_key']],
                        'model_has_roles_role_model_type_primary');
                } else {
                    $table->primary(['role_id', $columnNames['model_morph_key'], 'model_type'],
                        'model_has_roles_role_model_type_primary');
                }
            });
        }

        // 5. Create 'role_has_permissions'
        // Existing table 'permission_role' might exist. Spatie uses 'role_has_permissions'.
        // We will create Spatie's table and migrate data if permission_role exists.
        if (! Schema::hasTable('role_has_permissions')) {
            Schema::create('role_has_permissions', function (Blueprint $table) use ($tableNames) {
                $table->unsignedBigInteger('permission_id');
                $table->unsignedBigInteger('role_id');

                $table->foreign('permission_id')
                    ->references('id')
                    ->on($tableNames['permissions'])
                    ->onDelete('cascade');

                $table->foreign('role_id')
                    ->references('id')
                    ->on($tableNames['roles'])
                    ->onDelete('cascade');

                $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
            });
        }

        // DATA MIGRATION

        // Migrate User roles
        // Assuming 'users' table has 'role_id'
        if (Schema::hasColumn('users', 'role_id')) {
            $users = DB::table('users')->whereNotNull('role_id')->get();
            foreach ($users as $user) {
                // Check if entry already exists to avoid duplicates
                $exists = DB::table('model_has_roles')
                    ->where('model_id', $user->id)
                    ->where('model_type', 'App\Models\User')
                    ->where('role_id', $user->role_id)
                    ->exists();

                if (! $exists) {
                    DB::table('model_has_roles')->insert([
                        'role_id' => $user->role_id,
                        'model_type' => 'App\Models\User',
                        'model_id' => $user->id,
                    ]);
                }
            }
        }

        // Migrate Permission Role Pivot
        // Assuming existing table is 'permission_role' with 'permission_id' and 'role_id'
        if (Schema::hasTable('permission_role')) {
            $pivots = DB::table('permission_role')->get();
            foreach ($pivots as $pivot) {
                $exists = DB::table('role_has_permissions')
                    ->where('permission_id', $pivot->permission_id)
                    ->where('role_id', $pivot->role_id)
                    ->exists();

                if (! $exists) {
                    DB::table('role_has_permissions')->insert([
                        'permission_id' => $pivot->permission_id,
                        'role_id' => $pivot->role_id,
                    ]);
                }
            }
        }

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We do not drop tables as they might have existed before.
        // We only drop the ones we definitely created if we were sure they didn't exist,
        // but for safety in this hybrid approach, we mainly strip columns.

        $tableNames = config('permission.table_names');

        if (Schema::hasTable('roles')) {
            Schema::table('roles', function (Blueprint $table) {
                if (Schema::hasColumn('roles', 'guard_name')) {
                    $table->dropColumn('guard_name');
                }
            });
        }
        if (Schema::hasTable('permissions')) {
            Schema::table('permissions', function (Blueprint $table) {
                if (Schema::hasColumn('permissions', 'guard_name')) {
                    $table->dropColumn('guard_name');
                }
            });
        }

        // Dropping these might lose data if we revert, but standard behavior implies rolling back creation.
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('role_has_permissions');
    }
};
