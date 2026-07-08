<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot do gabinete do criador no momento da criação da requisição.
 *
 * A requisição só guardava usuario_id; o cabeçalho do documento era derivado
 * ao vivo de usuario→departamento→gabinete, o que faria o cabeçalho de
 * documentos históricos mudar se o utilizador fosse transferido. Esta coluna
 * fixa o gabinete à data de emissão, garantindo imutabilidade do documento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisicoes', function (Blueprint $table) {
            $table->foreignId('gabinete_id')
                ->nullable()
                ->after('usuario_id')
                ->constrained('gabinetes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('requisicoes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gabinete_id');
        });
    }
};
