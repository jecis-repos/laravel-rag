# Contributing to laravel-rag

Thanks for considering contributing! This package benefits from community input — whether that's bug reports, feature ideas, or pull requests.

## Bug Reports

Before filing a bug, please check [existing issues](https://github.com/jecis-repos/laravel-rag/issues). If your bug is new, include:

- PHP and Laravel versions
- PostgreSQL version and pgvector extension version
- Steps to reproduce
- Expected vs actual behavior
- Relevant error messages or stack traces

## Feature Requests

Open an issue with the `enhancement` label. Describe the use case — what problem does it solve? Features that align with the package's core purpose (understanding Laravel code structure) are prioritized.

## Pull Requests

1. Fork the repo and create a branch from `main`
2. Write tests for any new functionality
3. Ensure all tests pass: `vendor/bin/phpunit`
4. Follow existing code style (PSR-12, strict types, type hints)
5. Keep PRs focused — one feature or fix per PR

### Development Setup

```bash
git clone https://github.com/jecis-repos/laravel-rag.git
cd laravel-rag
composer install
vendor/bin/phpunit
```

Most tests run without a database. Integration tests that require PostgreSQL + pgvector are skipped automatically when the database is unavailable.

### Adding a New Extractor

The most likely contribution is a new AST extractor. To add one:

1. Create a class in `src/Extractors/` implementing `ExtractorContract`
2. Register it in `config/laravel-rag.php` under `extractors`
3. Add tests in `tests/` covering the extraction logic
4. Update the extractor table in `README.md`

### Code Style

- `declare(strict_types=1)` on every PHP file
- Full type hints on parameters and return types
- No unnecessary dependencies — this package stays lean

## Questions?

Open a discussion or issue. No question is too small.
