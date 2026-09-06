<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/app/Core/helpers.php';
require_once APP_ROOT . '/platform/Security/MarkdownRenderer.php';
require_once APP_ROOT . '/platform/Security/EngineeringWorkspaceContentContract.php';
require_once APP_ROOT . '/apps/Studio/Tools/EngineeringWorkspaces/Services/EngineeringWorkspaceHubService.php';
require_once APP_ROOT . '/apps/Studio/Tools/EngineeringWorkspaces/Services/EngineeringWorkspaceCoverageService.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureOwnerDiscoveryService.php';

use Apps\Studio\Tools\EngineeringWorkspaces\Services\EngineeringWorkspaceCoverageService;
use Apps\Studio\Tools\EngineeringWorkspaces\Services\EngineeringWorkspaceHubService;
use Platform\Security\EngineeringWorkspaceContentContract;

$passed = 0;
$failed = 0;

function ewh_assert_true(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }
    $failed++;
    echo '  FAIL [' . $label . ']' . PHP_EOL;
}

function ewh_assert_eq(mixed $expected, mixed $actual, string $label): void
{
    global $passed, $failed;
    if ($expected === $actual) {
        $passed++;
        return;
    }
    $failed++;
    echo '  FAIL [' . $label . ']: expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . PHP_EOL;
}

function ewh_render(array $model): string
{
    $engineeringWorkspaceHubModel = $model;
    ob_start();
    require APP_ROOT . '/apps/Studio/Tools/EngineeringWorkspaces/Views/preview.php';
    return (string)ob_get_clean();
}

$workspaceModel = EngineeringWorkspaceHubService::buildModel('workspaces');
$workspaceModel['coverage'] = EngineeringWorkspaceCoverageService::buildCoverage();
$templateModel = EngineeringWorkspaceHubService::buildModel('templates');

ewh_assert_eq('workspaces', $workspaceModel['active_tab'] ?? '', 'default tab is Workspaces');
ewh_assert_eq('templates', $templateModel['active_tab'] ?? '', 'templates tab is selected by explicit tab');

$supported = EngineeringWorkspaceContentContract::supportedWorkspaceKeys();
$workspaceRows = isset($workspaceModel['workspaces']) && is_array($workspaceModel['workspaces']) ? $workspaceModel['workspaces'] : [];
ewh_assert_eq(count($supported), count($workspaceRows), 'Hub uses registered workspace source only');

$workspaceKeys = array_map(static fn(array $row): string => (string)($row['workspace_key'] ?? ''), $workspaceRows);
sort($workspaceKeys);
$expectedKeys = $supported;
sort($expectedKeys);
ewh_assert_eq($expectedKeys, $workspaceKeys, 'Hub does not infer arbitrary engineering folders');

$studioRow = null;
foreach ($workspaceRows as $row) {
    if (($row['workspace_key'] ?? '') === 'Studio') {
        $studioRow = $row;
        break;
    }
}
ewh_assert_true(is_array($studioRow), 'Studio workspace row exists');
ewh_assert_eq('ready', (string)($studioRow['status'] ?? ''), 'Studio workspace is Ready');

$studioDocs = isset($studioRow['documents']) && is_array($studioRow['documents']) ? $studioRow['documents'] : [];
ewh_assert_eq(4, count($studioDocs), 'Studio row exposes four documents');
foreach ($studioDocs as $doc) {
    ewh_assert_eq('valid', (string)($doc['status'] ?? ''), 'Valid document status for ' . (string)($doc['document_key'] ?? 'document'));
    ewh_assert_true(str_starts_with((string)($doc['url'] ?? ''), '/apps/studio/engineering-workspaces?workspace_key=Studio&document='), 'Valid document links to parameterized viewer');
}

$templates = isset($templateModel['templates']) && is_array($templateModel['templates']) ? $templateModel['templates'] : [];
ewh_assert_eq(4, count($templates), 'Templates tab exposes four canonical templates');
foreach ($templates as $template) {
    ewh_assert_true(in_array((string)($template['filename'] ?? ''), ['overview.md', 'work.md', 'rules.md', 'decisions.md'], true), 'Canonical template filename');
    ewh_assert_eq('valid', (string)($template['source_status'] ?? ''), 'Template source is valid for ' . (string)($template['filename'] ?? 'template'));
    ewh_assert_true((string)($template['rendered_preview'] ?? '') !== '', 'Template has rendered read-only preview');
}

$workspaceHtml = ewh_render($workspaceModel);
$templateHtml = ewh_render($templateModel);
ewh_assert_true(str_contains($workspaceHtml, 'Engineering Workspace Coverage'), 'Coverage title renders');
ewh_assert_true(str_contains($workspaceHtml, 'Evaluate authoritative owner contexts, registered workspaces, and existing workspace artifacts'), 'Coverage description renders');
ewh_assert_true(str_contains($workspaceHtml, '/apps/studio/engineering-workspaces?workspace_key=Studio&amp;document=overview'), 'Rendered document link uses parameterized viewer');
ewh_assert_true(str_contains($workspaceHtml, 'Owner contexts scanned'), 'Summary shows Owner contexts scanned');
ewh_assert_true(str_contains($workspaceHtml, 'Exact workspace mappings'), 'Summary shows Exact workspace mappings');
ewh_assert_true(str_contains($workspaceHtml, 'Coverage decisions required'), 'Summary shows Coverage decisions required');
ewh_assert_true(str_contains($workspaceHtml, 'Linked valid'), 'Summary shows Linked valid');
ewh_assert_true(str_contains($workspaceHtml, 'Owner coverage'), 'Grouped summary section Owner coverage exists');
ewh_assert_true(str_contains($workspaceHtml, 'Workspace readiness'), 'Grouped summary section Workspace readiness exists');
ewh_assert_true(str_contains($workspaceHtml, 'Provisioning readiness'), 'Table column Provisioning readiness exists');
ewh_assert_true(str_contains($workspaceHtml, 'Preserve'), 'Row shows Preserve readiness');
ewh_assert_true(!str_contains($workspaceHtml, APP_ROOT), 'Workspace render does not disclose APP_ROOT');
ewh_assert_true(str_contains($templateHtml, 'Workspace Templates'), 'Templates title renders');
ewh_assert_true(str_contains($templateHtml, 'Inspect the approved starter templates used when a new Engineering Workspace document is initialized.'), 'Templates description renders');
ewh_assert_true(!str_contains($templateHtml, APP_ROOT), 'Template render does not disclose APP_ROOT');

// --- Sidebar navigation assertions ---
$navConfig = require APP_ROOT . '/apps/Studio/navigation.php';
$navItems = is_array($navConfig['items'] ?? null) ? $navConfig['items'] : [];
$ewVisible = [];
$ewHidden = [];
$ewLegacy = [];
foreach ($navItems as $item) {
    $url = (string)($item['url'] ?? '');
    if (str_contains($url, 'engineering-workspaces')) {
        if (!empty($item['nav_visible'])) {
            $ewVisible[] = $item;
        } else {
            $ewHidden[] = $item;
        }
        if ($url === '/apps/studio/engineering-workspaces') {
            $ewLegacy[] = $item;
        }
    }
}
ewh_assert_eq(1, count($ewVisible), 'Exactly one nav_visible Engineering Workspaces sidebar entry');
ewh_assert_eq('/apps/studio/tools/engineering-workspaces', (string)($ewVisible[0]['url'] ?? ''), 'Visible entry href is /apps/studio/tools/engineering-workspaces');
ewh_assert_eq('Engineering Workspaces', (string)($ewVisible[0]['label'] ?? ''), 'Visible entry label is Engineering Workspaces');
ewh_assert_true(count($ewHidden) >= 1, 'Hidden Engineering Workspace utility entries may exist');
ewh_assert_eq(1, count($ewLegacy), 'Legacy viewer entry exists with nav_visible=false');
ewh_assert_eq('/apps/studio/engineering-workspaces', (string)($ewLegacy[0]['url'] ?? ''), 'Hidden legacy entry href is /apps/studio/engineering-workspaces');
ewh_assert_true(empty($ewLegacy[0]['nav_visible']), 'Legacy viewer entry is not nav_visible');

// Verify no orphan Engineering Workspaces label without nav_visible
$orphanLabels = [];
foreach ($navItems as $item) {
    $label = (string)($item['label'] ?? '');
    $url = (string)($item['url'] ?? '');
    if (str_contains($label, 'Engineering Workspaces') && $url === '') {
        $orphanLabels[] = $item;
    }
}
ewh_assert_eq(0, count($orphanLabels), 'No orphan Engineering Workspaces label without url');

echo "EngineeringWorkspaceHub: {$passed} passed, {$failed} failed" . PHP_EOL;

if ($failed > 0) {
    exit(1);
}

exit(0);
