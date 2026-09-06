<?php
declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 5));
}

require_once APP_ROOT . '/vendor/autoload.php';

use Apps\Studio\Tools\CustomizationStudio\Services\CustomizationStudioCapabilityInventory;

$assert = static function (bool $cond, string $label): void {
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    echo "  ok: {$label}\n";
};

$assertEq = static function (mixed $expected, mixed $actual, string $label): void {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$label}: expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n");
        exit(1);
    }
    echo "  ok: {$label}\n";
};

echo "=== CustomizationStudio Capability Preparation Probe ===\n\n";

// --- 1. Socket catalog path ---
echo "--- 1. Socket catalog path ---\n";

$newPath = APP_ROOT . '/apps/Shell/DesignSystem/Resources/socket-catalog';
$oldPath = APP_ROOT . '/apps/Shell/Style/Resources/socket-catalog';

$assert(is_dir($newPath), 'New DesignSystem socket catalog directory exists: ' . $newPath);
$assert(!is_dir($oldPath), 'Old Style socket catalog directory does not exist: ' . $oldPath);

$expectedFiles = [
    'accessibility.json', 'core-tokens.json', 'data-display.json',
    'diagrams-graphs.json', 'feedback.json', 'forms-editors.json',
    'layout.json', 'media-assets.json', 'motion-transform.json',
    'navigation.json', 'overlays.json', 'primitives.json',
    'print-export.json', 'responsive.json', 'root-mode.json',
    'shell-chrome.json', 'states.json', 'tables-grids.json',
    'visualization.json', 'workflow-operations.json',
];

foreach ($expectedFiles as $f) {
    $path = $newPath . '/' . $f;
    $assert(is_file($path), "Socket catalog file exists: {$f}");
    $raw = file_get_contents($path);
    $assert(is_string($raw) && $raw !== '', "Socket catalog file readable: {$f}");
    $data = json_decode($raw, true);
    $assert(is_array($data), "Socket catalog valid JSON: {$f}");
}

echo "\n";

// --- 2. CssSelectorInspector manifest ---
echo "--- 2. CssSelectorInspector manifest ---\n";

$inspectorManifest = APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/CssSelectorInspector/manifest.php';
$assert(is_file($inspectorManifest), 'CssSelectorInspector manifest exists');

$manifest = require $inspectorManifest;
$assert(is_array($manifest), 'CssSelectorInspector manifest is valid array');
$assertEq(true, !empty($manifest['placeholder']), 'manifest.placeholder = true');
$assertEq(true, empty($manifest['can_modify']), 'manifest.can_modify = false');
$assertEq(true, empty($manifest['requires_approval']), 'manifest.requires_approval = false');
$assertEq(true, empty($manifest['writes_to_owner_artifact']), 'manifest.writes_to_owner_artifact = false');
$assertEq(true, empty($manifest['supports_diff']), 'manifest.supports_diff = false');
$assertEq(true, empty($manifest['supports_snapshot']), 'manifest.supports_snapshot = false');
$assertEq(true, empty($manifest['supports_rollback']), 'manifest.supports_rollback = false');

echo "\n";

// --- 3. Capability inventory ---
echo "--- 3. Capability inventory ---\n";

$inventory = CustomizationStudioCapabilityInventory::inventory();
$inventoryDiag = CustomizationStudioCapabilityInventory::inventoryWithDiagnostics();

$assert(count($inventory) > 0, 'Inventory returns tools: ' . count($inventory));

$expectedKeys = [
    'css_token_editor', 'css_live_editor', 'css_selector_tool',
    'style_compliance', 'theme_tool', 'token_impact_explorer',
    'visual_customizer', 'special_effects',
];

foreach ($expectedKeys as $k) {
    $assert(isset($inventory[$k]), "Inventory contains tool: {$k}");
    $assert(isset($inventoryDiag[$k]), "Inventory with diagnostics contains tool: {$k}");
}

// CSS Token Editor is the only mutating tool
$assertEq(true, $inventory['css_token_editor']['can_modify'], 'css_token_editor.can_modify = true');
$assertEq(false, $inventory['style_compliance']['can_modify'], 'style_compliance.can_modify = false');

// CssSelectorInspector is placeholder-only
$assertEq(true, $inventory['css_selector_tool']['is_placeholder'], 'css_selector_tool.is_placeholder = true');

echo "\n";

// --- 4. Runtime style flags remain disabled ---
echo "--- 4. Runtime style consumption flags disabled ---\n";

$runtimeFlagsToCheck = [
    APP_ROOT . '/platform/Style/ResolvedStyleConsumer.php' => ['isRuntimeConsumptionEnabled'],
    APP_ROOT . '/platform/Style/Consumption/StyleConsumptionSurface.php' => ['isRuntimeConsumptionEnabled'],
    APP_ROOT . '/platform/Style/Runtime/RuntimeStyleApplication.php' => ['isRuntimeApplicationEnabled'],
    APP_ROOT . '/platform/Style/ShellInsertion/ShellInsertion.php' => ['isShellInsertionEnabled'],
    APP_ROOT . '/platform/Style/Proof/RenderedAdminProof.php' => ['isProofEnabled'],
];

foreach ($runtimeFlagsToCheck as $path => $flags) {
    if (!is_file($path)) {
        echo "  skip: {$path} not found\n";
        continue;
    }

    $content = file_get_contents($path);
    $assert(is_string($content), "Readable: {$path}");

    foreach ($flags as $flagMethod) {
        $methodPos = strpos($content, "function {$flagMethod}(");
        $assert($methodPos !== false, "Method {$flagMethod}() exists in {$path}");

        $methodEnd = strpos($content, '{', $methodPos);
        $methodBodyStart = $methodEnd + 1;
        $braceDepth = 1;
        $i = $methodBodyStart;
        while ($braceDepth > 0 && $i < strlen($content)) {
            if ($content[$i] === '{') $braceDepth++;
            elseif ($content[$i] === '}') $braceDepth--;
            $i++;
        }
        $methodBody = substr($content, $methodBodyStart, $i - $methodBodyStart - 1);
        $methodBody = trim(preg_replace('/\s+/', ' ', $methodBody));

        $assert(
            !str_contains($methodBody, 'return true'),
            "{$flagMethod}() does not return true in {$path}"
        );
    }
}

echo "\n";

// --- 5. Landing page description ---
echo "--- 5. Landing page updated ---\n";

$landingPath = APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Views/landing.php';
$assert(is_file($landingPath), 'Landing page exists');

$landingContent = file_get_contents($landingPath);
$assert(is_string($landingContent), 'Landing page readable');

$assert(
    !str_contains($landingContent, 'Preview-only workspace for future theme'),
    'Landing page no longer says "Preview-only workspace for future theme"'
);

$assert(
    str_contains($landingContent, 'Gateway to CSS token editing'),
    'Landing page now says "Gateway to CSS token editing"'
);

echo "\n=== CustomizationStudio Capability Preparation Probe: ALL PASS ===\n";
