<?php

return [
    'enabled' => env('RAG_ENABLED', false),

    'engine' => env('RAG_ENGINE', 'local'),

    'base_url' => env('RAG_BASE_URL', 'http://127.0.0.1:8001'),

    'connect_timeout' => (int) env('RAG_CONNECT_TIMEOUT', 5),
    'request_timeout' => (int) env('RAG_REQUEST_TIMEOUT', 30),
    'ingest_timeout' => (int) env('RAG_INGEST_TIMEOUT', 180),
    'retry_count' => (int) env('RAG_RETRY_COUNT', 1),

    'top_k' => [
        'default' => (int) env('RAG_TOP_K_DEFAULT', 5),
        'min' => (int) env('RAG_TOP_K_MIN', 1),
        'max' => (int) env('RAG_TOP_K_MAX', 10),
    ],

    'search_pool_limit' => (int) env('RAG_SEARCH_POOL_LIMIT', 1000),
    'search_timeout' => (int) env('RAG_SEARCH_TIMEOUT', 3),
    'search_max_failures' => (int) env('RAG_SEARCH_MAX_FAILURES', 2),

    'query_planner' => [
        'enabled' => (bool) env('RAG_QUERY_PLANNER_ENABLED', true),
        'timeout' => (int) env('RAG_QUERY_PLANNER_TIMEOUT', 6),
        'max_queries' => (int) env('RAG_QUERY_PLANNER_MAX_QUERIES', 6),
    ],

    'uploads' => [
        'disk' => env('RAG_UPLOAD_DISK', 'local'),
        'directory' => env('RAG_UPLOAD_DIRECTORY', 'private/knowledge-base'),
        'max_size_kb' => (int) env('RAG_MAX_UPLOAD_SIZE_KB', 51200),
        'allowed_extensions' => ['pdf', 'docx', 'pptx', 'xlsx', 'csv', 'txt', 'md', 'markdown', 'html', 'htm', 'json'],
        'allowed_mime_types' => [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/csv',
            'text/plain',
            'text/markdown',
            'text/html',
            'application/json',
        ],
    ],

    'chunking' => [
        'max_chars' => (int) env('RAG_CHUNK_MAX_CHARS', 3500),
        'overlap_chars' => (int) env('RAG_CHUNK_OVERLAP_CHARS', 400),
    ],

    'chat' => [
        'provider' => env('RAG_CHAT_PROVIDER', 'openai'),
        'base_url' => env('RAG_CHAT_BASE_URL'),
        'api_key' => env('RAG_CHAT_API_KEY')
            ?: (env('RAG_CHAT_PROVIDER', 'openai') === 'deepseek'
                ? env('DEEPSEEK_API_KEY')
                : env('OPENAI_API_KEY')),
        'model' => env('RAG_CHAT_MODEL'),
        'timeout' => (int) env('RAG_CHAT_TIMEOUT', 90),
        'retry_count' => (int) env('RAG_CHAT_RETRY_COUNT', 0),
        'temperature' => (float) env('RAG_CHAT_TEMPERATURE', 0.2),
        'max_tokens' => (int) env('RAG_CHAT_MAX_TOKENS', 700),
        'context_per_source_chars' => (int) env('RAG_CHAT_CONTEXT_PER_SOURCE_CHARS', 900),
        'context_total_chars' => (int) env('RAG_CHAT_CONTEXT_TOTAL_CHARS', 4500),
    ],

    'embeddings' => [
        'provider' => env('RAG_EMBEDDING_PROVIDER', 'openai'),
        'base_url' => env('RAG_EMBEDDING_BASE_URL', 'https://api.openai.com/v1'),
        'api_key' => env('RAG_EMBEDDING_API_KEY', env('OPENAI_API_KEY')),
        'model' => env('RAG_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'batch_size' => (int) env('RAG_EMBEDDING_BATCH_SIZE', 32),
    ],

    'health_cache_seconds' => (int) env('RAG_HEALTH_CACHE_SECONDS', 30),

    'delete_endpoint' => env('RAG_DELETE_ENDPOINT'),

    'runtime_settings' => [
        'enabled' => (bool) env('RAG_RUNTIME_SETTINGS', true),
        'cache_key' => 'rag:settings:v1',
        'cache_ttl' => 60,
    ],

    'budget' => [
        'total_ms' => (int) env('RAG_BUDGET_TOTAL_MS', 12000),
        'reserve_answer_ms' => (int) env('RAG_BUDGET_RESERVE_ANSWER_MS', 4000),
        'stage_default_ms' => (int) env('RAG_BUDGET_STAGE_DEFAULT_MS', 500),
    ],

    'gate' => [
        'mode' => env('RAG_GATE_MODE', 'shadow'),
        'sufficient' => (float) env('RAG_GATE_SUFFICIENT', 0.62),
        'expand' => (float) env('RAG_GATE_EXPAND', 0.28),
        'weights' => [
            'top_score' => 0.24,
            'margin' => 0.12,
            'term_coverage' => 0.28,
            'content_density' => 0.20,
            'agreement' => 0.10,
            'source_count' => 0.06,
        ],
    ],

    'ladder' => [
        'stages' => ['lexicon_bridge', 'vector', 'structural', 'stored_document', 'planner'],
        'top_k' => [
            'lexicon_bridge' => 4,
            'vector' => 6,
            'structural' => 8,
            'stored_document' => 4,
            'planner' => 8,
        ],
        'max_sources' => (int) env('RAG_LADDER_MAX_SOURCES', 10),
    ],

    'router' => [
        'local_max_sources' => (int) env('RAG_ROUTER_LOCAL_MAX_SOURCES', 3),
        'local_min_score' => (float) env('RAG_ROUTER_LOCAL_MIN_SCORE', 0.72),
        'local_max_context' => (int) env('RAG_ROUTER_LOCAL_MAX_CONTEXT', 2400),
        'remote_model' => env('RAG_CHAT_MODEL'),
        'local_model' => env('RAG_LOCAL_CHAT_MODEL', 'qwen2.5:7b-instruct'),
        'local_base_url' => env('RAG_LOCAL_CHAT_BASE_URL', 'http://127.0.0.1:11434'),
        'local_timeout' => (int) env('RAG_LOCAL_CHAT_TIMEOUT', 25),
    ],

    'answer_cache' => [
        'enabled' => (bool) env('RAG_ANSWER_CACHE_ENABLED', true),
        'exact' => (bool) env('RAG_ANSWER_CACHE_EXACT', true),
        'semantic' => (bool) env('RAG_ANSWER_CACHE_SEMANTIC', false),
        'min_similarity' => (float) env('RAG_ANSWER_CACHE_MIN_SIMILARITY', 0.97),
        'max_rows' => (int) env('RAG_ANSWER_CACHE_MAX_ROWS', 5000),
        'ttl_days' => (int) env('RAG_ANSWER_CACHE_TTL_DAYS', 30),
    ],

    'grounding' => [
        'mode' => env('RAG_GROUNDING_MODE', 'shadow'),
        'min_support' => (float) env('RAG_GROUNDING_MIN_SUPPORT', 0.34),
        'numeric_guard' => (bool) env('RAG_GROUNDING_NUMERIC_GUARD', true),
        'require_citations' => (bool) env('RAG_GROUNDING_REQUIRE_CITATIONS', true),
        'semantic_tier' => (bool) env('RAG_GROUNDING_SEMANTIC_TIER', false),
        'ambiguous_band' => [0.20, 0.55],
    ],

    'lexicon' => [
        'enabled' => (bool) env('RAG_LEXICON_ENABLED', true),
        'cache_key' => 'rag:lexicon:v1',
        'cache_ttl' => 900,
        'stopword_df' => (float) env('RAG_LEXICON_STOPWORD_DF', 0.60),
        'min_term_length' => (int) env('RAG_LEXICON_MIN_TERM_LENGTH', 3),
        'pmi_min' => (float) env('RAG_LEXICON_PMI_MIN', 2.0),
        'pmi_min_cooccur' => (int) env('RAG_LEXICON_MIN_COOCCUR', 4),
        'edges_per_term' => (int) env('RAG_LEXICON_EDGES_PER_TERM', 8),
        'expansion_per_query' => (int) env('RAG_LEXICON_EXPANSION_PER_QUERY', 6),
        'trigram_min_score' => (float) env('RAG_LEXICON_TRIGRAM_MIN_SCORE', 0.62),
    ],

    'autotune' => [
        'enabled' => (bool) env('RAG_AUTOTUNE_ENABLED', false),
        'schedule' => env('RAG_AUTOTUNE_SCHEDULE', '03:20'),
        'iterations' => (int) env('RAG_AUTOTUNE_ITERATIONS', 240),
        'min_cases' => (int) env('RAG_AUTOTUNE_MIN_CASES', 25),
        'latency_p95_ms' => (int) env('RAG_AUTOTUNE_LATENCY_P95_MS', 12000),
        'max_unsupported' => (float) env('RAG_AUTOTUNE_MAX_UNSUPPORTED', 0.05),
        'require_no_regression' => true,
    ],

    'trace' => [
        'enabled' => (bool) env('RAG_TRACE_ENABLED', true),
        'retain_days' => (int) env('RAG_TRACE_RETAIN_DAYS', 90),
        'store_queries' => (bool) env('RAG_TRACE_STORE_QUERIES', true),
    ],
];
