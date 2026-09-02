# Especificação da API: WebScan Bridge Local Agent

## Visão Geral
O **WebScan Bridge Local** é um serviço em segundo plano (daemon/serviço) executado na máquina do utilizador que estabelece a ponte entre navegadores web (via HTTP/WebSocket local) e scanners de documentos físicos conectados via drivers TWAIN, WIA (Windows) ou SANE (Linux).

- **Endereço Base HTTP:** `http://127.0.0.1:18090`
- **Endereço Base WebSocket:** `ws://127.0.0.1:18090/ws`
- **Protocolo de Comunicação:** JSON over HTTP / WebSocket
- **CORS:** Headers `Access-Control-Allow-Origin: *` habilitados para aceitar requisições de origens da aplicação EDMS.

---

## Endpoints HTTP da API

### 1. Verification / Healthcheck
Retorna o estado do agente local para que o frontend exiba o indicador de scanner ativo.

- **Método:** `GET /status`
- **Response `200 OK`:**
```json
{
  "status": "online",
  "version": "1.0.0",
  "agent": "WebScanBridgeDaemon",
  "os": "windows_x64",
  "drivers_available": ["TWAIN", "WIA"]
}
```

---

### 2. Listar Scanners Conectados
Retorna a lista de dispositivos de digitalização reconhecidos pelo sistema operacional.

- **Método:** `GET /scanners`
- **Response `200 OK`:**
```json
{
  "status": "success",
  "scanners": [
    {
      "id": "twain_fujitsu_fi7160",
      "name": "Fujitsu fi-7160 (TWAIN)",
      "driver": "TWAIN",
      "is_default": true,
      "capabilities": {
        "sources": ["adf", "flatbed"],
        "color_modes": ["bw", "gray", "color"],
        "dpis": [150, 200, 300, 600],
        "duplex_supported": true
      }
    },
    {
      "id": "wia_hp_scanjet_pro",
      "name": "HP ScanJet Pro 2500 f1 (WIA)",
      "driver": "WIA",
      "is_default": false,
      "capabilities": {
        "sources": ["flatbed", "adf"],
        "color_modes": ["bw", "color"],
        "dpis": [200, 300],
        "duplex_supported": false
      }
    }
  ]
}
```

---

### 3. Iniciar Digitalização (HTTP Post)
Solicita ao scanner a captura das páginas de acordo com as configurações especificadas.

- **Método:** `POST /scan`
- **Request Body:**
```json
{
  "scanner_id": "twain_fujitsu_fi7160",
  "dpi": 200,
  "color_mode": "color",
  "source": "adf",
  "duplex": true,
  "auto_deskew": true,
  "auto_crop": true,
  "output_format": "pdf"
}
```

- **Response `200 OK`:**
```json
{
  "status": "success",
  "job_id": "job_98412384",
  "page_count": 2,
  "pdf_base64": "data:application/pdf;base64,JVBERi0xLj...=",
  "pages": [
    {
      "page_number": 1,
      "image_base64": "data:image/jpeg;base64,/9j/4AAQSkZJRg...",
      "width": 1654,
      "height": 2339
    },
    {
      "page_number": 2,
      "image_base64": "data:image/jpeg;base64,/9j/4AAQSkZJRg...",
      "width": 1654,
      "height": 2339
    }
  ]
}
```

- **Response Erro (`400` / `500`):**
```json
{
  "status": "error",
  "error_code": "PAPER_JAM",
  "message": "Atolamento de papel detectado no alimentador (ADF)."
}
```

---

## Comunicação via WebSocket (Streaming de Progresso)

- **Conexão:** `ws://127.0.0.1:18090/ws`

### Mensagens Enviadas pelo Frontend ao Agente:
```json
{
  "action": "START_SCAN",
  "params": {
    "scanner_id": "twain_fujitsu_fi7160",
    "dpi": 300,
    "color_mode": "bw",
    "source": "adf",
    "duplex": false
  }
}
```

### Eventos Emitidos pelo Agente ao Frontend:
1. **Progresso da Digitalização:**
   ```json
   { "event": "PROGRESS", "job_id": "job_123", "current_page": 1, "message": "Digitalizando página 1..." }
   ```
2. **Página Capturada em Tempo Real:**
   ```json
   { "event": "PAGE_CAPTURED", "job_id": "job_123", "page_number": 1, "image_base64": "data:image/jpeg;base64,..." }
   ```
3. **Conclusão:**
   ```json
   { "event": "SCAN_COMPLETE", "job_id": "job_123", "total_pages": 3, "pdf_base64": "data:application/pdf;base64,..." }
   ```
4. **Erro de Hardware:**
   ```json
   { "event": "ERROR", "error_code": "SCANNER_OFFLINE", "message": "O scanner selecionado está desligado ou desconectado." }
   ```
