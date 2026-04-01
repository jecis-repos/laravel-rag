<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    |
    | The database connection to use for knowledge nodes, edges, and search
    | queries. Must be a PostgreSQL connection with pgvector extension.
    |
    */
    'connection' => env('RAG_DB_CONNECTION', 'pgsql'),

    /*
    |--------------------------------------------------------------------------
    | Embedding Dimensions
    |--------------------------------------------------------------------------
    |
    | The dimension of your embedding vectors. Common values:
    |   - 1536: OpenAI text-embedding-3-small / ada-002
    |   - 3072: OpenAI text-embedding-3-large
    |   - 1024: Cohere embed-v3
    |   - 768:  Many open-source models
    |
    */
    'embedding_dimensions' => env('RAG_EMBEDDING_DIMENSIONS', 1536),

    /*
    |--------------------------------------------------------------------------
    | Embedding Driver
    |--------------------------------------------------------------------------
    |
    | How to generate embeddings. Set to 'callback' and provide a closure
    | in the service provider, or implement EmbeddingDriverContract.
    |
    | Supported: 'null' (returns zero vectors for testing)
    |
    */
    'embedding_driver' => env('RAG_EMBEDDING_DRIVER', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Search Configuration
    |--------------------------------------------------------------------------
    */
    'search' => [
        // Max results per search stage
        'top_k' => 20,

        // Score penalty per hop in multi-hop graph traversal
        'hop_penalty' => 0.15,

        // Maximum traversal depth
        'max_hops' => 2,

        // BM25 parameters
        'bm25_k1' => 1.5,
        'bm25_b' => 0.75,

        // Reciprocal Rank Fusion constant
        'rrf_k' => 60,

        // Recency boost: multiplier for files modified in last N days
        'recency_days' => 7,
        'recency_boost' => 1.2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Chunking Configuration
    |--------------------------------------------------------------------------
    */
    'chunking' => [
        // Overlap in characters between adjacent chunks
        'overlap' => 200,

        // Maximum chunk size in characters (for non-AST fallback)
        'max_chunk_size' => 2000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Extractors
    |--------------------------------------------------------------------------
    |
    | AST extractors to run during indexing. Each must implement
    | ExtractorContract. Order matters — later extractors can reference
    | nodes created by earlier ones.
    |
    */
    'extractors' => [
        \Jekabs\LaravelRag\Extractors\ClassExtractor::class,
        \Jekabs\LaravelRag\Extractors\MethodExtractor::class,
        \Jekabs\LaravelRag\Extractors\RelationshipExtractor::class,
        \Jekabs\LaravelRag\Extractors\MorphMapExtractor::class,
        \Jekabs\LaravelRag\Extractors\EventListenerExtractor::class,
        \Jekabs\LaravelRag\Extractors\ServiceBindingExtractor::class,
        \Jekabs\LaravelRag\Extractors\RouteExtractor::class,
        \Jekabs\LaravelRag\Extractors\MigrationExtractor::class,
        \Jekabs\LaravelRag\Extractors\ConfigExtractor::class,
        \Jekabs\LaravelRag\Extractors\FilamentResourceExtractor::class,
        \Jekabs\LaravelRag\Extractors\MiddlewareExtractor::class,
        \Jekabs\LaravelRag\Extractors\ValidationRuleExtractor::class,
        \Jekabs\LaravelRag\Extractors\ChunkExtractor::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'nodes' => 'rag_knowledge_nodes',
        'edges' => 'rag_knowledge_edges',
    ],
];
