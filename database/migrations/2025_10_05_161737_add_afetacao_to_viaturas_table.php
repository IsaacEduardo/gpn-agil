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
            $table->string('afetacao')->nullable()->after('status_operacional');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('viaturas', function (Blueprint $table) {
            $table->dropColumn('afetacao');
        });
    }
};
