<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'departamento_id')) {
                $table->foreignId('departamento_id')
                    ->nullable()
                    ->constrained('departamentos')
                    ->nullOnDelete()
                    ->after('role_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'departamento_id')) {
                $table->dropConstrainedForeignId('departamento_id');
            }
        });
    }
};
