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
        Schema::create('analises_tecnicas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('solicitacao_atribuicao_id')->constrained('solicitacoes_atribuicao')->cascadeOnDelete();
            $table->foreignId('tecnico_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('data_vistoria');
            $table->longText('parecer_tecnico');
            $table->enum('viabilidade', [
                'FAVORAVEL',
                'FAVORAVEL_COM_RESTRICOES',
                'DESFAVORAVEL'
            ])->default('FAVORAVEL');
            $table->string('coordenadas_vistoria')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analises_tecnicas');
    }
};
