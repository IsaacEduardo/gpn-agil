<?php

namespace App\Http\Controllers;

use App\Models\Viatura;
use App\Models\ViaturaFoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ViaturaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Viatura::class);
        $query = Viatura::query();

        // Filtro de busca por texto
        if ($request->has('search') && ! empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('identificacao', 'like', "%{$search}%")
                    ->orWhere('placa', 'like', "%{$search}%")
                    ->orWhere('modelo', 'like', "%{$search}%")
                    ->orWhere('marca', 'like', "%{$search}%");
            });
        }

        // Filtro por status operacional
        if ($request->has('status') && ! empty($request->status)) {
            $query->where('status_operacional', $request->status);
        }

        // Filtro por tipo
        if ($request->has('tipo') && ! empty($request->tipo)) {
            $query->where('tipo', $request->tipo);
        }

        // Filtro por ano
        if ($request->has('ano') && ! empty($request->ano)) {
            $query->where('ano', $request->ano);
        }

        // Ordenação
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');

        // Validar campo de ordenação para evitar SQL injection
        $allowedSortFields = ['created_at', 'placa', 'modelo', 'marca', 'ano', 'status_operacional', 'tipo'];
        if (! in_array($sortField, $allowedSortFields)) {
            $sortField = 'created_at';
        }

        $query->orderBy($sortField, $sortDirection);

        // Selecionar somente colunas necessárias para a listagem
        $query->select(['id', 'identificacao', 'placa', 'modelo', 'marca', 'ano', 'tipo', 'status_operacional']);

        $viaturas = $query->paginate(10)->withQueryString();

        // Obter anos únicos para o filtro
        $anos = Viatura::select('ano')->distinct()->orderBy('ano', 'desc')->pluck('ano');

        return view('viaturas.index', compact('viaturas', 'anos'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Viatura::class);

        return view('viaturas.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Viatura::class);
        // Gerar identificação automática se for "AUTO"
        if ($request->input('identificacao') === 'AUTO') {
            $request->merge(['identificacao' => 'V-'.time()]);
        }

        // Padronizar matrícula para maiúsculas
        if ($request->filled('placa')) {
            $request->merge(['placa' => strtoupper($request->input('placa'))]);
        }

        $validator = Validator::make($request->all(), [
            'identificacao' => 'required|string|max:50|unique:viaturas',
            'placa' => [
                'required',
                'string',
                'max:12',
                'unique:viaturas',
                'regex:/^(?:[A-Za-z]{2}-\d{2}-\d{2}(?:-[A-Za-z]{2})?|[A-Za-z]{3}-\d{2}-\d{2}(?:-[A-Za-z]{2})?)$/',
            ],
            'modelo' => 'required|string|max:100',
            'marca' => 'required|string|max:100',
            'ano' => 'required|integer|min:1900|max:'.(date('Y') + 1),
            'status_operacional' => 'required|in:Operacional,Em manutenção,Inoperante',
            'observacoes' => 'nullable|string',
            'fotos.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'documentos.*' => 'nullable|mimes:pdf,doc,docx|max:5120',
        ], [
            'placa.regex' => 'Formato inválido. Use LL-00-00, LL-00-00-LL, LLL-00-00 ou LLL-00-00-LL',
            'placa.max' => 'A matrícula deve ter no máximo 12 caracteres (ex.: LDA-00-00-XX)',
            'fotos.*.image' => 'Os arquivos devem ser imagens (jpeg, png, jpg, gif)',
            'fotos.*.max' => 'O tamanho máximo para cada foto é 2MB',
            'documentos.*.mimes' => 'Os documentos devem estar nos formatos PDF, DOC ou DOCX',
            'documentos.*.max' => 'O tamanho máximo para cada documento é 5MB',
        ]);

        if ($validator->fails()) {
            return redirect()->route('viaturas.create')
                ->withErrors($validator)
                ->withInput();
        }

        $viatura = Viatura::create($request->all());

        // Processa o upload de fotos
        if ($request->hasFile('fotos')) {
            foreach ($request->file('fotos') as $foto) {
                $nomeArquivo = time().'_'.uniqid().'.'.$foto->getClientOriginalExtension();
                $caminho = $foto->storeAs('viaturas/fotos', $nomeArquivo, 'public');

                ViaturaFoto::create([
                    'viatura_id' => $viatura->id,
                    'caminho_arquivo' => $caminho,
                    'tipo' => 'foto',
                ]);
            }
        }

        // Processa o upload de documentos
        if ($request->hasFile('documentos')) {
            foreach ($request->file('documentos') as $documento) {
                $nomeArquivo = time().'_'.uniqid().'.'.$documento->getClientOriginalExtension();
                $caminho = $documento->storeAs('viaturas/documentos', $nomeArquivo, 'public');

                ViaturaFoto::create([
                    'viatura_id' => $viatura->id,
                    'caminho_arquivo' => $caminho,
                    'tipo' => 'documento',
                ]);
            }
        }

        return redirect()->route('viaturas.show', $viatura->id)
            ->with('success', 'Viatura cadastrada com sucesso!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Viatura $viatura)
    {
        $this->authorize('view', $viatura);
        $viatura->load(['fotos:id,viatura_id,caminho_arquivo,tipo']);

        return view('viaturas.show', compact('viatura'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Viatura $viatura)
    {
        $this->authorize('update', $viatura);

        return view('viaturas.edit', compact('viatura'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Viatura $viatura)
    {
        $this->authorize('update', $viatura);
        // Padronizar matrícula para maiúsculas
        if ($request->filled('placa')) {
            $request->merge(['placa' => strtoupper($request->input('placa'))]);
        }
        $validator = Validator::make($request->all(), [
            'identificacao' => 'required|string|max:50|unique:viaturas,identificacao,'.$viatura->id,
            'placa' => [
                'required',
                'string',
                'max:12',
                'unique:viaturas,placa,'.$viatura->id,
                'regex:/^(?:[A-Za-z]{2}-\d{2}-\d{2}(?:-[A-Za-z]{2})?|[A-Za-z]{3}-\d{2}-\d{2}(?:-[A-Za-z]{2})?)$/',
            ],
            'modelo' => 'required|string|max:100',
            'marca' => 'required|string|max:100',
            'ano' => 'required|integer|min:1900|max:'.(date('Y') + 1),
            'status_operacional' => 'required|in:Operacional,Em manutenção,Inoperante',
            'observacoes' => 'nullable|string',
            'fotos.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'documentos.*' => 'nullable|mimes:pdf,doc,docx|max:5120',
            'remover_fotos.*' => 'nullable|exists:viatura_fotos,id',
        ], [
            'placa.regex' => 'Formato inválido. Use LL-00-00, LL-00-00-LL, LLL-00-00 ou LLL-00-00-LL',
            'placa.max' => 'A matrícula deve ter no máximo 12 caracteres (ex.: LDA-00-00-XX)',
            'fotos.*.image' => 'Os arquivos devem ser imagens (jpeg, png, jpg, gif)',
            'fotos.*.max' => 'O tamanho máximo para cada foto é 2MB',
            'documentos.*.mimes' => 'Os documentos devem estar nos formatos PDF, DOC ou DOCX',
            'documentos.*.max' => 'O tamanho máximo para cada documento é 5MB',
        ]);

        if ($validator->fails()) {
            return redirect()->route('viaturas.edit', $viatura->id)
                ->withErrors($validator)
                ->withInput();
        }

        // Atualiza apenas os campos validados (evita mass assignment de campos extra)
        $viatura->update($validator->validated());

        // Processa as fotos para remover
        if ($request->has('remover_fotos')) {
            foreach ($request->remover_fotos as $fotoId) {
                $foto = ViaturaFoto::find($fotoId);
                if ($foto && $foto->viatura_id == $viatura->id) {
                    // Remove o arquivo físico
                    if (Storage::disk('public')->exists($foto->caminho_arquivo)) {
                        Storage::disk('public')->delete($foto->caminho_arquivo);
                    }
                    // Remove o registro do banco
                    $foto->delete();
                }
            }
        }

        // Processa o upload de novas fotos
        if ($request->hasFile('fotos')) {
            foreach ($request->file('fotos') as $foto) {
                $nomeArquivo = time().'_'.uniqid().'.'.$foto->getClientOriginalExtension();
                $caminho = $foto->storeAs('viaturas/fotos', $nomeArquivo, 'public');

                ViaturaFoto::create([
                    'viatura_id' => $viatura->id,
                    'caminho_arquivo' => $caminho,
                    'tipo' => 'foto',
                ]);
            }
        }

        // Processa o upload de novos documentos
        if ($request->hasFile('documentos')) {
            foreach ($request->file('documentos') as $documento) {
                $nomeArquivo = time().'_'.uniqid().'.'.$documento->getClientOriginalExtension();
                $caminho = $documento->storeAs('viaturas/documentos', $nomeArquivo, 'public');

                ViaturaFoto::create([
                    'viatura_id' => $viatura->id,
                    'caminho_arquivo' => $caminho,
                    'tipo' => 'documento',
                ]);
            }
        }

        return redirect()->route('viaturas.show', $viatura->id)
            ->with('success', 'Viatura atualizada com sucesso!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Viatura $viatura)
    {
        $this->authorize('delete', $viatura);
        // Verificar se existem requisições de oficina associadas
        if ($viatura->requisicoes_oficina()->count() > 0) {
            return redirect()->route('viaturas.index')
                ->with('error', 'Não é possível excluir esta viatura pois existem requisições de oficina associadas.');
        }

        // Excluir fotos associadas
        foreach ($viatura->fotos as $foto) {
            if (Storage::disk('public')->exists($foto->caminho_arquivo)) {
                Storage::disk('public')->delete($foto->caminho_arquivo);
            }
            $foto->delete();
        }

        $viatura->delete();

        return redirect()->route('viaturas.index')
            ->with('success', 'Viatura excluída com sucesso!');
    }
}
