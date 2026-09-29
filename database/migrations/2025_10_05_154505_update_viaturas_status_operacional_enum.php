<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            // Base de testes: sem este ramo ficava com o CHECK antigo
            // ('Disponível', ...) e nenhuma viatura 'Operacional' era gravável,
            // pelo que o módulo de viaturas não podia ser testado.
            Schema::table('viaturas', function (Blueprint $table) {
                $table->enum('status_operacional', ['Operacional', 'Em manutenção', 'Inoperante'])
                    ->nullable()->default('Operacional')->change();
            });

            return;
        }
        if ($driver !== 'mysql') {
            return;
        }
        // Primeiro modificar o enum para incluir ambos os valores
        DB::statement("ALTER TABLE viaturas MODIFY COLUMN status_operacional ENUM('Disponível', 'Operacional', 'Em manutenção', 'Inoperante') DEFAULT 'Operacional'");

        // Depois atualizar os registros existentes de 'Disponível' para 'Operacional'
        DB::statement("UPDATE viaturas SET status_operacional = 'Operacional' WHERE status_operacional = 'Disponível'");

        // Por fim, remover o valor 'Disponível' do enum
        DB::statement("ALTER TABLE viaturas MODIFY COLUMN status_operacional ENUM('Operacional', 'Em manutenção', 'Inoperante') DEFAULT 'Operacional'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'mysql') {
            return;
        }
        // Modificar o enum de volta para usar 'Disponível'
        DB::statement("ALTER TABLE viaturas MODIFY COLUMN status_operacional ENUM('Disponível', 'Em manutenção', 'Inoperante') DEFAULT 'Disponível'");

        // Atualizar os registros existentes de 'Operacional' para 'Disponível'
        DB::statement("UPDATE viaturas SET status_operacional = 'Disponível' WHERE status_operacional = 'Operacional'");
    }
};
