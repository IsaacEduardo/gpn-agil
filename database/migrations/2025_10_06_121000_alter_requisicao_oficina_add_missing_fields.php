<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisicao_oficina', function (Blueprint $table) {
            if (! Schema::hasColumn('requisicao_oficina', 'quilometragem_atual')) {
                $table->integer('quilometragem_atual')->nullable()->after('viatura_id');
            }
            if (! Schema::hasColumn('requisicao_oficina', 'servicos_solicitados')) {
                $table->text('servicos_solicitados')->nullable()->after('descricao_problema');
            }
            if (! Schema::hasColumn('requisicao_oficina', 'urgencia')) {
                $table->enum('urgencia', ['baixa', 'media', 'alta', 'critica'])->default('media')->after('servicos_solicitados');
            }
        });
    }

    public function down(): void
    {
        Schema::table('requisicao_oficina', function (Blueprint $table) {
            $table->dropColumn(['quilometragem_atual', 'servicos_solicitados', 'urgencia']);
        });
    }
};
