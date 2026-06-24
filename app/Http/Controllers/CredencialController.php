<?php

namespace App\Http\Controllers;

use App\Models\TermoEntrega;
use App\Models\Viatura;
use App\Support\CatalogCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDF;

class CredencialController extends Controller
{
    /**
     * Listar credenciais.
     */
    public function index(Request $request)
    {
        if (! Auth::user()->can('credenciais.listar') && ! Auth::user()->isAdmin()) {
            abort(403, 'Acesso não autorizado.');
        }

        $query = TermoEntrega::where('tipo', 'credencial');

        if ($request->filled('beneficiario')) {
            $query->where('beneficiario_nome', 'like', '%'.$request->string('beneficiario').'%');
        }
        if ($request->filled('viatura_id')) {
            $query->where('viatura_id', (int) $request->input('viatura_id'));
        }

        $credenciais = $query->latest()->paginate(10)->withQueryString();

        return view('credenciais.index', compact('credenciais'));
    }

    /**
     * Formulário de criação de credencial
     */
    public function create()
    {
        $viaturas = CatalogCache::viaturasList();

        return view('credenciais.create', compact('viaturas'));
    }

    /**
     * Armazenar credencial e gerar PDF
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'beneficiario_nome' => 'required|string|max:255',
            'beneficiario_documento' => 'required|string|max:50',
            'beneficiario_documento_emitido_em' => 'required|date',
            'beneficiario_documento_emitido_local' => 'required|string|max:100',
            'beneficiario_setor' => 'nullable|string|max:100',
            'observacoes' => 'nullable|string|max:1000',
            'viatura_id' => 'required|exists:viaturas,id',
        ]);

        $viatura = Viatura::find($validated['viatura_id']);

        // Objeto para a view PDF
        $termoBase = (object) [
            'tipo' => 'credencial',
            'beneficiario_nome' => $validated['beneficiario_nome'],
            'beneficiario_documento' => $validated['beneficiario_documento'],
            'beneficiario_documento_emitido_em' => $validated['beneficiario_documento_emitido_em'],
            'beneficiario_documento_emitido_local' => $validated['beneficiario_documento_emitido_local'],
            'beneficiario_setor' => $validated['beneficiario_setor'] ?? null,
            'observacoes' => $validated['observacoes'] ?? null,
        ];

        // Gerar PDF e salvar registro dentro de transação
        $novoTermo = DB::transaction(function () use ($termoBase, $validated, $viatura) {
            $data = [
                'termo' => $termoBase,
                'viatura' => $viatura, // Passando viatura singular para a view
                'viaturas' => collect([$viatura]), // Mantendo compatibilidade caso a view espere collection
            ];

            $pdf = PDF::loadView('termos.credencial', $data);
            $nomeArquivo = 'credencial_'.now()->format('Ymd_His').'.pdf';
            $caminho = 'termos/'.$nomeArquivo; // Mantendo no mesmo diretório de termos por simplicidade
            Storage::disk('public')->put($caminho, $pdf->output());

            return TermoEntrega::create([
                'tipo' => 'credencial',
                'caminho_arquivo' => $caminho,
                'beneficiario_nome' => $validated['beneficiario_nome'],
                'beneficiario_documento' => $validated['beneficiario_documento'],
                'beneficiario_documento_emitido_em' => $validated['beneficiario_documento_emitido_em'],
                'beneficiario_documento_emitido_local' => $validated['beneficiario_documento_emitido_local'],
                'beneficiario_setor' => $validated['beneficiario_setor'] ?? null,
                'observacoes' => $validated['observacoes'] ?? null,
                'viatura_id' => $validated['viatura_id'],
            ]);
        });

        // Redirecionar para visualização do PDF
        return redirect()->route('credenciais.show', $novoTermo->id)->with('success', 'Credencial gerada com sucesso.');
    }

    /**
     * Exibir a credencial.
     */
    public function show($id)
    {
        $credencial = TermoEntrega::where('tipo', 'credencial')->findOrFail($id);

        $this->authorize('view', $credencial);

        // Verificar se o arquivo existe
        if (! Storage::disk('public')->exists($credencial->caminho_arquivo)) {
            abort(404);
        }

        $path = Storage::disk('public')->path($credencial->caminho_arquivo);
        if (request()->boolean('download')) {
            return response()->download($path, basename($path));
        }

        return response()->file($path);
    }

    /**
     * Excluir a credencial.
     */
    public function destroy($id)
    {
        $credencial = TermoEntrega::where('tipo', 'credencial')->findOrFail($id);
        $this->authorize('delete', $credencial);

        // Excluir o arquivo físico
        if (Storage::disk('public')->exists($credencial->caminho_arquivo)) {
            Storage::disk('public')->delete($credencial->caminho_arquivo);
        }

        // Excluir o registro do banco
        $credencial->delete();

        return redirect()->route('credenciais.index')
            ->with('success', 'Credencial excluída com sucesso!');
    }
}
