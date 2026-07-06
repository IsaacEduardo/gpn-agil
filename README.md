# GPN-AGIL

Sistema de Gestão Administrativa e Documental do **Governo Provincial do Namibe** (EDMS), construído em **Laravel 12 / PHP 8.2**.

## Módulos principais

- **Documentos de Entrada** — registo, protocolo, encaminhamento entre departamentos, vistos (departamento e gabinete) e arquivo
- **Documentos Internos** — criação por modelos institucionais, versionamento, workflow (rascunho → análise → aprovado → assinado) e assinatura digital com certificado P12
- **Requisições** — produto, oficina, serviço e passagem, com fluxo de visto e assinatura
- **EDMS / Arquivo** — pastas hierárquicas por departamento/gabinete, políticas de retenção e verificação de SLA
- **Reservas de Espaço, Viaturas e Termos de Entrega**
- **Assistente de IA** (opcional, feature-flag) — perguntas sobre documentos com controlo de permissões, OCR de anexos via Tesseract

## Início rápido (desenvolvimento)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
# Definir SEED_ADMIN_EMAIL e SEED_ADMIN_PASSWORD no .env antes de:
php artisan db:seed --class=InitialRolesAndAdminSeeder
php artisan serve
```

Testes:

```bash
./vendor/bin/phpunit
```

## Documentação

| Documento | Conteúdo |
|-----------|----------|
| [docs/README.md](docs/README.md) | Índice completo da documentação |
| [docs/ARQUITETURA.md](docs/ARQUITETURA.md) | Arquitetura, módulos e modelo de dados |
| [docs/FLUXOS-DE-NEGOCIO.md](docs/FLUXOS-DE-NEGOCIO.md) | Workflows e máquinas de estado |
| [docs/GUIA_HOSPEDAGEM.md](docs/GUIA_HOSPEDAGEM.md) | Escolha de hospedagem (cPanel, VPS, Docker) e dimensionamento |
| [docs/MANUAL_DEPLOY.md](docs/MANUAL_DEPLOY.md) | Passo a passo de deploy e `.env` de produção |

## Licença

Projeto proprietário do Governo Provincial do Namibe.
