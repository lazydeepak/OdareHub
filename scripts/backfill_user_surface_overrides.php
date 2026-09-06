<?php
/**
 * Backfill user_surface_overrides artifact table from transitional CSV
 * columns on user_dashboard_assignments, and print artifact coverage.
 *
 * Phase 6 of the Resolved Experience composition roadmap. Idempotent —
 * existing artifact rows are not touched.
 *
 * Usage: php scripts/backfill_user_surface_overrides.php
 */

declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', realpath(__DIR__ . '/../'));
}

require_once APP_ROOT . '/app/Core/helpers.php';
require_once APP_ROOT . '/plugins/Base/Services/UserSurfaceOverrideService.php';

use Plugins\Base\Services\UserSurfaceOverrideService;

$written = UserSurfaceOverrideService::backfillFromAssignments();
$summary = UserSurfaceOverrideService::coverageSummary();

printf("Backfilled %d artifact rows.\n\n", $written);
printf("%-22s %12s %12s %12s\n", 'field', 'artifact', 'inline_total', 'inline_only');
printf("%s\n", str_repeat('-', 62));
foreach ($summary as $field => $counts) {
    printf(
        "%-22s %12d %12d %12d\n",
        $field,
        $counts['artifact_rows'],
        $counts['inline_total'],
        $counts['inline_only']
    );
}
printf("\ninline_only > 0 means rows still depend on the transitional CSV column.\n");
