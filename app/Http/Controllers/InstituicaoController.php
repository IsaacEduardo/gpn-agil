<?php

namespace App\Http\Controllers;

use App\Models\DadosInstituicao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class InstituicaoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    protected function ensureAdmin()
    {
        $user = Auth::user();
        $isAdmin = $user && $user->role && $user->role->name === 'admin';

        if (! $isAdmin) {
            abort(403, 'Acesso restrito apenas a administradores.');
        }
    }

    /**
     * Exibe o formulário de edição dos dados da instituição.
     */
    public function edit()
    {
        $this->ensureAdmin();

        $dados = DadosInstituicao::first() ?? new DadosInstituicao;

        return view('instituicao.edit', compact('dados'));
    }

    /**
     * Atualiza os dados da instituição.
     */
    public function update(Request $request)
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'nome_oficial' => ['required', 'string', 'max:255'],
            'sigla' => ['required', 'string', 'max:10'],
            'cidade' => ['required', 'string', 'max:50'],
            'nif' => ['nullable', 'string', 'max:20'],
            'telefone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'endereco' => ['nullable', 'string'],
            'cabecalho_linha1' => ['nullable', 'string', 'max:255'],
            'cabecalho_linha2' => ['nullable', 'string', 'max:255'],
            'cabecalho_linha3' => ['nullable', 'string', 'max:255'],
            'rodape_texto' => ['nullable', 'string'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,svg', 'max:2048'],
            'rodape_img' => ['nullable', 'image', 'mimes:jpeg,png,jpg,svg', 'max:2048'],
        ]);

        $dados = DadosInstituicao::first() ?? new DadosInstituicao;

        $dados->nome_oficial = $data['nome_oficial'];
        $dados->sigla = $data['sigla'];
        $dados->cidade = $data['cidade'];
        $dados->nif = $data['nif'] ?? null;
        $dados->telefone = $data['telefone'] ?? null;
        $dados->email = $data['email'] ?? null;
        $dados->endereco = $data['endereco'] ?? null;
        $dados->cabecalho_linha1 = $data['cabecalho_linha1'] ?? null;
        $dados->cabecalho_linha2 = $data['cabecalho_linha2'] ?? null;
        $dados->cabecalho_linha3 = $data['cabecalho_linha3'] ?? null;
        $dados->rodape_texto = $data['rodape_texto'] ?? null;

        // Tratar upload do logótipo
        if ($request->hasFile('logo')) {
            // Remover imagem anterior se existir
            if ($dados->logo_path) {
                Storage::disk('public')->delete($dados->logo_path);
            }
            $path = $request->file('logo')->store('logos', 'public');
            $dados->logo_path = $path;
        }

        // Tratar upload do rodapé
        if ($request->hasFile('rodape_img')) {
            // Remover imagem anterior se existir
            if ($dados->rodape_img_path) {
                Storage::disk('public')->delete($dados->rodape_img_path);
            }
            $path = $request->file('rodape_img')->store('rodapes', 'public');
            $dados->rodape_img_path = $path;
        }

        $dados->save();

        \Illuminate\Support\Facades\Cache::forget('dados_instituicao_global');
        try {
            \Illuminate\Support\Facades\Artisan::call('view:clear');
        } catch (\Throwable $e) {}

        return redirect()->route('admin.instituicao.edit')->with('success', 'Configurações da instituição salvas com sucesso.');
    }
}
