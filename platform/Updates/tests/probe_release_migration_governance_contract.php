<?php
declare(strict_types=1);

/**
 * OH-AUTO1-059 pure contract fixtures.
 *
 * This deliberately models proposed release evidence in memory. It does not
 * load application bootstrap, connect to a database, execute SQL, inspect an
 * installed schema, or run migrations.
 */

$passed = 0;
$failed = 0;

function rel003_assert(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }
    $failed++;
    fwrite(STDERR, "FAIL: {$label}\n");
}

/** @param array<int,array<string,mixed>> $inventory @param array<int,array<string,mixed>> $installed */
function rel003_plan(array $inventory, array $installed, string $currentSchema, string $targetSchema, string $currentVersion, string $releaseVersion): array
{
    $errors = [];
    $blockers = [];
    $byId = [];
    foreach ($inventory as $item) {
        $id = (string)($item['id'] ?? '');
        if ($id === '' || isset($byId[$id])) {
            $errors[] = 'duplicate_migration_identity';
            continue;
        }
        $byId[$id] = $item;
    }

    if (version_compare($releaseVersion, $currentVersion, '<')) {
        $errors[] = 'unsupported_downgrade';
    }
    if ($currentSchema === '' || $targetSchema === '') {
        $errors[] = 'schema_identity_missing';
    }
    $installedById = [];
    foreach ($installed as $row) {
        $id = (string)($row['id'] ?? '');
        if ($id === '' || isset($installedById[$id])) {
            $errors[] = 'duplicate_installed_migration_identity';
            continue;
        }
        $installedById[$id] = $row;
        if (!isset($byId[$id])) {
            $errors[] = 'unknown_installed_migration';
        }
        if (($row['status'] ?? '') !== 'applied') {
            $errors[] = 'migration_recovery_required';
        }
        if (isset($byId[$id]) && (string)($row['checksum'] ?? '') !== (string)($byId[$id]['checksum'] ?? '')) {
            $errors[] = 'migration_checksum_mismatch';
        }
    }

    foreach ($byId as $id => $item) {
        if (($item['owner_status'] ?? 'enabled') === 'disabled' && !isset($installedById[$id])) {
            $blockers[] = 'disabled_owner_requires_migration';
        }
        foreach ((array)($item['requires'] ?? []) as $dependency) {
            $dependency = (string)$dependency;
            if (!isset($byId[$dependency]) && !isset($installedById[$dependency])) {
                $errors[] = 'migration_dependency_missing';
            }
        }
    }

    $unapplied = [];
    foreach ($byId as $id => $_item) {
        if (!isset($installedById[$id])) {
            $unapplied[] = $id;
        }
    }

    $incoming = [];
    foreach ($byId as $id => $item) {
        $incoming[$id] = 0;
        foreach ((array)($item['requires'] ?? []) as $dependency) {
            if (isset($byId[(string)$dependency])) {
                $incoming[$id]++;
            }
        }
    }
    $order = [];
    while ($incoming !== []) {
        $ready = [];
        foreach ($incoming as $id => $count) {
            if ($count === 0) {
                $ready[] = $id;
            }
        }
        sort($ready);
        if ($ready === []) {
            $errors[] = 'migration_dependency_cycle';
            break;
        }
        foreach ($ready as $id) {
            $order[] = $id;
            unset($incoming[$id]);
            foreach ($byId as $dependentId => $item) {
                if (isset($incoming[$dependentId]) && in_array($id, (array)($item['requires'] ?? []), true)) {
                    $incoming[$dependentId]--;
                }
            }
        }
    }

    $uniqueErrors = array_values(array_unique($errors));
    return [
        'state' => $uniqueErrors === [] && $blockers === [] ? ($unapplied === [] ? 'compatible_upgrade' : 'missing_migration') : 'blocked',
        'errors' => $uniqueErrors,
        'blockers' => array_values(array_unique($blockers)),
        'order' => $order,
        'unapplied' => $unapplied,
    ];
}

/**
 * Model the current whole-owner runner: one call consumes every file for one
 * owner in lexical order and cannot select a single plan step.
 *
 * @param array<int,array<string,mixed>> $inventory
 * @param array<int,string> $ownerOrder
 * @return array{ok:bool,applied:array<int,string>,blocked:?string}
 */
function rel003_owner_batches(array $inventory, array $ownerOrder): array
{
    $applied = [];
    foreach ($ownerOrder as $owner) {
        $files = array_values(array_filter($inventory, static function (array $item) use ($owner): bool {
            return (string)($item['owner'] ?? '') === $owner;
        }));
        usort($files, static fn (array $left, array $right): int => strcmp((string)$left['id'], (string)$right['id']));
        foreach ($files as $item) {
            foreach ((array)($item['requires'] ?? []) as $dependency) {
                if (!in_array((string)$dependency, $applied, true)) {
                    return ['ok' => false, 'applied' => $applied, 'blocked' => (string)$item['id']];
                }
            }
            $applied[] = (string)$item['id'];
        }
    }
    return ['ok' => true, 'applied' => $applied, 'blocked' => null];
}

$baseInventory = [
    ['id' => 'app:platform:001_core.sql', 'checksum' => 'aaa', 'requires' => []],
    ['id' => 'app:orders:001_orders.sql', 'checksum' => 'bbb', 'requires' => ['app:platform:001_core.sql']],
    ['id' => 'module:orders/Orders:002_index.sql', 'checksum' => 'ccc', 'requires' => ['app:orders:001_orders.sql']],
];
$baseInstalled = [
    ['id' => 'app:platform:001_core.sql', 'checksum' => 'aaa', 'status' => 'applied'],
];

$canonicalIdentityPattern = '/^(app|module):[^:]+:[^:]+\.sql$/';
rel003_assert(
    count(array_filter($baseInventory, static fn (array $item): bool => preg_match($canonicalIdentityPattern, (string)$item['id']) === 1)) === count($baseInventory),
    'fixture migration identifiers follow the canonical owner-type/owner-key/source-name grammar'
);

$compatible = rel003_plan($baseInventory, $baseInstalled, 'schema:1', 'schema:2', '1.0.0', '1.1.0');
rel003_assert($compatible['state'] === 'missing_migration', 'compatible upgrade produces a planned migration state');
rel003_assert($compatible['order'] === ['app:platform:001_core.sql', 'app:orders:001_orders.sql', 'module:orders/Orders:002_index.sql'], 'dependency order is deterministic');

$stable = rel003_plan(array_reverse($baseInventory), $baseInstalled, 'schema:1', 'schema:2', '1.0.0', '1.1.0');
rel003_assert($stable['order'] === $compatible['order'], 'independent/input-order variation does not change graph order');

$allApplied = rel003_plan($baseInventory, [
    ['id' => 'app:platform:001_core.sql', 'checksum' => 'aaa', 'status' => 'applied'],
    ['id' => 'app:orders:001_orders.sql', 'checksum' => 'bbb', 'status' => 'applied'],
    ['id' => 'module:orders/Orders:002_index.sql', 'checksum' => 'ccc', 'status' => 'applied'],
], 'schema:2', 'schema:2', '1.0.0', '1.1.0');
rel003_assert($allApplied['state'] === 'compatible_upgrade', 'fully applied matching inventory is compatible');

$unknown = rel003_plan($baseInventory, [['id' => 'app:legacy:001_old.sql', 'checksum' => 'old', 'status' => 'applied']], 'schema:1', 'schema:2', '1.0.0', '1.1.0');
rel003_assert(in_array('unknown_installed_migration', $unknown['errors'], true), 'unknown installed migration blocks');

$checksum = rel003_plan($baseInventory, [['id' => 'app:platform:001_core.sql', 'checksum' => 'changed', 'status' => 'applied']], 'schema:1', 'schema:2', '1.0.0', '1.1.0');
rel003_assert(in_array('migration_checksum_mismatch', $checksum['errors'], true), 'migration checksum mismatch blocks');

$cycle = rel003_plan([
    ['id' => 'app:a:001.sql', 'checksum' => 'a', 'requires' => ['app:b:001.sql']],
    ['id' => 'app:b:001.sql', 'checksum' => 'b', 'requires' => ['app:a:001.sql']],
], [], 'schema:1', 'schema:2', '1.0.0', '1.1.0');
rel003_assert(in_array('migration_dependency_cycle', $cycle['errors'], true), 'dependency cycle blocks');

$duplicate = rel003_plan([$baseInventory[0], $baseInventory[0]], [], 'schema:1', 'schema:2', '1.0.0', '1.1.0');
rel003_assert(in_array('duplicate_migration_identity', $duplicate['errors'], true), 'duplicate migration identity blocks');

$missingDependency = rel003_plan([
    ['id' => 'app:a:001.sql', 'checksum' => 'a', 'requires' => ['app:missing:001.sql']],
], [], 'schema:1', 'schema:2', '1.0.0', '1.1.0');
rel003_assert(in_array('migration_dependency_missing', $missingDependency['errors'], true), 'missing dependency blocks');

$downgrade = rel003_plan($baseInventory, [], 'schema:2', 'schema:1', '2.0.0', '1.1.0');
rel003_assert(in_array('unsupported_downgrade', $downgrade['errors'], true), 'downgrade is rejected');

$partial = rel003_plan($baseInventory, [['id' => 'app:platform:001_core.sql', 'checksum' => 'aaa', 'status' => 'failed']], 'schema:1', 'schema:2', '1.0.0', '1.1.0');
rel003_assert(in_array('migration_recovery_required', $partial['errors'], true), 'failed or partial migration evidence blocks');

$disabled = rel003_plan([
    ['id' => 'app:optional:001.sql', 'checksum' => 'opt', 'requires' => [], 'owner_status' => 'disabled'],
], [], 'schema:1', 'schema:2', '1.0.0', '1.1.0');
rel003_assert(in_array('disabled_owner_requires_migration', $disabled['blockers'], true), 'disabled owner does not silently activate');

$missingSchema = rel003_plan($baseInventory, [], '', 'schema:2', '1.0.0', '1.1.0');
rel003_assert(in_array('schema_identity_missing', $missingSchema['errors'], true), 'missing target/source schema identity blocks');

$interleaved = [
    ['id' => 'app:owner-a:001_a.sql', 'owner' => 'owner-a', 'requires' => []],
    ['id' => 'app:owner-a:002_a.sql', 'owner' => 'owner-a', 'requires' => ['app:owner-b:001_b.sql']],
    ['id' => 'app:owner-b:001_b.sql', 'owner' => 'owner-b', 'requires' => ['app:owner-a:001_a.sql']],
];
$ownerAFirst = rel003_owner_batches($interleaved, ['owner-a', 'owner-b']);
$ownerBFirst = rel003_owner_batches($interleaved, ['owner-b', 'owner-a']);
rel003_assert($ownerAFirst['ok'] === false && $ownerAFirst['blocked'] === 'app:owner-a:002_a.sql', 'owner A batch cannot skip its dependent second file');
rel003_assert($ownerBFirst['ok'] === false && $ownerBFirst['blocked'] === 'app:owner-b:001_b.sql', 'owner B batch cannot run before owner A prerequisite');
$exactApplied = [];
foreach (['app:owner-a:001_a.sql', 'app:owner-b:001_b.sql', 'app:owner-a:002_a.sql'] as $plannedId) {
    $plannedItem = array_values(array_filter($interleaved, static fn (array $item): bool => $item['id'] === $plannedId))[0] ?? null;
    $dependenciesSatisfied = is_array($plannedItem)
        && array_diff((array)($plannedItem['requires'] ?? []), $exactApplied) === [];
    if (!$dependenciesSatisfied) {
        break;
    }
    $exactApplied[] = $plannedId;
}
rel003_assert(count($exactApplied) === 3, 'release plan can represent exact interleaving independently of owner batches');

$failedRetryPolicy = static function (string $status): string {
    return $status === 'failed' ? 'migration_recovery_required' : 'eligible_for_step_check';
};
rel003_assert($failedRetryPolicy('failed') === 'migration_recovery_required', 'existing failed row is retained as a retry blocker');

echo "REL-003 migration governance fixture probe: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
