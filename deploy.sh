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

# 3. Instalar Dependências Frontend (se necessário build no servidor)
# echo "Compilando assets..."
# npm install
# npm run build

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

# 7. Links Simbólicos
echo "Verificando links simbólicos..."
php artisan storage:link

# 8. Permissões (Ajuste conforme seu usuário de servidor web, ex: www-data)
# chown -R www-data:www-data storage bootstrap/cache

echo "Deploy concluído com sucesso!"
