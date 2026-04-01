<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Extractors;

use Jekabs\LaravelRag\Graph\ExtractionResult;
use Jekabs\LaravelRag\Graph\KnowledgeNode;
use PhpParser\Node;

/**
 * Extracts migration definitions: Schema::create/table calls with column definitions.
 */
final class MigrationExtractor extends AbstractPhpExtractor
{
    public function type(): string
    {
        return 'migration';
    }

    public function supports(string $filePath, string $content): bool
    {
        return parent::supports($filePath, $content) && str_contains($content, 'Schema::');
    }

    public function extract(string $filePath, string $content): ExtractionResult
    {
        $stmts = $this->parse($content);
        if ($stmts === null) {
            return new ExtractionResult();
        }

        $nodes = [];

        $calls = $this->findNodes($stmts, fn (Node $n) =>
            $n instanceof Node\Expr\StaticCall
            && $n->class instanceof Node\Name
            && str_ends_with($n->class->toString(), 'Schema')
            && $n->name instanceof Node\Identifier
            && in_array($n->name->toString(), ['create', 'table'], true)
        );

        foreach ($calls as $call) {
            /** @var Node\Expr\StaticCall $call */
            $tableName = isset($call->args[0]) && $call->args[0]->value instanceof Node\Scalar\String_
                ? $call->args[0]->value->value
                : 'unknown';

            $operation = $call->name->toString();

            $nodes[] = new KnowledgeNode(
                sourcePath: $filePath,
                type: 'migration',
                name: $operation . ':' . $tableName,
                content: $this->nodeSource($call, $content),
                startLine: $call->getStartLine(),
                endLine: $call->getEndLine(),
                metadata: [
                    'table' => $tableName,
                    'operation' => $operation,
                ],
            );
        }

        return new ExtractionResult($nodes);
    }
}
