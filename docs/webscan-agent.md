# WebScan Bridge — Agente Local de Digitalização

Documentação técnica do agente que liga o EDMS aos scanners físicos dos postos
de trabalho. Para o guia do operador, ver [GUIA-SCANNER-OPERADOR.md](GUIA-SCANNER-OPERADOR.md).
Para o contrato HTTP, ver [webscan-bridge-spec.md](webscan-bridge-spec.md).

---

## 1. Porque existe um agente

O navegador não fala com drivers de digitalização. Não há API web para TWAIN,
WIA ou SANE, e não vai haver: dar a uma página acesso direto a hardware de
captura seria um problema de segurança sem solução razoável.

O agente resolve isto sendo um servidor HTTP minúsculo que corre **na máquina do
operador**, escuta **apenas em loopback** e traduz pedidos JSON em chamadas WIA.
A página do EDMS fala com `http://127.0.0.1:18090`; o agente fala com o scanner.

```
Browser (EDMS, HTTPS)  ──HTTP/JSON──>  Agente (127.0.0.1:18090)  ──COM──>  Driver WIA  ──USB──>  Scanner
```

---

## 2. Escolhas técnicas e porquê

| Decisão | Alternativa rejeitada | Razão |
|---|---|---|
| **Python 3.11 + pywin32** | C#/.NET, Node | WIA é COM; o pywin32 é a ligação estável e mantida. Empacota num `.exe` sem exigir runtime instalado. |
| **WIA, não TWAIN** | `pytwain` | O `pytwain` está sem manutenção e sofre de incompatibilidades DSM 32/64-bit que dariam falhas intermitentes em produção. Os fabricantes entregam driver WIA mesmo quando o equipamento também fala TWAIN. |
| **PDF em stdlib, `/DCTDecode`** | `img2pdf`, `reportlab`, Pillow | Os bytes JPEG do scanner são embebidos sem recompressão: ficheiro menor, imagem intacta para o OCR, zero dependências no caminho crítico. |
| **Sondagem em `/progress`** | WebSocket | Um endpoint de leitura dá o mesmo progresso real com muito menos superfície: sem handshake, sem gestão de ligações, sem `ws:` na CSP. |
| **Pillow opcional** | Obrigatório | Serve só para miniaturas. Se faltar, a digitalização e o PDF continuam a funcionar. |

### Listar nunca chama `Connect()`

Numa máquina com MFPs de rede registados mas desligados, `info.Connect()` bloqueia
**10 a 50 segundos por equipamento** antes de falhar. Com cinco registados, listar
scanners demorava minutos e o modal ficava pendurado — medido em hardware real
durante o desenvolvimento.

A listagem usa por isso apenas metadados, que são instantâneos. As capacidades
(ADF, duplex, DPI) exigem ligação e são apuradas em pano de fundo, dentro de um
orçamento curto, ficando em cache por `DeviceID`. Enquanto não houver resposta,
o equipamento é anunciado com capacidades conservadoras: **nunca oferecemos ADF
ou duplex que não tenhamos confirmado com o driver**.

### TWAIN — porque ficou de fora

O requisito era implementar TWAIN *se a biblioteca o suportasse de forma
robusta*. Não suporta. A costura fica aberta: basta uma classe que cumpra o
protocolo `ScannerAdapter` em [`scanners/base.py`](../agent/webscan_bridge/scanners/base.py)
e registá-la em `build_adapter()`. Nada no servidor, na segurança ou no
frontend precisa de mudar.

---

## 3. Estrutura

```
agent/
├── webscan_bridge/
│   ├── __main__.py        Arranque e CLI
│   ├── config.py          Configuração, token de pareamento
│   ├── errors.py          Taxonomia de erros → error_code
│   ├── pdf.py             Montagem do PDF multipágina
│   ├── server.py          HTTP, CORS, autenticação, limites
│   └── scanners/
│       ├── base.py        Contrato ScannerAdapter
│       ├── wia.py         Implementação WIA/COM
│       └── mock.py        Adaptador sem hardware
├── tests/                 38 testes, sem scanner
├── build.ps1              Gera dist\webscan-bridge.exe
├── install-servico.ps1    Instala e regista arranque automático
└── requirements.txt
```

---

## 4. Desenvolvimento

```powershell
cd agent
python -m pip install -r requirements.txt

# Sem scanner: adaptador simulado, gera páginas sintéticas
python -m webscan_bridge --driver mock --verbose

# Com scanner real
python -m webscan_bridge --verbose
```

Testes (não precisam de scanner nem de pywin32):

```powershell
cd agent
python -m unittest discover -s tests -t .
```

Da raiz do projeto: `npm run test:agent`.

---

## 5. Configuração

Ficheiro da **máquina** em `%ProgramData%\WebScanBridge\webscan-agent.json`,
escrito pelo instalador e partilhado por todas as contas do Windows desse PC. Se
não existir, o agente usa o antigo ficheiro por utilizador em
`%APPDATA%\WebScanBridge\` (postos instalados antes de 2026-09-30). Modelo em
[`agent/webscan-agent.example.json`](../agent/webscan-agent.example.json).

| Chave | Omissão | Função |
|---|---|---|
| `port` | `18090` | Porta loopback. |
| `allowed_origins` | `[]` | **Origens exactas** do EDMS autorizadas. Sem isto o agente recusa tudo. |
| `require_pairing` | `false` | Exige o código de pareamento. Desligado por omissão (ver §6). |
| `pairing_token` | vazio | Código, só usado com `require_pairing`. O instalador gera-o. |
| `driver` | `wia` | `wia` ou `mock`. |
| `max_pages` | `100` | Tecto de páginas por lote. |
| `max_body_bytes` | `65536` | Tamanho máximo do pedido. |
| `scan_timeout_seconds` | `180` | Tempo máximo de uma digitalização. |
| `preview_max_edge` | `320` | Lado maior das miniaturas, em píxeis. |

Variáveis de ambiente sobrepõem-se ao ficheiro: `WEBSCAN_PORT`,
`WEBSCAN_ALLOWED_ORIGINS`, `WEBSCAN_REQUIRE_PAIRING`, `WEBSCAN_PAIRING_TOKEN`,
`WEBSCAN_DRIVER`, `WEBSCAN_CONFIG`.

O agente **não guarda credenciais do EDMS**. O código de pareamento, quando ligado,
só serve para falar com este agente, nesta máquina, e não dá acesso a nada na aplicação.

---

## 6. Modelo de segurança

Filtros independentes, obrigatórios para `/scanners` e `/scan`:

1. **Host** — tem de ser `127.0.0.1`, `localhost` ou `::1`. Bloqueia *DNS
   rebinding*, em que um domínio hostil resolve para o loopback.
2. **Origin** — tem de constar de `allowed_origins`, comparada por igualdade
   exacta. O browser define este cabeçalho em pedidos cross-origin e a página
   não o pode falsificar. É isto que impede um site externo de acionar o scanner.
3. **Código de pareamento (opcional, desligado por omissão)** — `X-WebScan-Pairing`,
   comparado com `hmac.compare_digest`. Não acrescenta nada contra sites externos
   (os filtros 1 e 2 já os travam) nem contra software local (lê o ficheiro de
   configuração). Com a configuração por máquina seria o mesmo para todas as
   contas, pelo que também não as distingue. Desde 2026-09-30 fica desligado: o
   custo — funcionários a precisar de um técnico para reintroduzir o código —
   não tinha contrapartida. Liga-se com `-ExigirPareamento` no instalador.
4. **Payload** — `Content-Type` JSON, `Content-Length` dentro do limite, e cada
   parâmetro validado **contra as capacidades reais** do scanner selecionado.

Garantias adicionais:

- `Access-Control-Allow-Origin` devolve a origem exacta, nunca `*`, e só quando
  autorizada. Respostas recusadas não levam cabeçalhos CORS.
- `Access-Control-Allow-Private-Network: true` no preflight, exigido pelo Chrome
  para uma página pública contactar a rede privada.
- Uma digitalização de cada vez (`SCAN_BUSY`, HTTP 429).
- Limite de tempo cooperativo, verificado entre páginas (`SCAN_TIMEOUT`).
- **Os registos não contêm conteúdo de documentos**, nomes de ficheiro nem dados
  pessoais — apenas contagem de páginas, bytes e duração.

### O que o agente não protege

Um utilizador com sessão iniciada na própria máquina pode sempre usar o scanner
— tal como pode usá-lo fisicamente. O agente defende o scanner de **páginas web**
e de **outros dispositivos na rede**, não de quem está sentado ao PC. Não guarda
documentos: digitaliza o papel que está no scanner e entrega-o a quem pediu.

---

## 7. Empacotamento e instalação

```powershell
cd agent
.\build.ps1                                    # corre testes e gera dist\webscan-bridge.exe
# PowerShell como Administrador:
.\install-servico.ps1                          # produção: https://ondaka-gph.ao
.\install-servico.ps1 -Origem https://ondaka-gph.ao, http://localhost   # + desenvolvimento
```

**Instala-se uma vez por PC**, não por conta. Sem `-Origem`, usa a produção
(`https://ondaka-gph.ao`). O instalador exige Administrador e:

- copia o executável para `%ProgramFiles%\WebScanBridge`;
- escreve a configuração da máquina em `%ProgramData%\WebScanBridge`;
- regista o arranque em `HKLM:\...\Run` — **todas as contas** do PC ficam com o
  scanner ao entrar no Windows, sem instalar nem introduzir nada;
- retira as instalações antigas por utilizador (`%LOCALAPPDATA%`), que num PC
  partilhado disputavam a porta;
- sem `-ExigirPareamento`, não há código: o scanner liga-se sozinho no EDMS.

#### PCs com várias contas do Windows

O agente arranca em cada sessão, mas só um pode ter a porta 18090. Quem a tem
serve todas as contas (a configuração é da máquina). Os outros ficam **de
reserva** — tentam de 10 em 10 segundos e assumem quando a sessão que a tinha
termina, sem ninguém reiniciar nada. O agente já não liga `SO_REUSEADDR`, que no
Windows deixava dois agentes escutar a mesma porta e o browser falar ora com um,
ora com outro.

Recomenda-se que nesses PCs se **termine sessão** em vez de "Trocar de
utilizador" (pode impor-se por GPO com `HideFastUserSwitching`): com a sessão
anterior aberta, a digitalização corre no agente dessa sessão.

Origens configuradas actualmente (ver `webscan-agent.example.json`):

| Ambiente | Origem |
|---|---|
| Produção | `https://ondaka-gph.ao` |
| Desenvolvimento | `http://localhost` |
| Desenvolvimento (`artisan serve`) | `http://127.0.0.1:8000` |

A comparação é por igualdade exacta de esquema, host e porta. `http://localhost`
e `http://127.0.0.1:8000` são origens **diferentes** para o browser, por isso
ambas constam da lista.

### HTTPS (resolvido em 2026-09-28)

A produção está em **`https://ondaka-gph.ao`** (Let's Encrypt, renovação
automática). O acesso por `http://162.35.116.198` redirecciona para o domínio e
já não serve o EDMS — por isso essa origem deixou de constar do modelo. Postos
instalados antes de 2026-09-28 precisam de `https://ondaka-gph.ao` em
`allowed_origins` (reinstalar ou editar o JSON, sem BOM) e de reiniciar o agente.

O motivo, para referência: o Chrome só autoriza uma página a contactar o
*loopback* quando essa página vem de um **contexto seguro**. O cabeçalho `Access-Control-Allow-Private-Network`
que o agente devolve é o mecanismo de aceitação, mas ele próprio só é honrado em
HTTPS — em HTTP simples o pedido é bloqueado antes de o agente ser contactado, e
o operador vê "Scanner Offline" sem explicação.

Com a produção em `http://162.35.116.198` a digitalização **não funcionava no
Chrome**. As opções que se consideraram, por ordem de preferência (foi adoptada a 1):

1. **Certificado e domínio** para o EDMS (o
   [CHECKLIST_PRODUCAO_SEGURANCA.md](CHECKLIST_PRODUCAO_SEGURANCA.md) já traz a
   configuração nginx com Let's Encrypt). Resolve isto e o facto de credenciais
   e documentos circularem hoje em texto claro.
2. **Certificado interno** emitido pela instituição para o IP ou para um nome
   interno, distribuído às máquinas por GPO.
3. Enquanto isso, testar em `http://localhost`, que o browser considera seguro.

### Atualização

Voltar a correr o instalador (pára os agentes, substitui o `.exe` e mantém o
código, se houver). A configuração vive noutra pasta e sobrevive. Para um parque grande, distribuir por GPO ou pelo gestor de
software, mantendo `allowed_origins` no ficheiro de configuração.

---

## 8. Diagnóstico

### Verificador automático do posto

Antes de diagnosticar à mão, corra o verificador. Ele executa os casos do
roteiro que não precisam de papel — comunicação, pareamento, origem, deteção de
equipamento, limites — e diz o que falta validar com uma pessoa ao scanner:

```powershell
cd agent
python verificar-posto.py --origem https://ondaka-gph.ao
```

Avisa também quando a origem do EDMS é HTTP simples, que é a causa mais provável
de "Scanner Offline" com o agente aparentemente a funcionar.

### OCR: sem rasterizador o documento fica cego

Um PDF de scanner é imagem pura, sem camada de texto. Para o tornar pesquisável
o OCR precisa de rasterizar as páginas, e isso exige **um** destes no servidor:
`pdftoppm` (Poppler, preferido), Ghostscript ou ImageMagick. Sem nenhum, o anexo
é guardado e indexado apenas pelo assunto que o operador escreveu.

```powershell
winget install oschwartz10612.Poppler     # Windows
# defina OCR_PDFTOPPM_PATH no .env se o binário não estiver no PATH
```

```bash
sudo apt install poppler-utils            # servidor Linux
```

Confirme com `php artisan tinker --execute='print_r(app(\App\Services\Ocr\OcrService::class)->diagnose());'`
— o campo `converters` tem de ter pelo menos um `true`. O worker que consome a
fila está documentado no [GUIA_HOSPEDAGEM.md §5.4](GUIA_HOSPEDAGEM.md).

### Sintomas comuns

| Sintoma | Causa provável | Verificação |
|---|---|---|
| "Scanner Offline" com o agente a correr | CSP do EDMS ou origem não autorizada | Consola do browser: erro de CSP ou `ORIGIN_REJECTED`. Confirmar `connect-src` e `allowed_origins`. |
| Pede código de pareamento | Posto instalado antes de 2026-09-30 ou com `-ExigirPareamento` | Reinstalar com o instalador actual (sem código); com código, `webscan-bridge.exe --print-token` e introduzir uma vez por browser. |
| "Nenhum scanner detetado" | Driver WIA ausente ou equipamento desligado | Painel de Controlo → Dispositivos e Impressoras. Testar com a app Digitalizar do Windows. |
| Agente não arranca | Porta ocupada | `netstat -ano | findstr 18090` |
| Erro `DRIVER_UNAVAILABLE` | pywin32 em falta | Reinstalar o agente; `python -c "import win32com.client"`. |

Log detalhado: `webscan-bridge.exe --verbose`.

Teste rápido sem browser (substituir o token):

```powershell
curl.exe -H "Origin: https://edms.gov.ao" -H "X-WebScan-Pairing: SEU_TOKEN" http://127.0.0.1:18090/status
```

---

## 9. Roteiro de teste manual com scanner físico

A suite automática cobre tudo **menos o hardware**. Este roteiro é obrigatório
antes de aprovar o agente num parque novo. Registar modelo, driver e resultado.

### Preparação
- Agente instalado, `allowed_origins` com a origem real do EDMS.
- Scanner WIA ligado; 3 folhas A4 com texto no alimentador.

### Casos

| # | Passos | Resultado esperado |
|---|---|---|
| 1 | Agente parado, abrir o registo de entrada | Indicador "Scanner Offline"; botão **Digitalizar do Scanner** desativado |
| 2 | Arrancar o agente, recarregar | Indicador "Scanner ativo"; botão ativo |
| 3 | Abrir o modal (posto sem código) / com código errado (posto com `-ExigirPareamento`) | Scanner pronto sem pedir nada / painel de pareamento visível e início bloqueado |
| 4 | Introduzir o código correto | Lista com o nome real do equipamento e o driver |
| 5 | Verificar as opções | DPI, cor, ADF e duplex refletem o equipamento; duplex desativado em scanner simplex |
| 6 | Desligar o scanner e recarregar | "Nenhum scanner detetado"; início bloqueado |
| 7 | 3 folhas no ADF → Iniciar | Progresso avança por página; 3 miniaturas |
| 8 | Anexar PDF | Ficheiro aparece na lista de anexos com dimensão plausível |
| 9 | Retirar as folhas → Iniciar | Erro legível de alimentador vazio; a aplicação não quebra |
| 10 | Provocar encravamento | Mensagem de papel encravado; repetição funciona |
| 11 | Duplex com 2 folhas impressas dos dois lados | 4 páginas na ordem correta |
| 12 | Vidro (flatbed), 1 folha | 1 página |
| 13 | Registar o documento | Guardado sem erro de validação |
| 14 | Abrir o anexo no Acrobat/Edge | Todas as páginas, legíveis, na ordem correta |
| 15 | Lote grande (30+ folhas) | Ou completa, ou falha com mensagem clara — nunca PDF truncado em silêncio |
| 16 | Segundo scan sem fechar o modal | Substitui o lote anterior, sem páginas misturadas |
| 17 | Dois separadores a digitalizar ao mesmo tempo | O segundo recebe "já existe uma digitalização em curso" |
| 18 | Abrir `https://example.com` e tentar `fetch` ao agente pela consola | Bloqueado por CORS; o scanner não dispara |

### TWAIN
Não implementado. Se o equipamento só falar TWAIN, o caso 4 mostrará lista
vazia — é o comportamento esperado, não um defeito.
