<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoEspecie;
use App\Services\DocumentoPermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Gestão das espécies de documento e dos respetivos prazos de tratamento.
 *
 * O prazo por espécie comanda o SLA dos documentos de entrada: sem este ecrã só
 * seria possível defini-lo por SQL ou por migration.
 */
class DocumentoEspecieController extends Controller
{
    public function __construct(protected DocumentoPermissionService $permissionService) {}

    /**
     * Definir prazos é política institucional: fica reservado a administradores.
     */
    private function autorizar(): void
    {
        $user = Auth::user();

        if (! $user || ! $this->permissionService->isAdmin($user)) {
            abort(403, 'Acesso restrito a administradores.');
        }
    }

    public function index()
    {
        $this->autorizar();

        $especies = DocumentoEspecie::orderByRaw('ordem IS NULL, ordem')
            ->orderBy('nome')
            ->get();

        $emUso = $this->contagemDeUso($especies->pluck('nome')->all());

        return view('admin.documento_especies.index', [
            'especies' => $especies,
            'emUso' => $emUso,
            'prazoGlobal' => (int) config('documentos.prazo_tratamento_dias', 5),
            'fracaoAviso' => (float) config('documentos.fracao_aviso_prazo', 0.4),
        ]);
    }

    public function store(Request $request)
    {
        $this->autorizar();

        $request->merge(['nome' => trim((string) $request->input('nome'))]);

        $validado = $request->validate($this->regras(), $this->mensagens());

        DocumentoEspecie::create([
            'nome' => $validado['nome'],
            'ativo' => $request->boolean('ativo', true),
            'ordem' => $validado['ordem'] ?? null,
            'prazo_tratamento_dias' => $this->prazoOuNulo($validado),
        ]);

        return redirect()->route('admin.documento-especies.index')
            ->with('success', 'Espécie criada com sucesso.');
    }

    public function update(Request $request, DocumentoEspecie $documento_especie)
    {
        $this->autorizar();

        $request->merge(['nome' => trim((string) $request->input('nome'))]);

        $validado = $request->validate($this->regras($documento_especie), $this->mensagens());

        $documento_especie->update([
            'nome' => $validado['nome'],
            'ativo' => $request->boolean('ativo'),
            'ordem' => $validado['ordem'] ?? null,
            'prazo_tratamento_dias' => $this->prazoOuNulo($validado),
        ]);

        return redirect()->route('admin.documento-especies.index')
            ->with('success', 'Espécie "'.$documento_especie->nome.'" atualizada.');
    }

    public function destroy(DocumentoEspecie $documento_especie)
    {
        $this->autorizar();

        $uso = $this->contagemDeUso([$documento_especie->nome])[$documento_especie->nome] ?? 0;

        if ($uso > 0) {
            return redirect()->route('admin.documento-especies.index')
                ->with('error', 'A espécie "'.$documento_especie->nome.'" classifica '.$uso
                    .' documento(s) e não pode ser eliminada. Desative-a para deixar de a oferecer em novos registos.');
        }

        $documento_especie->delete();

        return redirect()->route('admin.documento-especies.index')
            ->with('success', 'Espécie eliminada.');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function regras(?DocumentoEspecie $existente = null): array
    {
        return [
            'nome' => [
                'required', 'string', 'max:100',
                // Unicidade insensivel a maiusculas/minusculas e a espacos, com
                // o mesmo resultado em qualquer motor: Rule::unique depende do
                // collation (MySQL apanharia 'parecer' vs 'Parecer', sqlite nao).
                function (string $atributo, $valor, $falhar) use ($existente) {
                    $duplicada = DocumentoEspecie::query()
                        ->whereRaw('LOWER(TRIM(nome)) = ?', [mb_strtolower(trim((string) $valor))])
                        ->when($existente, fn ($q) => $q->whereKeyNot($existente->getKey()))
                        ->exists();

                    if ($duplicada) {
                        $falhar('Já existe uma espécie com esse nome.');
                    }
                },
            ],
            'ordem' => ['nullable', 'integer', 'min:0', 'max:999'],
            // Em branco significa "usar o prazo global", não zero.
            'prazo_tratamento_dias' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mensagens(): array
    {
        return [
            'nome.required' => 'Indique o nome da espécie.',
            'prazo_tratamento_dias.min' => 'O prazo tem de ser de pelo menos 1 dia. Deixe em branco para usar o prazo global.',
            'prazo_tratamento_dias.max' => 'O prazo não pode exceder 365 dias.',
        ];
    }

    private function prazoOuNulo(array $validado): ?int
    {
        $prazo = $validado['prazo_tratamento_dias'] ?? null;

        return ($prazo === null || $prazo === '') ? null : (int) $prazo;
    }

    /**
     * Quantos documentos cada espécie classifica. A espécie é guardada pelo
     * nome, não por chave estrangeira, daí a contagem por nome.
     *
     * @param  string[]  $nomes
     * @return array<string, int>
     */
    private function contagemDeUso(array $nomes): array
    {
        if (empty($nomes)) {
            return [];
        }

        $entradas = DocumentoEntrada::query()
            ->whereIn('classificacao_especie', $nomes)
            ->selectRaw('classificacao_especie AS nome, COUNT(*) AS total')
            ->groupBy('classificacao_especie')
            ->pluck('total', 'nome');

        $uso = [];
        foreach ($nomes as $nome) {
            $uso[$nome] = (int) ($entradas[$nome] ?? 0);
        }

        return $uso;
    }
}
