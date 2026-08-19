# Roteiro de Demonstração — GPN-AGIL (para cliente)

> Guião cronometrado (~8 min) para gravar um vídeo de apresentação do sistema.
> Segue a ordem do menu lateral. Fala com calma, mostra o resultado antes de explicar.

---

## 0. Antes de gravar (checklist de 1 minuto)

1. Corre o arranque: `pwsh -File scripts\demo-start.ps1` (abre 3 janelas: WEB, REVERB, QUEUE).
2. Abre o browser em **http://127.0.0.1:8000/login** — janela **maximizada**, zoom 100%.
3. Fecha separadores/extensões visíveis; ativa o modo "Não incomodar" do Windows.
4. Tem 2 janelas de browser prontas (uma normal + uma anónima) para demonstrar o **tempo real** e a **edição colaborativa** com dois utilizadores em simultâneo.
5. Password de todas as contas de demo: **`Demo@2026`**

| Conta | Perfil | Serve para mostrar |
|-------|--------|--------------------|
| `admin@gpnagil.com` | Administrador | Visão global, configuração, matriz de acesso |
| `pedro.camati@mail.ao` | Super Chefe de Gabinete | Painel do Gabinete, assinatura final |
| `carlos@mail.com` | Chefe de Gabinete | Visto de gabinete |
| `gilberto@mail.com` | Chefe de Departamento | Visto departamental, gestão de equipa |
| `isaac@mail.com` | Utilizador (Logística) | Criação de documentos e requisições |

**Gravar:** `Win + G` → botão de gravação (Xbox Game Bar), ou OBS Studio. Áudio do microfone ligado.

---

## 1. Abertura (0:00 – 0:30)

> *"Bom dia. Vou apresentar o GPN-AGIL, o sistema de gestão administrativa e documental
> do Governo Provincial do Namibe. É uma plataforma web única que substitui o papel e o
> e-mail em todo o ciclo de vida de um documento — da entrada ao arquivo — com fluxos de
> aprovação, assinatura digital, notificações em tempo real e controlo de acessos por perfil."*

- Mostra o ecrã de **login** (branding institucional). Entra com **`admin@gpnagil.com`**.

---

## 2. Dashboard (0:30 – 1:15)

- Cai no **Dashboard**. Aponta para os cartões de indicadores (documentos pendentes, requisições, prazos/SLA).
- Passa o rato pelo **sino de notificações** (canto superior) e pela **pesquisa global**.

> *"Cada perfil vê o seu painel. Os números são indicadores vivos: o que está pendente de si,
> o que está a aproximar-se do prazo. Tudo o que aparece respeita as permissões do utilizador."*

---

## 3. Documentos de Entrada — o fluxo central (1:15 – 3:15)

Menu: **Gestão Documental → Caixa de Entrada**.

1. **Lista/Caixa de Entrada** — mostra a listagem, filtros e o estado de cada documento.
2. Abre **um documento** existente. Percorre: dados de protocolo, anexos, histórico/timeline.
3. Explica o ciclo, apontando na timeline:

   > *"Um documento entra, recebe protocolo, é **encaminhado** ao departamento certo,
   > passa por **visto do departamento** e depois **visto do gabinete**, e por fim é **arquivado**.
   > Cada passo fica registado — quem, quando, com que parecer."*

4. Mostra **Por Receber** e **Vistos & Pareceres → Pendentes Dept. / Pendentes Gab.** (as badges com contadores).
5. **Demonstração viva do fluxo (opcional, forte):** encaminha um documento a um departamento; troca para a conta **`gilberto@mail.com`** (chefe de departamento) na 2ª janela e mostra que o documento **aparece nos pendentes dele** e o sino toca — **notificação em tempo real**.

> *"Repare: não enviei e-mail nenhum. O sistema notificou o responsável no instante em que
> o documento chegou à secretária dele."*

---

## 4. Documentos Internos + Edição Colaborativa (3:15 – 5:00) ⭐

Menu: **Gestão Documental → Arquivo & Modelos → Internos (Legado)** (e **Modelos**).

1. Mostra a lista de **Documentos Internos** e os **Modelos institucionais** (ofício, despacho, etc.).
2. **Criar a partir de modelo:** o cabeçalho institucional (logo → República → instituição → gabinete) é preenchido automaticamente.
3. Abre um rascunho no **editor**. Mostra a barra de formatação (tabelas, alinhamento, sublinhado…).
4. **⭐ Edição colaborativa em tempo real (destaque):**
   - Abre o **mesmo documento** na 2ª janela com outro utilizador (`isaac@mail.com`).
   - Escreve numa janela → aparece **instantaneamente** na outra, com o **cursor e o nome** do outro colaborador visíveis.

   > *"Dois funcionários a redigir o mesmo despacho ao mesmo tempo, como no Google Docs,
   > mas dentro do sistema do Governo e sem sair do circuito de aprovação."*

5. Explica o **workflow**: rascunho → análise → aprovado → **assinado digitalmente** (certificado P12).

---

## 5. Requisições (5:00 – 5:45)

Menu: **Serviços Operacionais → Requisições** (submenu: Produtos, Oficina, Serviços, Passagem).

- Mostra **Visão Geral** e **Pendentes**.
- Abre uma requisição: fluxo de **visto e assinatura** semelhante ao dos documentos.

> *"Requisições de produto, oficina, serviço ou passagem seguem o mesmo princípio:
> pedido → visto do responsável → assinatura. Rastreável de ponta a ponta."*

---

## 6. EDMS / Arquivo, Reservas e Termos (5:45 – 6:45)

1. **Arquivo & Modelos → Gerenciador EDMS** — pastas hierárquicas por departamento/gabinete;
   menciona **políticas de retenção** e **verificação de SLA** (arraste/organização de ficheiros).
2. **Administração & Credenciais → Reservas** — cria uma **reserva de espaço** ao vivo
   (rápido: título, data, sala) para mostrar o calendário a atualizar.
3. **Credenciais** e **Termos e Declarações** — mostra a geração com **QR code** de verificação.

> *"O arquivo não é uma pasta morta: tem retenção, prazos e pesquisa. E documentos como
> credenciais e termos saem já com QR code para validação de autenticidade."*

---

## 7. Notificações em tempo real (6:45 – 7:15)

- Abre o **sino** e a página **Notificações**.
- Recorda o episódio do ponto 3/4: as notificações chegaram **sem refrescar a página** (Reverb/WebSocket).

> *"Alertas de prazo, escalonamento de SLA e pendências chegam em tempo real, com preferências
> por utilizador e horário de silêncio."*

---

## 8. Administração e Segurança (7:15 – 8:00)

Menu: **Configuração Geral** (visível como admin).

- **Matriz de Acesso** — mostra a grelha de **perfis × permissões** (Spatie): tudo é controlado por papel.
- **Usuários**, **Departamentos**, **Gabinetes**, **Instituição** — a estrutura organizacional configurável.

> *"O acesso é granular: cada função vê e faz apenas o que lhe compete. A instituição, os
> gabinetes e os departamentos configuram-se sem programação."*

---

## 9. Encerramento (8:00 – 8:30)

> *"Em resumo: um só sistema para receber, tramitar, redigir, assinar, arquivar e auditar —
> com aprovações digitais, colaboração em tempo real e total rastreabilidade. Reduz papel,
> encurta prazos e dá visibilidade à gestão. Fico à disposição para uma prova de conceito
> com os vossos próprios documentos."*

- Termina no **Dashboard**. Para a gravação (`Win + G`).

---

## Notas de gravação

- **Ritmo:** ~15–20 s por ecrã; mostra primeiro, explica depois.
- **Rato:** movimentos lentos; clica com intenção. Aumenta o cursor no Windows se ajudar.
- **Reservas/Viaturas/Termos** têm poucos registos de exemplo — por isso o roteiro pede para **criar ao vivo** (fica mais convincente do que uma lista vazia).
- **Assistente IA:** o menu existe mas o motor está desligado por omissão. Para o demonstrar, define `CHATBOT_ENABLED=true` e uma chave de API no `.env` (opcional).
- Se algo falhar a meio, não pares a gravação — respira e recomeça o passo; cortas depois.
- **Duração-alvo:** 8–9 min. Para uma versão curta (3 min), faz só os pontos **2, 3, 4 e 8**.
