<?php

namespace App\Http\Controllers;

use App\Models\DocumentoProtocolo;

/**
 * Consulta pública do protocolo de entrada.
 *
 * O QR do recibo apontava para uma rota dentro do grupo 'auth': quem entregava
 * o documento ao balcão levava um comprovativo que não conseguia abrir.
 *
 * Esta página mostra deliberadamente MENOS do que a interna. O P2 fechou a fuga
 * que expunha assunto, procedência, departamento e autor a utilizadores sem
 * relação com o documento; abrir isso ao público seria reabri-la em maior
 * escala. Aqui só há: código, data de entrada e um estado em linguagem comum.
 */
class ProtocoloPublicoController extends Controller
{
    /**
     * Tradução do estado interno para linguagem de quem entregou o documento.
     * Estados internos (nomes de setores, vistos, despachos) não saem daqui.
     */
    private const ESTADOS = [
        'pendente_tratamento' => ['rotulo' => 'Recebido, aguarda tratamento', 'cor' => 'warning'],
        'registrado' => ['rotulo' => 'Recebido, aguarda tratamento', 'cor' => 'warning'],
        'tratado' => ['rotulo' => 'Em tratamento', 'cor' => 'info'],
        'encaminhado' => ['rotulo' => 'Em tratamento', 'cor' => 'info'],
        'recebido' => ['rotulo' => 'Em tratamento', 'cor' => 'info'],
        'encaminhado_externo' => ['rotulo' => 'Encaminhado para outra entidade', 'cor' => 'info'],
        'arquivado' => ['rotulo' => 'Concluído e arquivado', 'cor' => 'success'],
        'finalizado' => ['rotulo' => 'Concluído', 'cor' => 'success'],
    ];

    public function __invoke(string $codigo)
    {
        $protocolo = DocumentoProtocolo::with('documentoEntrada')
            ->where('codigo', $codigo)
            ->firstOrFail();

        $documento = $protocolo->documentoEntrada;
        abort_if(! $documento, 404);

        $estado = self::ESTADOS[$documento->status] ?? ['rotulo' => 'Em tratamento', 'cor' => 'secondary'];

        if ($documento->arquivado) {
            $estado = self::ESTADOS['arquivado'];
        }

        return view('protocolo.publico', [
            'codigo' => $protocolo->codigo,
            'dataEntrada' => $documento->data_entrada,
            'estadoRotulo' => $estado['rotulo'],
            'estadoCor' => $estado['cor'],
            'atualizadoEm' => $documento->updated_at,
        ]);
    }
}
