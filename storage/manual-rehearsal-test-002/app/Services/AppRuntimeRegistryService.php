<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

final class AppRuntimeRegistryService
{
    public function syncEnabledRuntimeRegistries(): void
    {
        $this->ensureMenuSourceColumns();
        $this->ensurePermissionSourceColumns();
        $this->syncMenus();
        $this->syncPermissions();
    }

    /**
     * @return array{modules:int,permissions:int,hooks:int,enabled:bool}
     */
    public function refreshAppRuntimeArtifacts(string $appKey, ?array $manifest = null, ?bool $enabled = null): array
    {
        $row = DB::fetchOne('SELECT status, manifest_json FROM core_apps WHERE app_key=? LIMIT 1', [$appKey]);
        if (!is_array($row)) {
            throw new \RuntimeException('App not found: ' . $appKey);
        }

        if (!is_array($manifest)) {
            $manifest = json_decode((string)($row['manifest_json'] ?? '{}'), true);
            if (!is_array($manifest)) {
                throw new \RuntimeException('App manifest_json is invalid');
            }
        }

        $enabledState = $enabled;
        if ($enabledState === null) {
            $enabledState = ((string)($row['status'] ?? '')) === AppRegistryService::STATUS_ENABLED;
        }
        $enabledFlag = $enabledState ? 1 : 0;

        DB::query('DELETE FROM core_app_modules WHERE app_key=?', [$appKey]);
        DB::query('DELETE FROM core_app_permissions WHERE app_key=?', [$appKey]);
        DB::query('DELETE FROM core_app_hooks WHERE app_key=?', [$appKey]);

        $moduleCount = 0;
        foreach ((array)($manifest['modules'] ?? []) as $module) {
            $moduleKey = trim((string)($module['key'] ?? $module));
            if ($moduleKey === '') {
                continue;
            }
            DB::query(
                'INSERT INTO core_app_modules (app_key, module_key, module_name, is_enabled) VALUES (?,?,?,?)',
                [$appKey, $moduleKey, (string)($module['name'] ?? $moduleKey), $enabledFlag]
            );
            $moduleCount++;
        }

        $permissionCount = 0;
        foreach ((array)($manifest['permissions'] ?? []) as $permission) {
            $permKey = trim((string)$permission);
            if ($permKey === '') {
                continue;
            }
            DB::query(
                'INSERT INTO core_app_permissions (app_key, permission_key, description) VALUES (?,?,?)',
                [$appKey, $permKey, 'Manifest permission']
            );
            $permissionCount++;
        }

        $hookCount = 0;
        foreach (AppManifestService::surfaceHooksFromManifest($manifest) as $surfaceHook) {
            DB::query(
                'INSERT INTO core_app_hooks (app_key, hook_type, hook_key, payload_json, is_enabled) VALUES (?,?,?,?,?)',
                [
                    $appKey,
                    (string)($surfaceHook['hook_type'] ?? 'runtime'),
                    (string)($surfaceHook['hook_key'] ?? ''),
                    json_encode((array)($surfaceHook['payload'] ?? []), JSON_UNESCAPED_SLASHES),
                    $enabledFlag,
                ]
            );
            $hookCount++;
        }

        return [
            'modules' => $moduleCount,
            'permissions' => $permissionCount,
            'hooks' => $hookCount,
            'enabled' => $enabledState,
        ];
    }

    public function enabledWidgets(): array
    {
        return $this->enabledSurfacesByType('widget');
    }

    public function enabledCharts(): array
    {
        return $this->enabledSurfacesByType('chart');
    }

    public function enabledDashboards(): array
    {
        return $this->enabledSurfacesByType('dashboard');
    }

    public function enabledSearchEntries(): array
    {
        return $this->enabledSurfacesByType('search_entry');
    }

    public function emitBootHooks(): void
    {
        $this->emitBootHooksForApp(null);
    }

    public function emitBootHooksForApp(?string $appKey): void
    {
        $params = [];
        $whereApp = '';
        if ($appKey !== null && trim($appKey) !== '') {
            $whereApp = ' AND h.app_key = ?';
            $params[] = trim($appKey);
        }

        $rows = DB::fetchAll(
            "SELECT h.app_key, h.hook_key, h.payload_json
             FROM core_app_hooks h
             INNER JOIN core_apps a ON a.app_key = h.app_key
             WHERE a.status = 'enabled' AND h.is_enabled = 1 AND h.hook_type = 'boot'{$whereApp}
             ORDER BY h.id ASC",
            $params
        );

        foreach ($rows as $row) {
            $payload = json_decode((string)($row['payload_json'] ?? '{}'), true);
            if (!is_array($payload)) {
                continue;
            }

            $handlerFile = trim((string)($payload['handler_file'] ?? ''));
            if ($handlerFile === '') {
                continue;
            }

            $installPath = (string)(DB::fetchOne('SELECT install_path FROM core_apps WHERE app_key=? LIMIT 1', [(string)$row['app_key']])['install_path'] ?? '');
            if ($installPath === '') {
                continue;
            }

            $file = rtrim($installPath, '/') . '/' . ltrim($handlerFile, '/');
            if (!is_file($file)) {
                continue;
            }

            try {
                require $file;
            } catch (\Throwable $e) {
                AppPlatformLogger::lifecycle('boot_hook_failed', (string)$row['app_key'], ['error' => $e->getMessage(), 'hook' => (string)$row['hook_key']]);
            }
        }
    }

    private function syncMenus(): void
    {
        DB::query("DELETE FROM menus WHERE source_type = 'app'");

        $rows = DB::fetchAll(
            "SELECT h.app_key, h.hook_key, h.payload_json
             FROM core_app_hooks h
             INNER JOIN core_apps a ON a.app_key = h.app_key
             WHERE a.status = 'enabled' AND h.is_enabled = 1 AND h.hook_type = 'menu'
             ORDER BY h.id ASC"
        );

        foreach ($rows as $row) {
            $payload = json_decode((string)($row['payload_json'] ?? '{}'), true);
            if (!is_array($payload)) {
                continue;
            }

            $menuKey = trim((string)($payload['key'] ?? $row['hook_key'] ?? ''));
            if ($menuKey === '') {
                continue;
            }

            DB::query(
                "INSERT INTO menus (menu_key,label,url,parent_key,display_order,perm_key,source_app_key,source_type)
                 VALUES (?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                    label=VALUES(label),
                    url=VALUES(url),
                    parent_key=VALUES(parent_key),
                    display_order=VALUES(display_order),
                    perm_key=VALUES(perm_key),
                    source_app_key=VALUES(source_app_key),
                    source_type=VALUES(source_type)",
                [
                    $menuKey,
                    (string)($payload['label'] ?? $menuKey),
                    (string)($payload['url'] ?? ''),
                    $payload['parent'] ?? null,
                    (int)($payload['order'] ?? 100),
                    $payload['perm'] ?? null,
                    (string)($row['app_key'] ?? ''),
                    'app',
                ]
            );
        }
    }

    private function syncPermissions(): void
    {
        DB::query("DELETE FROM permissions WHERE source_type = 'app'");

        $rows = DB::fetchAll(
            "SELECT p.app_key, p.permission_key, p.description
             FROM core_app_permissions p
             INNER JOIN core_apps a ON a.app_key = p.app_key
             WHERE a.status = 'enabled'
             ORDER BY p.app_key ASC, p.permission_key ASC"
        );

        foreach ($rows as $row) {
            DB::query(
                "INSERT INTO permissions (perm_key, description, source_app_key, source_type)
                 VALUES (?,?,?,?)
                 ON DUPLICATE KEY UPDATE description=VALUES(description), source_app_key=VALUES(source_app_key), source_type=VALUES(source_type)",
                [
                    (string)($row['permission_key'] ?? ''),
                    (string)($row['description'] ?? 'App permission'),
                    (string)($row['app_key'] ?? ''),
                    'app',
                ]
            );
        }
    }

    private function ensureMenuSourceColumns(): void
    {
        try {
            DB::query("ALTER TABLE menus ADD COLUMN source_app_key VARCHAR(120) NULL");
        } catch (\Throwable $e) {
            // additive and idempotent
        }

        try {
            DB::query("ALTER TABLE menus ADD COLUMN source_type VARCHAR(30) NULL");
        } catch (\Throwable $e) {
            // additive and idempotent
        }
    }

    private function ensurePermissionSourceColumns(): void
    {
        try {
            DB::query("ALTER TABLE permissions ADD COLUMN source_app_key VARCHAR(120) NULL");
        } catch (\Throwable $e) {
            // additive and idempotent
        }

        try {
            DB::query("ALTER TABLE permissions ADD COLUMN source_type VARCHAR(30) NULL");
        } catch (\Throwable $e) {
            // additive and idempotent
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function enabledSurfacesByType(string $hookType): array
    {
        $hookType = trim($hookType);
        if ($hookType === '') {
            return [];
        }

        $rows = DB::fetchAll(
            "SELECT h.app_key, h.hook_key, h.payload_json
             FROM core_app_hooks h
             INNER JOIN core_apps a ON a.app_key = h.app_key
             WHERE a.status = 'enabled' AND h.is_enabled = 1 AND h.hook_type = ?
             ORDER BY h.id ASC",
            [$hookType]
        );

        $surfaces = [];
        foreach ($rows as $row) {
            $payload = json_decode((string)($row['payload_json'] ?? '{}'), true);
            if (!is_array($payload)) {
                continue;
            }

            $key = trim((string)($payload['key'] ?? $row['hook_key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $surfaces[] = [
                'key' => $key,
                'hook_type' => $hookType,
                'app_key' => (string)($row['app_key'] ?? ''),
                'title' => (string)($payload['title'] ?? ($payload['label'] ?? $key)),
                'label_key' => (string)($payload['label_key'] ?? ($payload['title_key'] ?? '')),
                'url' => (string)($payload['url'] ?? ''),
                'order' => (int)($payload['order'] ?? 99),
                'visible_if' => (string)($payload['visible_if'] ?? 'always'),
                'feature_key' => (string)($payload['feature_key'] ?? $key),
                'status' => (string)($payload['status'] ?? 'active'),
                'source_plugin' => (string)($payload['source_plugin'] ?? ($row['app_key'] ?? '')),
                'domain' => (string)($payload['domain'] ?? ''),
                'zone' => (string)($payload['zone'] ?? ''),
                'detail' => (string)($payload['detail'] ?? ''),
                'extra' => (string)($payload['extra'] ?? ''),
                'meta' => (string)($payload['meta'] ?? ''),
                'count' => $payload['count'] ?? null,
            ];
        }

        return $surfaces;
    }
}
