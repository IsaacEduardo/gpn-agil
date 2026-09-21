# Especificação da API: WebScan Bridge Local Agent

Contrato HTTP entre o browser e o agente local de digitalização.
Implementação em [`agent/`](../agent); notas técnicas em [webscan-agent.md](webscan-agent.md).

## Visão Geral

- **Endereço base:** `http://127.0.0.1:18090` (configurável; sempre loopback)
- **Protocolo:** JSON over HTTP/1.1
- **CORS:** apenas a origem exacta configurada no agente. `Access-Control-Allow-Origin: *` é proibido.

---

## Segurança de transporte

O agente recusa qualquer pedido que não passe os quatro filtros:

1. **Host** — `127.0.0.1`, `localhost` ou `::1`. Bloqueia *DNS rebinding*.
2. **Origin** — igualdade exacta contra `allowed_origins`. Obrigatório em
   `/scanners`, `/scan` e `/progress`; opcional em `/status` para diagnóstico local.
3. **`X-WebScan-Pairing`** — token local, comparado em tempo constante.
4. **Payload** — JSON, dentro de `max_body_bytes`, com cada parâmetro validado
   contra as **capacidades reais** do scanner escolhido.

Respostas autorizadas incluem `Access-Control-Allow-Origin` com a origem exacta e
`Vary: Origin`. Respostas recusadas não incluem cabeçalhos CORS.

O preflight `OPTIONS` devolve `Access-Control-Allow-Private-Network: true`,
exigido pelo Chrome para uma página HTTPS pública contactar a rede privada.

> A aplicação Laravel tem de autorizar esta origem em `connect-src`
> (`SecurityHeadersMiddleware`), ou o browser bloqueia o pedido antes de sair.

---

## Endpoints

### `GET /status`

```json
{
  "status": "online",
  "version": "1.0.0",
  "agent": "WebScanBridgeDaemon",
  "os": "windows_x64",
  "drivers_available": ["WIA"]
}
```

### `GET /scanners`

Lista vazia é resposta válida: significa que não há equipamento ligado.

```json
{
  "status": "success",
  "scanners": [
    {
      "id": "\\\\.\\Usb#Vid_04a9...",
      "name": "HP ScanJet Pro 2500 f1",
      "driver": "WIA",
      "is_default": true,
      "capabilities": {
        "sources": ["adf", "flatbed"],
        "color_modes": ["bw", "gray", "color"],
        "dpis": [150, 200, 300],
        "duplex_supported": false
      }
    }
  ]
}
```

### `POST /scan`

Pedido:

```json
{
  "scanner_id": "\\\\.\\Usb#Vid_04a9...",
  "dpi": 200,
  "color_mode": "color",
  "source": "adf",
  "duplex": true,
  "orientation": "portrait",
  "auto_deskew": true,
  "auto_crop": true
}
```

`dpi`, `color_mode`, `source` e `duplex` têm de constar das capacidades do
scanner indicado; caso contrário, `INVALID_REQUEST`.

Resposta:

```json
{
  "status": "success",
  "page_count": 3,
  "filename": "digitalizacao_20260916_101500.pdf",
  "pdf_base64": "JVBERi0xLjQK...",
  "pages": [
    { "page_number": 1, "mime_type": "image/jpeg", "preview_base64": "/9j/4AAQ..." }
  ]
}
```

- **`pdf_base64` é a única fonte do ficheiro.** PDF 1.4 multipágina, uma página
  por folha, com os JPEG originais embebidos via `/DCTDecode` — sem recompressão.
- `pages` são **miniaturas de pré-visualização**, não o documento. Podem vir
  vazias se o agente não tiver Pillow. O cliente nunca deve montar um PDF a
  partir delas.

O cliente valida a assinatura `%PDF-` antes de aceitar o ficheiro.

### `GET /progress`

Sondado enquanto o `POST /scan` decorre. Substitui o WebSocket: mesmo progresso
real, muito menos superfície.

```json
{
  "status": "success",
  "active": true,
  "current_page": 2,
  "total_pages": 3,
  "message": "A digitalizar pagina 2 de 3...",
  "percent": 66
}
```

---

## Erros

Formato único para todas as falhas:

```json
{ "status": "error", "error_code": "ADF_EMPTY", "message": "O alimentador esta vazio." }
```

O cliente decide pelo `error_code`; a `message` é texto para o operador.

| `error_code` | HTTP | Situação |
|---|---|---|
| `INVALID_REQUEST` | 400 | Payload malformado, fora dos limites ou incompatível com as capacidades |
| `PAIRING_REQUIRED` | 401 | Token ausente ou inválido |
| `ORIGIN_REJECTED` | 403 | Origem ou Host não autorizados |
| `SCANNER_NOT_FOUND` | 404 | O scanner indicado desapareceu |
| `NOT_FOUND` | 404 | Recurso inexistente |
| `ADF_EMPTY` | 409 | Alimentador vazio |
| `PAPER_JAM` | 409 | Papel encravado |
| `SCAN_CANCELLED` | 409 | Cancelado no equipamento |
| `SCAN_BUSY` | 429 | Já existe uma digitalização em curso |
| `DRIVER_UNAVAILABLE` | 503 | WIA indisponível ou driver em erro |
| `SCANNER_OFFLINE` | 503 | Equipamento desligado, ocupado ou a aquecer |
| `SCAN_TIMEOUT` | 504 | Excedeu `scan_timeout_seconds` |

Códigos gerados no cliente, sem resposta do agente:

| `error_code` | Situação |
|---|---|
| `OFFLINE` | Agente inacessível |
| `TIMEOUT` | Sem resposta dentro do prazo |
| `PDF_MISSING` | Resposta sem `pdf_base64` |
| `PDF_INVALID` | Conteúdo devolvido não é PDF |
| `SCANNER_REQUIRED` | Nenhum scanner selecionado |
