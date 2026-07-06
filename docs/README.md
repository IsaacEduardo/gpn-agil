# Documentação — GPN-AGIL

Documentação técnica do **Sistema de Gestão Administrativa e Documental do Governo Provincial do Namibe**.

## Índice

| Documento | Conteúdo |
|-----------|----------|
| [ARQUITETURA.md](ARQUITETURA.md) | Visão geral, stack, arquitetura em camadas, estrutura de diretórios, módulos, modelo de dados e infraestrutura (Docker). |
| [GUIA_HOSPEDAGEM.md](GUIA_HOSPEDAGEM.md) | Escolha de hospedagem (cPanel vs VPS vs Docker), requisitos, dimensionamento, Nginx, Supervisor e cron. |
| [MANUAL_DEPLOY.md](MANUAL_DEPLOY.md) | Passo a passo de deploy (SSH, cPanel e Docker), `.env` de produção e troubleshooting. |
| [FLUXOS-DE-NEGOCIO.md](FLUXOS-DE-NEGOCIO.md) | Workflows (máquinas de estado): documentos de entrada, documentos internos, assinatura digital, requisições e reservas. |
| [CHATBOT.md](CHATBOT.md) | Chatbot RAG sobre documentos: arquitetura, segurança (permissões), configuração, comandos, API e exemplos. |
| [deploy-performance.md](deploy-performance.md) | Notas de performance e deploy. |

> Os diagramas estão em [Mermaid](https://mermaid.js.org/) e são renderizados automaticamente no
> GitHub e no VS Code (com a extensão *Markdown Preview Mermaid Support*).
