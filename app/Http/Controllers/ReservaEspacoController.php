<?php

namespace App\Http\Controllers;

use App\Models\ReservaEspaco;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ReservaEspacoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = ReservaEspaco::with(['usuario', 'aprovador']);

        // Filtros
        if ($request->filled('tipo_espaco')) {
            $query->where('tipo_espaco', $request->tipo_espaco);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('data_inicio') && $request->filled('data_fim')) {
            $query->whereBetween('data_evento', [$request->data_inicio, $request->data_fim]);
        }

        // Escopo por papel/permissão: não-admin sem 'reservas.view_any' vê apenas próprias reservas
        $actor = Auth::user();
        if (! (($actor->role && $actor->role->name === 'admin') || $actor->hasPermission('reservas.view_any'))) {
            $query->where('usuario_id', $actor->id);
        }

        // Ordenação
        $query->orderBy('data_evento', 'desc')->orderBy('hora_inicio', 'desc');

        $reservas = $query->paginate(15);

        return view('reservas.index', compact('reservas'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('reservas.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tipo_espaco' => 'required|in:salao_nobre,anfiteatro',
            'solicitante_nome' => 'required|string|max:255',
            'solicitante_email' => 'required|email|max:255',
            'solicitante_telefone' => 'nullable|string|max:20',
            'evento_titulo' => 'required|string|max:255',
            'evento_descricao' => 'nullable|string',
            'data_evento' => 'required|date|after_or_equal:today',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fim' => 'required|date_format:H:i|after:hora_inicio',
            'numero_participantes' => 'nullable|integer|min:1',
            'observacoes' => 'nullable|string',
        ], [
            'tipo_espaco.required' => 'O tipo de espaço é obrigatório.',
            'tipo_espaco.in' => 'Tipo de espaço inválido.',
            'data_evento.after_or_equal' => 'A data do evento deve ser hoje ou uma data futura.',
            'hora_fim.after' => 'A hora de fim deve ser posterior à hora de início.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Verificar disponibilidade do espaço
        if (! ReservaEspaco::espacoDisponivel(
            $request->tipo_espaco,
            $request->data_evento,
            $request->hora_inicio,
            $request->hora_fim,
            $request->input('id')
        )) {
            return redirect()->back()
                ->with('error', 'O espaço não está disponível para a data e horário selecionados.')
                ->withInput();
        }

        // Criar reserva
        $reserva = new ReservaEspaco($request->only([
            'tipo_espaco',
            'solicitante_nome',
            'solicitante_email',
            'solicitante_telefone',
            'evento_titulo',
            'evento_descricao',
            'data_evento',
            'hora_inicio',
            'hora_fim',
            'numero_participantes',
            'observacoes',
        ]));
        $reserva->usuario_id = Auth::id();
        $reserva->status = ReservaEspaco::STATUS_PENDENTE;
        $reserva->codigo_reserva = 'RSV-'.time();
        $reserva->save();

        return redirect()->route('reservas.show', $reserva->id)
            ->with('success', 'Reserva criada com sucesso e enviada para aprovação.');
    }

    /**
     * Display the specified resource.
     */
    public function show(ReservaEspaco $reserva)
    {
        $reserva->load(['usuario', 'aprovador']);
        // Garantir que departamentos do solicitante estejam disponíveis para escopo na view
        $reserva->loadMissing('usuario.departamentos');

        return view('reservas.show', compact('reserva'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ReservaEspaco $reserva)
    {
        // Verificar se pode ser editada
        if (! $reserva->podeSerEditada()) {
            return redirect()->route('reservas.show', $reserva->id)
                ->with('error', 'Esta reserva não pode ser editada.');
        }

        // Verificar se o usuário pode editar (apenas o criador ou admin)
        if ($reserva->usuario_id !== Auth::id() && ! Auth::user()->hasPermission('gerenciar_reservas')) {
            return redirect()->route('reservas.show', $reserva->id)
                ->with('error', 'Você não tem permissão para editar esta reserva.');
        }

        return view('reservas.edit', compact('reserva'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ReservaEspaco $reserva)
    {
        // Verificar se pode ser editada
        if (! $reserva->podeSerEditada()) {
            return redirect()->route('reservas.show', $reserva->id)
                ->with('error', 'Esta reserva não pode ser editada.');
        }

        $validator = Validator::make($request->all(), [
            'tipo_espaco' => 'required|in:salao_nobre,anfiteatro',
            'solicitante_nome' => 'required|string|max:255',
            'solicitante_email' => 'required|email|max:255',
            'solicitante_telefone' => 'nullable|string|max:20',
            'evento_titulo' => 'required|string|max:255',
            'evento_descricao' => 'nullable|string',
            'data_evento' => 'required|date|after_or_equal:today',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fim' => 'required|date_format:H:i|after:hora_inicio',
            'numero_participantes' => 'nullable|integer|min:1',
            'observacoes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Verificar disponibilidade (excluindo a reserva atual)
        if (! ReservaEspaco::espacoDisponivel(
            $request->tipo_espaco,
            $request->data_evento,
            $request->hora_inicio,
            $request->hora_fim,
            $reserva->id
        )) {
            return redirect()->back()
                ->with('error', 'O espaço não está disponível para a data e horário selecionados.')
                ->withInput();
        }

        $reserva->update($request->only([
            'tipo_espaco',
            'solicitante_nome',
            'solicitante_email',
            'solicitante_telefone',
            'evento_titulo',
            'evento_descricao',
            'data_evento',
            'hora_inicio',
            'hora_fim',
            'numero_participantes',
            'observacoes',
        ]));

        return redirect()->route('reservas.show', $reserva->id)
            ->with('success', 'Reserva atualizada com sucesso!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ReservaEspaco $reserva)
    {
        // Verificar permissões
        if ($reserva->usuario_id !== Auth::id() && ! Auth::user()->hasPermission('gerenciar_reservas')) {
            return redirect()->route('reservas.index')
                ->with('error', 'Você não tem permissão para excluir esta reserva.');
        }

        $reserva->delete();

        return redirect()->route('reservas.index')
            ->with('success', 'Reserva excluída com sucesso!');
    }

    /**
     * Aprovar uma reserva
     */
    public function aprovar(ReservaEspaco $reserva)
    {
        $this->authorize('approve', $reserva);

        // Removida exigência de visto do departamento para aprovação final
        // Usuários com permissão podem aprovar diretamente

        if (! $reserva->isPendente()) {
            return redirect()->route('reservas.show', $reserva->id)
                ->with('error', 'Esta reserva não pode ser aprovada.');
        }

        $reserva->update([
            'status' => ReservaEspaco::STATUS_APROVADA,
            'aprovado_por' => Auth::id(),
            'data_aprovacao' => now(),
        ]);

        return redirect()->route('reservas.show', $reserva->id)
            ->with('success', 'Reserva aprovada com sucesso!');
    }

    /**
     * Rejeitar uma reserva
     */
    public function rejeitar(Request $request, ReservaEspaco $reserva)
    {
        $this->authorize('reject', $reserva);

        if (! $reserva->isPendente()) {
            return redirect()->route('reservas.show', $reserva->id)
                ->with('error', 'Esta reserva não pode ser rejeitada.');
        }

        $request->validate([
            'motivo_rejeicao' => 'required|string|max:500',
        ]);

        $reserva->update([
            'status' => ReservaEspaco::STATUS_REJEITADA,
            'motivo_rejeicao' => $request->motivo_rejeicao,
            'aprovado_por' => Auth::id(),
            'data_aprovacao' => now(),
        ]);

        return redirect()->route('reservas.show', $reserva->id)
            ->with('success', 'Reserva rejeitada.');
    }

    /**
     * Cancelar uma reserva
     */
    public function cancelar(ReservaEspaco $reserva)
    {
        // Verificar se pode ser cancelada
        if (! $reserva->podeSerCancelada()) {
            return redirect()->route('reservas.show', $reserva->id)
                ->with('error', 'Esta reserva não pode ser cancelada.');
        }

        // Verificar permissões
        if ($reserva->usuario_id !== Auth::id() && ! Auth::user()->hasPermission('gerenciar_reservas')) {
            return redirect()->route('reservas.show', $reserva->id)
                ->with('error', 'Você não tem permissão para cancelar esta reserva.');
        }

        $reserva->update([
            'status' => ReservaEspaco::STATUS_CANCELADA,
        ]);

        return redirect()->route('reservas.show', $reserva->id)
            ->with('success', 'Reserva cancelada com sucesso!');
    }

    /**
     * Verificar disponibilidade de espaço
     */
    public function verificarDisponibilidade(Request $request)
    {
        $data = $request->validate([
            'tipo_espaco' => 'required|in:salao_nobre,anfiteatro',
            'data_evento' => 'required|date|after_or_equal:today',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fim' => 'required|date_format:H:i|after:hora_inicio',
        ]);

        $disponivel = ReservaEspaco::espacoDisponivel(
            $data['tipo_espaco'],
            $data['data_evento'],
            $data['hora_inicio'],
            $data['hora_fim']
        );

        return response()->json(['disponivel' => $disponivel]);
    }

    /**
     * Mostrar calendário de reservas
     */
    public function calendar(Request $request)
    {
        // Se a requisição for JSON/AJAX, retornar dados do calendário
        $acceptsJson = $request->expectsJson() || $request->wantsJson() || $request->ajax() || $request->query('json');
        if ($acceptsJson) {
            $year = (int) $request->query('year');
            $month = (int) $request->query('month');
            $start = $request->query('start');
            $end = $request->query('end');

            $query = ReservaEspaco::query();

            // Escopo por papel/permissão: não-admin sem 'reservas.view_any' vê apenas próprias reservas
            $actor = Auth::user();
            if (! (($actor->role && $actor->role->name === 'admin') || $actor->hasPermission('reservas.view_any'))) {
                $query->where('usuario_id', $actor->id);
            }

            // Filtros opcionais de tipo e status
            if ($request->filled('tipo_espaco')) {
                $query->where('tipo_espaco', $request->query('tipo_espaco'));
            }
            if ($request->filled('status')) {
                $query->where('status', $request->query('status'));
            }

            // Intervalo semanal (prioritário se informado)
            if ($start && $end) {
                try {
                    $startDate = Carbon::parse($start)->startOfDay();
                    $endDate = Carbon::parse($end)->endOfDay();
                    $query->whereBetween('data_evento', [
                        $startDate->toDateString(),
                        $endDate->toDateString(),
                    ]);
                } catch (\Exception $e) {
                    // Se houver erro no parse, seguir para filtro por ano/mês
                }
            } elseif ($year && $month) {
                $query->whereYear('data_evento', $year)
                    ->whereMonth('data_evento', $month);
            }

            $reservas = $query->orderBy('data_evento', 'asc')
                ->orderBy('hora_inicio', 'asc')
                ->get([
                    'id',
                    'codigo_reserva',
                    'tipo_espaco',
                    'status',
                    'data_evento',
                    'hora_inicio',
                    'hora_fim',
                    'evento_titulo',
                    'evento_descricao',
                    'numero_participantes',
                    'solicitante_nome',
                    'solicitante_email',
                    'solicitante_telefone',
                ]);

            return response()->json($reservas);
        }

        // Caso contrário, renderizar a view do calendário
        $reservas = ReservaEspaco::orderBy('data_evento', 'desc')
            ->orderBy('hora_inicio', 'desc')
            ->get();

        return view('reservas.calendar', [
            'reservas' => $reservas,
        ]);
    }

    /**
     * Compatibilidade: manter o método em PT caso referenciado em algum lugar
     */
    public function calendario(Request $request)
    {
        return $this->calendar($request);
    }

    /**
     * Emitir visto do departamento (aprovar).
     */
    public function vistoAprovar(Request $request, ReservaEspaco $reserva)
    {
        $this->authorize('vistoAprovar', $reserva);

        if (! $reserva->isPendente()) {
            return redirect()->route('reservas.show', $reserva->id)
                ->with('error', 'Visto só pode ser emitido enquanto a reserva está pendente.');
        }

        if (method_exists($reserva, 'vistoDepartamentoAprovado') && $reserva->vistoDepartamentoAprovado()) {
            return redirect()->route('reservas.show', $reserva->id)
                ->with('error', 'O visto do departamento já foi aprovado.');
        }
        if (method_exists($reserva, 'vistoDepartamentoRejeitado') && $reserva->vistoDepartamentoRejeitado()) {
            return redirect()->route('reservas.show', $reserva->id)
                ->with('error', 'O visto do departamento já foi rejeitado.');
        }

        $reserva->update([
            'visto_departamento_status' => 'aprovado',
            'visto_departamento_por' => Auth::id(),
            'visto_departamento_data' => now(),
            'visto_departamento_observacao' => $request->input('observacao'),
        ]);

        return redirect()->route('reservas.show', $reserva->id)
            ->with('success', 'Visto do departamento aprovado.');
    }

    /**
     * Emitir visto do departamento (rejeitar).
     */
    public function vistoRejeitar(Request $request, ReservaEspaco $reserva)
    {
        $this->authorize('vistoRejeitar', $reserva);

        if (! $reserva->isPendente()) {
            return redirect()->route('reservas.show', $reserva->id)
                ->with('error', 'Visto só pode ser emitido enquanto a reserva está pendente.');
        }

        if (method_exists($reserva, 'vistoDepartamentoAprovado') && $reserva->vistoDepartamentoAprovado()) {
            return redirect()->route('reservas.show', $reserva->id)
                ->with('error', 'O visto do departamento já foi aprovado.');
        }
        if (method_exists($reserva, 'vistoDepartamentoRejeitado') && $reserva->vistoDepartamentoRejeitado()) {
            return redirect()->route('reservas.show', $reserva->id)
                ->with('error', 'O visto do departamento já foi rejeitado.');
        }

        $request->validate([
            'observacao' => 'nullable|string|max:500',
        ]);

        $reserva->update([
            'visto_departamento_status' => 'rejeitado',
            'visto_departamento_por' => Auth::id(),
            'visto_departamento_data' => now(),
            'visto_departamento_observacao' => $request->input('observacao'),
        ]);

        return redirect()->route('reservas.show', $reserva->id)
            ->with('success', 'Visto do departamento rejeitado.');
    }
}
