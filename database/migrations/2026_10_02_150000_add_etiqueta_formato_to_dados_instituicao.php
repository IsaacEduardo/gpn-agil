<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formato do rolo de etiquetas do protocolo (ver App\Support\FormatoEtiqueta).
 * A etiqueta é um PDF com o tamanho exacto do rolo; o formato segue o que está
 * montado nas impressoras térmicas, seja qual for a marca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dados_instituicao', function (Blueprint $table) {
            $table->string('etiqueta_formato', 20)->default('100x50')->after('rodape_img_path');
        });
    }

    public function down(): void
    {
        Schema::table('dados_instituicao', function (Blueprint $table) {
            $table->dropColumn('etiqueta_formato');
        });
    }
};
