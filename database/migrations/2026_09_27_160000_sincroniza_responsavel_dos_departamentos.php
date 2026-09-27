<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * departamentos.responsavel_id passa a reflectir o Chefe de Departamento designado na
 * Gestão de Utilizadores (papel "chefe-departamento" + departamento). Preenche onde há
 * exactamente um utilizador designado; com dois ou mais não decide — regista para revisão.
 */
return new class extends Migration
{
    public function up(): void
    {
        $roleChefe = DB::table('roles')->where('name', 'chefe-departamento')->value('id');
        if (! $roleChefe) {
            return;
        }

        $porDepartamento = DB::table('users')
            ->where('role_id', $roleChefe)
            ->whereNotNull('departamento_id')
            ->get(['id', 'name', 'departamento_id'])
            ->groupBy('departamento_id');

        foreach ($porDepartamento as $departamentoId => $chefes) {
            if ($chefes->count() === 1) {
                DB::table('departamentos')
                    ->where('id', $departamentoId)
                    ->whereNull('responsavel_id')
                    ->update(['responsavel_id' => $chefes->first()->id]);

                continue;
            }

            Log::warning('Departamento com mais de um Chefe de Departamento designado: responsavel_id não preenchido.', [
                'departamento_id' => $departamentoId,
                'utilizadores' => $chefes->map(fn ($u) => "{$u->id} {$u->name}")->all(),
            ]);
        }
    }

    public function down(): void
    {
        // Sem reversão: responsavel_id não era usado antes e passa a ser mantido pela aplicação.
    }
};
