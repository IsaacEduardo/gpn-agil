<?php

namespace App\Http\Controllers;

use App\Enums\StatusRequisicao;
use App\Enums\TipoRequisicao;
use App\Http\Requests\StoreRequisicaoPassagemRequest;
use App\Models\Empresa;
use App\Models\Gabinete;
use App\Models\Requisicao;
use App\Models\RequisicaoPassagem;
use App\Support\CatalogCache;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RequisicaoPassagemController extends Controller
{
    public function index(Request $request)
    {
        $query = Requisicao::where('tipo', TipoRequisicao::PASSAGEM)
            ->with(['usuario:id,name,departamento_id', 'passagem'])
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

        return view('requisicoes.passagem.index', compact('requisicoes'));
    }

    public function create()
    {
        $empresas = CatalogCache::empresasList();

        return view('requisicoes.passagem.create', compact('empresas'));
    }

    public function store(StoreRequisicaoPassagemRequest $request)
    {
        try {
            DB::beginTransaction();

            $requisicao = Requisicao::create([
                'tipo' => TipoRequisicao::PASSAGEM,
                'data_requisicao' => now()->toDateString(),
                'usuario_id' => Auth::id(),
                'status' => StatusRequisicao::PENDENTE,
                'observacoes' => $request->observacoes,
            ]);

            if ($request->filled('empresa_id')) {
                $empresa = Empresa::find($request->empresa_id);
                if ($empresa) {
                    $requisicao->empresa_id = $empresa->id;
                    $requisicao->empresa_destinataria = $empresa->nome;
                    $requisicao->save();
                }
            }

            RequisicaoPassagem::create([
                'requisicao_id' => $requisicao->id,
                'beneficiario_nome' => $request->beneficiario_nome,
                'destino' => $request->destino,
                'ida_volta' => (bool) $request->input('ida_volta'),
                'data_partida' => $request->data_partida,
                'data_regresso' => $request->input('data_regresso'),
            ]);

            DB::commit();

            return redirect()->route('requisicoes.passagem.print', ['requisicao' => $requisicao->id])
                ->with('success', 'Requisição de bilhete de passagem criada com sucesso!');
        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()
                ->withInput()
                ->with('error', 'Erro ao criar requisição: '.$e->getMessage());
        }
    }

    public function edit($id)
    {
        $requisicao = Requisicao::where('tipo', Requisicao::TIPO_PASSAGEM)
            ->with('passagem')
            ->findOrFail($id);

        $empresas = CatalogCache::empresasList();

        return view('requisicoes.passagem.edit', compact('requisicao', 'empresas'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'empresa_id' => 'required|exists:empresas,id',
            'observacoes' => 'nullable|string',
            'beneficiario_nome' => 'required|string|max:255',
            'destino' => 'required|string|max:255',
            'ida_volta' => 'nullable|boolean',
            'data_partida' => 'required|date',
            'data_regresso' => 'nullable|date',
        ]);

        try {
            DB::beginTransaction();

            $requisicao = Requisicao::where('tipo', Requisicao::TIPO_PASSAGEM)->findOrFail($id);
            $requisicao->update([
                'empresa_id' => $request->empresa_id,
                'observacoes' => $request->observacoes,
            ]);

            if ($request->filled('empresa_id')) {
                $empresa = Empresa::find($request->empresa_id);
                if ($empresa) {
                    $requisicao->empresa_destinataria = $empresa->nome;
                    $requisicao->save();
                }
            }

            $passagem = $requisicao->passagem;
            if ($passagem) {
                $passagem->update([
                    'beneficiario_nome' => $request->beneficiario_nome,
                    'destino' => $request->destino,
                    'ida_volta' => (bool) $request->input('ida_volta'),
                    'data_partida' => $request->data_partida,
                    'data_regresso' => $request->input('data_regresso'),
                ]);
            } else {
                RequisicaoPassagem::create([
                    'requisicao_id' => $requisicao->id,
                    'beneficiario_nome' => $request->beneficiario_nome,
                    'destino' => $request->destino,
                    'ida_volta' => (bool) $request->input('ida_volta'),
                    'data_partida' => $request->data_partida,
                    'data_regresso' => $request->input('data_regresso'),
                ]);
            }

            DB::commit();

            return redirect()->route('requisicoes.passagem.print', ['requisicao' => $requisicao->id])
                ->with('success', 'Requisição de bilhete de passagem atualizada com sucesso!');
        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()
                ->withInput()
                ->with('error', 'Erro ao atualizar requisição: '.$e->getMessage());
        }
    }

    public function pdf(?int $requisicaoId = null)
    {
        $query = Requisicao::where('tipo', Requisicao::TIPO_PASSAGEM)
            ->with(['empresa', 'passagem', 'usuario']);

        $requisicao = $requisicaoId
            ? $query->findOrFail($requisicaoId)
            : $query->orderBy('id', 'desc')->first();

        $empresa = $requisicao && $requisicao->empresa
            ? $requisicao->empresa
            : ($requisicao && $requisicao->empresa_destinataria ? new Empresa(['nome' => $requisicao->empresa_destinataria]) : new Empresa(['nome' => '']));

        $passagem = $requisicao && $requisicao->passagem ? $requisicao->passagem : new RequisicaoPassagem;

        $pdf = Pdf::loadView('requisicoes.passagem.pdf', [
            'requisicao' => $requisicao ?? new Requisicao([
                'tipo' => Requisicao::TIPO_PASSAGEM,
                'codigo_sequencial' => 'BIL-12/2025-001',
                'data_requisicao' => now(),
                'empresa_destinataria' => $empresa->nome,
            ]),
            'empresa' => $empresa,
            'passagem' => $passagem,
        ])->setPaper('a4');

        $codigo = $requisicao->codigo_sequencial ?? 'BIL-XXXX-000';
        $codigoSeguro = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $codigo);
        $nomeArquivo = 'Oficio_Requisicao_Passagem_'.$codigoSeguro.'.pdf';

        return $pdf->stream($nomeArquivo);
    }

    public function print(?int $requisicaoId = null)
    {
        $pdfUrl = $requisicaoId
            ? route('requisicoes.passagem.pdf', ['requisicao' => $requisicaoId])
            : route('requisicoes.passagem.pdf.noid');
        $redirectUrl = route('requisicoes.index');

        return view('requisicoes.print', compact('pdfUrl', 'redirectUrl'));
    }
}
