<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $conteudo = <<<'HTML'
<div style="font-family: 'Times New Roman', Times, serif; color: #000;">
    <div style="text-align: center;">
        <p style="margin: 0;"><strong>REPÚBLICA DE ANGOLA</strong></p>
        <p style="margin: 0;"><strong>GOVERNO PROVINCIAL DO NAMIBE</strong></p>
        <p style="margin: 0;"><strong>{{DEPARTAMENTO_NOME}}</strong></p>
    </div>

    <br><br>

    <div style="text-align: center;">
        <p style="margin: 0;">AO</p>
        <p style="margin: 0;">EXMO. SR.</p>
        <p style="margin: 5px 0;"><strong>{{DESTINATARIO_NOME}}</strong></p>
        <p style="margin: 0;">{{DESTINATARIO_CARGO}}</p>
        <p style="margin: 0;">{{DESTINATARIO_ORGAO}}</p>
        <p style="margin: 10px 0;"><strong><u>{{DESTINATARIO_LOCAL}}</u></strong></p>
    </div>

    <br><br>

    <p><strong>NOTA N.º _____/{{DEPARTAMENTO_SIGLA}}/{{ANO}}.</strong></p>
    <p><strong>ASSUNTO: <u>{{ASSUNTO}}</u></strong></p>

    <br>

    <p>Exmo. Sr,</p>
    
    <p style="text-align: justify; line-height: 1.5;">
        Escreva aqui o conteúdo do documento...
    </p>

    <br><br>

    <p>À Superior Consideração do Exmo. Senhor.</p>

    <br>

    <p><strong>{{DEPARTAMENTO_NOME}} DO GOVERNO PROVINCIAL DO NAMIBE</strong>, em Moçâmedes, {{DATA_EXTENSO}}.</p>

    <br><br><br>

    <div style="text-align: center;">
        <p style="margin: 0;">O Responsável</p>
        <br><br>
        <p style="margin: 0;"><strong>{{USUARIO_NOME}}</strong></p>
    </div>
</div>
HTML;

        // ID 8 = Nota (found via tinker)
        // If ID 8 doesn't exist, it might fail or we should find it dynamically, but for now hardcoding based on check is fine.
        // Or better, use a subquery or look it up if possible, but DB::table inside migration is fine.

        $especie = DB::table('documento_especies')->where('nome', 'Nota')->first();

        if (! $especie) {
            $especieId = DB::table('documento_especies')->insertGetId([
                'nome' => 'Nota',
                'ativo' => true,
                'ordem' => 8,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $especieId = $especie->id;
        }

        // Ensure User 1 exists (Admin from previous migration)
        $user = DB::table('users')->find(1);
        if (! $user) {
            // Fallback if default user migration didn't run or ID is different
            $user = DB::table('users')->first();
            $userId = $user ? $user->id : DB::table('users')->insertGetId([
                'name' => 'System Admin',
                'email' => 'system@admin.com',
                'password' => '$2y$12$K.z.7r.9/8.5.3.1.5.9.7.5.3.1.5.9.7.5.3.1.5.9.7.5.3', // dummy
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $userId = 1;
        }

        DB::table('modelo_documentos')->insert([
            'nome' => 'Nota Oficial GPN',
            'documento_especie_id' => $especieId,
            'conteudo' => $conteudo,
            'ativo' => true,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('modelo_documentos')->where('nome', 'Nota Oficial GPN')->delete();
    }
};
