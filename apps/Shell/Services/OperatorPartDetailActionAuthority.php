<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

require_once __DIR__ . '/OperatorLayerWidgetService.php';

/**
 * Shell-owned safety filter for Manufacturing-provided Part detail actions.
 * Manufacturing declares business actions; Shell validates operator address,
 * route authority, and interaction-profile constraints before rendering them.
 */
final class OperatorPartDetailActionAuthority
{
    /**
     * @param array<int,mixed> $actions
     * @return array<int,array<string,mixed>>
     */
    public static function filter(
        array $actions,
        string $username,
        callable $routeAccessResolver,
        bool $readOnly = false
    ): array {
        if ($readOnly || trim($username) === '') {
            return [];
        }

        $filtered = [];
        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }
            $url = trim((string)($action['url'] ?? ''));
            $normalized = OperatorLayerWidgetService::normalizeOperatorWidgetUrl($url, [
                'operator_username' => $username,
            ]);
            if ($normalized === '') {
                continue;
            }
            try {
                if (!$routeAccessResolver($normalized)) {
                    continue;
                }
            } catch (\Throwable) {
                continue;
            }
            $action['url'] = $normalized;
            $filtered[] = $action;
        }

        return $filtered;
    }
}
