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
        Schema::table('anexos', function (Blueprint $table) {
            $table->string('ocr_status', 30)->default('PENDENTE')->after('texto_extraido')->index();
            $table->timestamp('ocr_processado_em')->nullable()->after('ocr_status');
            $table->text('ocr_erro')->nullable()->after('ocr_processado_em');
            $table->unsignedTinyInteger('ocr_tentativas')->default(0)->after('ocr_erro');
            $table->string('ocr_metodo', 50)->nullable()->after('ocr_tentativas');
            $table->unsignedInteger('ocr_palavras_count')->default(0)->after('ocr_metodo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('anexos', function (Blueprint $table) {
            $table->dropIndex(['ocr_status']);
            $table->dropColumn([
                'ocr_status',
                'ocr_processado_em',
                'ocr_erro',
                'ocr_tentativas',
                'ocr_metodo',
                'ocr_palavras_count',
            ]);
        });
    }
};
