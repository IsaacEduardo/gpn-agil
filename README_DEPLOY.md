# Deploy de MVP do GPN-AGIL

Este guia cobre duas estratégias: Docker Compose (rápida para MVP) e instalação direta em Linux. Inclui criação de admin inicial, otimizações e testes básicos.

## 1) Docker Compose (recomendado para MVP)

### Pré-requisitos
- Docker Engine e Docker Compose
- Porta `8080` livre

### Arquivos incluídos
- `docker-compose.yml`: orquestra PHP-FPM, Nginx e MySQL
- `docker/php/Dockerfile`: PHP 8.2 com extensões Laravel
- `docker/nginx/default.conf`: configuração Nginx
- `.env.docker`: template de variáveis
- `database/seeders/InitialRolesAndAdminSeeder.php`: seeder de roles e admin

### Passo a passo
1. Copie `.env.docker` para `.env` e ajuste variáveis:
   - `APP_URL=http://localhost:8080`
   - `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (se desejar)
2. Suba os containers:
   - `docker compose up -d`
3. Instale dependências e gere chave:
   - `docker compose exec php composer install --no-dev --optimize-autoloader`
   - `docker compose exec php php artisan key:generate`
4. Migre o banco e faça seed:
   - `docker compose exec php php artisan migrate --force`
   - Defina `SEED_ADMIN_EMAIL` e `SEED_ADMIN_PASSWORD` (mín. 12 caracteres) no `.env`
   - `docker compose exec php php artisan db:seed --class=InitialRolesAndAdminSeeder --force`
5. Otimizações (opcional):
   - `docker compose exec php php artisan config:cache`
   - `docker compose exec php php artisan view:cache`
   - `docker compose exec php php artisan route:cache` (use apenas se não houver rotas com closures)
6. Acesse o sistema:
   - `http://localhost:8080/home`
   - Login inicial: o email/senha definidos em `SEED_ADMIN_EMAIL`/`SEED_ADMIN_PASSWORD` (não existem credenciais padrão)

### Observações
- MySQL está exposto na porta `3306`; em produção, prefira mantê-lo privado.
- Ajuste `APP_DEBUG=false` e configure HTTPS via proxy (Traefik/Nginx externo) se publicar.

## 2) Instalação direta (Linux – Nginx + PHP-FPM + MySQL)

### Requisitos
- PHP 8.2 + extensões: `mbstring`, `openssl`, `pdo_mysql`, `zip`, `xml`, `ctype`, `json`, `fileinfo`, `gd`, `intl`, `opcache`
- Nginx, MySQL 8.x, Composer 2.x

### Passos resumidos
1. Instalar pacotes:
   - `sudo apt update && sudo apt install -y nginx php8.2-fpm php8.2-{mbstring,xml,gd,cli,curl,zip,opcache,intl} mysql-server git unzip`
2. Clonar projeto e instalar deps:
   - `sudo git clone <repo> /var/www/GPN-AGIL && cd /var/www/GPN-AGIL`
   - `sudo chown -R www-data:www-data . && sudo -u www-data composer install --no-dev --optimize-autoloader`
3. `.env` de produção:
   - `cp .env.example .env` e ajuste `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `DB_*`
   - `php artisan key:generate`
4. Banco de dados:
   - Criar DB/usuário e permissões
   - `php artisan migrate --force`
   - Defina `SEED_ADMIN_EMAIL` e `SEED_ADMIN_PASSWORD` (mín. 12 caracteres) no `.env`
   - `php artisan db:seed --class=InitialRolesAndAdminSeeder --force`
5. Nginx:
   - Root em `/var/www/GPN-AGIL/public` e PHP-FPM socket
   - `try_files $uri $uri/ /index.php?$query_string;`
6. Permissões e otimizações:
   - `chown -R www-data:www-data storage bootstrap/cache`
   - `chmod -R 775 storage bootstrap/cache`
   - `php artisan config:cache && php artisan view:cache`

## 3) Testes do MVP
- Como admin:
  - Criar um `Gabinete` em `/gabinetes` e vincular responsável
  - Criar `Departamento` em `/departamentos/create` (Gabinete obrigatório)
  - Ver coluna "Gabinete" em `/departamentos`
- Como não-admin:
  - Confirmar que `/gabinetes` retorna 403 e item não aparece no menu
- Requisições:
  - Validar escopos por departamento nas listagens se aplicável

## 4) Segurança e produção
- HTTPS obrigatório (Certbot no Nginx ou proxy TLS)
- `APP_DEBUG=false`; logs em `storage/logs/laravel.log`
- Se usar filas/tarefas agendadas:
  - Cron: `* * * * * php artisan schedule:run`
  - Queue: `supervisor` gerenciando `php artisan queue:work`

## 5) Problemas comuns
- `route:cache` falha com closures em `routes/web.php` – remova closures ou não use
- Geração de PDF (DomPDF) exige `ext-gd` e pode precisar ajuste de `memory_limit`
- Permissões de `storage`/`bootstrap/cache` causam erro 500 se incorretas

## 6) Próximos passos
- Configurar SMTP real no `.env` e redefinir senha do admin
- Criar usuários chefes e usuários padrão
- Ativar backup automático do banco