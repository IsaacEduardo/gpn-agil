# Guia de Hardening & Checklist de Produção (VPS Linux / Nginx / DevSecOps)
**Sistema:** Gestão Documental e Arquivística (EDMS) — Governo Provincial (GPN-AGIL)  
**Conformidade:** OWASP Top 10, CIS Benchmark & Boas Práticas Governamentais

---

## 1. Configuração do Servidor Web (Nginx)

### 1.1 Bloco de VirtualHost Seguro (`/etc/nginx/sites-available/gpn-agil.conf`)

```nginx
# Redirecionamento HTTP para HTTPS
server {
    listen 80;
    listen [::]:80;
    server_name edms.governoprovincial.gov.ao;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name edms.governoprovincial.gov.ao;

    root /var/www/gpn-agil/public;
    index index.php index.html;

    # Certificados SSL/TLS (Let's Encrypt / Certificado Institucional)
    ssl_certificate /etc/letsencrypt/live/edms.governoprovincial.gov.ao/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/edms.governoprovincial.gov.ao/privkey.pem;
    ssl_trusted_certificate /etc/letsencrypt/live/edms.governoprovincial.gov.ao/chain.pem;

    # Protocolos e Cifras Modernas (Mozilla Intermediate)
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_timeout 1d;
    ssl_session_cache shared:SSL:10m;
    ssl_session_tickets off;
    ssl_stapling on;
    ssl_stapling_verify on;

    # Ocultação de assinaturas e versões
    server_tokens off;

    # Limite de tamanho de upload (ajustado para PDFs e anexos pesados)
    client_max_body_size 25M;
    client_body_buffer_size 128k;

    # Proteção de Arquivos Ocultos e Dotfiles (.env, .git, etc.)
    location ~ /\.(?!well-known).* {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Bloqueio de Execução PHP dentro do diretório de uploads/storage
    location ~* ^/storage/.*\.php$ {
        deny all;
        return 404;
    }

    # Roteamento Principal do Laravel
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Processamento PHP-FPM
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 180;
    }

    # Cache e Proteção de Ativos Estáticos
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff2|woff|ttf|svg)$ {
        expires 30d;
        add_header Cache-Control "public, no-transform";
        access_log off;
    }
}
```

---

## 2. Permissões de Ficheiros no Linux (Least Privilege)

Execute os comandos a partir da raiz da aplicação (`/var/www/gpn-agil`):

```bash
# 1. Definir propriedade do utilizador do sistema e do grupo do servidor web
sudo chown -R deployer:www-data /var/www/gpn-agil

# 2. Permissões padrão para diretórios (750) e ficheiros (640)
sudo find /var/www/gpn-agil -type d -exec chmod 750 {} \;
sudo find /var/www/gpn-agil -type f -exec chmod 640 {} \;

# 3. Permissões de escrita estritas apenas para storage e cache
sudo chmod -R ug+rwx /var/www/gpn-agil/storage /var/www/gpn-agil/bootstrap/cache
sudo chmod -R 775 /var/www/gpn-agil/storage /var/www/gpn-agil/bootstrap/cache

# 4. Blindagem do ficheiro .env (apenas leitura para o utilizador/servidor web)
sudo chmod 600 /var/www/gpn-agil/.env
sudo chown deployer:www-data /var/www/gpn-agil/.env
```

---

## 3. Hardening do PHP-FPM (`/etc/php/8.2/fpm/php.ini` & `pool.d/www.conf`)

### 3.1 `php.ini`
```ini
expose_php = Off
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /var/log/php/error.log
memory_limit = 512M
max_execution_time = 180
upload_max_filesize = 25M
post_max_size = 30M
session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = "Lax"
session.use_strict_mode = 1

; Desativar funções potencialmente perigosas não utilizadas
disable_functions = exec,passthru,popen,proc_nice,system,phpinfo
```

---

## 4. Firewall (UFW) & Fail2Ban

### 4.1 Configuração UFW
```bash
# Definir regras padrão
sudo ufw default deny incoming
sudo ufw default allow outgoing

# Permitir SSH (porta customizada se aplicável), HTTP e HTTPS
sudo ufw allow 22/tcp comment 'SSH'
sudo ufw allow 80/tcp comment 'HTTP'
sudo ufw allow 443/tcp comment 'HTTPS'

# Habilitar firewall
sudo ufw enable
```

### 4.2 Jail do Fail2Ban para Login do Laravel (`/etc/fail2ban/jail.d/laravel-auth.conf`)
```ini
[laravel-auth]
enabled = true
port = http,https
filter = laravel-auth
logpath = /var/www/gpn-agil/storage/logs/laravel.log
maxretry = 5
findtime = 900
bantime = 3600
```

---

## 5. Checklist de Verificação Pré-Produção

- [x] `APP_ENV=production` e `APP_DEBUG=false` no `.env`.
- [x] `APP_KEY` gerada e validada (`php artisan key:generate`).
- [x] `SESSION_SECURE_COOKIE=true` e `SESSION_SAME_SITE=lax`.
- [x] `SecurityHeadersMiddleware` ativo e injetando CSP, HSTS, X-Frame-Options e nosniff.
- [x] Políticas de autorização (Policies) ativas em 100% das rotas de documentos, anexos e pastas.
- [x] Rate limiting configurado para Login (5 tent / 15 min), OCR (15 req/min), IA (20 req/min) e Exports (10 req/min).
- [x] Sanitização HTML via `HtmlSanitizer` ativada contra XSS em despachos e documentos internos.
- [x] Permissões de storage e `.env` configuradas com menor privilégio no Linux.
- [x] Fila de background processada via Supervisor para OCR e notificações (`php artisan queue:work`).
