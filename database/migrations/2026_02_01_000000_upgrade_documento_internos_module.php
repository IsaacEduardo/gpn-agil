<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Atualizar documento_internos
        Schema::table('documento_internos', function (Blueprint $table) {
            $table->integer('versao_major')->default(0)->after('versao_atual');
            $table->integer('versao_minor')->default(0)->after('versao_major');
            $table->integer('versao_patch')->default(0)->after('versao_minor');
            // Status já é string, então não precisa alterar o tipo, apenas a lógica de uso mudará.
        });

        // 2. Atualizar documento_versaos
        Schema::table('documento_versaos', function (Blueprint $table) {
            $table->integer('major')->default(0)->after('versao');
            $table->integer('minor')->default(0)->after('major');
            $table->integer('patch')->default(0)->after('minor');
            $table->text('change_log')->nullable()->after('conteudo_final');
        });

        // 3. Criar tabela user_certificates
        Schema::create('user_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('encrypted_p12'); // Conteúdo do .p12 criptografado
            $table->text('public_key');    // Chave pública extraída para validação rápida
            $table->string('issuer')->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_certificates');

        Schema::table('documento_versaos', function (Blueprint $table) {
            $table->dropColumn(['major', 'minor', 'patch', 'change_log']);
        });

        Schema::table('documento_internos', function (Blueprint $table) {
            $table->dropColumn(['versao_major', 'versao_minor', 'versao_patch']);
        });
    }
};
