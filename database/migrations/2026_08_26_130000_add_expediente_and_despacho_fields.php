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
        // 1. Adicionar flag is_area_expediente em departamentos
        if (Schema::hasTable('departamentos') && ! Schema::hasColumn('departamentos', 'is_area_expediente')) {
            Schema::table('departamentos', function (Blueprint $table) {
                $table->boolean('is_area_expediente')->default(false)->after('gabinete_id');
            });
        }

        // 2. Adicionar campos de despacho na tabela documentos_entradas
        if (Schema::hasTable('documentos_entradas')) {
            Schema::table('documentos_entradas', function (Blueprint $table) {
                if (! Schema::hasColumn('documentos_entradas', 'texto_despacho')) {
                    $table->text('texto_despacho')->nullable()->after('observacoes');
                }
                if (! Schema::hasColumn('documentos_entradas', 'despachado_por_id')) {
                    $table->foreignId('despachado_por_id')
                        ->nullable()
                        ->after('texto_despacho')
                        ->constrained('users')
                        ->nullOnDelete();
                }
                if (! Schema::hasColumn('documentos_entradas', 'data_despacho')) {
                    $table->dateTime('data_despacho')->nullable()->after('despachado_por_id');
                }
            });
        }

        // 3. Criar tabela pivô documento_entrada_departamentos_destino para múltiplos destinatários
        if (! Schema::hasTable('documento_entrada_departamentos_destino')) {
            Schema::create('documento_entrada_departamentos_destino', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('documento_entrada_id');
                $table->unsignedBigInteger('departamento_id');
                $table->timestamps();

                $table->foreign('documento_entrada_id', 'fk_doc_dep_dest_doc')
                    ->references('id')
                    ->on('documentos_entradas')
                    ->onDelete('cascade');
                $table->foreign('departamento_id', 'fk_doc_dep_dest_dep')
                    ->references('id')
                    ->on('departamentos')
                    ->onDelete('cascade');

                $table->unique(['documento_entrada_id', 'departamento_id'], 'doc_dep_destino_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documento_entrada_departamentos_destino');

        if (Schema::hasTable('documentos_entradas')) {
            Schema::table('documentos_entradas', function (Blueprint $table) {
                if (Schema::hasColumn('documentos_entradas', 'despachado_por_id')) {
                    $table->dropForeign(['despachado_por_id']);
                    $table->dropColumn('despachado_por_id');
                }
                if (Schema::hasColumn('documentos_entradas', 'texto_despacho')) {
                    $table->dropColumn('texto_despacho');
                }
                if (Schema::hasColumn('documentos_entradas', 'data_despacho')) {
                    $table->dropColumn('data_despacho');
                }
            });
        }

        if (Schema::hasTable('departamentos') && Schema::hasColumn('departamentos', 'is_area_expediente')) {
            Schema::table('departamentos', function (Blueprint $table) {
                $table->dropColumn('is_area_expediente');
            });
        }
    }
};
