<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Responsável da instituição (ex.: o Governador Provincial), para os modelos de
 * documento. Nome e cargo em texto livre: o Governador pode não ter conta no
 * sistema, e o cargo cobre "Governadora", "Vice-Governador em exercício", etc.
 *
 * Sem histórico de mandatos: o documento grava o conteúdo já resolvido, pelo
 * que os emitidos mantêm o nome de quem estava em funções.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dados_instituicao', function (Blueprint $table) {
            $table->string('responsavel_nome', 150)->nullable()->after('endereco');
            $table->string('responsavel_cargo', 150)->nullable()->after('responsavel_nome');
        });
    }

    public function down(): void
    {
        Schema::table('dados_instituicao', function (Blueprint $table) {
            $table->dropColumn(['responsavel_nome', 'responsavel_cargo']);
        });
    }
};
