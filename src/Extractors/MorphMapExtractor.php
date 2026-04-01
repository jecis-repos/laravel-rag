<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Extractors;

use Jekabs\LaravelRag\Graph\ExtractionResult;
use Jekabs\LaravelRag\Graph\KnowledgeEdge;
use Jekabs\LaravelRag\Graph\KnowledgeNode;
use PhpParser\Node;

/**
 * Extracts Eloquent morph map definitions from service providers.
 * Looks for Relation::morphMap([...]) or Relation::enforceMorphMap([...]) calls.
 */
final class MorphMapExtractor extends AbstractPhpExtractor
{
    public function type(): string
    {
        return 'morph_map';
    }

    public function supports(string $filePath, string $content): bool
    {
        return parent::supports($filePath, $content)
            && (str_contains($content, 'morphMap') || str_contains($content, 'enforceMorphMap'));
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
            && $n->name instanceof Node\Identifier
            && in_array($n->name->toString(), ['morphMap', 'enforceMorphMap'], true)
        );

        foreach ($calls as $call) {
            /** @var Node\Expr\StaticCall $call */
            if (!isset($call->args[0]) || !$call->args[0] instanceof Node\Arg) {
                continue;
            }

            $arg = $call->args[0]->value;
            if (!$arg instanceof Node\Expr\Array_) {
                continue;
            }

            $mappings = [];
            foreach ($arg->items as $item) {
                if (!$item instanceof Node\Expr\ArrayItem
                    || !$item->key instanceof Node\Scalar\String_) {
                    continue;
                }

                $alias = $item->key->value;
                $model = null;

                if ($item->value instanceof Node\Expr\ClassConstFetch
                    && $item->value->class instanceof Node\Name) {
                    $model = $item->value->class->toString();
                }

                if ($model) {
                    $mappings[$alias] = $model;
                    $edges[] = new KnowledgeEdge($filePath, $model, 'morph_alias', ['alias' => $alias]);
                }
            }

            if ($mappings) {
                $nodes[] = new KnowledgeNode(
                    sourcePath: $filePath,
                    type: 'morph_map',
                    name: 'morphMap',
                    content: $this->nodeSource($call, $content),
                    startLine: $call->getStartLine(),
                    endLine: $call->getEndLine(),
                    metadata: ['mappings' => $mappings],
                );
            }
        }

        return new ExtractionResult($nodes, $edges);
    }
}
