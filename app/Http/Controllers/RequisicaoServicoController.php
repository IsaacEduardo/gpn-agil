<?php

namespace App\Http\Controllers;

use App\Enums\StatusRequisicao;
use App\Enums\TipoRequisicao;
use App\Models\Empresa;
use App\Models\Gabinete;
use App\Models\Requisicao;
use App\Models\RequisicaoServico;
use App\Support\CatalogCache;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Requests\StoreRequisicaoServicoRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RequisicaoServicoController extends Controller
{
    public function index()
    {
        $query = Requisicao::where('tipo', TipoRequisicao::SERVICO)
            ->with(['usuario:id,name,departamento_id', 'servicos']);

        if (Auth::check()) {
            $query->accessibleBy(Auth::user());
        }

        $requisicoes = $query
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('requisicoes.index', compact('requisicoes'));
    }

    public function create()
    {
        $empresas = CatalogCache::empresasList();

        return view('requisicoes.servicos.create', compact('empresas'));
    }

    public function store(StoreRequisicaoServicoRequest $request)
    {
        try {
            DB::beginTransaction();

            // O código sequencial é gerado automaticamente pelo RequisicaoObserver

            // Criar a requisição
            $requisicao = Requisicao::create([
                'tipo' => TipoRequisicao::SERVICO,
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

            // Normalizar prioridade para enum do banco
            $prioridadeEnum = match ($request->prioridade) {
                'baixa' => 'Baixa',
                'media' => 'Média',
                'alta' => 'Alta',
                'urgente' => 'Urgente',
                default => 'Média',
            };

            // Converter data prevista (date) para datetime esperado pelo banco
            $dataHoraDesejada = \Carbon\Carbon::parse($request->data_prevista)->startOfDay();

            // Criar o registro de serviço (mapeando para colunas reais)
            RequisicaoServico::create([
                'requisicao_id' => $requisicao->id,
                'tipo_servico' => $request->tipo_servico,
                'descricao' => $request->descricao_servico,
                'local' => $request->local_execucao,
                'data_hora_desejada' => $dataHoraDesejada,
                'prioridade' => $prioridadeEnum,
            ]);

            DB::commit();

            return redirect()->route('requisicoes.servico.print', ['requisicao' => $requisicao->id])
                ->with('success', 'Requisição de serviço criada com sucesso!');
        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()
                ->withInput()
                ->with('error', 'Erro ao criar requisição: '.$e->getMessage());
        }
    }

    public function show($id)
    {
        $requisicao = Requisicao::where('tipo', TipoRequisicao::SERVICO)
            ->with(['usuario', 'servicos'])
            ->findOrFail($id);
        $this->authorize('view', $requisicao);

        return view('requisicoes.servico.show', compact('requisicao'));
    }

    public function edit($id)
    {
        $requisicao = Requisicao::where('tipo', TipoRequisicao::SERVICO)
            ->with('servicos')
            ->findOrFail($id);

        $empresas = \App\Support\CatalogCache::empresasList();

        return view('requisicoes.servico.edit', compact('requisicao', 'empresas'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'empresa_id' => 'required|exists:empresas,id',
            'observacoes' => 'nullable|string',
            'tipo_servico' => 'required|string|max:255',
            'descricao_servico' => 'required|string',
            'local_execucao' => 'required|string|max:255',
            'data_prevista' => 'required|date',
            'prioridade' => 'required|in:baixa,media,alta,urgente',
        ]);

        try {
            DB::beginTransaction();

            $requisicao = Requisicao::where('tipo', TipoRequisicao::SERVICO)->findOrFail($id);

            $requisicao->update([
                'empresa_id' => $request->empresa_id,
                'observacoes' => $request->observacoes,
            ]);

            // Compatibilidade: atualizar empresa_destinataria conforme empresa selecionada
            if ($request->filled('empresa_id')) {
                $empresa = \App\Models\Empresa::find($request->empresa_id);
                if ($empresa) {
                    $requisicao->empresa_destinataria = $empresa->nome;
                    $requisicao->save();
                }
            }

            $servico = $requisicao->servicos; // hasOne
            if ($servico) {
                $prioridadeEnum = match ($request->prioridade) {
                    'baixa' => 'Baixa',
                    'media' => 'Média',
                    'alta' => 'Alta',
                    'urgente' => 'Urgente',
                    default => 'Média',
                };

                $dataHoraDesejada = \Carbon\Carbon::parse($request->data_prevista)->startOfDay();

                $servico->update([
                    'tipo_servico' => $request->tipo_servico,
                    'descricao' => $request->descricao_servico,
                    'local' => $request->local_execucao,
                    'data_hora_desejada' => $dataHoraDesejada,
                    'prioridade' => $prioridadeEnum,
                ]);
            } else {
                $prioridadeEnum = match ($request->prioridade) {
                    'baixa' => 'Baixa',
                    'media' => 'Média',
                    'alta' => 'Alta',
                    'urgente' => 'Urgente',
                    default => 'Média',
                };
                $dataHoraDesejada = \Carbon\Carbon::parse($request->data_prevista)->startOfDay();
                \App\Models\RequisicaoServico::create([
                    'requisicao_id' => $requisicao->id,
                    'tipo_servico' => $request->tipo_servico,
                    'descricao' => $request->descricao_servico,
                    'local' => $request->local_execucao,
                    'data_hora_desejada' => $dataHoraDesejada,
                    'prioridade' => $prioridadeEnum,
                ]);
            }

            DB::commit();

            return redirect()->route('requisicoes.servico.print', ['requisicao' => $requisicao->id])
                ->with('success', 'Requisição de serviço atualizada com sucesso!');
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
            $requisicao = Requisicao::where('tipo', Requisicao::TIPO_SERVICO)->findOrFail($id);
            $requisicao->delete();

            return redirect()->route('requisicoes.index')
                ->with('success', 'Requisição de serviço excluída com sucesso!');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Erro ao excluir requisição: '.$e->getMessage());
        }
    }

    /**
     * Gera o PDF do ofício para a requisição de serviço.
     * Aceita um ID opcional; se não informado, usa a última requisição cadastrada.
     */
    public function pdf(?int $requisicaoId = null)
    {
        // Buscar requisição de serviço com suas relações
        $query = Requisicao::where('tipo', Requisicao::TIPO_SERVICO)
            ->with(['empresa', 'servicos', 'usuario']);

        $requisicao = $requisicaoId
            ? $query->findOrFail($requisicaoId)
            : $query->orderBy('id', 'desc')->first();

        // Preparar dados da empresa e serviço
        if ($requisicao) {
            $empresa = $requisicao->empresa ?? ($requisicao->empresa_destinataria ? new Empresa(['nome' => $requisicao->empresa_destinataria]) : new Empresa(['nome' => '']));
            $servico = $requisicao->servicos ?? new RequisicaoServico;
        } else {
            // Fallback de demonstração quando não há registro
            $empresa = new Empresa(['nome' => 'EMPRESA EXEMPLO']);
            $servico = new RequisicaoServico([
                'tipo_servico' => 'Limpeza Técnica',
                'descricao' => 'Limpeza e higienização de área administrativa',
                'local' => 'Sede do Governo Provincial',
                'data_hora_desejada' => now(),
                'prioridade' => 'Média',
            ]);

            $requisicao = new Requisicao([
                'tipo' => Requisicao::TIPO_SERVICO,
                'codigo_sequencial' => 'SER-10/2025-001',
                'data_requisicao' => now(),
                'empresa_destinataria' => $empresa->nome,
            ]);
        }

        // Gerar HTML e compilar via PdfRenderService
        $html = view('requisicoes.servico.pdf', [
            'requisicao' => $requisicao,
            'empresa' => $empresa,
            'servico' => $servico,
        ])->render();

        // Sanitizar nome do arquivo
        $codigo = $requisicao->codigo_sequencial ?? 'SER-XXXX-000';
        $codigoSeguro = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $codigo);
        $nomeArquivo = 'Oficio_Requisicao_Servico_'.$codigoSeguro.'.pdf';

        return app(\App\Services\PdfRenderService::class)->createPdfResponse($html, $nomeArquivo);
    }

    public function print(?int $requisicaoId = null)
    {
        $pdfUrl = $requisicaoId
            ? route('requisicoes.servico.pdf', ['requisicao' => $requisicaoId])
            : route('requisicoes.servico.pdf.noid');
        $redirectUrl = route('requisicoes.index');

        return view('requisicoes.print', compact('pdfUrl', 'redirectUrl'));
    }
}
