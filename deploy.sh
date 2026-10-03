#!/bin/bash
# Script de Deploy para GPN-AGIL
# Execute este script no servidor de produção

echo "Iniciando Deploy..."

# 1. Atualizar Código
echo "Atualizando repositório..."
git pull origin main

# 2. Instalar Dependências PHP
echo "Instalando dependências do Composer..."
composer install --no-dev --optimize-autoloader

# 3. Assets (public/build não está no git). Só com COMPILAR_ASSETS=1 — é preciso
#    quando o deploy toca em resources/js, resources/css ou vite.config.js.
#    O Vite 7 (e o editor colaborativo) exigem Node 20.19+: com Node antigo o
#    build parte e deixava a pasta a meio, por isso aqui recusa-se antes.
if [ "${COMPILAR_ASSETS:-0}" = "1" ]; then
    NODE_MAJOR=$(node -v 2>/dev/null | cut -d. -f1 | tr -d v)
    if [ -z "$NODE_MAJOR" ] || [ "$NODE_MAJOR" -lt 20 ]; then
        echo "ERRO: Node 20+ necessário para compilar os assets (encontrado: $(node -v 2>/dev/null || echo nenhum))."
        exit 1
    fi
    echo "Compilando assets..."
    npm ci && npm run build || { echo "ERRO: build dos assets falhou."; exit 1; }
fi

# 4. Banco de Dados
echo "Executando migrações..."
php artisan migrate --force

# 5. Otimização
echo "Limpando e cacheando configurações..."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 6. Fila de Notificações
# O worker (systemd: gpn-queue.service) mantém o código antigo em memória
# depois de um pull. Sem este restart, as notificações passam a ser
# processadas por código desatualizado até o --max-time reciclar o processo.
echo "Reiniciando worker da fila..."
php artisan queue:restart

# 6.1. Servidor de tempo real (Reverb), se instalado (deploy/systemd/gpn-reverb.service):
# o processo mantém o código antigo em memória; o restart pede-lhe que termine e o
# systemd arranca-o de novo.
if systemctl is-active --quiet gpn-reverb 2>/dev/null; then
    echo "Reiniciando Reverb..."
    php artisan reverb:restart
fi

# 7. Links Simbólicos
echo "Verificando links simbólicos..."
php artisan storage:link

# 8. Permissões (Ajuste conforme seu usuário de servidor web, ex: www-data)
# chown -R www-data:www-data storage bootstrap/cache

echo "Deploy concluído com sucesso!"
