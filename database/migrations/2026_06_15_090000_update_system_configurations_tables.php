<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Alterar tabela empresas
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('nif', 20)->nullable()->after('nome');
            $table->string('telefone_principal', 30)->nullable()->after('contacto');
            $table->string('email_institucional', 100)->nullable()->after('telefone_principal');
            $table->string('logo_path')->nullable()->after('endereco');
        });

        // 2. Alterar tabela gabinetes
        Schema::table('gabinetes', function (Blueprint $table) {
            $table->integer('sla_despacho_dias')->default(2)->after('responsavel_id');
        });

        // 3. Alterar tabela departamentos
        Schema::table('departamentos', function (Blueprint $table) {
            $table->unsignedBigInteger('responsavel_id')->nullable()->after('sigla');
            $table->unsignedBigInteger('parent_id')->nullable()->after('responsavel_id');

            $table->foreign('responsavel_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('parent_id')->references('id')->on('departamentos')->onDelete('cascade');
        });

        // 4. Alterar tabela users (para delegações)
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('delegado_id')->nullable()->after('departamento_id');
            $table->dateTime('delegacao_inicio')->nullable()->after('delegado_id');
            $table->dateTime('delegacao_fim')->nullable()->after('delegacao_inicio');

            $table->foreign('delegado_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['delegado_id']);
            $table->dropColumn(['delegado_id', 'delegacao_inicio', 'delegacao_fim']);
        });

        Schema::table('departamentos', function (Blueprint $table) {
            $table->dropForeign(['responsavel_id']);
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['responsavel_id', 'parent_id']);
        });

        Schema::table('gabinetes', function (Blueprint $table) {
            $table->dropColumn('sla_despacho_dias');
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['nif', 'telefone_principal', 'email_institucional', 'logo_path']);
        });
    }
};
