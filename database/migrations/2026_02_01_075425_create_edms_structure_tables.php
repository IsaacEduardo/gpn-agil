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
        // 1. Tabela de Temporalidade
        if (! Schema::hasTable('retention_schedules')) {
            Schema::create('retention_schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('documento_especie_id')->constrained('documento_especies')->cascadeOnDelete();
                $table->integer('temporalidade_anos')->comment('Tempo de guarda em anos');
                $table->enum('acao_final', ['arquivar', 'eliminar'])->default('arquivar');
                $table->text('observacoes')->nullable();
                $table->timestamps();
            });
        }

        // 2. Melhorias na tabela de Versões (Suporte a Arquivos Físicos e Integridade)
        Schema::table('documento_versaos', function (Blueprint $table) {
            if (! Schema::hasColumn('documento_versaos', 'caminho_arquivo')) {
                $table->string('caminho_arquivo')->nullable()->after('conteudo_final')->comment('Caminho no storage');
            }
            if (! Schema::hasColumn('documento_versaos', 'checksum')) {
                $table->string('checksum', 64)->nullable()->after('caminho_arquivo')->comment('SHA-256 Hash');
            }
            if (! Schema::hasColumn('documento_versaos', 'tamanho_bytes')) {
                $table->unsignedBigInteger('tamanho_bytes')->nullable()->after('checksum');
            }
            if (! Schema::hasColumn('documento_versaos', 'mime_type')) {
                $table->string('mime_type')->nullable()->after('tamanho_bytes');
            }
            if (! Schema::hasColumn('documento_versaos', 'is_signed')) {
                $table->boolean('is_signed')->default(false)->after('mime_type');
            }
            if (! Schema::hasColumn('documento_versaos', 'assinatura_hash')) {
                $table->text('assinatura_hash')->nullable()->after('is_signed');
            }
        });

        // 3. Vincular Documentos Internos a Pastas
        Schema::table('documento_internos', function (Blueprint $table) {
            if (! Schema::hasColumn('documento_internos', 'pasta_id')) {
                $table->foreignId('pasta_id')->nullable()->constrained('pastas')->nullOnDelete()->after('departamento_id');
            }
        });

        // 4. Metadados de Arquivo (para indexação avançada)
        if (! Schema::hasTable('file_metadata')) {
            Schema::create('file_metadata', function (Blueprint $table) {
                $table->id();
                $table->string('metadatable_type'); // DocumentoInterno, DocumentoEntrada
                $table->unsignedBigInteger('metadatable_id');
                $table->string('key');
                $table->text('value');
                $table->timestamps();

                $table->index(['metadatable_type', 'metadatable_id']);
                $table->index('key');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_metadata');

        if (Schema::hasColumn('documento_internos', 'pasta_id')) {
            Schema::table('documento_internos', function (Blueprint $table) {
                // Check if foreign key exists before dropping?
                // Hard to check FK name reliably without query, assuming standard naming or ignoring error
                try {
                    $table->dropForeign(['pasta_id']);
                } catch (\Exception $e) {
                }
                $table->dropColumn('pasta_id');
            });
        }

        Schema::table('documento_versaos', function (Blueprint $table) {
            $table->dropColumn(['caminho_arquivo', 'checksum', 'tamanho_bytes', 'mime_type', 'is_signed', 'assinatura_hash']);
        });

        Schema::dropIfExists('retention_schedules');
    }
};
