<?php

namespace App\Services;

use App\Enums\DocumentoStatus;
use App\Models\Departamento;
use App\Models\Gabinete;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\DocumentoVersao;
use App\Models\ModeloDocumento;
use App\Models\User;
use App\Support\CamposVinculados;
use App\Support\SeriesNumeracao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentoInternoService
{
    /**
     * Classe do <span> que marca a referência no corpo. É uma classe, e não um
     * data-*, porque o HtmlSanitizer só preserva class/style.
     */
    public const CLASSE_NOSSA_REFERENCIA = 'ref-nossa-referencia';

    /** Marcador do número (Nº da Ordem de Serviço, Nº no quadro da Informação), preenchido ao gravar. */
    public const CLASSE_NUMERO_ORDEM = 'ref-numero-ordem';

    /** Marcador da referência sem a identificação da série, para títulos ("INFORMAÇÃO Nº …"). */
    public const CLASSE_REFERENCIA_TITULO = 'ref-referencia-titulo';

    /**
     * Verifica se o utilizador pertence à Secretaria Geral ou possui privilégios de Admin.
     */
    public function isUserSecretariaGeral(User $user): bool
    {
        if ($user->isAdmin() || $user->hasRole('admin') || $user->hasRole('Admin')) {
            return true;
        }

        $validSiglas = ['SEC_GERAL', 'SEC.GERAL', 'SEC_GER', 'SG', 'SEC.GER.GOV.PROV.HLA'];

        $dep = $user->departamento;
        if ($dep) {
            $depSigla = strtoupper($dep->sigla ?? '');
            $depNome = mb_strtoupper($dep->nome ?? '');

            if (in_array($depSigla, $validSiglas)) {
                return true;
            }

            if (str_contains($depNome, 'SECRETARIA GERAL') || str_contains($depNome, 'SECRETÁRIA GERAL')) {
                return true;
            }
        }

        // Gabinete do departamento ou, sem departamento, o que o utilizador chefia
        // (o Secretário Geral não pertence a nenhum departamento).
        $gab = $this->gabineteDoUtilizador($user);
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
     * Gabinete do utilizador: o do seu departamento ou, sem departamento, o que chefia.
     */
    public function gabineteDoUtilizador(User $user): ?Gabinete
    {
        return $user->departamento?->gabinete
            ?? $user->gabineteGerenciado
            ?? $user->gabineteSuperGerenciado;
    }

    /**
     * Retorna a lista dos Chefes de Departamento do gabinete do utilizador.
     */
    public function getChefesDepartamentoForUser(User $user)
    {
        $gabineteId = $this->gabineteDoUtilizador($user)?->id;

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
        $gabineteId = $this->gabineteDoUtilizador($user)?->id;
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
                        ->orWhereNotIn('codigo', ModeloDocumento::CODIGOS_EXCLUSIVOS_SEC_GERAL);
                });
            })
            ->with('especie')
            ->get();
    }

    /**
     * @param  Departamento|Gabinete|null  $emissor  quem emite: um departamento, o próprio gabinete
     *                                              (sem departamento) ou, se null, o departamento do utilizador
     * @param  DocumentoEspecie|null  $especie  espécie do documento: a referência provisória sai no
     *                                          formato da série certa (ex.: "NOTA ___/…")
     */
    public function processarTemplate(string $conteudoTemplate, ?DocumentoEntrada $docEntrada, User $user, array $dadosExtras = [], Departamento|Gabinete|null $emissor = null, ?DocumentoEspecie $especie = null): string
    {
        if ($emissor instanceof Gabinete) {
            [$dep, $gab] = [null, $emissor];
        } else {
            $dep = $emissor ?? $user->departamento;
            $gab = $dep?->gabinete ?? ($dep ? null : ($user->gabineteGerenciado ?? $user->gabineteSuperGerenciado));
        }
        $numeracao = app(NumeracaoDocumentoService::class);

        // Obter Responsável do Gabinete (Chefe do Gabinete / Secretário Geral)
        $responsavel = $gab?->responsavel
            ?? $gab?->superChefe
            ?? $dep?->responsavel
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

        $siglaGabinete = $gab?->sigla
            ?? $dep?->sigla
            ?? 'SEC.GER.GOV.PROV.HLA';

        $insigniaUrl = ! empty($dadosInstituicao->logo_url) ? $dadosInstituicao->logo_url : asset('images/insignia.png');
        $governoNome = ! empty($dadosInstituicao->cabecalho_linha2) ? $dadosInstituicao->cabecalho_linha2 : 'Governo Provincial da Huíla';
        $gabineteSecretariaNome = $gab?->nome ?? ($dep ? $dep->nome : 'Secretaria Geral');

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
            '{{DEPARTAMENTO_NOME}}' => $dep ? $dep->nome : 'Departamento',
            '{{ DEPARTAMENTO_NOME }}' => $dep ? $dep->nome : 'Departamento',
            '{{GABINETE_NOME}}' => $gab?->nome ?? '',
            '{{ GABINETE_NOME }}' => $gab?->nome ?? '',
            // Emitido pelo gabinete: a sigla é a do gabinete.
            '{{DEPARTAMENTO_SIGLA}}' => $numeracao->siglaEmissor($dep, $gab),
            '{{ DEPARTAMENTO_SIGLA }}' => $numeracao->siglaEmissor($dep, $gab),
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

        // Responsável da instituição (ex.: o Governador). Diferente de RESPONSAVEL_NOME,
        // que é o do gabinete emissor. Sem registo fica um aviso visível, como no
        // CHEFE_DEPARTAMENTO_NOME: um nome em branco num documento passaria despercebido.
        $responsavelInstituicao = e($dadosInstituicao->responsavel_nome ?: '[Responsável da instituição não definido]');
        $cargoResponsavelInstituicao = e($dadosInstituicao->responsavel_cargo ?: '[Cargo do responsável não definido]');
        $placeholders['{{INSTITUICAO_RESPONSAVEL_NOME}}'] = $responsavelInstituicao;
        $placeholders['{{ INSTITUICAO_RESPONSAVEL_NOME }}'] = $responsavelInstituicao;
        $placeholders['{{INSTITUICAO_RESPONSAVEL_CARGO}}'] = $cargoResponsavelInstituicao;
        $placeholders['{{ INSTITUICAO_RESPONSAVEL_CARGO }}'] = $cargoResponsavelInstituicao;

        // Construir a linha da data institucional
        $gabineteNome = $gab?->nome ?? ($dep ? $dep->nome : $dadosInstituicao->cabecalho_linha2);
        $linhaData = mb_strtoupper($gabineteNome).', em '.$dadosInstituicao->cidade.', aos '.$dataExtensoFormatada;

        $placeholders['{{RODAPE_INSTITUCIONAL_DATA}}'] = $linhaData;

        // Ofício: referência (marcador substituído pela definitiva ao gravar) e datação.
        $placeholders['{{GABINETE_CODIGO_OFICIOS}}'] = (string) ($gab?->codigo_oficios ?? '');
        $referenciaProvisoria = $this->referenciaProvisoria($dep, $gab, $especie);
        $placeholders['{{NOSSA_REFERENCIA}}'] = '<span class="'.self::CLASSE_NOSSA_REFERENCIA.'">'
            .e($referenciaProvisoria).'</span>';
        $placeholders['{{REFERENCIA_TITULO}}'] = '<span class="'.self::CLASSE_REFERENCIA_TITULO.'">'
            .e(SeriesNumeracao::semIdentificacao($referenciaProvisoria)).'</span>';

        // Nota: assina o Chefe de Departamento designado para o departamento emissor.
        $chefeDepartamento = $dep?->chefeDesignado()?->name ?? '[Chefe de Departamento não definido]';
        $placeholders['{{CHEFE_DEPARTAMENTO_NOME}}'] = e($chefeDepartamento);
        $placeholders['{{ CHEFE_DEPARTAMENTO_NOME }}'] = e($chefeDepartamento);
        $placeholders['{{GABINETE_INSTITUICAO}}'] = mb_strtoupper(
            ($gab?->nome ? $gab->nome.' do ' : '').$dadosInstituicao->nome_oficial
        );
        // Nota: a datação começa pelo departamento que a emite
        // ("DEPARTAMENTO DE … DA SECRETARIA GERAL DO GOVERNO PROVINCIAL DA HUÍLA").
        $placeholders['{{DEPARTAMENTO_GABINETE_INSTITUICAO}}'] = $dep
            ? mb_strtoupper($dep->nome.($gab?->nome ? ' '.$this->preposicaoPara($gab->nome).' ' : ' do ')
                .($gab?->nome ? $gab->nome.' do ' : '').$dadosInstituicao->nome_oficial)
            : $placeholders['{{GABINETE_INSTITUICAO}}'];
        $placeholders['{{SUA_REFERENCIA}}'] = $docEntrada
            ? ($docEntrada->classificacao_ref_numero ?: $docEntrada->numero_sequencial.'/'.$docEntrada->ano_referencia)
            : '';
        $placeholders['{{SUA_COMUNICACAO}}'] = $docEntrada?->data_documento?->format('d/m/Y') ?? '';

        // Helper to trim and check
        $getVal = fn ($key, $default) => ! empty($dadosExtras[$key]) && trim($dadosExtras[$key]) !== '' ? trim($dadosExtras[$key]) : $default;

        // Assunto e destinatário: ver CamposVinculados (aplicados depois do merge dos extras).

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

        // Ordem de Serviço: o número vem da série do gabinete (OS:{código}), reservado ao
        // gravar; aqui fica um marcador (substituído em aplicarReferenciaDefinitiva).
        $numeroOrdemFinal = '<span class="'.self::CLASSE_NUMERO_ORDEM.'">__</span>';
        $anoAtualServidor = (string) NumeracaoDocumentoService::anoCorrente();
        $codigoOrdemServico = $numeracao->codigoOficios($gab) ?? $numeracao->siglaEmissor($dep, $gab);

        // Assunto e destinatário em marcadores sincronizáveis. Depois do merge acima,
        // que de outro modo os sobrepunha com o valor cru (ou vazio).
        $placeholders = array_merge($placeholders, CamposVinculados::placeholders(
            CamposVinculados::valores($dadosExtras, $dadosInstituicao->cidade)
        ));

        // Placeholders específicos formatados (sobrepõem entradas brutas se necessário)
        $placeholders['{{ qr_code_img_url }}'] = $getVal('qr_code_img_url', $qrCodeDefault);
        $placeholders['{{ insignia_nacional_url }}'] = $getVal('insignia_nacional_url', $insigniaUrl);
        $placeholders['{{ governo_provincial_nome }}'] = $getVal('governo_provincial_nome', 'Governo Provincial da Huíla');
        $placeholders['{{ gabinete_secretaria_nome }}'] = $getVal('gabinete_secretaria_nome', 'Secretaria Geral');
        $placeholders['{{ numero_ordem }}'] = $numeroOrdemFinal;
        $placeholders['{{NUMERO_ORDEM}}'] = $numeroOrdemFinal;
        $placeholders['{{NUMERO_DOCUMENTO}}'] = $numeroOrdemFinal;
        // Informação/Parecer: "Proc." é opcional; vazio fica a linha para preencher à mão.
        $placeholders['{{ numero_processo }}'] = e($getVal('numero_processo', '________'));
        $placeholders['{{ sigla_gabinete }}'] = $getVal('sigla_gabinete', $siglaGabinete);
        $placeholders['{{CODIGO_ORDEM_SERVICO}}'] = $codigoOrdemServico;
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

    /**
     * "da" ou "do" antes do nome de uma unidade, pelo género da primeira palavra
     * (Secretaria/Direcção → "da"; Gabinete/Departamento → "do").
     */
    private function preposicaoPara(string $nome): string
    {
        $primeira = mb_strtolower(strtok(trim($nome), ' ') ?: '');

        return preg_match('/(a|ção|cção|dade|agem)$/u', $primeira) ? 'da' : 'do';
    }

    /**
     * Referência com o número em branco. Com a espécie, usa a série real (ofício, nota, OS…);
     * sem ela, mantém o formato do ofício.
     */
    private function referenciaProvisoria(?Departamento $dep, ?Gabinete $gab, ?DocumentoEspecie $especie): string
    {
        if (! $especie || ! $gab) {
            return app(NumeracaoDocumentoService::class)->referenciaProvisoria($dep, $gab);
        }

        // Documento fictício (não gravado), só para determinar a série.
        $doc = new DocumentoInterno;
        $doc->departamento_id = $dep?->id;
        $doc->gabinete_id = $gab->id;
        $doc->setRelation('departamento', $dep);
        $doc->setRelation('gabinete', $gab);
        $doc->setRelation('especie', $especie);

        return SeriesNumeracao::paraDocumento($doc)
            ->formatarProvisoria(NumeracaoDocumentoService::anoCorrente());
    }

    /**
     * Reserva a referência do documento (ver NumeracaoDocumentoService).
     * Chamar dentro da mesma transacção em que o documento é gravado.
     */
    public function gerarNumeroReferencia(DocumentoInterno $doc): string
    {
        return app(NumeracaoDocumentoService::class)->gerar($doc);
    }

    /**
     * Como gerarNumeroReferencia, mas devolve também o número (ex.: o nº da Ordem de Serviço).
     *
     * @return array{referencia: string, numero: int}
     */
    public function reservarNumero(DocumentoInterno $doc): array
    {
        return app(NumeracaoDocumentoService::class)->reservarParaDocumento($doc);
    }

    /**
     * Antes de gravar: repõe o Assunto e o destinatário submetidos nos marcadores do
     * corpo (vale mesmo sem JavaScript). Os vazios ficam gravados, para reaparecerem se o
     * campo for preenchido mais tarde; só a apresentação os esconde
     * (DocumentoInterno::conteudoParaApresentacao).
     */
    public function prepararCamposVinculados(string $html, array $entrada): string
    {
        $cidade = rescue(fn () => \App\Models\DadosInstituicao::first()?->cidade, null, false) ?: 'Sede';

        return CamposVinculados::sincronizar($html, CamposVinculados::valores($entrada, $cidade));
    }

    /**
     * Substitui o texto do marcador {{NOSSA_REFERENCIA}} pela referência definitiva,
     * para que o corpo impresso coincida com numero_referencia.
     */
    public function aplicarReferenciaDefinitiva(string $html, string $referencia, ?int $numero = null): string
    {
        $html = $this->preencherMarcador($html, self::CLASSE_NOSSA_REFERENCIA, $referencia);
        $html = $this->preencherMarcador($html, self::CLASSE_REFERENCIA_TITULO, SeriesNumeracao::semIdentificacao($referencia));

        // Nº no título/quadro: "Nº 08" nas Ordens de Serviço, "Nº 12" na Informação/Parecer.
        return $numero !== null
            ? $this->preencherMarcador($html, self::CLASSE_NUMERO_ORDEM, SeriesNumeracao::numeroParaTitulo($referencia, $numero))
            : $html;
    }

    private function preencherMarcador(string $html, string $classe, string $texto): string
    {
        $classe = preg_quote($classe, '/');

        return preg_replace_callback(
            '/(<span\b[^>]*\bclass\s*=\s*["\'][^"\']*\b'.$classe.'\b[^"\']*["\'][^>]*>)(.*?)(<\/span>)/is',
            fn ($m) => $m[1].e($texto).$m[3],
            $html
        ) ?? $html;
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
     * Garante que o conteúdo actual existe no histórico (versão inicial, salvaguardas).
     * Sem versão para o número actual cria-a com esse número; se existir mas o conteúdo
     * divergir (ex.: autosave), cria uma nova versão patch. Não faz nada se já coincidir.
     */
    public function garantirVersaoDoConteudoActual(DocumentoInterno $documento, User $usuario, string $changeLog): void
    {
        $ultima = DocumentoVersao::where('documento_interno_id', $documento->id)->orderByDesc('versao')->first();

        if ($ultima && $ultima->conteudo_final === $documento->conteudo_final && $ultima->titulo === $documento->titulo) {
            return;
        }

        if (! $ultima || (int) $ultima->versao < (int) ($documento->versao_atual ?? 1)) {
            DocumentoVersao::create([
                'documento_interno_id' => $documento->id,
                'versao' => $documento->versao_atual ?? 1,
                'major' => $documento->versao_major ?? 0,
                'minor' => $documento->versao_minor ?? 0,
                'patch' => $documento->versao_patch ?? 0,
                'titulo' => $documento->titulo,
                'conteudo_final' => $documento->conteudo_final,
                'criado_por' => $usuario->id,
                'change_log' => $changeLog,
            ]);

            return;
        }

        $this->updateWithVersioning($documento, [
            'titulo' => $documento->titulo,
            'conteudo_final' => $documento->conteudo_final,
        ], $usuario, 'patch', $changeLog);
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
     * Consulta da listagem de documentos internos: visibilidade, separador activo e
     * filtros explícitos (que se somam ao separador). Partilhada pela listagem e
     * pelos cartões do painel, para que o número do cartão seja o da lista.
     */
    public function queryListagem(Request $request, User $user)
    {
        $profile = $this->getUserWorkflowProfile($user);
        $activeTab = $request->input('tab') ?: $this->getDefaultTabForProfile($profile);

        $query = DocumentoInterno::accessibleBy($user)
            ->with(['especie', 'autor', 'departamento', 'gabinete'])
            ->withExists(['favoritadoPor as is_favorited' => function ($q) use ($user) {
                $q->where('user_id', $user->id);
            }]);

        $this->applyRoleTabFilter($query, $activeTab, $user, $profile);

        // Filtro por Texto (Título, Referência ou Conteúdo)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('titulo', 'like', "%{$search}%")
                    ->orWhere('numero_referencia', 'like', "%{$search}%")
                    ->orWhere('conteudo_final', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('favoritos')) {
            $query->whereHas('favoritadoPor', fn ($q) => $q->where('user_id', $user->id));
        }
        if ($request->filled('especie_id')) {
            $query->where('documento_especie_id', $request->especie_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('departamento_id')) {
            $query->where('departamento_id', $request->departamento_id);
        }
        if ($request->filled('data_inicio')) {
            $query->whereDate('created_at', '>=', $request->data_inicio);
        }
        if ($request->filled('data_fim')) {
            $query->whereDate('created_at', '<=', $request->data_fim);
        }
        if ($request->filled('autor_id')) {
            $query->where('criado_por', $request->autor_id);
        }

        // Ordenação dinâmica, com whitelist de colunas
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('order') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['numero_referencia', 'titulo', 'created_at', 'updated_at', 'status'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderByDesc('created_at');
        }

        return $query;
    }

    /**
     * Total de linhas que a listagem mostraria com estes parâmetros (mesma
     * contagem do paginador).
     */
    public function contarListagem(array $parametros, User $user): int
    {
        $request = Request::create(route('documentos-internos.index'), 'GET', $parametros);

        return $this->queryListagem($request, $user)->toBase()->getCountForPagination();
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
