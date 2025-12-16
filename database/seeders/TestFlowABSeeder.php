<?php

namespace Database\Seeders;

use App\Models\Departamento;
use App\Models\DocumentoEncaminhamento;
use App\Models\DocumentoEntrada;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestFlowABSeeder extends Seeder
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
            ->first();

        if (! $doc) {
            $lastSeq = (int) (DocumentoEntrada::where('ano_referencia', $ano)->max('numero_sequencial') ?? 0);
            $doc = DocumentoEntrada::create([
                'numero_sequencial' => $lastSeq + 1,
                'ano_referencia' => $ano,
                'data_entrada' => now(),
                'classificacao_especie' => 'Ofício',
                'classificacao_ref_numero' => null,
                'data_documento' => null,
                'procedencia' => 'Departamento A',
                'assunto' => $assunto,
                'observacoes' => 'Documento de teste para fluxo A→B',
                'saida_gabinete_data' => null,
                'encaminhamento_orgao' => null,
                'encaminhamento_oficio_numero' => null,
                'encaminhamento_data' => null,
                'departamento_id' => $depA->id,
                'user_id' => $user->id,
                'status' => 'registrado',
                'arquivo_caminho' => null,
            ]);
        }

        // Encaminhamento pendente A -> B (se não existir)
        $hasPending = DocumentoEncaminhamento::where('documento_entrada_id', $doc->id)
            ->whereNull('recebido_em')
            ->exists();

        if (! $hasPending) {
            DocumentoEncaminhamento::create([
                'documento_entrada_id' => $doc->id,
                'origem_departamento_id' => $depA->id,
                'destino_departamento_id' => $depB->id,
                'usuario_id' => $user->id,
                'encaminhado_em' => now(),
                'recebido_em' => null,
                'status' => 'encaminhado',
                'observacao' => 'Encaminhamento automático A→B',
            ]);

            $doc->status = 'encaminhado';
            $doc->encaminhamento_data = now();
            $doc->save();
        }
    }
}
