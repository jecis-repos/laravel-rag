<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Services;

/**
 * Format-aware content chunking for embedding.
 *
 * Splits documents by structural boundaries (PHP class/function declarations,
 * Markdown headers, or paragraphs), merges small blocks up to the size limit,
 * and adds overlap between consecutive chunks for context continuity.
 */
final class ChunkingService
{
    public function __construct(
        private readonly int $maxChunkChars = 3200,
        private readonly int $overlapChars = 200,
    ) {}

    /**
     * Chunk a document into embeddable pieces.
     *
     * @return list<string>
     */
    public function chunk(string $content, string $format): array
    {
        if (trim($content) === '') {
            return [];
        }

        $blocks = match ($format) {
            'markdown' => $this->splitMarkdown($content),
            'php' => $this->splitPhp($content),
            default => $this->splitByParagraphs($content),
        };

        if ($blocks === []) {
            return [trim($content)];
        }

        return $this->mergeAndOverlap($blocks);
    }

    /**
     * Detect format from file extension.
     */
    public function detectFormat(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'md', 'mdx' => 'markdown',
            'php' => 'php',
            'js', 'ts', 'jsx', 'tsx' => 'javascript',
            'py' => 'python',
            'go' => 'go',
            'yml', 'yaml' => 'yaml',
            'json' => 'json',
            default => 'text',
        };
    }

    /**
     * @return list<string>
     */
    private function splitMarkdown(string $content): array
    {
        $blocks = preg_split('/(?=^#{1,3}\s)/m', $content);

        return $blocks !== false
            ? array_values(array_filter($blocks, fn (string $b): bool => trim($b) !== ''))
            : [$content];
    }

    /**
     * @return list<string>
     */
    private function splitPhp(string $content): array
    {
        $blocks = preg_split(
            '/(?=^\s*(?:class|interface|trait|enum|function|abstract\s+class|final\s+class|final\s+readonly\s+class|readonly\s+class)\s)/m',
            $content,
        );

        return $blocks !== false
            ? array_values(array_filter($blocks, fn (string $b): bool => trim($b) !== ''))
            : [$content];
    }

    /**
     * @return list<string>
     */
    private function splitByParagraphs(string $content): array
    {
        $blocks = preg_split('/\n{2,}/', $content);

        return $blocks !== false
            ? array_values(array_filter($blocks, fn (string $b): bool => trim($b) !== ''))
            : [$content];
    }

    /**
     * @param  list<string>  $blocks
     * @return list<string>
     */
    private function mergeAndOverlap(array $blocks): array
    {
        $merged = $this->mergeSmallBlocks($blocks);

        return $this->addOverlap($merged);
    }

    /**
     * @param  list<string>  $blocks
     * @return list<string>
     */
    private function mergeSmallBlocks(array $blocks): array
    {
        $chunks = [];
        $current = '';

        foreach ($blocks as $block) {
            if (strlen($current) + strlen($block) <= $this->maxChunkChars) {
                $current .= ($current !== '' ? "\n\n" : '') . $block;
            } else {
                if ($current !== '') {
                    $chunks[] = $current;
                }
                $current = $block;
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * @param  list<string>  $chunks
     * @return list<string>
     */
    private function addOverlap(array $chunks): array
    {
        if (count($chunks) <= 1) {
            return $chunks;
        }

        $overlapped = [$chunks[0]];

        for ($i = 1, $count = count($chunks); $i < $count; $i++) {
            $overlapSize = min($this->overlapChars, strlen($chunks[$i - 1]));
            $prevTail = substr($chunks[$i - 1], -$overlapSize);
            $combined = $prevTail . "\n\n" . $chunks[$i];

            if (strlen($combined) > $this->maxChunkChars + $this->overlapChars) {
                $combined = substr($combined, 0, $this->maxChunkChars + $this->overlapChars);
            }

            $overlapped[] = $combined;
        }

        return $overlapped;
    }
}
