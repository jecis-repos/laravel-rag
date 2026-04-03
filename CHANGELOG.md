# Changelog

All notable changes to `jekabs/laravel-rag` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-04-01

### Added
- 12 AST extractors for Laravel-specific code patterns (classes, methods, Eloquent relationships, morph maps, events/listeners, service bindings, routes, migrations, configs, Filament resources, middleware, validation rules)
- Knowledge graph with typed edges (belongs_to, has_many, listens_to, binds_to, routes_to, morph_alias, manages_model, etc.)
- 4-stage search pipeline: pgvector semantic search, ILIKE keyword search with BM25 scoring, Reciprocal Rank Fusion, multi-hop graph expansion
- Incremental indexing with content hash tracking (skips unchanged files)
- Git repository indexing support
- CLI commands: `rag:index`, `rag:search`, `rag:stats`
- Configurable embedding driver contract (bring your own: OpenAI, Ollama, etc.)
- Recency boost for recently modified files
- 81 tests covering extractors, BM25 algorithm, chunking, search pipeline, and end-to-end scenarios
- GitHub Actions CI for PHP 8.2, 8.3, 8.4

[1.0.0]: https://github.com/jecis-repos/laravel-rag/releases/tag/v1.0.0
