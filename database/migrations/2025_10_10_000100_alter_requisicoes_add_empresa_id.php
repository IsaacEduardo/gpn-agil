<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisicoes', function (Blueprint $table) {
            if (! Schema::hasColumn('requisicoes', 'empresa_id')) {
                $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete()->after('usuario_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('requisicoes', function (Blueprint $table) {
            if (Schema::hasColumn('requisicoes', 'empresa_id')) {
                $table->dropConstrainedForeignId('empresa_id');
            }
        });
    }
};
