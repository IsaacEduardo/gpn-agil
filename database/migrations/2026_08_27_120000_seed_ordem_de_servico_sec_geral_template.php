<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Ensure Ordem de Serviço species exists
        $especie = DB::table('documento_especies')->where('nome', 'Ordem de Serviço')->first();
        if (! $especie) {
            $maxOrdem = (int) DB::table('documento_especies')->max('ordem');
            $especieId = DB::table('documento_especies')->insertGetId([
                'nome' => 'Ordem de Serviço',
                'ativo' => true,
                'ordem' => $maxOrdem + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $especieId = $especie->id;
        }

        // 2. Resolve admin user ID
        $user = DB::table('users')->first();
        $userId = $user ? $user->id : 1;

        // 3. Define HTML template and dynamic fields schema
        $camposDinamicos = json_encode([
            'data_inicio_ausencia' => 'date',
            'substituto_user_id' => 'select_chefes_departamento',
            'verbo_operativo' => 'select:INDICO:,DETERMINO:,DESPACHO:',
            'cargo_signatario' => 'text',
        ]);

        $conteudo = '<div style="font-family: \'Times New Roman\', Times, serif; font-size: 12pt; line-height: 1.5; color: #111111;">
    
    <!-- Título da Ordem de Serviço da Secretaria Geral -->
    <div style="text-align: center; margin: 30px 0 35px 0; font-weight: bold; letter-spacing: 0.5px;">
        ORDEM DE SERVIÇO Nº <span style="border-bottom: 1.5px solid #111111; padding: 0 8px; display: inline-block; min-width: 35px; text-align: center;">{{ numero_ordem }}</span> /SEC.GER.GOV.PROV.HLA/{{ ano_corrente }}
    </div>

    <!-- Dispositivo / Texto -->
    <div style="text-align: justify; line-height: 1.6;">
        <div style="margin-bottom: 22px;">
            Ausentando-me para cumprimento de missão de Serviço Oficial, a partir do dia {{ data_inicio_ausencia }} e havendo necessidade de se assegurar o normal funcionamento da Secretaria Geral do Governo, enquanto durar a minha ausência;
        </div>

        <div style="text-align: center; font-weight: bold; letter-spacing: 1px; margin: 25px 0;">
            {{ verbo_operativo }}
        </div>

        <div style="margin-bottom: 25px;">
            O Senhor <strong>{{ substituto_nome }}</strong> - Chefe de Departamento de {{ substituto_departamento }} da Secretaria Geral do Governo Provincial, a responder pelos assuntos correntes da referida Secretaria.
        </div>

        <div style="text-align: center; margin: 25px 0 30px 0;">
            Comunicações devidas.
        </div>
    </div>

    <!-- Datação Oficial -->
    <div style="text-align: left; font-weight: bold; font-size: 11pt; margin-bottom: 45px; text-transform: uppercase;">
        SECRETARIA GERAL DO GOVERNO PROVINCIAL DA HUÍLA, no Lubango, aos {{ localidade_data_extenso }}.
    </div>

    <!-- Bloco de Assinatura pelo Chefe do Gabinete (Secretário Geral) -->
    <div style="text-align: center; margin: 10px auto 35px auto; width: 320px;">
        <div style="font-weight: bold; font-size: 11.5pt; margin-bottom: 40px;">{{ cargo_signatario }}</div>
        <div style="font-weight: bold; font-size: 11.5pt;">{{ RESPONSAVEL_NOME }}</div>
    </div>

</div>';

        $modeloData = [
            'nome' => 'Ordem de Serviço (Exclusivo Secretaria Geral)',
            'codigo' => 'ORDEM_DE_SERVICO_SEC_GERAL',
            'documento_especie_id' => $especieId,
            'conteudo' => $conteudo,
            'campos_dinamicos' => $camposDinamicos,
            'ativo' => true,
            'user_id' => $userId,
            'gabinete_id' => null,
            'updated_at' => now(),
        ];

        $existing = DB::table('modelo_documentos')
            ->where('codigo', 'ORDEM_DE_SERVICO_SEC_GERAL')
            ->orWhere('nome', 'Ordem de Serviço (Exclusivo Secretaria Geral)')
            ->first();

        if ($existing) {
            DB::table('modelo_documentos')->where('id', $existing->id)->update($modeloData);
        } else {
            $modeloData['created_at'] = now();
            DB::table('modelo_documentos')->insert($modeloData);
        }

        Cache::forget('documento_especies_names');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('modelo_documentos')->where('codigo', 'ORDEM_DE_SERVICO_SEC_GERAL')->delete();
        Cache::forget('documento_especies_names');
    }
};
