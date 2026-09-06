<?php
declare(strict_types=1);

namespace Apps\SBAIO\Services;

final class OperatorSurfaceContributionService
{
    /**
     * @param array<string,mixed> $request
     * @return array<string,mixed>|array<int,array<string,mixed>>
     */
    public static function contribute(array $request): array
    {
        $surface = strtolower(trim((string)($request['surface'] ?? '')));
        $region = strtolower(trim((string)($request['region'] ?? '')));
        $context = is_array($request['context'] ?? null) ? (array)$request['context'] : [];

        if ($surface !== 'operator' || !self::isSbaioAssigned($context)) {
            return [];
        }

        return match ($region) {
            'sidebar' => [
                'sections' => [
                    [
                        'title' => self::tr('operator.sidebar.sbaio', 'SBAIO'),
                        'items' => self::buildSidebarItems(),
                    ],
                ],
            ],
            'focus_views' => [
                'view_map' => [
                    'sbaio' => APP_ROOT . '/apps/SBAIO/Views/operator/sbaio.php',
                ],
            ],
            'data_exchange' => [
                'definitions' => self::dataExchangeDefinitions(),
            ],
            default => [],
        };
    }

    /**
     * @return array<int,array{icon:string,label:string,route:string,badge:null|int}>
     */
    private static function buildSidebarItems(): array
    {
        $items = [
            ['icon' => '👥', 'label' => self::tr('operator.sbaio.nav.workspace', 'SBAIO Workspace'), 'route' => '/u/{user}/sbaio', 'badge' => null],
        ];

        foreach (self::tabDefinitions() as $tab) {
            $slug = strtolower(trim((string)($tab['slug'] ?? '')));
            if ($slug === '' || $slug === 'overview') {
                continue;
            }

            $items[] = [
                'icon' => (string)($tab['icon'] ?? '•'),
                'label' => (string)($tab['label'] ?? self::tr('operator.sbaio.nav.section', 'Section')),
                'route' => '/u/{user}/sbaio?tab=' . rawurlencode($slug),
                'badge' => null,
            ];
        }

        return $items;
    }

    /**
     * @return array<int,array{slug:string,label:string,icon:string}>
     */
    private static function tabDefinitions(): array
    {
        return [
            ['slug' => 'overview', 'label' => self::tr('operator.sbaio.subnav.overview', 'Overview'), 'icon' => '📌'],
            ['slug' => 'attendance', 'label' => self::tr('nav.sbaio_attendance', 'Attendance'), 'icon' => '🕒'],
            ['slug' => 'timecards', 'label' => self::tr('nav.sbaio_timecards', 'Timecards'), 'icon' => '🧾'],
            ['slug' => 'payroll', 'label' => self::tr('nav.sbaio_payroll', 'Payroll'), 'icon' => '💸'],
            ['slug' => 'leave', 'label' => self::tr('nav.sbaio_leave', 'Leave'), 'icon' => '🌴'],
            ['slug' => 'staff', 'label' => self::tr('nav.sbaio_staff', 'Staff'), 'icon' => '👤'],
            ['slug' => 'schedules', 'label' => self::tr('nav.sbaio_schedules', 'Schedules'), 'icon' => '📅'],
            ['slug' => 'tasks', 'label' => self::tr('nav.sbaio_tasks', 'Tasks'), 'icon' => '✅'],
            ['slug' => 'customers', 'label' => self::tr('nav.sbaio_customers', 'Customers'), 'icon' => '🤝'],
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function dataExchangeDefinitions(): array
    {
        return [
            [
                'key' => 'sbaio.attendance_daily',
                'app_key' => 'sbaio',
                'module_key' => 'attendance',
                'title' => self::tr('operator.data_exchange.sbaio.attendance.title', 'Attendance Daily Import/Export'),
                'description' => self::tr('operator.data_exchange.sbaio.attendance.desc', 'Exchange daily attendance snapshots and exception adjustments.'),
                'supports_import' => true,
                'supports_export' => true,
                'template_name' => self::tr('operator.data_exchange.template.sbaio_attendance', 'sbaio_attendance_template.csv'),
                'governance_mode' => 'audited',
                'import_columns' => ['attendance_date', 'employee_code', 'status', 'hours_worked', 'notes'],
                'export_columns' => ['attendance_date', 'employee_code', 'status', 'hours_worked', 'notes'],
            ],
            [
                'key' => 'sbaio.timecard_weekly',
                'app_key' => 'sbaio',
                'module_key' => 'timecards',
                'title' => self::tr('operator.data_exchange.sbaio.timecard.title', 'Timecard Weekly Import/Export'),
                'description' => self::tr('operator.data_exchange.sbaio.timecard.desc', 'Exchange weekly timecards for payroll and utilization reconciliation.'),
                'supports_import' => true,
                'supports_export' => true,
                'template_name' => self::tr('operator.data_exchange.template.sbaio_timecard', 'sbaio_timecard_template.csv'),
                'governance_mode' => 'audited',
                'import_columns' => ['week_start_date', 'employee_code', 'clock_in', 'clock_out', 'break_minutes', 'total_hours'],
                'export_columns' => ['week_start_date', 'employee_code', 'clock_in', 'clock_out', 'break_minutes', 'total_hours'],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $context
     */
    private static function isSbaioAssigned(array $context): bool
    {
        $apps = array_map(
            static fn ($value): string => strtolower(trim((string)$value)),
            (array)($context['active_assigned_apps'] ?? $context['assigned_apps'] ?? [])
        );

        return in_array('sbaio', $apps, true);
    }

    private static function tr(string $key, string $fallback, array $params = []): string
    {
        if (function_exists('t')) {
            $translated = (string)t($key, $params);
            if ($translated !== '' && $translated !== $key) {
                return $translated;
            }
        }
        if ($params === []) {
            return $fallback;
        }
        $replace = [];
        foreach ($params as $paramKey => $paramValue) {
            $replace['{' . $paramKey . '}'] = (string)$paramValue;
        }
        return strtr($fallback, $replace);
    }
}
