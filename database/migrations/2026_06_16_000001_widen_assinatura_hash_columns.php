<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A assinatura digital real (RSA, base64) ocupa ~344+ caracteres, ultrapassando o
 * varchar(255) padrão. Alarga a coluna para 'text' em documento_internos e requisicoes,
 * garantindo que a assinatura por certificado funcione em MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documento_internos', function (Blueprint $table) {
            $table->text('assinatura_hash')->nullable()->change();
        });

        Schema::table('requisicoes', function (Blueprint $table) {
            $table->text('assinatura_hash')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('documento_internos', function (Blueprint $table) {
            $table->string('assinatura_hash')->nullable()->change();
        });

        Schema::table('requisicoes', function (Blueprint $table) {
            $table->string('assinatura_hash')->nullable()->change();
        });
    }
};
