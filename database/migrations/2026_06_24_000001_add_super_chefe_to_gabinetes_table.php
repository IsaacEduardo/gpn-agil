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
        Schema::table('gabinetes', function (Blueprint $table) {
            if (! Schema::hasColumn('gabinetes', 'super_chefe_id')) {
                $table->foreignId('super_chefe_id')
                    ->nullable()
                    ->constrained('users')
                    ->onDelete('set null')
                    ->after('responsavel_id');
            }
        });

        // Garantir que a role super-chefe-gabinete é criada na tabela de roles
        if (Schema::hasTable('roles')) {
            $exists = DB::table('roles')->where('name', 'super-chefe-gabinete')->exists();
            if (! $exists) {
                DB::table('roles')->insert([
                    'name' => 'super-chefe-gabinete',
                    'description' => 'Super Chefe de Gabinete com permissão de auditoria e controle do gabinete',
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gabinetes', function (Blueprint $table) {
            if (Schema::hasColumn('gabinetes', 'super_chefe_id')) {
                $table->dropConstrainedForeignId('super_chefe_id');
            }
        });

        if (Schema::hasTable('roles')) {
            DB::table('roles')->where('name', 'super-chefe-gabinete')->delete();
        }
    }
};
