<?php
declare(strict_types=1);

namespace Apps\Platform\Services;

/**
 * Read-only role-aware admin status payload.
 *
 * The compatibility class name is retained while the runtime surface graduates
 * from a migration card into a stable status component. Platform owns the
 * capability/route meaning; Shell only renders this payload.
 */
final class AdminUpgradeWorkbenchService
{
    /** @param array<string,mixed> $context @return array<string,mixed> */
    public static function build(array $context): array
    {
        $authorityRole = strtolower(trim((string)($context['authority_role'] ?? '')));
        if ($authorityRole === 'app_admin') {
            $assignedApps = array_values(array_filter(array_map('strval', (array)($context['active_assigned_apps'] ?? $context['assigned_apps'] ?? [])), static fn(string $app): bool => trim($app) !== ''));
            return [
                'kicker_key' => 'admin.app_admin_status.kicker',
                'status_key' => 'admin.platform_status.status.operational',
                'title_key' => 'admin.app_admin_status.title',
                'description_key' => 'admin.app_admin_status.description',
                'show_migration_details' => false,
                'assigned_apps_count' => count($assignedApps),
                'links' => [],
            ];
        }
        if ($authorityRole !== 'platform_admin') {
            return [];
        }

        $currentMode = class_exists(\App\Services\PlatformModeService::class)
            ? \App\Services\PlatformModeService::currentMode()
            : 'production';
        $modeLocked = class_exists(\App\Services\PlatformModeService::class)
            && \App\Services\PlatformModeService::isModeSwitchLocked();
        $legacySnapshot = self::legacySnapshot($context);
        $showMigrationDetails = !empty($context['developer_tools_visible']);
        $links = [
            ['label_key' => 'admin.upgrade_workbench.link.system_tools', 'url' => '/admin/system-tools/platform-mode', 'kind' => 'target'],
        ];
        if ($showMigrationDetails) {
            array_unshift($links, ['label_key' => 'admin.upgrade_workbench.link.catalog', 'url' => '/admin/system-tools/upgrade-catalog', 'kind' => 'catalog']);
            $links[] = ['label_key' => 'admin.upgrade_workbench.link.architecture_health', 'url' => '/admin/architecture-health', 'kind' => 'evidence'];
        }

        return [
            'candidate_id' => 'ADM-MODE-001',
            'kicker_key' => 'admin.platform_status.kicker',
            'status_key' => 'admin.platform_status.status.operational',
            'title_key' => 'admin.platform_status.title',
            'description_key' => 'admin.platform_status.description',
            'method_key' => 'admin.upgrade_workbench.method',
            'next_key' => 'admin.upgrade_workbench.next',
            'show_migration_details' => $showMigrationDetails,
            'mode' => $currentMode,
            'mode_locked' => $modeLocked,
            'snapshot_cards' => $legacySnapshot['cards'],
            'snapshot_environment' => $legacySnapshot['environment'],
            'snapshot_state' => $legacySnapshot['state'],
            'links' => $links,
        ];
    }

    /** @param array<string,mixed> $context @return array{state:string,cards:array<int,array<string,mixed>>,environment:array<int,array<string,mixed>>} */
    private static function legacySnapshot(array $context): array
    {
        $empty = ['state' => 'degraded', 'cards' => [], 'environment' => []];
        if (!defined('APP_ROOT') || !function_exists('should_show_feature')) {
            return $empty;
        }
        $snapshotServicePath = APP_ROOT . '/plugins/Base/Services/PlatformAdminStatusSnapshotService.php';
        if (!class_exists(\Plugins\Base\Services\PlatformAdminStatusSnapshotService::class) && is_file($snapshotServicePath)) {
            require_once $snapshotServicePath;
        }
        if (!class_exists(\Plugins\Base\Services\PlatformAdminStatusSnapshotService::class)) {
            return $empty;
        }
        try {
            return \Plugins\Base\Services\PlatformAdminStatusSnapshotService::snapshot($context);
        } catch (\Throwable) {
            return $empty;
        }
    }

    /** @return array<int,array<string,mixed>> */
    public static function catalog(): array
    {
        return [
            self::candidate('ADM-MODE-001', 'admin.upgrade_catalog.capability.mode', 'System Tools', 'promoted', '/admin/system-tools/platform-mode', '/admin/system-tools/platform-mode'),
            self::candidate('ADM-HEALTH-001', 'admin.upgrade_catalog.capability.health', 'System Tools', 'promoted', '/admin/setup/health', '/admin/system-tools'),
            self::candidate('ADM-LOGS-001', 'admin.upgrade_catalog.capability.logs', 'System Tools', 'promoted', '/admin/system-tools/runtime-report', '/admin/system-tools'),
            self::candidate('ADM-APPS-001', 'admin.upgrade_catalog.capability.apps', 'Apps & Config', 'promoted', '/admin/apps', '/admin/system-tools'),
            self::candidate('ADM-SETUP-001', 'admin.upgrade_catalog.capability.setup', 'System Tools', 'promoted', '/admin/setup', '/admin/setup'),
            self::candidate('ADM-ENV-001', 'admin.upgrade_catalog.capability.environment', 'System Tools', 'promoted', '/admin/setup/environment', '/admin/system-tools'),
            self::candidate('ADM-SCHEMA-001', 'admin.upgrade_catalog.capability.schema', 'Apps & Config', 'promoted', '/admin/base', '/admin/base'),
            self::candidate('ADM-ACCESS-001', 'admin.upgrade_catalog.capability.access', 'Governance', 'promoted', '/ops/access-control', '/ops/platform-operations'),
            self::candidate('ADM-USERS-001', 'admin.upgrade_catalog.capability.users', 'Governance', 'promoted', '/ops/user-control', '/ops/platform-operations'),
            self::candidate('ADM-PROFILE-001', 'admin.upgrade_catalog.capability.profiles', 'Governance', 'promoted', '/ops/workspace-profiles', '/ops/platform-operations'),
            self::candidate('ADM-AUDIT-001', 'admin.upgrade_catalog.capability.audit', 'Governance', 'promoted', '/ops/audit-log', '/ops/platform-operations'),
            self::candidate('ADM-NOTIFY-001', 'admin.upgrade_catalog.capability.notifications', 'Governance', 'promoted', '/ops/notifications', '/ops/platform-operations'),
            self::candidate('ADM-ORG-001', 'admin.upgrade_catalog.capability.organization', 'Apps & Config', 'promoted', '/ops/organization', '/ops/organization'),
            self::candidate('ADM-ROUTES-001', 'admin.upgrade_catalog.capability.routes', 'Developer', 'promoted', '/admin/routes', '/admin/architecture-health'),
            self::candidate('ADM-DATA-001', 'admin.upgrade_catalog.capability.data', 'System Tools', 'promoted', '/admin/system-tools/data-control', '/admin/system-tools/data-control'),
            self::candidate('ADM-WIDGET-001', 'admin.upgrade_catalog.capability.design', 'Developer', 'promoted', '/ops/widget-builder', '/ops/widget-builder'),
            self::candidate('ADM-PLAN-001', 'admin.upgrade_catalog.capability.planned', 'System Tools', 'promoted', '/admin/setup/release', '/admin/system-tools/resilience'),
        ];
    }

    /** @return array<string,mixed> */
    private static function candidate(string $id, string $labelKey, string $group, string $status, string $source, string $target): array
    {
        return compact('id', 'labelKey', 'group', 'status', 'source', 'target') + ['preserve_source' => true];
    }
}
