<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repara os documentos deixados a meio pelo despacho em duas fases.
 *
 * O painel rápido da gaveta criava os encaminhamentos mas deixava o documento
 * em TRATADO, pelo que documentos já entregues continuavam a figurar como
 * "prontos a encaminhar" e o botão de encaminhar permanecia ativo, chegando a
 * duplicar a entrega. Gravava ainda a instrução do despacho numa chave
 * inexistente ('despacho_instrucao'), que o mass assignment descartava — o
 * destinatário recebia o documento sem a instrução.
 *
 * Esta migração alinha os dados com a regra nova: despachar é entregar.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Documentos em TRATADO que já tinham sido encaminhados: o estado
        //    verdadeiro é ENCAMINHADO, com a data do primeiro encaminhamento.
        $presos = DB::table('documentos_entradas as d')
            ->whereNull('d.deleted_at')
            ->where('d.arquivado', false)
            ->where('d.status', 'tratado')
            ->whereExists(function ($q) {
                $q->selectRaw(1)
                    ->from('documento_encaminhamentos as e')
                    ->whereColumn('e.documento_entrada_id', 'd.id');
            })
            ->pluck('d.id');

        foreach ($presos as $documentoId) {
            $valores = ['status' => 'encaminhado'];

            $atual = DB::table('documentos_entradas')->where('id', $documentoId)->value('encaminhamento_data');
            if (empty($atual)) {
                $primeiro = DB::table('documento_encaminhamentos')
                    ->where('documento_entrada_id', $documentoId)
                    ->whereNotNull('encaminhado_em')
                    ->min('encaminhado_em');

                if ($primeiro) {
                    $valores['encaminhamento_data'] = $primeiro;
                }
            }

            DB::table('documentos_entradas')->where('id', $documentoId)->update($valores);
        }

        // 2. Instrução do despacho perdida: repõe-se a partir do documento,
        //    para o departamento de destino passar a vê-la.
        $semObservacao = DB::table('documento_encaminhamentos as e')
            ->join('documentos_entradas as d', 'd.id', '=', 'e.documento_entrada_id')
            ->whereNull('e.observacao')
            ->whereNotNull('d.texto_despacho')
            ->where('d.texto_despacho', '!=', '')
            ->select('e.id', 'd.texto_despacho')
            ->get();

        foreach ($semObservacao as $linha) {
            DB::table('documento_encaminhamentos')
                ->where('id', $linha->id)
                ->update(['observacao' => $linha->texto_despacho]);
        }
    }

    public function down(): void
    {
        // Sem reversão: não há forma de distinguir os documentos corrigidos aqui
        // dos que chegaram a ENCAMINHADO pelo fluxo normal, e repor o estado
        // TRATADO voltaria a escondê-los do separador correto. Reverter
        // destruiria informação em vez de a restaurar.
    }
};
