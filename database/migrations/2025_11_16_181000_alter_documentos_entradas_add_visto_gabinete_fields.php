<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos_entradas', function (Blueprint $table) {
            if (! Schema::hasColumn('documentos_entradas', 'visto_gabinete_status')) {
                $table->string('visto_gabinete_status', 20)->nullable()->after('visto_departamento_observacao');
            }
            if (! Schema::hasColumn('documentos_entradas', 'visto_gabinete_por')) {
                $table->foreignId('visto_gabinete_por')->nullable()->constrained('users')->nullOnDelete()->after('visto_gabinete_status');
            }
            if (! Schema::hasColumn('documentos_entradas', 'visto_gabinete_data')) {
                $table->dateTime('visto_gabinete_data')->nullable()->after('visto_gabinete_por');
            }
            if (! Schema::hasColumn('documentos_entradas', 'visto_gabinete_observacao')) {
                $table->text('visto_gabinete_observacao')->nullable()->after('visto_gabinete_data');
            }
            $table->index('visto_gabinete_status');
        });
    }

    public function down(): void
    {
        Schema::table('documentos_entradas', function (Blueprint $table) {
            if (Schema::hasColumn('documentos_entradas', 'visto_gabinete_observacao')) {
                $table->dropColumn('visto_gabinete_observacao');
            }
            if (Schema::hasColumn('documentos_entradas', 'visto_gabinete_data')) {
                $table->dropColumn('visto_gabinete_data');
            }
            if (Schema::hasColumn('documentos_entradas', 'visto_gabinete_por')) {
                $table->dropConstrainedForeignId('visto_gabinete_por');
            }
            if (Schema::hasColumn('documentos_entradas', 'visto_gabinete_status')) {
                $table->dropColumn('visto_gabinete_status');
            }
        });
    }
};
