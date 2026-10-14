<?php
declare(strict_types=1);

/**
 * OH-AUTO1-023 SEC-AUTH-001 reproduction.
 *
 * This is an in-memory reflection probe. It injects a synthetic empty role
 * baseline into the existing Core evaluator, invokes the private composition
 * stage, and never calls AclPolicy::can() or UserDashboardAssignmentService's
 * database-backed context resolver. It does not modify app/Core.
 */

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'Plugins\\Base\\Services\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = $root . '/plugins/Base/Services/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

$assertions = 0;
$failures = [];
$assert = static function (bool $condition, string $label) use (&$assertions, &$failures): void {
    $assertions++;
    if (!$condition) {
        $failures[] = $label;
    }
};

$policy = new ReflectionClass(App\Core\AclPolicy::class);
$roleProperty = $policy->getProperty('effectiveRolePermissions');
$roleProperty->setValue(null, [
    'synthetic_empty_role' => [],
]);

$compose = $policy->getMethod('effectivePermissionsForContext');
$permission = 'acl.manage';
$workflowPermission = 'workflow.production_plan.approve';

$evaluate = static function (array $context) use ($compose): array {
    return (array)$compose->invoke(null, $context, ['role' => '']);
};

$baseline = $evaluate(['authority_role' => 'synthetic_empty_role', 'legacy_role' => '']);
$assert(!isset($baseline[$permission]), 'empty synthetic role baseline does not grant acl.manage');

$profile = $evaluate([
    'authority_role' => 'synthetic_empty_role',
    'legacy_role' => '',
    'access_profiles' => ['manufacturing_approval'],
]);
$assert(isset($profile[$workflowPermission]), 'manufacturing_approval access profile re-adds workflow.production_plan.approve');

$explicit = $evaluate([
    'authority_role' => 'synthetic_empty_role',
    'legacy_role' => '',
    'permissions' => [$permission],
]);
$assert(isset($explicit[$permission]), 'explicit user permission re-adds acl.manage');

$assignedApp = $evaluate([
    'authority_role' => 'synthetic_empty_role',
    'legacy_role' => '',
    'assigned_apps' => ['manufacturing'],
]);
$assert(isset($assignedApp[$workflowPermission]), 'manufacturing assigned app adds workflow.production_plan.approve');

$mixed = $evaluate([
    'authority_role' => 'synthetic_empty_role',
    'legacy_role' => '',
    'access_profiles' => ['manufacturing_approval'],
    'permissions' => [$permission],
    'assigned_apps' => ['manufacturing'],
]);
$assert(isset($mixed[$permission]) && isset($mixed[$workflowPermission]), 'combined later grants retain denied permissions');

$source = (string)file_get_contents($root . '/app/Core/AclPolicy.php');
$assert(str_contains($source, "if (\$state === 'deny')"), 'Core role override path contains deny handling');
$assert(str_contains($source, "foreach (self::accessProfileMatrix()"), 'access-profile additive path is present');
$assert(str_contains($source, 'foreach ((array)($ctx[\'permissions\'] ?? [])'), 'explicit permission additive path is present');
$assert(str_contains($source, 'foreach (self::APP_PERMISSION_GRANTS'), 'assigned-app additive path is present');
$assert(str_contains($source, 'custom_deny overrides applied via the ACL matrix take runtime effect'), 'documented deny contract is present');

echo json_encode([
    'probe' => 'SEC-AUTH-001',
    'mode' => 'in_memory_reflection_only',
    'database_connected' => false,
    'database_mutated' => false,
    'core_modified' => false,
    'assertions' => $assertions,
    'failures' => $failures,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
exit($failures === [] ? 0 : 1);
