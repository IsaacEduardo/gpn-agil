# Plano de Correção — Módulo de Entrada de Documentos Externos

> Briefing de execução para sessões de agente de código neste repositório.
> Origem: auditoria do módulo `documentos-entradas` (fluxo, regra de perfis,
> usabilidade e automação). Ordem das fases é intencional — as fases 1 e 2 são as
> que alteram comportamento real do sistema.

## Índice

- [A. Contrato de execução](#a-contrato--colar-no-início-de-cada-sessão)
- [B. Fase 1 — Regra de perfis (P1, P2, P3, P4)](#b-fase-1--fechar-as-quebras-da-regra-de-perfis)
- [C. Fase 2 — Fluxo autónomo (F1, F2)](#c-fase-2--fazer-o-fluxo-andar-sozinho)
- [D. Fase 3 — Fonte única de autorização (P5, P6)](#d-fase-3--uma-só-fonte-de-autorização-e-congelar-o-documento-tramitado)
- [E. Fase 4 — Integridade do registo (F4, F5, F6, F9)](#e-fase-4--integridade-do-registo-no-balcão)
- [F. Fase 5 — Escala (F7, F8)](#f-fase-5--escala)
- [G. Fase 6 — Automação](#g-fase-6--automação)

---

## A. Contrato — colar no início de cada sessão

```
Projeto: GPN-AGIL (Laravel). Módulo: entrada de documentos externos.
Branch de trabalho: uma por fase, a partir de feat/gestao-terrenos.

REGRAS DE EXECUÇÃO
1. Os números de linha do briefing vêm de uma análise anterior e podem ter
   derivado. Localiza sempre o código pelo nome do método/símbolo, não pela
   linha. Se o que encontras não bate certo com o que o briefing descreve,
   PARA e reporta a divergência antes de editar.
2. Lê o ficheiro completo antes de o editar.
3. Não criares ficheiros novos a não ser os estritamente necessários
   (migrations, testes, uma classe extraída). Nada de ficheiros de documentação.
4. Cada correção tem de ficar coberta por um teste que FALHA antes e PASSA
   depois. Mostra-me a saída das duas execuções.
5. Strings visíveis ao utilizador em português de Portugal, no registo já
   usado no módulo.
6. No fim de cada fase: php artisan test  (suite completa, não só o filtro).
   Se algum teste pré-existente passar a falhar, corrige a causa — não o teste.
7. Commits pequenos, um por defeito corrigido, mensagem a citar o código do
   defeito (ex.: "fix(P2): exige autorização nas rotas de protocolo").

FORA DE ÂMBITO NESTA FASE (não tocar)
- Módulo de documentos internos, requisições, viaturas, EDMS.
- Refactor das blades gigantes (index/show) — só as alterações cirúrgicas pedidas.
- Alterar o conjunto de estados do DocumentoStatus ou a numeração sequencial.
- Renomear rotas, métodos públicos ou colunas existentes.

INVARIANTE DO MÓDULO (é isto que estamos a proteger)
Um utilizador só pode AGIR sobre um documento na medida em que o seu perfil o
autoriza NAQUELE gabinete/departamento. "Conseguir ver" (canViewDocument, que é
propositadamente largo) NUNCA é fundamento suficiente para despachar, dar visto,
delegar ou alterar. Toda a decisão de autorização vive na Policy ou no
DocumentoPermissionService — nunca duplicada num controller ou numa blade.
```

---

## B. Fase 1 — Fechar as quebras da regra de perfis

```
Corrige quatro falhas de autorização no módulo de entrada de documentos externos.
Trabalha-as por esta ordem e com um commit cada.

P2 — DocumentoEntradaProtocoloController não tem uma única verificação de
autorização. Os métodos protocolo(), protocoloPdf(), protocoloEtiqueta() e
marcarImpresso() são alcançáveis por qualquer utilizador autenticado, que assim
enumera IDs e lê assunto, procedência, departamento e autor de documentos de
outros gabinetes.
→ Aplica $this->authorize('view', $documento) nos três primeiros e
  $this->authorize('update', $documento) em marcarImpresso (marcar como impresso
  é uma escrita). A policy 'view' já delega em canViewDocument.
→ Testes novos em tests/Feature/IdorProtectionTest.php: utilizador do
  Departamento B recebe 403 nas quatro rotas de um documento do Departamento A,
  e 200 nas do seu próprio.

P1 — DocumentoEntradaController::quickAction protege-se apenas com
canViewDocument + perfil do utilizador, e depois deixa o ramo 'gabinete' emitir
despacho, escrever visto_gabinete=aprovado e sincronizar departamentosDestino,
e o ramo 'chefe_departamento' criar tarefa para qualquer exists:users,id e
escrever visto_departamento=aprovado. O endpoint despachar() faz a verificação
correta com canDespachar() e o DocumentoEntradaTarefaController::store valida o
destinatário — o quickAction é o caminho paralelo que não valida nada.
→ Ramo 'gabinete': exige canDespachar($actor, $documento); 403 caso contrário.
→ Ramo 'chefe_departamento': exige canManageTasks($actor, $documento) E valida
  que o utilizador destino pertence ao departamento/gabinete, com a MESMA regra
  do TarefaController. Não dupliques essa regra: extrai-a do TarefaController
  para um método reutilizável (sugestão: DocumentoEntradaService::
  assertDestinatarioTarefaValido(DocumentoEntrada, User $destino, User $actor))
  e chama-a nos dois sítios. O TarefaController tem de continuar a passar nos
  testes existentes.
→ Ramo 'tecnico' já está correto (filtra por assigned_to_user_id); não mexer.
→ Testes: responsável do Gabinete A com visibilidade sobre documento do
  Gabinete B (dá-lhe visibilidade por histórico de encaminhamento) recebe 403 no
  quickAction; chefe do Dep. A não consegue atribuir tarefa a utilizador do Dep. B.

P3 — despachar() e quickAction validam os departamentos de destino só com
exists:departamentos,id, e as views recebem a lista global de departamentos
(previewAjax faz Departamento::orderBy('nome')->get(); show e modal-despacho usam
o cache 'departamentos_list'). Despachar para departamento de outro gabinete
concede-lhe visibilidade via departamentosDestino. Para saída inter-gabinete já
existe sendToGabinete.
→ Restringe os destinos aos departamentos do gabinete do documento, no servidor
  (regra de validação, Rule::in ou exists com where) E na lista passada às views.
→ Teste: despachar para departamento de outro gabinete devolve 422.

P4 — index() nunca chama authorize('viewAny'), pelo que a permissão
'documentos_entrada.listar' declarada na policy não é aplicada.
→ ANTES de alterar: verifica nos seeders/migrations de permissões que
  'documentos_entrada.listar' está atribuída aos quatro perfis (gabinete,
  expediente, chefe de departamento, técnico). Se NÃO estiver, isto trancaria a
  listagem a toda a gente — nesse caso corrige primeiro a atribuição da
  permissão e diz-me o que encontraste, antes de adicionar o authorize.
→ Só depois: $this->authorize('viewAny', DocumentoEntrada::class) em index().
→ Teste: um utilizador de cada perfil abre a listagem com 200.
```

---

## C. Fase 2 — Fazer o fluxo andar sozinho

```
O workflow de entrada só avança se alguém se lembrar de abrir a listagem.
Duas causas, e uma armadilha a evitar.

F2 — create.blade.php promete "será encaminhado automaticamente para a caixa de
entrada do departamento selecionado" e o comentário do store() afirma que o
Service trata do "encaminhamento inicial". Nenhuma das duas coisas acontece:
DocumentoEntradaService::processCreation grava departamento_id, protocolo, tags e
OCR, e mais nada. Ninguém é notificado de um documento novo.

  ARMADILHA — NÃO resolvas isto criando um DocumentoEncaminhamento inicial com
  recebido_em a null. Vários pontos do módulo tratam "encaminhamento por receber"
  como bloqueio: forwardDocument() lança RuntimeException, o endpoint encaminhar
  recusa, saidaGabinete recusa e o can_forward da listagem desliga. Um
  encaminhamento inicial pendente congelava o documento no arranque.

→ Resolve por notificação: no fim de processCreation (depois do commit da
  transação), notifica o chefe do departamento de destino e o responsável do
  gabinete desse departamento, reutilizando o padrão já existente
  (SimpleBroadcastNotification com tipo próprio, ex. 'documento_registado', ou
  uma Notification nova ao estilo de DocumentoEncaminhadoDepartamento). Nunca
  notifiques o próprio autor do registo.
→ A notificação não pode fazer falhar o registo: envia fora da transação e
  protege com try/catch + log, como já se faz no audit().
→ Corrige o texto de create.blade.php e o comentário do store() para descreverem
  o que o sistema faz de facto.
→ Teste: Notification::fake(); registar documento notifica o chefe do
  departamento de destino e não notifica o autor.

F1 — CheckSlaCommand filtra whereIn('status', ['registrado','recebido']), mas o
registo nasce em 'pendente_tratamento'. Resultado: a fase em que o documento
fica mais tempo parado — à espera de despacho no gabinete — nunca gera alerta
nem escalonamento. 'encaminhado' (enviado e nunca recebido) também está fora.
O SlaAutomationTest só cria documentos 'registrado', por isso o buraco passa.
→ Inclui 'pendente_tratamento' e 'encaminhado' no filtro.
→ Para 'pendente_tratamento' o destinatário do alerta não é o chefe do
  departamento — é quem pode despachar (responsável do gabinete). Ajusta o
  encaminhamento do alerta em função do estado, mantendo a idempotência que já
  existe (sla_nivel_notificado) e o escalonamento único (sla_escalado_em).
→ Documento 'encaminhado' sem recebido_em há N dias: alerta ao chefe do
  departamento de DESTINO ("documento à sua espera, por receber"). Usa a mesma
  mecânica de idempotência; não inventes colunas novas se sla_nivel_notificado
  chegar.
→ Em DocumentoEntrada::getSlaStatusAttribute, a lista de exclusão inclui
  'cancelado', que não existe no enum DocumentoStatus. Alinha-a com o enum.
→ Testes em SlaAutomationTest: um caso por estado novo, mais um que prova que a
  segunda execução do comando não repete a notificação.
```

---

## D. Fase 3 — Uma só fonte de autorização e congelar o documento tramitado

```
P5 — DocumentoEntradaController::show calcula $canVisto, $canVistoGabinete e
$canAssignTask e passa-os à view; show.blade.php ignora-os e recalcula tudo em
blocos @php. As duas versões já divergiram: a da blade para canVistoGabinete é
apenas "sou responsável de algum gabinete", tendo perdido a verificação de que é
o gabinete DO DOCUMENTO. E testa role->name === 'chefe-departamento' (hífen),
enquanto DocumentoPermissionService::isChefeDepartamento aceita também
'chefe_departamento' — quem tiver a variante com underscore não vê botão nenhum
mas passa nas verificações do servidor.
→ Apaga TODOS os blocos @php de autorização de show.blade.php e usa as variáveis
  que o controller já envia, ou @can com as abilities da policy.
→ Qualquer teste de nome de role passa a ser feito por
  DocumentoPermissionService::isChefeDepartamento. Nenhuma comparação literal a
  'chefe-departamento' fora dessa classe.
→ Guarda os botões de ação com @can: "Editar" em show.blade.php e na listagem
  com @can('update', $doc), "Eliminar" com @can('delete', $doc). Hoje aparecem a
  quem só tem visibilidade por histórico e devolvem 403 ao clique.
→ Teste de smoke: um técnico com visibilidade apenas por histórico abre o show
  com 200 e a resposta NÃO contém a rota de edição nem a de eliminação.

P6 — DocumentoEntradaPolicy::update só bloqueia se arquivado. Qualquer membro do
departamento atual altera assunto, procedência e departamento_id de um documento
já despachado e encaminhado — e updateDocument grava departamento_id
diretamente, movendo o documento sem criar encaminhamento nem deixar rasto de
tramitação.
→ A policy passa a negar update depois de o documento ter sido despachado
  (data_despacho preenchida) ou de ter encaminhamentos, exceto para admin.
  Devolve Response::deny com mensagem explícita, ao estilo do create().
→ departamento_id deixa de ser alterável pelo formulário de edição: retira-o das
  regras de validação do update() e do payload de updateDocument; no
  edit.blade.php mostra-o em leitura com a indicação de que a mudança de setor se
  faz por encaminhamento.
→ Testes: update de documento despachado devolve 403; POST de update com
  departamento_id diferente não altera a coluna.
```

---

## E. Fase 4 — Integridade do registo no balcão

```
Todas no ponto de entrada (formulário + StoreDocumentoEntradaRequest + processCreation).

F6 — classificacao_especie é required no HTML e nullable na validação. A espécie
é a chave da tabela de retenção (RetentionSchedule). → required no FormRequest.

F5 — data_entrada é sempre now(), sem campo no formulário. Correspondência
recebida em atraso fica com data errada e contamina o SLA.
→ Campo no formulário, nullable|date|before_or_equal:today, com hoje por defeito;
  processCreation usa o valor recebido em vez de now(). O registo de uma data
  retroativa fica visível na auditoria (o trait Auditable já regista).

F4 — nada impede registar duas vezes o mesmo ofício.
→ Aviso, não bloqueio: se já existir documento com a mesma procedencia_id +
  classificacao_ref_numero + data_documento, devolve o formulário com um aviso e
  o link para o registo existente, e exige confirmação explícita para prosseguir.
  O balcão tem de continuar a poder registar uma segunda via legítima.

Limites de ficheiro — 'arquivo' aceita 2 MB e 'anexos' 5 MB. O formulário de
registo já nem expõe 'arquivo' (só anexos[]), mas o de edição expõe, pelo que
digitalizar A4 a 300 dpi só falha ao editar.
→ Um limite único e coerente para os dois campos, em store e update, e a indicação
  do limite no componente de upload.

F9 — só o autor pode cancelar um encaminhamento
(DocumentoEntradaEncaminhamentoController::cancelar). Se essa pessoa estiver
ausente, o documento fica preso: não se cria novo encaminhamento enquanto houver
um pendente.
→ Nova ability na policy (ex. cancelarEncaminhamento) que permite ao autor, ao
  admin, ao chefe do departamento de origem e ao responsável do gabinete.
  O controller passa a usar $this->authorize(...) em vez da comparação inline.
→ Testes: chefe do departamento de origem cancela; técnico sem relação recebe 403.
```

---

## F. Fase 5 — Escala

```
F7 — searchJson e getFilteredDocumentsQuery fazem LIKE '%termo%' sobre
anexos.texto_extraido (LONGTEXT). Não existe nenhum índice FULLTEXT no projeto.
→ Migration com índice FULLTEXT em anexos.texto_extraido, guardada por driver
  exatamente como a migration 2025_12_09_151000 (return early se não for mysql).
→ A pesquisa passa a usar MATCH...AGAINST em MySQL MANTENDO o LIKE como fallback:
  a suite de testes corre em sqlite e tem de continuar a passar. Isola a escolha
  num único sítio, não espalhes ifs de driver pelas queries.

F8 — exportPDF/exportExcel fazem ->get() do resultado filtrado inteiro e
entregam-no ao DomPDF; batchReceber/batchEncaminhar aceitam 'ids' como JSON sem
limite de cardinalidade e iteram com queries e notificações por documento.
→ Limite superior de linhas na exportação (ex. 5000) com mensagem a pedir filtros
  quando excedido; ou processamento por chunks.
→ Limite ao número de ids no lote (ex. 200), validado antes de decodificar, com
  mensagem clara.
→ Testes: exportação acima do limite devolve aviso; lote acima do limite devolve 422.
```

---

## G. Fase 6 — Automação

Cada item merece prompt próprio, porque tem decisão de produto por trás (que
campos o OCR pode pré-preencher, que prazo por espécie, se o despacho em lote
precisa de confirmação).

| Oportunidade | Base já existente | Ganho |
|---|---|---|
| Prazo por espécie | `documento_especies` + `RetentionSchedule` | Substitui os 2/5 dias fixos de F3 por prazo real. **Melhor retorno/risco:** uma coluna, um seeder, três linhas no modelo — e desbloqueia o resto do SLA |
| Auto-preenchimento por OCR | `anexos.texto_extraido` + `DocumentoAssistantService` | O OCR só serve hoje para pesquisa *a posteriori*; propor espécie, nº ref., data e procedência no registo corta o tempo de balcão |
| Lembrete de receção pendente | `docs:check-sla` já agendado | Coberto pela Fase 2 |
| Destino sugerido | histórico `procedencia_id` → `departamento_id` | Pré-seleciona o departamento no formulário |
| Despacho em lote | já há receber/encaminhar em lote | A operação mais repetitiva do gabinete não tem lote |
| Modelos de despacho no painel rápido | `ModeloDespacho` já carregado no `show` | O `previewAjax` não os passa — quem usa a ação rápida escreve tudo à mão |
| Consulta pública de protocolo | `/verificar/documento/{hash}` com `throttle:30,1` | O QR do recibo aponta hoje para rota autenticada; o munícipe não o consegue abrir |

---

## Registo de execução

| Fase | Estado | Branch | Notas |
|---|---|---|---|
| 1 — Regra de perfis | **concluída** | `fix/entrada-docs-fase1-perfis` | P1–P4 fechados + 1 defeito novo (ver abaixo) |
| 2 — Fluxo autónomo | **concluída** | `fix/entrada-docs-fase2-fluxo` | F1 e F2 fechados (ramifica da fase 1, que ainda não está integrada) |
| 3 — Fonte única de autorização | por fazer | | |
| 4 — Integridade do registo | por fazer | | |
| 5 — Escala | por fazer | | |
| 6 — Automação | por fazer | | |

### Fase 1 — o que ficou feito

| Commit | Defeito | Resolução |
|---|---|---|
| `89a9e02` | **P2** | Rotas de protocolo exigem a ability `view`; `marcarImpresso` também (e não `update`, para a reimpressão continuar a funcionar depois do despacho). |
| `94431d8` | **P1** | `quickAction` exige `canDespachar()` no ramo gabinete e `canManageTasks()` + validação de destinatário no ramo chefia. A regra de destinatário saiu do `TarefaController` para `DocumentoEntradaService::validarDestinatarioTarefa()`. |
| `e832dbd` | **novo** | `despachar()` e `encaminhar()` declaravam `$documentos_entrada` contra a rota `{documento}`: sem *implicit binding*, o Laravel injetava um modelo vazio e o despacho respondia **sempre 403**. O botão "Despachar Documento" da listagem estava morto e o fluxo só passava pelo `quickAction`. |
| `1e27bc6` | **P3** | Destinos do despacho limitados ao gabinete do documento, no servidor (`Rule::in`) e nas listas das views, via `DocumentoEntradaService::departamentosDestinoPermitidos()`. |
| `004174a` | **P4** | `index()` aplica `viewAny`. Migration idempotente concede `documentos_entrada.listar` a todos os perfis existentes antes de a exigir — `user` e `tecnico` não a tinham nos seeders. |

**Antes de aplicar em produção:** correr `php artisan migrate` — sem a migration
`2026_09_13_090000_grant_documentos_entrada_listar_to_all_roles`, o `viewAny`
tranca os perfis operacionais fora da listagem.

### Fase 2 — o que ficou feito

| Commit | Defeito | Resolução |
|---|---|---|
| `c53dbfa` | **F2** | Nova `DocumentoEntradaRegistado` (type `documento_registado`, categoria `documentos`) enviada fora da transação a partir de `createDocument`. Destinatários: chefia do departamento de destino, responsável do departamento e responsável do gabinete — nunca quem regista. Texto do formulário e comentário do `store()` corrigidos. |
| `61116d3` | **F1** | Estados em curso passam a incluir `pendente_tratamento` e `encaminhado`. Em `pendente_tratamento` o alerta vai a quem despacha; `encaminhado` ganha pista própria de recebimento pendente com o relógio no `encaminhado_em`. `getSlaStatusAttribute` alinhado com o enum. |

**Decisões de implementação que convém não desfazer:**

- **F2 resolve-se por notificação, não por encaminhamento inicial.** Um
  `DocumentoEncaminhamento` com `recebido_em` a null bloqueia `forwardDocument()`,
  o endpoint `encaminhar`, a saída de gabinete e o `can_forward` da listagem — o
  documento ficaria congelado à nascença.
- **`notificarRegisto()` apanha `\Throwable`, não `\Exception`.** Deixar escapar
  uma `QueryException` faria o ciclo de retentativa de numeração em
  `createDocument()` repetir a criação e gerar um documento duplicado.
- **As duas pistas de SLA partilham `sla_nivel_notificado` com o prefixo
  `recebimento:`.** Sem o prefixo, um documento avisado em `warning` no
  departamento voltaria a ser silenciado depois de encaminhado.
- **`Departamento::getChefeAttribute()` já recorre ao `responsavel_id`** quando não
  há utilizador com o papel `chefe-departamento`. O fallback previsto no plano era
  redundante e não foi acrescentado; ficou apenas um teste de regressão.

**Falha de teste pré-existente, não introduzida aqui:**
`Tests\Unit\DocumentoInternoServiceTest::test_processar_template_injects_date_line_before_signature`
falha também na branch base — espera `GOVERNO PROVINCIAL DO NAMIBE, em Moçâmedes`
e recebe `GOVERNO PROVINCIAL, em Sede` (`DadosInstituicao` por omissão). Fica para
tratar fora deste plano.
