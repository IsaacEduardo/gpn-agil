<?php

namespace Tests\Unit\Jobs;

use App\Jobs\GerarPdfDocumentoJob;
use App\Jobs\ProcessarAssinaturaLoteJob;
use App\Jobs\ProcessarOcrAnexo;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AsyncJobsTest extends TestCase
{
    public function test_pode_despachar_job_de_geracao_de_pdf_para_fila(): void
    {
        Queue::fake();

        GerarPdfDocumentoJob::dispatch(123, 'documentos/pdf_123.pdf');

        Queue::assertPushed(GerarPdfDocumentoJob::class, function ($job) {
            return $job->documentoId === 123 && $job->caminhoDestino === 'documentos/pdf_123.pdf';
        });
    }

    public function test_pode_despachar_job_de_assinatura_em_lote_para_fila(): void
    {
        Queue::fake();

        ProcessarAssinaturaLoteJob::dispatch([1, 2, 3], 42, 'secret123', 'certpass');

        Queue::assertPushed(ProcessarAssinaturaLoteJob::class, function ($job) {
            return $job->documentoIds === [1, 2, 3] && $job->userId === 42;
        });
    }

    public function test_pode_despachar_job_de_ocr_para_fila(): void
    {
        Queue::fake();

        ProcessarOcrAnexo::dispatch(99);

        Queue::assertPushed(ProcessarOcrAnexo::class);
    }
}
