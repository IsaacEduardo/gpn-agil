# Manual de Deploy — GPN-AGIL

> Manual verificado contra o código-fonte real do projeto (Laravel 12 / PHP 8.2).
> Substitui e corrige instruções imprecisas de `DEPLOY_CPANEL.md` (ver §9).
> Última verificação: 2026-06-21.

---

## 0. Índice

1. [Requisitos do servidor](#1-requisitos-do-servidor)
2. [Limitações de hospedagem compartilhada (leia antes)](#2-limitações-de-hospedagem-compartilhada-leia-antes)
3. [Preparação local (na sua máquina)](#3-preparação-local-na-sua-máquina)
4. [Arquivo `.env` de produção (modelo limpo)](#4-arquivo-env-de-produção-modelo-limpo)
5. [Banco de dados](#5-banco-de-dados)
6. [Caminho A — Deploy via SSH (recomendado)](#6-caminho-a--deploy-via-ssh-recomendado)
7. [Caminho B — Deploy via cPanel sem SSH](#7-caminho-b--deploy-via-cpanel-sem-ssh)
8. [Pós-deploy: agendador (cron) e filas](#8-pós-deploy-agendador-cron-e-filas)
9. [Segurança (obrigatório)](#9-segurança-obrigatório)
10. [Resolução de problemas](#10-resolução-de-problemas)

---

## 1. Requisitos do servidor

Confirmados em `composer.json` e `docker/php/Dockerfile`:

| Item | Versão / detalhe |
|---|---|
| **PHP** | `^8.2` (8.2 ou superior) |
| **Composer** | 2.x |
| **Banco** | MySQL 5.7+ / MariaDB 10.3+ |
| **Extensões PHP** | `gd` (com freetype+jpeg), `mbstring`, `pdo_mysql`, `zip`, `xml`, `intl`, `opcache`, `bcmath`, `ctype`, `json`, `openssl`, `fileinfo` |
| **Node.js** | Apenas para `npm run build` — pode ser feito **na sua máquina**, não no servidor |
| **Binário `tesseract`** | `tesseract-ocr` + `tesseract-ocr-por` + `tesseract-ocr-eng` — **somente** se for usar OCR de anexos (ver §2) |
| **Redis** (opcional) | Só se quiser cache/fila/sessão via Redis. Em cPanel compartilhado normalmente **não existe** — use `database` (ver §4) |

---

## 2. Limitações de hospedagem compartilhada (leia antes)

Em cPanel compartilhado (ex.: InfinityLink) há restrições reais que afetam este sistema:

- **Filas (queue):** não há Supervisor para manter um worker vivo. Soluções em §8.
- **OCR (`tesseract`):** o job `ProcessarOcrAnexo` chama o binário `tesseract`. Se o servidor não o tiver, **o OCR falha** — mas o upload do documento **continua funcionando** (o OCR é assíncrono via fila). Para desligar tentativas de OCR, basta não processar a fila `default`/onde ele cai, ou aceitar que esses jobs falhem e fiquem em `failed_jobs`. O resto do sistema não depende de OCR.
- **Notificações Web Push:** usam a fila `notifications`. Sem worker, não saem. Configure o cron de fila em §8.
- **Redis:** raramente disponível. Use drivers `database`/`file` (modelo `.env` em §4).
- **SSH:** se a hospedagem oferecer (cPanel → "SSH Access" / "Terminal"), use o **Caminho A** — é muito mais simples e confiável.

---

## 3. Preparação local (na sua máquina)

Execute na raiz do projeto, **antes** de enviar arquivos.

```bash
# 1. Compilar assets de produção (gera public/build)
npm install
npm run build

# 2. Instalar dependências PHP de produção (sem pacotes de dev)
composer install --no-dev --optimize-autoloader
```

> Se o deploy for por **SSH com Composer no servidor** (Caminho A), você pode pular o passo 2 aqui e rodá-lo no servidor. O `npm run build` deve ser feito localmente de qualquer forma (Node geralmente não está no shared hosting).

---

## 4. Arquivo `.env` de produção (modelo limpo)

> ⚠️ **Não copie o `.env.example` como está** — ele contém chaves duplicadas que terminam apontando para Redis. Use o modelo abaixo, que é limpo e adequado a cPanel sem Redis.

```ini
APP_NAME="GPN-AGIL"
APP_ENV=production
APP_KEY=                      # gerar (ver abaixo)
APP_DEBUG=false
APP_URL=https://seu-dominio.com

# Chave da rota utilitária /deploy-setup.
# DEIXE VAZIO para manter a rota DESATIVADA (recomendado após o deploy).
# NÃO use "InfinityDeploy2024!" — é rejeitada em produção pelo código.
APP_DEPLOY_KEY=

APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=en

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error

# --- Banco de dados (preencher com os dados do cPanel) ---
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=prefixo_gpn
DB_USERNAME=prefixo_user
DB_PASSWORD=senha_forte_do_banco

# --- Drivers sem Redis (adequado a shared hosting) ---
CACHE_STORE=database
SESSION_DRIVER=database
SESSION_LIFETIME=120
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

# --- Armazenamento seguro de documentos (CRÍTICO) ---
# 'private' => storage/app/private_docs (NÃO acessível por URL pública)
DOCS_STORAGE_DISK=private

# --- E-mail (use uma conta criada no cPanel) ---
MAIL_MAILER=smtp
MAIL_HOST=mail.seu-dominio.com
MAIL_PORT=465
MAIL_USERNAME=noreply@seu-dominio.com
MAIL_PASSWORD=senha_do_email
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS="noreply@seu-dominio.com"
MAIL_FROM_NAME="GPN-AGIL"

# --- Web Push (opcional; gerar com: php artisan push:vapid:generate) ---
VAPID_PUBLIC_KEY=
VAPID_PRIVATE_KEY=
VAPID_SUBJECT=mailto:admin@seu-dominio.com

# --- Funcionalidades opcionais (deixe false se não for usar) ---
FEATURE_VISTO_DEPARTAMENTO=false
FEATURE_ASSISTENTE=false
CHATBOT_ENABLED=false
```

**Gerar a `APP_KEY`:**
- Com SSH: `php artisan key:generate`
- Sem SSH: rode `php artisan key:generate --show` na sua máquina e cole o valor (formato `base64:...`) em `APP_KEY`.

---

## 5. Banco de dados

1. cPanel → **MySQL Databases** (ou "Assistente de Banco de Dados MySQL").
2. Crie o banco (ex.: `prefixo_gpn`).
3. Crie um usuário e senha forte.
4. **Adicione o usuário ao banco com TODOS os privilégios** (All Privileges).
5. Anote `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` e use no `.env` (§4).

As tabelas são criadas pelas **migrações** (`php artisan migrate --force`), executadas no §6 (SSH) ou §7 (rota de setup). Não importe SQL manualmente.

---

## 6. Caminho A — Deploy via SSH (recomendado)

Se você tem acesso SSH, este é o caminho mais seguro e repetível. Há um script pronto: `deploy.sh`.

### 6.1. Primeiro deploy

```bash
# 1. Enviar o código para o servidor (git clone ou upload)
#    Coloque o projeto FORA de public_html, ex.: /home/USUARIO/gpn-agil

cd /home/USUARIO/gpn-agil

# 2. Criar o .env (use o modelo da §4) e gerar a chave
nano .env                 # cole o conteúdo da §4
php artisan key:generate

# 3. Dependências de produção
composer install --no-dev --optimize-autoloader

# 4. Banco + storage + caches
php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 5. Permissões de escrita
chmod -R 775 storage bootstrap/cache
```

### 6.2. Apontar o domínio para `public/`

A raiz pública do site (Document Root) deve ser a pasta `public/` do projeto.
- Se puder configurar o Document Root no cPanel/Apache, aponte para `/home/USUARIO/gpn-agil/public`.
- Se **não** puder mudar o Document Root (caso clássico do cPanel), use a estrutura do **Caminho B §7.2** (projeto fora, conteúdo de `public/` dentro de `public_html`, com `index.php` ajustado).

### 6.3. Deploys seguintes (atualizações)

O `deploy.sh` automatiza atualização. Conteúdo real do script:

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan storage:link
```

Para usá-lo:

```bash
cd /home/USUARIO/gpn-agil
chmod +x deploy.sh
./deploy.sh
```

> Os assets (`public/build`) precisam estar atualizados. Como o script **não** roda `npm run build` (as linhas estão comentadas porque Node costuma não existir no servidor), rode `npm run build` localmente e faça commit/upload da pasta `public/build` antes do `git pull`.

---

## 7. Caminho B — Deploy via cPanel sem SSH

Use quando **só** houver Gerenciador de Arquivos (sem terminal).

### 7.1. Estrutura de pastas (segurança)

Nunca coloque o código todo do Laravel dentro de `public_html`. A estrutura correta:

```
/home/USUARIO/
├── gpn-agil/            ← TODO o projeto, EXCETO o conteúdo de public/
│   ├── app/ bootstrap/ config/ database/ routes/ storage/ vendor/ ...
│   └── .env
└── public_html/         ← apenas o CONTEÚDO da pasta public/ do projeto
    ├── index.php        ← editar (passo 7.3)
    ├── .htaccess
    ├── build/           ← gerado por `npm run build`
    └── ...
```

### 7.2. Upload

1. Na sua máquina, após a §3, crie **dois zips**:
   - `gpn-agil.zip` → tudo do projeto **exceto** a pasta `public`.
   - `public.zip` → **o conteúdo** de `public/` (não a pasta, o conteúdo).
2. cPanel → **Gerenciador de Arquivos** → raiz `/home/USUARIO/`.
3. Faça upload e **extraia** `gpn-agil.zip` → resulta em `/home/USUARIO/gpn-agil`.
4. Entre em `public_html`, apague o `index.html` padrão ("Parabéns"), faça upload e extraia `public.zip` ali dentro.

### 7.3. Ajustar o `index.php`

Edite `public_html/index.php` para apontar um nível acima, para a pasta do projeto.

**Antes:**
```php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
```

**Depois (ajuste o nome da pasta se usou outro):**
```php
require __DIR__.'/../gpn-agil/vendor/autoload.php';
$app = require_once __DIR__.'/../gpn-agil/bootstrap/app.php';
```

> Em Laravel 12 o `index.php` também referencia `bootstrap/cache/...` via `$app`. Ajustar essas duas linhas de `require` é suficiente, pois os demais caminhos são derivados delas.

### 7.4. Criar o `.env`

Em `/home/USUARIO/gpn-agil`, crie o arquivo `.env` com o modelo da §4. Gere a `APP_KEY` localmente (`php artisan key:generate --show`) e cole o valor.

### 7.5. Rodar o setup pela rota utilitária `/deploy-setup`

Como não há terminal, o projeto expõe uma rota que roda migração, storage:link e caches. **Atenção ao comportamento real do código** ([routes/web.php:254](routes/web.php#L254)):

1. **Defina `APP_DEPLOY_KEY` no `.env`** com um segredo forte e único, por exemplo:
   ```ini
   APP_DEPLOY_KEY=cole-aqui-um-segredo-longo-e-aleatorio
   ```
   - Se ficar **vazio**, a rota responde **403** (fica desativada).
   - O valor literal `InfinityDeploy2024!` é **rejeitado** em produção (403). Não use.

2. Acesse a rota passando a chave. **Preferencial via header** (não vaza em logs/Referer):
   ```bash
   curl -H "X-Deploy-Key: SEU_SEGREDO" https://seu-dominio.com/deploy-setup
   ```
   Alternativa por query string (funciona, porém fica em logs):
   ```
   https://seu-dominio.com/deploy-setup?key=SEU_SEGREDO
   ```

3. A rota executa, nesta ordem: `optimize:clear` → `migrate --force` → `storage:link` → `config:cache` → `route:cache` → `view:cache`, e retorna um resumo HTML. Há limite de **6 chamadas por minuto**.

4. **Permissões:** se der erro 500, ajuste no Gerenciador de Arquivos as pastas `gpn-agil/storage` e `gpn-agil/bootstrap/cache` para **775**.

5. **Storage de imagens:** o `storage:link` cria `public/storage → storage/app/public`. Como a pasta pública aqui é `public_html`, talvez seja preciso criar o link manualmente apontando para o projeto:
   ```
   ln -s /home/USUARIO/gpn-agil/storage/app/public /home/USUARIO/public_html/storage
   ```
   (Pode ser feito via Cron Job de execução única, se não houver SSH.)

6. **Depois de validar o site, ZERE a chave** (`APP_DEPLOY_KEY=`) no `.env` para desativar a rota. Ver §9.

---

## 8. Pós-deploy: agendador (cron) e filas

### 8.1. Agendador (obrigatório)

O sistema tem tarefas agendadas em `routes/console.php`:
- `CheckRetentionPolicy` — diariamente às **02:00** (política de retenção de documentos).
- `docs:check-sla` — diariamente às **08:00** (verificação de SLA).

Crie **um** Cron Job no cPanel (cPanel → **Cron Jobs**), de minuto em minuto:

```
* * * * * cd /home/USUARIO/gpn-agil && php artisan schedule:run >> /dev/null 2>&1
```

> Use o caminho completo do PHP se necessário (ex.: `/usr/local/bin/ea-php82`). Um único `schedule:run` por minuto cobre **todas** as tarefas agendadas — não crie crons separados para cada uma.

### 8.2. Filas (queue)

Com `QUEUE_CONNECTION=database` (§4), os jobs ficam na tabela `jobs`. Sem Supervisor, processe via cron:

```
* * * * * cd /home/USUARIO/gpn-agil && php artisan queue:work --stop-when-empty --max-time=55 >> storage/logs/worker.log 2>&1
```

- Isto processa a fila padrão a cada minuto e encerra sozinho. Para incluir Web Push, garanta que a fila `notifications` também seja processada:
  ```
  php artisan queue:work --queue=notifications,default --stop-when-empty --max-time=55
  ```
- **Alternativa simples (sem worker):** definir `QUEUE_CONNECTION=sync` no `.env` faz os jobs rodarem na hora, dentro da requisição. Funciona sem cron, mas o **OCR de anexos torna o upload lento** e exige o binário `tesseract`. Recomendado apenas se você não usa OCR/Push.

### 8.3. Worker com Supervisor (somente VPS/servidor dedicado)

Se você estiver num VPS (não shared hosting), prefira Supervisor:

```ini
[program:gpn-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /home/USUARIO/gpn-agil/artisan queue:work --queue=notifications,default --sleep=3 --tries=3
autostart=true
autorestart=true
user=USUARIO
numprocs=2
redirect_stderr=true
stdout_logfile=/home/USUARIO/gpn-agil/storage/logs/worker.log
```

---

## 9. Segurança (obrigatório)

1. **Desative a rota `/deploy-setup` após o deploy.** Modo mais simples: deixe `APP_DEPLOY_KEY=` (vazio) no `.env` e rode `php artisan config:clear && php artisan config:cache` (ou acesse a rota de setup uma última vez não é possível com chave vazia — então limpe o cache manualmente). Em VPS, você também pode comentar o bloco da rota em `routes/web.php`.
2. **`APP_DEBUG=false`** em produção (já no modelo da §4). Nunca exponha stack traces.
3. **Documentos privados:** confira que `DOCS_STORAGE_DISK=private`. Isso guarda os arquivos em `storage/app/private_docs`, fora do alcance de URL pública; o acesso passa por controller com checagem de permissão.
4. **Permissões:** `storage` e `bootstrap/cache` em `775` (ou `755` se o usuário web for o dono). Evite `777`.
5. **Nunca** versione o `.env` real nem a `APP_KEY`/senhas. O `APP_DEPLOY_KEY` deve existir **apenas** no servidor.
6. **HTTPS:** garanta certificado SSL no domínio (cPanel → SSL/TLS) e `APP_URL` com `https://`.

---

## 10. Resolução de problemas

| Sintoma | Causa provável / solução |
|---|---|
| **403 em `/deploy-setup`** | `APP_DEPLOY_KEY` vazio, ou você usou `InfinityDeploy2024!` (rejeitado em produção), ou a chave enviada não confere. Defina um segredo forte e reenvie via header `X-Deploy-Key`. |
| **500 (Internal Server Error)** | Permissão de `storage` / `bootstrap/cache` → ajuste para `775`. Verifique também `APP_KEY` preenchida e o ajuste do `index.php` (§7.3). |
| **Tela em branco / "could not find driver"** | Extensão `pdo_mysql` desativada, ou credenciais de banco erradas no `.env`. |
| **CSS/JS não carregam** | Faltou `npm run build` (gera `public/build`) ou a pasta `build` não subiu para `public_html`. |
| **Imagens/anexos não aparecem** | `storage:link` não criado ou apontando para o lugar errado (§7.5). |
| **Mudei o `.env` e nada muda** | Há config em cache. Rode `php artisan config:clear` e depois `php artisan config:cache`. |
| **OCR não funciona** | Binário `tesseract` ausente no servidor (comum em shared hosting). O upload segue funcionando; o job de OCR falha e vai para `failed_jobs`. |
| **Notificações/e-mails não saem** | Worker de fila não está rodando (§8.2) ou SMTP mal configurado no `.env`. |
| **Erro de migração** | Usuário do banco sem privilégios (§5) ou banco inexistente. |
```
