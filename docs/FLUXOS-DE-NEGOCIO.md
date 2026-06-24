# Fluxos de Negócio — GPN-AGIL

> Workflows (máquinas de estado) dos principais módulos do sistema.
> Complementa o documento de [Arquitetura](ARQUITETURA.md).

---

## 1. Documento de Entrada (protocolo → arquivo)

Documentos recebidos pela instituição são registados, protocolados e roteados internamente
até serem arquivados.

```mermaid
stateDiagram-v2
    [*] --> Registrado: store() + dispatch OCR (async)
    Registrado --> Protocolado: gera protocolo + QR code
    Protocolado --> Encaminhado: encaminhar (interno/externo)
    Encaminhado --> Recebido: receberEncaminhamento
    Recebido --> VistoDepartamento: aprovar / rejeitar
    VistoDepartamento --> VistoGabinete: aprovar / rejeitar
    VistoGabinete --> Arquivado: pastas/arquivar
    Arquivado --> [*]
```

**Recursos associados:**
- **Tarefas** (`DocumentoTarefa`) com responsável, conclusão e cancelamento.
- **Documentos relacionados** (auto-relacionamento N:N via `documento_relacoes`).
- **SLA** — atributo calculado `sla_status` em `DocumentoEntrada`: `normal` / `warning` (≥ 2 dias) /
  `critical` (≥ 5 dias), ignorado para documentos arquivados/finalizados.
- **OCR** — ao anexar imagem/PDF, o job `ProcessarOcrAnexo` extrai o texto para busca.

---

## 2. Documento Interno + Assinatura Digital

Documentos produzidos internamente seguem um workflow de aprovação antes da assinatura.

```mermaid
stateDiagram-v2
    [*] --> Rascunho: create (a partir de modelo)
    Rascunho --> EmAnalise: submit()
    EmAnalise --> Aprovado: approve() [bloqueia edição]
    EmAnalise --> Rascunho: reject(motivo)
    Aprovado --> Assinado: sign() [SignatureService]
    Aprovado --> Rascunho: reject
    Assinado --> Arquivado: archive()
    Assinado --> [*]: Verificação pública por hash
```

> A transição de estados é controlada por `DocumentoWorkflowService`, que valida o estado de origem
> antes de cada transição (ex.: apenas *rascunhos* podem ser enviados para análise).

### Verificação pública

Documentos assinados expõem uma rota **pública** (sem autenticação) para validar autenticidade:

```
GET /verificar/documento/{hash}
```

---

## 3. Motor de Assinatura Digital (`SignatureService`)

```mermaid
flowchart TD
    A[sign documento, user, senha] --> B{Senha do utilizador válida?}
    B -->|Não| X1[ValidationException]
    B -->|Sim| C{canSign? regra por espécie/papel}
    C -->|Não| X2[ValidationException]
    C -->|Sim| D{Status APROVADO? só p/ Doc. Interno}
    D -->|Não| X3[ValidationException]
    D -->|Sim| E[Gera hash SHA-256 do conteúdo]
    E --> F{Utilizador tem certificado P12 válido?}
    F -->|Sim| G[openssl_sign com chave privada P12<br/>senha do P12 NUNCA é persistida]
    F -->|Não| H[Fallback: hash SHA-256 da assinatura]
    G --> I[Grava assinatura + bloqueia edição]
    H --> I
    I --> J[Doc. Interno: incrementa versão MAJOR]
```

### Regras de quem pode assinar (`canSign` por espécie)

| Espécie do documento | Quem assina |
|----------------------|-------------|
| **PARECER** | O autor (`criado_por`) |
| **OFÍCIO, CARTA, MEMORANDO, CIRCULAR, DESPACHO** | Chefe de Gabinete (responsável pelo gabinete) |
| **NOTA, REQUERIMENTO, DECLARAÇÃO** | Chefe de Departamento |
| *(Requisição)* | Chefe de Departamento do requisitante |
| **admin** | Sempre pode (override) |

---

## 4. Requisições (Strategy Pattern)

Quatro tipos de requisição compartilham o mesmo fluxo de aprovação, mas têm regras de
criação/validação específicas resolvidas por *strategies*.

```mermaid
flowchart LR
    A[create] --> B{Tipo?}
    B -->|Produto| P[ProdutoStrategy]
    B -->|Oficina| O[OficinaStrategy]
    B -->|Serviço| S[ServicoStrategy]
    B -->|Passagem| Pa[PassagemStrategy]
    P & O & S & Pa --> ST[Pendente]
    ST --> VD[Visto Departamento]
    VD --> AP{Aprovar / Rejeitar}
    AP -->|Aprovada| SG[Assinar - sign]
    SG --> CC[Concluída]
    AP -->|Rejeitada| RJ[Rejeitada + motivo]
```

### Estados (`StatusRequisicao`)

| Estado | Valor | Cor |
|--------|-------|-----|
| Pendente | `pendente` | warning |
| Assinada | `assinada` | primary |
| Aprovada | `aprovada` | success |
| Rejeitada | `rejeitada` | danger |
| Concluída | `concluida` | info |

Inclui **aprovação em massa** (`requisicoes/aprovar-em-massa`) e **vistos de departamento** em lote
via dashboards de Departamento e Gabinete.

---

## 5. Reserva de Espaços

```mermaid
stateDiagram-v2
    [*] --> Pendente: store (verifica disponibilidade)
    Pendente --> VistoDepartamento: visto aprovar/rejeitar
    VistoDepartamento --> Aprovada: aprovar
    VistoDepartamento --> Rejeitada: rejeitar
    Aprovada --> Cancelada: cancelar
    Aprovada --> [*]
    Rejeitada --> [*]
```

Possui visão de **calendário** e verificação de disponibilidade em tempo real antes da criação.

---

## Ver também

- [Arquitetura](ARQUITETURA.md) — camadas, estrutura, modelo de dados e infraestrutura.
