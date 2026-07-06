<?php

namespace App\Http\Controllers;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\Empresa;
use App\Models\Requisicao;
use App\Models\Viatura;
use App\Services\DocumentoEntradaService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    public function __construct(private DocumentoEntradaService $documentoService) {}

    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([]);
        }

        $query = trim($request->input('q'));
        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $results = [];

        // 1. Viaturas (Placa ou Modelo)
        $viaturas = Viatura::where('placa', 'like', "%{$query}%")
            ->orWhere('modelo', 'like', "%{$query}%")
            ->limit(3)
            ->get();

        foreach ($viaturas as $v) {
            $results[] = [
                'category' => 'Viaturas',
                'label' => "{$v->modelo} - {$v->placa}",
                'url' => route('viaturas.edit', $v->id), // Assuming edit or show
                'icon' => 'fa-car',
            ];
        }

        // 2. Requisições (ID, Observações, Produtos) — restritas à visibilidade do utilizador
        $reqsQuery = Requisicao::query()->visibleToUser($user);

        if (is_numeric($query)) {
            $reqsQuery->where('id', $query);
        } else {
            $reqsQuery->where(function ($sub) use ($query) {
                $sub->where('observacoes', 'like', "%{$query}%")
                    ->orWhereHas('produtos', function ($q) use ($query) {
                        $q->where('nome_produto', 'like', "%{$query}%")
                            ->orWhere('finalidade', 'like', "%{$query}%");
                    });
            });
        }

        $requisicoes = $reqsQuery->limit(5)->get();

        foreach ($requisicoes as $req) {
            $desc = $req->observacoes ?? 'Requisição sem observações';

            // Tenta pegar o nome do primeiro produto se houver
            if ($req->produtos->count() > 0) {
                $desc = $req->produtos->first()->nome_produto;
            }

            $results[] = [
                'category' => 'Requisições',
                'label' => "Requisição #{$req->id} - ".Str::limit($desc, 30),
                'url' => $this->getRequisicaoUrl($req),
                'icon' => 'fa-file-alt',
            ];
        }

        // 3. Documentos de Entrada (Assunto, Protocolo, Procedência, Conteúdo Anexo)
        //    Restritos à visibilidade do utilizador (departamento, gabinete ou histórico).
        $docsQuery = DocumentoEntrada::query()
            ->where(function ($sub) use ($query) {
                $sub->where('assunto', 'like', "%{$query}%")
                    ->orWhere('procedencia', 'like', "%{$query}%")
                    ->orWhereHas('protocolo', function ($q) use ($query) {
                        $q->where('codigo', 'like', "%{$query}%");
                    })
                    ->orWhereHas('anexos', function ($q) use ($query) {
                        $q->where('texto_extraido', 'like', "%{$query}%");
                    });
            });

        $this->documentoService->applyVisibilityScope($docsQuery, $user);

        $docs = $docsQuery->limit(5)->get();

        foreach ($docs as $doc) {
            $prot = $doc->protocolo ? $doc->protocolo->codigo : 'S/P';
            $results[] = [
                'category' => 'Documentos',
                'label' => "{$prot} - ".Str::limit($doc->assunto, 40),
                'url' => route('documentos-entradas.show', $doc->id),
                'icon' => 'fa-inbox',
            ];
        }

        // 4. Departamentos
        $deps = Departamento::where('nome', 'like', "%{$query}%")->limit(3)->get();
        foreach ($deps as $dep) {
            $results[] = [
                'category' => 'Departamentos',
                'label' => $dep->nome,
                'url' => route('departamentos.edit', $dep->id), // Assuming edit
                'icon' => 'fa-sitemap',
            ];
        }

        // 5. Empresas
        $empresas = Empresa::where('nome', 'like', "%{$query}%")->limit(3)->get();
        foreach ($empresas as $emp) {
            $results[] = [
                'category' => 'Empresas',
                'label' => $emp->nome,
                'url' => route('empresas.edit', $emp->id), // Assuming edit
                'icon' => 'fa-building',
            ];
        }

        return response()->json($results);
    }

    private function getRequisicaoUrl($req)
    {
        // Determine URL based on type
        // Assuming routes follow specific patterns
        switch ($req->tipo) {
            case 'produto': return route('requisicoes.produtos.edit', $req->id);
            case 'oficina': return route('requisicoes.oficina.edit', $req->id);
            case 'servico': return route('requisicoes.servico.edit', $req->id);
            case 'passagem': return route('requisicoes.passagem.edit', $req->id);
            default: return '#';
        }
    }
}
