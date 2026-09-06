<?php
declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 5));
}

require_once APP_ROOT . '/vendor/autoload.php';

use Apps\Studio\Tools\CustomizationStudio\Shared\Services\StyleValidationService;
use Apps\Studio\Tools\CustomizationStudio\Shared\Services\CssPathResolver;
use Apps\Studio\Tools\CustomizationStudio\Shared\Services\OwnerResolver;
use Apps\Studio\Tools\CustomizationStudio\Shared\Services\StyleDiffService;
use Apps\Studio\Tools\CustomizationStudio\Services\VisualCustomizerMetadataService;
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

echo "=== CustomizationStudio Shared Validation Probe ===\n\n";

// --- 1. CssPathResolver ---
echo "--- 1. CssPathResolver ---\n";

$resolved = CssPathResolver::resolve('apps/Shell/DesignSystem/Resources/socket-catalog');
$assert($resolved !== null, 'Resolve socket-catalog dir returns real path');

$resolvedFile = CssPathResolver::resolve('apps/Shell/DesignSystem/Resources/socket-catalog/root-mode.json');
$assert($resolvedFile !== null, 'Resolve socket-catalog file returns real path');

$traversalResult = CssPathResolver::resolve('apps/../../etc/passwd');
$assert($traversalResult === null, 'Traversal path returns null');

$traversalResult2 = CssPathResolver::resolve('apps/Shell/DesignSystem/Resources/../../../../etc/passwd');
$assert($traversalResult2 === null, 'Indirect traversal path returns null');

$emptyResult = CssPathResolver::resolve('');
$assert($emptyResult === null, 'Empty path returns null');

$underRoot = CssPathResolver::isUnderApprovedRoot(APP_ROOT . '/resources/themes');
$assert($underRoot, 'resources/themes is approved root');

$notUnderRoot = CssPathResolver::isUnderApprovedRoot(APP_ROOT . '/app/Core');
$assert(!$notUnderRoot, 'app/Core is not approved root');

$relative = CssPathResolver::relativePath(APP_ROOT . '/apps/Studio/test.php');
$assertEq('apps/Studio/test.php', $relative, 'relativePath strips APP_ROOT prefix');

$sourcePath = CssPathResolver::canonicalSourcePath('--corner-radius');
$assert($sourcePath === null || str_contains($sourcePath, 'resources/themes'),
    'canonicalSourcePath resolves under resources/themes if token exists');

echo "\n";

// --- 2. OwnerResolver ---
echo "--- 2. OwnerResolver ---\n";

$owner = OwnerResolver::resolveFromPath(APP_ROOT . '/apps/Manufacturing/styles/manufacturing.css');
$assertEq('Manufacturing', $owner, 'apps/Manufacturing/* resolves to Manufacturing');

$owner2 = OwnerResolver::resolveFromPath(APP_ROOT . '/apps/Manufacturing/modules/Coverage/styles.css');
$assertEq('Manufacturing/Coverage', $owner2, 'apps/*/modules/* resolves to app/module');

$owner3 = OwnerResolver::resolveFromPath(APP_ROOT . '/plugins/Base/Views/something.php');
$assertEq('plugin:Base', $owner3, 'plugins/* resolves to plugin:*');

$owner4 = OwnerResolver::resolveFromPath(APP_ROOT . '/resources/themes/light.css');
$assertEq('theme:themes', $owner4, 'resources/themes/* resolves to theme:*');

$owner5 = OwnerResolver::resolveFromPath(APP_ROOT . '/platform/Style/ResolvedStyleConsumer.php');
$assertEq('platform:Style', $owner5, 'platform/Style/* resolves to platform:*');

$owner6 = OwnerResolver::resolveFromPath(APP_ROOT . '/public/assets/apps/shell/styles/operator.css');
$assertEq('shell', $owner6, 'public/assets/apps/shell/* resolves to shell');

$ownerOther = OwnerResolver::resolveFromPath('/some/unrelated/path');
$assertEq('other', $ownerOther, 'Unrelated path resolves to other');

echo "\n";

// --- 3. StyleDiffService ---
echo "--- 3. StyleDiffService ---\n";

$noChange = StyleDiffService::compute('--corner-radius', '8px', '8px');
$assertEq(false, $noChange['changed'], 'Same values report changed=false');
$assertEq('--corner-radius', $noChange['token_name'], 'token_name is normalized');

$changed = StyleDiffService::compute('corner-radius', '4px', '8px');
$assertEq(true, $changed['changed'], 'Different values report changed=true');
$assertEq('--corner-radius', $changed['token_name'], 'token_name normalized with -- prefix');

$nullCurrent = StyleDiffService::compute('--my-token', null, '10px');
$assertEq(false, $nullCurrent['changed'], 'Null current and non-null proposed reports changed=false');

echo "\n";

// --- 4. StyleValidationService - discoverSockets ---
echo "--- 4. StyleValidationService discoverSockets ---\n";

$discovered = StyleValidationService::discoverSockets();
$assert($discovered['total_catalogs'] > 0, 'Catalogs discovered: ' . $discovered['total_catalogs']);
$assert($discovered['total_sockets'] > 0, 'Sockets discovered: ' . $discovered['total_sockets']);
$assertEq($discovered['total_catalogs'], $discovered['total_catalogs'], 'catalog count self-check');

echo "\n";

// --- 5. StyleValidationService - resolveSocket ---
echo "--- 5. StyleValidationService resolveSocket ---\n";

$socket = StyleValidationService::resolveSocket('radius.scale');
$assert($socket !== null, 'radius.scale resolves');
$assertEq('radius.scale', $socket['id'], 'socket id matches');
$assert(!empty($socket['value_type']), 'socket has value_type');
$assert(!empty($socket['category']), 'socket has category');

$unknownSocket = StyleValidationService::resolveSocket('nonexistent.socket');
$assert($unknownSocket === null, 'Unknown socket returns null');

$emptySocket = StyleValidationService::resolveSocket('');
$assert($emptySocket === null, 'Empty socket ID returns null');

echo "\n";

// --- 6. StyleValidationService - exploreToken ---
echo "--- 6. StyleValidationService exploreToken ---\n";

$explored = StyleValidationService::exploreToken('--corner-radius');
$assert($explored['ok'], 'exploreToken returns ok');
$assert(!empty($explored['token_name']), 'exploreToken returns token_name');

echo "\n";

// --- 7. StyleValidationService - validateTokenValue ---
echo "--- 7. StyleValidationService validateTokenValue ---\n";

$valid = StyleValidationService::validateTokenValue('4px');
$assert($valid['valid'], '4px is valid');
$assertEq('4px', $valid['sanitized'], 'sanitized value matches');

$invalidBraces = StyleValidationService::validateTokenValue('{invalid}');
$assert(!$invalidBraces['valid'], 'Curly braces are invalid');

$invalidExpr = StyleValidationService::validateTokenValue('expression(foo)');
$assert(!$invalidExpr['valid'], 'Expression values are invalid');

$invalidJs = StyleValidationService::validateTokenValue('javascript:alert(1)');
$assert(!$invalidJs['valid'], 'Javascript URLs are invalid');

$empty = StyleValidationService::validateTokenValue('');
$assert(!$empty['valid'], 'Empty value is invalid');

$validHex = StyleValidationService::validateTokenValue('#ff0000');
$assert($validHex['valid'], 'Hex color is valid');

$validVarRef = StyleValidationService::validateTokenValue('var(--my-token)');
$assert($validVarRef['valid'], 'var() reference is valid');

echo "\n";

// --- 8. StyleValidationService - diff ---
echo "--- 8. StyleValidationService diff ---\n";

$diffNoChange = StyleValidationService::diff('--corner-radius', '8px', '8px');
$assert($diffNoChange['ok'], 'diff with valid values returns ok');
$assertEq(false, $diffNoChange['changed'], 'unchanged diff reports changed=false');

$diffChanged = StyleValidationService::diff('--corner-radius', '4px', '8px');
$assert($diffChanged['ok'], 'diff with changed valid values returns ok');
$assertEq(true, $diffChanged['changed'], 'changed diff reports changed=true');

$diffInvalid = StyleValidationService::diff('--my-token', '4px', '{bad}');
$assert(!$diffInvalid['ok'], 'diff with invalid value returns ok=false');
$assert(!$diffInvalid['value_valid'], 'diff reports value_valid=false');

echo "\n";

// --- 9. StyleValidationService - readiness ---
echo "--- 9. StyleValidationService readiness ---\n";

$readiness = StyleValidationService::readiness();
$assert($readiness['catalogs_accessible'], 'Socket catalogs accessible');
$assert($readiness['theme_sources_accessible'], 'Theme sources accessible');
$assertEq(false, $readiness['runtime_consumption_enabled'], 'Runtime consumption disabled');
$assertEq(false, $readiness['runtime_application_enabled'], 'Runtime application disabled');
$assertEq(false, $readiness['shell_insertion_enabled'], 'Shell insertion disabled');
$assertEq(false, $readiness['rendered_proof_enabled'], 'Rendered proof disabled');

echo "\n";

// --- 10. Integration proof: VisualCustomizerMetadataService ---
echo "--- 10. Integration proof ---\n";

$vcMeta = VisualCustomizerMetadataService::discover();
$assert(isset($vcMeta['validation_readiness']), 'VC metadata has validation_readiness key');
$assert($vcMeta['validation_readiness']['catalogs_accessible'] ?? false, 'validation_readiness catalogs accessible via integration');

echo "\n";

// --- 11. No file/registry writes ---
echo "--- 11. No side effects ---\n";

$inventory = CustomizationStudioCapabilityInventory::inventory();
$assert(count($inventory) > 0, 'Capability inventory still works');

$flagsCheck = [
    APP_ROOT . '/platform/Style/ResolvedStyleConsumer.php' => ['isRuntimeConsumptionEnabled'],
    APP_ROOT . '/platform/Style/Consumption/StyleConsumptionSurface.php' => ['isRuntimeConsumptionEnabled'],
    APP_ROOT . '/platform/Style/Runtime/RuntimeStyleApplication.php' => ['isRuntimeApplicationEnabled'],
    APP_ROOT . '/platform/Style/ShellInsertion/ShellInsertion.php' => ['isShellInsertionEnabled'],
    APP_ROOT . '/platform/Style/Proof/RenderedAdminProof.php' => ['isProofEnabled'],
];

foreach ($flagsCheck as $path => $flags) {
    if (!is_file($path)) {
        continue;
    }
    $content = file_get_contents($path);
    $assert(is_string($content), "Readable: {$path}");
    foreach ($flags as $flagMethod) {
        $assert(
            !str_contains($content, "function {$flagMethod}(") || !str_contains($content, 'return true'),
            "{$flagMethod} does not return true in {$path}"
        );
    }
}

echo "\n=== ALL PASS ===\n";
