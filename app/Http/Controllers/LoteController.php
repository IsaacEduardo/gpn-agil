<?php

namespace App\Http\Controllers;

use App\Models\Lote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoteController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Lote::class, 'lote');
    }

    public function index(Request $request)
    {
        $query = Lote::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('codigo_lote', 'like', "%{$search}%")
                  ->orWhere('matricula_cartoraria', 'like', "%{$search}%")
                  ->orWhere('bairro_distrito', 'like', "%{$search}%")
                  ->orWhere('zona_setor', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('zoneamento')) {
            $query->where('zoneamento', $request->zoneamento);
        }

        $lotes = $query->orderBy('codigo_lote')->paginate(15);

        return view('lotes.index', compact('lotes'));
    }

    public function geoJson()
    {
        $this->authorize('viewAny', Lote::class);

        $lotes = Lote::whereNotNull('geojson_geometria')->get();

        $features = [];
        foreach ($lotes as $lote) {
            $geometry = json_decode($lote->geojson_geometria, true);
            if ($geometry) {
                $features[] = [
                    'type' => 'Feature',
                    'geometry' => $geometry,
                    'properties' => [
                        'id' => $lote->id,
                        'codigo_lote' => $lote->codigo_lote,
                        'status' => $lote->status,
                        'zoneamento' => $lote->zoneamento,
                        'area_m2' => number_format($lote->area_m2, 2, ',', '.') . ' m²',
                        'municipio' => $lote->municipio,
                        'bairro' => $lote->bairro_distrito,
                    ],
                ];
            }
        }

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    public function create()
    {
        return view('lotes.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'codigo_lote' => 'nullable|string|max:50|unique:lotes,codigo_lote',
            'matricula_cartoraria' => 'nullable|string|max:100',
            'inscricao_imobiliaria' => 'nullable|string|max:100',
            'municipio' => 'required|string|max:100',
            'comuna' => 'nullable|string|max:100',
            'bairro_distrito' => 'nullable|string|max:100',
            'zona_setor' => 'nullable|string|max:100',
            'area_m2' => 'required|numeric|min:0',
            'perimetro_m' => 'nullable|numeric|min:0',
            'zoneamento' => 'required|in:HABITACIONAL,COMERCIAL,INDUSTRIAL,AGRICOLA,EQUIPAMENTO_PUBLICO,MISTO',
            'status' => 'required|in:DISPONIVEL,RESERVADO,ATRIBUIDO,EM_LICITACAO,INDISPONIVEL',
            'latitude_centro' => 'nullable|numeric|between:-90,90',
            'longitude_centro' => 'nullable|numeric|between:-180,180',
            'geojson_geometria' => 'nullable|string',
            'observacoes' => 'nullable|string',
        ]);

        $validated['created_by_user_id'] = Auth::id();

        $lote = Lote::create($validated);

        return redirect()->route('lotes.show', $lote)
            ->with('success', 'Lote cadastrado com sucesso no inventário territorial.');
    }

    public function show(Lote $lote)
    {
        $lote->load(['solicitacoes.requerente', 'createdBy']);
        return view('lotes.show', compact('lote'));
    }

    public function edit(Lote $lote)
    {
        return view('lotes.edit', compact('lote'));
    }

    public function update(Request $request, Lote $lote)
    {
        $validated = $request->validate([
            'codigo_lote' => 'required|string|max:50|unique:lotes,codigo_lote,' . $lote->id,
            'matricula_cartoraria' => 'nullable|string|max:100',
            'inscricao_imobiliaria' => 'nullable|string|max:100',
            'municipio' => 'required|string|max:100',
            'comuna' => 'nullable|string|max:100',
            'bairro_distrito' => 'nullable|string|max:100',
            'zona_setor' => 'nullable|string|max:100',
            'area_m2' => 'required|numeric|min:0',
            'perimetro_m' => 'nullable|numeric|min:0',
            'zoneamento' => 'required|in:HABITACIONAL,COMERCIAL,INDUSTRIAL,AGRICOLA,EQUIPAMENTO_PUBLICO,MISTO',
            'status' => 'required|in:DISPONIVEL,RESERVADO,ATRIBUIDO,EM_LICITACAO,INDISPONIVEL',
            'latitude_centro' => 'nullable|numeric|between:-90,90',
            'longitude_centro' => 'nullable|numeric|between:-180,180',
            'geojson_geometria' => 'nullable|string',
            'observacoes' => 'nullable|string',
        ]);

        $lote->update($validated);

        return redirect()->route('lotes.show', $lote)
            ->with('success', 'Informações do lote atualizadas com sucesso.');
    }

    public function destroy(Lote $lote)
    {
        $lote->delete();
        return redirect()->route('lotes.index')
            ->with('success', 'Lote removido do inventário com sucesso.');
    }
}
