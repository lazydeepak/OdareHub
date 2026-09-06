<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

trait StudioReferenceDiscoveryPathTrait
{
    /** @param mixed $prefixes @return array<int,string> */
    private static function normalizedPrefixes($prefixes): array
    {
        if (!is_array($prefixes)) {
            return [];
        }
        $normalized = [];
        foreach ($prefixes as $prefix) {
            $prefix = ltrim(str_replace('\\', '/', trim((string)$prefix)), '/');
            if ($prefix !== '') {
                $normalized[$prefix] = $prefix;
            }
        }
        return array_values($normalized);
    }

    /** @param array<int,string> $includePrefixes @param array<int,string> $excludePrefixes */
    private static function pathAllowed(string $relativePath, array $includePrefixes, array $excludePrefixes): bool
    {
        foreach ($excludePrefixes as $prefix) {
            if ($relativePath === rtrim($prefix, '/') || str_starts_with($relativePath, rtrim($prefix, '/') . '/')) {
                return false;
            }
        }
        if ($includePrefixes === []) {
            return true;
        }
        foreach ($includePrefixes as $prefix) {
            if ($relativePath === rtrim($prefix, '/') || str_starts_with($relativePath, rtrim($prefix, '/') . '/')) {
                return true;
            }
        }
        return false;
    }

    private static function isIncludedTextFile(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::INCLUDED_EXTENSIONS, true);
    }

    private static function isExcludedPath(string $path, string $rootPath): bool
    {
        $relative = str_replace('\\', '/', self::relativePath($path, $rootPath));
        foreach (explode('/', $relative) as $segment) {
            if (in_array($segment, self::EXCLUDED_SEGMENTS, true)) {
                return true;
            }
        }
        foreach (self::EXCLUDED_PATH_PARTS as $part) {
            if ($relative === $part || str_starts_with($relative, $part . '/')) {
                return true;
            }
        }
        return false;
    }

    private static function excerpt(string $line, string $needle): string
    {
        $line = trim(preg_replace('/\s+/', ' ', $line) ?? $line);
        if (strlen($line) <= 180) {
            return $line;
        }
        $position = strpos($line, $needle);
        if ($position === false) {
            return substr($line, 0, 177) . '...';
        }
        $start = max(0, $position - 70);
        return ($start > 0 ? '...' : '') . substr($line, $start, 177) . '...';
    }

    private static function relativePath(string $path, string $rootPath): string
    {
        if ($path === $rootPath) {
            return '';
        }
        $prefix = rtrim($rootPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (str_starts_with($path, $prefix)) {
            return str_replace('\\', '/', substr($path, strlen($prefix)));
        }
        return str_replace('\\', '/', $path);
    }

    private static function referenceKey(string $source, string $target): string
    {
        return basename($source) . ' -> ' . basename($target);
    }

    /** @return array<string,mixed> */
    private static function searchScope(string $root, int $scannedFiles): array
    {
        return [
            'root' => $root,
            'scanned_files' => $scannedFiles,
            'excluded_segments' => self::EXCLUDED_SEGMENTS,
            'excluded_path_parts' => self::EXCLUDED_PATH_PARTS,
            'included_extensions' => self::INCLUDED_EXTENSIONS,
        ];
    }
}
