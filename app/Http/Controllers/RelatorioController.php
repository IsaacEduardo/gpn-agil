<?php

namespace App\Http\Controllers;

use App\Exports\RelatorioGeralExport;
use App\Models\User;
use App\Services\ReportService;
use App\Support\CabecalhoDocumento;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Painel de Relatórios & Business Intelligence do EDMS e respetivas
 * exportações formais (PDF, XLSX, CSV e XML).
 *
 * O acesso exige `relatorios.view`; qualquer exportação exige também
 * `relatorios.export`. Ver RelatoriosPermissionSeeder para a atribuição
 * das permissões aos papéis.
 */
class RelatorioController extends Controller
{
    public function __construct(protected ReportService $reportService) {}

    /**
     * Painel principal: filtros ativos, KPIs, séries dos gráficos e tabelas.
     */
    public function index(Request $request)
    {
        $user = $this->utilizador();
        $this->autorizarLeitura($user);

        $data = $this->reportService->getReportData($this->filtrosValidados($request), $user);

        return view('relatorios.index', $data);
    }

    /**
     * PDF institucional. Os gráficos são recebidos como PNG em base64, gerados
     * no cliente pelo Chart.js — o DomPDF não executa JavaScript.
     */
    public function exportPdf(Request $request): SymfonyResponse
    {
        $user = $this->utilizador();
        $this->autorizarExportacao($user);

        $data = $this->dadosExportacao($request, $user);

        if ($data['lists']['excede_limite']) {
            return $this->recusarExportacao();
        }

        $data['graficos_base64'] = array_filter([
            'Evolução temporal no período' => $this->imagemBase64($request->input('chart_temporal_img')),
            'Distribuição por estado' => $this->imagemBase64($request->input('chart_status_img')),
            'Produtividade por departamento' => $this->imagemBase64($request->input('chart_departamentos_img')),
        ]);

        $pdf = Pdf::loadView('relatorios.pdf', $data)
            ->setPaper('a4', 'portrait')
            ->setOption(['isRemoteEnabled' => true, 'isHtml5ParserEnabled' => true]);

        return $pdf->download($this->nomeFicheiro('pdf'));
    }

    /**
     * Planilha XLSX com o resumo de indicadores e o detalhe documental.
     */
    public function exportExcel(Request $request): SymfonyResponse
    {
        $user = $this->utilizador();
        $this->autorizarExportacao($user);

        $data = $this->dadosExportacao($request, $user);

        if ($data['lists']['excede_limite']) {
            return $this->recusarExportacao();
        }

        return Excel::download(new RelatorioGeralExport($data), $this->nomeFicheiro('xlsx'));
    }

    /**
     * Mesmo conteúdo do XLSX em CSV, para reutilização noutras ferramentas.
     */
    public function exportCsv(Request $request): SymfonyResponse
    {
        $user = $this->utilizador();
        $this->autorizarExportacao($user);

        $data = $this->dadosExportacao($request, $user);

        if ($data['lists']['excede_limite']) {
            return $this->recusarExportacao();
        }

        return Excel::download(new RelatorioGeralExport($data), $this->nomeFicheiro('csv'), ExcelFormat::CSV);
    }

    /**
     * Metadados em XML estruturado, para interoperabilidade entre sistemas
     * de arquivo. É construído em streaming (XMLWriter) para suportar o teto
     * de linhas das exportações sem acumular a árvore inteira em memória.
     */
    public function exportXml(Request $request): SymfonyResponse
    {
        $user = $this->utilizador();
        $this->autorizarExportacao($user);

        $data = $this->dadosExportacao($request, $user);

        if ($data['lists']['excede_limite']) {
            return $this->recusarExportacao();
        }

        $filtros = $data['filters'];

        $xml = new \XMLWriter;
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement('relatorio_edms');
        $xml->writeAttribute('sistema', 'GPN-AGIL');
        $xml->writeAttribute('gerado_em', now()->toIso8601String());
        $xml->writeAttribute('operador', (string) $user->name);

        $xml->startElement('periodo');
        $xml->writeAttribute('granularidade', (string) $filtros['granularity']);
        $xml->writeAttribute('de', (string) $filtros['date_from']);
        $xml->writeAttribute('ate', (string) $filtros['date_to']);
        $xml->endElement();

        $xml->startElement('kpis');
        foreach ($data['kpis'] as $chave => $valor) {
            $xml->writeElement($chave, $valor === null ? '' : (string) $valor);
        }
        $xml->endElement();

        $xml->startElement('documentos_entrada');
        foreach ($data['lists']['entradas'] as $doc) {
            $xml->startElement('documento');
            $xml->writeElement('id', (string) $doc->id);
            $xml->writeElement('numero', $doc->numero_sequencial.'/'.$doc->ano_referencia);
            $xml->writeElement('data_entrada', optional($doc->data_entrada)->format('Y-m-d') ?: '');
            $xml->writeElement('especie', (string) $doc->classificacao_especie);
            $xml->writeElement('assunto', (string) $doc->assunto);
            $xml->writeElement('procedencia', (string) (optional($doc->procedenciaCatalogo)->nome ?: $doc->procedencia));
            $xml->writeElement('departamento', (string) optional($doc->departamento)->nome);
            $xml->writeElement('status', (string) $doc->status);
            $xml->writeElement('dias_decorridos', (string) $doc->dias_decorridos);
            $xml->endElement();
        }
        $xml->endElement();

        $xml->startElement('documentos_internos');
        foreach ($data['lists']['internos'] as $doc) {
            $xml->startElement('documento');
            $xml->writeElement('id', (string) $doc->id);
            $xml->writeElement('numero_referencia', (string) $doc->numero_referencia);
            $xml->writeElement('titulo', (string) $doc->titulo);
            $xml->writeElement('especie', (string) optional($doc->especie)->nome);
            $xml->writeElement('departamento', (string) optional($doc->departamento)->nome);
            $xml->writeElement('autor', (string) optional($doc->autor)->name);
            $xml->writeElement('status', $doc->status instanceof \BackedEnum ? $doc->status->value : (string) $doc->status);
            $xml->writeElement('criado_em', optional($doc->created_at)->toIso8601String() ?: '');
            $xml->writeElement('assinado_em', optional($doc->assinado_em)->toIso8601String() ?: '');
            $xml->endElement();
        }
        $xml->endElement();

        $xml->endElement();
        $xml->endDocument();

        return response($xml->outputMemory(), 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="'.$this->nomeFicheiro('xml').'"',
        ]);
    }

    // -----------------------------------------------------------------
    // Apoio
    // -----------------------------------------------------------------

    /**
     * Filtros aceites. Validar aqui evita que uma granularidade ou uma data
     * inválidas cheguem ao serviço de agregação.
     *
     * @return array<string, mixed>
     */
    protected function filtrosValidados(Request $request): array
    {
        return $request->validate([
            'granularity' => ['nullable', 'string', 'in:'.implode(',', ReportService::GRANULARIDADES)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'departamento_id' => ['nullable', 'integer', 'exists:departamentos,id'],
            'especie' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:30'],
        ]);
    }

    /**
     * Conjunto de dados das exportações: listas completas e cabeçalho de emissão.
     *
     * @return array<string, mixed>
     */
    protected function dadosExportacao(Request $request, User $user): array
    {
        $data = $this->reportService->getReportData($this->filtrosValidados($request), $user, paraExportacao: true);

        $instituicao = CabecalhoDocumento::instituicao();

        $data['instituicao'] = $instituicao;
        $data['linha_gabinete'] = CabecalhoDocumento::linhaGabineteDeUser($user, $instituicao);
        $data['emitido_por'] = $user->name;
        $data['emitido_em'] = now()->format('d/m/Y H:i:s');
        // Identificador curto de emissão, para rastrear a cópia impressa.
        $data['identificador_emissao'] = strtoupper(substr(sha1($user->id.'|'.now()->timestamp), 0, 10));

        return $data;
    }

    /**
     * Aceita apenas data URIs de imagem PNG/JPEG produzidas pelo Chart.js.
     * Sem esta validação, um POST arbitrário faria o DomPDF ir buscar um
     * recurso externo indicado pelo cliente.
     */
    protected function imagemBase64(?string $valor): ?string
    {
        if (! is_string($valor) || $valor === '') {
            return null;
        }

        return preg_match('#^data:image/(png|jpeg);base64,[A-Za-z0-9+/=]+$#', $valor) === 1
            ? $valor
            : null;
    }

    /**
     * Acima do teto de registos a exportação é recusada, e não truncada: um
     * relatório oficial incompleto sem aviso é pior do que nenhum. Mesma
     * regra das exportações de documentos de entrada.
     */
    protected function recusarExportacao(): SymfonyResponse
    {
        $limite = (int) config('documentos.limite_exportacao', 5000);

        return back()->with('error', sprintf(
            'A exportação excede o limite de %s registos. Restrinja o período, o departamento ou a espécie e tente novamente.',
            number_format($limite, 0, ',', ' ')
        ));
    }

    protected function nomeFicheiro(string $extensao): string
    {
        return 'relatorio-edms-'.now()->format('Y-m-d-His').'.'.$extensao;
    }

    protected function utilizador(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    /**
     * Leitura do painel. Os perfis de chefia têm acesso por inerência; os
     * restantes precisam da permissão explícita.
     */
    protected function autorizarLeitura(User $user): void
    {
        $permitido = $user->can('relatorios.view')
            || $user->isAdmin()
            || $user->isChefeGabinete()
            || $user->isSuperChefeGabinete()
            || $user->isChefeDepartamento();

        abort_unless($permitido, 403, 'Acesso negado ao módulo de relatórios.');
    }

    /**
     * Exportar é um ato distinto de consultar: extrai o acervo para fora do
     * sistema, por isso exige a sua própria permissão.
     */
    protected function autorizarExportacao(User $user): void
    {
        $this->autorizarLeitura($user);

        $permitido = $user->can('relatorios.export')
            || $user->isAdmin()
            || $user->isChefeGabinete()
            || $user->isSuperChefeGabinete()
            || $user->isChefeDepartamento();

        abort_unless($permitido, 403, 'Sem permissão para exportar relatórios.');
    }
}
