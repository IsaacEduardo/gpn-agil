<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data a partir da qual os livros do gabinete (Ofício, Ordem de Serviço, Nota e
 * Informação/Parecer) são numerados pelo sistema. Antes dela os documentos saem com o
 * número em branco, para preencher à mão no livro em papel: a instituição adoptou o
 * sistema a meio de 2026 e só passa a numerar no sistema a partir de 01/01/2027.
 * Sem valor, a numeração é sempre automática.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('dados_instituicao', 'numeracao_gabinete_desde')) {
            Schema::table('dados_instituicao', function (Blueprint $table) {
                $table->date('numeracao_gabinete_desde')->nullable();
            });

            DB::table('dados_instituicao')->update(['numeracao_gabinete_desde' => '2027-01-01']);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('dados_instituicao', 'numeracao_gabinete_desde')) {
            Schema::table('dados_instituicao', function (Blueprint $table) {
                $table->dropColumn('numeracao_gabinete_desde');
            });
        }
    }
};
