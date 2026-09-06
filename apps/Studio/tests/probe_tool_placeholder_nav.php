<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('current_lang')) {
    function current_lang(): string
    {
        return 'en';
    }
}

$passes = 0;
$fails = 0;

$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    if ($condition) {
        $passes++;
        echo "PASS: {$message}\n";
        return;
    }
    $fails++;
    echo "FAIL: {$message}\n";
};

$renderPlaceholder = static function (string $requestUri): string {
    $_SERVER['REQUEST_URI'] = $requestUri;
    $toolModel = [
        'key' => 'probe',
        'display_name' => 'Probe Tool',
        'description' => 'Probe placeholder.',
        'status' => 'planned',
        'risk_level' => 'low',
    ];
    $studioToolLocale = [
        'studio.placeholder.eyebrow' => 'Probe',
        'studio.status.planned' => 'Planned',
        'studio.risk.low' => 'Low',
        'studio.placeholder.lifecycle_title' => 'Lifecycle',
        'studio.placeholder.lifecycle_description' => 'Lifecycle probe.',
        'studio.placeholder.read_only' => 'Read-only',
        'studio.operation.create' => 'Create',
        'studio.operation.edit' => 'Edit',
        'studio.operation.rename' => 'Rename',
        'studio.operation.disable' => 'Disable',
        'studio.operation.delete' => 'Delete',
        'studio.operation.restore' => 'Restore',
        'studio.placeholder.planned_action' => 'Planned',
        'studio.placeholder.not_supported' => 'Not supported',
        'studio.placeholder.safety_notice' => 'Safe.',
        'studio.placeholder.resources_title' => 'Resources',
        'studio.placeholder.resources_description' => 'Resources probe.',
        'studio.placeholder.governance_title' => 'Governance',
        'studio.placeholder.diff' => 'Diff',
        'studio.placeholder.approval' => 'Approval',
        'studio.placeholder.snapshot' => 'Snapshot',
        'studio.placeholder.rollback' => 'Rollback',
        'studio.common.yes' => 'Yes',
        'studio.common.no' => 'No',
        'studio.common.required' => 'Required',
        'studio.common.not_required' => 'Not required',
    ];

    ob_start();
    require APP_ROOT . '/apps/Studio/Views/pages/tool_placeholder.php';
    return (string)ob_get_clean();
};

$diagnosticRoutes = [
    '/apps/studio/tools/customization-studio/diagnose/style-compliance',
    '/apps/studio/tools/customization-studio/diagnose/theme-doctor',
    '/apps/studio/tools/customization-studio/diagnose/token-impact-explorer',
    '/apps/studio/tools/customization-studio/diagnose/css-selector-inspector',
];

foreach ($diagnosticRoutes as $diagnosticRoute) {
    $diagnosticHtml = $renderPlaceholder($diagnosticRoute);
    $assert(!str_contains($diagnosticHtml, 'Back to Parent Tool'), $diagnosticRoute . ' omits fake parent label');
    $assert(!str_contains($diagnosticHtml, 'href="/apps/studio/tools/customization-studio/diagnose"'), $diagnosticRoute . ' omits namespace parent href');
    $assert(!str_contains($diagnosticHtml, 'Back to Studio'), $diagnosticRoute . ' omits Back to Studio');
}

$validParentHtml = $renderPlaceholder('/apps/studio/tools/customization-studio/visual-customizer');
$assert(str_contains($validParentHtml, 'Back to Parent Tool'), 'valid parent tool still renders parent label');
$assert(str_contains($validParentHtml, 'href="/apps/studio/tools/customization-studio"'), 'valid parent tool still links to real parent page');

echo "\nResults: {$passes} passed, {$fails} failed\n";
exit($fails > 0 ? 1 : 0);
