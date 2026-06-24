<?php

namespace App\Services;

use App\Models\DocumentoEntrada;
use App\Models\DocumentoInterno;
use App\Models\DocumentoVersao;
use App\Models\ModeloDocumento;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DocumentoInternoService
{
    /**
     * Retorna os templates disponíveis para o usuário, priorizando os do seu gabinete.
     */
    public function getTemplatesForUser(User $user)
    {
        $gabineteId = $user->departamento ? $user->departamento->gabinete_id : null;

        return ModeloDocumento::where('ativo', true)
            ->where(function ($q) use ($gabineteId) {
                $q->whereNull('gabinete_id');
                if ($gabineteId) {
                    $q->orWhere('gabinete_id', $gabineteId);
                }
            })
            ->with('especie')
            ->get();
        // Lógica adicional de filtragem pode ser aplicada aqui se necessário
        // Ex: Se existir um modelo específico do gabinete para uma espécie, ocultar o global.
    }

    public function processarTemplate(string $conteudoTemplate, ?DocumentoEntrada $docEntrada, User $user, array $dadosExtras = []): string
    {
        // Obter Responsável do Gabinete
        $responsavel = $user->departamento?->gabinete?->responsavel;
        $nomeResponsavel = $responsavel ? $responsavel->name : $user->name;
        // Se houver cargo definido no user, usa, senão tenta inferir ou usa genérico
        // Assumindo que o cargo pode vir de uma propriedade ou relação futura. Por agora, usamos placeholder genérico se não for o user atual.
        // Mas vamos manter simples: O Nome é o mais importante.

        // Obter dados da instituição cadastrada
        try {
            $dadosInstituicao = \App\Models\DadosInstituicao::first() ?? new \App\Models\DadosInstituicao;
        } catch (\Exception $e) {
            $dadosInstituicao = new \App\Models\DadosInstituicao;
        }

        // Fallbacks se estiver vazio
        if (empty($dadosInstituicao->nome_oficial)) {
            $dadosInstituicao->nome_oficial = 'Governo Provincial do Namibe';
        }
        if (empty($dadosInstituicao->sigla)) {
            $dadosInstituicao->sigla = 'GPN';
        }
        if (empty($dadosInstituicao->cidade)) {
            $dadosInstituicao->cidade = 'Moçâmedes';
        }
        if (empty($dadosInstituicao->cabecalho_linha1)) {
            $dadosInstituicao->cabecalho_linha1 = 'REPÚBLICA DE ANGOLA';
        }
        if (empty($dadosInstituicao->cabecalho_linha2)) {
            $dadosInstituicao->cabecalho_linha2 = 'GOVERNO PROVINCIAL DO NAMIBE';
        }

        $placeholders = [
            '{{DATA_ATUAL}}' => now()->format('d/m/Y'),
            '{{DATA_EXTENSO}}' => now()->translatedFormat('d \d\e F \d\e Y'),
            '{{ANO}}' => now()->format('Y'),
            '{{USUARIO_NOME}}' => $user->name, // Quem elabora
            '{{RESPONSAVEL_NOME}}' => $nomeResponsavel, // Quem assina (Chefe ou próprio)
            '{{DEPARTAMENTO_NOME}}' => $user->departamento ? $user->departamento->nome : 'Departamento',
            '{{GABINETE_NOME}}' => ($user->departamento && $user->departamento->gabinete) ? $user->departamento->gabinete->nome : '',
            '{{DEPARTAMENTO_SIGLA}}' => $user->departamento ? strtoupper(Str::slug($user->departamento->nome, '')) : 'DEP',
            '{{INSTITUICAO_NOME}}' => $dadosInstituicao->nome_oficial,
            '{{INSTITUICAO_CABECALHO_1}}' => $dadosInstituicao->cabecalho_linha1,
            '{{INSTITUICAO_CABECALHO_2}}' => $dadosInstituicao->cabecalho_linha2,
            '{{INSTITUICAO_CABECALHO_3}}' => $dadosInstituicao->cabecalho_linha3 ?? '',
            '{{INSTITUICAO_LOCAL}}' => $dadosInstituicao->cidade,
        ];

        // Construir a linha da data institucional
        $gabineteNome = ($user->departamento && $user->departamento->gabinete) ? $user->departamento->gabinete->nome : ($user->departamento ? $user->departamento->nome : $dadosInstituicao->cabecalho_linha2);
        $linhaData = mb_strtoupper($gabineteNome).', em '.$dadosInstituicao->cidade.', aos '.now()->translatedFormat('d \d\e F \d\e Y');

        $placeholders['{{RODAPE_INSTITUCIONAL_DATA}}'] = $linhaData;

        // Helper to trim and check
        $getVal = fn ($key, $default) => ! empty($dadosExtras[$key]) && trim($dadosExtras[$key]) !== '' ? trim($dadosExtras[$key]) : $default;

        // Recipient placeholders from dadosExtras (which comes from request inputs)
        $placeholders['{{DESTINATARIO_NOME}}'] = $getVal('destinatario_nome', '[NOME DO DESTINATÁRIO]');
        $placeholders['{{DESTINATARIO_CARGO}}'] = $getVal('destinatario_cargo', '[CARGO]');
        $placeholders['{{DESTINATARIO_ORGAO}}'] = $getVal('destinatario_orgao', '[INSTITUIÇÃO/ÓRGÃO]');
        $placeholders['{{DESTINATARIO_LOCAL}}'] = $getVal('destinatario_local', $dadosInstituicao->cidade);

        // Subject placeholder
        $placeholders['{{ASSUNTO}}'] = $getVal('titulo', '[ASSUNTO]');

        if ($docEntrada) {
            $placeholders['{{DOCUMENTO_ORIGEM_NUMERO}}'] = $docEntrada->numero_sequencial.'/'.$docEntrada->ano_referencia;
            $placeholders['{{DOCUMENTO_ORIGEM_ASSUNTO}}'] = $docEntrada->assunto;
            $placeholders['{{DOCUMENTO_ORIGEM_PROCEDENCIA}}'] = $docEntrada->procedencia;
            $placeholders['{{DOCUMENTO_ORIGEM_DATA}}'] = $docEntrada->data_documento ? $docEntrada->data_documento->format('d/m/Y') : '';
        }

        // Merge extra data (user input fields)
        foreach ($dadosExtras as $key => $value) {
            $placeholders['{{'.strtoupper($key).'}}'] = $value;
        }

        $conteudoProcessado = str_replace(array_keys($placeholders), array_values($placeholders), $conteudoTemplate);

        // Auto-fix: Se a linha da data não estiver presente (via placeholder ou texto), injetar antes da assinatura
        // Procurar por padrões comuns de assinatura
        if (! str_contains($conteudoTemplate, '{{RODAPE_INSTITUCIONAL_DATA}}') && ! str_contains($conteudoProcessado, $linhaData)) {
            // Tentar identificar o bloco de assinatura (geralmente centralizado no final)
            // Heurística: Inserir antes do último <div> com text-align: center que contém {{RESPONSAVEL_NOME}} ou similar

            // Se encontrar a tag da assinatura
            if (str_contains($conteudoTemplate, '{{RESPONSAVEL_NOME}}')) {
                // Substituir o bloco que contém a assinatura adicionando a data antes
                // Como não podemos fazer parse HTML robusto aqui, vamos tentar inserir antes do container pai se possível
                // Ou simplesmente antes da variável.

                // Estratégia simples: Inserir antes da variável {{RESPONSAVEL_NOME}} um bloco div com a data?
                // Não, pois a assinatura já tem um div.

                // Vamos tentar encontrar o container "text-align: center" que envolve a assinatura.
                // Regex para encontrar <div ...> ... {{RESPONSAVEL_NOME}} ... </div>
                // Isso é complexo.

                // Vamos usar uma abordagem mais segura: Inserir logo acima da variável {{RESPONSAVEL_NOME}}
                // Mas a variável costuma estar dentro de um <strong> e depois de uma linha _____________.

                // Se inserirmos antes de "_____________________________________________", geralmente acertamos.
                // Usando Regex para ser mais flexível com a quantidade de underscores (mínimo 10)
                if (preg_match('/_{10,}/', $conteudoProcessado)) {
                    // Substitui apenas a primeira ocorrência encontrada, adicionando a data antes
                    $conteudoProcessado = preg_replace(
                        '/(_{10,})/',
                        '<div style="margin-bottom: 20px; font-family: \'Times New Roman\', serif; font-size: 12pt;">'.$linhaData.'</div>$1',
                        $conteudoProcessado,
                        1
                    );
                } elseif (str_contains($conteudoTemplate, '{{RESPONSAVEL_NOME}}')) {
                    // Fallback: Inserir antes do nome do responsável, mas pode quebrar a linha de assinatura
                    // Vamos tentar inserir antes de "O(A) CHEFE", "A DIREÇÃO", etc.
                    $termosAssinatura = ['O(A) CHEFE DE DEPARTAMENTO', 'A DIREÇÃO', 'O Responsável', 'O Diretor', 'O Secretário'];
                    foreach ($termosAssinatura as $termo) {
                        if (str_contains($conteudoProcessado, $termo)) {
                            $conteudoProcessado = str_replace(
                                $termo,
                                '<div style="margin-bottom: 20px; font-family: \'Times New Roman\', serif; font-size: 12pt;">'.$linhaData.'</div>'.$termo,
                                $conteudoProcessado
                            );
                            break;
                        }
                    }
                }
            }
        }

        return $conteudoProcessado;
    }

    public function gerarNumeroReferencia(DocumentoInterno $doc): string
    {
        // Format: SIGLA_DEP/ESPECIE/SEQ/ANO
        // Example: DTI/MEMO/001/2025

        $ano = now()->year;

        // Count existing docs for this department and year
        $count = DocumentoInterno::where('departamento_id', $doc->departamento_id)
            ->whereYear('created_at', $ano)
            ->count();
        // Note: This count assumes the current doc is not yet saved or we increment.
        // Since we generate this usually before or during save, let's assume +1.
        // If updating, we should check if ref already exists.

        $seq = $count + 1;

        $especie = $doc->especie ? strtoupper(Str::slug($doc->especie->nome, '')) : 'DOC';
        // Shorten especie if needed, e.g. MEMORANDO -> MEMO
        $especieShort = match ($especie) {
            'MEMORANDO' => 'MEMO',
            'OFICIO' => 'OF',
            'DESPACHO' => 'DESP',
            'CIRCULAR' => 'CIRC',
            'NOTA' => 'NOTA',
            default => substr($especie, 0, 4)
        };

        $depSigla = 'DEP';
        if ($doc->departamento) {
            if (! empty($doc->departamento->sigla)) {
                $depSigla = $doc->departamento->sigla;
            } else {
                $slug = strtoupper(Str::slug($doc->departamento->nome, ''));
                $depSigla = substr($slug, 0, 3);
            }
        }

        return sprintf('%s/%s/%03d/%d', $depSigla, $especieShort, $seq, $ano);
    }

    /**
     * Atualiza o documento e gera uma nova versão no histórico (Semântico).
     *
     * @param  array  $dados  Novos dados (titulo, conteudo_final)
     * @param  User  $usuario  Usuario realizando a alteração
     * @param  string  $changeType  'major', 'minor', 'patch'
     * @param  string|null  $changeLog  Descrição da alteração
     */
    public function updateWithVersioning(DocumentoInterno $documento, array $dados, User $usuario, string $changeType = 'patch', ?string $changeLog = null): DocumentoInterno
    {
        return DB::transaction(function () use ($documento, $dados, $usuario, $changeType, $changeLog) {
            // Determine new version components
            $major = $documento->versao_major ?? 0;
            $minor = $documento->versao_minor ?? 0;
            $patch = $documento->versao_patch ?? 0;

            switch ($changeType) {
                case 'major':
                    $major++;
                    $minor = 0;
                    $patch = 0;
                    break;
                case 'minor':
                    $minor++;
                    $patch = 0;
                    break;
                case 'patch':
                default:
                    $patch++;
                    break;
            }

            // 1. Atualiza o documento principal
            $documento->update([
                'titulo' => $dados['titulo'],
                'conteudo_final' => $dados['conteudo_final'],
                'versao_major' => $major,
                'versao_minor' => $minor,
                'versao_patch' => $patch,
                'versao_atual' => ($documento->versao_atual ?? 0) + 1, // Legacy increment
            ]);

            // 2. Cria o registro no histórico de versões
            DocumentoVersao::create([
                'documento_interno_id' => $documento->id,
                'versao' => $documento->versao_atual,
                'major' => $major,
                'minor' => $minor,
                'patch' => $patch,
                'titulo' => $dados['titulo'],
                'conteudo_final' => $dados['conteudo_final'],
                'criado_por' => $usuario->id,
                'change_log' => $changeLog,
            ]);

            return $documento;
        });
    }

    /**
     * Restaura uma versão antiga do documento.
     * Salva o estado atual como uma nova versão antes de restaurar.
     */
    public function restoreVersion(DocumentoInterno $documento, int $versionId, User $usuario): DocumentoInterno
    {
        return DB::transaction(function () use ($documento, $versionId, $usuario) {
            // Encontra a versão desejada (pelo ID da versão legado ou ID da tabela?)
            // O parâmetro chama $version, mas no código original buscava por 'versao'.
            // Vamos assumir que $versionId é o número sequencial da versão (versao_atual).
            $historico = DocumentoVersao::where('documento_interno_id', $documento->id)
                ->where('versao', $versionId)
                ->firstOrFail();

            // Salva o estado atual como uma nova versão antes de sobrescrever (Patch change)
            $this->updateWithVersioning($documento, [
                'titulo' => $documento->titulo,
                'conteudo_final' => $documento->conteudo_final,
            ], $usuario, 'patch', "Backup antes de restaurar versão {$versionId}");

            // Restaura o conteúdo da versão antiga no documento principal
            // Mas sobrescreve os dados atuais. A versão semântica foi incrementada no updateWithVersioning acima.
            $documento->update([
                'titulo' => $historico->titulo,
                'conteudo_final' => $historico->conteudo_final,
            ]);

            return $documento;
        });
    }
}
