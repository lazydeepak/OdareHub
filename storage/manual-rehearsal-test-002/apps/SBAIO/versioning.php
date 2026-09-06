<?php
declare(strict_types=1);

$moduleSummaries = [
    'Staff' => 'Standardized the staff master and shared module page layout for SBAIO workforce operations.',
    'Attendance' => 'Improved attendance visibility, missing-punch monitoring, and host-surface summaries.',
    'Timecards' => 'Added clearer draft-period handling and compact workflow-oriented summaries.',
    'Payroll' => 'Expanded payroll run visibility, draft payroll actions, and workbook migration readiness.',
    'Schedules' => 'Standardized schedules layout and readiness for guided setup and release workflows.',
    'Leave' => 'Improved leave request visibility and consistent module lifecycle handling.',
    'Customers' => 'Moved lighter customer data access into thin services and standardized page structure.',
    'Tasks' => 'Moved lighter task data access into thin services and standardized page structure.',
    'Sales' => 'Moved lighter sales data access into thin services and standardized page structure.',
    'Expenses' => 'Moved lighter expense data access into thin services and standardized page structure.',
    'Notices' => 'Moved lighter notice data access into thin services and standardized page structure.',
];

$modules = [];
foreach ($moduleSummaries as $moduleName => $summary) {
    $modules[$moduleName] = [
        'releases' => [
            [
                'version' => '1.0.0',
                'release_date' => '2026-04-09',
                'scope' => 'module',
                'summary' => $summary,
                'breaking_changes' => [],
                'migration_notes' => [
                    'No dedicated module migration notes recorded yet. Review suite verification and module schema checks before upgrading.',
                ],
                'setup_upgrade_notes' => [
                    'Run module repair or suite configure if runtime hooks, schema, or dependencies need refresh after upgrade.',
                ],
            ],
        ],
    ];
}

return [
    'suite' => [
        'releases' => [
            [
                'version' => '1.0.0',
                'release_date' => '2026-04-09',
                'scope' => 'suite',
                'summary' => 'Established SBAIO dashboard, /me integration, setup flows, demo/import/export/restore paths, and service-backed lighter modules.',
                'breaking_changes' => [],
                'migration_notes' => [
                    'Re-run SBAIO verify after upgrade to confirm attendance, payroll, leave, and workbook-backed reference tables remain healthy.',
                ],
                'setup_upgrade_notes' => [
                    'Use the SBAIO suite detail page to review profile changes, verification warnings, and child module states after upgrade.',
                ],
            ],
        ],
    ],
    'modules' => $modules,
];
