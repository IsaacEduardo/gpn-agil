<?php

namespace App\Http\Controllers;

use App\Models\Viatura;
use App\Models\ViaturaFoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ViaturaFotoController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $viatura_id)
    {
        $validator = Validator::make($request->all(), [
            'arquivo' => 'required|file|mimes:jpeg,png,jpg,pdf|max:5120', // 5MB max
            'tipo' => 'required|in:foto,documento',
        ]);

        if ($validator->fails()) {
            return redirect()->route('viaturas.show', $viatura_id)
                ->withErrors($validator)
                ->withInput();
        }

        $viatura = Viatura::findOrFail($viatura_id);

        if ($request->hasFile('arquivo')) {
            $arquivo = $request->file('arquivo');
            $tipo = $request->input('tipo');
            $extensao = $arquivo->getClientOriginalExtension();

            // Define o caminho de armazenamento baseado no tipo
            $pasta = $tipo === 'foto' ? 'viaturas/fotos' : 'viaturas/documentos';
            $nomeArquivo = time().'_'.$viatura->identificacao.'.'.$extensao;

            // Armazena o arquivo
            $caminho = $arquivo->storeAs($pasta, $nomeArquivo, 'public');

            // Cria o registro no banco
            ViaturaFoto::create([
                'viatura_id' => $viatura_id,
                'caminho_arquivo' => $caminho,
                'tipo' => $tipo,
            ]);

            return redirect()->route('viaturas.show', $viatura_id)
                ->with('success', ($tipo === 'foto' ? 'Foto' : 'Documento').' adicionado com sucesso!');
        }

        return redirect()->route('viaturas.show', $viatura_id)
            ->with('error', 'Erro ao fazer upload do arquivo.');
    }

    /**
     * Display the specified resource.
     */
    public function show(ViaturaFoto $foto)
    {
        // Verificar se o arquivo existe
        if (! Storage::disk('public')->exists($foto->caminho_arquivo)) {
            abort(404);
        }

        // Se for um PDF, retorna o arquivo para download/visualização
        if (pathinfo($foto->caminho_arquivo, PATHINFO_EXTENSION) === 'pdf') {
            return response()->file(Storage::disk('public')->path($foto->caminho_arquivo));
        }

        // Se for uma imagem, retorna a imagem
        return response()->file(Storage::disk('public')->path($foto->caminho_arquivo));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ViaturaFoto $foto)
    {
        $viatura_id = $foto->viatura_id;

        // Excluir o arquivo físico
        if (Storage::disk('public')->exists($foto->caminho_arquivo)) {
            Storage::disk('public')->delete($foto->caminho_arquivo);
        }

        // Excluir o registro do banco
        $foto->delete();

        return redirect()->route('viaturas.show', $viatura_id)
            ->with('success', 'Arquivo excluído com sucesso!');
    }
}
