<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use App\Services\RequisicaoService;
use App\Support\CatalogCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class RequisicaoController extends Controller
{
    protected RequisicaoService $service;

    public function __construct(RequisicaoService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Eager load das relações usadas na listagem para evitar N+1
        // (usuario.departamento na coluna de solicitante; oficina no badge de manutenção).
        $query = Requisicao::with(['usuario.departamento:id,sigla,nome', 'oficina']);

        if (Auth::check()) {
            $query->visibleToUser(Auth::user());
        }

        // Apply scopes
        $query->search($request->q)
            ->byTipo($request->tipo)
            ->byStatus($request->status);

        if ($request->filled('empresa')) {
            $query->where('empresa_destinataria', 'like', '%'.$request->empresa.'%');
        }

        if ($request->filled('solicitante')) {
            $nome = trim($request->solicitante);
            $query->whereHas('usuario', function ($u) use ($nome) {
                $u->where('name', 'like', "%{$nome}%");
            });
        }

        // Intervalo de datas
        if ($request->filled('data_inicio')) {
            $query->whereDate('data_requisicao', '>=', $request->data_inicio);
        }
        if ($request->filled('data_fim')) {
            $query->whereDate('data_requisicao', '<=', $request->data_fim);
        }

        // Ordenação segura
        $allowedSort = ['codigo_sequencial', 'tipo', 'data_requisicao', 'empresa_destinataria', 'status'];
        $sort = $request->get('sort', 'data_requisicao');
        $direction = $request->get('direction', 'desc');

        if (! in_array($sort, $allowedSort)) {
            $sort = 'data_requisicao';
        }
        if (! in_array(strtolower($direction), ['asc', 'desc'])) {
            $direction = 'desc';
        }

        $query->orderBy($sort, $direction);

        $requisicoes = $query->paginate((int) $request->get('per_page', 10))->withQueryString();

        return view('requisicoes.index', compact('requisicoes'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $this->authorize('create', Requisicao::class);
        $tipo = $request->query('tipo', 'produto');

        $viaturas = [];
        if ($tipo == 'oficina') {
            $viaturas = CatalogCache::viaturasOperacionais();
        }
        $empresas = CatalogCache::empresasList();

        return view('requisicoes.create', compact('tipo', 'viaturas', 'empresas'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Requisicao::class);

        $validator = Validator::make($request->all(), [
            'tipo' => 'required|in:produto,oficina,servico,passagem',
            'empresa_destinataria' => 'required|string|max:255',
            'observacoes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->route('requisicoes.create')
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $requisicao = $this->service->createRequisicao($request->all(), Auth::user());
            $route = $this->service->getRedirectRoute($requisicao->tipo->value ?? $requisicao->tipo);

            // Se a rota for a genérica show, passamos o ID
            if (str_contains($route, 'requisicoes.show')) {
                return redirect()->route('requisicoes.show', $requisicao->id);
            }

            // Para as rotas de criação de itens específicos (que usam sessão ou cookie para saber qual a última requisicao)
            // Assumindo que o fluxo original mantinha o ID na sessão ou que o 'novo' pega a última do usuário
            return redirect()->route($route);

        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao criar requisição: '.$e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Requisicao $requisicao)
    {
        $this->authorize('view', $requisicao);

        $this->service->loadRelationships($requisicao);

        return view('requisicoes.show', compact('requisicao'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Requisicao $requisicao)
    {
        if ($requisicao->status === StatusRequisicao::APROVADO) {
            return redirect()->route('requisicoes.show', $requisicao->id)
                ->with('error', 'Não é possível editar uma requisição já aprovada.');
        }

        return view('requisicoes.edit', compact('requisicao'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Requisicao $requisicao)
    {
        // A validação de status já é feita no service, mas mantemos aqui para UX rápida
        if ($requisicao->status === StatusRequisicao::APROVADO) {
            return redirect()->route('requisicoes.show', $requisicao->id)
                ->with('error', 'Não é possível editar uma requisição já aprovada.');
        }

        $validator = Validator::make($request->all(), [
            'empresa_destinataria' => 'required|string|max:255',
            'observacoes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->route('requisicoes.edit', $requisicao->id)
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $this->service->update($requisicao, $request->all());

            return redirect()->route('requisicoes.show', $requisicao->id)
                ->with('success', 'Requisição atualizada com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Requisicao $requisicao)
    {
        try {
            $this->service->delete($requisicao);

            return redirect()->route('requisicoes.index')
                ->with('success', 'Requisição excluída com sucesso!');
        } catch (\Exception $e) {
            return redirect()->route('requisicoes.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Aprovar a requisição.
     */
    public function aprovar(Request $request, Requisicao $requisicao)
    {
        $this->authorize('approve', $requisicao);

        try {
            $this->service->aprovar($requisicao, Auth::user());

            return redirect()->route('requisicoes.show', $requisicao->id)
                ->with('success', 'Requisição aprovada com sucesso!');
        } catch (\Exception $e) {
            return redirect()->route('requisicoes.show', $requisicao->id)
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Rejeitar a requisição.
     */
    public function rejeitar(Request $request, Requisicao $requisicao)
    {
        $this->authorize('reject', $requisicao);

        $request->validate([
            'motivo_rejeicao' => 'required|string|max:1000',
        ]);

        try {
            $this->service->rejeitar($requisicao, Auth::user(), $request->motivo_rejeicao);

            return redirect()->route('requisicoes.show', $requisicao->id)
                ->with('success', 'Requisição rejeitada com sucesso!');
        } catch (\Exception $e) {
            return redirect()->route('requisicoes.show', $requisicao->id)
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Emitir visto do departamento (aprovar).
     */
    public function vistoAprovar(Request $request, Requisicao $requisicao)
    {
        $this->authorize('vistoAprovar', $requisicao);

        try {
            $this->service->emitirVistoAprovado(
                $requisicao,
                Auth::user(),
                $request->input('observacao')
            );
        } catch (\DomainException $e) {
            return redirect()->route('requisicoes.show', $requisicao->id)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('requisicoes.show', $requisicao->id)
            ->with('success', 'Visto do departamento aprovado.');
    }

    /**
     * Emitir visto do departamento (rejeitar).
     */
    public function vistoRejeitar(Request $request, Requisicao $requisicao)
    {
        $this->authorize('vistoRejeitar', $requisicao);

        $request->validate([
            'observacao' => 'nullable|string|max:500',
        ]);

        try {
            $this->service->emitirVistoRejeitado(
                $requisicao,
                Auth::user(),
                (string) $request->input('observacao', '')
            );
        } catch (\DomainException $e) {
            return redirect()->route('requisicoes.show', $requisicao->id)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('requisicoes.show', $requisicao->id)
            ->with('success', 'Visto do departamento rejeitado.');
    }

    /**
     * Lista requisições pendentes de aprovação pelo usuário atual.
     */
    public function pendentes(Request $request)
    {
        $query = Requisicao::with('usuario')->where('status', StatusRequisicao::PENDENTE);

        if (Auth::check()) {
            // Reutiliza o escopo de visibilidade, pois quem aprova geralmente vê o que pode aprovar
            // Mas o escopo visibleToUser é mais abrangente.
            // Para "pendentes de aprovação", a lógica original era bem específica sobre chefes.
            // Vamos manter o scopeVisibleToUser pois ele filtra por departamento/gabinete corretamente.
            $query->visibleToUser(Auth::user());
        }

        $requisicoes = $query->orderBy('created_at', 'asc')->paginate(15);

        return view('requisicoes.pendentes', compact('requisicoes'));
    }

    /**
     * Aprovar múltiplas requisições.
     */
    public function aprovarEmMassa(Request $request)
    {
        $ids = $request->input('requisicoes', []);

        if (empty($ids)) {
            return redirect()->back()->with('error', 'Nenhuma requisição selecionada.');
        }

        $count = 0;
        foreach ($ids as $id) {
            $requisicao = Requisicao::find($id);
            if ($requisicao && $requisicao->status === StatusRequisicao::PENDENTE) {
                if (Auth::user()->can('approve', $requisicao)) {
                    try {
                        $this->service->aprovar($requisicao, Auth::user());
                        $count++;
                    } catch (\Exception $e) {
                        // Ignora erro individual em massa ou loga
                    }
                }
            }
        }

        return redirect()->back()->with('success', "{$count} requisições aprovadas com sucesso.");
    }

    /**
     * Assinar digitalmente a requisição.
     */
    public function sign(Request $request, Requisicao $requisicao)
    {
        $request->validate([
            'password' => 'required|string',
            'certificate_password' => 'nullable|string',
        ]);

        try {
            // Usa o método encapsulado no serviço que lida com status, visto e notificações
            $this->service->assinar($requisicao, Auth::user(), $request->password, $request->input('certificate_password'));

            return back()->with('success', 'Requisição assinada digitalmente com sucesso.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
