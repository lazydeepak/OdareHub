<?php
declare(strict_types=1);

$moduleSummaries = [
    'Workflow' => 'Stabilized workflow-aware IPM routing and stage-based lifecycle coordination.',
    'Supply' => 'Added supply-side planning support for the manufacturing demand pipeline.',
    'Coverage' => 'Expanded coverage calculations and release-readiness visibility for demand and planning.',
    'Products' => 'Strengthened the parts master and product context for IPM workflows.',
    'Machines' => 'Improved machine master coverage for execution and planning workflows.',
    'PartMachineMap' => 'Standardized part-to-machine mapping for planning and restore/export readiness.',
    'DailyOrders' => 'Expanded daily order handling and demand workspace integration.',
    'PreOrders' => 'Added template-friendly pre-order import and planning support.',
    'ProductionPlans' => 'Improved production planning governance and workflow alignment.',
    'ProductionQueue' => 'Expanded queue-level execution readiness for production handoff.',
    'ProductionEntries' => 'Strengthened production execution entries and downstream ledger alignment.',
    'QCPlans' => 'Improved QC plan management for staged manufacturing workflows.',
    'QCEntries' => 'Expanded QC execution visibility and dashboard alignment.',
    'DispatchEntries' => 'Strengthened dispatch workflow handling and release readiness visibility.',
    'Ledger' => 'Improved manufacturing ledger support for traceable execution state.',
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
                    'Use module repair or suite configure after upgrade if runtime hooks, schema, or dependencies need refresh.',
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
                'summary' => 'Established Manufacturing dashboard, setup flows, import/export/restore paths, environment portability, and release readiness packaging.',
                'breaking_changes' => [],
                'migration_notes' => [
                    'Re-run Manufacturing verify after upgrade to confirm planning, workflow, QC, dispatch, and ledger tables remain healthy.',
                ],
                'setup_upgrade_notes' => [
                    'Use the Manufacturing suite detail page to review configuration profile changes, verification warnings, and child module states after upgrade.',
                ],
            ],
        ],
    ],
    'modules' => $modules,
];
