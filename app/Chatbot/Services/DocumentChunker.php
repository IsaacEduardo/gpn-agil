<?php

namespace App\Chatbot\Services;

use App\Models\DocumentoEntrada;
use App\Models\DocumentoInterno;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

/**
 * Extrai e fragmenta o texto dos documentos para indexação.
 * - Externos (DocumentoEntrada): metadados + anexos (PDF por página via pdfparser; OCR como fallback).
 * - Internos (DocumentoInterno): metadados + conteudo_final (HTML → texto).
 *
 * Cada chunk: ['tipo','anexo_id','pagina','indice','conteudo'].
 */
class DocumentChunker
{
    private int $size;

    private int $overlap;

    private int $maxChunks = 200;

    public function __construct()
    {
        $this->size = (int) config('chatbot.chunk.size', 1000);
        $this->overlap = (int) config('chatbot.chunk.overlap', 150);
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function chunkEntrada(DocumentoEntrada $doc): array
    {
        $doc->loadMissing('anexos');
        $chunks = [];

        // Metadados como primeiro chunk (procedência/assunto são muito pesquisados).
        $meta = collect([
            'Assunto: '.($doc->assunto ?? ''),
            'Procedência: '.($doc->procedencia ?? ''),
            'Espécie: '.($doc->classificacao_especie ?? ''),
            'Nº/Ano: '.($doc->numero_sequencial ?? '').'/'.($doc->ano_referencia ?? ''),
            'Observações: '.($doc->observacoes ?? ''),
        ])->filter(fn ($l) => trim(substr($l, strpos($l, ':') + 1)) !== '')->implode("\n");

        if (trim($meta) !== '') {
            $chunks[] = ['tipo' => 'externo', 'anexo_id' => null, 'pagina' => null, 'conteudo' => $meta];
        }

        $disk = config('filesystems.docs_disk', 'private');

        foreach ($doc->anexos as $anexo) {
            $paginas = $this->extrairPaginasAnexo($anexo, $disk);

            foreach ($paginas as $pagina => $texto) {
                foreach ($this->splitText($texto) as $parte) {
                    $chunks[] = [
                        'tipo' => 'externo',
                        'anexo_id' => $anexo->id,
                        'pagina' => is_int($pagina) ? $pagina : null,
                        'conteudo' => $parte,
                    ];
                }
            }
        }

        return $this->finalize($chunks);
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function chunkInterno(DocumentoInterno $doc): array
    {
        $doc->loadMissing('especie');
        $corpo = trim($this->htmlParaTexto((string) $doc->conteudo_final));

        $cabecalho = collect([
            'Título: '.($doc->titulo ?? ''),
            'Referência: '.($doc->numero_referencia ?? ''),
            'Espécie: '.(optional($doc->especie)->nome ?? ''),
            'Destinatário: '.($doc->destinatario_nome ?? ''),
        ])->filter(fn ($l) => trim(substr($l, strpos($l, ':') + 1)) !== '')->implode("\n");

        $texto = trim($cabecalho."\n\n".$corpo);
        $chunks = [];
        foreach ($this->splitText($texto) as $parte) {
            $chunks[] = ['tipo' => 'interno', 'anexo_id' => null, 'pagina' => null, 'conteudo' => $parte];
        }

        return $this->finalize($chunks);
    }

    /**
     * Extrai texto por página. Para PDFs com texto nativo devolve [pagina => texto];
     * caso contrário usa o texto_extraido (OCR) como página única [null => texto].
     *
     * @return array<int|string, string>
     */
    private function extrairPaginasAnexo($anexo, string $disk): array
    {
        try {
            if ($anexo->mime_type === 'application/pdf'
                && $anexo->caminho_arquivo
                && Storage::disk($disk)->exists($anexo->caminho_arquivo)) {
                $parser = new Parser;
                $pdf = $parser->parseFile(Storage::disk($disk)->path($anexo->caminho_arquivo));
                $paginas = [];
                $n = 0;
                foreach ($pdf->getPages() as $page) {
                    $n++;
                    $txt = trim($this->normalizar($page->getText()));
                    if ($txt !== '') {
                        $paginas[$n] = $txt;
                    }
                }
                if ($paginas !== []) {
                    return $paginas;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Chatbot chunker: falha a ler PDF do anexo '.$anexo->id.': '.$e->getMessage());
        }

        // Fallback: texto já extraído (OCR), sem granularidade de página.
        $texto = trim($this->normalizar((string) $anexo->texto_extraido));

        return $texto !== '' ? ['ocr' => $texto] : [];
    }

    /**
     * @return array<int, string>
     */
    private function splitText(string $text): array
    {
        $text = trim($this->normalizar($text));
        if ($text === '') {
            return [];
        }
        if (mb_strlen($text) <= $this->size) {
            return [$text];
        }

        $parts = [];
        $start = 0;
        $len = mb_strlen($text);
        $step = max(1, $this->size - $this->overlap);
        while ($start < $len) {
            $parts[] = mb_substr($text, $start, $this->size);
            $start += $step;
        }

        return $parts;
    }

    private function normalizar(string $text): string
    {
        return preg_replace('/[ \t]+/u', ' ', preg_replace('/\R{3,}/u', "\n\n", $text)) ?? $text;
    }

    private function htmlParaTexto(string $html): string
    {
        $html = preg_replace('#<\s*br\s*/?>#i', "\n", $html);
        $html = preg_replace('#</\s*(p|div|li|h[1-6]|tr)\s*>#i', "\n", $html);

        return html_entity_decode(strip_tags($html ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Reindexa os índices e aplica o limite máximo de chunks.
     *
     * @param  array<int, array<string,mixed>>  $chunks
     * @return array<int, array<string,mixed>>
     */
    private function finalize(array $chunks): array
    {
        $chunks = array_slice($chunks, 0, $this->maxChunks);
        foreach ($chunks as $i => &$c) {
            $c['indice'] = $i;
        }

        return $chunks;
    }
}
