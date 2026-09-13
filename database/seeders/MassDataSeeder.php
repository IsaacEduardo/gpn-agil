<?php

namespace Database\Seeders;

use App\Models\Departamento;
use App\Models\DocumentoEspecie;
use App\Models\Gabinete;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeder de Carga Massiva para Testes de Estresse no GPN-AGIL.
 * Insere em lote 5.500+ registos entre Documentos, Lotes e Requisições.
 */
class MassDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Iniciando povoamento de massa para testes de estresse...');

        $user = User::first() ?? User::factory()->create([
            'name' => 'Utilizador Teste Carga',
            'email' => 'carga@gpn.gov.ao',
        ]);

        $gabinete = Gabinete::first() ?? Gabinete::create([
            'nome' => 'Gabinete Provincial do Namibe',
            'sigla' => 'GPN',
        ]);

        $departamento = Departamento::first() ?? Departamento::create([
            'gabinete_id' => $gabinete->id,
            'nome' => 'Departamento de Infraestruturas',
            'sigla' => 'DINF',
        ]);

        $especie = DocumentoEspecie::first() ?? DocumentoEspecie::create([
            'nome' => 'Ofício',
            'sigla' => 'OF',
        ]);

        $now = now()->toDateTimeString();

        // 1. Povoamento em lote de Documentos de Entrada (2.000 registos)
        $this->command->info('Criando 2.000 Documentos de Entrada...');
        $docsEntrada = [];
        $maxSeq = (int) DB::table('documentos_entradas')->where('ano_referencia', (int) date('Y'))->max('numero_sequencial');

        for ($i = 1; $i <= 2000; $i++) {
            $seq = $maxSeq + $i;
            $docsEntrada[] = [
                'numero_sequencial' => $seq,
                'ano_referencia' => (int) date('Y'),
                'data_entrada' => date('Y-m-d'),
                'classificacao_especie' => 'OFICIO',
                'classificacao_ref_numero' => "REF-EXT-{$seq}",
                'data_documento' => date('Y-m-d'),
                'procedencia' => "Entidade Externa {$seq}",
                'assunto' => "Documento de Carga N.º {$seq} - Projeto Infraestrutura",
                'departamento_id' => $departamento->id,
                'user_id' => $user->id,
                // 'registado' (grafia PT) não existe em DocumentoStatus: os
                // documentos de carga ficavam fora de todos os separadores da
                // listagem e invisíveis ao SLA, o que tornava o teste de volume
                // inútil para este módulo.
                'status' => \App\Enums\DocumentoStatus::REGISTRADO->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($docsEntrada, 500) as $chunk) {
            DB::table('documentos_entradas')->insert($chunk);
        }

        // 2. Povoamento em lote de Documentos Internos (1.500 registos)
        $this->command->info('Criando 1.500 Documentos Internos...');
        $docsInternos = [];
        for ($i = 1; $i <= 1500; $i++) {
            $docsInternos[] = [
                'numero_referencia' => "INT-CARGA-{$i}-" . uniqid() . "/2026",
                'titulo' => "Nota Interna N.º {$i} - Parecer Técnico",
                'conteudo_final' => "Conteúdo do parecer técnico de teste de estresse N.º {$i}",
                'documento_especie_id' => $especie->id,
                'departamento_id' => $departamento->id,
                'criado_por' => $user->id,
                'status' => 'aprovado',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($docsInternos, 500) as $chunk) {
            DB::table('documento_internos')->insert($chunk);
        }

        // 3. Povoamento em lote de Lotes Territoriais (1.000 registos)
        $this->command->info('Criando 1.000 Lotes Territoriais...');
        $lotes = [];
        for ($i = 1; $i <= 1000; $i++) {
            $lotes[] = [
                'uuid' => (string) Str::uuid(),
                'codigo_lote' => 'LOTE-NAM-2026-' . str_pad((string) rand(10000, 99999), 5, '0', STR_PAD_LEFT) . "-{$i}",
                'municipio' => 'Moçâmedes',
                'bairro_distrito' => 'Bairro Juventude',
                'area_m2' => rand(250, 5000),
                'status' => 'DISPONIVEL',
                'created_by_user_id' => $user->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($lotes, 500) as $chunk) {
            DB::table('lotes')->insert($chunk);
        }

        // 4. Povoamento em lote de Requisições Logísticas (1.000 registos)
        $this->command->info('Criando 1.000 Requisições Logísticas...');
        $requisicoes = [];
        for ($i = 1; $i <= 1000; $i++) {
            $requisicoes[] = [
                'codigo_sequencial' => "REQ-2026-" . str_pad((string) rand(10000, 99999), 5, '0', STR_PAD_LEFT) . "-{$i}",
                'tipo' => 'produto',
                'empresa_destinataria' => "Empresa Fornecedora N.º {$i}",
                'data_requisicao' => date('Y-m-d'),
                'usuario_id' => $user->id,
                'gabinete_id' => $gabinete->id,
                'status' => 'aprovado',
                'observacoes' => "Requisição de teste de estresse N.º {$i}",
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($requisicoes, 500) as $chunk) {
            DB::table('requisicoes')->insert($chunk);
        }

        $this->command->info('Povoamento de massa concluído com sucesso: 5.500 registos inseridos!');
    }
}
