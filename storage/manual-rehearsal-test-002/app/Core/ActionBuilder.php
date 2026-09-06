<?php
declare(strict_types=1);

namespace App\Core;

final class ActionBuilder
{
    /**
     * @param array{
     *   role?:string,
     *   loggedIn?:bool,
     *   isAdmin?:bool,
     *   hasAdminToolsAccess?:bool,
     *   canAccessBase?:bool,
     *   availableGetRoutes?:array<int,string>,
    *   availablePostRoutes?:array<int,string>,
     *   coverageAvailable?:bool
     * } $context
     * @return array<string,mixed>
     */
    public static function buildHome(array $context): array
    {
        $visibility = [
            'role' => strtolower(trim((string)($context['role'] ?? ''))),
            'logged_in' => (bool)($context['loggedIn'] ?? false),
            'is_admin' => (bool)($context['isAdmin'] ?? false),
            'has_admin_tools_access' => (bool)($context['hasAdminToolsAccess'] ?? false),
            'can_access_base' => (bool)($context['canAccessBase'] ?? false),
        ];

        $coverageAvailable = (bool)($context['coverageAvailable'] ?? false);
        $getRouteMap = self::buildRouteMap((array)($context['availableGetRoutes'] ?? []));
        $postRouteMap = self::buildRouteMap((array)($context['availablePostRoutes'] ?? []));

        $widgetActions = [];
        $coverageGlobal = [];
        $coverageStates = [
            'low' => [],
            'partial' => [],
            'full' => [],
        ];

        foreach (self::loadActions() as $def) {
            if (!is_array($def) || empty($def['key'])) {
                continue;
            }

            if (!self::isVisible((string)($def['visible_if'] ?? 'always'), $visibility)) {
                continue;
            }

            $action = self::normalizeAction($def, $getRouteMap, $postRouteMap, $coverageAvailable, $visibility);

            $attachTo = (string)($def['attach_to'] ?? '');
            $target = (string)($def['target'] ?? '');

            if ($attachTo === 'widget' && $target !== '') {
                $widgetActions[$target][] = $action;
                continue;
            }

            if ($attachTo === 'coverage_zone' && $target === 'global') {
                $coverageGlobal[] = $action;
                continue;
            }

            if ($attachTo === 'coverage_state' && isset($coverageStates[$target])) {
                $coverageStates[$target][] = $action;
            }
        }

        foreach ($widgetActions as &$list) {
            self::sortByPriority($list);
        }
        unset($list);
        self::sortByPriority($coverageGlobal);
        foreach ($coverageStates as &$list) {
            self::sortByPriority($list);
        }
        unset($list);

        return [
            'widget_actions' => $widgetActions,
            'coverage' => [
                'global' => $coverageGlobal,
                'states' => $coverageStates,
            ],
        ];
    }

    /**
     * @param array<int,string> $routes
     * @return array<string,bool>
     */
    private static function buildRouteMap(array $routes): array
    {
        $map = [];
        foreach ($routes as $route) {
            $path = parse_url((string)$route, PHP_URL_PATH);
            if (!is_string($path) || $path === '') {
                continue;
            }
            $map[rtrim($path, '/') ?: '/'] = true;
        }
        return $map;
    }

    /**
     * @param array<string,mixed> $def
     * @param array<string,bool> $getRouteMap
     * @param array<string,bool> $postRouteMap
     * @param array<string,mixed> $visibility
     * @return array<string,mixed>
     */
    private static function normalizeAction(array $def, array $getRouteMap, array $postRouteMap, bool $coverageAvailable, array $visibility): array
    {
        $targetUrl = (string)($def['target_url'] ?? '');
        $method = (string)($def['method'] ?? 'link');
        $enabled = self::isEnabled((string)($def['enabled_if'] ?? 'always'), $targetUrl, $method, $getRouteMap, $postRouteMap, $coverageAvailable, $visibility);
        $contextParams = is_array($def['context_params'] ?? null) ? (array)$def['context_params'] : [];
        $prefillParams = is_array($def['prefill_params'] ?? null) ? (array)$def['prefill_params'] : [];
        $transactionPayload = is_array($def['transaction_payload'] ?? null) ? (array)$def['transaction_payload'] : [];
        $requiresRecordContext = (bool)($def['requires_record_context'] ?? false);
        if (!$requiresRecordContext && self::containsTemplateToken($targetUrl)) {
            $requiresRecordContext = true;
        }

        return [
            'key' => (string)$def['key'],
            'label_key' => (string)($def['label_key'] ?? ''),
            'label' => t((string)($def['label_key'] ?? '')),
            'module_key' => (string)($def['module_key'] ?? ''),
            'domain' => (string)($def['domain'] ?? ''),
            'owner_plugin' => (string)($def['owner_plugin'] ?? ''),
            'source_signal' => (string)($def['source_signal'] ?? ''),
            'action_type' => (string)($def['action_type'] ?? 'navigate'),
            'target_url' => $targetUrl,
            'method' => $method,
            'priority' => (string)($def['priority'] ?? 'normal'),
            'visible_if' => (string)($def['visible_if'] ?? 'always'),
            'enabled_if' => (string)($def['enabled_if'] ?? 'always'),
            'enabled' => $enabled,
            'status' => (string)($def['status'] ?? 'active'),
            'requires_confirmation' => (bool)($def['requires_confirmation'] ?? false),
            'confirmation_message' => (string)($def['confirmation_message'] ?? ''),
            'is_placeholder' => (bool)($def['is_placeholder'] ?? false),
            'workflow_key' => (string)($def['workflow_key'] ?? (string)$def['key']),
            'workflow_type' => (string)($def['workflow_type'] ?? (string)($def['action_type'] ?? 'navigate')),
            'entry_intent' => (string)($def['entry_intent'] ?? ''),
            'target_mode' => (string)($def['target_mode'] ?? 'list'),
            'context_params' => $contextParams,
            'prefill_params' => $prefillParams,
            'transaction_type' => (string)($def['transaction_type'] ?? ''),
            'transaction_target' => (string)($def['transaction_target'] ?? ''),
            'create_mode' => (string)($def['create_mode'] ?? ''),
            'draft_mode' => (string)($def['draft_mode'] ?? ''),
            'transaction_payload' => $transactionPayload,
            'audit_action' => (string)($def['audit_action'] ?? ''),
            'success_redirect' => (string)($def['success_redirect'] ?? ''),
            'failure_fallback' => (string)($def['failure_fallback'] ?? ''),
            'transaction_safe_mode' => (bool)($def['transaction_safe_mode'] ?? true),
            'source_state' => (string)($def['source_state'] ?? ''),
            'requires_role' => (string)($def['requires_role'] ?? (string)($def['visible_if'] ?? 'always')),
            'requires_record_context' => $requiresRecordContext,
            'empty_state_fallback' => (string)($def['empty_state_fallback'] ?? ''),
            'safe_mode' => (bool)($def['safe_mode'] ?? true),
        ];
    }

    private static function containsTemplateToken(string $value): bool
    {
        return $value !== '' && preg_match('/\{[a-zA-Z0-9_]+\}/', $value) === 1;
    }

    /**
     * @param array<string,bool> $getRouteMap
     * @param array<string,bool> $postRouteMap
     * @param array<string,mixed> $visibility
     */
    private static function isEnabled(string $rule, string $targetUrl, string $method, array $getRouteMap, array $postRouteMap, bool $coverageAvailable, array $visibility): bool
    {
        $path = parse_url($targetUrl, PHP_URL_PATH);
        $normalizedPath = is_string($path) ? (rtrim($path, '/') ?: '/') : '';
        $routeMap = strtolower($method) === 'post' ? $postRouteMap : $getRouteMap;

        return match ($rule) {
            'always', '' => true,
            'never' => false,
            'coverage_available' => $coverageAvailable,
            'route_exists' => $normalizedPath !== '' && isset($routeMap[$normalizedPath]),
            'admin_tools' => (bool)($visibility['has_admin_tools_access'] ?? false),
            default => true,
        };
    }

    /**
     * @param array<string,mixed> $visibility
     */
    private static function isVisible(string $rule, array $visibility): bool
    {
        return AclPolicy::allowsVisibilityRule($rule, $visibility);
    }

    /**
     * @param array<int,array<string,mixed>> $actions
     */
    private static function sortByPriority(array &$actions): void
    {
        $priorityWeight = [
            'critical' => 1,
            'high' => 2,
            'normal' => 3,
            'secondary' => 4,
        ];

        usort($actions, static function (array $a, array $b) use ($priorityWeight): int {
            $aw = $priorityWeight[(string)($a['priority'] ?? 'normal')] ?? 5;
            $bw = $priorityWeight[(string)($b['priority'] ?? 'normal')] ?? 5;
            if ($aw !== $bw) {
                return $aw <=> $bw;
            }
            return strcmp((string)($a['key'] ?? ''), (string)($b['key'] ?? ''));
        });
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function loadActions(): array
    {
        $path = APP_ROOT . '/app/Actions/actions.php';
        if (!is_file($path)) {
            return [];
        }

        $actions = require $path;
        return is_array($actions) ? array_values($actions) : [];
    }
}
