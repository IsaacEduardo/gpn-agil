<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Código de ofícios do gabinete (ex.: SEC.GOV.PROV.HLA).
 *
 * Compõe a referência dos ofícios: {nº}/{codigo_oficios}.{sigla do departamento}/{ano}.
 * Não cabe na `sigla` (máx. 10 caracteres) e tem outro propósito, por isso é coluna própria.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('gabinetes', 'codigo_oficios')) {
            Schema::table('gabinetes', function (Blueprint $table) {
                $table->string('codigo_oficios', 40)->nullable()->after('sigla');
            });
        }

        // Secretaria Geral (id 1): só se o registo for de facto a Secretaria Geral e o
        // campo ainda estiver vazio. Noutros ambientes em que o id difira, o código é
        // preenchido pelo formulário do gabinete.
        DB::table('gabinetes')
            ->where('id', 1)
            ->where('nome', 'like', '%Secretaria Geral%')
            ->where(fn ($q) => $q->whereNull('codigo_oficios')->orWhere('codigo_oficios', ''))
            ->update(['codigo_oficios' => 'SEC.GOV.PROV.HLA', 'updated_at' => now()]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('gabinetes', 'codigo_oficios')) {
            Schema::table('gabinetes', function (Blueprint $table) {
                $table->dropColumn('codigo_oficios');
            });
        }
    }
};
