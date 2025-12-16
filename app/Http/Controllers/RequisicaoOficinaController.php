<?php

namespace App\Http\Controllers;

use App\Enums\StatusRequisicao;
use App\Enums\TipoRequisicao;
use App\Http\Requests\StoreRequisicaoOficinaRequest;
use App\Models\Empresa;
use App\Models\Gabinete;
use App\Models\Requisicao;
use App\Models\RequisicaoOficina;
use App\Support\CatalogCache;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RequisicaoOficinaController extends Controller
{
    public function index(Request $request)
    {
        $query = Requisicao::where('tipo', TipoRequisicao::OFICINA)
            ->with(['usuario:id,name,departamento_id', 'oficina'])
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

        return view('requisicoes.oficina.index', compact('requisicoes'));
    }

    public function create()
    {
        $viaturas = CatalogCache::viaturasOperacionais();
        $empresas = CatalogCache::empresasList();

        return view('requisicoes.oficina.create', compact('viaturas', 'empresas'));
    }

    public function store(StoreRequisicaoOficinaRequest $request)
    {
        $newRequisicaoId = null;
        DB::transaction(function () use ($request, &$newRequisicaoId) {
            $requisicao = Requisicao::create([
                'tipo' => TipoRequisicao::OFICINA,
                'data_requisicao' => $request->data_requisicao ? (string) $request->data_requisicao : now()->toDateString(),
                'usuario_id' => Auth::id(),
                'status' => StatusRequisicao::PENDENTE,
                'observacoes' => $request->observacoes,
            ]);
            $newRequisicaoId = $requisicao->id;

            // Garantir persistência de empresa_id e compatibilizar empresa_destinataria para views antigas
            if ($request->filled('empresa_id')) {
                $empresa = Empresa::find($request->empresa_id);
                if ($empresa) {
                    $requisicao->empresa_id = $empresa->id;
                    $requisicao->empresa_destinataria = $empresa->nome; // compatibilidade
                    $requisicao->save();
                }
            }

            $tipoManutencao = $request->input('tipo_manutencao');
            $tipoServico = match ($tipoManutencao) {
                'Preventiva' => 'Preventivo',
                'Corretiva' => 'Corretivo',
                'Revisão' => 'Corretivo',
                default => 'Corretivo',
            };

            $prioridade = $request->input('prioridade');
            $urgencia = match ($prioridade) {
                'Baixa' => 'baixa',
                'Média' => 'media',
                'Alta' => 'alta',
                'Urgente' => 'critica',
                default => 'media',
            };

            RequisicaoOficina::create([
                'requisicao_id' => $requisicao->id,
                'viatura_id' => $request->viatura_id,
                'tipo_servico' => $tipoServico,
                'quilometragem_atual' => $request->quilometragem_atual,
                'descricao_problema' => $request->descricao_problema,
                'servicos_solicitados' => $request->servicos_solicitados,
                'urgencia' => $urgencia,
            ]);
        });

        return redirect()->route('requisicoes.oficina.print', ['requisicao' => $newRequisicaoId])
            ->with('success', 'Requisição de oficina criada com sucesso.');
    }

    public function show($id)
    {
        $requisicao = Requisicao::where('tipo', Requisicao::TIPO_OFICINA)
            ->with(['usuario', 'oficina'])
            ->findOrFail($id);
        $this->authorize('view', $requisicao);

        return view('requisicoes.oficina.show', compact('requisicao'));
    }

    public function edit($id)
    {
        $requisicao = Requisicao::where('tipo', Requisicao::TIPO_OFICINA)
            ->with('oficina')
            ->findOrFail($id);

        $empresas = \App\Support\CatalogCache::empresasList();
        $viaturas = \App\Support\CatalogCache::viaturasOperacionais();

        return view('requisicoes.oficina.edit', compact('requisicao', 'empresas', 'viaturas'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'empresa_id' => 'required|exists:empresas,id',
            'observacoes' => 'nullable|string',
            'viatura_id' => 'required|exists:viaturas,id',
            'quilometragem_atual' => 'nullable|integer|min:0',
            'descricao_problema' => 'required|string',
            'servicos_solicitados' => 'nullable|string',
            'tipo_manutencao' => 'required|string|in:Preventiva,Corretiva,Revisão',
            'prioridade' => 'required|string|in:Baixa,Média,Alta,Urgente',
        ]);

        try {
            DB::beginTransaction();

            $requisicao = Requisicao::where('tipo', Requisicao::TIPO_OFICINA)->findOrFail($id);

            $requisicao->update([
                'empresa_id' => $request->empresa_id,
                'observacoes' => $request->observacoes,
            ]);

            $tipoManutencao = $request->input('tipo_manutencao');
            $tipoServico = match ($tipoManutencao) {
                'Preventiva' => 'Preventivo',
                'Corretiva' => 'Corretivo',
                'Revisão' => 'Corretivo',
                default => 'Corretivo',
            };

            $prioridade = $request->input('prioridade');
            $urgencia = match ($prioridade) {
                'Baixa' => 'baixa',
                'Média' => 'media',
                'Alta' => 'alta',
                'Urgente' => 'critica',
                default => 'media',
            };

            $oficina = $requisicao->oficina;
            if ($oficina) {
                $oficina->update([
                    'viatura_id' => $request->viatura_id,
                    'quilometragem_atual' => $request->quilometragem_atual,
                    'descricao_problema' => $request->descricao_problema,
                    'servicos_solicitados' => $request->servicos_solicitados,
                    'tipo_servico' => $tipoServico,
                    'urgencia' => $urgencia,
                ]);
            }

            DB::commit();

            return redirect()->route('requisicoes.oficina.print', ['requisicao' => $requisicao->id])
                ->with('success', 'Requisição de oficina atualizada com sucesso!');
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
            $requisicao = Requisicao::where('tipo', Requisicao::TIPO_OFICINA)->findOrFail($id);
            $requisicao->delete();

            return redirect()->route('requisicoes.oficina.index')
                ->with('success', 'Requisição de oficina excluída com sucesso!');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Erro ao excluir requisição: '.$e->getMessage());
        }
    }

    /**
     * Gera o PDF do ofício para a requisição de oficina.
     * Aceita um ID opcional; se não informado, usa a última requisição cadastrada.
     */
    public function pdf(?int $requisicaoId = null)
    {
        // Buscar requisição de oficina com suas relações
        $query = Requisicao::where('tipo', Requisicao::TIPO_OFICINA)
            ->with(['empresa', 'oficina.viatura', 'usuario']);

        $requisicao = $requisicaoId
            ? $query->findOrFail($requisicaoId)
            : $query->orderBy('id', 'desc')->first();

        // Fallback: criar um exemplo quando não houver registro no banco
        if (! $requisicao) {
            $viatura = new \App\Models\Viatura([
                'marca' => 'Toyota',
                'modelo' => 'Lexus V8',
                'placa' => 'LD-25-98-FG',
                'ano' => '2020',
                'tipo' => 'SUV',
            ]);

            $empresa = new Empresa(['nome' => 'EMPRESA INDAGRO']);

            $oficina = new RequisicaoOficina([
                'tipo_servico' => 'Preventivo',
                'descricao_problema' => 'Mudança de óleo e filtros',
                'urgencia' => 'media',
                'quilometragem_atual' => 0,
            ]);

            $requisicao = new Requisicao([
                'tipo' => Requisicao::TIPO_OFICINA,
                'codigo_sequencial' => 'OFI-03/2024-001',
                'data_requisicao' => now(),
                'empresa_destinataria' => $empresa->nome,
                'observacoes' => null,
            ]);

            $requisicao->setRelation('empresa', $empresa);
            $requisicao->setRelation('oficina', $oficina);
            $oficina->setRelation('viatura', $viatura);
        } else {
            // Garantir que a viatura esteja carregada
            $requisicao->loadMissing(['oficina.viatura']);
        }

        $data = [
            'requisicao' => $requisicao,
            'oficina' => $requisicao->oficina,
            'viatura' => optional($requisicao->oficina)->viatura,
            'empresa' => $requisicao->empresa ?? ($requisicao->empresa_destinataria ? new Empresa(['nome' => $requisicao->empresa_destinataria]) : null),
        ];

        $pdf = Pdf::loadView('requisicoes.oficina.pdf', $data)->setPaper('A4');
        // Sanitizar o nome do arquivo para evitar caracteres inválidos (ex.: "/" e "\\")
        $codigo = $requisicao->codigo_sequencial ?? 'demo';
        $codigoSeguro = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $codigo);
        $nomeArquivo = 'Oficio_Requisicao_Oficina_'.$codigoSeguro.'.pdf';

        return $pdf->stream($nomeArquivo);
    }

    public function print(?int $requisicaoId = null)
    {
        $pdfUrl = $requisicaoId
            ? route('requisicoes.oficina.pdf', ['requisicao' => $requisicaoId])
            : route('requisicoes.oficina.pdf.noid');
        $redirectUrl = route('requisicoes.index');

        return view('requisicoes.print', compact('pdfUrl', 'redirectUrl'));
    }
}
