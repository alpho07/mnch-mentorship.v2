# Filament RAG Knowledge Base

This feature adds a disabled-by-default private RAG document manager and Filament chat page.

It supports two engines:

- `local`: the original local HTTP RAG service.
- `external`: in-app document extraction, local chunk storage, external embeddings, and external OpenAI-compatible chat.
- `hybrid`: local HTTP ingestion, extraction, chunking, and embeddings, with DeepSeek/OpenAI-compatible chat for final answers.

## Runtime

For `RAG_ENGINE=local`, the Laravel app calls a server-configured local service:

- `GET /health`
- `POST /ingest`
- `POST /ask`
- optional configured delete endpoint

For `RAG_ENGINE=hybrid`, Laravel calls the local service for:

- `GET /health`
- `POST /ingest`
- `POST /search`

The retrieved excerpts are then sent to the configured chat provider. This avoids external embedding spend while keeping DeepSeek as the answer model.

For `RAG_ENGINE=external`, Laravel extracts text from uploaded files, stores global chunks in `rag_chunks`, embeds chunks through the configured embedding API, retrieves the top matching chunks, and sends only those excerpts to the configured chat provider.

Files are uploaded to the configured private disk and directory. They are not stored in `public/`.

Supported extraction types: PDF, DOCX, PPTX, XLSX, CSV, TXT, Markdown, HTML, and JSON.

## Environment

Add these to the real environment only when ready:

```env
RAG_ENABLED=false
RAG_ENGINE=hybrid
RAG_BASE_URL=http://127.0.0.1:8001
RAG_CONNECT_TIMEOUT=5
RAG_REQUEST_TIMEOUT=30
RAG_INGEST_TIMEOUT=180
RAG_RETRY_COUNT=1
RAG_TOP_K_DEFAULT=5
RAG_TOP_K_MIN=1
RAG_TOP_K_MAX=10
RAG_SEARCH_POOL_LIMIT=1000
RAG_UPLOAD_DISK=local
RAG_UPLOAD_DIRECTORY=private/knowledge-base
RAG_MAX_UPLOAD_SIZE_KB=102400
RAG_HEALTH_CACHE_SECONDS=30
RAG_DELETE_ENDPOINT=
RAG_CHAT_PROVIDER=deepseek
RAG_CHAT_BASE_URL=https://api.deepseek.com
RAG_CHAT_MODEL=deepseek-v4-flash
RAG_CHAT_API_KEY=
RAG_CHAT_MAX_TOKENS=650
RAG_EMBEDDING_PROVIDER=openai
RAG_EMBEDDING_BASE_URL=https://api.openai.com/v1
RAG_EMBEDDING_MODEL=text-embedding-3-small
RAG_EMBEDDING_API_KEY=
```

Use `RAG_CHAT_PROVIDER=openai`, `RAG_CHAT_BASE_URL=https://api.openai.com/v1`, and an OpenAI chat model if OpenAI should answer instead of DeepSeek. DeepSeek chat is OpenAI-compatible. In `hybrid` mode, embeddings stay local through the local RAG service; in `external` mode, embeddings use `RAG_EMBEDDING_*`.

## Database

Run the additive migrations during a controlled maintenance window:

- `2026_08_04_000001_create_rag_documents_table.php`
- `2026_08_04_000002_create_rag_conversations_table.php`
- `2026_08_04_000003_create_rag_messages_table.php`
- `2026_08_04_000004_create_rag_chunks_table.php`

No destructive migration is required.

## Permissions

The feature is hidden unless `RAG_ENABLED=true`.

Document management checks:

- `view_any_rag::document`
- `view_rag::document`
- `create_rag::document`
- `update_rag::document`
- `delete_rag::document`

Chat checks:

- `page_RagChat`
- `use_rag_chat`

`super_admin` and `admin` are allowed as operational break-glass roles. The `resource_manager` role receives the KB permissions through `RolePermissionSeeder`.

## Queue

Uploads create a `pending` document and dispatch `ProcessRagDocument`. The job is safe with `QUEUE_CONNECTION=sync`, but production should use a worker because ingestion can take longer than an HTTP request.

For `RAG_ENGINE=external`, queue workers need outbound HTTPS access to the embedding provider. For `RAG_ENGINE=hybrid`, queue workers need access to the local RAG service and the chat provider.

## Rollback

1. Set `RAG_ENABLED=false`.
2. Stop queue workers from processing new RAG jobs if needed.
3. Roll back the RAG migrations in reverse order during a maintenance window.
4. Remove private uploaded files from the configured RAG upload directory if policy allows.

## Checks

Useful local checks:

```bash
php artisan config:show rag
php artisan route:list --name=rag
php artisan migrate --pretend
php artisan test --filter=Rag
```

Do not run migrations or restart services from this review workflow.
