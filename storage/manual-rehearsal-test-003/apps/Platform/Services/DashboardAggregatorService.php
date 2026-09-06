<?php
declare(strict_types=1);

namespace Apps\Platform\Services;

use Apps\Studio\Services\GuiStudioService;

final class DashboardAggregatorService
{
    /** @param array<string,mixed> $user @return array<int,array<string,mixed>> */
    public static function getTasksForUser(array $user): array
    {
        $dashboardType = self::mapUserRoleToDashboardType((string)($user['account_type'] ?? 'platform_admin'));
        $userId = (string)($user['id'] ?? '');
        $userEmail = (string)($user['email'] ?? '');
        return self::getTasksForDashboardType($dashboardType, $userId, $userEmail);
    }

    /** @return array<int,array<string,mixed>> */
    public static function getTasksForDashboardType(string $dashboardType, string $userId = '', string $userEmail = ''): array
    {
        $tasks = [];
        if (!self::loadStudioServiceIfEnabled()) {
            return $tasks;
        }

        foreach (GuiStudioService::generatedModuleDefinitions(false) as $module) {
            if (!is_array($module)) {
                continue;
            }

            $runtime = GuiStudioService::generatedModuleRuntimeData($module, []);
            $rows = is_array($runtime['rows'] ?? null) ? $runtime['rows'] : [];
            $appKey = self::normalizeKey((string)($module['app_key'] ?? ''));
            $moduleKey = self::normalizeKey((string)($module['module_key'] ?? ''));
            if ($appKey === '' || $moduleKey === '') {
                continue;
            }

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                if (!self::shouldShowTaskInDashboard($row, $dashboardType, $userId)) {
                    continue;
                }

                $tasks[] = [
                    'id' => (string)($row['id'] ?? ''),
                    'app_key' => $appKey,
                    'module_key' => $moduleKey,
                    'app_display_name' => (string)($module['display_name'] ?? self::displayName($appKey)),
                    'module_display_name' => (string)($module['module_key'] ?? ''),
                    'status' => self::normalizeWorkflowStatus((string)($row['status'] ?? 'draft')),
                    'title' => (string)($row['title'] ?? $row['name'] ?? ''),
                    'description' => (string)($row['description'] ?? ''),
                    'created_at' => (string)($row['created_at'] ?? gmdate('c')),
                    'updated_at' => (string)($row['updated_at'] ?? gmdate('c')),
                    'time_in_state_seconds' => (int)($row['time_in_state_seconds'] ?? 0),
                    'time_in_state_label' => (string)($row['time_in_state_label'] ?? ''),
                    'max_time_in_state_seconds' => (int)($row['max_time_in_state_seconds'] ?? 0),
                    'is_overdue' => !empty($row['is_overdue']),
                    'is_delayed' => !empty($row['is_delayed']),
                    'history' => self::normalizeWorkflowHistory($row['history'] ?? []),
                ];
            }
        }

        return $tasks;
    }

    private static function loadStudioServiceIfEnabled(): bool
    {
        try {
            $row = \App\Core\DB::fetchOne('SELECT status FROM core_apps WHERE app_key=? LIMIT 1', ['studio']);
            if (!is_array($row) || (string)($row['status'] ?? '') !== 'enabled') {
                return false;
            }
        } catch (\Throwable $e) {
            return false;
        }

        $servicePath = APP_ROOT . '/apps/Studio/Services/GuiStudioService.php';
        if (!class_exists(GuiStudioService::class) && is_file($servicePath)) {
            require_once $servicePath;
        }

        return class_exists(GuiStudioService::class);
    }

    public static function mapUserRoleToDashboardType(string $accountType): string
    {
        $mapping = [
            'platform_admin' => 'admin',
            'app_admin' => 'admin',
            'operator' => 'operator',
            'qc_inspector' => 'qc',
            'dispatch_manager' => 'dispatch',
        ];
        return $mapping[strtolower($accountType)] ?? 'operator';
    }

    /** @param array<string,mixed> $row */
    private static function shouldShowTaskInDashboard(array $row, string $dashboardType, string $userId): bool
    {
        $status = self::normalizeWorkflowStatus((string)($row['status'] ?? 'draft'));

        if ($dashboardType === 'operator') {
            return $status === 'in_progress';
        }
        if ($dashboardType === 'qc') {
            return in_array($status, ['completed', 'approved'], true);
        }
        if ($dashboardType === 'dispatch') {
            return in_array($status, ['approved', 'dispatched'], true);
        }

        return true;
    }

    private static function normalizeKey(string $value): string
    {
        $key = strtolower(trim($value));
        $key = str_replace('-', '_', $key);
        $key = preg_replace('/[^a-z0-9_]+/', '_', $key) ?? '';
        return trim($key, '_');
    }

    private static function displayName(string $key): string
    {
        $normalized = preg_replace('/[^a-z0-9]+/i', ' ', $key) ?? '';
        $parts = preg_split('/\s+/', trim($normalized)) ?: [];
        $name = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $name .= ucfirst(strtolower($part));
        }
        return $name !== '' ? $name : 'Draft';
    }

    private static function normalizeWorkflowStatus(string $status): string
    {
        $normalized = strtolower(trim($status));
        $allowed = ['draft', 'in_progress', 'completed', 'approved', 'dispatched', 'rejected', 'cancelled', 'on_hold'];
        return in_array($normalized, $allowed, true) ? $normalized : 'draft';
    }

    /** @param mixed $historyRaw @return array<int,array<string,mixed>> */
    private static function normalizeWorkflowHistory(mixed $historyRaw): array
    {
        if (!is_array($historyRaw)) {
            return [];
        }

        $history = [];
        foreach ($historyRaw as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $history[] = [
                'from_status' => self::normalizeWorkflowStatus((string)($entry['from_status'] ?? 'draft')),
                'to_status' => self::normalizeWorkflowStatus((string)($entry['to_status'] ?? 'draft')),
                'role' => self::normalizeKey((string)($entry['role'] ?? 'read_only')) ?: 'read_only',
                'timestamp' => trim((string)($entry['timestamp'] ?? '')) ?: gmdate('c'),
                'actor' => self::safeActor((string)($entry['actor'] ?? 'system')),
                'duration_in_previous_state_seconds' => max(0, (int)($entry['duration_in_previous_state_seconds'] ?? 0)),
                'duration_in_previous_state_label' => (string)($entry['duration_in_previous_state_label'] ?? ''),
            ];
        }

        return array_values($history);
    }

    private static function safeActor(string $value): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
        $value = substr($value, 0, 120);
        return $value !== '' ? $value : 'system';
    }
}
