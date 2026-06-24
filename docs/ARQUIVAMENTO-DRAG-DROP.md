# Arquivamento de Documentos por Arrastar-e-Soltar (Drag-and-Drop)

Funcionalidade de **arquivamento em lote** de documentos (Internos e de Entrada) arrastando
linhas de uma listagem para uma **barra de destinos** (arquivamento automático cronológico
ou para uma pasta específica acessível). Aplica-se às páginas **Documentos Internos**,
**Documentos de Entrada** e **EDMS** (separador *Pendentes de Arquivamento*).

> Este documento descreve a implementação **após** a auditoria de qualidade. Resume o que
> mudou, define formalmente "arquivar", e explica como integrar a funcionalidade noutras
> listagens.

---

## 1. O que significa "arquivar"

Arquivar é uma **mudança de estado lógica + organização em pasta**. **Não** move o ficheiro
físico (`arquivo_caminho`/anexos permanecem intactos). Fonte canónica única:
[`App\Services\ArchiveService::archive()`](../app/Services/ArchiveService.php).

Ao arquivar, o documento passa a ter:

| Campo | Valor |
|---|---|
| `arquivado` | `true` |
| `arquivado_em` | `now()` |
| `arquivado_por` | id do utilizador (ator real) |
| `pasta_id` | pasta de destino |
| `status` | `DocumentoStatus::ARQUIVADO` (`'arquivado'`) — interno; string `'arquivado'` — entrada |

**Destino (`pasta_id`)** pode ser:
- **Automático/cronológico** (`'auto'`): cria/reutiliza `01. Correspondência Recebida > AAAA > MM - Mês`
  (entrada) ou `03. Documentos Internos` (interno), via `PastaService::getOrCreateChronologicalFolder()`.
- **Pasta específica**: tem de passar `Pasta::accessibleBy($user)` **e** `PastaService::isFolderCompatibleWithType()`.

**Transições:** a partir de qualquer estado **não-arquivado** (documentos já arquivados são
rejeitados). É registada **auditoria** (`audit_logs`, ação `documento.arquivado`).

---

## 2. Regras de perfil (quem pode arquivar)

Definidas nas Policies (ability `archive`) e **sempre reavaliadas no servidor**, por documento:

**Documentos Internos** — [`DocumentoInternoPolicy::archive`](../app/Policies/DocumentoInternoPolicy.php):
- Admin: qualquer documento;
- **Autor: apenas os seus próprios RASCUNHOS** (documentos assinados exigem chefia);
- Chefe de Departamento: documentos do seu departamento;
- Chefe de Gabinete: documentos dos departamentos do seu gabinete.

**Documentos de Entrada** — [`DocumentoEntradaPolicy::archive`](../app/Policies/DocumentoEntradaPolicy.php):
- Admin: qualquer documento;
- Quem registou o documento (`user_id`);
- Chefe de Departamento do documento;
- Chefe de Gabinete responsável pelo gabinete do departamento.

> ⚠️ A regra "o autor de um documento interno só arquiva os seus **rascunhos**" é a regra
> **existente** no código e foi mantida. Se a intenção for permitir ao autor arquivar também
> documentos assinados, é uma alteração de Policy a confirmar com o negócio (não foi assumida).

---

## 3. Arquitetura

### Backend

| Camada | Ficheiro | Responsabilidade |
|---|---|---|
| Validação | [`ArchiveDocumentRequest`](../app/Http/Requests/ArchiveDocumentRequest.php) | `document_type` (entrada/interno), `document_ids` (1–100), `destination_type` (status/folder), `destination_id`. |
| Controller | [`DocumentArchiveController`](../app/Http/Controllers/DocumentArchiveController.php) | `store` (lote) e `destinations` (pastas acessíveis). |
| Job | [`ArchiveDocumentJob`](../app/Jobs/ArchiveDocumentJob.php) | Arquivamento **assíncrono** (fila), `tries=2`, dispara o evento. |
| Serviço | [`ArchiveService`](../app/Services/ArchiveService.php) | Regra canónica (estado + pasta + validação + auditoria). |
| Evento/Listener | [`DocumentoArquivado`](../app/Events/DocumentoArquivado.php) → [`LogDocumentoArquivado`](../app/Listeners/LogDocumentoArquivado.php) | Efeitos secundários desacoplados (log; ponto de extensão p/ notificações/broadcast). |
| Rotas | [`routes/api.php`](../routes/api.php) | `POST /api/documents/archive`, `GET /api/documents/archive/destinations` (`web`+`auth`+`throttle:30,1`). |

**Defesa em profundidade** (4 camadas) no `store`:
1. **Form Request** — autenticação + payload válido;
2. **Pré-validação síncrona da pasta** — acessível + compatível → `403`/`422` **imediato**
   (em vez de falha silenciosa dentro do Job);
3. **Autorização por documento** — `Gate::archive` (cada item pode ter dono/departamento
   diferentes); itens negados são reportados individualmente em `denied`;
4. **Re-validação no Job** — o `ArchiveService` volta a validar a pasta (o cliente nunca é a
   fonte de verdade).

**Resposta do `store`:**
```jsonc
// 200 quando ≥1 despachado; 422 quando nenhum; 403/422 na pré-validação da pasta
{ "message": "...", "dispatched": [12, 15], "denied": [{ "id": 13, "reason": "..." }] }
```

### Frontend

Decisão do projeto: **Alpine via CDN + componente inline** (sem dependência de build Vite).

Componente reutilizável: [`resources/views/components/archive-dropzone.blade.php`](../resources/views/components/archive-dropzone.blade.php).

- `Alpine.store('archive')` — seleção (lote) + ação `run()` (POST agrupado por tipo,
  barra de **progresso**, **toasts**, *fade* das linhas arquivadas, tratamento de erros por item);
- `Alpine.data('archiveDropZone')` — zonas de *drop* (automático + por pasta) com **dica visual**
  de compatibilidade (espelha o servidor; **não** é autoritativa);
- *Listeners* delegados (vanilla) tratam `dragstart`/`dragend`/`checkbox` — as linhas **não**
  precisam de `x-data`, o que torna a integração trivial e sem conflitos com o JS existente.

---

## 4. Como integrar numa nova listagem

1. **Tornar as linhas arrastáveis** (no `<tr>` ou cartão):
   ```html
   <tr draggable="true" data-doc-id="{{ $doc->id }}" data-doc-type="interno"> ... </tr>
   ```
   `data-doc-type` deve ser `interno` ou `entrada`.

2. **(Opcional) Multi-seleção em lote** — adicionar um checkbox por linha:
   ```html
   <input type="checkbox" class="archive-select"
          data-doc-id="{{ $doc->id }}" data-doc-type="interno"
          onclick="event.stopPropagation()">
   ```

3. **Incluir o componente uma vez** na página (componente anónimo Blade `archive-dropzone`):
   - tipo fixo: `document-type="interno"` (ou `"entrada"`);
   - tipo misto (ex.: EDMS): sem atributo — o tipo é lido de cada linha.

O componente injeta as pastas **acessíveis** do utilizador como destinos (pré-filtradas pelo
tipo quando este é fixo) e usa a `toast-container` e o Bootstrap JS já presentes no layout.

> Pré-requisitos do layout (já satisfeitos em `layouts/app.blade.php`): `<meta name="csrf-token">`,
> `.toast-container`, Bootstrap JS, e `@stack('scripts')` no fim do `<body>`.

---

## 5. Segurança e auditoria

- **Autorização no servidor** por documento (Policy `archive`) — o feedback no cliente é apenas UX.
- **Pasta de destino** validada (acessível + compatível) síncrona e novamente no Job.
- **Rate limiting**: `throttle:30,1` na rota de arquivamento.
- **Auditoria**: cada arquivamento grava em `audit_logs` (ação `documento.arquivado`, ator,
  pasta) — incluindo o fluxo legado (`POST /pastas/{id}/arquivar`), agora unificado no
  `ArchiveService` com verificação de Policy.
- **Assincronia**: o trabalho corre na fila (`ArchiveDocumentJob`) para não bloquear a interface.

---

## 6. Testes

[`tests/Feature/ArchiveDragDropTest.php`](../tests/Feature/ArchiveDragDropTest.php):
autenticação obrigatória; despacho do Job; arquivamento automático (estado + auditoria);
**negação entre departamentos**; rejeição de já-arquivado; **pré-validação** de pasta
incompatível (`422`) e inacessível (`403`); endpoint de destinos (regressão do bug do
FormRequest); criação cronológica no `ArchiveService`.

[`tests/Feature/EdmsArchiveTest.php`](../tests/Feature/EdmsArchiveTest.php) continua válido
(fluxo legado, agora via `ArchiveService`).

```bash
php -d memory_limit=1024M vendor/bin/phpunit --filter "ArchiveDragDropTest|EdmsArchiveTest"
```

---

## 7. Resumo das correções da auditoria

| Achado | Correção |
|---|---|
| 🔴 Funcionalidade morta no browser (Alpine/JS nunca carregado) | Componente Blade carrega Alpine via CDN e regista-se inline. |
| 🟠 Controller duplicado/morto + perda de pré-validação | Um único `DocumentArchiveController` com pré-validação síncrona da pasta. |
| 🟠 Endpoint `destinations` quebrado (FormRequest em GET) | Passou a usar `Request`; coberto por teste de regressão. |
| 🟠 *Fade* das linhas não funcionava (`data-doc-id` em falta) | Linhas passam a expor `data-doc-id`/`data-doc-type`. |
| 🟠 Multi-seleção e destinos por pasta inexistentes na UI | Checkboxes de lote + zonas de *drop* por pasta (com dica de compatibilidade). |
| 🟡 Duas fontes de verdade / segurança fraca no legado | `PastaController::arquivar` unificado no `ArchiveService` + Policy. |
| 🟡 Sem progresso em lote | Barra de progresso + resultados por documento. |
| 🟡 Evento sem ouvinte | `LogDocumentoArquivado` registado. |
| 🟡 Sem testes do novo fluxo | `ArchiveDragDropTest` (9 casos). |

## 8. Limitações conhecidas / trabalho futuro

- **Desarquivar** (`PastaController::desarquivar`) ainda só trata Documentos de Entrada e
  repõe o estado para `registrado`; o suporte simétrico a Internos requer definir a transição
  de estado de retorno (a confirmar com o negócio) — fora do âmbito desta entrega.
- `DocumentoEntrada.status` é uma string (sem *cast* de enum), ao contrário de `DocumentoInterno`.
  Mantido por compatibilidade com os dados/consultas existentes.
- O evento `DocumentoArquivado` não faz *broadcast* (Echo/WebSockets não configurado); o
  `LogDocumentoArquivado` é o ponto de extensão para notificações em tempo real no futuro.
