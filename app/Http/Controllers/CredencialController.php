<?php

namespace App\Http\Controllers;

use App\Models\TermoEntrega;
use App\Models\Viatura;
use App\Support\CatalogCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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
        $this->authorize('createCredencial', TermoEntrega::class);

        $viaturas = Viatura::orderBy('identificacao')->get();
        $users = \App\Models\User::with('departamento')->orderBy('name')->get();

        return view('credenciais.create', compact('viaturas', 'users'));
    }

    /**
     * Armazenar credencial e gerar PDF
     */
    public function store(Request $request)
    {
        $this->authorize('createCredencial', TermoEntrega::class);

        $validated = $request->validate([
            'tipo_credencial' => 'required|in:utilizacao_normal,seguir_viagem',
            'beneficiario_nome' => 'required|string|max:255',
            'beneficiario_documento' => 'required|string|max:50',
            'beneficiario_documento_emitido_em' => 'required|date',
            'beneficiario_documento_emitido_local' => 'required|string|max:100',
            'beneficiario_setor' => 'nullable|string|max:100',
            'origem_viagem' => 'required_if:tipo_credencial,seguir_viagem|nullable|string|max:100',
            'destino_viagem' => 'required_if:tipo_credencial,seguir_viagem|nullable|string|max:100',
            'instituicao_vinculo' => 'nullable|string|max:150',
            'motor_numero' => 'nullable|string|max:100',
            'cor_viatura' => 'nullable|string|max:50',
            'observacoes' => 'nullable|string|max:1000',
            'viatura_id' => 'required|exists:viaturas,id',
        ]);

        $viatura = Viatura::find($validated['viatura_id']);

        // A viatura é a fonte do nº do motor e da cor. Um valor diferente do
        // registado é recusado: o termo não pode dizer uma coisa e o cadastro
        // outra, e a viatura nunca é reescrita em silêncio a partir daqui.
        $divergencias = [];
        foreach (['motor_numero' => ['motor_numero', 'O número do motor'], 'cor_viatura' => ['cor', 'A cor']] as $campo => [$coluna, $rotulo]) {
            $registado = trim((string) $viatura->{$coluna});
            $indicado = trim((string) ($validated[$campo] ?? ''));
            if ($registado !== '' && $indicado !== '' && mb_strtoupper($indicado) !== mb_strtoupper($registado)) {
                $divergencias[$campo] = "{$rotulo} indicado não coincide com o registado na viatura ({$registado}). Corrija-o no cadastro da viatura.";
            }
        }
        if ($divergencias !== []) {
            throw ValidationException::withMessages($divergencias);
        }

        // Se o número do motor ou cor foram fornecidos e a viatura não possuía, salvar na viatura
        if (! empty($validated['motor_numero']) && empty($viatura->motor_numero)) {
            $viatura->motor_numero = $validated['motor_numero'];
            $viatura->save();
        }
        if (! empty($validated['cor_viatura']) && empty($viatura->cor)) {
            $viatura->cor = $validated['cor_viatura'];
            $viatura->save();
        }

        $user = Auth::user();
        $responsavel = $user->departamento?->gabinete?->responsavel
            ?? $user->departamento?->gabinete?->superChefe
            ?? $user->departamento?->responsavel
            ?? $user;
        $nomeSignatario = $request->input('nome_signatario', $responsavel->name);

        // Objeto para a view PDF
        $termoBase = (object) [
            'tipo' => 'credencial',
            'tipo_credencial' => $validated['tipo_credencial'],
            'beneficiario_nome' => $validated['beneficiario_nome'],
            'beneficiario_documento' => $validated['beneficiario_documento'],
            'beneficiario_documento_emitido_em' => $validated['beneficiario_documento_emitido_em'],
            'beneficiario_documento_emitido_local' => $validated['beneficiario_documento_emitido_local'],
            'beneficiario_setor' => $validated['beneficiario_setor'] ?? null,
            'origem_viagem' => $validated['origem_viagem'] ?? null,
            'destino_viagem' => $validated['destino_viagem'] ?? null,
            'instituicao_vinculo' => $validated['instituicao_vinculo'] ?? ($validated['beneficiario_setor'] ?? null),
            'motor_numero' => $viatura->motor_numero ?: ($validated['motor_numero'] ?? null),
            'cor_viatura' => $viatura->cor ?: ($validated['cor_viatura'] ?? null),
            'nome_signatario' => $nomeSignatario,
            'cargo_signatario' => $request->input('cargo_signatario', 'O Secretário Geral'),
            'observacoes' => $validated['observacoes'] ?? null,
        ];

        // Gerar PDF e salvar registro dentro de transação
        $novoTermo = DB::transaction(function () use ($termoBase, $validated, $viatura) {
            $dadosInstituicao = \App\Models\DadosInstituicao::first() ?? new \App\Models\DadosInstituicao;

            $data = [
                'termo' => $termoBase,
                'viatura' => $viatura,
                'viaturas' => collect([$viatura]),
                'dadosInstituicao' => $dadosInstituicao,
            ];

            $pdf = PDF::loadView('termos.credencial', $data);
            $nomeArquivo = 'credencial_'.$validated['tipo_credencial'].'_'.now()->format('Ymd_His').'.pdf';
            $caminho = 'termos/'.$nomeArquivo;
            Storage::disk('public')->put($caminho, $pdf->output());

            return TermoEntrega::create([
                'tipo' => 'credencial',
                'tipo_credencial' => $validated['tipo_credencial'],
                'caminho_arquivo' => $caminho,
                'beneficiario_nome' => $validated['beneficiario_nome'],
                'beneficiario_documento' => $validated['beneficiario_documento'],
                'beneficiario_documento_emitido_em' => $validated['beneficiario_documento_emitido_em'],
                'beneficiario_documento_emitido_local' => $validated['beneficiario_documento_emitido_local'],
                'beneficiario_setor' => $validated['beneficiario_setor'] ?? null,
                'origem_viagem' => $validated['origem_viagem'] ?? null,
                'destino_viagem' => $validated['destino_viagem'] ?? null,
                'instituicao_vinculo' => $validated['instituicao_vinculo'] ?? null,
                'motor_numero' => $viatura->motor_numero ?: ($validated['motor_numero'] ?? null),
                'cor_viatura' => $viatura->cor ?: ($validated['cor_viatura'] ?? null),
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
            return response()->download($path, basename($path), ['X-Content-Type-Options' => 'nosniff']);
        }

        // Credenciais são PDFs gerados pelo sistema; força o tipo e impede sniffing
        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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
