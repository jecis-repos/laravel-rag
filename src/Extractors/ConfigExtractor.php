<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Extractors;

use Jekabs\LaravelRag\Graph\ExtractionResult;
use Jekabs\LaravelRag\Graph\KnowledgeNode;

/**
 * Extracts top-level config keys from Laravel config files.
 */
final class ConfigExtractor extends AbstractPhpExtractor
{
    public function type(): string
    {
        return 'config';
    }

    public function supports(string $filePath, string $content): bool
    {
        return parent::supports($filePath, $content)
            && str_contains($filePath, '/config/')
            && str_contains($content, 'return [');
    }

    public function extract(string $filePath, string $content): ExtractionResult
    {
        $configName = pathinfo($filePath, PATHINFO_FILENAME);

        return new ExtractionResult([
            new KnowledgeNode(
                sourcePath: $filePath,
                type: 'config',
                name: $configName,
                content: $content,
                metadata: ['config_file' => $configName],
            ),
        ]);
    }
}
