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
        if ($driver === 'mysql') {
            // Remover constraint da chave estrangeira e tornar requisicao_id opcional
            try {
                DB::statement('ALTER TABLE termos_entrega DROP FOREIGN KEY termos_entrega_requisicao_id_foreign');
            } catch (\Throwable $e) {
                // Ignorar caso constraint já tenha sido removida
            }

            // Tornar a coluna requisicao_id opcional
            try {
                DB::statement('ALTER TABLE termos_entrega MODIFY requisicao_id BIGINT UNSIGNED NULL');
            } catch (\Throwable $e) {
                // Ignorar em bancos sem suporte
            }

            // Ajustar enum de tipo para suportar os novos tipos autônomos
            try {
                DB::statement("ALTER TABLE termos_entrega MODIFY tipo ENUM('definitiva','devolutivo','viatura') DEFAULT 'definitiva'");
            } catch (\Throwable $e) {
                // Caso o banco não suporte ENUM, manter coluna como está
            }
        }

        // Adicionar campos independentes
        Schema::table('termos_entrega', function (Blueprint $table) {
            if (! Schema::hasColumn('termos_entrega', 'beneficiario_nome')) {
                $table->string('beneficiario_nome')->nullable()->after('requisicao_id');
            }
            if (! Schema::hasColumn('termos_entrega', 'beneficiario_documento')) {
                $table->string('beneficiario_documento')->nullable()->after('beneficiario_nome');
            }
            if (! Schema::hasColumn('termos_entrega', 'beneficiario_setor')) {
                $table->string('beneficiario_setor')->nullable()->after('beneficiario_documento');
            }
            if (! Schema::hasColumn('termos_entrega', 'item_descricao')) {
                $table->string('item_descricao')->nullable()->after('beneficiario_setor');
            }
            if (! Schema::hasColumn('termos_entrega', 'quantidade')) {
                $table->integer('quantidade')->nullable()->after('item_descricao');
            }
            if (! Schema::hasColumn('termos_entrega', 'unidade')) {
                $table->string('unidade')->nullable()->after('quantidade');
            }
            if (! Schema::hasColumn('termos_entrega', 'observacoes')) {
                $table->text('observacoes')->nullable()->after('unidade');
            }
            if (! Schema::hasColumn('termos_entrega', 'viatura_id')) {
                $table->foreignId('viatura_id')->nullable()->constrained('viaturas')->nullOnDelete()->after('observacoes');
            }
        });
    }

    public function down(): void
    {
        // Opcional: manter alterações (sem reversão completa para evitar perda de dados)
    }
};
