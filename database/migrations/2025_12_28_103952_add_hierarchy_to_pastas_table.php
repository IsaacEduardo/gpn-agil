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
        Schema::table('pastas', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('pastas')->onDelete('cascade');
            $table->boolean('is_system')->default(false)->after('descricao');
            $table->string('type')->default('custom')->after('is_system'); // entrada, saida, interno, custom
            $table->string('path')->nullable()->after('type'); // Materialized path for easier querying
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pastas', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'is_system', 'type', 'path']);
        });
    }
};
