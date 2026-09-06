<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

use App\Core\Auth;
use App\Core\DB;

final class ModuleSurfaceWidgetFactory
{
    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $config
     * @return array<int,array<string,mixed>>
     */
    public static function build(string $region, array $context, array $config): array
    {
        if (!self::canAccessRoute((string)($config['url'] ?? '/'), $context)) {
            return [];
        }

        if ($region === 'summary_cards') {
            if (!(bool)($config['include_summary'] ?? true)) {
                return [];
            }

            return [self::summaryCard($config, $context)];
        }

        if ($region !== 'monitoring_sections') {
            return [];
        }

        $attachFormToTable = (bool)self::resolveValue($config['attach_form_to_table'] ?? true, $context, $config);

        $items = [];
        if ((bool)($config['include_worker_view'] ?? true)) {
            $items[] = self::workerWorkSection($config, $context);
        }
        if ((bool)($config['include_chart'] ?? true)) {
            $items[] = self::chartSection($config, $context);
        }
        if ((bool)($config['include_table'] ?? true)) {
            $items[] = self::tableSection($config, $context, $attachFormToTable);
        }
        if ((bool)($config['include_form'] ?? true) && !$attachFormToTable) {
            $items[] = self::formSection($config, $context);
        }

        return $items;
    }

    /**
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    private static function workerWorkSection(array $config, array $context): array
    {
        $fallbackRows = [[
            'cells' => ['Workspace', (string)($config['title'] ?? 'Module'), 'Current queue'],
            'url' => (string)($config['url'] ?? '/apps/manufacturing'),
        ]];

        $rows = self::resolveArray(
            $config['worker_rows']
            ?? $config['table_rows']
            ?? $fallbackRows,
            $context,
            $config
        );

        if ($rows === []) {
            $rows = $fallbackRows;
        }

        return [
            'module_key' => (string)($config['key'] ?? 'module'),
            'widget_key' => (string)($config['key'] ?? 'module') . '_worker_queue',
            'surface_key' => 'me',
            'view_kind' => 'table',
            'widget_type' => 'queue',
            'placement_zone' => 'primary_work',
            'interaction_profiles' => self::resolveArray($config['worker_profiles'] ?? ['worker'], $context, $config),
            'supports_empty_state' => true,
            'supports_clickthrough' => true,
            'title' => self::resolveString($config['worker_title'] ?? ((string)($config['title'] ?? 'Module') . ' · My Work'), $context, $config),
            'description' => self::resolveString($config['worker_description'] ?? 'Worker-side actionable module queue.', $context, $config),
            'kind' => 'table',
            'columns' => self::resolveArray($config['worker_columns'] ?? $config['table_columns'] ?? ['Queue', 'Focus', 'Context'], $context, $config),
            'rows' => array_values(array_slice($rows, 0, (int)($config['worker_max_rows'] ?? 6))),
            'empty_message' => self::resolveString($config['worker_empty_message'] ?? 'No worker queue items for this module.', $context, $config),
            'weight' => (int)($config['priority'] ?? 30) + 12,
        ];
    }

    /**
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    private static function summaryCard(array $config, array $context): array
    {
        $total = self::safeCount((string)($config['table'] ?? ''));
        $recent = self::safeRecentCount((string)($config['table'] ?? ''));
        $value = self::resolveScalar($config['summary_value'] ?? $total, $context, $config);
        $meta = self::resolveString($config['summary_meta'] ?? ('Recent 7d: ' . $recent), $context, $config);
        $tone = self::resolveString($config['summary_tone'] ?? ($total > 0 ? 'info' : 'neutral'), $context, $config);
        $widgetType = self::resolveString($config['summary_widget_type'] ?? ($total > 0 ? 'informative' : 'reference'), $context, $config);
        $profiles = self::resolveArray($config['summary_profiles'] ?? ['worker', 'leader', 'admin', 'read_only'], $context, $config);

        return [
            'widget_key' => (string)($config['key'] ?? 'module') . '_summary',
            'surface_key' => 'me',
            'view_kind' => 'kpi',
            'widget_type' => $widgetType,
            'placement_zone' => 'dashboard_summary',
            'interaction_profiles' => $profiles,
            'supports_empty_state' => true,
            'supports_clickthrough' => true,
            'title' => (string)($config['title'] ?? 'Module'),
            'value' => $value,
            'meta' => $meta,
            'url' => (string)($config['url'] ?? '/apps/manufacturing'),
            'tone' => $tone,
            'priority' => (int)($config['priority'] ?? 30),
            'weight' => (int)($config['priority'] ?? 30),
        ];
    }

    /**
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    private static function chartSection(array $config, array $context): array
    {
        $total = self::safeCount((string)($config['table'] ?? ''));
        $recent = self::safeRecentCount((string)($config['table'] ?? ''));
        $rows = self::resolveArray($config['chart_rows'] ?? [
            ['label' => 'Total', 'value' => $total, 'meta' => 'All records'],
            ['label' => 'Recent 7d', 'value' => $recent, 'meta' => 'New or updated'],
        ], $context, $config);

        return [
            'module_key' => (string)($config['key'] ?? 'module'),
            'widget_key' => (string)($config['key'] ?? 'module') . '_chart',
            'surface_key' => 'me',
            'view_kind' => 'chart',
            'widget_type' => 'chart',
            'placement_zone' => 'monitoring',
            'interaction_profiles' => self::resolveArray($config['chart_profiles'] ?? ['leader', 'admin', 'read_only', 'display'], $context, $config),
            'supports_empty_state' => true,
            'supports_clickthrough' => true,
            'title' => self::resolveString($config['chart_title'] ?? ((string)($config['title'] ?? 'Module') . ' · Snapshot'), $context, $config),
            'description' => self::resolveString($config['chart_description'] ?? 'Role-aware operational volume snapshot for this module.', $context, $config),
            'kind' => 'chart',
            'rows' => $rows,
            'empty_message' => self::resolveString($config['chart_empty_message'] ?? 'No operational records currently available.', $context, $config),
            'weight' => (int)($config['priority'] ?? 30) + 10,
        ];
    }

    /**
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    private static function tableSection(array $config, array $context, bool $attachFormToTable = false): array
    {
        $rows = [[
            'cells' => ['Workspace', (string)($config['title'] ?? 'Module'), 'Core Surface'],
            'url' => (string)($config['url'] ?? '/apps/manufacturing'),
        ]];

        $reportUrl = trim((string)($config['report_url'] ?? ''));
        if ($reportUrl !== '') {
            $rows[] = [
                'cells' => ['Report', (string)($config['title'] ?? 'Module') . ' Report', 'Analysis'],
                'url' => $reportUrl,
            ];
        }

        $exportUrl = trim((string)($config['export_url'] ?? ''));
        if ($exportUrl !== '') {
            $rows[] = [
                'cells' => ['Export', (string)($config['title'] ?? 'Module') . ' Export', 'Dataset'],
                'url' => $exportUrl,
            ];
        }

        $rows = self::resolveArray($config['table_rows'] ?? $rows, $context, $config);

        $inlineFilter = null;
        if ($attachFormToTable && (bool)($config['include_form'] ?? true)) {
            $formSpec = self::resolvedFormSpec($config, $context);
            if ($formSpec['action'] !== '' && $formSpec['fields'] !== []) {
                $inlineFilter = [
                    'title' => self::resolveString($config['form_title'] ?? ((string)($config['title'] ?? 'Module') . ' · Quick Filter'), $context, $config),
                    'description' => self::resolveString($config['form_description'] ?? 'Role-aware launcher form for quick module navigation.', $context, $config),
                    'method' => $formSpec['method'],
                    'action' => $formSpec['action'],
                    'fields' => $formSpec['fields'],
                    'actions' => $formSpec['actions'],
                ];
            }
        }

        return [
            'module_key' => (string)($config['key'] ?? 'module'),
            'widget_key' => (string)($config['key'] ?? 'module') . '_table',
            'surface_key' => 'me',
            'view_kind' => 'table',
            'widget_type' => 'reference',
            'placement_zone' => 'supporting_visibility',
            'interaction_profiles' => self::resolveArray($config['table_profiles'] ?? ['worker', 'leader', 'admin', 'read_only'], $context, $config),
            'supports_empty_state' => true,
            'supports_clickthrough' => true,
            'title' => self::resolveString($config['table_title'] ?? ((string)($config['title'] ?? 'Module') . ' · Surfaces'), $context, $config),
            'description' => self::resolveString($config['table_description'] ?? 'Role-safe table of module surfaces available in this workspace.', $context, $config),
            'kind' => self::resolveString($config['table_kind'] ?? 'table', $context, $config),
            'columns' => self::resolveArray($config['table_columns'] ?? ['Surface', 'Purpose', 'Context'], $context, $config),
            'rows' => $rows,
            'inline_filter' => $inlineFilter,
            'empty_message' => self::resolveString($config['table_empty_message'] ?? 'No module surfaces are currently available.', $context, $config),
            'weight' => (int)($config['priority'] ?? 30) + 15,
        ];
    }

    /**
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    private static function formSection(array $config, array $context): array
    {
        $formSpec = self::resolvedFormSpec($config, $context);

        return [
            'module_key' => (string)($config['key'] ?? 'module'),
            'widget_key' => (string)($config['key'] ?? 'module') . '_form',
            'surface_key' => 'me',
            'view_kind' => 'form',
            'widget_type' => 'action',
            'placement_zone' => 'operator_actions',
            'interaction_profiles' => self::resolveArray($config['form_profiles'] ?? ['worker', 'leader', 'admin'], $context, $config),
            'supports_empty_state' => true,
            'supports_clickthrough' => true,
            'title' => self::resolveString($config['form_title'] ?? ((string)($config['title'] ?? 'Module') . ' · Quick Filter'), $context, $config),
            'description' => self::resolveString($config['form_description'] ?? 'Role-aware launcher form for quick module navigation.', $context, $config),
            'kind' => 'form',
            'requires_companion_views' => (bool)self::resolveValue($config['form_requires_companion_views'] ?? false, $context, $config),
            'method' => $formSpec['method'],
            'action' => $formSpec['action'],
            'fields' => $formSpec['fields'],
            'actions' => $formSpec['actions'],
            'empty_message' => self::resolveString($config['form_empty_message'] ?? 'Quick filter is unavailable for this module.', $context, $config),
            'weight' => (int)($config['priority'] ?? 30) + 18,
        ];
    }

    /**
     * @param array<string,mixed> $config
     * @param array<string,mixed> $context
     * @return array{method:string,action:string,fields:array<int|string,mixed>,actions:array<int|string,mixed>}
     */
    private static function resolvedFormSpec(array $config, array $context): array
    {
        return [
            'method' => self::resolveString($config['form_method'] ?? 'get', $context, $config),
            'action' => self::resolveString($config['form_action'] ?? (string)($config['url'] ?? '/apps/manufacturing'), $context, $config),
            'fields' => self::resolveArray($config['form_fields'] ?? [
                [
                    'type' => 'select',
                    'name' => 'scope',
                    'label' => 'Scope',
                    'options' => [
                        ['value' => 'all', 'label' => 'All', 'selected' => true],
                        ['value' => 'my', 'label' => 'My'],
                    ],
                ],
                [
                    'type' => 'text',
                    'name' => 'q',
                    'label' => 'Search',
                    'value' => '',
                ],
            ], $context, $config),
            'actions' => self::resolveArray($config['form_actions'] ?? [
                ['type' => 'submit', 'label' => 'Open Module', 'primary' => true],
            ], $context, $config),
        ];
    }

    /**
     * @param mixed $value
     * @param array<string,mixed> $context
     * @param array<string,mixed> $config
     * @return mixed
     */
    private static function resolveValue(mixed $value, array $context, array $config): mixed
    {
        if ($value instanceof \Closure || is_callable($value)) {
            return $value($context, $config);
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @param array<string,mixed> $context
     * @param array<string,mixed> $config
     */
    private static function resolveString(mixed $value, array $context, array $config): string
    {
        return (string)self::resolveValue($value, $context, $config);
    }

    /**
     * @param mixed $value
     * @param array<string,mixed> $context
     * @param array<string,mixed> $config
     */
    private static function resolveScalar(mixed $value, array $context, array $config): int|float|string
    {
        $resolved = self::resolveValue($value, $context, $config);
        if (is_int($resolved) || is_float($resolved) || is_string($resolved)) {
            return $resolved;
        }

        return is_numeric($resolved) ? 0 + (string)$resolved : (string)$resolved;
    }

    /**
     * @param mixed $value
     * @param array<string,mixed> $context
     * @param array<string,mixed> $config
     * @return array<int|string,mixed>
     */
    private static function resolveArray(mixed $value, array $context, array $config): array
    {
        $resolved = self::resolveValue($value, $context, $config);
        return is_array($resolved) ? $resolved : [];
    }

    private static function safeCount(string $table): int
    {
        if ($table === '' || !self::tableExists($table)) {
            return 0;
        }

        try {
            $row = DB::fetchOne("SELECT COUNT(*) AS total FROM {$table}");
            return (int)($row['total'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private static function safeRecentCount(string $table): int
    {
        if ($table === '' || !self::tableExists($table)) {
            return 0;
        }

        $timeColumn = self::firstExistingColumn($table, ['updated_at', 'created_at', 'entry_date', 'plan_date', 'order_date']);
        if ($timeColumn === null) {
            return 0;
        }

        try {
            $row = DB::fetchOne(
                "SELECT COUNT(*) AS total FROM {$table} WHERE DATE({$timeColumn}) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
            );
            return (int)($row['total'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private static function tableExists(string $table): bool
    {
        try {
            return DB::fetchOne(
                'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1',
                [$table]
            ) !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param array<int,string> $columns
     */
    private static function firstExistingColumn(string $table, array $columns): ?string
    {
        foreach ($columns as $column) {
            try {
                $exists = DB::fetchOne(
                    'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1',
                    [$table, $column]
                );
                if ($exists !== null) {
                    return $column;
                }
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $context
     */
    private static function canAccessRoute(string $url, array $context): bool
    {
        $path = trim((string)parse_url($url, PHP_URL_PATH));
        if ($path === '') {
            return false;
        }

        if (!function_exists('platform_user_access_policy_contract')) {
            return true;
        }

        $policy = platform_user_access_policy_contract();
        if (!is_object($policy)) {
            return true;
        }

        $user = is_array($context['user'] ?? null) ? $context['user'] : Auth::user();
        if (!is_array($user)) {
            return true;
        }

        try {
            $decision = $policy->routeAccessDecision($user, $path, 'GET');
            return (bool)($decision['allowed'] ?? false);
        } catch (\Throwable $e) {
            return true;
        }
    }
}
