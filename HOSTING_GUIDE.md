# Guia de Hospedagem - GPN-AGIL

Este guia descreve os passos para hospedar o sistema GPN-AGIL em seu domínio próprio.

## 1. Requisitos do Servidor

*   **PHP**: 8.2 ou superior.
*   **Banco de Dados**: MySQL 5.7+ ou MariaDB 10.3+.
*   **Servidor Web**: Nginx ou Apache.
*   **Composer**: Instalado.
*   **Node.js**: (Opcional, apenas se for compilar assets no servidor).

## 2. Configuração do Ambiente (.env)

No servidor, crie o arquivo `.env` baseado no `.env.example` e ajuste as variáveis críticas:

```ini
APP_NAME="GPN-AGIL"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://seu-dominio.com

# Banco de Dados
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nome_do_banco
DB_USERNAME=usuario_do_banco
DB_PASSWORD=senha_do_banco

# Configuração de Armazenamento Seguro (CRÍTICO)
FILESYSTEM_DISK=local
DOCS_STORAGE_DISK=private
```

## 3. Armazenamento Seguro (Documentos)

O sistema foi configurado para armazenar documentos em uma pasta privada (`storage/app/private_docs`) que **não** é acessível publicamente via URL. O acesso é feito via Controller com verificação de permissões.

Certifique-se de que a pasta `storage` tenha permissões de escrita para o usuário do servidor web (ex: `www-data`).

```bash
chmod -R 775 storage bootstrap/cache
```

## 4. Configuração do Servidor Web

### Nginx (Exemplo)

```nginx
server {
    listen 80;
    server_name seu-dominio.com;
    root /var/www/gpn-agil/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## 5. Deploy Automatizado

Use o script `deploy.sh` incluído na raiz do projeto para atualizar o sistema:

```bash
chmod +x deploy.sh
./deploy.sh
```

Este script executa `git pull`, instala dependências, roda migrações e otimiza o cache.

## 6. Filas (Queues)

Para processamento de OCR e envio de e-mails, configure o Supervisor para rodar o worker:

```ini
[program:gpn-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/gpn-agil/artisan queue:work database --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/gpn-agil/storage/logs/worker.log
```
