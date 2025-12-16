<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documento_encaminhamentos', function (Blueprint $table) {
            $table->index(['documento_entrada_id', 'recebido_em'], 'de_doc_recebido_idx');
            $table->index('destino_departamento_id', 'de_destino_dep_idx');
        });
    }

    public function down(): void
    {
        Schema::table('documento_encaminhamentos', function (Blueprint $table) {
            $table->dropIndex('de_doc_recebido_idx');
            $table->dropIndex('de_destino_dep_idx');
        });
    }
};
