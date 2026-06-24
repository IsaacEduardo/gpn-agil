<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documento_internos', function (Blueprint $table) {
            $table->integer('versao_atual')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('documento_internos', function (Blueprint $table) {
            $table->dropColumn('versao_atual');
        });
    }
};
