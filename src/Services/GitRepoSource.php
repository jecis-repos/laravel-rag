<?php

declare(strict_types=1);

namespace Jekabs\LaravelRag\Services;

use RuntimeException;

/**
 * Clones and manages local copies of git repositories for indexing.
 *
 * Uses git CLI for maximum compatibility — works with GitHub, GitLab,
 * Bitbucket, self-hosted repos, SSH keys, and HTTPS tokens.
 */
final class GitRepoSource
{
    private readonly string $storageBase;

    public function __construct(?string $storageBase = null)
    {
        $this->storageBase = $storageBase ?? storage_path('laravel-rag/repos');
    }

    /**
     * Ensure a local clone exists and is up-to-date.
     *
     * Returns the local path to the cloned repository.
     */
    public function sync(string $repoUrl, string $branch = 'main'): string
    {
        $localPath = $this->localPath($repoUrl);

        if (is_dir($localPath . '/.git')) {
            $this->pull($localPath, $branch);
        } else {
            $this->clone($repoUrl, $localPath, $branch);
        }

        return $localPath;
    }

    /**
     * Get the list of changed files since last index.
     *
     * If $sinceCommit is null, returns all files.
     *
     * @return list<string> Relative file paths
     */
    public function changedFiles(string $localPath, ?string $sinceCommit = null): array
    {
        if ($sinceCommit === null) {
            return $this->allFiles($localPath);
        }

        $output = $this->exec(
            sprintf('git diff --name-only --diff-filter=ACMR %s HEAD', escapeshellarg($sinceCommit)),
            $localPath,
        );

        return array_values(array_filter(explode("\n", $output), fn (string $f): bool => $f !== ''));
    }

    /**
     * Get the current HEAD commit SHA.
     */
    public function headCommit(string $localPath): string
    {
        return trim($this->exec('git rev-parse HEAD', $localPath));
    }

    /**
     * List all tracked files in the repository.
     *
     * @return list<string>
     */
    public function allFiles(string $localPath): array
    {
        $output = $this->exec('git ls-files', $localPath);

        return array_values(array_filter(explode("\n", $output), fn (string $f): bool => $f !== ''));
    }

    /**
     * Read a file from the cloned repository.
     */
    public function readFile(string $localPath, string $relativePath): ?string
    {
        $fullPath = $localPath . '/' . $relativePath;

        if (! is_file($fullPath) || ! is_readable($fullPath)) {
            return null;
        }

        $content = file_get_contents($fullPath);

        return $content !== false ? $content : null;
    }

    /**
     * Filter file paths by glob patterns.
     *
     * @param  list<string>  $files
     * @param  list<string>  $patterns  e.g. ['app/**\/*.php', 'config/*.php', 'README.md']
     * @return list<string>
     */
    public function filterByPatterns(array $files, array $patterns): array
    {
        if ($patterns === []) {
            return $files;
        }

        $matched = [];

        foreach ($files as $file) {
            foreach ($patterns as $pattern) {
                if (fnmatch($pattern, $file, FNM_PATHNAME)) {
                    $matched[] = $file;
                    break;
                }
            }
        }

        return $matched;
    }

    /**
     * Remove the local clone.
     */
    public function remove(string $repoUrl): void
    {
        $localPath = $this->localPath($repoUrl);

        if (is_dir($localPath)) {
            $this->exec('rm -rf ' . escapeshellarg($localPath));
        }
    }

    /**
     * Derive a stable local path from a repo URL.
     *
     * github.com/laravel/framework → storage/laravel-rag/repos/github.com/laravel/framework
     */
    private function localPath(string $repoUrl): string
    {
        // Normalize: strip protocol, .git suffix, trailing slashes
        $normalized = preg_replace('#^(https?://|git@|ssh://)#', '', $repoUrl) ?? $repoUrl;
        $normalized = preg_replace('#\.git$#', '', $normalized) ?? $normalized;
        $normalized = str_replace(':', '/', $normalized);
        $normalized = trim($normalized, '/');

        return $this->storageBase . '/' . $normalized;
    }

    private function clone(string $repoUrl, string $localPath, string $branch): void
    {
        $parentDir = dirname($localPath);

        if (! is_dir($parentDir)) {
            mkdir($parentDir, 0755, true);
        }

        $this->exec(sprintf(
            'git clone --depth 50 --single-branch --branch %s %s %s',
            escapeshellarg($branch),
            escapeshellarg($repoUrl),
            escapeshellarg($localPath),
        ));
    }

    private function pull(string $localPath, string $branch): void
    {
        $this->exec(sprintf('git checkout %s', escapeshellarg($branch)), $localPath);
        $this->exec('git pull --ff-only', $localPath);
    }

    /**
     * @throws RuntimeException
     */
    private function exec(string $command, ?string $cwd = null): string
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, $cwd);

        if (! is_resource($process)) {
            throw new RuntimeException("Failed to execute: {$command}");
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new RuntimeException("Command failed ({$exitCode}): {$command}\n{$stderr}");
        }

        return $stdout ?: '';
    }
}
