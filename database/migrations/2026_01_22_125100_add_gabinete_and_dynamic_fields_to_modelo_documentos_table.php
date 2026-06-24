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
        Schema::table('modelo_documentos', function (Blueprint $table) {
            $table->foreignId('gabinete_id')->nullable()->after('user_id')->constrained('gabinetes')->nullOnDelete();
            $table->json('campos_dinamicos')->nullable()->after('conteudo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('modelo_documentos', function (Blueprint $table) {
            $table->dropForeign(['gabinete_id']);
            $table->dropColumn(['gabinete_id', 'campos_dinamicos']);
        });
    }
};
