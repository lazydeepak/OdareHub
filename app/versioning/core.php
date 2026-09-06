<?php
declare(strict_types=1);

return [
    'releases' => [
        [
            'version' => '0.4.0',
            'release_date' => '2026-04-09',
            'scope' => 'core',
            'summary' => 'Introduced layered setup, environment portability, restore/import-back, and release workflow foundations for Susankhya OS.',
            'breaking_changes' => [],
            'migration_notes' => [
                'Re-run Core verification after upgrade so runtime hooks, schema tables, and writable path checks are refreshed.',
            ],
            'setup_upgrade_notes' => [
                'Use /admin/setup for Core, Suite, Module, Environment, and Release workflows instead of ad hoc bootstrap steps.',
            ],
        ],
    ],
];
