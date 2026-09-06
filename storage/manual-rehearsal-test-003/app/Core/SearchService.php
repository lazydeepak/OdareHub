<?php
declare(strict_types=1);

namespace App\Core;

use Platform\Search\AuthorizedSearchIndexService;
use Platform\Search\SearchProviderContext;
use Platform\Search\SearchProviderRegistry;

/**
 * Canonical authenticated search orchestrator.
 * Owner providers discover candidates; Core normalizes, authorizes, deduplicates,
 * groups, and delivers the final result contract.
 */
final class SearchService
{
    private const MAX_RESULTS_PER_TYPE = 8;
    /** @var array<string, array<int,string>> */
    private static array $tableColumnsCache = [];
    private const SCOPE_SERVICE_CLASS = '\\Plugins\\Base\\Services\\UserDashboardAssignmentService';

    /**
     * Search across data entities for the active user.
     *
     * @param string $query normalized search text
     * @param ?array<string,mixed> $user logged-in user context
     * @param array{candidate_scope?:string} $context optional authorized candidate scope
     * @return array{
     *   success: bool,
     *   query: string,
     *   groups: array<int, array{
     *     type: string,
     *     label: string,
     *     items: array<int, array{
     *       id: string,
     *       label: string,
     *       status?: string,
     *       url: string,
     *       meta?: string
     *     }>
     *   }>
     * }
     */
    public static function search(string $query, ?array $user = null, array $context = []): array
    {
        $query = self::normalizeQuery($query);
        if (strlen($query) < 2) {
            return [
                'success' => true,
                'query' => $query,
                'groups' => [],
            ];
        }

        $loggedIn = $user !== null && is_array($user);
        $results = [];
        $providerGroupLabels = [];
        $providerGroupPriorities = [];
        $scopeContext = $loggedIn ? self::buildScopeContext($user) : [];

        $candidateScope = trim((string)($context['candidate_scope'] ?? ''));
        if ($loggedIn && $candidateScope !== '') {
            return AuthorizedSearchIndexService::search(
                $query,
                $user,
                $candidateScope,
                static fn(string $path): bool => self::isPathAllowedForUser($user, $scopeContext, $path)
            );
        }

        if ($loggedIn) {
            $providerContext = self::providerContext($user, $scopeContext);
            foreach (SearchProviderRegistry::providers() as $provider) {
                $providerGroupLabels = array_merge($providerGroupLabels, $provider->groupLabels());
                foreach ($provider->groupPriorities() as $intent => $priorities) {
                    foreach ($priorities as $type => $priority) {
                        $current = $providerGroupPriorities[$intent][$type] ?? null;
                        $providerGroupPriorities[$intent][$type] = $current === null
                            ? (int)$priority
                            : min((int)$current, (int)$priority);
                    }
                }
                foreach ($provider->search($query, $providerContext) as $type => $items) {
                    if ($items !== []) {
                        $results[$type] = array_merge((array)($results[$type] ?? []), $items);
                    }
                }
            }
        }

        $groups = [];
        $pathAuthorizationCache = [];
        foreach ($results as $type => $items) {
            if (empty($items)) {
                continue;
            }

            $authorizedByIdentity = [];
            $identityOrder = [];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $path = self::pathFromUrl((string)($item['url'] ?? ''));
                if ($path === '' || !$loggedIn) {
                    continue;
                }
                if (!array_key_exists($path, $pathAuthorizationCache)) {
                    $pathAuthorizationCache[$path] = self::isPathAllowedForUser($user, $scopeContext, $path);
                }
                if (!$pathAuthorizationCache[$path]) {
                    continue;
                }
                $id = trim((string)($item['id'] ?? ''));
                $dedupeKey = strtolower((string)$type . '|' . ($id !== ''
                    ? 'id:' . $id
                    : 'path:' . $path . '|label:' . (string)($item['label'] ?? '')));
                $precedence = (int)($item['_search_precedence'] ?? 0);
                unset($item['_search_precedence']);
                $fingerprint = (string)json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                if (!isset($authorizedByIdentity[$dedupeKey])) {
                    $identityOrder[] = $dedupeKey;
                    $authorizedByIdentity[$dedupeKey] = [
                        'item' => $item,
                        'precedence' => $precedence,
                        'fingerprint' => $fingerprint,
                    ];
                    continue;
                }

                $existing = $authorizedByIdentity[$dedupeKey];
                if (self::shouldReplaceResultCandidate($precedence, $fingerprint, $existing)) {
                    $authorizedByIdentity[$dedupeKey] = [
                        'item' => $item,
                        'precedence' => $precedence,
                        'fingerprint' => $fingerprint,
                    ];
                }
            }
            $authorizedItems = array_map(
                static fn(string $identity): array => (array)$authorizedByIdentity[$identity]['item'],
                $identityOrder
            );
            if ($authorizedItems === []) {
                continue;
            }

            $groups[] = [
                'type' => $type,
                'label' => $providerGroupLabels[$type] ?? ucwords(str_replace('_', ' ', (string)$type)),
                'items' => array_slice($authorizedItems, 0, self::MAX_RESULTS_PER_TYPE),
            ];
        }

        $groups = self::sortGroupsByPriority($groups, (array)($providerGroupPriorities['default'] ?? []));
        $groups = self::refineGroupsForIntent($groups, $query, $providerGroupPriorities);

        return [
            'success' => true,
            'query' => $query,
            'groups' => $groups,
        ];
    }

    /** @param array{precedence:int,fingerprint:string} $existing */
    private static function shouldReplaceResultCandidate(int $precedence, string $fingerprint, array $existing): bool
    {
        $existingPrecedence = (int)($existing['precedence'] ?? 0);
        if ($precedence !== $existingPrecedence) {
            return $precedence > $existingPrecedence;
        }

        return $fingerprint < (string)($existing['fingerprint'] ?? '');
    }

    /** @param array<string,mixed> $user @param array<string,mixed> $scopeContext */
    private static function providerContext(array $user, array $scopeContext): SearchProviderContext
    {
        return new SearchProviderContext(
            $user,
            $scopeContext,
            static fn(string $path): bool => self::isPathAllowedForUser($user, $scopeContext, $path),
            static fn(string $table, array $scopeMap, string $alias = ''): array => self::buildScopeClause($table, $scopeMap, $scopeContext, $alias),
            static fn(string $table, array $columns): array => self::buildTableExpressions($table, $columns),
            static fn(array $expressions, string $query): array => self::buildPhraseWhere($expressions, $query),
            static fn(array $expressions, array $tokens, bool $requireAllTokens = true): array => self::buildTokenWhere($expressions, $tokens, $requireAllTokens),
            static fn(string $query): array => self::queryTokens($query),
            static fn(string $query, array $fragments): bool => self::matchesQuery($query, $fragments)
        );
    }

    /**
     * @param array<int,string> $fragments
     */
    private static function matchesQuery(string $query, array $fragments): bool
    {
        $query = self::normalizeQuery($query);
        if ($query === '') {
            return false;
        }

        $haystack = self::normalizeQuery(implode(' ', array_values(array_filter($fragments, static fn(string $v): bool => trim($v) !== ''))));
        if ($haystack === '') {
            return false;
        }

        if (str_contains($haystack, $query)) {
            return true;
        }

        foreach (self::queryTokens($query) as $token) {
            $matched = false;
            foreach (self::tokenVariants($token) as $variant) {
                if ($variant !== '' && str_contains($haystack, $variant)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return false;
            }
        }

        return true;
    }

    private static function pathFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || trim($path) === '') {
            return '';
        }

        $normalized = '/' . ltrim($path, '/');
        return rtrim($normalized, '/') ?: '/';
    }

    /**
     * @param array<int, array{type:string,label:string,items:array<int,array{id:string,label:string,status?:string,url:string,meta?:string}>}> $groups
     * @return array<int, array{type:string,label:string,items:array<int,array{id:string,label:string,status?:string,url:string,meta?:string}>}>
     */
    private static function refineGroupsForIntent(array $groups, string $query, array $providerGroupPriorities): array
    {
        if (!self::isLogIntentQuery($query)) {
            return $groups;
        }

        $filtered = [];
        foreach ($groups as $group) {
            $items = [];
            foreach ((array)($group['items'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                if (self::isLogIntentMatch($item)) {
                    $items[] = $item;
                }
            }

            if ($items !== []) {
                $group['items'] = array_slice($items, 0, self::MAX_RESULTS_PER_TYPE);
                $filtered[] = $group;
            }
        }

        if ($filtered === []) {
            return $groups;
        }

        $priority = (array)($providerGroupPriorities['log'] ?? []);

        return self::sortGroupsByPriority($filtered, $priority);
    }

    /**
     * @param array<int,array<string,mixed>> $groups
     * @param array<string,int> $priority
     * @return array<int,array<string,mixed>>
     */
    private static function sortGroupsByPriority(array $groups, array $priority): array
    {
        foreach ($groups as $index => &$group) {
            $group['_search_order'] = $index;
        }
        unset($group);

        usort($groups, static function (array $left, array $right) use ($priority): int {
            $leftPriority = $priority[(string)($left['type'] ?? '')] ?? PHP_INT_MAX;
            $rightPriority = $priority[(string)($right['type'] ?? '')] ?? PHP_INT_MAX;
            if ($leftPriority !== $rightPriority) {
                return $leftPriority <=> $rightPriority;
            }
            return ((int)($left['_search_order'] ?? 0)) <=> ((int)($right['_search_order'] ?? 0));
        });

        foreach ($groups as &$group) {
            unset($group['_search_order']);
        }
        unset($group);

        return $groups;
    }

    private static function isLogIntentQuery(string $query): bool
    {
        $q = ' ' . self::normalizeQuery($query) . ' ';
        return preg_match('/\b(log|logs|audit|diagnostic|diagnostics|trace|traces)\b/', $q) === 1;
    }

    /**
     * @param array{id?:string,label?:string,status?:string,url?:string,meta?:string} $item
     */
    private static function isLogIntentMatch(array $item): bool
    {
        $label = strtolower(trim((string)($item['label'] ?? '')));
        $meta = strtolower(trim((string)($item['meta'] ?? '')));
        $url = strtolower(trim((string)($item['url'] ?? '')));
        $hay = trim($label . ' ' . $meta . ' ' . $url);
        if ($hay === '') {
            return false;
        }

        return preg_match('/\b(log|logs|audit|diagnostic|diagnostics|trace|traces)\b/', ' ' . $hay . ' ') === 1;
    }

    /**
     * @return array<int,string>
     */
    private static function queryTokens(string $query): array
    {
        $parts = preg_split('/\s+/', self::normalizeQuery($query)) ?: [];
        $tokens = [];
        foreach ($parts as $part) {
            $token = trim((string)$part);
            if ($token === '') {
                continue;
            }
            $tokens[] = $token;
        }

        return array_values(array_unique($tokens));
    }

    /**
     * Build a case-insensitive partial-match WHERE clause where each token must match at least one expression.
     *
     * @param array<int,string> $expressions
     * @param array<int,string> $tokens
     * @return array{0:string,1:array<int,string>}
     */
    private static function buildTokenWhere(array $expressions, array $tokens, bool $requireAllTokens = true): array
    {
        if ($tokens === [] || $expressions === []) {
            return ['1=1', []];
        }

        $clauses = [];
        $params = [];
        foreach ($tokens as $token) {
            $variants = self::tokenVariants($token);
            $subClauses = [];
            foreach ($expressions as $expr) {
                foreach ($variants as $variant) {
                    $subClauses[] = 'LOWER(' . $expr . ') LIKE ?';
                    $params[] = '%' . $variant . '%';
                }
            }
            $clauses[] = '(' . implode(' OR ', $subClauses) . ')';
        }

        return [implode($requireAllTokens ? ' AND ' : ' OR ', $clauses), $params];
    }

    /**
     * Return normalized token variants so common noun inflections can match each other.
     *
     * @return array<int,string>
     */
    private static function tokenVariants(string $token): array
    {
        $token = strtolower(trim($token));
        if ($token === '') {
            return [];
        }

        $variants = [$token => true];
        $generated = self::inflectionRoots($token);
        foreach ($generated as $root) {
            $variants[$root] = true;
        }

        return array_values(array_keys($variants));
    }

    /**
     * Build likely root forms using generic noun-inflection rules.
     *
     * @return array<int,string>
     */
    private static function inflectionRoots(string $token): array
    {
        $roots = [];
        $len = strlen($token);

        if ($len > 4 && preg_match('/ies$/', $token) === 1) {
            $roots[] = substr($token, 0, -3) . 'y';
        }

        if ($len > 4 && preg_match('/ves$/', $token) === 1) {
            $roots[] = substr($token, 0, -3) . 'f';
            $roots[] = substr($token, 0, -3) . 'fe';
        }

        if ($len > 4 && preg_match('/(ches|shes|xes|zes|sses)$/', $token) === 1) {
            $roots[] = substr($token, 0, -2);
        }

        if ($len > 3 && preg_match('/men$/', $token) === 1) {
            $roots[] = substr($token, 0, -3) . 'man';
        }

        if (
            $len > 3 &&
            preg_match('/s$/', $token) === 1 &&
            preg_match('/(ss|us|is)$/', $token) !== 1
        ) {
            $roots[] = substr($token, 0, -1);
        }

        $clean = [];
        foreach ($roots as $root) {
            $value = trim($root);
            if ($value === '' || strlen($value) < 2) {
                continue;
            }
            $clean[$value] = true;
        }

        return array_values(array_keys($clean));
    }

    /**
     * @param array<int,string> $expressions
     * @return array{0:string,1:array<int,string>}
     */
    private static function buildPhraseWhere(array $expressions, string $query): array
    {
        $phrase = strtolower(trim($query));
        if ($phrase === '' || $expressions === []) {
            return ['1=1', []];
        }

        $clauses = [];
        $params = [];
        foreach ($expressions as $expr) {
            $clauses[] = 'LOWER(' . $expr . ') LIKE ?';
            $params[] = '%' . $phrase . '%';
        }

        return ['(' . implode(' OR ', $clauses) . ')', $params];
    }

    /**
     * Build searchable SQL expressions for a table using base columns plus dynamic "title/header/tile/table/data" columns.
     *
     * @param array<int,string> $baseColumns
     * @return array<int,string>
     */
    private static function buildTableExpressions(string $table, array $baseColumns): array
    {
        $columns = self::getTableColumns($table);
        $expressionSet = [];

        $addExpression = static function (string $column) use (&$expressionSet): void {
            $key = strtolower($column);
            if ($key === 'id') {
                $expressionSet['cast_id'] = 'CAST(id AS CHAR)';
                return;
            }
            $escaped = str_replace('`', '``', $column);
            $expressionSet[$key] = '`' . $escaped . '`';
        };

        foreach ($baseColumns as $column) {
            if (in_array($column, $columns, true) || $column === 'id') {
                $addExpression($column);
            }
        }

        foreach ($columns as $column) {
            $col = strtolower($column);
            if (
                str_contains($col, 'title') ||
                str_contains($col, 'header') ||
                str_contains($col, 'tile') ||
                str_contains($col, 'table') ||
                str_contains($col, 'data') ||
                str_contains($col, 'label')
            ) {
                $addExpression($column);
            }
        }

        return array_values($expressionSet);
    }

    /**
     * @return array<int,string>
     */
    private static function getTableColumns(string $table): array
    {
        if (isset(self::$tableColumnsCache[$table])) {
            return self::$tableColumnsCache[$table];
        }

        try {
            $rows = DB::fetchAll('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
            $columns = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $name = trim((string)($row['Field'] ?? ''));
                if ($name !== '') {
                    $columns[] = $name;
                }
            }
            self::$tableColumnsCache[$table] = $columns;
            return $columns;
        } catch (\Throwable $e) {
            self::$tableColumnsCache[$table] = [];
            return [];
        }
    }

    private static function normalizeQuery(string $query): string
    {
        $query = strtolower(trim((string)$query));
        if ($query === '') {
            return '';
        }

        $normalized = preg_replace('/[^\p{L}\p{N}\-_.#\/]+/u', ' ', $query);
        if (!is_string($normalized)) {
            $normalized = $query;
        }
        $normalized = preg_replace('/\s+/', ' ', trim($normalized));
        return is_string($normalized) ? $normalized : $query;
    }

    /**
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    private static function buildScopeContext(array $user): array
    {
        $service = self::scopeServiceClass();
        if ($service === '') {
            return [
                'service' => '',
                'scope' => [],
                'user_context' => [],
                'visibility_context' => self::buildVisibilityContext($user, []),
            ];
        }

        try {
            $ctx = method_exists($service, 'resolveUserContext') ? (array)$service::resolveUserContext($user) : [];
            return [
                'service' => $service,
                'scope' => (array)($ctx['scope'] ?? []),
                'user_context' => $ctx,
                'visibility_context' => self::buildVisibilityContext($user, $ctx),
            ];
        } catch (\Throwable $e) {
            return [
                'service' => $service,
                'scope' => [],
                'user_context' => [],
                'visibility_context' => self::buildVisibilityContext($user, []),
            ];
        }
    }

    /**
     * @param array<string,mixed> $user
     * @param array<string,mixed> $ctx
     * @return array<string,mixed>
     */
    private static function buildVisibilityContext(array $user, array $ctx): array
    {
        $isAdmin = function_exists('base_is_admin_user')
            ? (bool)base_is_admin_user($user)
            : strtolower(trim((string)($user['role'] ?? ''))) === 'admin';
        $canAccessBase = function_exists('base_can_access_builder')
            ? (bool)base_can_access_builder($user)
            : $isAdmin;
        $hasAdminToolsAccess = function_exists('base_can_access_admin_tools')
            ? (bool)base_can_access_admin_tools($user)
            : false;
        $hasSupervisorAccess = function_exists('base_can_access_supervisor_cockpit')
            ? (bool)base_can_access_supervisor_cockpit($user)
            : false;
        $devToolsEnabled = function_exists('app_dev_tools_enabled')
            ? (bool)app_dev_tools_enabled()
            : false;

        return [
            'logged_in' => true,
            'is_admin' => $isAdmin,
            'can_access_base' => $canAccessBase,
            'developer_tools' => $devToolsEnabled && ($isAdmin || $hasAdminToolsAccess || $canAccessBase),
            'role' => strtolower(trim((string)($user['role'] ?? ''))),
            'authority_role' => (string)($ctx['authority_role'] ?? 'app_user'),
            'assigned_apps' => array_values((array)($ctx['assigned_apps'] ?? [])),
            'has_admin_tools_access' => $hasAdminToolsAccess,
            'has_supervisor_access' => $hasSupervisorAccess,
        ];
    }

    private static function scopeServiceClass(): string
    {
        $service = self::SCOPE_SERVICE_CLASS;
        if (class_exists($service)) {
            return $service;
        }

        $serviceFile = dirname(__DIR__, 2) . '/plugins/Base/Services/UserDashboardAssignmentService.php';
        if (is_file($serviceFile)) {
            require_once $serviceFile;
        }

        return class_exists($service) ? $service : '';
    }

    /**
     * @param array<string,mixed> $user
     * @param array<string,mixed> $scopeContext
     */
    private static function isPathAllowedForUser(array $user, array $scopeContext, string $path): bool
    {
        $service = (string)($scopeContext['service'] ?? '');
        if ($service === '' || !method_exists($service, 'routeAccessDecision')) {
            return true;
        }

        try {
            $decision = (array)$service::routeAccessDecision($user, $path, 'GET');
            return (bool)($decision['allowed'] ?? true);
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * @param array<string,string> $columnToScopeKey
     * @param array<string,mixed> $scopeContext
     * @return array{0:string,1:array<int,int|string>}
     */
    private static function buildScopeClause(string $table, array $columnToScopeKey, array $scopeContext, string $alias = ''): array
    {
        $scope = (array)($scopeContext['scope'] ?? []);
        if ($scope === []) {
            return ['', []];
        }

        $columns = self::getTableColumns($table);
        if ($columns === []) {
            return ['', []];
        }

        $sql = '';
        $params = [];
        $appliedScopeKeys = [];
        foreach ($columnToScopeKey as $column => $scopeKey) {
            if (isset($appliedScopeKeys[$scopeKey])) {
                continue;
            }
            if (!in_array($column, $columns, true)) {
                continue;
            }

            $qualified = $alias !== ''
                ? $alias . '.`' . str_replace('`', '``', $column) . '`'
                : '`' . str_replace('`', '``', $column) . '`';

            if (in_array($scopeKey, ['machine_ids', 'part_ids'], true)) {
                $values = array_values(array_filter(array_map('intval', (array)($scope[$scopeKey] ?? [])), static fn(int $v): bool => $v > 0));
                if ($values === []) {
                    continue;
                }
                $holders = implode(',', array_fill(0, count($values), '?'));
                $sql .= " AND {$qualified} IN ({$holders})";
                foreach ($values as $value) {
                    $params[] = $value;
                }
                $appliedScopeKeys[$scopeKey] = true;
                continue;
            }

            if (in_array($scopeKey, ['department_code', 'branch_code'], true)) {
                $value = trim((string)($scope[$scopeKey] ?? ''));
                if ($value === '') {
                    continue;
                }
                $sql .= " AND {$qualified} = ?";
                $params[] = $value;
                $appliedScopeKeys[$scopeKey] = true;
            }
        }

        return [$sql, $params];
    }
}
