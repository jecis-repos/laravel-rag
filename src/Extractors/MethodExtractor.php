<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Extractors;

use Jekabs\LaravelRag\Graph\ExtractionResult;
use Jekabs\LaravelRag\Graph\KnowledgeNode;
use PhpParser\Node;

/**
 * Extracts method declarations from classes, including visibility,
 * return types, and parameter signatures.
 */
final class MethodExtractor extends AbstractPhpExtractor
{
    public function type(): string
    {
        return 'method';
    }

    public function extract(string $filePath, string $content): ExtractionResult
    {
        $stmts = $this->parse($content);
        if ($stmts === null) {
            return new ExtractionResult();
        }

        $nodes = [];

        $methods = $this->findNodes($stmts, fn (Node $n) => $n instanceof Node\Stmt\ClassMethod);

        /** @var Node\Stmt\ClassMethod $method */
        foreach ($methods as $method) {
            $visibility = match (true) {
                $method->isPublic() => 'public',
                $method->isProtected() => 'protected',
                $method->isPrivate() => 'private',
                default => 'public',
            };

            $params = array_map(function (Node\Param $param) {
                $type = $param->type ? $this->typeToString($param->type) : 'mixed';
                $name = '$' . ($param->var instanceof Node\Expr\Variable ? $param->var->name : '?');
                return $type . ' ' . $name;
            }, $method->params);

            $returnType = $method->returnType ? $this->typeToString($method->returnType) : null;

            $nodes[] = new KnowledgeNode(
                sourcePath: $filePath,
                type: 'method',
                name: $method->name->toString(),
                content: $this->nodeSource($method, $content),
                startLine: $method->getStartLine(),
                endLine: $method->getEndLine(),
                metadata: [
                    'visibility' => $visibility,
                    'static' => $method->isStatic(),
                    'abstract' => $method->isAbstract(),
                    'params' => implode(', ', $params),
                    'return_type' => $returnType,
                ],
            );
        }

        return new ExtractionResult($nodes);
    }

    private function typeToString(Node $type): string
    {
        return match (true) {
            $type instanceof Node\Identifier => $type->toString(),
            $type instanceof Node\Name => $type->toString(),
            $type instanceof Node\NullableType => '?' . $this->typeToString($type->type),
            $type instanceof Node\UnionType => implode('|', array_map([$this, 'typeToString'], $type->types)),
            $type instanceof Node\IntersectionType => implode('&', array_map([$this, 'typeToString'], $type->types)),
            default => 'mixed',
        };
    }
}
