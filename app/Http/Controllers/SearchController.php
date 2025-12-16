<?php

namespace App\Http\Controllers;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\Empresa;
use App\Models\Requisicao;
use App\Models\Viatura;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    public function index(Request $request)
    {
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
                'icon' => 'fa-car'
            ];
        }

        // 2. Requisições (ID)
        if (is_numeric($query)) {
            $req = Requisicao::find($query);
            if ($req) {
                $results[] = [
                    'category' => 'Requisições',
                    'label' => "Requisição #{$req->id} - " . Str::limit($req->descricao ?? 'Sem descrição', 30),
                    'url' => $this->getRequisicaoUrl($req),
                    'icon' => 'fa-file-alt'
                ];
            }
        }

        // 3. Documentos de Entrada (Assunto, Protocolo, Procedência)
        $docs = DocumentoEntrada::where('assunto', 'like', "%{$query}%")
            ->orWhere('procedencia', 'like', "%{$query}%")
            ->orWhereHas('protocolo', function($q) use ($query) {
                $q->where('codigo', 'like', "%{$query}%");
            })
            ->limit(5)
            ->get();

        foreach ($docs as $doc) {
            $prot = $doc->protocolo ? $doc->protocolo->codigo : 'S/P';
            $results[] = [
                'category' => 'Documentos',
                'label' => "{$prot} - " . Str::limit($doc->assunto, 40),
                'url' => route('documentos-entradas.show', $doc->id),
                'icon' => 'fa-inbox'
            ];
        }

        // 4. Departamentos
        $deps = Departamento::where('nome', 'like', "%{$query}%")->limit(3)->get();
        foreach ($deps as $dep) {
            $results[] = [
                'category' => 'Departamentos',
                'label' => $dep->nome,
                'url' => route('departamentos.edit', $dep->id), // Assuming edit
                'icon' => 'fa-sitemap'
            ];
        }

         // 5. Empresas
         $empresas = Empresa::where('nome', 'like', "%{$query}%")->limit(3)->get();
         foreach ($empresas as $emp) {
             $results[] = [
                 'category' => 'Empresas',
                 'label' => $emp->nome,
                 'url' => route('empresas.edit', $emp->id), // Assuming edit
                 'icon' => 'fa-building'
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
