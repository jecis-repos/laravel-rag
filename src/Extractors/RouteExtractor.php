<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Extractors;

use Jekabs\LaravelRag\Graph\ExtractionResult;
use Jekabs\LaravelRag\Graph\KnowledgeEdge;
use Jekabs\LaravelRag\Graph\KnowledgeNode;
use PhpParser\Node;

/**
 * Extracts route definitions: Route::get/post/put/delete/resource/apiResource.
 * Creates edges from routes to their controllers.
 */
final class RouteExtractor extends AbstractPhpExtractor
{
    private const ROUTE_METHODS = ['get', 'post', 'put', 'patch', 'delete', 'any', 'resource', 'apiResource'];

    public function type(): string
    {
        return 'route';
    }

    public function supports(string $filePath, string $content): bool
    {
        return parent::supports($filePath, $content) && str_contains($content, 'Route::');
    }

    public function extract(string $filePath, string $content): ExtractionResult
    {
        $stmts = $this->parse($content);
        if ($stmts === null) {
            return new ExtractionResult();
        }

        $nodes = [];
        $edges = [];

        $calls = $this->findNodes($stmts, fn (Node $n) =>
            $n instanceof Node\Expr\StaticCall
            && $n->class instanceof Node\Name
            && str_ends_with($n->class->toString(), 'Route')
            && $n->name instanceof Node\Identifier
            && in_array($n->name->toString(), self::ROUTE_METHODS, true)
        );

        foreach ($calls as $call) {
            /** @var Node\Expr\StaticCall $call */
            $method = $call->name->toString();

            $uri = isset($call->args[0]) && $call->args[0]->value instanceof Node\Scalar\String_
                ? $call->args[0]->value->value
                : null;

            $controller = $this->resolveController($call->args[1] ?? null);

            $nodes[] = new KnowledgeNode(
                sourcePath: $filePath,
                type: 'route',
                name: strtoupper($method) . ' ' . ($uri ?? '?'),
                content: $this->nodeSource($call, $content),
                startLine: $call->getStartLine(),
                endLine: $call->getEndLine(),
                metadata: [
                    'http_method' => $method,
                    'uri' => $uri,
                    'controller' => $controller,
                ],
            );

            if ($controller) {
                $edges[] = new KnowledgeEdge($filePath, $controller, 'routes_to', ['uri' => $uri]);
            }
        }

        return new ExtractionResult($nodes, $edges);
    }

    private function resolveController(?Node\Arg $arg): ?string
    {
        if (!$arg) {
            return null;
        }

        $value = $arg->value;

        // [Controller::class, 'method']
        if ($value instanceof Node\Expr\Array_
            && isset($value->items[0])
            && $value->items[0] instanceof Node\Expr\ArrayItem
            && $value->items[0]->value instanceof Node\Expr\ClassConstFetch
            && $value->items[0]->value->class instanceof Node\Name) {
            return $value->items[0]->value->class->toString();
        }

        // Controller::class (resource routes)
        if ($value instanceof Node\Expr\ClassConstFetch
            && $value->class instanceof Node\Name) {
            return $value->class->toString();
        }

        return null;
    }
}
