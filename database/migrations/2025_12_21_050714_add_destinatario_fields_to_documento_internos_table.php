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
        Schema::table('documento_internos', function (Blueprint $table) {
            $table->string('destinatario_nome')->nullable()->after('titulo');
            $table->string('destinatario_cargo')->nullable()->after('destinatario_nome');
            $table->string('destinatario_orgao')->nullable()->after('destinatario_cargo');
            $table->string('destinatario_local')->nullable()->after('destinatario_orgao');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documento_internos', function (Blueprint $table) {
            $table->dropColumn([
                'destinatario_nome',
                'destinatario_cargo',
                'destinatario_orgao',
                'destinatario_local',
            ]);
        });
    }
};
