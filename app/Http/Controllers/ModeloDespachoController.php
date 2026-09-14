<?php

namespace App\Http\Controllers;

use App\Models\ModeloDespacho;
use App\Services\DocumentoPermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Modelos de texto de despacho.
 *
 * Existiam no modelo de dados e no ecrã de detalhe, mas não havia forma de os
 * criar — zero na base — o que tornava a funcionalidade inerte, tal como
 * aconteceu com os prazos por espécie.
 *
 * Um modelo pode ser pessoal (do utilizador que o criou) ou institucional
 * (user_id nulo, visível a todos). Só os pessoais são editáveis pelo dono.
 */
class ModeloDespachoController extends Controller
{
    public function __construct(protected DocumentoPermissionService $permissionService) {}

    /**
     * Quem despacha é quem usa modelos de despacho: responsáveis de gabinete,
     * super chefes e administradores.
     */
    private function autorizar(): void
    {
        $user = Auth::user();

        $podeDespachar = $user && (
            $this->permissionService->isAdmin($user)
            || count($this->permissionService->getUserResponsibleGabinetes($user)) > 0
            || (method_exists($user, 'isSuperChefeGabinete') && $user->isSuperChefeGabinete())
        );

        if (! $podeDespachar) {
            abort(403, 'Os modelos de despacho estão reservados a quem despacha documentos.');
        }
    }

    private function meu(ModeloDespacho $modelo): void
    {
        if ((int) $modelo->user_id !== (int) Auth::id()) {
            abort(403, 'Só pode alterar os seus próprios modelos. Os modelos institucionais são geridos por um administrador.');
        }
    }

    public function index()
    {
        $this->autorizar();

        return view('modelos_despacho.index', [
            'meus' => ModeloDespacho::where('user_id', Auth::id())->orderBy('titulo')->get(),
            'institucionais' => ModeloDespacho::whereNull('user_id')->orderBy('titulo')->get(),
            'podeGerirInstitucionais' => $this->permissionService->isAdmin(Auth::user()),
        ]);
    }

    public function store(Request $request)
    {
        $this->autorizar();

        $validado = $request->validate($this->regras(), $this->mensagens());

        // Só um administrador cria modelos institucionais (sem dono).
        $institucional = $request->boolean('institucional')
            && $this->permissionService->isAdmin(Auth::user());

        ModeloDespacho::create([
            'titulo' => $validado['titulo'],
            'texto' => $validado['texto'],
            'user_id' => $institucional ? null : Auth::id(),
            'ativo' => true,
        ]);

        return redirect()->route('modelos-despacho.index')
            ->with('success', 'Modelo de despacho criado.');
    }

    public function update(Request $request, ModeloDespacho $modelos_despacho)
    {
        $this->autorizar();
        $this->meu($modelos_despacho);

        $validado = $request->validate($this->regras(), $this->mensagens());

        $modelos_despacho->update([
            'titulo' => $validado['titulo'],
            'texto' => $validado['texto'],
            'ativo' => $request->boolean('ativo'),
        ]);

        return redirect()->route('modelos-despacho.index')
            ->with('success', 'Modelo atualizado.');
    }

    public function destroy(ModeloDespacho $modelos_despacho)
    {
        $this->autorizar();
        $this->meu($modelos_despacho);

        $modelos_despacho->delete();

        return redirect()->route('modelos-despacho.index')
            ->with('success', 'Modelo eliminado.');
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function regras(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:120'],
            'texto' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mensagens(): array
    {
        return [
            'titulo.required' => 'Dê um nome ao modelo para o reconhecer na lista.',
            'texto.required' => 'Escreva o texto do despacho.',
        ];
    }
}
