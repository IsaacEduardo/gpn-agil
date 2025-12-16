<?php

namespace App\Services;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoProtocolo;
use App\Models\Tag;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use App\Models\User;
use Illuminate\Http\Request;

class DocumentoEntradaService
{
    public function getFilteredDocuments(Request $request, ?User $user)
    {
        $query = DocumentoEntrada::select([
            'id',
            'numero_sequencial',
            'ano_referencia',
            'data_entrada',
            'classificacao_especie',
            'classificacao_ref_numero',
            'procedencia',
            'assunto',
            'saida_gabinete_data',
            'encaminhamento_orgao',
            'encaminhamento_oficio_numero',
            'encaminhamento_data',
            'departamento_id',
            'status',
            'visto_departamento_status',
            'visto_gabinete_status',
            'arquivo_caminho',
        ])->with([
            'departamento:id,nome',
            'ultimoEncaminhamento',
            'ultimoEncaminhamento.origemDepartamento:id,nome',
            'ultimoEncaminhamento.destinoDepartamento:id,nome',
        ]);

        // Exclude archived documents by default
        if (!$request->has('incluir_arquivados') || !$request->boolean('incluir_arquivados')) {
            $query->where('arquivado', false);
        }

        // Authorization Scopes
        if ($user) {
            $isAdmin = $user->role && $user->role->name === 'admin';
            if (!$isAdmin) {
                $actorDeps = (method_exists($user, 'departamentos') && $user->departamentos)
                    ? $user->departamentos->pluck('id')->all() : [];
                if (!count($actorDeps) && $user->departamento_id) {
                    $actorDeps = [$user->departamento_id];
                }
                
                if (count($actorDeps)) {
                    $query->where(function ($q) use ($actorDeps) {
                        $q->whereIn('departamento_id', $actorDeps)
                            ->orWhereExists(function ($sub) use ($actorDeps) {
                                $sub->selectRaw(1)
                                    ->from('documento_encaminhamentos as de')
                                    ->whereColumn('de.documento_entrada_id', 'documentos_entradas.id')
                                    ->where(function ($subQ) use ($actorDeps) {
                                        $subQ->whereIn('de.destino_departamento_id', $actorDeps)
                                            ->orWhereIn('de.origem_departamento_id', $actorDeps);
                                    });
                            });
                    });
                }
            }
        }

        // Standard Filters
        if ($request->filled('search')) {
            $s = trim($request->input('search'));
            $query->where(function ($q) use ($s) {
                $q->where('assunto', 'like', "%$s%")
                    ->orWhere('procedencia', 'like', "%$s%")
                    ->orWhere('classificacao_especie', 'like', "%$s%")
                    ->orWhere('classificacao_ref_numero', 'like', "%$s%")
                    ->orWhereHas('tags', function ($t) use ($s) {
                        $t->where('nome', 'like', "%$s%");
                    })
                    ->orWhereHas('anexos', function ($a) use ($s) {
                         $a->where('texto_extraido', 'like', "%$s%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('departamento_id')) {
            $query->where('departamento_id', (int) $request->input('departamento_id'));
        }
        if ($request->filled('data_de')) {
            $query->whereDate('data_entrada', '>=', $request->input('data_de'));
        }
        if ($request->filled('data_ate')) {
            $query->whereDate('data_entrada', '<=', $request->input('data_ate'));
        }
        if ($request->filled('ano')) {
            $query->where('ano_referencia', (int) $request->input('ano'));
        }

        // Special Views ("Meus")
        if ($user && $meus = $request->input('meus')) {
            $deps = (method_exists($user, 'departamentos') && $user->departamentos) ? $user->departamentos->pluck('id')->all() : [];
            if (! count($deps) && $user->departamento_id) {
                $deps = [$user->departamento_id];
            }
            $actorGabIds = \App\Models\Gabinete::where('responsavel_id', $user->id)->pluck('id')->all();

            switch ($meus) {
                case 'pendentes_recebimento':
                    if (count($deps)) {
                        $query->whereExists(function ($sub) use ($deps) {
                            $sub->selectRaw(1)
                                ->from('documento_encaminhamentos as de')
                                ->whereColumn('de.documento_entrada_id', 'documentos_entradas.id')
                                ->whereNull('de.recebido_em')
                                ->whereIn('de.destino_departamento_id', $deps);
                        });
                    }
                    break;
                case 'visto_pendente':
                    if (count($deps)) {
                        $query->whereNull('visto_departamento_status')
                            ->where('status', 'recebido')
                            ->whereIn('departamento_id', $deps);
                    }
                    break;
                case 'visto_aprovado':
                    if (count($deps)) {
                        $query->where('visto_departamento_status', 'aprovado')->whereIn('departamento_id', $deps);
                    }
                    break;
                case 'visto_rejeitado':
                    if (count($deps)) {
                        $query->where('visto_departamento_status', 'rejeitado')->whereIn('departamento_id', $deps);
                    }
                    break;
                case 'visto_gabinete_pendente':
                    if (count($actorGabIds)) {
                        $query->whereNull('visto_gabinete_status')
                            ->whereHas('departamento', function ($q) use ($actorGabIds) {
                                $q->whereIn('gabinete_id', $actorGabIds);
                            });
                    }
                    break;
                case 'visto_gabinete_aprovado':
                    if (count($actorGabIds)) {
                        $query->where('visto_gabinete_status', 'aprovado')
                            ->whereHas('departamento', function ($q) use ($actorGabIds) {
                                $q->whereIn('gabinete_id', $actorGabIds);
                            });
                    }
                    break;
                case 'visto_gabinete_rejeitado':
                    if (count($actorGabIds)) {
                        $query->where('visto_gabinete_status', 'rejeitado')
                            ->whereHas('departamento', function ($q) use ($actorGabIds) {
                                $q->whereIn('gabinete_id', $actorGabIds);
                            });
                    }
                    break;
                case 'tarefas_responsavel_pendente':
                    $query->whereExists(function ($sub) use ($user) {
                        $sub->selectRaw(1)
                            ->from('documento_tarefas as dt')
                            ->whereColumn('dt.documento_entrada_id', 'documentos_entradas.id')
                            ->where('dt.responsavel_user_id', $user->id)
                            ->where('dt.status', 'pendente');
                    });
                    break;
            }
        }

        // Sort
        $sort = $request->input('sort', 'data_entrada');
        $dir = $request->input('direction', 'desc');
        $allowedSort = ['data_entrada', 'numero_sequencial', 'ano_referencia'];
        if (!in_array($sort, $allowedSort)) $sort = 'data_entrada';
        if (!in_array($dir, ['asc', 'desc'])) $dir = 'desc';
        $query->orderBy($sort, $dir);

        // Paginate
        $perPage = (int) $request->input('per_page', 10);
        if ($perPage < 5) $perPage = 5;
        if ($perPage > 100) $perPage = 100;

        return $query->paginate($perPage)->appends($request->query());
    }

    public function createDocument(array $data, $mainFile = null, $attachments = [])
    {
        for ($attempts = 1; $attempts <= 3; $attempts++) {
            try {
                return DB::transaction(function () use ($data, $mainFile, $attachments) {
                    return $this->processCreation($data, $mainFile, $attachments);
                });
            } catch (QueryException $e) {
                $msg = strtolower($e->getMessage());
                $isDup = str_contains($msg, 'duplicate') || (int) $e->getCode() === 23000;
                if ($isDup && $attempts < 3) {
                    continue;
                }
                throw $e;
            }
        }
    }

    protected function processCreation(array $data, $mainFile, $attachments)
    {
        $ano = (int) date('Y');
        $lastSeq = (int) (DocumentoEntrada::where('ano_referencia', $ano)->max('numero_sequencial') ?? 0);
        $seq = $lastSeq + 1;

        $doc = DocumentoEntrada::create([
            'numero_sequencial' => $seq,
            'ano_referencia' => $ano,
            'data_entrada' => now(),
            'classificacao_especie' => $data['classificacao_especie'] ?? null,
            'classificacao_ref_numero' => $data['classificacao_ref_numero'] ?? null,
            'data_documento' => $data['data_documento'] ?? null,
            'procedencia' => $data['procedencia'] ?? null,
            'assunto' => $data['assunto'],
            'observacoes' => $data['observacoes'] ?? null,
            'saida_gabinete_data' => $data['saida_gabinete_data'] ?? null,
            'encaminhamento_orgao' => $data['encaminhamento_orgao'] ?? null,
            'encaminhamento_oficio_numero' => $data['encaminhamento_oficio_numero'] ?? null,
            'encaminhamento_data' => null,
            'departamento_id' => $data['departamento_id'],
            'user_id' => Auth::id(),
            'status' => 'registrado',
            'arquivo_caminho' => null,
        ]);

        // File Path Components
        $dep = Departamento::with('gabinete')->find((int) $doc->departamento_id);
        $gab = optional($dep)->gabinete;
        $depSlug = Str::slug(optional($dep)->nome ?? 'sem-departamento');
        $gabSlug = Str::slug(optional($gab)->sigla ?? (optional($gab)->nome ?? 'sem-gabinete'));
        $docsDisk = config('filesystems.docs_disk', 'public');

        // Main File
        if ($mainFile) {
            $base = 'documentos_entradas/'.$ano.'/'.$gabSlug.'/'.$depSlug.'/'.sprintf('%03d', $seq);
            $storedPath = $mainFile->store($base, $docsDisk);
            $doc->arquivo_caminho = $storedPath;
            $doc->save();
        }

        // Protocol Generation
        $codigoProt = sprintf('PRT-%d-%03d-%s', $ano, $seq, strtoupper(Str::random(6)));
        $urlConsulta = route('documentos-entradas.protocolo', ['documento' => $doc->id]);
        DocumentoProtocolo::create([
            'documento_entrada_id' => $doc->id,
            'codigo' => $codigoProt,
            'url_consulta' => $urlConsulta,
            'gerado_em' => now(),
        ]);

        // Tags
        if (!empty($data['tags'])) {
            $tagNames = array_filter(array_map('trim', explode(',', $data['tags'])));
            $tagIds = [];
            foreach ($tagNames as $tagName) {
                $tag = Tag::firstOrCreate(
                    ['slug' => Str::slug($tagName)],
                    ['nome' => $tagName]
                );
                $tagIds[] = $tag->id;
            }
            $doc->tags()->sync($tagIds);
        }

        // Attachments
        if (!empty($attachments)) {
            $base = 'documentos_entradas/'.$ano.'/'.$gabSlug.'/'.$depSlug.'/'.sprintf('%03d', $seq).'/anexos';
            $ordem = 0;
            foreach ($attachments as $file) {
                $ordem++;
                $storedPath = $file->store($base, $docsDisk);
                $anexo = $doc->anexos()->create([
                    'nome_original' => $file->getClientOriginalName(),
                    'caminho_arquivo' => $storedPath,
                    'mime_type' => $file->getMimeType(),
                    'tamanho_bytes' => $file->getSize(),
                    'descricao' => null,
                    'ordem' => $ordem,
                    'user_id' => Auth::id(),
                ]);
                
                // Dispatch OCR Job
                \App\Jobs\ProcessarOcrAnexo::dispatch($anexo->id);

                // Fallback for main file
                if (! $doc->arquivo_caminho && $ordem === 1) {
                    $doc->arquivo_caminho = $storedPath;
                    $doc->save();
                }
            }
        }

        return $doc;
    }
}
