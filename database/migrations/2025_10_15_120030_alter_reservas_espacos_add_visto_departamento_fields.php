<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservas_espacos', function (Blueprint $table) {
            if (! Schema::hasColumn('reservas_espacos', 'visto_departamento_status')) {
                $table->string('visto_departamento_status', 20)
                    ->nullable()
                    ->after('status');
            }
            if (! Schema::hasColumn('reservas_espacos', 'visto_departamento_por')) {
                $table->foreignId('visto_departamento_por')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete()
                    ->after('visto_departamento_status');
            }
            if (! Schema::hasColumn('reservas_espacos', 'visto_departamento_data')) {
                $table->dateTime('visto_departamento_data')
                    ->nullable()
                    ->after('visto_departamento_por');
            }
            if (! Schema::hasColumn('reservas_espacos', 'visto_departamento_observacao')) {
                $table->text('visto_departamento_observacao')
                    ->nullable()
                    ->after('visto_departamento_data');
            }
            $table->index('visto_departamento_status');
        });
    }

    public function down(): void
    {
        Schema::table('reservas_espacos', function (Blueprint $table) {
            if (Schema::hasColumn('reservas_espacos', 'visto_departamento_observacao')) {
                $table->dropColumn('visto_departamento_observacao');
            }
            if (Schema::hasColumn('reservas_espacos', 'visto_departamento_data')) {
                $table->dropColumn('visto_departamento_data');
            }
            if (Schema::hasColumn('reservas_espacos', 'visto_departamento_por')) {
                $table->dropConstrainedForeignId('visto_departamento_por');
            }
            if (Schema::hasColumn('reservas_espacos', 'visto_departamento_status')) {
                $table->dropColumn('visto_departamento_status');
            }
        });
    }
};
