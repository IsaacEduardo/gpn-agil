<?php

use App\Models\Requisicao;
use App\Support\CabecalhoDocumento;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Preenche gabinete_id nas requisições já existentes, derivando-o do criador
 * (usuario→departamento→gabinete) através do mesmo resolvedor usado em runtime,
 * para que o backfill seja idêntico ao que o observer gravaria numa criação nova.
 *
 * Usa update direto (query builder) para não disparar eventos nem alterar
 * updated_at dos registos históricos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Requisicao::query()
            ->whereNull('gabinete_id')
            ->with('usuario')
            ->chunkById(200, function ($requisicoes) {
                foreach ($requisicoes as $requisicao) {
                    $gabineteId = CabecalhoDocumento::gabineteDeUser($requisicao->usuario)?->id;

                    if ($gabineteId) {
                        DB::table('requisicoes')
                            ->where('id', $requisicao->id)
                            ->update(['gabinete_id' => $gabineteId]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Sem reversão: o valor histórico não é distinguível de um preenchimento manual.
    }
};
