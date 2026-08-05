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
        Schema::create('lotes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('codigo_lote')->unique(); // Ex: LOTE-NAM-2026-0001
            $table->string('matricula_cartoraria')->nullable();
            $table->string('inscricao_imobiliaria')->nullable();
            $table->string('municipio')->default('Namibe');
            $table->string('comuna')->nullable();
            $table->string('bairro_distrito')->nullable();
            $table->string('zona_setor')->nullable();
            $table->decimal('area_m2', 14, 2)->default(0);
            $table->decimal('perimetro_m', 12, 2)->nullable();
            $table->enum('zoneamento', [
                'HABITACIONAL',
                'COMERCIAL',
                'INDUSTRIAL',
                'AGRICOLA',
                'EQUIPAMENTO_PUBLICO',
                'MISTO'
            ])->default('HABITACIONAL');
            $table->enum('status', [
                'DISPONIVEL',
                'RESERVADO',
                'ATRIBUIDO',
                'EM_LICITACAO',
                'INDISPONIVEL'
            ])->default('DISPONIVEL');
            $table->decimal('latitude_centro', 10, 7)->nullable();
            $table->decimal('longitude_centro', 10, 7)->nullable();
            $table->longText('geojson_geometria')->nullable(); // Polígono em GeoJSON para Leaflet.js
            $table->text('observacoes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lotes');
    }
};
