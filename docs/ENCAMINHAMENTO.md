# Encaminhamento de Documentos de Entrada

Fluxo de **encaminhamento** de documentos de entrada entre **departamentos** (interno) e
entre **gabinetes** (externo / "saída de gabinete"), com recebimento, cancelamento, lote,
auditoria e notificações.

> Este documento descreve o fluxo **após** a auditoria de qualidade: resume o que existia,
> o que foi corrigido/melhorado (com justificação), e como usar/estender.

---

## 1. Estados e transições (extraídos do código)

`status` de [DocumentoEntrada](../app/Models/DocumentoEntrada.php) (string; valores em
[DocumentoStatus](../app/Enums/DocumentoStatus.php)):

```
REGISTRADO ──encaminhar──▶ ENCAMINHADO ──receber──▶ RECEBIDO ──encaminhar──▶ ENCAMINHADO ...
                                  │
                                  └──cancelar──▶ REGISTRADO (se nunca recebido) | RECEBIDO (se já houve recebimento)

(qualquer)  ──saída de gabinete──▶ ENCAMINHADO_EXTERNO
```

- **Encaminhar** (`forwardDocument`): cria um registo em `documento_encaminhamentos`
  (`origem`/`destino`/`usuario`/`encaminhado_em`), `status = ENCAMINHADO`. **Não** muda o
  `departamento_id` (só muda no recebimento).
- **Receber** (`receiveDocument`): marca `recebido_em`/`recebido_por_id`, **move o
  `departamento_id` para o destino** e `status = RECEBIDO`. **Idempotente** (duplo-clique não repete).
- **Cancelar** (`cancelForwarding`): só enquanto **não recebido**; remove o encaminhamento e
  repõe o `status` de forma coerente com o histórico (ver §4).
- **Saída de gabinete** (`sendToGabinete`): cria `documento_encaminhamentos_externos`,
  `status = ENCAMINHADO_EXTERNO`.

Fonte: [DocumentoEntradaService](../app/Services/DocumentoEntradaService.php).

---

## 2. Permissões (regras de perfil — inalteradas)

[DocumentoEntradaPolicy](../app/Policies/DocumentoEntradaPolicy.php) +
[DocumentoPermissionService](../app/Services/DocumentoPermissionService.php):

| Ação | Quem pode |
|---|---|
| `encaminhar` | Admin **ou** quem pertence ao **departamento atual** do documento. |
| Receber | Admin **ou** quem pertence ao **departamento de destino** (`canReceiveInDepartment`). |
| `saidaGabinete` | Admin **ou** **responsável do gabinete**. |
| Cancelar | Apenas o **autor** do encaminhamento e **antes** do recebimento. |

A autorização é **sempre reavaliada no servidor**, por documento — inclusive no lote
(`forwardBatch` chama `Gate::allows('encaminhar', $doc)` para cada item).

---

## 3. Arquitetura

### Backend
| Camada | Ficheiro | Notas |
|---|---|---|
| Serviço | [DocumentoEntradaService](../app/Services/DocumentoEntradaService.php) | `forwardDocument`, `receiveDocument`, `cancelForwarding`, `receiveBatch`, **`forwardBatch`**, `sendToGabinete` + auditoria. |
| Controller | [DocumentoEntradaController](../app/Http/Controllers/DocumentoEntradaController.php) | `encaminhar`, `receberEncaminhamento`, `cancelarEncaminhamento`, `batchReceber`, **`batchEncaminhar`** — respondem **JSON** a pedidos AJAX. |
| Rotas | [routes/web.php](../routes/web.php) | `…encaminhar`, `…encaminhamentos.receber`, `…encaminhamentos.cancelar`, `…batch.receber`, **`…batch.encaminhar`**. |
| Notificações | [DocumentoEncaminhadoDepartamento](../app/Notifications/DocumentoEncaminhadoDepartamento.php) / Externo | `ShouldQueue` (fila `notifications`), via `database`+`broadcast`. |

### Frontend
| Componente | Ficheiro |
|---|---|
| Combobox pesquisável de departamentos | [components/departamento-combobox.blade.php](../resources/views/components/departamento-combobox.blade.php) |
| Encaminhamento na listagem (modal + AJAX) | [partials/encaminhamento-index.blade.php](../resources/views/documentos_entradas/partials/encaminhamento-index.blade.php) |
| Listagem | [documentos_entradas/index.blade.php](../resources/views/documentos_entradas/index.blade.php) |
| Detalhe (modal de encaminhar) | [partials/modals.blade.php](../resources/views/documentos_entradas/partials/modals.blade.php) |

Stack: **Blade + vanilla JS + Bootstrap** (sem framework novo). As ações AJAX são
*progressive enhancement* — se o JS falhar, os formulários nativos continuam a funcionar.

---

## 4. O que foi corrigido/melhorado (com justificação)

| # | Achado da auditoria | Correção |
|---|---|---|
| 🔴1 | `cancelForwarding` apagava (hard delete) o encaminhamento → perdia a trilha | **Auditoria central** (`audit_logs`) regista o cancelamento **antes** do delete (ator, origem, destino, estado revertido). |
| 🔴2 | Cancelamento repunha sempre `RECEBIDO` (errado no 1.º encaminhamento) | Repõe **`REGISTRADO`** se o documento nunca foi recebido; **`RECEBIDO`** se já existe um encaminhamento recebido anterior (heurística baseada apenas nos dados existentes). |
| 🔴3 | Sem auditoria central de encaminhar/receber/cancelar/saída | `audit_logs` para todas as ações (`documento.encaminhado`, `documento.recebido`, `documento.encaminhamento_cancelado`, `documento.saida_gabinete`). |
| 🔴4 | Corrida / duplo-clique no encaminhar e no receber | `lockForUpdate` + **re-verificação dentro da transação**; `receiveDocument` é **idempotente** (devolve `false` se já recebido). |
| 🔴5 | `Policy::encaminhar` com bloco morto vazio | Removido (comportamento idêntico, código limpo). |
| 🟠6 | Encaminhar obrigava a abrir a página de detalhe | **Encaminhar a partir da listagem** (ação por linha + lote), via modal AJAX. |
| 🟠7 | `<select>` "enorme" sem pesquisa | **Combobox pesquisável** reutilizável (listagem e detalhe), com exclusão do departamento atual. |
| 🟠8/🟡10 | Recarregamento de página inteira; sem feedback imediato | **AJAX + toasts** + atualização inline da linha (encaminhar individual/lote e receber individual). |
| 🟠9 | Sem encaminhamento em lote | **`batchEncaminhar`** + botão "Encaminhar" na barra de seleção. |

---

## 5. Auditoria

Cada ação grava em `audit_logs` (reutilizando o `AuditLog`, como o `ArchiveService`):

| `action` | `new_values` (resumo) |
|---|---|
| `documento.encaminhado` | `encaminhamento_id`, `origem_departamento_id`, `destino_departamento_id`, `status` |
| `documento.recebido` | `encaminhamento_id`, `origem_departamento_id`, `destino_departamento_id`, `status` |
| `documento.encaminhamento_cancelado` | `encaminhamento_id`, `origem/destino_departamento_id`, `status_revertido` |
| `documento.saida_gabinete` | `encaminhamento_externo_id`, `origem/destino_gabinete_id`, `oficio_numero` |

A auditoria nunca bloqueia a operação (falhas são silenciadas).

---

## 6. Como usar (listagem)

- **Selecionar**: o checkbox aparece em linhas **recebíveis** (encaminhadas ao seu
  departamento) **ou** **encaminháveis** (do seu departamento, sem pendência).
- **Encaminhar individual**: menu de ações da linha → **Encaminhar** → escolher destino no
  combobox → confirma (AJAX, toast, linha atualizada).
- **Encaminhar em lote**: selecionar várias linhas → barra de ações → **Encaminhar** →
  destino único. O servidor valida cada documento e reporta quantos foram processados.
- **Receber individual**: menu de ações → **Receber** (AJAX, toast). O documento passa a
  pertencer ao seu departamento.
- **Receber em lote**: barra de ações → **Receber** (mantém o formulário nativo).

---

## 7. Testes

[tests/Feature/EncaminhamentoFlowTest.php](../tests/Feature/EncaminhamentoFlowTest.php):
encaminhar cria registo + audita; bloqueio de pendência (atómico); receber move o
departamento e é **idempotente**; cancelamento repõe `REGISTRADO`/`RECEBIDO` + audita; lote
respeita autorização/pendência; endpoints AJAX (`encaminhar`, `batch.encaminhar`); render da
listagem com a UI de encaminhamento.

[tests/Feature/DocumentoEntradaPolicyTest.php](../tests/Feature/DocumentoEntradaPolicyTest.php)
continua válido.

```bash
php -d memory_limit=1024M vendor/bin/phpunit --filter "EncaminhamentoFlowTest|DocumentoEntradaPolicyTest"
```

---

## 8. Limitações conhecidas / trabalho futuro

- O **recebimento em lote** mantém o formulário nativo (reload); pode ser migrado para AJAX
  expondo JSON em `batchReceber` (o padrão já existe nos restantes endpoints).
- O **encaminhamento no detalhe** (página show) usa o combobox pesquisável mas mantém o seu
  fluxo de confirmação nativo (submit normal) — a listagem é totalmente AJAX.
- `documento_encaminhamentos` continua a usar **hard delete** no cancelamento; a evidência
  fica preservada em `audit_logs`. Para retenção total do registo, adicionar `SoftDeletes`
  (migração) numa iteração futura.
- O evento de encaminhamento continua a ser notificado diretamente no serviço (notificações
  já em fila); um `Event`/`Listener` dedicado seria o ponto de extensão para broadcasting
  adicional.
