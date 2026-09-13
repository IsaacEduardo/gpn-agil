<?php

namespace App\Http\Controllers;

use App\Exports\DocumentoEntradasExport;
use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoProtocolo;
use App\Services\DocumentoEntradaService;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Protocolo de receção e exportações (PDF/Excel) de documentos de entrada.
 * Extraído de DocumentoEntradaController.
 */
class DocumentoEntradaProtocoloController extends Controller
{
    public function __construct(protected DocumentoEntradaService $documentoService) {}

    public function protocolo(DocumentoEntrada $documento)
    {
        $this->authorize('view', $documento);

        $documento->load(['departamento', 'usuario', 'protocolo']);

        $this->ensureProtocolo($documento);

        $consultaUrl = $documento->protocolo->url_consulta ?? route('documentos-entradas.protocolo', $documento);
        $protocolo = $documento->protocolo;

        return view('documentos_entradas.protocolo', compact('documento', 'protocolo', 'consultaUrl'));
    }

    public function protocoloEtiqueta(DocumentoEntrada $documento, Request $request)
    {
        $this->authorize('view', $documento);

        $documento->load(['departamento.gabinete', 'usuario', 'protocolo']);

        $this->ensureProtocolo($documento);

        $consultaUrl = $documento->protocolo->url_consulta ?? route('documentos-entradas.protocolo', $documento);
        $protocolo = $documento->protocolo;

        $autoPrint = $request->boolean('auto_print', false);

        // QR Code SVG inline alta definição
        try {
            $qrCodeSvg = (string) QrCode::size(90)->margin(0)->generate($consultaUrl);
        } catch (\Exception $e) {
            $qrCodeSvg = '';
        }

        return view('documentos_entradas.protocolo_etiqueta', compact(
            'documento',
            'protocolo',
            'consultaUrl',
            'qrCodeSvg',
            'autoPrint'
        ));
    }

    public function protocoloPdf(DocumentoEntrada $documento)
    {
        $this->authorize('view', $documento);

        $documento->load(['departamento', 'usuario', 'protocolo']);

        $this->ensureProtocolo($documento);

        $consultaUrl = $documento->protocolo->url_consulta ?? route('documentos-entradas.protocolo', $documento);
        $protocolo = $documento->protocolo;

        // Create temporary QR Code SVG file for robust DomPDF rendering
        $tempQrCodePath = storage_path('app/temp_qr_'.$documento->id.'_'.Str::random(4).'.svg');
        try {
            $qrCodeSvg = QrCode::size(120)->generate($consultaUrl);
            file_put_contents($tempQrCodePath, (string) $qrCodeSvg);
        } catch (\Exception $e) {
            $tempQrCodePath = null;
        }

        $options = new Options;
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('chroot', base_path()); // Allow local file access for insignia and QR Code
        $dompdf = new Dompdf($options);

        $html = view('documentos_entradas.protocolo_pdf', compact('documento', 'protocolo', 'consultaUrl', 'tempQrCodePath'))->render();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A5', 'landscape');
        $dompdf->render();

        // Cleanup temporary QR Code file
        if ($tempQrCodePath && file_exists($tempQrCodePath)) {
            @unlink($tempQrCodePath);
        }

        $filename = sprintf('protocolo_%03d_%d.pdf', $documento->numero_sequencial, $documento->ano_referencia);

        return response($dompdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$filename.'"');
    }

    public function marcarImpresso(DocumentoEntrada $documento, Request $request)
    {
        // 'view' e não 'update': marcar o recibo como impresso é o carimbo de uma
        // impressão legítima, não uma alteração do documento. Quem pode ver o
        // protocolo pode imprimi-lo — incluindo reimprimir depois do despacho.
        $this->authorize('view', $documento);

        $documento->load('protocolo');
        if (! $documento->protocolo) {
            return response()->json(['message' => 'Protocolo não encontrado'], 404);
        }

        $documento->protocolo->impresso_em = Carbon::now();
        $documento->protocolo->save();

        return response()->json([
            'message' => 'Protocolo marcado como impresso',
            'impresso_em' => $documento->protocolo->impresso_em,
        ]);
    }

    public function exportPDF(Request $request)
    {
        $query = $this->documentoService->getFilteredDocumentsQuery($request, Auth::user());
        $query->with([
            'departamento:id,nome',
            'ultimoEncaminhamento',
            'ultimoEncaminhamento.origemDepartamento:id,nome',
            'ultimoEncaminhamento.destinoDepartamento:id,nome',
        ]);

        $documentos = $this->colherAteAoLimite($query);
        if ($documentos === null) {
            return $this->recusarExportacao();
        }

        $filtersSummary = [];
        if ($request->filled('departamento_id')) {
            $depName = optional(Departamento::find($request->input('departamento_id')))->nome;
            if ($depName) {
                $filtersSummary['Departamento'] = $depName;
            }
        }

        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);
        $html = view('documentos_entradas.pdf', compact('documentos', 'filtersSummary'))->render();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->stream('relatorio-documentos-entradas-'.date('Y-m-d').'.pdf');
    }

    public function exportExcel(Request $request)
    {
        $query = $this->documentoService->getFilteredDocumentsQuery($request, Auth::user());

        $documentos = $this->colherAteAoLimite($query);
        if ($documentos === null) {
            return $this->recusarExportacao();
        }

        return Excel::download(new DocumentoEntradasExport($documentos), 'relatorio-documentos-entradas-'.date('Y-m-d').'.xlsx');
    }

    /**
     * Colhe o resultado com um item a mais do que o limite: se vier esse item
     * extra, o pedido excede o limite e é recusado. Uma só query — contar e
     * depois colher percorreria o filtro duas vezes.
     *
     * @return \Illuminate\Support\Collection|null  null quando excede o limite.
     */
    private function colherAteAoLimite($query)
    {
        $limite = (int) config('documentos.limite_exportacao', 5000);

        $documentos = $query->limit($limite + 1)->get();

        return $documentos->count() > $limite ? null : $documentos;
    }

    private function recusarExportacao()
    {
        $limite = (int) config('documentos.limite_exportacao', 5000);

        return back()->with('error', sprintf(
            'A exportação excede o limite de %s registos. Restrinja o intervalo de datas, o departamento ou o estado e tente novamente.',
            number_format($limite, 0, ',', ' '),
        ));
    }

    /**
     * Garante que o documento tem protocolo (normalmente criado no registo).
     */
    private function ensureProtocolo(DocumentoEntrada $documento): void
    {
        if ($documento->protocolo) {
            return;
        }

        $codigo = sprintf('PRT-%d-%03d-%s', $documento->ano_referencia, $documento->numero_sequencial, strtoupper(Str::random(6)));
        $consultaUrl = route('documentos-entradas.protocolo', $documento);
        $protocolo = DocumentoProtocolo::create([
            'documento_entrada_id' => $documento->id,
            'codigo' => $codigo,
            'url_consulta' => $consultaUrl,
            'gerado_em' => now(),
        ]);
        $documento->setRelation('protocolo', $protocolo);
    }
}
