<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quem contribuiu para cada versão criada na edição colaborativa. Até aqui a versão
 * ficava só em nome de quem carregou em "Guardar versão", e o registo de quem
 * escreveu (user_id do log Yjs) apagava-se na compactação.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documento_versaos', function (Blueprint $table) {
            if (! Schema::hasColumn('documento_versaos', 'contribuidores')) {
                $table->json('contribuidores')->nullable()->after('criado_por');
            }
        });
    }

    public function down(): void
    {
        Schema::table('documento_versaos', function (Blueprint $table) {
            if (Schema::hasColumn('documento_versaos', 'contribuidores')) {
                $table->dropColumn('contribuidores');
            }
        });
    }
};
