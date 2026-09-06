<?php

declare(strict_types=1);

/**
 * StudioWorkspaceRouteMapper Probe
 *
 * Tests exact, prefix, child-page, unknown, and unmapped route resolution.
 *
 * Usage: php probe_route_mapper.php
 * Exit code: 0 = all passed, 1 = failure
 */

require_once __DIR__ . '/../Services/StudioWorkspaceRouteMapper.php';

use Apps\Studio\Services\StudioWorkspaceRouteMapper;

$passed = 0;
$failed = 0;

function assert_eq(mixed $expected, mixed $actual, string $label): void
{
    global $passed, $failed;
    if ($expected === $actual) {
        $passed++;
    } else {
        $failed++;
        $expectedStr = var_export($expected, true);
        $actualStr = var_export($actual, true);
        echo "  FAIL [{$label}]: expected {$expectedStr}, got {$actualStr}\n";
    }
}

function assert_null(mixed $actual, string $label): void
{
    global $passed, $failed;
    if ($actual === null) {
        $passed++;
    } else {
        $failed++;
        $actualStr = var_export($actual, true);
        echo "  FAIL [{$label}]: expected null, got {$actualStr}\n";
    }
}

// =============================================
// Exact match tests
// =============================================
assert_eq('Studio', StudioWorkspaceRouteMapper::resolveWorkspaceKey('/apps/studio'), 'exact: /apps/studio');
assert_eq('Studio', StudioWorkspaceRouteMapper::resolveWorkspaceKey('/apps/studio/'), 'exact: /apps/studio/ with trailing slash');

assert_eq(
    'Studio/tools/CustomizationStudio',
    StudioWorkspaceRouteMapper::resolveWorkspaceKey('/apps/studio/tools/customization-studio'),
    'exact: /apps/studio/tools/customization-studio'
);
assert_eq(
    'Studio/tools/LocalizationStudio',
    StudioWorkspaceRouteMapper::resolveWorkspaceKey('/apps/studio/tools/localization-studio'),
    'exact: /apps/studio/tools/localization-studio'
);
assert_eq(
    'Studio/tools/LabelDesigner',
    StudioWorkspaceRouteMapper::resolveWorkspaceKey('/apps/studio/tools/label-designer'),
    'exact: /apps/studio/tools/label-designer'
);
assert_eq(
    'Studio/tools/OwnerStructureScan',
    StudioWorkspaceRouteMapper::resolveWorkspaceKey('/apps/studio/tools/owner-structure-scan'),
    'exact: owner-structure-scan registered tool'
);
assert_eq(
    'Studio/tools/OwnerStructureScan',
    StudioWorkspaceRouteMapper::resolveWorkspaceKey('/apps/studio/tools/owner-structure-scan/initialize-workspace-artifacts'),
    'prefix: owner-structure-scan child route'
);
assert_eq(
    'Studio/tools/ReportDesigner',
    StudioWorkspaceRouteMapper::resolveWorkspaceKey('/apps/studio/tools/report-designer'),
    'exact: /apps/studio/tools/report-designer'
);

// =============================================
// Prefix match (child page) tests
// =============================================
assert_eq(
    'Studio/tools/CustomizationStudio',
    StudioWorkspaceRouteMapper::resolveWorkspaceKey('/apps/studio/tools/customization-studio/diagnose/style-compliance'),
    'prefix: customization-studio child page'
);
assert_eq(
    'Studio/tools/CustomizationStudio',
    StudioWorkspaceRouteMapper::resolveWorkspaceKey('/apps/studio/tools/customization-studio/design-system/tokens'),
    'prefix: customization-studio deep child'
);
assert_eq(
    'Studio/tools/LocalizationStudio',
    StudioWorkspaceRouteMapper::resolveWorkspaceKey('/apps/studio/tools/localization-studio/editor'),
    'prefix: localization-studio child'
);
assert_eq(
    'Studio/tools/LabelDesigner',
    StudioWorkspaceRouteMapper::resolveWorkspaceKey('/apps/studio/tools/label-designer/preview/render'),
    'prefix: label-designer child'
);
assert_eq(
    'Studio/tools/ReportDesigner',
    StudioWorkspaceRouteMapper::resolveWorkspaceKey('/apps/studio/tools/report-designer/reports/new'),
    'prefix: report-designer child'
);

// =============================================
// Unknown / unmapped tests
// =============================================
assert_null(
    StudioWorkspaceRouteMapper::resolveWorkspaceKey('/apps/studio/legacy'),
    'unknown: /apps/studio/legacy (unmapped child)'
);
assert_null(
    StudioWorkspaceRouteMapper::resolveWorkspaceKey('/apps/studio/tools/css-token-editor'),
    'unknown: css-token-editor (no workspace)'
);
assert_null(
    StudioWorkspaceRouteMapper::resolveWorkspaceKey('/apps/studio/tools/theme-tool'),
    'unknown: theme-tool (no workspace)'
);
assert_eq(
    'Studio/tools/LocalizationScanExtraction',
    StudioWorkspaceRouteMapper::resolveWorkspaceKey('/apps/studio/tools/localization-scan-extraction'),
    'exact: localization-scan-extraction registered tool'
);
assert_eq(
    'Studio/tools/HelperTool',
    StudioWorkspaceRouteMapper::resolveWorkspaceKey('/apps/studio/tools/helper-tool'),
    'exact: helper-tool registered tool'
);
assert_eq(
    'Studio/tools/HelperTool',
    StudioWorkspaceRouteMapper::resolveWorkspaceKey('/apps/studio/tools/helper-tool/inspect-file'),
    'prefix: helper-tool inspector child route'
);
assert_null(
    StudioWorkspaceRouteMapper::resolveWorkspaceKey('/apps/studio/tools/theme-aware-repair'),
    'unknown: theme-aware-repair (no workspace)'
);
assert_null(
    StudioWorkspaceRouteMapper::resolveWorkspaceKey('/apps/studio/tools/engineering-workspaces'),
    'unknown: engineering-workspaces hub has no Developer Strip workspace'
);
assert_null(
    StudioWorkspaceRouteMapper::resolveWorkspaceKey('/apps/manufacturing/products'),
    'unmapped: manufacturing/products (not a Studio route)'
);
assert_null(
    StudioWorkspaceRouteMapper::resolveWorkspaceKey('/u/lazydeepak/dashboard'),
    'unmapped: /u/* operator route'
);
assert_null(
    StudioWorkspaceRouteMapper::resolveWorkspaceKey('/admin/lazydeepak'),
    'unmapped: /admin/* route'
);
assert_null(
    StudioWorkspaceRouteMapper::resolveWorkspaceKey(''),
    'unmapped: empty path'
);
assert_null(
    StudioWorkspaceRouteMapper::resolveWorkspaceKey('/'),
    'unmapped: root path'
);

// =============================================
// Summary
// =============================================
echo "StudioWorkspaceRouteMapper: {$passed} passed, {$failed} failed\n";

if ($failed > 0) {
    echo "SOME TESTS FAILED\n";
    exit(1);
}

echo "ALL TESTS PASSED\n";
exit(0);
