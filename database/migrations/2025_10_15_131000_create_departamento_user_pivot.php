<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamento_user', function (Blueprint $table) {
            $table->unsignedBigInteger('departamento_id');
            $table->unsignedBigInteger('user_id');
            $table->primary(['departamento_id', 'user_id']);
            $table->foreign('departamento_id')->references('id')->on('departamentos')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Backfill: inserir vínculos existentes de users.departamento_id na pivot
        $rows = DB::table('users')->whereNotNull('departamento_id')->select('id as user_id', 'departamento_id')->get();
        $insert = [];
        foreach ($rows as $row) {
            $insert[] = [
                'departamento_id' => $row->departamento_id,
                'user_id' => $row->user_id,
            ];
        }
        if (! empty($insert)) {
            DB::table('departamento_user')->insert($insert);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('departamento_user');
    }
};
