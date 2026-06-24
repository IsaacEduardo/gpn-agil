# Relatório de Análise de Arquitetura, Qualidade e Performance — Sistema GPN‑AGIL

> Sistema de Gestão Documental (EDMS) do Governo Provincial do Namibe, em **Laravel 12 / PHP 8.2+**.
> Análise baseada **exclusivamente no código** do repositório. Cada afirmação remete para o
> ficheiro (e linha, quando relevante). As áreas não inspecionadas em profundidade estão
> declaradas em **§7 Limitações**.

Data da análise: 2026‑06‑19.

---

## 1. Sumário Executivo

O GPN‑AGIL é um **monólito Laravel maduro e rico em funcionalidades**, com forte modelação de
domínio público‑administrativo (gabinetes → departamentos → utilizadores) e várias áreas
funcionais: documentos internos e de entrada, encaminhamento, tarefas, arquivo eletrónico
(EDMS), requisições, reservas de espaços, viaturas, termos de entrega, credenciais, assinatura
digital, notificações (in‑app/web push), pesquisa global, RBAC e um **módulo de IA/RAG** sobre
documentos.

**Dimensão (evidência):** ~41 controllers ([app/Http/Controllers](../app/Http/Controllers)),
35 modelos ([app/Models](../app/Models)), camada de serviços abrangente (incl. **Strategy
pattern** para requisições e módulo [app/Chatbot](../app/Chatbot)), 6 Policies, ~37 ficheiros de
teste ([tests](../tests)), filas, eventos, observers, jobs e auditoria.

**Maturidade:** média‑alta. O sistema demonstra boas decisões arquiteturais (serviços, policies,
jobs, eventos, caching com invalidação, transações) mas convive com **inconsistências
transversais**: validação maioritariamente *inline* (apenas 6 Form Requests), autorização
dispersa por controllers/serviços/policies (sem guarda de rota para administração nem
`Gate::before` central), `status` ora como enum ora como *string solta*, e **auditoria
automática limitada a 2 modelos**.

**Principais conclusões:**
- ✅ Núcleo de permissões profundo (Spatie + herança por delegação) e serviços de domínio bem
  isolados; fluxos recentes de **encaminhamento** e **arquivamento** já com atomicidade,
  auditoria e testes.
- ⚠️ Riscos de **consistência de autorização** (administração protegida só por verificações
  manuais em cada método), endpoint **`/deploy-setup`** que executa `artisan` em produção,
  e **cobertura de auditoria parcial**.
- ⚠️ Pontos de **performance** a vigiar (contagens de menu por render, dashboards de lote) e
  **dívida de testes** desigual entre módulos.

---

## 2. FASE 1 — Levantamento Arquitetural

### 2.1 Estrutura de diretórios (relevante)
```
app/
 ├─ Http/Controllers/        41 controllers (domínio + Auth + Admin)
 ├─ Http/Requests/           6 Form Requests (Requisição x4, DocumentoEntrada, Archive)
 ├─ Http/Middleware/         (vazio — sem middleware personalizado)
 ├─ Models/                  35 modelos Eloquent
 ├─ Policies/                6 (DocumentoEntrada, DocumentoInterno, Requisicao, Reserva, Viatura, Termo)
 ├─ Services/                Archive, DocumentoEntrada, DocumentoInterno, DocumentoWorkflow,
 │                           DocumentoPermission, Edms, Pasta, Signature, Html­Sanitizer,
 │                           Requisicao (+ Strategy/), Ai/ (LlmClient, Anthropic, Fake, Assistant)
 ├─ Jobs/                    ArchiveDocumentJob, ProcessarOcrAnexo, CheckRetentionPolicy
 ├─ Events/ Listeners/       DocumentoArquivado → LogDocumentoArquivado
 ├─ Notifications/           SimpleBroadcast, DocumentoEncaminhado(Departamento|Externo), RequisicaoAssinada
 ├─ Observers/               RequisicaoObserver
 ├─ Console/Commands/        CheckSla, SyncUserRoles, ApplyDepartmentRoles, QueueDiagnose, Notify/WebPush
 ├─ Traits/Auditable.php     auditoria automática (create/update/delete)
 └─ Chatbot/                 módulo RAG completo (Contracts, Embeddings, VectorStore, Observers, Jobs)
```

### 2.2 Modelos e relações (núcleo)
- **DocumentoEntrada** ([model](../app/Models/DocumentoEntrada.php)): `status` (string), `departamento_id`, `arquivado`, vistos (departamento/gabinete), relações `encaminhamentos`, `ultimoEncaminhamento` (`latestOfMany`), `encaminhamentosExternos`, `anexos` (morph), `tarefas`, `protocolo`, `tags`, `pasta`. Usa `Auditable` + `SoftDeletes`.
- **DocumentoInterno** ([model](../app/Models/DocumentoInterno.php)): `status` (**enum** `DocumentoStatus`), versões (`DocumentoVersao`), assinatura, `pasta`, `arquivado`. Usa `Auditable`.
- **DocumentoEncaminhamento / …Externo**, **DocumentoTarefa**, **Pasta** (hierárquica, `accessibleBy` scope), **AuditLog** (morph), **User** (Spatie `HasRoles` + `Notifiable`), **Role/Permission**, **Departamento/Gabinete**, **Requisicao** (+ sub‑tipos Produto/Oficina/Serviço/Passagem), **ReservaEspaco**, **Viatura**, **TermoEntrega**, **RetentionSchedule**, **UserCertificate**.

### 2.3 Autenticação e Autorização
- **Auth** via `laravel/ui` (`Auth::routes()` em [web.php:21](../routes/web.php#L21)); restante app sob `middleware('auth')`.
- **RBAC**: Spatie `spatie/laravel-permission` + **camada híbrida legada** em
  [User::hasPermissionTo](../app/Models/User.php#L156-L176), que adiciona **herança de permissões
  por delegação temporal** (`delegado_id`, `delegacao_inicio/fim`) e *fallback* por nome de role.
- **Policies** (6) registadas em [AuthServiceProvider](../app/Providers/AuthServiceProvider.php#L14-L21).
- **Gates por rota**: apenas `can:assistente.usar` no módulo de IA ([web.php:245](../routes/web.php#L245)).
- **Sem middleware personalizado** ([app/Http/Middleware] vazio) e **sem `Gate::before` global**
  ([AppServiceProvider](../app/Providers/AppServiceProvider.php) não o define) → a autorização de
  administração é feita por **verificações manuais em cada método** (ex.:
  [UserAdminController::ensurePermission](../app/Http/Controllers/Admin/UserAdminController.php#L21-L30),
  [RolePermissionController:27‑30](../app/Http/Controllers/RolePermissionController.php#L27),
  [InstituicaoController:20‑23](../app/Http/Controllers/InstituicaoController.php#L20)).

### 2.4 Filas, Eventos, Notificações, Serviços externos
- **Filas**: `QUEUE_CONNECTION=database` (local) / `redis` (produção) ([.env.example:64,134](../.env.example#L64)); fila dedicada **`notifications`** ([composer scripts](../composer.json#L65-L72)).
- **Eventos**: [EventServiceProvider](../app/Providers/EventServiceProvider.php) (`Registered`, `DocumentoArquivado`); `shouldDiscoverEvents()=false`. **Observers**: `RequisicaoObserver` ([AppServiceProvider:73](../app/Providers/AppServiceProvider.php#L73)) e os observers do Chatbot.
- **Notificações**: `database` + `broadcast` (Pusher/Echo) + **Web Push** (`minishlink/web-push`, VAPID, `PushSubscription`). `BROADCAST_CONNECTION=log` por omissão.
- **Email**: `MAIL_MAILER=log` por omissão; SES/Postmark/Resend disponíveis ([config/services.php](../config/services.php)).
- **IA**: Anthropic (driver real/`fake`) ([AppServiceProvider:29‑36](../app/Providers/AppServiceProvider.php#L29-L36)); embeddings Ollama/OpenAI no módulo Chatbot.
- **Sem** integração SMS/WhatsApp (nem package nem config).

### 2.5 Rotas
- **web.php** ([routes/web.php](../routes/web.php)): tudo sob `auth`, agrupado por módulo; mistura
  `Route::resource` com muitas rotas de verbo personalizado (encaminhar, vistos, tarefas,
  arquivar, EDMS, dashboards de lote, RBAC, perfil, notificações).
- **api.php** ([routes/api.php](../routes/api.php)): baseada em sessão (`web`+`auth`, **sem
  Sanctum**); grupos `chatbot` (`throttle:60,1`, `can:assistente.usar`) e `documents/archive`
  (`throttle:30,1`).
- **Rotas sensíveis**: `/deploy-setup` (executa `artisan migrate/optimize`, protegida por chave +
  `throttle:6,1`, [web.php:254‑306](../routes/web.php#L254)) e `/dev-login/{id}` (**só `local`**,
  [web.php:309](../routes/web.php#L309)).

---

## 3. FASE 2 — Análise por Fluxo

### 3.1 Gestão de Documentos (internos e de entrada)
**Internos** ([DocumentoInternoController](../app/Http/Controllers/DocumentoInternoController.php),
[DocumentoInternoService](../app/Services/DocumentoInternoService.php)): ciclo de vida por **enum**
`DocumentoStatus` (`RASCUNHO → EM_ANALISE → APROVADO → ASSINADO → ARQUIVADO`) com workflow
explícito (`submit/approve/reject/sign/restore` de versões) e **assinatura digital**
([SignatureService](../app/Services/SignatureService.php), `UserCertificate`,
[SignatureSecurityTest](../tests/Feature/SignatureSecurityTest.php)). Verificação pública por hash
([web.php:24](../routes/web.php#L24)). Autorização por [DocumentoInternoPolicy](../app/Policies/DocumentoInternoPolicy.php).

**De entrada** ([DocumentoEntradaController](../app/Http/Controllers/DocumentoEntradaController.php)):
registo com **sequência anual atómica** (`lockForUpdate` + retry em duplicados,
[service:259‑290](../app/Services/DocumentoEntradaService.php#L259)), protocolo + QR, anexos com
**OCR assíncrono** ([ProcessarOcrAnexo](../app/Jobs/ProcessarOcrAnexo.php)), vistos
departamento/gabinete, estados em **string** (`registrado/encaminhado/recebido/arquivado/…`).

| | |
|---|---|
| ✅ Fortes | Workflow de internos coeso; sequência anual atómica; OCR assíncrono; assinatura digital com testes de segurança; `Auditable` nos dois modelos. |
| 🔧 Melhorias | **Duas representações de estado** (enum nos internos vs string nas entradas) dificultam consistência; validação *inline* nos controllers (sem Form Request) — exceto `StoreDocumentoEntradaRequest`. |
| 🔴 Críticos | `downloadArquivo` recorre a *fallback* por histórico de encaminhamentos para autorizar ([controller:921‑952](../app/Http/Controllers/DocumentoEntradaController.php#L921)); rever para garantir que não amplia acesso indevido. |

### 3.2 Encaminhamento entre Gabinetes/Departamentos
Lógica em [DocumentoEntradaService](../app/Services/DocumentoEntradaService.php) (forward/receive/
cancel/saída) com **transações, `lockForUpdate` e idempotência** (trabalho recente), autorização
por [DocumentoEntradaPolicy::encaminhar](../app/Policies/DocumentoEntradaPolicy.php#L70) +
`canReceiveInDepartment`. UI AJAX na listagem (modal + combobox pesquisável + lote) — ver
[docs/ENCAMINHAMENTO.md](ENCAMINHAMENTO.md).

| | |
|---|---|
| ✅ Fortes | Atomicidade anti‑duplo‑clique; **auditoria central** (`documento.encaminhado/recebido/…`); encaminhamento individual e **em lote**; notificações em fila (`ShouldQueue`); testes dedicados ([EncaminhamentoFlowTest](../tests/Feature/EncaminhamentoFlowTest.php)). |
| 🔧 Melhorias | Cancelamento continua a fazer **hard delete** do registo (evidência preservada em `audit_logs`); recebimento em lote ainda recarrega a página. |
| 🔴 Críticos | — (corrigidos nesta iteração). |

### 3.3 Delegação de Tarefas
[DocumentoTarefa](../app/Models/DocumentoTarefa.php): `assigned_to_user_id` **ou**
`assigned_to_departamento_id`, `assigned_by_id`, `responsavel_user_id`, `prazo_at`, `status`.
Criação por [tarefasStore](../app/Http/Controllers/DocumentoEntradaController.php#L417) (validação
*inline*; permissão via `canManageTasks`) → [createTask](../app/Services/DocumentoEntradaService.php#L355).

| | |
|---|---|
| ✅ Fortes | Notifica o destinatário (utilizador) ou o grupo (chefe + dep + responsável de gabinete); permissões claras. |
| 🔧 Melhorias | Notificação **só in‑app** (`database`+`broadcast`) e **síncrona** (`SimpleBroadcastNotification` **não** é `ShouldQueue`); **sem evento `TaskAssigned`** (acoplamento no serviço); **sem email/WhatsApp** e **sem coluna de contacto telefónico** na tabela `users` ([migração](../database/migrations/0001_01_01_000000_create_users_table.php#L14)). |
| 🔴 Críticos | — |

### 3.4 Arquivamento (drag‑and‑drop)
Consolidado em [DocumentArchiveController](../app/Http/Controllers/DocumentArchiveController.php) +
[ArchiveService](../app/Services/ArchiveService.php) + [ArchiveDocumentJob](../app/Jobs/ArchiveDocumentJob.php)
+ evento/listener + componente Alpine ([archive-dropzone](../resources/views/components/archive-dropzone.blade.php)) —
ver [docs/ARQUIVAMENTO-DRAG-DROP.md](ARQUIVAMENTO-DRAG-DROP.md).

| | |
|---|---|
| ✅ Fortes | Autorização por documento (Policy `archive`) **no servidor**; pré‑validação da pasta; trabalho **assíncrono**; **auditoria**; `throttle:30,1`; testes ([ArchiveDragDropTest](../tests/Feature/ArchiveDragDropTest.php)). |
| 🔧 Melhorias | Frontend usa **Alpine via CDN** (sem build Vite no layout); `desarquivar` só trata entradas. |
| 🔴 Críticos | — (corrigidos: recursão de componente, controller duplicado, endpoint de destinos). |

### 3.5 Notificações (email e WhatsApp)
[SimpleBroadcastNotification](../app/Notifications/SimpleBroadcastNotification.php) (`database`+
`broadcast`, **não** `ShouldQueue`); [DocumentoEncaminhado*](../app/Notifications/DocumentoEncaminhadoDepartamento.php)
(`ShouldQueue`, fila `notifications`); **Web Push** (`PushSubscription`, VAPID). Dropdown in‑app
([notifications-dropdown](../resources/views/components/notifications-dropdown.blade.php)).

| | |
|---|---|
| ✅ Fortes | In‑app + broadcast + web push; notificações de encaminhamento já em fila com `viaQueues`. |
| 🔧 Melhorias | `SimpleBroadcastNotification` síncrona (impacta tempo de resposta); **canal `mail` não usado** em lado nenhum (apesar de configurável); **WhatsApp/SMS inexistente** (sem package/config/contacto). |
| 🔴 Críticos | `BROADCAST_CONNECTION=log` por omissão → o "tempo real" só funciona quando Pusher estiver configurado (limitação de ambiente, não bug). |

### 3.6 Outros fluxos revelados pelo código
- **EDMS / Arquivo** ([EdmsController](../app/Http/Controllers/EdmsController.php)): pastas
  hierárquicas, upload com versões, *streaming* de ficheiros/anexos, partilha de pastas,
  histórico/auditoria por pasta, **temporalidade** (`RetentionSchedule`,
  [CheckRetentionPolicy](../app/Jobs/CheckRetentionPolicy.php)).
- **SLA** ([CheckSlaCommand](../app/Console/Commands/CheckSlaCommand.php)) e atributo
  `sla_status` em DocumentoEntrada.
- **Requisições** ([RequisicaoController](../app/Http/Controllers/RequisicaoController.php) +
  **Strategy pattern** [Services/Requisicao](../app/Services/Requisicao)): produto/oficina/
  serviço/passagem, vistos, assinatura, termos de entrega/credenciais.
- **Reservas de espaços**, **Viaturas** (+ fotos, relatórios PDF/Excel), **Empresas**,
  **Feedbacks**.
- **Dashboards** de Gabinete e Departamento com **operações em lote** (aprovar/assinar).
- **RBAC** ([RolePermissionController](../app/Http/Controllers/RolePermissionController.php)) e
  administração de utilizadores/instituição.
- **Pesquisa**: global ([SearchController](../app/Http/Controllers/SearchController.php)) e por
  documento (`searchJson`, incl. conteúdo OCR).
- **Assistente de IA / Chatbot RAG** ([app/Chatbot](../app/Chatbot)): embeddings, *vector store*,
  recuperação, observers de ingestão, jobs, comandos — *gated* por feature flag.

---

## 4. FASE 3 — Qualidade e Performance Transversais

### 4.1 Qualidade do código
- ✅ **Camada de serviços** rica e **Strategy pattern** (requisições) e **interfaces/drivers** (IA,
  embeddings, vector store) — bom isolamento e testabilidade (`Fake*`).
- ✅ Uso consistente de **`DB::transaction`** (56 ocorrências de transação/fila/notificação em 22
  ficheiros) e **Jobs/Events**.
- 🔧 **Validação maioritariamente *inline*** (apenas 6 Form Requests) → regras de validação
  espalhadas e menos reutilizáveis.
- 🔧 **Nomenclatura mista PT/EN** e **estado de documento inconsistente** (enum vs string).
- 🔧 Lógica de permissões **híbrida** (Spatie + legado + delegação) é poderosa mas **complexa** e
  difícil de auditar ([User:156‑230](../app/Models/User.php#L156)).

### 4.2 Segurança
- ✅ **CSRF** (grupo `web`), **mass assignment** controlado por `$fillable`, **XSS** mitigado por
  [HtmlSanitizer](../app/Services/HtmlSanitizer.php) ([SanitizerTest](../tests/Unit/SanitizerTest.php)),
  **assinatura digital** com testes de segurança, queries via Eloquent/binding (sem SQL cru
  relevante exposto a input).
- ⚠️ **Autorização de administração sem guarda de rota**: o grupo `/admin` tem só `auth`
  ([web.php:163](../routes/web.php#L163)); a restrição depende de **chamadas manuais** em cada
  método. Os controllers verificados (`UserAdmin`, `RolePermission`, `Instituicao`) **guardam**,
  mas o padrão é frágil (um método sem o *check* = escalonamento de privilégios).
- ⚠️ **`/deploy-setup`** executa `artisan migrate/optimize/storage:link` remotamente; protegido por
  chave + `hash_equals` + `throttle` + bloqueio da chave padrão em produção, mas continua a ser
  uma **superfície sensível** publicamente alcançável.
- ⚠️ `downloadArquivo`/`downloadAnexo` usam *fallback* de autorização por histórico — rever.
- ℹ️ `/dev-login` está corretamente limitado a `local`.

### 4.3 Performance
- ✅ **Filas** para OCR, arquivamento, notificações de encaminhamento e indexação RAG.
- ✅ **Caching com invalidação**: [CatalogCache](../app/Support/CatalogCache.php) (viaturas/empresas),
  caches por utilizador (departamentos/gabinetes) invalidados em `User::saved/deleted`
  ([AppServiceProvider:76‑99](../app/Providers/AppServiceProvider.php#L76)), e **contagens de menu
  em cache 30s/utilizador**.
- ✅ **Índices** dedicados (várias migrações `add_indexes_*`) e **eager loading** do
  `ultimoEncaminhamento` na listagem ([service:217‑225](../app/Services/DocumentoEntradaService.php#L217)).
- 🔧 O **View::composer de `layouts.app`** executa ~8 contagens por render (mitigado por cache 30s,
  mas é um *hot path* a vigiar) ([AppServiceProvider:117‑205](../app/Providers/AppServiceProvider.php#L117)).
- 🔧 RAG calcula **cosseno em PHP** sobre candidatos (adequado a corpora pequenos/médios; a
  interface `VectorStore` permite migrar para Qdrant/pgvector).
- 🔧 `SimpleBroadcastNotification` **síncrona** acrescenta latência às ações que a despacham.

### 4.4 Auditoria e Logs
- ✅ `AuditLog` + trait [Auditable](../app/Traits/Auditable.php) (create/update/delete) — **mas só
  em `DocumentoEntrada` e `DocumentoInterno`** (confirmado por inspeção dos modelos).
- ✅ **Auditoria de ações de domínio** explícita em `ArchiveService` e `DocumentoEntradaService`
  (arquivamento, encaminhamento/recebimento/cancelamento/saída).
- 🔴 **Lacuna**: alterações de **RBAC** (roles/permissões), **administração de utilizadores**,
  **reservas**, **requisições** e **viaturas** **não** são auditadas automaticamente.

### 4.5 Tratamento de Erros
- ✅ `DB::transaction` garante atomicidade nos fluxos críticos; retry em colisão de sequência.
- 🔧 [bootstrap/app.php](../bootstrap/app.php) tem `withExceptions` **vazio** (handler por
  omissão) — sem normalização central de respostas de erro/JSON.
- 🔧 Alguns `try/catch` **silenciam** exceções (auditoria, dados de instituição) — correto para não
  bloquear, mas convém registar em log.

---

## 5. Matriz de Riscos

| # | Risco | Prob. | Impacto | Prioridade |
|---|---|---|---|---|
| R1 | Autorização de administração só por *checks* manuais (sem guarda de rota/Gate central) | Média | Alto (escalonamento) | **P0** |
| R2 | `/deploy-setup` executa `artisan` remotamente | Baixa | Crítico | **P0** |
| R3 | Auditoria ausente em RBAC/utilizadores/reservas/requisições | Média | Alto (conformidade) | **P1** |
| R4 | Validação *inline* dispersa (poucos Form Requests) | Alta | Médio | **P1** |
| R5 | Estado de documento inconsistente (enum vs string) | Média | Médio | **P1** |
| R6 | `SimpleBroadcastNotification` síncrona (latência) | Alta | Baixo‑Médio | **P2** |
| R7 | Contagens de menu por render (hot path) | Média | Médio | **P2** |
| R8 | Complexidade da lógica híbrida de permissões | Média | Médio | **P2** |
| R9 | `downloadArquivo` com *fallback* de autorização | Baixa | Médio | **P2** |

---

## 6. Recomendações Priorizadas

**P0 — Segurança imediata**
1. **Centralizar a autorização de administração**: middleware `role:admin`/Gate ou
   `Gate::before` + `authorize()` nas rotas `/admin` e RBAC, eliminando a dependência de *checks*
   manuais por método (R1).
2. **Endurecer/remover `/deploy-setup`** em produção: idealmente substituir por *pipeline* de
   deploy; se mantido, exigir IP allow‑list além da chave (R2).

**P1 — Qualidade e conformidade**
3. **Estender a auditoria** (trait `Auditable` ou log explícito) a RBAC, utilizadores, reservas,
   requisições e viaturas (R3).
4. **Migrar validação para Form Requests** nos controllers de maior risco (documentos, tarefas,
   reservas, requisições) (R4).
5. **Unificar o estado de documento** (adotar o enum `DocumentoStatus` também nas entradas, com
   *cast*), reduzindo divergências (R5).

**P2 — Sustentabilidade e performance**
6. Tornar `SimpleBroadcastNotification` **`ShouldQueue`** (fila `notifications`) e disparar a
   notificação de tarefa via um **evento `TaskAssigned`** desacoplado (R6).
7. Rever o **View::composer de contagens** (consolidar numa query/materialização) (R7).
8. Documentar/testar a **lógica híbrida de permissões** (casos de delegação) (R8).
9. Auditar o caminho de `downloadArquivo`/`downloadAnexo` (R9).

---

## 7. Limitações da Análise
- Análise **estática do código** apenas; não foram executados *profilers*, scanners de segurança
  nem testes de carga.
- **Serviços externos** (Anthropic, Ollama/OpenAI, Pusher, SES/Postmark/Resend, OCR Tesseract)
  não foram exercitados — apenas a sua **configuração** foi inspecionada.
- **Inspeção em profundidade** concentrou‑se nos fluxos de **documentos, encaminhamento, tarefas,
  arquivamento, notificações, autorização e auditoria**. Os módulos de **requisições, reservas,
  viaturas, termos, credenciais, assinatura e Chatbot** foram analisados **estruturalmente**
  (rotas, ficheiros, testes e leituras pontuais), pelo que a sua lógica interna detalhada não foi
  exaustivamente revista.
- O relatório reflete o estado do repositório **na data indicada**; alterações posteriores podem
  invalidar pontos específicos.
