<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

trait StudioReferenceDiscoveryPatternTrait
{
    /**
     * @return array<int,array{needle:string,match_type:string,confidence:string}>
     */
    public static function patterns(string $source, string $target, string $ownerKey = ''): array
    {
        unset($ownerKey);
        $basename = basename($source);
        $patterns = [];

        if (self::isFolderRenameOperation($source, $target)) {
            if ($source !== '') {
                $patterns[] = ['needle' => $source, 'match_type' => 'exact path', 'confidence' => 'high'];
                $patterns[] = ['needle' => rtrim($source, '/') . '/', 'match_type' => 'folder path', 'confidence' => 'high'];
            }
            if (str_starts_with($source, 'apps/')) {
                $ownerPath = substr($source, 5);
                if ($ownerPath !== '') {
                    $patterns[] = ['needle' => $ownerPath, 'match_type' => 'owner path', 'confidence' => 'high'];
                    $patterns[] = ['needle' => '/' . trim($ownerPath, '/') . '/', 'match_type' => 'owner path segment', 'confidence' => 'high'];
                }
            }
            $namespacePath = self::pathToNamespace($source);
            if ($namespacePath !== '') {
                $patterns[] = ['needle' => $namespacePath . '\\', 'match_type' => 'namespace path', 'confidence' => 'high'];
                $patterns[] = ['needle' => 'namespace ' . $namespacePath, 'match_type' => 'namespace declaration', 'confidence' => 'high'];
                $patterns[] = ['needle' => 'use ' . $namespacePath, 'match_type' => 'import path', 'confidence' => 'high'];
                $patterns[] = ['needle' => str_replace('\\', '\\\\', $namespacePath), 'match_type' => 'escaped namespace path', 'confidence' => 'high'];
            }
            return self::dedupePatterns($patterns);
        }

        if ($source !== '') {
            $patterns[] = ['needle' => $source, 'match_type' => 'exact path', 'confidence' => 'high'];
        }
        if ($basename !== '') {
            $matchType = 'basename';
            if ($basename === 'plugin.json') {
                $matchType = 'manifest/plugin reference';
            } elseif ($basename === 'menu.php') {
                $matchType = 'navigation/menu reference';
            } elseif ($basename === 'migrations') {
                $matchType = 'migration path reference';
            }
            $patterns[] = ['needle' => $basename, 'match_type' => $matchType, 'confidence' => 'medium'];
        }
        if (str_ends_with($source, '/migrations')) {
            $patterns[] = ['needle' => 'migrations/', 'match_type' => 'migration path reference', 'confidence' => 'medium'];
        }
        return self::dedupePatterns($patterns);
    }

    /** @param array<string,mixed> $candidate @param array<string,mixed> $existing */
    public static function shouldPreferReference(array $candidate, array $existing): bool
    {
        $candidateScore = self::matchSpecificityScore((string)($candidate['match_type'] ?? ''));
        $existingScore = self::matchSpecificityScore((string)($existing['match_type'] ?? ''));
        if ($candidateScore !== $existingScore) {
            return $candidateScore > $existingScore;
        }
        return strlen((string)($candidate['matched_pattern'] ?? '')) > strlen((string)($existing['matched_pattern'] ?? ''));
    }

    public static function proposedReplacement(string $matchType, string $needle, string $source, string $target): string
    {
        if (!self::isFolderRenameOperation($source, $target) || $needle === '') {
            return '';
        }
        $sourceOwnerPath = str_starts_with($source, 'apps/') ? substr($source, 5) : '';
        $targetOwnerPath = str_starts_with($target, 'apps/') ? substr($target, 5) : '';
        $sourceNamespacePath = self::pathToNamespace($source);
        $targetNamespacePath = self::pathToNamespace($target);

        if ($matchType === 'exact path' && $needle === $source) {
            return $target;
        }
        if ($matchType === 'folder path' && $needle === rtrim($source, '/') . '/') {
            return rtrim($target, '/') . '/';
        }
        if ($sourceOwnerPath !== '' && $targetOwnerPath !== '' && $matchType === 'owner path' && $needle === $sourceOwnerPath) {
            return $targetOwnerPath;
        }
        if ($sourceOwnerPath !== '' && $targetOwnerPath !== '' && $matchType === 'owner path segment' && $needle === '/' . trim($sourceOwnerPath, '/') . '/') {
            return '/' . trim($targetOwnerPath, '/') . '/';
        }
        if ($sourceNamespacePath !== '' && $targetNamespacePath !== '' && $matchType === 'namespace path' && $needle === $sourceNamespacePath . '\\') {
            return $targetNamespacePath . '\\';
        }
        if ($sourceNamespacePath !== '' && $targetNamespacePath !== '' && $matchType === 'namespace declaration' && $needle === 'namespace ' . $sourceNamespacePath) {
            return 'namespace ' . $targetNamespacePath;
        }
        if ($sourceNamespacePath !== '' && $targetNamespacePath !== '' && $matchType === 'import path' && $needle === 'use ' . $sourceNamespacePath) {
            return 'use ' . $targetNamespacePath;
        }
        if ($sourceNamespacePath !== '' && $targetNamespacePath !== '' && $matchType === 'escaped namespace path' && $needle === str_replace('\\', '\\\\', $sourceNamespacePath)) {
            return str_replace('\\', '\\\\', $targetNamespacePath);
        }
        return '';
    }

    private static function matchSpecificityScore(string $matchType): int
    {
        return (int)([
            'namespace declaration' => 500,
            'import path' => 490,
            'namespace path' => 480,
            'escaped namespace path' => 470,
            'exact path' => 400,
            'folder path' => 390,
            'owner path segment' => 380,
            'owner path' => 370,
            'manifest/plugin reference' => 320,
            'navigation/menu reference' => 320,
            'migration path reference' => 310,
            'basename' => 300,
        ][$matchType] ?? 0);
    }

    private static function isFolderRenameOperation(string $source, string $target): bool
    {
        return $source !== ''
            && $target !== ''
            && pathinfo($source, PATHINFO_EXTENSION) === ''
            && pathinfo($target, PATHINFO_EXTENSION) === ''
            && self::dirnamePath($source) === self::dirnamePath($target)
            && basename($source) !== basename($target);
    }

    private static function pathToNamespace(string $path): string
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/')), static fn(string $segment): bool => $segment !== ''));
        if ($segments === []) {
            return '';
        }
        $parts = [];
        foreach ($segments as $segment) {
            if (!preg_match('/^[A-Za-z0-9_]+$/', $segment)) {
                return '';
            }
            $parts[] = ctype_lower($segment) ? ucfirst($segment) : $segment;
        }
        return implode('\\', $parts);
    }

    /** @param array<int,array{needle:string,match_type:string,confidence:string}> $patterns */
    private static function dedupePatterns(array $patterns): array
    {
        $deduped = [];
        foreach ($patterns as $pattern) {
            $deduped[$pattern['needle'] . '|' . $pattern['match_type']] = $pattern;
        }
        return array_values($deduped);
    }

    private static function dirnamePath(string $path): string
    {
        $normalized = rtrim(str_replace('\\', '/', $path), '/');
        if ($normalized === '' || !str_contains($normalized, '/')) {
            return '';
        }
        $directory = dirname($normalized);
        return $directory === '.' ? '' : str_replace('\\', '/', $directory);
    }
}
