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
        Schema::create('solicitacoes_atribuicao', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('numero_protocolo')->unique(); // Ex: SOL-2026/00001
            $table->foreignId('requerente_id')->constrained('requerentes')->cascadeOnDelete();
            $table->foreignId('lote_id')->nullable()->constrained('lotes')->nullOnDelete();
            $table->enum('finalidade_uso', [
                'RESIDENCIAL',
                'COMERCIAL',
                'AGROPECUARIA',
                'INDUSTRIAL',
                'SOCIAL_INSTITUCIONAL'
            ])->default('RESIDENCIAL');
            $table->enum('modalidade_atribuicao', [
                'CDRU',
                'COMPRA_VENDA',
                'PERMISSAO_USO',
                'ARRENDAMENTO'
            ])->default('CDRU');
            $table->enum('status', [
                'RASCUNHO',
                'SUBMETIDO',
                'EM_TRIAGEM',
                'EM_VISTORIA',
                'EM_ANALISE_JURIDICA',
                'AGUARDANDO_HOMOLOGACAO',
                'APROVADO',
                'REJEITADO',
                'CANCELADO'
            ])->default('RASCUNHO');
            $table->foreignId('documento_entrada_id')->nullable()->constrained('documentos_entradas')->nullOnDelete();
            $table->foreignId('documento_interno_termo_id')->nullable()->constrained('documento_internos')->nullOnDelete();
            $table->timestamp('data_solicitacao')->useCurrent();
            $table->timestamp('data_homologacao')->nullable();
            $table->text('motivo_rejeicao')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitacoes_atribuicao');
    }
};
