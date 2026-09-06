<?php
declare(strict_types=1);

namespace Platform\Search;

/**
 * Session-backed search index for candidates that were already resolved and
 * authorized on the server for a specific signed-in user and surface.
 */
final class AuthorizedSearchIndexService
{
    private const SESSION_KEY = 'authorized_search_indexes_v1';
    private const INDEX_TTL_SECONDS = 7200;
    private const MAX_INDEXES_PER_USER = 6;
    private const MAX_CANDIDATES = 500;
    private const MAX_RESULTS = 14;

    /**
     * @param array<string,mixed> $user
     * @param array<int,array<string,mixed>> $candidates
     * @param array{path_prefix?:string} $constraints
     */
    public static function publish(array $user, string $surface, array $candidates, array $constraints = []): string
    {
        $userId = (int)($user['id'] ?? 0);
        $surface = self::normalizeIdentifier($surface);
        if ($userId <= 0 || $surface === '' || session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }

        $pathPrefix = self::normalizePath((string)($constraints['path_prefix'] ?? ''));
        $normalized = [];
        $seen = [];
        foreach (array_slice($candidates, 0, self::MAX_CANDIDATES) as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }
            $item = self::normalizeCandidate($candidate, $pathPrefix);
            if ($item === null) {
                continue;
            }
            $dedupeKey = strtolower($item['type'] . '|' . $item['url'] . '|' . $item['id']);
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;
            $normalized[] = $item;
        }

        self::pruneIndexes();
        $scope = bin2hex(random_bytes(18));
        $_SESSION[self::SESSION_KEY][$scope] = [
            'user_id' => $userId,
            'surface' => $surface,
            'path_prefix' => $pathPrefix,
            'created_at' => time(),
            'expires_at' => time() + self::INDEX_TTL_SECONDS,
            'candidates' => $normalized,
        ];
        self::trimUserIndexes($userId);

        return $scope;
    }

    /**
     * @param array<string,mixed> $user
     * @param callable(string):bool $pathAllowed
     * @return array{success:bool,query:string,groups:array<int,array{type:string,label:string,items:array<int,array<string,mixed>>}>}
     */
    public static function search(string $query, array $user, string $scope, callable $pathAllowed): array
    {
        $query = self::normalizeText($query);
        if (mb_strlen($query, 'UTF-8') < 2) {
            return self::response($query, []);
        }

        $index = self::resolveIndex($scope, (int)($user['id'] ?? 0));
        if ($index === null) {
            return self::response($query, []);
        }

        $matches = [];
        foreach ((array)($index['candidates'] ?? []) as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }
            $path = self::normalizePath((string)parse_url((string)($candidate['url'] ?? ''), PHP_URL_PATH));
            if ($path === '' || !self::pathWithinPrefix($path, (string)($index['path_prefix'] ?? ''))) {
                continue;
            }
            if (!$pathAllowed($path)) {
                continue;
            }
            $score = self::score((string)($candidate['search_text'] ?? ''), $query);
            if ($score < 20) {
                continue;
            }
            $candidate['score'] = $score;
            $matches[] = $candidate;
        }

        usort($matches, static function (array $left, array $right): int {
            $scoreOrder = ((int)($right['score'] ?? 0)) <=> ((int)($left['score'] ?? 0));
            if ($scoreOrder !== 0) {
                return $scoreOrder;
            }
            return strcasecmp((string)($left['label'] ?? ''), (string)($right['label'] ?? ''));
        });

        $groups = [];
        foreach (array_slice($matches, 0, self::MAX_RESULTS) as $candidate) {
            $type = self::normalizeIdentifier((string)($candidate['type'] ?? 'result')) ?: 'result';
            unset($candidate['search_text'], $candidate['score']);
            $groups[$type][] = $candidate;
        }

        $responseGroups = [];
        foreach ($groups as $type => $items) {
            $responseGroups[] = [
                'type' => $type,
                'label' => ucwords(str_replace('_', ' ', $type)),
                'items' => $items,
            ];
        }

        return self::response($query, $responseGroups);
    }

    /** @return array<string,mixed>|null */
    private static function resolveIndex(string $scope, int $userId): ?array
    {
        if ($userId <= 0 || preg_match('/^[a-f0-9]{36}$/', $scope) !== 1 || session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }
        self::pruneIndexes();
        $index = $_SESSION[self::SESSION_KEY][$scope] ?? null;
        if (!is_array($index) || (int)($index['user_id'] ?? 0) !== $userId) {
            return null;
        }
        return $index;
    }

    /** @param array<string,mixed> $candidate @return array<string,mixed>|null */
    private static function normalizeCandidate(array $candidate, string $pathPrefix): ?array
    {
        $url = trim((string)($candidate['url'] ?? $candidate['route'] ?? ''));
        $label = trim((string)($candidate['label'] ?? ''));
        $path = self::normalizePath((string)parse_url($url, PHP_URL_PATH));
        if (
            $url === '' ||
            !str_starts_with($url, '/') ||
            str_starts_with($url, '//') ||
            $label === '' ||
            $path === '' ||
            !self::pathWithinPrefix($path, $pathPrefix)
        ) {
            return null;
        }

        $type = self::normalizeIdentifier((string)($candidate['type'] ?? $candidate['kind'] ?? 'result')) ?: 'result';
        $id = trim((string)($candidate['id'] ?? $candidate['entity_id'] ?? ''));
        if ($id === '') {
            $id = hash('sha256', $type . '|' . $url . '|' . $label);
        }
        $meta = trim((string)($candidate['meta'] ?? $url));
        $searchText = trim(implode(' ', array_filter([
            $label,
            (string)($candidate['text'] ?? ''),
            $meta,
            $url,
            (string)($candidate['entity_name'] ?? ''),
            (string)($candidate['entity_number'] ?? ''),
            (string)($candidate['entity_id'] ?? ''),
        ], static fn(string $value): bool => trim($value) !== '')));

        return [
            'id' => $id,
            'type' => $type,
            'kind' => $type,
            'label' => $label,
            'status' => trim((string)($candidate['status'] ?? 'available')),
            'url' => $url,
            'route' => $url,
            'meta' => $meta,
            'entity_type' => trim((string)($candidate['entity_type'] ?? '')),
            'entity_id' => trim((string)($candidate['entity_id'] ?? '')),
            'search_text' => self::normalizeText($searchText),
        ];
    }

    private static function score(string $target, string $query): int
    {
        $target = self::normalizeText($target);
        if ($target === '' || $query === '') {
            return -1;
        }
        if ($target === $query) {
            return 1400;
        }
        $offset = mb_strpos($target, $query, 0, 'UTF-8');
        if ($offset !== false) {
            return 1200 - min((int)$offset, 400);
        }

        $score = 0;
        $tokens = array_values(array_unique(preg_split('/\s+/u', $query) ?: []));
        foreach ($tokens as $token) {
            if ($token !== '' && mb_strpos($target, $token, 0, 'UTF-8') !== false) {
                $score += max(60, 160 - mb_strlen($token, 'UTF-8') * 4);
            }
        }
        return $score > 0 ? $score : -1;
    }

    private static function normalizeText(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
        return trim((string)(preg_replace('/\s+/u', ' ', $value) ?? $value));
    }

    private static function normalizeIdentifier(string $value): string
    {
        return trim((string)(preg_replace('/[^a-z0-9_]+/', '_', strtolower(trim($value))) ?? ''), '_');
    }

    private static function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '' || $path[0] !== '/') {
            return '';
        }
        return rtrim((string)(preg_replace('#/+#', '/', $path) ?? $path), '/') ?: '/';
    }

    private static function pathWithinPrefix(string $path, string $prefix): bool
    {
        if ($prefix === '') {
            return true;
        }
        return $path === $prefix || str_starts_with($path, $prefix . '/');
    }

    private static function pruneIndexes(): void
    {
        $indexes = $_SESSION[self::SESSION_KEY] ?? [];
        if (!is_array($indexes)) {
            $indexes = [];
        }
        $now = time();
        foreach ($indexes as $scope => $index) {
            if (!is_array($index) || (int)($index['expires_at'] ?? 0) <= $now) {
                unset($indexes[$scope]);
            }
        }
        $_SESSION[self::SESSION_KEY] = $indexes;
    }

    private static function trimUserIndexes(int $userId): void
    {
        $indexes = (array)($_SESSION[self::SESSION_KEY] ?? []);
        $owned = [];
        foreach ($indexes as $scope => $index) {
            if (is_array($index) && (int)($index['user_id'] ?? 0) === $userId) {
                $owned[$scope] = (int)($index['created_at'] ?? 0);
            }
        }
        arsort($owned, SORT_NUMERIC);
        foreach (array_slice(array_keys($owned), self::MAX_INDEXES_PER_USER) as $scope) {
            unset($indexes[$scope]);
        }
        $_SESSION[self::SESSION_KEY] = $indexes;
    }

    /**
     * @param array<int,array{type:string,label:string,items:array<int,array<string,mixed>>}> $groups
     * @return array{success:bool,query:string,groups:array<int,array{type:string,label:string,items:array<int,array<string,mixed>>}>}
     */
    private static function response(string $query, array $groups): array
    {
        return ['success' => true, 'query' => $query, 'groups' => $groups];
    }
}
