# Guia de Hospedagem — GPN-AGIL

> Guia de **escolha e dimensionamento** de hospedagem, fundamentado na análise do código-fonte
> (Laravel 12 / PHP 8.2, ~90 migrações, filas, OCR, web push, assinatura digital, chatbot RAG).
> Para o **passo a passo de instalação**, ver [MANUAL_DEPLOY.md](MANUAL_DEPLOY.md).
> Verificado em: 2026-06-22.

---

## 0. Resumo executivo (TL;DR)

- O **núcleo** do sistema (documentos, requisições, frota, reservas, RBAC, PDF, assinatura, auditoria)
  roda bem até em **hospedagem compartilhada (cPanel)**.
- Mas 4 recursos exigem **infraestrutura que shared hosting normalmente não oferece**:
  **OCR** (binário tesseract), **filas/processamento assíncrono** (processo persistente),
  **chatbot RAG** (servidor Ollama) e **tempo real** (broadcast/websockets).
- **Recomendação:** para usar o sistema completo, hospede num **VPS** (mínimo **2 vCPU / 4 GB RAM**;
  **8 GB** se ativar o chatbot com Ollama). Há `docker-compose.yml` pronto que já sobe tudo.
- Hospedagem compartilhada só é adequada se você abrir mão de OCR automático e chatbot.

---

## 1. O que o sistema realmente precisa (análise do código)

### 1.1. Componentes obrigatórios (núcleo)

| Componente | Evidência no código | Requisito de hospedagem |
|---|---|---|
| **PHP 8.2+** | `composer.json` → `"php": "^8.2"` | Qualquer host com PHP 8.2+ |
| **MySQL / MariaDB** | `config/database.php`, ~90 migrações em `database/migrations/` | 1 banco MySQL 5.7+/MariaDB 10.3+ (alvo: MySQL 8.0) |
| **Extensões PHP** | `docker/php/Dockerfile` | `gd`, `mbstring`, `pdo_mysql`, `zip`, `xml`, `intl`, `opcache`, `openssl`, `bcmath`, `fileinfo` |
| **Composer 2** | build de dependências | Local ou no servidor |
| **Assets (Vite)** | `package.json` → `vite build` | `npm run build` (faça **localmente**; gera `public/build`) |
| **Storage gravável** | `config/filesystems.php` | `storage/` e `bootstrap/cache/` com escrita (775) |

### 1.2. Componentes que definem a escolha do host

| Recurso | O que precisa | Evidência | Em shared hosting? |
|---|---|---|---|
| **Filas / Jobs assíncronos** | Processo persistente *ou* cron por minuto | `app/Jobs/ProcessarOcrAnexo`, `CheckRetentionPolicy`, `ArchiveDocumentJob` + jobs de indexação do chatbot | Parcial (via cron; sem Supervisor) |
| **Agendador (cron)** | `php artisan schedule:run` a cada minuto | `routes/console.php` → `CheckRetentionPolicy` 02:00, `docs:check-sla` 08:00 | ✅ Sim (Cron Jobs do cPanel) |
| **OCR de anexos** | Binário **`tesseract`** + idiomas `por`/`eng` | `app/Jobs/ProcessarOcrAnexo.php` usa `thiagoalessio\TesseractOCR`; caminho configurável em `config/services.php` (`ocr.path` / `OCR_BINARY_PATH`) | ❌ Raramente (binário ausente) |
| **Web Push (notificações)** | Chaves VAPID + worker da fila `notifications` | `minishlink/web-push`, `routes/console.php` (`push:vapid:generate`) | Parcial (precisa do worker) |
| **Tempo real (broadcast)** | Driver de broadcast; opcionalmente Pusher/websockets | `config/broadcasting.php`, `routes/channels.php`; padrão `BROADCAST_CONNECTION=log` | Funciona sem tempo real (degrada para polling/refresh) |
| **Assinatura digital** | OpenSSL + certificados `.p12` por utilizador | `app/Services/SignatureService`, migrações `user_certificates` | ✅ Sim (extensão `openssl`) |
| **Cache / Sessão** | `database` (simples) ou **Redis** (recomendado) | `config/cache.php`, `config/session.php`; `docs/deploy-performance.md` recomenda Redis | `database` ✅ / Redis ❌ normalmente |

### 1.3. Recursos opcionais (desligados por padrão) — alto impacto de infra

| Recurso | Flag | O que precisa |
|---|---|---|
| **Assistente IA** | `FEATURE_ASSISTENTE=false` | Chave **Anthropic API** (`ANTHROPIC_API_KEY`) — serviço externo, só rede de saída |
| **Chatbot RAG** | `CHATBOT_ENABLED=false` | **Embeddings**: por padrão **Ollama on-premise** (`OLLAMA_BASE_URL=http://localhost:11434`, modelo `nomic-embed-text`, 768 dim) — exige um **servidor Ollama** (RAM/CPU dedicados). Alternativa: `EMBEDDINGS_DRIVER=openai` (API externa). **Geração**: Anthropic API. **Vetores**: guardados no próprio MySQL (`CHATBOT_VECTOR_DRIVER=mysql`). |

> ⚠️ O **chatbot com Ollama** é o recurso mais pesado: o Ollama é um processo de servidor que carrega o
> modelo de embeddings em memória. Não roda em shared hosting. Reserve **+2 GB de RAM** (ou use o driver
> `openai`/desligue o chatbot). Tudo isto está **desligado por padrão** — o sistema funciona sem.

---

## 2. Opções de hospedagem (comparação)

| Critério | A) Compartilhada (cPanel) | B) VPS (recomendado) | C) Docker (VPS/servidor próprio) |
|---|---|---|---|
| Núcleo (docs, requisições, RBAC, PDF, assinatura) | ✅ | ✅ | ✅ |
| OCR automático (tesseract) | ❌ (salvo binário disponível) | ✅ | ✅ (já incluso na imagem) |
| Filas com Supervisor | ❌ (só cron) | ✅ | ✅ (serviço `queue`) |
| Redis (cache/fila/sessão) | ❌ normalmente | ✅ | ✅ (serviço `redis`) |
| Chatbot RAG (Ollama) | ❌ | ✅ (com RAM suficiente) | ✅ (adicionar serviço) |
| Tempo real / websockets | ❌ | ✅ | ✅ |
| Controle / custo | Baixo custo, baixo controle | Médio custo, controle total | Médio custo, reprodutível |
| **Indicado para** | Piloto/demonstração do núcleo | **Produção completa** | Produção + equipe DevOps |

**Veredito:** sendo um sistema de órgão público com OCR, assinatura digital, auditoria e (opcional)
chatbot, o destino correto é um **VPS** — idealmente via **Docker**, que já está configurado no repositório.

---

## 3. Dimensionamento (sizing)

Meta de desempenho declarada em `docs/deploy-performance.md`: **>15 utilizadores concorrentes, P95 < 500 ms**.

| Cenário | vCPU | RAM | Disco | Observação |
|---|---|---|---|---|
| Núcleo + OCR + filas (sem chatbot) | 2 | **4 GB** | 40 GB SSD | Suficiente para o uso descrito |
| + Chatbot com **Ollama** on-premise | 4 | **8 GB** | 60 GB SSD | Ollama carrega o modelo em memória |
| + Crescimento / muitos PDFs/anexos | 4 | 8–16 GB | 80+ GB SSD | Disco cresce com documentos digitalizados |

> O disco é dominado pelos **anexos/documentos** (`storage/app/private_docs`) e backups. Planeje
> crescimento e backup desse diretório + dump do MySQL.

---

## 4. Caminho A — Hospedagem compartilhada (cPanel)

Use apenas para **piloto/demonstração do núcleo**. Resumo (passo a passo completo em
[MANUAL_DEPLOY.md §7](MANUAL_DEPLOY.md)):

1. PHP 8.2+ selecionado no cPanel (MultiPHP), com as extensões da §1.1 ativas.
2. Banco MySQL criado (usuário com *All Privileges*).
3. Estrutura segura: projeto **fora** de `public_html`; conteúdo de `public/` **dentro**; ajustar `index.php`.
4. `.env` limpo com drivers `database` (sem Redis) e `DOCS_STORAGE_DISK=private`.
5. Migrações/caches via rota utilitária `/deploy-setup` (defina um `APP_DEPLOY_KEY` forte — **não** use a chave padrão).
6. **Cron** de minuto em minuto: `php artisan schedule:run` (+ um cron de fila, se quiser Web Push).

**Limitações aceitas neste caminho:** sem OCR automático (tesseract ausente), sem chatbot, sem tempo real.
O upload de documentos continua funcionando; os jobs de OCR apenas falham silenciosamente para `failed_jobs`.

---

## 5. Caminho B — VPS (recomendado, instalação manual)

Pilha alvo: **Nginx + PHP-FPM 8.2 + MySQL 8 + Redis 7 + Supervisor**.

### 5.1. Instalar pacotes (Ubuntu/Debian, exemplo)

```bash
sudo apt update
sudo apt install -y nginx mysql-server redis-server \
  php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml php8.2-zip \
  php8.2-gd php8.2-intl php8.2-bcmath php8.2-curl php8.2-opcache \
  tesseract-ocr tesseract-ocr-por tesseract-ocr-eng unzip git
# Composer: instalar conforme getcomposer.org
```

### 5.2. Aplicação

```bash
sudo mkdir -p /var/www/gpn-agil && cd /var/www/gpn-agil
# clonar o repositório aqui
composer install --no-dev --optimize-autoloader
# .env: ver MANUAL_DEPLOY.md §4 (em VPS pode usar Redis)
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan optimize        # config + route + view cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

> Em VPS, o `.env` pode (e deve) usar Redis: `CACHE_STORE=redis`, `SESSION_DRIVER=redis`,
> `QUEUE_CONNECTION=redis`, `REDIS_CLIENT=phpredis` (instale `php8.2-redis`).

### 5.3. Nginx (Document Root → `public/`)

Use o exemplo de `HOSTING_GUIDE.md` (server block com `root .../public`, `try_files`, e `fastcgi_pass`
para o socket do PHP-FPM 8.2). Garanta HTTPS (Let's Encrypt / certbot) e `APP_URL=https://...`.

### 5.4. Worker de fila (Supervisor) — necessário para OCR, Push, indexação

```ini
[program:gpn-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/gpn-agil/artisan queue:work --queue=notifications,default --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/gpn-agil/storage/logs/worker.log
```

### 5.5. Agendador (cron único)

```
* * * * * cd /var/www/gpn-agil && php artisan schedule:run >> /dev/null 2>&1
```

Cobre `CheckRetentionPolicy` (02:00) e `docs:check-sla` (08:00) — não crie crons separados por tarefa.

---

## 6. Caminho C — Docker (mais simples e reprodutível)

O repositório já traz `docker-compose.yml` com os serviços: **nginx, php, mysql, redis, queue, scheduler**
(o `php` já inclui tesseract + por/eng e a extensão phpredis — ver `docker/php/Dockerfile`).

```bash
cp .env.docker .env
# editar .env: definir APP_KEY, senhas de banco fortes, APP_URL, e-mail, etc.
docker compose up -d --build
docker compose exec php php artisan key:generate
docker compose exec php php artisan migrate --force
docker compose exec php php artisan storage:link
docker compose exec php php artisan optimize
```

- A fila e o agendador já rodam como serviços próprios (`queue`, `scheduler`) — **não** precisa de Supervisor.
- Para produção, coloque um proxy reverso com HTTPS à frente do `nginx` (ex.: Caddy/Traefik) e ajuste
  `APP_URL`. Troque as credenciais de exemplo do `.env.docker` (são apenas para desenvolvimento local).
- **Chatbot (opcional):** adicione um serviço `ollama` ao compose, defina `OLLAMA_BASE_URL=http://ollama:11434`,
  `CHATBOT_ENABLED=true`, e baixe o modelo (`ollama pull nomic-embed-text`). Reserve a RAM da §3.

---

## 7. Pós-implantação (todos os caminhos)

1. **Criar índices/caches**: `php artisan migrate --force` aplica as migrações de índice de performance.
2. **Otimizar**: `config:cache`, `route:cache`, `view:cache` (ou `php artisan optimize`).
3. **Segurança**: `APP_DEBUG=false`; desativar a rota `/deploy-setup` (zerar `APP_DEPLOY_KEY`);
   documentos em disco `private`; HTTPS obrigatório. Detalhes em [MANUAL_DEPLOY.md §9](MANUAL_DEPLOY.md).
4. **Web Push (se usar)**: gerar chaves com `php artisan push:vapid:generate` e preencher `VAPID_*` no `.env`.
5. **Backup**: agendar dump do MySQL **+** cópia de `storage/app/private_docs` (os anexos/documentos reais).
6. **Monitoramento**: log rotate; acompanhar `failed_jobs`; em staging usar `laravel/telescope` para N+1.

---

## 8. Matriz de decisão rápida

| Sua necessidade | Caminho |
|---|---|
| Mostrar o sistema rápido, baixo custo, sem OCR/chatbot | **A) cPanel** |
| Produção real com OCR, filas, push e assinatura | **B) VPS** ou **C) Docker** |
| Quero o setup pronto e reprodutível, com equipe técnica | **C) Docker** |
| Vou ativar o chatbot RAG (Ollama) | **B/C com 8 GB RAM** ou usar `EMBEDDINGS_DRIVER=openai` |

---

## Documentos relacionados

- [MANUAL_DEPLOY.md](MANUAL_DEPLOY.md) — passo a passo de instalação (SSH e cPanel), `.env` modelo, troubleshooting.
- [HOSTING_GUIDE.md](HOSTING_GUIDE.md) — exemplo de Nginx + Supervisor.
- [docs/ARQUITETURA.md](docs/ARQUITETURA.md) — arquitetura e componentes do sistema.
- [docs/deploy-performance.md](docs/deploy-performance.md) — metas e checklist de performance.
- [docs/CHATBOT.md](docs/CHATBOT.md) — detalhes do módulo de chatbot/RAG.
