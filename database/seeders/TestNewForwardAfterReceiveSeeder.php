<?php

namespace Database\Seeders;

use App\Models\Departamento;
use App\Models\DocumentoEncaminhamento;
use App\Models\DocumentoEntrada;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestNewForwardAfterReceiveSeeder extends Seeder
{
    public function run(): void
    {
        $depA = Departamento::where('sigla', 'A')->orWhere('nome', 'Departamento A')->firstOrFail();
        $depB = Departamento::where('sigla', 'B')->orWhere('nome', 'Departamento B')->firstOrFail();
        $user = User::where('email', 'admin@gpnagil.com')->first() ?? User::firstOrFail();

        $assunto = 'Fluxo A→B (auto)';
        $ano = (int) date('Y');
        $doc = DocumentoEntrada::where('assunto', $assunto)
            ->where('ano_referencia', $ano)
            ->firstOrFail();

        // Garantir que não há pendente
        $hasPending = DocumentoEncaminhamento::where('documento_entrada_id', $doc->id)
            ->whereNull('recebido_em')
            ->exists();
        if ($hasPending) {
            return; // ainda pendente; não cria novo
        }

        // Após recebido em B, criar novo encaminhamento B -> A
        DocumentoEncaminhamento::create([
            'documento_entrada_id' => $doc->id,
            'origem_departamento_id' => $depB->id,
            'destino_departamento_id' => $depA->id,
            'usuario_id' => $user->id,
            'encaminhado_em' => now(),
            'recebido_em' => null,
            'status' => 'encaminhado',
            'observacao' => 'Encaminhamento automático B→A após recebimento',
        ]);

        $doc->status = 'encaminhado';
        $doc->encaminhamento_data = now();
        $doc->save();
    }
}
