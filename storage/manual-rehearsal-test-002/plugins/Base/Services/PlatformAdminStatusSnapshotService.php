<?php
declare(strict_types=1);

namespace Plugins\Base\Services;

use Plugins\Base\Controllers\RoleDashboardsController;

/**
 * Owner-side read-only adapter for Platform Admin status presentation.
 * Mutation metadata from the legacy dashboard is deliberately not exported.
 */
final class PlatformAdminStatusSnapshotService
{
    /** @param array<string,mixed> $context @return array{state:string,cards:array<int,array<string,mixed>>,environment:array<int,array<string,mixed>>} */
    public static function snapshot(array $context): array
    {
        if (strtolower(trim((string)($context['authority_role'] ?? ''))) !== 'platform_admin') {
            return ['state' => 'not_authorized', 'cards' => [], 'environment' => []];
        }

        try {
            $snapshotAppRoot = defined('APP_ROOT') ? (string)\APP_ROOT : dirname(__DIR__, 3);
            $roleDashboardControllerPath = $snapshotAppRoot . '/plugins/Base/Controllers/RoleDashboardsController.php';
            if (!class_exists(RoleDashboardsController::class) && is_file($roleDashboardControllerPath)) {
                require_once $roleDashboardControllerPath;
            }
            $payload = RoleDashboardsController::dashboardPayloadForContext($context, 'platform_admin');
        } catch (\Throwable $e) {
            error_log('Platform admin snapshot provider failed: ' . get_class($e) . ': ' . $e->getMessage());
            return ['state' => 'degraded', 'cards' => [], 'environment' => []];
        }

        return self::composePayload($payload, !empty($context['developer_tools_visible']));
    }

    /** @param array<string,mixed> $payload @return array{state:string,cards:array<int,array<string,mixed>>,environment:array<int,array<string,mixed>>} */
    public static function composePayload(array $payload, bool $developerToolsVisible): array
    {

        $cardContract = [
            'active_apps' => ['admin.upgrade_workbench.metric.active_apps', '/admin/apps'],
            'installed_apps' => ['admin.upgrade_workbench.metric.installed_apps', '/admin/app-manager'],
            'failed_migrations' => ['admin.upgrade_workbench.metric.failed_migrations', '/admin/apps'],
            'schema_sync_gaps' => ['admin.upgrade_workbench.metric.schema_gaps', '/admin/base'],
            'log_files' => ['admin.upgrade_workbench.metric.log_files', '/admin/system-tools'],
            'log_size_mb' => ['admin.upgrade_workbench.metric.log_size', '/admin/system-tools'],
            'access_control_assignments' => ['admin.upgrade_workbench.metric.access_assignments', '/ops/access-control'],
            'scope_assignment_rows' => ['admin.upgrade_workbench.metric.scope_rows', '/ops/access-control'],
            'module_visibility_rules' => ['admin.upgrade_workbench.metric.visibility_rules', '/ops/navigation-tree'],
        ];
        $cards = [];
        foreach ((array)($payload['cards'] ?? []) as $card) {
            $key = (string)($card['key'] ?? '');
            if (!isset($cardContract[$key])) { continue; }
            [$labelKey, $target] = $cardContract[$key];
            $cards[] = ['key' => $key, 'label_key' => $labelKey, 'value' => (string)($card['value'] ?? '0'), 'tone' => (string)($card['tone'] ?? 'info'), 'target' => $target];
        }

        $environmentContract = [
            'php_version' => ['admin.upgrade_workbench.environment.php', '/admin/setup/environment'],
            'runtime_environment' => ['admin.upgrade_workbench.environment.runtime', '/admin/setup/environment'],
            'route_integrity' => ['admin.upgrade_workbench.environment.routes', '/admin/architecture-health'],
        ];
        $environment = [];
        foreach ((array)($payload['sections'] ?? []) as $section) {
            if ((string)($section['key'] ?? '') !== 'environment') { continue; }
            foreach ((array)($section['items'] ?? []) as $item) {
                $key = (string)($item['key'] ?? '');
                if (!isset($environmentContract[$key])) { continue; }
                if ($key === 'route_integrity' && !$developerToolsVisible) { continue; }
                [$labelKey, $target] = $environmentContract[$key];
                $environment[] = ['key' => $key, 'label_key' => $labelKey, 'value' => (string)($item['value'] ?? ''), 'target' => $target];
            }
        }

        return ['state' => ($cards !== [] || $environment !== []) ? 'ready' : 'degraded', 'cards' => $cards, 'environment' => $environment];
    }
}
