<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/app/Core/helpers.php';
require_once APP_ROOT . '/platform/Security/EngineeringWorkspaceContentContract.php';
require_once APP_ROOT . '/platform/Security/EngineeringWorkspaceResolver.php';
require_once APP_ROOT . '/platform/Security/EngineeringWorkspacePageContextResolver.php';

use Platform\Security\EngineeringWorkspaceContentContract;
use Platform\Security\EngineeringWorkspacePageContextResolver;

$passed = 0;
$failed = 0;

function ewp_assert_eq(mixed $expected, mixed $actual, string $label): void
{
    global $passed, $failed;
    if ($expected === $actual) {
        $passed++;
        return;
    }

    $failed++;
    echo '  FAIL [' . $label . ']: expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . PHP_EOL;
}

function ewp_assert_true(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }

    $failed++;
    echo '  FAIL [' . $label . ']' . PHP_EOL;
}

function ewp_parent(string $workspaceKey): array
{
    return EngineeringWorkspacePageContextResolver::parentPageForWorkspace($workspaceKey);
}

function ewp_render_viewer(string $workspaceKey, string $documentType = 'overview'): string
{
    $documentLabel = EngineeringWorkspaceContentContract::documentLabel($documentType) ?? ucfirst($documentType);
    $engineeringWorkspaceView = [
        'workspace_key' => $workspaceKey,
        'document_type' => $documentType,
        'document_label' => $documentLabel,
        'document_tabs' => [
            'overview' => 'Overview',
            'work' => 'Work',
            'rules' => 'Rules',
            'decisions' => 'Decisions',
        ],
        'parent_page' => EngineeringWorkspacePageContextResolver::parentPageForWorkspace($workspaceKey),
        'expected_sections' => [],
        'exists' => true,
        'rendered_content' => '<h1>Probe</h1>',
        'raw_content' => '# Probe',
        'draft_content' => '# Probe',
        'fingerprint' => str_repeat('a', 64),
        'error' => '',
        'error_state' => '',
        'source_path' => 'engineering/' . $workspaceKey . '/' . $documentType . '.md',
        'mode' => 'view',
        'csrf' => 'probe',
        'save_ok' => false,
        'save_error' => '',
    ];

    ob_start();
    require APP_ROOT . '/apps/Studio/Views/engineering-workspace/viewer.php';
    return (string)ob_get_clean();
}

$helper = ewp_parent('Studio/tools/HelperTool');
ewp_assert_eq('Repository Scanner', $helper['label'] ?? '', 'Helper Tool parent label override');
ewp_assert_eq('/apps/studio/tools/helper-tool', $helper['url'] ?? '', 'Helper Tool parent URL');
ewp_assert_eq(true, (bool)($helper['resolved'] ?? false), 'Helper Tool parent resolved');

$products = ewp_parent('Manufacturing/Products');
ewp_assert_eq('Products', $products['label'] ?? '', 'Products parent label');
ewp_assert_eq('/apps/manufacturing/products', $products['url'] ?? '', 'Products parent URL');
ewp_assert_eq(true, (bool)($products['resolved'] ?? false), 'Products parent resolved');

$studio = ewp_parent('Studio');
ewp_assert_eq('Studio', $studio['label'] ?? '', 'Studio parent label');
ewp_assert_eq('/apps/studio', $studio['url'] ?? '', 'Studio parent URL');
ewp_assert_eq(true, (bool)($studio['resolved'] ?? false), 'Studio parent resolved');

$unknown = ewp_parent('Unknown/Workspace');
ewp_assert_eq('Engineering Workspaces', $unknown['label'] ?? '', 'Unknown parent falls back to hub label');
ewp_assert_eq('/apps/studio/tools/engineering-workspaces', $unknown['url'] ?? '', 'Unknown parent falls back to hub URL');
ewp_assert_eq(false, (bool)($unknown['resolved'] ?? true), 'Unknown parent is unresolved');

$helperHtml = ewp_render_viewer('Studio/tools/HelperTool');
ewp_assert_true(str_contains($helperHtml, 'href="/apps/studio/tools/helper-tool"'), 'Viewer links Helper Tool parent URL');
ewp_assert_true(str_contains($helperHtml, '&larr; Back to Repository Scanner'), 'Viewer labels Helper Tool parent link');
ewp_assert_true(!str_contains($helperHtml, 'Back to previous page'), 'Viewer does not render browser-history label');
ewp_assert_true(str_contains($helperHtml, 'document=overview'), 'Viewer preserves Overview tab');
ewp_assert_true(str_contains($helperHtml, 'document=work'), 'Viewer preserves Work tab');
ewp_assert_true(str_contains($helperHtml, 'document=rules'), 'Viewer preserves Rules tab');
ewp_assert_true(str_contains($helperHtml, 'document=decisions'), 'Viewer preserves Decisions tab');

$unknownHtml = ewp_render_viewer('Unknown/Workspace');
ewp_assert_true(str_contains($unknownHtml, 'href="/apps/studio/tools/engineering-workspaces"'), 'Viewer links hub for unresolved workspace');
ewp_assert_true(str_contains($unknownHtml, '&larr; Back to Engineering Workspaces'), 'Viewer labels unresolved parent fallback');

echo "EngineeringWorkspaceParentNavigation: {$passed} passed, {$failed} failed" . PHP_EOL;

if ($failed > 0) {
    exit(1);
}

exit(0);
