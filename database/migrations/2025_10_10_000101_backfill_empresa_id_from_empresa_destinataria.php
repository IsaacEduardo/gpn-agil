<?php

use App\Models\Empresa;
use App\Models\Requisicao;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Copiar empresa_destinataria (string) para empresa_id quando houver correspondência pelo nome
        DB::transaction(function () {
            Requisicao::whereNotNull('empresa_destinataria')
                ->whereNull('empresa_id')
                ->orderBy('id')
                ->chunk(200, function ($lote) {
                    foreach ($lote as $req) {
                        $nome = trim($req->empresa_destinataria);
                        if ($nome === '') {
                            continue;
                        }
                        $empresa = Empresa::where('nome', $nome)->first();
                        if ($empresa) {
                            $req->empresa_id = $empresa->id;
                            $req->save();
                        }
                    }
                });
        });
    }

    public function down(): void
    {
        // Reverter preenchimento (opcional): limpar empresa_id onde veio de empresa_destinataria
        DB::table('requisicoes')
            ->whereNotNull('empresa_destinataria')
            ->update(['empresa_id' => null]);
    }
};
