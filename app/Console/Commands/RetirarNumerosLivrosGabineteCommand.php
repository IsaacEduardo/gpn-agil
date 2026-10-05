<?php

namespace App\Console\Commands;

use App\Models\DocumentoInterno;
use App\Models\User;
use App\Services\DocumentoInternoService;
use App\Services\NumeracaoDocumentoService;
use App\Support\SeriesNumeracao;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Retira o número aos documentos dos livros do gabinete (Ofício, OS, Nota, Informação/Parecer)
 * criados antes do início da numeração automática: a referência e o "Nº" do corpo ficam em
 * branco, como nos documentos novos, e a série do ano volta ao maior número que ainda resta.
 *
 * Sem --executar só lista. Ficam de fora (listados para decisão manual) os documentos
 * assinados — alterar o corpo invalidaria a assinatura — e as referências que não seguem o
 * formato da série (ex.: referências antigas).
 */
class RetirarNumerosLivrosGabineteCommand extends Command
{
    protected $signature = 'numeracao:retirar-numeros-gabinete {--executar : Aplica as alterações (sem esta opção só lista)}';

    protected $description = 'Retira o número aos ofícios, OS, notas e informações/pareceres do gabinete criados antes do início da numeração automática';

    public function handle(DocumentoInternoService $documentos): int
    {
        $inicio = NumeracaoDocumentoService::inicioNumeracaoGabinete();
        if (! $inicio) {
            $this->error('Não há data de início da numeração dos livros do gabinete (ecrã Numeração): nada a fazer.');

            return self::FAILURE;
        }

        $candidatos = DocumentoInterno::with(['especie', 'departamento', 'gabinete', 'autor'])
            ->whereNotNull('numero_referencia')
            ->where('created_at', '<', $inicio->setTimezone(config('app.timezone')))
            ->orderBy('id')
            ->get()
            ->map(function (DocumentoInterno $doc) {
                $serie = SeriesNumeracao::paraDocumento($doc);
                $ano = (int) $doc->created_at->copy()->setTimezone(NumeracaoDocumentoService::FUSO)->year;

                return compact('doc', 'serie', 'ano');
            })
            ->filter(fn ($c) => $c['serie']->livroDoGabinete());

        $aplicar = $ignorados = [];
        foreach ($candidatos as $c) {
            $doc = $c['doc'];
            if ($doc->assinado_em || $doc->assinatura_hash) {
                $ignorados[] = [$doc->id, $doc->numero_referencia, 'assinado'];
            } elseif ($c['serie']->numeroDe($doc->numero_referencia, $c['ano']) === null) {
                $ignorados[] = [$doc->id, $doc->numero_referencia, 'fora do formato da série'];
            } else {
                $aplicar[] = $c;
            }
        }

        $this->info('Numeração automática dos livros do gabinete a partir de '.$inicio->format('d/m/Y').'.');
        $this->table(['ID', 'Referência actual', 'Fica'], array_map(fn ($c) => [
            $c['doc']->id, $c['doc']->numero_referencia, $c['serie']->formatarProvisoria($c['ano']),
        ], $aplicar));

        if ($ignorados !== []) {
            $this->warn('Não alterados (decidir manualmente):');
            $this->table(['ID', 'Referência', 'Motivo'], $ignorados);
        }

        if (! $this->option('executar')) {
            $this->comment('Simulação: nada foi alterado. Repita com --executar para aplicar.');

            return self::SUCCESS;
        }

        $series = [];
        foreach ($aplicar as $c) {
            DB::transaction(function () use ($c, $documentos, $inicio) {
                $doc = $c['doc'];
                $autor = $doc->autor ?? User::find($doc->criado_por);
                if ($autor) {
                    $documentos->garantirVersaoDoConteudoActual($doc, $autor, 'Antes de retirar o número (numeração no sistema a partir de '.$inicio->format('d/m/Y').')');
                    $doc->refresh();
                }

                $doc->auditMotivo = 'Número retirado: a numeração dos livros do gabinete no sistema começa a '.$inicio->format('d/m/Y');
                $doc->conteudo_final = $documentos->aplicarReferenciaEmBranco((string) $doc->conteudo_final, $c['serie']->formatarProvisoria($c['ano']));
                $doc->numero_referencia = null;
                $doc->save();

                // Como numa gravação no editor clássico: o estado colaborativo fica desactualizado.
                $doc->collabUpdates()->delete();
                DocumentoInterno::whereKey($doc->id)->increment('revisao_classica');
            });

            $series[$c['serie']->chave.'|'.$c['ano']] = $c;
        }

        // A série do ano volta ao maior número que ainda existe (normalmente 0).
        foreach ($series as $c) {
            DB::table('sequencias_documentos')
                ->where('chave', $c['serie']->chave)->where('ano', $c['ano'])
                ->update(['ultimo_numero' => $c['serie']->maiorEmitido($c['ano']), 'updated_at' => now()]);
        }

        $this->info(count($aplicar).' documento(s) ficaram sem número.');

        return self::SUCCESS;
    }
}
