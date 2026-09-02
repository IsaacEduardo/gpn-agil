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
        Schema::table('viaturas', function (Blueprint $table) {
            if (! Schema::hasColumn('viaturas', 'motor_numero')) {
                $table->string('motor_numero')->nullable()->after('ano');
            }
            if (! Schema::hasColumn('viaturas', 'cor')) {
                $table->string('cor')->nullable()->after('motor_numero');
            }
        });

        Schema::table('termos_entrega', function (Blueprint $table) {
            if (! Schema::hasColumn('termos_entrega', 'tipo_credencial')) {
                $table->string('tipo_credencial')->default('utilizacao_normal')->after('tipo');
            }
            if (! Schema::hasColumn('termos_entrega', 'origem_viagem')) {
                $table->string('origem_viagem')->nullable()->after('beneficiario_setor');
            }
            if (! Schema::hasColumn('termos_entrega', 'destino_viagem')) {
                $table->string('destino_viagem')->nullable()->after('origem_viagem');
            }
            if (! Schema::hasColumn('termos_entrega', 'instituicao_vinculo')) {
                $table->string('instituicao_vinculo')->nullable()->after('destino_viagem');
            }
            if (! Schema::hasColumn('termos_entrega', 'motor_numero')) {
                $table->string('motor_numero')->nullable()->after('instituicao_vinculo');
            }
            if (! Schema::hasColumn('termos_entrega', 'cor_viatura')) {
                $table->string('cor_viatura')->nullable()->after('motor_numero');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('viaturas', function (Blueprint $table) {
            $table->dropColumn(['motor_numero', 'cor']);
        });

        Schema::table('termos_entrega', function (Blueprint $table) {
            $table->dropColumn([
                'tipo_credencial',
                'origem_viagem',
                'destino_viagem',
                'instituicao_vinculo',
                'motor_numero',
                'cor_viatura',
            ]);
        });
    }
};
