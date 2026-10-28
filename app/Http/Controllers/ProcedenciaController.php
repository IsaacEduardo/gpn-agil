<?php

namespace App\Http\Controllers;

use App\Models\Procedencia;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProcedenciaController extends Controller
{
    /** Máximo de resultados que o combobox pode pedir de uma vez. */
    private const LIMITE_MAXIMO = 50;

    /**
     * GET /api/procedencias
     * Retorna a lista de procedências ativas com suporte a busca.
     *
     * `limite` é opcional: o combobox do registo pede só as primeiras, em vez
     * de receber o catálogo inteiro no HTML (milhares de opções). Sem ele, a
     * resposta continua a ser a lista completa.
     */
    public function index(Request $request)
    {
        $search = trim($request->query('q') ?? $request->query('search') ?? '');
        $limite = (int) $request->query('limite', 0);

        $query = Procedencia::where('ativo', true);

        if (! empty($search)) {
            $query->where('nome', 'like', "%{$search}%");
        }

        if ($limite > 0) {
            $query->limit(min($limite, self::LIMITE_MAXIMO));
        }

        $procedencias = $query->orderBy('nome', 'asc')
            ->get(['id', 'nome']);

        return response()->json($procedencias);
    }

    /**
     * POST /api/procedencias
     * Cadastra uma nova procedência garantindo unicidade case-insensitive e limpeza de espaços.
     */
    public function store(Request $request)
    {
        $request->merge([
            'nome' => trim((string) $request->input('nome')),
        ]);

        $validated = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
        ], [
            'nome.required' => 'O nome da procedência é obrigatório.',
            'nome.max' => 'O nome da procedência não pode ter mais de 255 caracteres.',
        ]);

        $nomeLimpo = $validated['nome'];

        // Validação de duplicidade case-insensitive
        $jaExiste = Procedencia::whereRaw('LOWER(TRIM(nome)) = ?', [mb_strtolower($nomeLimpo)])->first();

        if ($jaExiste) {
            return response()->json([
                'message' => 'Procedência já cadastrada.',
                'errors' => [
                    'nome' => ['Esta procedência já está cadastrada no sistema.'],
                ],
                'data' => [
                    'id' => $jaExiste->id,
                    'nome' => $jaExiste->nome,
                ],
            ], 422);
        }

        $procedencia = Procedencia::create([
            'nome' => $nomeLimpo,
            'ativo' => true,
        ]);

        return response()->json([
            'id' => $procedencia->id,
            'nome' => $procedencia->nome,
        ], 201);
    }
}
