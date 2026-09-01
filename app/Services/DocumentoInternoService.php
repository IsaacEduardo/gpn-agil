<?php

namespace App\Services;

use App\Enums\DocumentoStatus;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\DocumentoVersao;
use App\Models\ModeloDocumento;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DocumentoInternoService
{
    /**
     * Verifica se o utilizador pertence à Secretaria Geral ou possui privilégios de Admin.
     */
    public function isUserSecretariaGeral(User $user): bool
    {
        if ($user->isAdmin() || $user->hasRole('admin') || $user->hasRole('Admin')) {
            return true;
        }

        $dep = $user->departamento;
        if (! $dep) {
            return false;
        }

        $depSigla = strtoupper($dep->sigla ?? '');
        $depNome = mb_strtoupper($dep->nome ?? '');

        $validSiglas = ['SEC_GERAL', 'SEC.GERAL', 'SEC_GER', 'SG', 'SEC.GER.GOV.PROV.HLA'];
        if (in_array($depSigla, $validSiglas)) {
            return true;
        }

        if (str_contains($depNome, 'SECRETARIA GERAL') || str_contains($depNome, 'SECRETÁRIA GERAL')) {
            return true;
        }

        $gab = $dep->gabinete;
        if ($gab) {
            $gabSigla = strtoupper($gab->sigla ?? '');
            $gabNome = mb_strtoupper($gab->nome ?? '');
            if (in_array($gabSigla, $validSiglas) || str_contains($gabNome, 'SECRETARIA GERAL') || str_contains($gabNome, 'SECRETÁRIA GERAL')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retorna a lista dos Chefes de Departamento do gabinete do utilizador.
     */
    public function getChefesDepartamentoForUser(User $user)
    {
        $gabineteId = $user->departamento ? $user->departamento->gabinete_id : null;

        $query = User::query()->with('departamento');

        if ($gabineteId) {
            $query->whereHas('departamento', function ($q) use ($gabineteId) {
                $q->where('gabinete_id', $gabineteId);
            });
        } else {
            $query->where('departamento_id', $user->departamento_id);
        }

        return $query->where(function ($q) {
            $q->whereHas('roles', function ($sq) {
                $sq->where('name', 'chefe-departamento')
                    ->orWhere('name', 'Chefe de Departamento')
                    ->orWhere('name', 'like', '%chefe%');
            })
            ->orWhereIn('id', function ($sq) {
                $sq->select('responsavel_id')
                    ->from('departamentos')
                    ->whereNotNull('responsavel_id');
            });
        })
        ->orderBy('name')
        ->get()
        ->map(function ($u) {
            $deptoNome = $u->departamento ? $u->departamento->nome : 'Departamento';

            return [
                'id' => $u->id,
                'nome' => $u->name,
                'departamento_nome' => $deptoNome,
                'label' => $u->name.' - Chefe de Departamento de '.$deptoNome,
            ];
        });
    }

    /**
     * Retorna os templates disponíveis para o usuário, priorizando os do seu gabinete e aplicando RBAC de visualização.
     */
    public function getTemplatesForUser(User $user)
    {
        $gabineteId = $user->departamento ? $user->departamento->gabinete_id : null;
        $isSecGeral = $this->isUserSecretariaGeral($user);

        return ModeloDocumento::where('ativo', true)
            ->where(function ($q) use ($gabineteId) {
                $q->whereNull('gabinete_id');
                if ($gabineteId) {
                    $q->orWhere('gabinete_id', $gabineteId);
                }
            })
            ->when(! $isSecGeral, function ($q) {
                $q->where(function ($sq) {
                    $sq->whereNull('codigo')
                        ->orWhere('codigo', '!=', 'ORDEM_DE_SERVICO_SEC_GERAL');
                });
            })
            ->with('especie')
            ->get();
    }

    public function processarTemplate(string $conteudoTemplate, ?DocumentoEntrada $docEntrada, User $user, array $dadosExtras = []): string
    {
        // Obter Responsável do Gabinete (Chefe do Gabinete / Secretário Geral)
        $responsavel = $user->departamento?->gabinete?->responsavel
            ?? $user->departamento?->gabinete?->superChefe
            ?? $user->departamento?->responsavel
            ?? $user;
        $nomeResponsavel = $responsavel->name;

        // Obter dados da instituição cadastrada
        try {
            $dadosInstituicao = \App\Models\DadosInstituicao::first() ?? new \App\Models\DadosInstituicao;
        } catch (\Exception $e) {
            $dadosInstituicao = new \App\Models\DadosInstituicao;
        }

        if (empty($dadosInstituicao->nome_oficial)) {
            $dadosInstituicao->nome_oficial = 'Governo Provincial';
        }
        if (empty($dadosInstituicao->sigla)) {
            $dadosInstituicao->sigla = 'GOV';
        }
        if (empty($dadosInstituicao->cidade)) {
            $dadosInstituicao->cidade = 'Sede';
        }
        if (empty($dadosInstituicao->cabecalho_linha1)) {
            $dadosInstituicao->cabecalho_linha1 = 'REPÚBLICA DE ANGOLA';
        }
        if (empty($dadosInstituicao->cabecalho_linha2)) {
            $dadosInstituicao->cabecalho_linha2 = mb_strtoupper($dadosInstituicao->nome_oficial);
        }

        $siglaGabinete = $user->departamento?->gabinete?->sigla
            ?? $user->departamento?->sigla
            ?? 'SEC.GER.GOV.PROV.HLA';

        $insigniaUrl = ! empty($dadosInstituicao->logo_url) ? $dadosInstituicao->logo_url : asset('images/insignia.png');
        $governoNome = ! empty($dadosInstituicao->cabecalho_linha2) ? $dadosInstituicao->cabecalho_linha2 : 'Governo Provincial da Huíla';
        $gabineteSecretariaNome = ($user->departamento && $user->departamento->gabinete)
            ? $user->departamento->gabinete->nome
            : ($user->departamento ? $user->departamento->nome : 'Secretaria Geral');

        $qrCodeDefault = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="70" height="70" viewBox="0 0 100 100"><rect width="100" height="100" fill="%23eee"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-size="10" fill="%23666">QR CODE</text></svg>';

        $dataExtensoFormatada = now()->translatedFormat('d \d\e F \d\e Y');

        $placeholders = [
            '{{DATA_ATUAL}}' => now()->format('d/m/Y'),
            '{{ DATA_ATUAL }}' => now()->format('d/m/Y'),
            '{{DATA_EXTENSO}}' => $dataExtensoFormatada,
            '{{ DATA_EXTENSO }}' => $dataExtensoFormatada,
            '{{ANO}}' => now()->format('Y'),
            '{{ ANO }}' => now()->format('Y'),
            '{{USUARIO_NOME}}' => $user->name,
            '{{ USUARIO_NOME }}' => $user->name,
            '{{RESPONSAVEL_NOME}}' => $nomeResponsavel,
            '{{ RESPONSAVEL_NOME }}' => $nomeResponsavel,
            '{{DEPARTAMENTO_NOME}}' => $user->departamento ? $user->departamento->nome : 'Departamento',
            '{{ DEPARTAMENTO_NOME }}' => $user->departamento ? $user->departamento->nome : 'Departamento',
            '{{GABINETE_NOME}}' => ($user->departamento && $user->departamento->gabinete) ? $user->departamento->gabinete->nome : '',
            '{{ GABINETE_NOME }}' => ($user->departamento && $user->departamento->gabinete) ? $user->departamento->gabinete->nome : '',
            '{{DEPARTAMENTO_SIGLA}}' => $user->departamento ? strtoupper(Str::slug($user->departamento->nome, '')) : 'DEP',
            '{{ DEPARTAMENTO_SIGLA }}' => $user->departamento ? strtoupper(Str::slug($user->departamento->nome, '')) : 'DEP',
            '{{INSTITUICAO_NOME}}' => $dadosInstituicao->nome_oficial,
            '{{ INSTITUICAO_NOME }}' => $dadosInstituicao->nome_oficial,
            '{{INSTITUICAO_CABECALHO_1}}' => $dadosInstituicao->cabecalho_linha1,
            '{{ INSTITUICAO_CABECALHO_1 }}' => $dadosInstituicao->cabecalho_linha1,
            '{{INSTITUICAO_CABECALHO_2}}' => $dadosInstituicao->cabecalho_linha2,
            '{{ INSTITUICAO_CABECALHO_2 }}' => $dadosInstituicao->cabecalho_linha2,
            '{{INSTITUICAO_CABECALHO_3}}' => $dadosInstituicao->cabecalho_linha3 ?? '',
            '{{ INSTITUICAO_CABECALHO_3 }}' => $dadosInstituicao->cabecalho_linha3 ?? '',
            '{{INSTITUICAO_LOCAL}}' => $dadosInstituicao->cidade,
            '{{ INSTITUICAO_LOCAL }}' => $dadosInstituicao->cidade,
        ];

        // Construir a linha da data institucional
        $gabineteNome = ($user->departamento && $user->departamento->gabinete) ? $user->departamento->gabinete->nome : ($user->departamento ? $user->departamento->nome : $dadosInstituicao->cabecalho_linha2);
        $linhaData = mb_strtoupper($gabineteNome).', em '.$dadosInstituicao->cidade.', aos '.$dataExtensoFormatada;

        $placeholders['{{RODAPE_INSTITUCIONAL_DATA}}'] = $linhaData;

        // Helper to trim and check
        $getVal = fn ($key, $default) => ! empty($dadosExtras[$key]) && trim($dadosExtras[$key]) !== '' ? trim($dadosExtras[$key]) : $default;

        // Recipient placeholders from dadosExtras
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

        // Resolvendo Data de Ausência dinâmica
        $rawDate = $getVal('data_inicio_ausencia', null);
        if ($rawDate) {
            try {
                $dataInicioFormatada = \Carbon\Carbon::parse($rawDate)->translatedFormat('d \d\e F \d\e Y');
            } catch (\Exception $e) {
                $dataInicioFormatada = $rawDate;
            }
        } else {
            $dataInicioFormatada = '26 de Maio de 2026';
        }

        // Resolvendo Substituto (Chefe de Departamento do Gabinete)
        $substitutoUserId = $getVal('substituto_user_id', null);
        $substitutoNome = 'Eduardo Chivangulula Gabriel';
        $substitutoDepto = 'Gestão do Orçamento e Contabilidade';

        if ($substitutoUserId) {
            $subUser = User::with('departamento')->find($substitutoUserId);
            if ($subUser) {
                $substitutoNome = $subUser->name;
                if ($subUser->departamento) {
                    $substitutoDepto = $subUser->departamento->nome;
                }
            }
        } else {
            if (! empty($dadosExtras['substituto_nome'])) {
                $substitutoNome = $dadosExtras['substituto_nome'];
            }
            if (! empty($dadosExtras['substituto_departamento'])) {
                $substitutoDepto = $dadosExtras['substituto_departamento'];
            }
        }

        $preambuloDefault = "Ausentando-me para cumprimento de missão de Serviço Oficial, a partir do dia {$dataInicioFormatada} e havendo necessidade de se assegurar o normal funcionamento da Secretaria Geral do Governo, enquanto durar a minha ausência;";
        $deliberacaoDefault = "O Senhor <strong>{$substitutoNome}</strong> - Chefe de Departamento de {$substitutoDepto} da Secretaria Geral do Governo Provincial, a responder pelos assuntos correntes da referida Secretaria.";

        // Merge extra data (user input fields) with upper and exact case variations
        foreach ($dadosExtras as $key => $value) {
            $placeholders['{{'.strtoupper($key).'}}'] = $value;
            $placeholders['{{ '.$key.' }}'] = $value;
            $placeholders['{{'.$key.'}}'] = $value;
            $placeholders['{{{ '.$key.' }}}'] = $value;
        }

        // Calcular número de ordem sequencial automático para o ano atual do servidor
        $especieOrdemId = DocumentoEspecie::where('nome', 'like', '%Ordem%')->value('id');
        $countExistentes = DocumentoInterno::where(function ($q) use ($especieOrdemId) {
            if ($especieOrdemId) {
                $q->where('documento_especie_id', $especieOrdemId);
            } else {
                $q->where('titulo', 'like', '%Ordem%');
            }
        })
        ->whereYear('created_at', now()->year)
        ->count();

        $numAutoCalculado = sprintf('%02d', 6 + $countExistentes);
        $numeroOrdemFinal = $getVal('numero_ordem', $numAutoCalculado);
        $anoAtualServidor = now()->format('Y');

        // Placeholders específicos formatados (sobrepõem entradas brutas se necessário)
        $placeholders['{{ qr_code_img_url }}'] = $getVal('qr_code_img_url', $qrCodeDefault);
        $placeholders['{{ insignia_nacional_url }}'] = $getVal('insignia_nacional_url', $insigniaUrl);
        $placeholders['{{ governo_provincial_nome }}'] = $getVal('governo_provincial_nome', 'Governo Provincial da Huíla');
        $placeholders['{{ gabinete_secretaria_nome }}'] = $getVal('gabinete_secretaria_nome', 'Secretaria Geral');
        $placeholders['{{ numero_ordem }}'] = $numeroOrdemFinal;
        $placeholders['{{NUMERO_ORDEM}}'] = $numeroOrdemFinal;
        $placeholders['{{ sigla_gabinete }}'] = $getVal('sigla_gabinete', $siglaGabinete);
        $placeholders['{{ ano_corrente }}'] = $anoAtualServidor;
        $placeholders['{{ANO_CORRENTE}}'] = $anoAtualServidor;
        
        $placeholders['{{ data_inicio_ausencia }}'] = $dataInicioFormatada;
        $placeholders['{{DATA_INICIO_AUSENCIA}}'] = $dataInicioFormatada;
        $placeholders['{{ substituto_nome }}'] = $substitutoNome;
        $placeholders['{{SUBSTITUTO_NOME}}'] = $substitutoNome;
        $placeholders['{{ substituto_departamento }}'] = $substitutoDepto;
        $placeholders['{{SUBSTITUTO_DEPARTAMENTO}}'] = $substitutoDepto;

        $placeholders['{{ preambulo_motivo }}'] = $getVal('preambulo_motivo', $preambuloDefault);
        $placeholders['{{PREAMBULO_MOTIVO}}'] = $placeholders['{{ preambulo_motivo }}'];
        $placeholders['{{ verbo_operativo }}'] = $getVal('verbo_operativo', 'INDICO:');
        $placeholders['{{ texto_deliberacao }}'] = $getVal('texto_deliberacao', $deliberacaoDefault);
        $placeholders['{{TEXTO_DELIBERACAO}}'] = $placeholders['{{ texto_deliberacao }}'];
        
        $placeholders['{{ localidade_data_extenso }}'] = $getVal('localidade_data_extenso', $dataExtensoFormatada);
        $placeholders['{{LOCALIDADE_DATA_EXTENSO}}'] = $placeholders['{{ localidade_data_extenso }}'];
        $placeholders['{{ cargo_signatario }}'] = $getVal('cargo_signatario', 'O Secretário Geral');
        $placeholders['{{ nome_signatario }}'] = $getVal('nome_signatario', $nomeResponsavel);
        $placeholders['{{{ endereco_rodape_html }}}'] = $getVal('endereco_rodape_html', 'Largo Gabriel Calof<br>telf: (+244)948956662<br>e-mail: govprovhuila@gmail.com<br>Lubango<br>ANGOLA');
        $placeholders['{{ endereco_rodape }}'] = $getVal('endereco_rodape', 'Largo Gabriel Calof, Lubango, ANGOLA');
        $placeholders['{{ portal_url }}'] = $getVal('portal_url', 'huila.gov.ao');

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
            'ORDEMDESERVICO', 'ORDEM' => 'OS',
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

    /**
     * Identifica o perfil de fluxo de trabalho do utilizador para documentos internos.
     */
    public function getUserWorkflowProfile(User $user): string
    {
        if ($user->isAdmin() || $user->isChefeGabinete() || $user->isSuperChefeGabinete()) {
            return 'gabinete';
        }

        try {
            if ($user->hasPermissionTo('gabinete.view_all')) {
                return 'gabinete';
            }
        } catch (\Throwable $e) {
        }

        if ($user->isChefeDepartamento()) {
            return 'chefe_departamento';
        }

        return 'tecnico';
    }

    /**
     * Retorna a aba padrão para o perfil.
     */
    public function getDefaultTabForProfile(string $profile): string
    {
        return match ($profile) {
            'gabinete' => 'homologacao',
            'chefe_departamento' => 'revisao',
            'tecnico' => 'meus_rascunhos',
            default => 'todos',
        };
    }

    /**
     * Aplica o filtro da aba ativa na query de documentos internos.
     */
    public function applyRoleTabFilter($query, string $tab, ?User $user, string $profile): void
    {
        if (! $user) {
            return;
        }

        switch ($profile) {
            case 'gabinete':
                if ($tab === 'homologacao') {
                    $query->whereIn('status', [
                        DocumentoStatus::EM_ANALISE,
                        DocumentoStatus::PENDENTE_TRATAMENTO,
                        DocumentoStatus::TRATADO,
                    ]);
                } elseif ($tab === 'assinados') {
                    $query->whereIn('status', [
                        DocumentoStatus::APROVADO,
                        DocumentoStatus::ASSINADO,
                        DocumentoStatus::FINALIZADO,
                    ]);
                }
                // 'todos' -> histórico global do gabinete (sem filtro extra de status)
                break;

            case 'chefe_departamento':
                if ($tab === 'revisao') {
                    $query->where('status', DocumentoStatus::EM_ANALISE);
                } elseif ($tab === 'elaboracao') {
                    $query->where('status', DocumentoStatus::RASCUNHO);
                } elseif ($tab === 'assinados') {
                    $query->whereIn('status', [
                        DocumentoStatus::APROVADO,
                        DocumentoStatus::ASSINADO,
                        DocumentoStatus::FINALIZADO,
                    ]);
                }
                // 'todos' -> histórico completo do departamento
                break;

            case 'tecnico':
                if ($tab === 'meus_rascunhos') {
                    $query->where('criado_por', $user->id)
                        ->where('status', DocumentoStatus::RASCUNHO);
                } elseif ($tab === 'em_revisao') {
                    $query->where('criado_por', $user->id)
                        ->where('status', DocumentoStatus::EM_ANALISE);
                } elseif ($tab === 'aprovados') {
                    $query->whereIn('status', [
                        DocumentoStatus::APROVADO,
                        DocumentoStatus::ASSINADO,
                        DocumentoStatus::FINALIZADO,
                    ]);
                }
                break;
        }
    }

    /**
     * Gera a estrutura de Underline Tabs com contagens dinâmicas para o utilizador.
     */
    public function getRoleWorkflowTabs(?User $user, Request $request): array
    {
        if (! $user) {
            return [];
        }

        $profile = $this->getUserWorkflowProfile($user);
        $activeTab = $request->input('tab') ?: $this->getDefaultTabForProfile($profile);

        $baseQuery = DocumentoInterno::accessibleBy($user);

        $tabsConfig = match ($profile) {
            'gabinete' => [
                ['key' => 'homologacao', 'label' => 'Para Homologação', 'icon' => 'far fa-clock', 'badge_type' => 'warning'],
                ['key' => 'assinados', 'label' => 'Assinados / Homologados', 'icon' => 'far fa-check-circle', 'badge_type' => 'info'],
                ['key' => 'todos', 'label' => 'Todos do Gabinete', 'icon' => 'fas fa-layer-group', 'badge_type' => 'neutral'],
            ],
            'chefe_departamento' => [
                ['key' => 'revisao', 'label' => 'Aguardando Revisão', 'icon' => 'far fa-clock', 'badge_type' => 'warning'],
                ['key' => 'elaboracao', 'label' => 'Em Elaboração', 'icon' => 'far fa-edit', 'badge_type' => 'info'],
                ['key' => 'assinados', 'label' => 'Assinados / Expedidos', 'icon' => 'far fa-check-circle', 'badge_type' => 'neutral'],
                ['key' => 'todos', 'label' => 'Todos do Departamento', 'icon' => 'fas fa-building', 'badge_type' => 'neutral'],
            ],
            'tecnico' => [
                ['key' => 'meus_rascunhos', 'label' => 'Meus Rascunhos', 'icon' => 'far fa-edit', 'badge_type' => 'warning'],
                ['key' => 'em_revisao', 'label' => 'Em Revisão', 'icon' => 'far fa-clock', 'badge_type' => 'info'],
                ['key' => 'aprovados', 'label' => 'Aprovados do Departamento', 'icon' => 'far fa-check-circle', 'badge_type' => 'neutral'],
            ],
            default => [
                ['key' => 'todos', 'label' => 'Todos os Documentos', 'icon' => 'fas fa-layer-group', 'badge_type' => 'neutral'],
            ],
        };

        $tabs = [];
        foreach ($tabsConfig as $cfg) {
            $q = clone $baseQuery;
            $this->applyRoleTabFilter($q, $cfg['key'], $user, $profile);
            $count = $q->count();

            $badgeClass = match ($cfg['badge_type']) {
                'warning' => $count > 0 ? 'tab-badge-warning' : 'tab-badge-neutral opacity-50',
                'info' => $count > 0 ? 'tab-badge-info' : 'tab-badge-neutral opacity-50',
                default => 'tab-badge-neutral'.($count == 0 ? ' opacity-50' : ''),
            };

            $tabs[] = [
                'key' => $cfg['key'],
                'label' => $cfg['label'],
                'icon' => $cfg['icon'],
                'count' => $count,
                'badge_class' => $badgeClass,
                'is_active' => ($cfg['key'] === $activeTab),
            ];
        }

        return $tabs;
    }
}
