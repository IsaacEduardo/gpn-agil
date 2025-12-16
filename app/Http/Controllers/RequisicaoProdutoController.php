<?php

namespace App\Http\Controllers;

use App\Enums\StatusRequisicao;
use App\Enums\TipoRequisicao;
use App\Http\Requests\StoreRequisicaoProdutoRequest;
use App\Models\Empresa;
use App\Models\Gabinete;
use App\Models\Requisicao;
use App\Models\RequisicaoProduto;
use App\Support\CatalogCache;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RequisicaoProdutoController extends Controller
{
    public function index(Request $request)
    {
        $query = Requisicao::where('tipo', TipoRequisicao::PRODUTO)
            ->with(['usuario', 'produtos'])
            ->orderBy('created_at', 'desc');

        if ($request->has('q') && $request->q) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('codigo_sequencial', 'like', "%{$search}%")
                    ->orWhere('empresa_destinataria', 'like', "%{$search}%")
                    ->orWhere('observacoes', 'like', "%{$search}%")
                    ->orWhereHas('usuario', function ($u) use ($search) {
                        $u->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('empresa') && $request->empresa) {
            $query->where('empresa_destinataria', 'like', "%{$request->empresa}%");
        }

        if ($request->has('solicitante') && $request->solicitante) {
            $query->whereHas('usuario', function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->solicitante}%");
            });
        }

        if ($request->has('data_inicio') && $request->data_inicio) {
            $query->whereDate('data_requisicao', '>=', $request->data_inicio);
        }

        if ($request->has('data_fim') && $request->data_fim) {
            $query->whereDate('data_requisicao', '<=', $request->data_fim);
        }

        if (Auth::check()) {
            $query->accessibleBy(Auth::user());
        }

        $requisicoes = $query->paginate(10);

        return view('requisicoes.produtos.index', compact('requisicoes'));
    }

    public function create()
    {
        $empresas = CatalogCache::empresasList();

        return view('requisicoes.produtos.create', compact('empresas'));
    }

    public function store(StoreRequisicaoProdutoRequest $request)
    {
        $hasMultiple = $request->has('produtos') && is_array($request->input('produtos'));

        try {
            DB::beginTransaction();

            $requisicao = Requisicao::create([
                'tipo' => TipoRequisicao::PRODUTO,
                'data_requisicao' => now()->toDateString(),
                'usuario_id' => Auth::id(),
                'status' => StatusRequisicao::PENDENTE,
                'observacoes' => $request->observacoes,
            ]);

            // Garantir persistência de empresa_id e compatibilizar empresa_destinataria para views antigas
            if ($request->filled('empresa_id')) {
                $empresa = Empresa::find($request->empresa_id);
                if ($empresa) {
                    $requisicao->empresa_id = $empresa->id;
                    $requisicao->empresa_destinataria = $empresa->nome; // compatibilidade
                    $requisicao->save();
                }
            }

            if ($hasMultiple) {
                foreach ($request->input('produtos') as $p) {
                    $nomeProduto = $p['nome_produto'] ?? ($p['descricao'] ?? null);
                    $unidadeMedida = $p['unidade_medida'] ?? ($p['unidade'] ?? null);
                    RequisicaoProduto::create([
                        'requisicao_id' => $requisicao->id,
                        'nome_produto' => $nomeProduto,
                        'quantidade' => (int) $p['quantidade'],
                        'unidade_medida' => $unidadeMedida,
                        'prioridade' => $p['prioridade'] ?? 'Média',
                        'finalidade' => $p['finalidade'] ?? null,
                    ]);
                }
            } else {
                $nomeProduto = $request->input('nome_produto') ?? $request->input('descricao');
                $unidadeMedida = $request->input('unidade_medida') ?? $request->input('unidade');
                RequisicaoProduto::create([
                    'requisicao_id' => $requisicao->id,
                    'nome_produto' => $nomeProduto,
                    'quantidade' => $request->quantidade,
                    'unidade_medida' => $unidadeMedida,
                    'prioridade' => $request->prioridade,
                    'finalidade' => $request->input('finalidade'),
                ]);
            }

            DB::commit();

            return redirect()->route('requisicoes.produtos.print', ['requisicao' => $requisicao->id])
                ->with('success', 'Requisição de produto criada com sucesso!');
        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()
                ->withInput()
                ->with('error', 'Erro ao criar requisição: '.$e->getMessage());
        }
    }

    public function show($id)
    {
        $requisicao = Requisicao::where('tipo', Requisicao::TIPO_PRODUTO)
            ->with(['usuario', 'produtos'])
            ->findOrFail($id);
        $this->authorize('view', $requisicao);

        return view('requisicoes.produtos.show', compact('requisicao'));
    }

    public function edit($id)
    {
        $requisicao = Requisicao::where('tipo', Requisicao::TIPO_PRODUTO)
            ->with('produtos')
            ->findOrFail($id);

        $empresas = \App\Support\CatalogCache::empresasList();

        return view('requisicoes.produtos.edit', compact('requisicao', 'empresas'));
    }

    public function update(Request $request, $id)
    {
        $hasMultiple = $request->has('produtos') && is_array($request->input('produtos'));

        if ($hasMultiple) {
            $request->validate([
                'empresa_id' => 'required|exists:empresas,id',
                'observacoes' => 'nullable|string',
                'produtos' => 'required|array|min:1',
                'produtos.*.nome_produto' => 'required|string|max:255',
                'produtos.*.quantidade' => 'required|integer|min:1',
                'produtos.*.unidade_medida' => 'required|string|max:50',
                'produtos.*.finalidade' => 'nullable|string',
                'produtos.*.prioridade' => 'required|in:Baixa,Média,Alta,Urgente',
            ]);
        } else {
            $request->validate([
                'empresa_id' => 'required|exists:empresas,id',
                'observacoes' => 'nullable|string',
                'nome_produto' => 'required_without:descricao|string|max:255',
                'descricao' => 'required_without:nome_produto|string|max:255',
                'quantidade' => 'required|integer|min:1',
                'unidade_medida' => 'required_without:unidade|string|max:50',
                'unidade' => 'required_without:unidade_medida|string|max:50',
                'finalidade' => 'nullable|string',
                'prioridade' => 'required|in:Baixa,Média,Alta,Urgente',
                'valor_unitario' => 'nullable|numeric|min:0',
            ]);
        }

        try {
            DB::beginTransaction();

            $requisicao = Requisicao::where('tipo', Requisicao::TIPO_PRODUTO)->findOrFail($id);
            $requisicao->update([
                'empresa_id' => $request->empresa_id,
                'observacoes' => $request->observacoes,
            ]);

            // Atualizar empresa_destinataria para compatibilidade
            if ($request->filled('empresa_id')) {
                $empresa = Empresa::find($request->empresa_id);
                if ($empresa) {
                    $requisicao->empresa_destinataria = $empresa->nome;
                    $requisicao->save();
                }
            }

            if ($hasMultiple) {
                // Substitui os produtos existentes pelos enviados
                $requisicao->produtos()->delete();
                foreach ($request->input('produtos') as $p) {
                    $nomeProduto = $p['nome_produto'] ?? ($p['descricao'] ?? null);
                    $unidadeMedida = $p['unidade_medida'] ?? ($p['unidade'] ?? null);
                    RequisicaoProduto::create([
                        'requisicao_id' => $requisicao->id,
                        'nome_produto' => $nomeProduto,
                        'quantidade' => (int) ($p['quantidade'] ?? 0),
                        'unidade_medida' => $unidadeMedida,
                        'prioridade' => $p['prioridade'] ?? 'Média',
                        'finalidade' => $p['finalidade'] ?? null,
                    ]);
                }
            } else {
                $produto = $requisicao->produtos()->first();
                if ($produto) {
                    $nomeProduto = $request->input('nome_produto') ?? $request->input('descricao');
                    $unidadeMedida = $request->input('unidade_medida') ?? $request->input('unidade');

                    $produto->update([
                        'nome_produto' => $nomeProduto,
                        'quantidade' => (int) $request->quantidade,
                        'unidade_medida' => $unidadeMedida,
                        'prioridade' => $request->prioridade,
                        'finalidade' => $request->input('finalidade'),
                    ]);
                } else {
                    // Caso não exista, cria um
                    $nomeProduto = $request->input('nome_produto') ?? $request->input('descricao');
                    $unidadeMedida = $request->input('unidade_medida') ?? $request->input('unidade');
                    RequisicaoProduto::create([
                        'requisicao_id' => $requisicao->id,
                        'nome_produto' => $nomeProduto,
                        'quantidade' => (int) $request->quantidade,
                        'unidade_medida' => $unidadeMedida,
                        'prioridade' => $request->prioridade,
                        'finalidade' => $request->input('finalidade'),
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('requisicoes.produtos.print', ['requisicao' => $requisicao->id])
                ->with('success', 'Requisição de produto atualizada com sucesso!');
        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()
                ->withInput()
                ->with('error', 'Erro ao atualizar requisição: '.$e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $requisicao = Requisicao::where('tipo', Requisicao::TIPO_PRODUTO)->findOrFail($id);
            $requisicao->delete();

            return redirect()->route('requisicoes.produtos.index')
                ->with('success', 'Requisição de produto excluída com sucesso!');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Erro ao excluir requisição: '.$e->getMessage());
        }
    }

    /**
     * Gera o PDF do ofício para a requisição de produtos.
     * ID opcional; se ausente, usa a última requisição cadastrada.
     */
    public function pdf(?int $requisicaoId = null)
    {
        $query = Requisicao::where('tipo', Requisicao::TIPO_PRODUTO)
            ->with(['empresa', 'produtos', 'usuario']);

        $requisicao = $requisicaoId
            ? $query->findOrFail($requisicaoId)
            : $query->orderBy('id', 'desc')->first();

        if ($requisicao) {
            $empresa = $requisicao->empresa ?? ($requisicao->empresa_destinataria ? new Empresa(['nome' => $requisicao->empresa_destinataria]) : new Empresa(['nome' => '']));
            $produtos = $requisicao->produtos ?? collect();
        } else {
            $empresa = new Empresa(['nome' => 'EMPRESA EXEMPLO']);
            $requisicao = new Requisicao([
                'tipo' => Requisicao::TIPO_PRODUTO,
                'codigo_sequencial' => 'PRO-10/2025-001',
                'data_requisicao' => now(),
                'empresa_destinataria' => $empresa->nome,
            ]);
            $produtos = collect([
                new RequisicaoProduto(['nome_produto' => 'Papel A4', 'quantidade' => 50, 'unidade_medida' => 'pacote', 'prioridade' => 'Média']),
                new RequisicaoProduto(['nome_produto' => 'Canetas', 'quantidade' => 100, 'unidade_medida' => 'unidade', 'prioridade' => 'Baixa']),
            ]);
        }

        $pdf = Pdf::loadView('requisicoes.produtos.pdf', [
            'requisicao' => $requisicao,
            'empresa' => $empresa,
            'produtos' => $produtos,
        ])->setPaper('a4');

        $codigo = $requisicao->codigo_sequencial ?? 'PRO-XXXX-000';
        $codigoSeguro = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $codigo);
        $nomeArquivo = 'Oficio_Requisicao_Produtos_'.$codigoSeguro.'.pdf';

        return $pdf->stream($nomeArquivo);
    }

    public function print(?int $requisicaoId = null)
    {
        $pdfUrl = $requisicaoId
            ? route('requisicoes.produtos.pdf', ['requisicao' => $requisicaoId])
            : route('requisicoes.produtos.pdf');
        $redirectUrl = route('requisicoes.index');

        return view('requisicoes.print', compact('pdfUrl', 'redirectUrl'));
    }
}
