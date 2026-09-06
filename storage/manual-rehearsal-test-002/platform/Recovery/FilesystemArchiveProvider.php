<?php
declare(strict_types=1);

namespace Platform\Recovery;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

final class FilesystemArchiveProvider
{
    /**
     * @param array<int,string> $includeRoots
     * @param array<int,string> $excludePrefixes
     * @return array<string,mixed>
     */
    public function createArchive(string $root, string $targetZip, array $includeRoots, array $excludePrefixes = []): array
    {
        $root = rtrim($root, '/');
        $targetDirectory = dirname($targetZip);
        if (!is_dir($targetDirectory) || !is_writable($targetDirectory)) {
            return $this->failure('Target archive directory is not writable.');
        }

        $zip = new ZipArchive();
        if ($zip->open($targetZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return $this->failure('Unable to create filesystem archive.');
        }

        $included = [];
        $skipped = [];

        foreach ($includeRoots as $relativeRoot) {
            $relativeRoot = $this->normalizeRelativePath($relativeRoot);
            if ($relativeRoot === null) {
                $skipped[] = ['path' => (string)$relativeRoot, 'reason' => 'invalid path'];
                continue;
            }

            $source = $root . '/' . $relativeRoot;
            if (!file_exists($source)) {
                $skipped[] = ['path' => $relativeRoot, 'reason' => 'missing'];
                continue;
            }

            if (is_file($source)) {
                if (is_link($source)) {
                    $skipped[] = ['path' => $relativeRoot, 'reason' => 'symlink'];
                    continue;
                }
                if ($this->isExcluded($relativeRoot, $excludePrefixes)) {
                    $skipped[] = ['path' => $relativeRoot, 'reason' => 'excluded'];
                    continue;
                }
                $zip->addFile($source, $relativeRoot);
                $included[] = $relativeRoot;
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->isLink()) {
                    continue;
                }
                $absolute = (string)$file->getPathname();
                $relative = str_replace('\\', '/', substr($absolute, strlen($root) + 1));
                if ($this->isExcluded($relative, $excludePrefixes)) {
                    $skipped[] = ['path' => $relative, 'reason' => 'excluded'];
                    continue;
                }
                $zip->addFile($absolute, $relative);
                $included[] = $relative;
            }
        }

        $zip->close();

        return [
            'ok' => true,
            'errors' => [],
            'warnings' => [],
            'provider' => 'ziparchive',
            'included' => $included,
            'skipped' => $skipped,
            'payload' => [
                'path' => basename($targetZip),
                'sha256' => hash_file('sha256', $targetZip) ?: '',
                'size_bytes' => filesize($targetZip) ?: 0,
            ],
            'preservation_inventory_sha256' => hash('sha256', json_encode([
                'include_roots' => array_values($includeRoots),
                'exclude_prefixes' => array_values($excludePrefixes),
                'included' => $included,
                'skipped' => $skipped,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: ''),
        ];
    }

    /** @return array<string,mixed> */
    private function failure(string $message): array
    {
        return [
            'ok' => false,
            'errors' => [$message],
            'warnings' => [],
        ];
    }

    private function normalizeRelativePath(string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '..')) {
            return null;
        }

        return $path;
    }

    /** @param array<int,string> $excludePrefixes */
    private function isExcluded(string $relativePath, array $excludePrefixes): bool
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath), '/');
        foreach ($excludePrefixes as $prefix) {
            $prefix = trim(str_replace('\\', '/', $prefix), '/');
            if ($prefix !== '' && ($relativePath === $prefix || str_starts_with($relativePath, $prefix . '/'))) {
                return true;
            }
        }

        return false;
    }
}
