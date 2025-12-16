<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FeedbackController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Verificar se o usuário tem permissão para visualizar todos os feedbacks
        if (! Auth::user()->hasPermission('visualizar_feedbacks')) {
            return redirect()->route('dashboard')
                ->with('error', 'Você não tem permissão para acessar esta página.');
        }

        $feedbacks = Feedback::with('user')->latest()->paginate(10);

        return view('feedbacks.index', compact('feedbacks'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('feedbacks.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'tipo_modulo' => 'required|string|max:50',
            'avaliacao' => 'required|integer|min:1|max:5',
            'comentario' => 'nullable|string|max:500',
            'sugestao_melhoria' => 'nullable|string|max:500',
        ]);

        Feedback::create([
            'user_id' => Auth::id(),
            'tipo_modulo' => $request->tipo_modulo,
            'avaliacao' => $request->avaliacao,
            'comentario' => $request->comentario,
            'sugestao_melhoria' => $request->sugestao_melhoria,
        ]);

        return redirect()->route('dashboard')
            ->with('success', 'Obrigado pelo seu feedback! Sua opinião é muito importante para melhorarmos o sistema.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Feedback $feedback)
    {
        // Verificar se o usuário tem permissão para visualizar feedbacks
        if (! Auth::user()->hasPermission('visualizar_feedbacks') && Auth::id() !== $feedback->user_id) {
            return redirect()->route('dashboard')
                ->with('error', 'Você não tem permissão para acessar esta página.');
        }

        return view('feedbacks.show', compact('feedback'));
    }

    /**
     * Exibir relatório de feedbacks por módulo.
     */
    public function relatorio()
    {
        // Verificar se o usuário tem permissão para visualizar relatórios
        if (! Auth::user()->hasPermission('visualizar_relatorios')) {
            return redirect()->route('dashboard')
                ->with('error', 'Você não tem permissão para acessar esta página.');
        }

        // Agrupar feedbacks por módulo e calcular média de avaliação
        $estatisticas = Feedback::selectRaw('tipo_modulo, AVG(avaliacao) as media, COUNT(*) as total')
            ->groupBy('tipo_modulo')
            ->get();

        // Obter os comentários mais recentes
        $comentarios_recentes = Feedback::whereNotNull('comentario')
            ->with('user')
            ->latest()
            ->limit(10)
            ->get();

        // Obter as sugestões mais recentes
        $sugestoes_recentes = Feedback::whereNotNull('sugestao_melhoria')
            ->with('user')
            ->latest()
            ->limit(10)
            ->get();

        return view('feedbacks.relatorio', compact('estatisticas', 'comentarios_recentes', 'sugestoes_recentes'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Feedback $feedback)
    {
        // Verificar se o usuário tem permissão para excluir feedbacks
        if (! Auth::user()->hasPermission('excluir_feedbacks')) {
            return redirect()->route('feedbacks.index')
                ->with('error', 'Você não tem permissão para excluir feedbacks.');
        }

        $feedback->delete();

        return redirect()->route('feedbacks.index')
            ->with('success', 'Feedback excluído com sucesso.');
    }
}
