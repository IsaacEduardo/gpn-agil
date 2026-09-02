<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('documento_vinculos', function (Blueprint $table) {
            $table->id();
            $table->string('origem_tipo', 20)->comment('EXTERNO ou INTERNO');
            $table->unsignedBigInteger('origem_id');
            $table->string('destino_tipo', 20)->comment('EXTERNO ou INTERNO');
            $table->unsignedBigInteger('destino_id');
            $table->string('tipo_relacao', 30)->default('COMPLEMENTAR')->comment('RESPOSTA, INSTRUCAO_TECNICA, COMPLEMENTAR, APENSO_ANEXO');
            $table->foreignId('vinculado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('justificativa')->nullable();
            $table->timestamps();

            $table->index(['origem_tipo', 'origem_id'], 'idx_doc_vinculos_origem');
            $table->index(['destino_tipo', 'destino_id'], 'idx_doc_vinculos_destino');
            $table->index(['origem_tipo', 'origem_id', 'destino_tipo', 'destino_id'], 'idx_doc_vinculos_pair');
        });

        // Migração de dados legados existentes sem perda de histórico
        $this->migrarDadosLegados();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documento_vinculos');
    }

    /**
     * Migra dados legados de documento_relacoes e documentos_internos.documento_entrada_id
     */
    protected function migrarDadosLegados(): void
    {
        $now = now();

        // 1. Migrar documento_relacoes (Entrada <-> Entrada)
        if (Schema::hasTable('documento_relacoes')) {
            $relacoes = DB::table('documento_relacoes')->get();
            foreach ($relacoes as $rel) {
                $tipoRelacao = match (strtolower((string) $rel->tipo)) {
                    'resposta' => 'RESPOSTA',
                    'anexo' => 'APENSO_ANEXO',
                    'instrucao', 'parecer' => 'INSTRUCAO_TECNICA',
                    default => 'COMPLEMENTAR',
                };

                // Verificar se já não foi migrado
                $existe = DB::table('documento_vinculos')
                    ->where('origem_tipo', 'EXTERNO')
                    ->where('origem_id', $rel->documento_id)
                    ->where('destino_tipo', 'EXTERNO')
                    ->where('destino_id', $rel->relacionado_id)
                    ->exists();

                if (! $existe) {
                    DB::table('documento_vinculos')->insert([
                        'origem_tipo' => 'EXTERNO',
                        'origem_id' => $rel->documento_id,
                        'destino_tipo' => 'EXTERNO',
                        'destino_id' => $rel->relacionado_id,
                        'tipo_relacao' => $tipoRelacao,
                        'vinculado_por_id' => null,
                        'justificativa' => 'Migrado do vínculo legado entre entradas',
                        'created_at' => $rel->created_at ?? $now,
                        'updated_at' => $rel->updated_at ?? $now,
                    ]);
                }
            }
        }

        // 2. Migrar documentos_internos com documento_entrada_id (Entrada -> Interno)
        if (Schema::hasTable('documentos_internos') && Schema::hasColumn('documentos_internos', 'documento_entrada_id')) {
            $internos = DB::table('documentos_internos')
                ->whereNotNull('documento_entrada_id')
                ->get();

            foreach ($internos as $interno) {
                $existe = DB::table('documento_vinculos')
                    ->where('origem_tipo', 'EXTERNO')
                    ->where('origem_id', $interno->documento_entrada_id)
                    ->where('destino_tipo', 'INTERNO')
                    ->where('destino_id', $interno->id)
                    ->exists();

                if (! $existe) {
                    DB::table('documento_vinculos')->insert([
                        'origem_tipo' => 'EXTERNO',
                        'origem_id' => $interno->documento_entrada_id,
                        'destino_tipo' => 'INTERNO',
                        'destino_id' => $interno->id,
                        'tipo_relacao' => 'RESPOSTA',
                        'vinculado_por_id' => $interno->criado_por ?? null,
                        'justificativa' => 'Resposta ou referência associada na criação do documento interno',
                        'created_at' => $interno->created_at ?? $now,
                        'updated_at' => $interno->updated_at ?? $now,
                    ]);
                }
            }
        }
    }
};
