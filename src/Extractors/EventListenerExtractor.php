<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Extractors;

use Jekabs\LaravelRag\Graph\ExtractionResult;
use Jekabs\LaravelRag\Graph\KnowledgeEdge;
use Jekabs\LaravelRag\Graph\KnowledgeNode;
use PhpParser\Node;

/**
 * Extracts event/listener bindings from EventServiceProvider's $listen property
 * or Event::listen() calls.
 */
final class EventListenerExtractor extends AbstractPhpExtractor
{
    public function type(): string
    {
        return 'event_listener';
    }

    public function supports(string $filePath, string $content): bool
    {
        return parent::supports($filePath, $content)
            && (str_contains($content, '$listen') || str_contains($content, 'Event::listen'));
    }

    public function extract(string $filePath, string $content): ExtractionResult
    {
        $stmts = $this->parse($content);
        if ($stmts === null) {
            return new ExtractionResult();
        }

        $nodes = [];
        $edges = [];

        // Find $listen property (EventServiceProvider pattern)
        $properties = $this->findNodes($stmts, fn (Node $n) =>
            $n instanceof Node\Stmt\Property
            && count($n->props) > 0
            && $n->props[0]->name->toString() === 'listen'
        );

        foreach ($properties as $prop) {
            /** @var Node\Stmt\Property $prop */
            $default = $prop->props[0]->default ?? null;
            if (!$default instanceof Node\Expr\Array_) {
                continue;
            }

            foreach ($default->items as $item) {
                if (!$item instanceof Node\Expr\ArrayItem) {
                    continue;
                }

                $event = $this->resolveClassReference($item->key);
                if (!$event) {
                    continue;
                }

                $listeners = [];
                if ($item->value instanceof Node\Expr\Array_) {
                    foreach ($item->value->items as $listenerItem) {
                        if ($listenerItem instanceof Node\Expr\ArrayItem) {
                            $listener = $this->resolveClassReference($listenerItem->value);
                            if ($listener) {
                                $listeners[] = $listener;
                                $edges[] = new KnowledgeEdge($event, $listener, 'listened_by');
                            }
                        }
                    }
                }

                $nodes[] = new KnowledgeNode(
                    sourcePath: $filePath,
                    type: 'event_binding',
                    name: $event,
                    content: $this->nodeSource($item, $content),
                    startLine: $item->getStartLine(),
                    endLine: $item->getEndLine(),
                    metadata: ['listeners' => $listeners],
                );
            }
        }

        return new ExtractionResult($nodes, $edges);
    }

    private function resolveClassReference(?Node $node): ?string
    {
        if ($node instanceof Node\Expr\ClassConstFetch
            && $node->class instanceof Node\Name
            && $node->name instanceof Node\Identifier
            && $node->name->toString() === 'class') {
            return $node->class->toString();
        }

        if ($node instanceof Node\Scalar\String_) {
            return $node->value;
        }

        return null;
    }
}
