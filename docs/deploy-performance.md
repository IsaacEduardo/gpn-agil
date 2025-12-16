# Deploy e Performance para GPN-AGIL

## Objetivos
- Sustentar >15 usuários concorrentes com latência estável.
- Reduzir carga de BD e eliminar N+1 em páginas críticas.

## Checklist de Produção
- Configurar `.env`:
  - `APP_ENV=production`, `APP_DEBUG=false`
  - `CACHE_DRIVER=redis`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis`, `REDIS_CLIENT=phpredis`
- Redis:
  - Instalar Redis e extensão `phpredis`.
  - Proteger com senha/bind e ajustar memória.
- Otimizações Laravel:
  - `php artisan config:cache`
  - `php artisan route:cache` (sem closures)
  - `php artisan view:cache`
  - `php artisan optimize`
- Banco de Dados:
  - Rodar migrações de índices: `php artisan migrate --force`
  - MySQL tuning: `max_connections`, `innodb_buffer_pool_size`, `innodb_flush_log_at_trx_commit`, `thread_cache_size`.
  - Ativar slow query log.
- PHP-FPM + Nginx/Apache:
  - Habilitar OPcache (`opcache.enable=1`, `opcache.memory_consumption=128`, `opcache.max_accelerated_files=10000`).
  - Cache estático (ETag/Cache-Control) e compressão gzip/br.
- Filas:
  - Mover tarefas pesadas para filas (`redis` + `supervisor`).

## Código já otimizado
- Catálogo cacheado: `App\Support\CatalogCache` (viaturas/empresas) com invalidação em `AppServiceProvider`.
- Controladores usam catálogos cacheados nas telas de criação (termos, requisicoes produto/serviço/oficina).
- `TermoEntregaController@store` em transação.
- Migração de índices resiliente aplicada: `2025_10_15_000102_add_indexes_for_performance_v3.php`.

## Teste de Carga
- k6/artillery: simular 30–50 usuários em `/viaturas`, `/requisicoes`, `/termos`.
- Meta: P95 < 500ms, erro < 1%.

## Operação
- Log rotate e monitoramento de erros.
- Em staging, usar `laravel/telescope` para detectar N+1 e gargalos.