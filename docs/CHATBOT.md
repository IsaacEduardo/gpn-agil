# Chatbot RAG sobre documentos — GPN-AGIL

Assistente conversacional que pesquisa e responde sobre **documentos internos e de entrada**, com
**busca semântica (embeddings + vector store)** e **controlo de acesso por utilizador/departamento/gabinete**.

## Princípio de segurança

**RAG "permissões primeiro".** O modelo só recebe conteúdo que o utilizador já pode ver:
1. A busca vetorial é **pré-filtrada** por `departamento_id`/`gabinete_id` (denormalizados em cada chunk).
2. Cada documento candidato é **re-autorizado** (`DocumentoPermissionService::canViewDocument` para entradas;
   Policy `view` para internos) antes de entrar no contexto ou virar citação — defesa em profundidade.
3. As respostas citam apenas fontes permitidas, com tipo (interno/externo) e página (quando disponível).

## Arquitetura

```
Pergunta
  → EmbeddingClient (Ollama on-premise)        app/Chatbot/Embeddings
  → VectorStore::search (MySQL + cosseno)      app/Chatbot/VectorStore
  → filtro de permissão (DocumentoPermissionService) + híbrido por palavra-chave
  → re-autorização por documento (defesa em profundidade)   app/Chatbot/Services/ChatbotRetriever
  → prompt com [Doc N · p.X] → LlmClient (Claude)           app/Chatbot/Services/ChatbotService
  → resposta + citações {documento, tipo, página, url}
```

Ingestão (desacoplada): **Observers** (DocumentoEntrada/Interno/Anexo) → **IndexDocumentJob** (fila) →
**DocumentIndexer** (extrai por página com pdfparser → fragmenta → embeddings → `chatbot_chunks`).

Componentes (`app/Chatbot/`): `Contracts/`, `Embeddings/` (Ollama, OpenAI, Fake), `VectorStore/MysqlVectorStore`,
`Services/` (DocumentChunker, DocumentIndexer, ChatbotRetriever, ChatbotService), `Jobs/`, `Observers/`,
`Console/` (comandos), `Models/` (ChatbotChunk, ChatbotConversation, ChatbotMessage, ChatbotQueryLog),
`Providers/ChatbotServiceProvider`. Geração reutiliza `App\Services\Ai\LlmClient`.

## Configuração (.env)

```dotenv
CHATBOT_ENABLED=true            # liga API + ingestão automática
EMBEDDINGS_DRIVER=ollama        # ollama | openai | fake
OLLAMA_BASE_URL=http://localhost:11434
EMBEDDINGS_MODEL=nomic-embed-text
EMBEDDINGS_DIM=768
CHATBOT_VECTOR_DRIVER=mysql
CHATBOT_TOPK=6
CHATBOT_CHUNK_SIZE=1000
CHATBOT_CHUNK_OVERLAP=150
CHATBOT_HYBRID=true
# Geração (já existentes): ANTHROPIC_API_KEY, ASSISTENTE_MODEL, ...
```

On-premise: instalar [Ollama](https://ollama.com) e `ollama pull nomic-embed-text`. Nenhum dado sai da instituição.

## Comandos

```bash
php artisan chatbot:index-all            # enfileira indexação de todos os documentos
php artisan chatbot:index-all --sync     # indexa já (sem fila)
php artisan chatbot:index-all --tipo=interno
php artisan chatbot:flush --force        # apaga o índice
php artisan migrate                       # cria as tabelas chatbot_*
```

## API REST (sessão; mesma origem)

> Esta app é baseada em sessão (sem Sanctum). Os endpoints usam o guard `web` + permissão `assistente.usar`.
> Em chamadas do browser, enviar o cabeçalho `X-CSRF-TOKEN` (meta `csrf-token`). Para uma API por token,
> instalar `laravel/sanctum` e trocar o middleware para `auth:sanctum`.

```
POST   /api/chatbot/perguntar                              { pergunta, conversation_id? }
POST   /api/chatbot/documentos-entradas/{documento}/perguntar
POST   /api/chatbot/documentos-internos/{documentoInterno}/perguntar
GET    /api/chatbot/conversas
GET    /api/chatbot/conversas/{conversa}
DELETE /api/chatbot/conversas/{conversa}
```

### Exemplo

```bash
curl -X POST https://app/api/chatbot/perguntar \
  -H "Content-Type: application/json" -H "X-CSRF-TOKEN: <token>" \
  --cookie "<sessão>" \
  -d '{"pergunta":"Que documentos mencionam audiências este ano?"}'
```

```json
{
  "conversation_id": 12,
  "resposta": "Há dois documentos sobre audiências [Doc 1] e [Doc 2] ...",
  "fontes": [
    {"ref":"Doc 1","tipo":"externo","tipo_label":"Documento de entrada","titulo":"10/2026 — Pedido de Audiência","pagina":1,"url":"/documentos-entradas/10"},
    {"ref":"Doc 2","tipo":"interno","tipo_label":"Documento interno","titulo":"DRPP/OF/001/2026 — Resposta","pagina":null,"url":"/documentos-internos/3"}
  ]
}
```

A página web `/assistente` usa automaticamente a busca semântica quando `CHATBOT_ENABLED=true` (senão, recai
na busca por palavra-chave).

## Limitações
- **Página**: fiável em PDFs com texto nativo; digitalizados/imagens ficam com `pagina=null` (OCR por página é melhoria futura).
- **Escala**: cosseno em PHP sobre candidatos pré-filtrados é adequado a corpora pequenos/médios; a interface
  `VectorStore` permite migrar para Qdrant/pgvector sem alterar os serviços.

## Testes
`tests/Feature/ChatbotRagTest.php` cobre indexação, recuperação restrita por permissões (dept. A nunca vê dept. B),
comando de carga e API (auth, 403 entre departamentos, citações só de documentos permitidos). Usa `FakeEmbeddingClient`
+ `FakeLlmClient` (sem rede/custo).
