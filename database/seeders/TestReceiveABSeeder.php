<?php

namespace Database\Seeders;

use App\Models\Departamento;
use App\Models\DocumentoEncaminhamento;
use App\Models\DocumentoEntrada;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestReceiveABSeeder extends Seeder
{
    public function run(): void
    {
        $depB = Departamento::where('sigla', 'B')->orWhere('nome', 'Departamento B')->firstOrFail();
        $user = User::where('email', 'admin@gpnagil.com')->first() ?? User::firstOrFail();

        $assunto = 'Fluxo A→B (auto)';
        $ano = (int) date('Y');
        $doc = DocumentoEntrada::where('assunto', $assunto)
            ->where('ano_referencia', $ano)
            ->firstOrFail();

        $enc = DocumentoEncaminhamento::where('documento_entrada_id', $doc->id)
            ->whereNull('recebido_em')
            ->orderByDesc('encaminhado_em')
            ->first();

        if ($enc) {
            $enc->recebido_em = now();
            $enc->recebido_por_id = $user->id;
            $enc->status = 'recebido';
            $enc->save();

            $doc->departamento_id = $depB->id;
            $doc->status = 'recebido';
            $doc->save();
        }
    }
}
