<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Departamento;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Services\DocumentoPermissionService;
use App\Services\NumeracaoDocumentoService;
use App\Support\SeriesNumeracao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ecrã "Numeração": séries de numeração por ano e último número emitido fora do sistema.
 *
 * Permite arrancar a meio do ano continuando a numeração em papel (ex.: o último ofício
 * foi o 584 → o próximo será 585) e preparar as séries do ano seguinte, que recomeçam em 1.
 */
class NumeracaoController extends Controller
{
    public function __construct(
        protected DocumentoPermissionService $permissionService,
        protected NumeracaoDocumentoService $numeracao,
    ) {}

    private function autorizar(): void
    {
        $user = Auth::user();

        if (! $user || ! $this->permissionService->isAdmin($user)) {
            abort(403, 'Acesso restrito a administradores.');
        }
    }

    /** Anos configuráveis: o corrente e o seguinte (preparar a viragem do ano). */
    private function anosPermitidos(): array
    {
        $ano = NumeracaoDocumentoService::anoCorrente();

        return [$ano, $ano + 1];
    }

    public function index(Request $request)
    {
        $this->autorizar();

        $anos = $this->anosPermitidos();
        $ano = in_array((int) $request->input('ano'), $anos, true) ? (int) $request->input('ano') : $anos[0];

        $series = DB::table('sequencias_documentos')->where('ano', $ano)->orderBy('rotulo')->orderBy('chave')->get()
            ->map(function ($linha) use ($ano) {
                $serie = SeriesNumeracao::daChave($linha->chave, $linha->rotulo);

                return (object) [
                    'id' => $linha->id,
                    'chave' => $linha->chave,
                    'rotulo' => $serie->rotulo,
                    'ultimo_numero' => (int) $linha->ultimo_numero,
                    'maior_emitido' => $serie->maiorEmitido($ano),
                    'proxima' => $serie->formatar((int) $linha->ultimo_numero + 1, $ano),
                    'atualizado_em' => $linha->updated_at,
                ];
            });

        return view('admin.numeracao.index', [
            'ano' => $ano,
            'anos' => $anos,
            'series' => $series,
            'gabinetes' => Gabinete::with(['departamentos' => fn ($q) => $q->orderBy('nome')])->orderBy('nome')->get(),
            'especies' => DocumentoEspecie::where('ativo', true)->orderBy('nome')->get(),
        ]);
    }

    /** Pré-visualização: "O próximo documento será …" para um valor indicado. */
    public function previa(Request $request): JsonResponse
    {
        $this->autorizar();

        $dados = $request->validate([
            'ano' => 'required|integer',
            'ultimo_numero' => 'required|integer|min:0|max:9999999',
            'chave' => 'nullable|string',
            'tipo' => 'nullable|in:entradas,internos',
            'emissor' => 'nullable|string',
            'documento_especie_id' => 'nullable|integer',
        ]);

        $serie = $this->serieDoPedido($request);

        return response()->json([
            'serie' => $serie->rotulo,
            'proxima' => $serie->formatar((int) $dados['ultimo_numero'] + 1, (int) $dados['ano']),
            'minimo' => $serie->maiorEmitido((int) $dados['ano']),
        ]);
    }

    /** Define (cria, se preciso) a série do ano com o último número emitido fora do sistema. */
    public function definir(Request $request)
    {
        $this->autorizar();

        $dados = $request->validate([
            'ano' => 'required|integer|in:'.implode(',', $this->anosPermitidos()),
            'ultimo_numero' => 'required|integer|min:0|max:9999999',
            'motivo' => 'required|string|min:5|max:500',
            'chave' => 'nullable|string|max:120',
            'tipo' => 'nullable|required_without:chave|in:entradas,internos',
            'emissor' => 'nullable|required_if:tipo,internos|string',
            'documento_especie_id' => 'nullable|required_if:tipo,internos|exists:documento_especies,id',
        ], [
            'motivo.required' => 'Indique o motivo (ex.: "Último ofício em papel: nº 584, de 30/06").',
        ]);

        $serie = $this->serieDoPedido($request);
        $ano = (int) $dados['ano'];
        $valores = $this->numeracao->definirUltimoNumero($serie, $ano, (int) $dados['ultimo_numero']);

        $linha = DB::table('sequencias_documentos')->where('chave', $serie->chave)->where('ano', $ano)->first();

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'numeracao.definir',
            'auditable_type' => 'sequencias_documentos',
            'auditable_id' => $linha->id,
            'old_values' => ['serie' => $serie->chave, 'ano' => $ano, 'ultimo_numero' => $valores['anterior']],
            'new_values' => ['serie' => $serie->chave, 'ano' => $ano, 'ultimo_numero' => $valores['novo']],
            'motivo' => $dados['motivo'],
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return redirect()->route('admin.numeracao.index', ['ano' => $ano])
            ->with('success', "{$serie->rotulo} ({$ano}): o próximo documento será ".$serie->formatar($valores['novo'] + 1, $ano).'.');
    }

    /**
     * Série a partir do pedido: por chave (série já listada) ou por tipo + emissor + espécie.
     * Para documentos internos usa-se a mesma regra da numeração real (paraDocumento),
     * pelo que a série configurada é exactamente a que os documentos vão usar.
     */
    private function serieDoPedido(Request $request): SeriesNumeracao
    {
        if ($chave = $request->input('chave')) {
            $rotulo = DB::table('sequencias_documentos')->where('chave', $chave)->value('rotulo');

            return SeriesNumeracao::daChave($chave, $rotulo);
        }

        if ($request->input('tipo') === 'entradas') {
            return SeriesNumeracao::entradas();
        }

        $especie = DocumentoEspecie::find($request->input('documento_especie_id'));
        if (! $especie || ! preg_match('/^(dep|gab):(\d+)$/', (string) $request->input('emissor'), $m)) {
            throw ValidationException::withMessages(['emissor' => 'Escolha o emissor e a espécie.']);
        }

        [$departamento, $gabinete] = $m[1] === 'dep'
            ? [($d = Departamento::with('gabinete')->find($m[2])), $d?->gabinete]
            : [null, Gabinete::find($m[2])];

        if (! $gabinete) {
            throw ValidationException::withMessages(['emissor' => 'Emissor inválido.']);
        }

        // Documento fictício (não gravado), só para determinar a série.
        $doc = new DocumentoInterno;
        $doc->departamento_id = $departamento?->id;
        $doc->gabinete_id = $gabinete->id;
        $doc->setRelation('departamento', $departamento);
        $doc->setRelation('gabinete', $gabinete);
        $doc->setRelation('especie', $especie);

        return SeriesNumeracao::paraDocumento($doc);
    }
}
