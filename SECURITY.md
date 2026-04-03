# Security Policy

## Reporting a Vulnerability

If you discover a security vulnerability in laravel-rag, please report it responsibly:

**Email:** jekabsporietis@gmail.com

Please do **not** open a public issue for security vulnerabilities. I'll acknowledge receipt within 48 hours and work on a fix promptly.

## Scope

This package processes PHP source code via AST parsing. Security considerations include:
- File path traversal during indexing
- SQL injection in search queries (mitigated via parameterized queries)
- Embedding driver input sanitization
