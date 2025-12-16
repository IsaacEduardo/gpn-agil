<?php

namespace App\Http\Controllers;

use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use App\Models\TermoEntrega;
use App\Models\Viatura;
use App\Support\CatalogCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDF;

class TermoEntregaController extends Controller
{
    /**
     * Listar termos de entrega (página autônoma).
     */
    public function index(Request $request)
    {
        $query = TermoEntrega::query();

        // Filtros para o novo modelo independente
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->string('tipo'));
        }
        if ($request->filled('beneficiario')) {
            $query->where('beneficiario_nome', 'like', '%' . $request->string('beneficiario') . '%');
        }
        if ($request->filled('viatura_id')) {
            $query->where('viatura_id', (int) $request->input('viatura_id'));
        }

        $termos = $query->latest()->paginate(10)->withQueryString();

        return view('termos.index', compact('termos'));
    }

    /**
     * Formulário de criação de termo independente
     */
    public function create()
    {
        $viaturas = CatalogCache::viaturasList();

        return view('termos.create', compact('viaturas'));
    }

    /**
     * Armazenar termo independente e gerar PDF
     */
    public function store(Request $request)
    {
        $baseRules = [
            'tipo' => 'required|in:definitiva,devolutivo,viatura',
            'beneficiario_nome' => 'required|string|max:255',
            'beneficiario_documento' => 'nullable|string|max:50',
            'beneficiario_setor' => 'nullable|string|max:100',
            'observacoes' => 'nullable|string|max:1000',
        ];

        if ($request->input('tipo') === 'viatura') {
            $rules = $baseRules + [
                'viatura_ids' => 'required|array|min:1',
                'viatura_ids.*' => 'exists:viaturas,id',
            ];
        } else {
            $rules = $baseRules + [
                'items' => 'required|array|min:1',
                'items.*.descricao' => 'required|string|max:255',
                'items.*.quantidade' => 'nullable|numeric|min:0',
                'items.*.unidade' => 'nullable|string|max:20',
            ];
        }

        $validated = $request->validate($rules);

        // Base do termo para as views
        $termoBase = (object) [
            'tipo' => $validated['tipo'],
            'beneficiario_nome' => $validated['beneficiario_nome'],
            'beneficiario_documento' => $validated['beneficiario_documento'] ?? null,
            'beneficiario_setor' => $validated['beneficiario_setor'] ?? null,
            'observacoes' => $validated['observacoes'] ?? null,
        ];

        // Determinar view e dados adicionais
        $view = null;
        $data = ['termo' => $termoBase];

        switch ($validated['tipo']) {
            case 'definitiva':
                $view = 'termos.definitiva';
                $data['items'] = $validated['items'];
                break;
            case 'devolutivo':
                $view = 'termos.devolutivo';
                $data['items'] = $validated['items'];
                break;
            case 'viatura':
                $view = 'termos.viatura';
                $data['viaturas'] = Viatura::whereIn('id', $validated['viatura_ids'])->get();
                break;
        }

        // Gerar PDF e salvar termo dentro de transação para consistência
        $novoTermo = DB::transaction(function () use ($view, $data, $validated) {
            $pdf = PDF::loadView($view, $data);
            $nomeArquivo = 'termo_' . $validated['tipo'] . '_' . now()->format('Ymd_His') . '.pdf';
            $caminho = 'termos/' . $nomeArquivo;
            Storage::disk('public')->put($caminho, $pdf->output());

            $viaturaIdParaRegistro = $validated['tipo'] === 'viatura' ? ($validated['viatura_ids'][0] ?? null) : null;

            return TermoEntrega::create([
                'tipo' => $validated['tipo'],
                'caminho_arquivo' => $caminho,
                'beneficiario_nome' => $validated['beneficiario_nome'],
                'beneficiario_documento' => $validated['beneficiario_documento'] ?? null,
                'beneficiario_setor' => $validated['beneficiario_setor'] ?? null,
                'item_descricao' => null,
                'quantidade' => null,
                'unidade' => null,
                'observacoes' => $validated['observacoes'] ?? null,
                'viatura_id' => $viaturaIdParaRegistro,
            ]);
        });

        // Redirecionar para visualização do PDF
        return redirect()->route('termos.show', $novoTermo->id)->with('success', 'Termo gerado com sucesso.');
    }

    /**
     * Gerar termo de entrega para a requisição.
     */
    public function gerar($requisicao_id, $tipo)
    {
        $requisicao = Requisicao::with(['usuario', 'aprovador'])->findOrFail($requisicao_id);

        // Verificar se a requisição foi aprovada
        if ($requisicao->status !== StatusRequisicao::APROVADO) {
            return redirect()->route('termos.index')
                ->with('error', 'Não é possível gerar termo para requisição não aprovada.');
        }

        // Verificar se o tipo é válido
        if (! in_array($tipo, ['padrao', 'devolutivo'])) {
            return redirect()->route('termos.index')
                ->with('error', 'Tipo de termo inválido.');
        }

        // Carregar dados específicos com base no tipo de requisição
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
        }

        // Mapear tipo para o nome da view existente
        $viewName = $tipo === 'devolutivo' ? 'devolucao' : 'padrao';

        // Gerar o PDF
        $pdf = PDF::loadView('termos.' . $viewName, compact('requisicao'));

        // Salvar o PDF
        $nomeArquivo = 'termo_' . $tipo . '_' . $requisicao->codigo_sequencial . '.pdf';
        $caminho = 'termos/' . $nomeArquivo;
        Storage::disk('public')->put($caminho, $pdf->output());

        // Registrar o termo no banco de dados
        $termo = TermoEntrega::create([
            'requisicao_id' => $requisicao_id,
            'tipo' => $tipo,
            'caminho_arquivo' => $caminho,
        ]);

        return redirect()->route('termos.index')
            ->with('success', 'Termo de entrega gerado com sucesso!');
    }

    /**
     * Exibir o termo de entrega.
     */
    public function show(TermoEntrega $termo)
    {
        // Verificar se o arquivo existe
        if (! Storage::disk('public')->exists($termo->caminho_arquivo)) {
            abort(404);
        }

        $path = Storage::disk('public')->path($termo->caminho_arquivo);
        if (request()->boolean('download')) {
            return response()->download($path, basename($path));
        }

        return response()->file($path);
    }

    /**
     * Exibir o PDF do termo de entrega.
     */
    public function pdf(TermoEntrega $termo)
    {
        // Verificar se o arquivo existe
        if (! Storage::disk('public')->exists($termo->caminho_arquivo)) {
            abort(404);
        }

        $path = Storage::disk('public')->path($termo->caminho_arquivo);

        return response()->file($path);
    }

    /**
     * Excluir o termo de entrega.
     */
    public function destroy(TermoEntrega $termo)
    {
        $requisicao_id = $termo->requisicao_id;

        // Excluir o arquivo físico
        if (Storage::disk('public')->exists($termo->caminho_arquivo)) {
            Storage::disk('public')->delete($termo->caminho_arquivo);
        }

        // Excluir o registro do banco
        $termo->delete();

        return redirect()->route('termos.index')
            ->with('success', 'Termo de entrega excluído com sucesso!');
    }
}
