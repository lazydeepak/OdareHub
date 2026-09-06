<?php

declare(strict_types=1);

namespace Apps\Studio\Tools\CustomizationStudio\Diagnose\TokenImpactExplorer\Services;

final class TokenImpactDiscoveryService
{
    private const SCAN_DIRS = [
        'apps',
        'plugins',
    ];

    private const CSS_ASSETS_DIR = 'public/assets/apps';

    private const CACHE_TTL = 300;

    private static ?array $cachedTokens = null;
    private static int $cacheTime = 0;

    public static function discoverAllTokens(): array
    {
        $now = time();
        if (self::$cachedTokens !== null && ($now - self::$cacheTime) < self::CACHE_TTL) {
            return self::$cachedTokens;
        }

        $catalogTokens = self::loadSocketCatalogTokens();
        $runtimeTokens = self::scanRuntimeTokens();
        $merged = self::mergeTokenLists($catalogTokens, $runtimeTokens);

        self::$cachedTokens = $merged;
        self::$cacheTime = $now;

        return $merged;
    }

    public static function exploreToken(string $tokenName): array
    {
        $normalized = self::normalizeTokenName($tokenName);
        $allTokens = self::discoverAllTokens();
        $catalogMatch = null;

        foreach ($allTokens as $token) {
            $matchName = $token['token_name'] ?? '';
            $altNames = [$matchName];
            if (!empty($token['advanced_token'])) {
                $altNames[] = $token['advanced_token'];
            }
            if (!empty($token['socket_id'])) {
                $altNames[] = $token['socket_id'];
            }
            if (in_array($normalized, $altNames, true)) {
                $catalogMatch = $token;
                break;
            }
            if (in_array($tokenName, $altNames, true)) {
                $catalogMatch = $token;
                break;
            }
        }

        $definitions = self::scanDefinitions($normalized);
        $references = self::scanReferences($normalized);

        $ownerMap = [];
        foreach ($definitions as $d) {
            $owner = $d['owner'];
            if (!isset($ownerMap[$owner])) {
                $ownerMap[$owner] = ['name' => $owner, 'count' => 0, 'files' => []];
            }
            $ownerMap[$owner]['count']++;
            $fileKey = $d['file'];
            if (!in_array($fileKey, $ownerMap[$owner]['files'], true)) {
                $ownerMap[$owner]['files'][] = $fileKey;
            }
        }
        foreach ($references as $r) {
            $owner = $r['owner'];
            if (!isset($ownerMap[$owner])) {
                $ownerMap[$owner] = ['name' => $owner, 'count' => 0, 'files' => []];
            }
            $ownerMap[$owner]['count']++;
            $fileKey = $r['file'];
            if (!in_array($fileKey, $ownerMap[$owner]['files'], true)) {
                $ownerMap[$owner]['files'][] = $fileKey;
            }
        }
        uasort($ownerMap, fn($a, $b) => $b['count'] - $a['count']);

        $total = count($definitions) + count($references);

        return [
            'token_name' => $normalized,
            'input_name' => $tokenName,
            'catalog_match' => $catalogMatch,
            'total_occurrences' => $total,
            'definitions' => $definitions,
            'references' => $references,
            'owners' => array_values($ownerMap),
        ];
    }

    private static function normalizeTokenName(string $name): string
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return '';
        }
        if (str_starts_with($trimmed, '--')) {
            return $trimmed;
        }
        return '--' . ltrim($trimmed, '-');
    }

    private static function loadSocketCatalogTokens(): array
    {
        $dirPath = APP_ROOT . '/apps/Shell/DesignSystem/Resources/socket-catalog';
        if (!is_dir($dirPath)) {
            return [];
        }

        $matches = glob($dirPath . '/*.json');
        if ($matches === false || $matches === []) {
            return [];
        }

        sort($matches, SORT_STRING);
        $tokens = [];
        foreach ($matches as $path) {
            $raw = @file_get_contents($path);
            $decoded = json_decode($raw, true);
            if (!is_array($decoded) || !isset($decoded['sockets'])) {
                continue;
            }
            $catalogId = (string)($decoded['catalog'] ?? pathinfo($path, PATHINFO_FILENAME));
            foreach ($decoded['sockets'] as $socket) {
                if (!is_array($socket)) {
                    continue;
                }
                $socketId = (string)($socket['socket'] ?? '');
                if ($socketId === '') {
                    continue;
                }
                $advancedToken = (string)($socket['advanced_token'] ?? '');
                $tokenName = $advancedToken !== '' ? $advancedToken : '--' . str_replace('.', '-', $socketId);
                $tokens[] = [
                    'source' => 'catalog',
                    'catalog_id' => $catalogId,
                    'socket_id' => $socketId,
                    'token_name' => $tokenName,
                    'label' => (string)($socket['label'] ?? $socketId),
                    'category' => (string)($socket['category'] ?? ''),
                    'owner' => (string)($socket['owner'] ?? 'shell'),
                    'value_type' => (string)($socket['value_type'] ?? ''),
                    'advanced_token' => $advancedToken,
                    'description' => (string)($socket['description'] ?? ''),
                    'scope' => (string)($socket['scope'] ?? ''),
                    'runtime_consumption' => (string)($socket['runtime_consumption'] ?? 'catalog_only_not_consumed'),
                ];
            }
        }
        return $tokens;
    }

    private static function scanRuntimeTokens(): array
    {
        $tokens = [];
        $seen = [];

        $cssDirs = [
            APP_ROOT . '/public/assets/apps',
            APP_ROOT . '/apps',
        ];

        foreach ($cssDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $ext = strtolower($file->getExtension());
                if (!in_array($ext, ['css', 'php', 'js'], true)) {
                    continue;
                }
                $path = $file->getRealPath();
                $contents = @file_get_contents($path);
                if ($contents === false || $contents === '') {
                    continue;
                }
                if (preg_match_all('/var\s*\(\s*(--[\w-]+)\s*\)/i', $contents, $matches)) {
                    foreach ($matches[1] as $tokenName) {
                        $lower = strtolower($tokenName);
                        if (!isset($seen[$lower])) {
                            $seen[$lower] = true;
                            $owner = self::resolveOwnerFromPath($path);
                            $tokens[] = [
                                'source' => 'runtime',
                                'token_name' => $tokenName,
                                'label' => $tokenName,
                                'category' => 'runtime',
                                'owner' => $owner,
                                'value_type' => '',
                                'description' => 'Referenced in ' . self::relativePath($path),
                            ];
                        }
                    }
                }
            }
        }

        usort($tokens, fn($a, $b) => strcmp($a['token_name'], $b['token_name']));
        return $tokens;
    }

    private static function mergeTokenLists(array $catalog, array $runtime): array
    {
        $seen = [];
        $merged = [];

        foreach ($catalog as $token) {
            $key = strtolower($token['token_name']);
            $seen[$key] = true;
            $merged[] = $token;
        }

        foreach ($runtime as $token) {
            $key = strtolower($token['token_name']);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $merged[] = $token;
            }
        }

        usort($merged, fn($a, $b) => strcmp($a['token_name'], $b['token_name']));
        return $merged;
    }

    private static function scanDefinitions(string $tokenName): array
    {
        $results = [];
        $pattern = '/(' . preg_quote($tokenName, '/') . ')\s*:/i';

        $cssDirs = [
            APP_ROOT . '/public/assets/apps',
            APP_ROOT . '/apps',
        ];

        foreach ($cssDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $ext = strtolower($file->getExtension());
                if (!in_array($ext, ['css', 'php', 'js'], true)) {
                    continue;
                }
                $path = $file->getRealPath();
                $contents = @file_get_contents($path);
                if ($contents === false || $contents === '') {
                    continue;
                }
                $lines = explode("\n", $contents);
                foreach ($lines as $lineNum => $line) {
                    if (preg_match($pattern, $line)) {
                        $results[] = [
                            'owner' => self::resolveOwnerFromPath($path),
                            'file' => self::relativePath($path),
                            'line' => $lineNum + 1,
                            'value' => trim($line),
                        ];
                    }
                }
            }
        }

        return $results;
    }

    private static function scanReferences(string $tokenName): array
    {
        $results = [];
        $pattern = '/var\s*\(\s*' . preg_quote($tokenName, '/') . '\s*\)/i';

        $scanDirs = [
            APP_ROOT . '/apps',
            APP_ROOT . '/plugins',
        ];

        foreach ($scanDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $ext = strtolower($file->getExtension());
                if (!in_array($ext, ['php', 'css', 'js'], true)) {
                    continue;
                }
                $path = $file->getRealPath();
                $contents = @file_get_contents($path);
                if ($contents === false || $contents === '') {
                    continue;
                }
                $lines = explode("\n", $contents);
                foreach ($lines as $lineNum => $line) {
                    if (preg_match($pattern, $line)) {
                        $results[] = [
                            'owner' => self::resolveOwnerFromPath($path),
                            'file' => self::relativePath($path),
                            'line' => $lineNum + 1,
                            'value' => trim($line),
                        ];
                    }
                }
            }
        }

        return $results;
    }

    private static function resolveOwnerFromPath(string $realPath): string
    {
        $relative = self::relativePath($realPath);
        $parts = explode('/', $relative);

        if (isset($parts[0]) && $parts[0] === 'apps' && isset($parts[1])) {
            return $parts[1];
        }
        if (isset($parts[0]) && $parts[0] === 'plugins' && isset($parts[1])) {
            return 'plugin:' . $parts[1];
        }
        if (isset($parts[0]) && $parts[0] === 'public' && isset($parts[3])) {
            return $parts[3];
        }

        return 'other';
    }

    private static function relativePath(string $realPath): string
    {
        $root = rtrim(APP_ROOT, '/') . '/';
        if (str_starts_with($realPath, $root)) {
            return substr($realPath, strlen($root));
        }
        return $realPath;
    }
}
