<?php
declare(strict_types=1);

/**
 * SEC-AUTH-001 authorized Core regression probe.
 *
 * In-memory only: role permissions and retained role denials are injected by
 * reflection. No user-context resolver, database, or live account is used.
 */

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

$policy = new ReflectionClass(App\Core\AclPolicy::class);
$rolePermissions = $policy->getProperty('effectiveRolePermissions');
$roleDenials = $policy->getProperty('effectiveRoleDenials');
$compose = $policy->getMethod('effectivePermissionsForContext');
$role = 'synthetic_acl_role';
$deniedPermission = 'workflow.production_plan.approve';
$unrelatedPermission = 'ops.my_work.view';
$rolePermissions->setValue(null, [
    $role => [$deniedPermission => true, $unrelatedPermission => true],
]);
$roleDenials->setValue(null, [$role => [$deniedPermission => true]]);

$assertions = 0;
$failures = [];
$assert = static function (bool $condition, string $label) use (&$assertions, &$failures): void {
    $assertions++;
    if (!$condition) {
        $failures[] = $label;
    }
};
$evaluate = static function (array $context) use ($compose): array {
    return (array)$compose->invoke(null, $context, ['role' => '']);
};
$base = ['authority_role' => $role, 'legacy_role' => ''];

$allowed = $evaluate($base);
$assert(isset($allowed[$unrelatedPermission]), 'role baseline still permits unrelated permission');
$assert(!isset($allowed['default.deny.permission']), 'unknown permission remains default-denied');

$profile = $evaluate($base + ['access_profiles' => ['manufacturing_approval']]);
$assert(!isset($profile[$deniedPermission]), 'role deny dominates access-profile grant');

$explicit = $evaluate($base + ['permissions' => [$deniedPermission]]);
$assert(!isset($explicit[$deniedPermission]), 'role deny dominates explicit permission grant');

$assigned = $evaluate($base + ['assigned_apps' => ['manufacturing']]);
$assert(!isset($assigned[$deniedPermission]), 'role deny dominates assigned-app grant');

$mixed = $evaluate($base + [
    'access_profiles' => ['manufacturing_approval'],
    'permissions' => [$deniedPermission],
    'assigned_apps' => ['manufacturing'],
]);
$assert(!isset($mixed[$deniedPermission]), 'role deny dominates simultaneous grant sources');
$assert(isset($mixed[$unrelatedPermission]), 'unrelated allowed permission survives simultaneous grants');

$otherRole = $evaluate(['authority_role' => 'other_synthetic_role', 'legacy_role' => '']);
$assert(!isset($otherRole[$deniedPermission]), 'unrelated role retains default-deny behavior');

$display = $evaluate(array_merge($base, [
    'authority_role' => 'tv_display',
    'assigned_apps' => ['manufacturing'],
    'permissions' => [$deniedPermission],
]));
$assert($display === [], 'tv_display remains non-interactive');

$source = (string)file_get_contents($root . '/app/Core/AclPolicy.php');
$assert(str_contains($source, 'effectiveRoleDenials'), 'Core retains a separate role-denial cache');
$assert(str_contains($source, 'unset($granted[$permission])'), 'retained denies are applied after additive grants');
$assert(str_contains($source, 'self::$effectiveRoleDenials = null'), 'cache invalidation clears retained denies');

echo json_encode([
    'probe' => 'SEC-AUTH-001-regression',
    'mode' => 'in_memory_reflection_only',
    'database_connected' => false,
    'database_mutated' => false,
    'assertions' => $assertions,
    'failures' => $failures,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
exit($failures === [] ? 0 : 1);
