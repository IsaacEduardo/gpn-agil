<?php

namespace App\Http\Controllers;

use App\Models\AnaliseTecnica;
use App\Models\SolicitacaoAtribuicao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AnaliseTecnicaController extends Controller
{
    public function create(SolicitacaoAtribuicao $solicitacao)
    {
        $this->authorize('update', $solicitacao);

        return view('analises_tecnicas.create', compact('solicitacao'));
    }

    public function store(Request $request, SolicitacaoAtribuicao $solicitacao)
    {
        $this->authorize('update', $solicitacao);

        $validated = $request->validate([
            'data_vistoria' => 'required|date',
            'parecer_tecnico' => 'required|string',
            'viabilidade' => 'required|in:FAVORAVEL,FAVORAVEL_COM_RESTRICOES,DESFAVORAVEL',
            'coordenadas_vistoria' => 'nullable|string|max:255',
        ]);

        $validated['solicitacao_atribuicao_id'] = $solicitacao->id;
        $validated['tecnico_user_id'] = Auth::id();

        AnaliseTecnica::create($validated);

        // Se a solicitação estava em triagem ou submetida, muda automaticamente para EM_VISTORIA ou EM_ANALISE_JURIDICA
        if (in_array($solicitacao->status, ['SUBMETIDO', 'EM_TRIAGEM', 'EM_VISTORIA'])) {
            $solicitacao->update(['status' => 'EM_ANALISE_JURIDICA']);
        }

        return redirect()->route('solicitacoes.show', $solicitacao)
            ->with('success', 'Laudo de Análise Técnica registado com sucesso.');
    }
}
