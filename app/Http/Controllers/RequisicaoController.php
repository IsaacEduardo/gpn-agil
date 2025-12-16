<?php

namespace App\Http\Controllers;

use App\Enums\StatusRequisicao;
use App\Models\Empresa;
use App\Models\Gabinete;
use App\Models\Requisicao;
use App\Support\CatalogCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class RequisicaoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Requisicao::with('usuario');

        // Escopo para chefe de departamento: ver apenas requisições do seu departamento
        if (Auth::check()) {
            $actor = Auth::user();
            if ($actor->hasPermission('visto_departamento_requisicoes') && ! $actor->hasPermission('requisicoes.view_any')) {
                $depId = $actor->departamento_id;
                if ($depId) {
                    $query->whereHas('usuario', function ($u) use ($depId) {
                        $u->where('departamento_id', $depId)
                            ->orWhereHas('departamentos', function ($qq) use ($depId) {
                                $qq->where('departamentos.id', $depId);
                            });
                    });
                }
            }
        }

        if (Auth::check()) {
            $actor = Auth::user();
            $isAdmin = $actor->role && $actor->role->name === 'admin';
            $canViewAny = $actor->hasPermission('requisicoes.view_any');
            if (! $isAdmin && ! $canViewAny) {
                $actorDeps = (method_exists($actor, 'departamentos') && $actor->departamentos)
                    ? $actor->departamentos->pluck('id')->all() : [];
                if (! count($actorDeps) && $actor->departamento_id) {
                    $actorDeps = [$actor->departamento_id];
                }
                $headedGabIds = Gabinete::where('responsavel_id', $actor->id)->pluck('id')->all();
                if (count($headedGabIds)) {
                    $query->whereHas('usuario', function ($u) use ($headedGabIds) {
                        $u->whereHas('departamento', function ($q) use ($headedGabIds) {
                            $q->whereIn('gabinete_id', $headedGabIds);
                        })
                            ->orWhereHas('departamentos', function ($qq) use ($headedGabIds) {
                                $qq->whereIn('gabinete_id', $headedGabIds);
                            });
                    });
                } elseif (count($actorDeps)) {
                    $query->whereHas('usuario', function ($u) use ($actorDeps) {
                        $u->whereIn('departamento_id', $actorDeps)
                            ->orWhereHas('departamentos', function ($qq) use ($actorDeps) {
                                $qq->whereIn('departamentos.id', $actorDeps);
                            });
                    });
                } else {
                    $query->where('usuario_id', $actor->id);
                }
            }
        }

        // Busca livre (código, empresa, observações)
        if ($request->filled('q')) {
            $q = trim($request->q);
            $query->where(function ($sub) use ($q) {
                $sub->where('codigo_sequencial', 'like', "%{$q}%")
                    ->orWhere('empresa_destinataria', 'like', "%{$q}%")
                    ->orWhere('observacoes', 'like', "%{$q}%");
            });
        }

        // Filtros específicos
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('empresa')) {
            $query->where('empresa_destinataria', 'like', '%'.$request->empresa.'%');
        }

        if ($request->filled('solicitante')) {
            $nome = trim($request->solicitante);
            $query->whereHas('usuario', function ($u) use ($nome) {
                $u->where('name', 'like', "%{$nome}%");
            });
        }

        // Intervalo de datas por data_requisicao
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

        // Paginação
        $perPage = (int) $request->get('per_page', 10);
        if ($perPage < 5 || $perPage > 100) {
            $perPage = 10;
        }

        $requisicoes = $query->paginate($perPage)->withQueryString();

        return view('requisicoes.index', compact('requisicoes'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $tipo = $request->query('tipo', 'produto');

        // Carregar viaturas para o formulário de oficina
        $viaturas = [];
        if ($tipo == 'oficina') {
            $viaturas = CatalogCache::viaturasOperacionais();
        }
        // Carregar lista de empresas para o select
        $empresas = CatalogCache::empresasList();

        return view('requisicoes.create', compact('tipo', 'viaturas', 'empresas'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tipo' => 'required|in:produto,oficina,servico',
            'empresa_destinataria' => 'required|string|max:255',
            'observacoes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->route('requisicoes.create')
                ->withErrors($validator)
                ->withInput();
        }

        // Gerar código sequencial (formato: TIPO-MES/ANO-SEQUENCIAL)
        $tipo = strtoupper(substr($request->tipo, 0, 3));
        $mesAno = date('m/Y');

        // Mapear o tipo para o formato correto aceito pelo enum antes da busca
        $tipoMapeado = '';
        switch ($request->tipo) {
            case 'produto':
                $tipoMapeado = 'produto';
                break;
            case 'oficina':
                $tipoMapeado = 'oficina';
                break;
            case 'servico':
                $tipoMapeado = 'servico';
                break;
            default:
                $tipoMapeado = 'produto';
        }

        // Buscar a última requisição com o tipo mapeado correto
        $ultimaRequisicao = Requisicao::where('tipo', $tipoMapeado)
            ->whereMonth('data_requisicao', date('m'))
            ->whereYear('data_requisicao', date('Y'))
            ->orderBy('id', 'desc')
            ->first();

        $sequencial = $ultimaRequisicao ? intval(substr($ultimaRequisicao->codigo_sequencial, -3)) + 1 : 1;
        $codigoSequencial = $tipo.'-'.$mesAno.'-'.str_pad($sequencial, 3, '0', STR_PAD_LEFT);

        // O tipo já foi mapeado acima

        // Criar a requisição
        $requisicao = Requisicao::create([
            'tipo' => (string) $tipoMapeado, // Garantir que seja tratado como string
            'codigo_sequencial' => $codigoSequencial,
            'data_requisicao' => now(),
            'usuario_id' => Auth::id(),
            'status' => StatusRequisicao::PENDENTE,
            'empresa_destinataria' => $request->empresa_destinataria,
            'observacoes' => $request->observacoes,
        ]);

        // Redirecionar para o formulário específico do tipo de requisição
        switch ($request->tipo) {
            case 'produto':
                return redirect()->route('requisicoes.produtos.create.novo');
            case 'oficina':
                return redirect()->route('requisicoes.oficinas.create.novo');
            case 'servico':
                return redirect()->route('requisicoes.servicos.create.novo');
            default:
                return redirect()->route('requisicoes.show', $requisicao->id);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Requisicao $requisicao)
    {
        $this->authorize('view', $requisicao);
        // Carregar relacionamentos específicos com base no tipo
        switch ($requisicao->tipo) {
            case 'produto':
                $requisicao->load('produtos');
                break;
            case 'oficina':
                $requisicao->load('oficina', 'oficina.viatura');
                break;
            case 'servico':
                $requisicao->load('servicos');
                break;
            case 'passagem':
                $requisicao->load('passagem');
                break;
        }

        // Carregar termos de entrega
        $requisicao->load('termos');
        // Garantir que o solicitante e seus departamentos estejam disponíveis para verificação de escopo no Blade
        $requisicao->loadMissing('usuario.departamentos');

        return view('requisicoes.show', compact('requisicao'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Requisicao $requisicao)
    {
        // Verificar se a requisição já foi aprovada
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
        // Verificar se a requisição já foi aprovada
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

        $requisicao->update([
            'empresa_destinataria' => $request->empresa_destinataria,
            'observacoes' => $request->observacoes,
        ]);

        return redirect()->route('requisicoes.show', $requisicao->id)
            ->with('success', 'Requisição atualizada com sucesso!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Requisicao $requisicao)
    {
        // Verificar se a requisição já foi aprovada
        if ($requisicao->status === StatusRequisicao::APROVADO) {
            return redirect()->route('requisicoes.index')
                ->with('error', 'Não é possível excluir uma requisição já aprovada.');
        }

        // Excluir registros relacionados com base no tipo
        switch ($requisicao->tipo) {
            case 'produto':
                $requisicao->produtos()->delete();
                break;
            case 'oficina':
                $requisicao->oficina()->delete();
                break;
            case 'servico':
                $requisicao->servico()->delete();
                break;
            case 'passagem':
                $requisicao->passagem()->delete();
                break;
        }

        // Excluir termos de entrega
        foreach ($requisicao->termos as $termo) {
            if ($termo->caminho_arquivo) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($termo->caminho_arquivo);
            }
            $termo->delete();
        }

        $requisicao->delete();

        return redirect()->route('requisicoes.index')
            ->with('success', 'Requisição excluída com sucesso!');
    }

    /**
     * Aprovar a requisição.
     */
    public function aprovar(Request $request, Requisicao $requisicao)
    {
        $this->authorize('approve', $requisicao);

        // Removida exigência de visto do departamento para aprovação final
        // O chefe de departamento ou usuários com permissão podem aprovar diretamente

        // Verificar se a requisição já foi aprovada
        if ($requisicao->status === StatusRequisicao::APROVADO) {
            return redirect()->route('requisicoes.show', $requisicao->id)
                ->with('error', 'Esta requisição já foi aprovada.');
        }

        $requisicao->update([
            'status' => StatusRequisicao::APROVADO,
            'aprovado_por' => Auth::id(),
            'data_aprovacao' => now(),
        ]);

        return redirect()->route('requisicoes.show', $requisicao->id)
            ->with('success', 'Requisição aprovada com sucesso!');
    }

    /**
     * Rejeitar a requisição.
     */
    public function rejeitar(Request $request, Requisicao $requisicao)
    {
        $this->authorize('reject', $requisicao);

        // Removida exigência de visto do departamento; rejeição segue apenas status atual
        // Verificar se a requisição já foi aprovada ou rejeitada
        if ($requisicao->status === StatusRequisicao::APROVADO || $requisicao->status === StatusRequisicao::REJEITADO) {
            return redirect()->route('requisicoes.show', $requisicao->id)
                ->with('error', 'Esta requisição já foi processada.');
        }

        $request->validate([
            'motivo_rejeicao' => 'required|string|max:1000',
        ]);

        $requisicao->update([
            'status' => StatusRequisicao::REJEITADO,
            'aprovado_por' => Auth::id(),
            'data_aprovacao' => now(),
            'motivo_rejeicao' => $request->motivo_rejeicao,
        ]);

        return redirect()->route('requisicoes.show', $requisicao->id)
            ->with('success', 'Requisição rejeitada com sucesso!');
    }

    /**
     * Emitir visto do departamento (aprovar).
     */
    public function vistoAprovar(Request $request, Requisicao $requisicao)
    {
        $this->authorize('vistoAprovar', $requisicao);

        if ($requisicao->status !== StatusRequisicao::PENDENTE) {
            return redirect()->route('requisicoes.show', $requisicao->id)
                ->with('error', 'Visto só pode ser emitido enquanto a requisição está pendente.');
        }

        if (method_exists($requisicao, 'vistoDepartamentoAprovado') && $requisicao->vistoDepartamentoAprovado()) {
            return redirect()->route('requisicoes.show', $requisicao->id)
                ->with('error', 'O visto do departamento já foi aprovado.');
        }
        if (method_exists($requisicao, 'vistoDepartamentoRejeitado') && $requisicao->vistoDepartamentoRejeitado()) {
            return redirect()->route('requisicoes.show', $requisicao->id)
                ->with('error', 'O visto do departamento já foi rejeitado.');
        }

        $requisicao->update([
            'visto_departamento_status' => 'aprovado',
            'visto_departamento_por' => Auth::id(),
            'visto_departamento_data' => now(),
            'visto_departamento_observacao' => $request->input('observacao'),
        ]);

        return redirect()->route('requisicoes.show', $requisicao->id)
            ->with('success', 'Visto do departamento aprovado.');
    }

    /**
     * Emitir visto do departamento (rejeitar).
     */
    public function vistoRejeitar(Request $request, Requisicao $requisicao)
    {
        $this->authorize('vistoRejeitar', $requisicao);

        if ($requisicao->status !== StatusRequisicao::PENDENTE) {
            return redirect()->route('requisicoes.show', $requisicao->id)
                ->with('error', 'Visto só pode ser emitido enquanto a requisição está pendente.');
        }

        if (method_exists($requisicao, 'vistoDepartamentoAprovado') && $requisicao->vistoDepartamentoAprovado()) {
            return redirect()->route('requisicoes.show', $requisicao->id)
                ->with('error', 'O visto do departamento já foi aprovado.');
        }
        if (method_exists($requisicao, 'vistoDepartamentoRejeitado') && $requisicao->vistoDepartamentoRejeitado()) {
            return redirect()->route('requisicoes.show', $requisicao->id)
                ->with('error', 'O visto do departamento já foi rejeitado.');
        }

        $request->validate([
            'observacao' => 'nullable|string|max:500',
        ]);

        $requisicao->update([
            'visto_departamento_status' => 'rejeitado',
            'visto_departamento_por' => Auth::id(),
            'visto_departamento_data' => now(),
            'visto_departamento_observacao' => $request->input('observacao'),
        ]);

        return redirect()->route('requisicoes.show', $requisicao->id)
            ->with('success', 'Visto do departamento rejeitado.');
    }

    /**
     * Lista requisições pendentes de aprovação pelo usuário atual.
     */
    public function pendentes(Request $request)
    {
        $query = Requisicao::with('usuario')->where('status', StatusRequisicao::PENDENTE);

        // Aplicar a mesma lógica de filtro de escopo do index, mas focado em pendentes
        if (Auth::check()) {
            $actor = Auth::user();
            $isAdmin = $actor->role && $actor->role->name === 'admin';
            $canViewAny = $actor->hasPermission('requisicoes.view_any');
            
            // Se não for admin e não tiver view_any, filtra pelo escopo
            if (! $isAdmin && ! $canViewAny) {
                $actorDeps = (method_exists($actor, 'departamentos') && $actor->departamentos)
                    ? $actor->departamentos->pluck('id')->all() : [];
                if (! count($actorDeps) && $actor->departamento_id) {
                    $actorDeps = [$actor->departamento_id];
                }
                $headedGabIds = Gabinete::where('responsavel_id', $actor->id)->pluck('id')->all();
                
                if (count($headedGabIds)) {
                    // Chefe de Gabinete vê tudo do seu gabinete
                    $query->whereHas('usuario', function ($u) use ($headedGabIds) {
                        $u->whereHas('departamento', function ($q) use ($headedGabIds) {
                            $q->whereIn('gabinete_id', $headedGabIds);
                        })
                            ->orWhereHas('departamentos', function ($qq) use ($headedGabIds) {
                                $qq->whereIn('gabinete_id', $headedGabIds);
                            });
                    });
                } elseif (count($actorDeps)) {
                    // Chefe de Departamento vê tudo do seu departamento
                    // Assumindo que apenas chefes acessam essa rota para aprovar
                     $query->whereHas('usuario', function ($u) use ($actorDeps) {
                        $u->whereIn('departamento_id', $actorDeps)
                            ->orWhereHas('departamentos', function ($qq) use ($actorDeps) {
                                $qq->whereIn('departamentos.id', $actorDeps);
                            });
                    });
                } else {
                     // Usuário comum não vê nada pendente para aprovar (tecnicamente)
                     // ou vê suas próprias se for para acompanhamento, mas aqui é "Pendentes de Aprovação"
                     // Então retornamos vazio se não tiver papel de chefia
                     $query->whereRaw('0 = 1');
                }
            }
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
                // Verificar autorização para cada item
                if (Auth::user()->can('approve', $requisicao)) {
                    $requisicao->update([
                        'status' => StatusRequisicao::APROVADO,
                        'aprovado_por' => Auth::id(),
                        'data_aprovacao' => now(),
                    ]);
                    $count++;
                }
            }
        }

        return redirect()->back()->with('success', "{$count} requisições aprovadas com sucesso.");
    }
}
