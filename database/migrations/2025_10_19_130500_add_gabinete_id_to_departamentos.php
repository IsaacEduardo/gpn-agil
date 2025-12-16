<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departamentos', function (Blueprint $table) {
            $table->foreignId('gabinete_id')->nullable()->after('sigla')->constrained('gabinetes')->onDelete('set null');
            $table->index('gabinete_id');
        });
    }

    public function down(): void
    {
        Schema::table('departamentos', function (Blueprint $table) {
            $table->dropForeign(['gabinete_id']);
            $table->dropColumn('gabinete_id');
        });
    }
};
