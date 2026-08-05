<?php

namespace App\Http\Controllers;

use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Lote;
use App\Models\ModeloDocumento;
use App\Models\Requerente;
use App\Models\SolicitacaoAtribuicao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SolicitacaoAtribuicaoController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(SolicitacaoAtribuicao::class, 'solicitacao');
    }

    public function index(Request $request)
    {
        $query = SolicitacaoAtribuicao::with(['requerente', 'lote']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('numero_protocolo', 'like', "%{$search}%")
                  ->orWhereHas('requerente', function ($req) use ($search) {
                      $req->where('nome_razao_social', 'like', "%{$search}%")
                          ->orWhere('nif_bi', 'like', "%{$search}%");
                  })
                  ->orWhereHas('lote', function ($l) use ($search) {
                      $l->where('codigo_lote', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('finalidade_uso')) {
            $query->where('finalidade_uso', $request->finalidade_uso);
        }

        $solicitacoes = $query->orderByDesc('created_at')->paginate(15);

        return view('solicitacoes.index', compact('solicitacoes'));
    }

    public function create()
    {
        $requerentes = Requerente::orderBy('nome_razao_social')->get();
        $lotesDisponiveis = Lote::where('status', 'DISPONIVEL')->orderBy('codigo_lote')->get();

        return view('solicitacoes.create', compact('requerentes', 'lotesDisponiveis'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'requerente_id' => 'required|exists:requerentes,id',
            'lote_id' => 'nullable|exists:lotes,id',
            'finalidade_uso' => 'required|in:RESIDENCIAL,COMERCIAL,AGROPECUARIA,INDUSTRIAL,SOCIAL_INSTITUCIONAL',
            'modalidade_atribuicao' => 'required|in:CDRU,COMPRA_VENDA,PERMISSAO_USO,ARRENDAMENTO',
        ]);

        $validated['status'] = 'SUBMETIDO';
        $validated['created_by_user_id'] = Auth::id();

        $solicitacao = DB::transaction(function () use ($validated) {
            $sol = SolicitacaoAtribuicao::create($validated);

            // Reservar lote se selecionado
            if ($sol->lote_id) {
                $lote = Lote::find($sol->lote_id);
                if ($lote && $lote->status === 'DISPONIVEL') {
                    $lote->update(['status' => 'RESERVADO']);
                }
            }

            return $sol;
        });

        return redirect()->route('solicitacoes.show', $solicitacao)
            ->with('success', 'Solicitação de atribuição aberta com sucesso. Protocolo: ' . $solicitacao->numero_protocolo);
    }

    public function show(SolicitacaoAtribuicao $solicitacao)
    {
        $solicitacao->load(['requerente', 'lote', 'analisesTecnicas.tecnico', 'documentoTermo', 'createdBy']);
        $lotesDisponiveis = Lote::where('status', 'DISPONIVEL')->orWhere('id', $solicitacao->lote_id)->get();

        return view('solicitacoes.show', compact('solicitacao', 'lotesDisponiveis'));
    }

    public function vincularLote(Request $request, SolicitacaoAtribuicao $solicitacao)
    {
        $this->authorize('update', $solicitacao);

        $request->validate([
            'lote_id' => 'required|exists:lotes,id',
        ]);

        DB::transaction(function () use ($solicitacao, $request) {
            // Se já havia um lote reservado, libertar
            if ($solicitacao->lote_id && $solicitacao->lote_id != $request->lote_id) {
                Lote::where('id', $solicitacao->lote_id)->update(['status' => 'DISPONIVEL']);
            }

            $novoLote = Lote::findOrFail($request->lote_id);
            $novoLote->update(['status' => 'RESERVADO']);

            $solicitacao->update(['lote_id' => $novoLote->id]);
        });

        return redirect()->route('solicitacoes.show', $solicitacao)
            ->with('success', 'Lote de terra reservado e vinculado à solicitação com sucesso.');
    }

    public function transicionarStatus(Request $request, SolicitacaoAtribuicao $solicitacao)
    {
        $request->validate([
            'novo_status' => 'required|in:EM_TRIAGEM,EM_VISTORIA,EM_ANALISE_JURIDICA,AGUARDANDO_HOMOLOGACAO,APROVADO,REJEITADO,CANCELADO',
            'motivo_rejeicao' => 'required_if:novo_status,REJEITADO|nullable|string',
        ]);

        $novoStatus = $request->novo_status;

        // Aprovar/rejeitar é a decisão de mérito do processo e exige homologação;
        // as restantes transições fazem parte da instrução e bastam 'analisar'.
        $this->authorize(
            in_array($novoStatus, ['APROVADO', 'REJEITADO'], true) ? 'homologar' : 'update',
            $solicitacao
        );

        DB::transaction(function () use ($solicitacao, $novoStatus, $request) {
            $updateData = ['status' => $novoStatus];

            if ($novoStatus === 'REJEITADO' || $novoStatus === 'CANCELADO') {
                $updateData['motivo_rejeicao'] = $request->motivo_rejeicao;

                // Se tinha lote reservado, libertar lote
                if ($solicitacao->lote_id) {
                    Lote::where('id', $solicitacao->lote_id)->update(['status' => 'DISPONIVEL']);
                }
            }

            if ($novoStatus === 'APROVADO') {
                $updateData['data_homologacao'] = now();

                // Atualizar lote para ATRIBUIDO
                if ($solicitacao->lote_id) {
                    Lote::where('id', $solicitacao->lote_id)->update(['status' => 'ATRIBUIDO']);
                }
            }

            $solicitacao->update($updateData);
        });

        return redirect()->route('solicitacoes.show', $solicitacao)
            ->with('success', "Status da solicitação alterado para {$novoStatus}.");
    }

    public function emitirTermo(SolicitacaoAtribuicao $solicitacao)
    {
        $this->authorize('homologar', $solicitacao);

        if ($solicitacao->status !== 'APROVADO') {
            return redirect()->back()->with('error', 'Apenas solicitações aprovadas podem ter o Termo de Atribuição emitido.');
        }

        if ($solicitacao->documento_interno_termo_id) {
            return redirect()->route('documentos-internos.show', $solicitacao->documento_interno_termo_id);
        }

        DB::transaction(function () use ($solicitacao) {
            // Obter espécie de documento para Termo/Certidão
            $especie = DocumentoEspecie::firstOrCreate(
                ['nome' => 'Termo de Atribuição de Lote'],
                ['sigla' => 'TAL', 'ativo' => true]
            );

            // Criar DocumentoInterno
            $conteudo = sprintf(
                "<h2 style='text-align: center;'>TERMO OFICIAL DE ATRIBUIÇÃO DE LOTE DE TERRA</h2>" .
                "<p>O Governo Provincial do Namibe concede e atribui formalmente ao requerente <strong>%s</strong> (NIF/BI: %s), " .
                "o lote de terra identificado pelo código <strong>%s</strong>, com área total de <strong>%s m²</strong>, localizado em %s, " .
                "para a finalidade <strong>%s</strong> sob a modalidade <strong>%s</strong>.</p>" .
                "<p><strong>Protocolo de Homologação:</strong> %s<br><strong>Data de Emissão:</strong> %s</p>",
                e($solicitacao->requerente->nome_razao_social),
                e($solicitacao->requerente->nif_bi),
                e($solicitacao->lote->codigo_lote ?? 'N/A'),
                number_format($solicitacao->lote->area_m2 ?? 0, 2, ',', '.'),
                e(($solicitacao->lote->bairro_distrito ?? '') . ', ' . ($solicitacao->lote->municipio ?? 'Namibe')),
                e($solicitacao->finalidade_uso),
                e($solicitacao->modalidade_atribuicao),
                e($solicitacao->numero_protocolo),
                now()->format('d/m/Y H:i')
            );

            $documento = DocumentoInterno::create([
                'documento_especie_id' => $especie->id,
                'titulo' => 'Termo de Atribuição - ' . $solicitacao->numero_protocolo,
                'conteudo_final' => $conteudo,
                'status' => \App\Enums\DocumentoStatus::RASCUNHO,
                'criado_por' => Auth::id(),
                'departamento_id' => Auth::user()->departamento_id ?? \App\Models\Departamento::first()?->id ?? 1,
            ]);

            $solicitacao->update(['documento_interno_termo_id' => $documento->id]);
        });

        return redirect()->route('documentos-internos.show', $solicitacao->documento_interno_termo_id)
            ->with('success', 'Termo de Atribuição de Lote gerado no módulo de documentos.');
    }
}
