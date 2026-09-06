<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$tempRoot = sys_get_temp_dir() . '/registry-probe-' . bin2hex(random_bytes(6));

if (!defined('APP_ROOT')) {
    define('APP_ROOT', $tempRoot);
}

require_once $root . '/apps/Platform/StyleRegistry/Contracts/ApprovedStyleRegistryContract.php';
require_once $root . '/apps/Platform/StyleRegistry/Services/ApprovedStyleRegistry.php';

use Apps\Platform\StyleRegistry\Services\ApprovedStyleRegistry;

function probe_assert_same($actual, $expected, string $message): void
{
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL: {$message}\n");
        fwrite(STDERR, "Expected: " . json_encode($expected, JSON_UNESCAPED_SLASHES) . "\n");
        fwrite(STDERR, "Actual: " . json_encode($actual, JSON_UNESCAPED_SLASHES) . "\n");
        exit(1);
    }

    echo "ok: {$message}\n";
}

function probe_assert_array_has(array $data, string $key, string $message): void
{
    if (!array_key_exists($key, $data)) {
        fwrite(STDERR, "FAIL: {$message}\n");
        fwrite(STDERR, "Expected key '{$key}' not found in data\n");
        exit(1);
    }
    echo "ok: {$message}\n";
}

function probe_remove_tree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $items = scandir($path);
    if (!is_array($items)) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $child = $path . DIRECTORY_SEPARATOR . $item;
        if (is_dir($child)) {
            probe_remove_tree($child);
        } else {
            @unlink($child);
        }
    }

    @rmdir($path);
}

$registry = new ApprovedStyleRegistry();

try {
    // 1. isWritable('radius.scale') returns true
    probe_assert_same($registry->isWritable('radius.scale'), true, 'isWritable radius.scale returns true');

    // 2. isWritable('unknown.socket') returns false
    probe_assert_same($registry->isWritable('unknown.socket'), false, 'isWritable unknown.socket returns false');
    probe_assert_same($registry->isWritable(''), false, 'isWritable empty string returns false');
    probe_assert_same($registry->isWritable('core.unknown'), false, 'isWritable core.unknown returns false');

    // 3. getValue('radius.scale') returns null before any write
    probe_assert_same($registry->getValue('radius.scale'), null, 'getValue radius.scale returns null before set');
    probe_assert_same($registry->getValue('unknown.socket'), null, 'getValue unknown.socket returns null');

    // 4. setValue('radius.scale', 'sharp', provenance) succeeds
    $result = $registry->setValue('radius.scale', 'sharp', [
        'request_id' => 'probe-req-01',
        'snapshot_id' => 'probe-snap-01',
        'applied_by_user_id' => 999,
    ]);
    probe_assert_same($result['ok'] ?? false, true, 'setValue radius.scale sharp succeeds');
    probe_assert_same($result['socket'] ?? '', 'radius.scale', 'setValue returns socket radius.scale');
    probe_assert_same($result['value'] ?? '', 'sharp', 'setValue returns value sharp');

    // 5. getValue('radius.scale') returns sharp after set
    probe_assert_same($registry->getValue('radius.scale'), 'sharp', 'getValue radius.scale returns sharp');

    // 6. setValue('radius.scale', 'round', provenance) succeeds
    $result = $registry->setValue('radius.scale', 'round', [
        'request_id' => 'probe-req-02',
        'snapshot_id' => 'probe-snap-02',
        'applied_by_user_id' => 999,
    ]);
    probe_assert_same($result['ok'] ?? false, true, 'setValue radius.scale round succeeds');

    // 7. getValue('radius.scale') returns round after overwrite
    probe_assert_same($registry->getValue('radius.scale'), 'round', 'getValue radius.scale returns round after overwrite');

    // 8. setValue('radius.scale', 'invalid') is rejected
    $result = $registry->setValue('radius.scale', 'invalid');
    probe_assert_same($result['ok'] ?? true, false, 'setValue radius.scale invalid is rejected');
    probe_assert_same($result['error'] ?? '', 'invalid_value', 'setValue invalid value error is invalid_value');
    probe_assert_same($registry->getValue('radius.scale'), 'round', 'getValue unchanged after rejected invalid write');

    // 9. setValue('unknown.socket', 'round') is rejected
    $result = $registry->setValue('unknown.socket', 'round');
    probe_assert_same($result['ok'] ?? true, false, 'setValue unknown.socket is rejected');
    probe_assert_same($result['error'] ?? '', 'unknown_socket', 'setValue unknown socket error is unknown_socket');

    // 10. setValue('', 'round') is rejected
    $result = $registry->setValue('', 'round');
    probe_assert_same($result['ok'] ?? true, false, 'setValue empty socket is rejected');
    probe_assert_same($result['error'] ?? '', 'missing_socket_id', 'setValue empty socket error is missing_socket_id');

    // 11. setValue('radius.scale', '') is rejected
    $result = $registry->setValue('radius.scale', '');
    probe_assert_same($result['ok'] ?? true, false, 'setValue empty value is rejected');
    probe_assert_same($result['error'] ?? '', 'missing_value', 'setValue empty value error is missing_value');

    // 12. Verify all allowed values work
    foreach (['sharp', 'soft', 'round'] as $value) {
        $r = $registry->setValue('radius.scale', $value, ['applied_by_user_id' => 999]);
        probe_assert_same($r['ok'] ?? false, true, "setValue radius.scale {$value} is allowed");
        probe_assert_same($registry->getValue('radius.scale'), $value, "getValue radius.scale returns {$value}");
    }

    // 13. Stored JSON includes required fields
    $storagePath = APP_ROOT . '/storage/platform/style-registry/approved-values/radius.scale.json';
    probe_assert_same(is_file($storagePath), true, 'storage file radius.scale.json exists');
    $raw = file_get_contents($storagePath);
    $decoded = json_decode(is_string($raw) ? $raw : '', true);
    probe_assert_same(is_array($decoded), true, 'storage file is valid JSON');
    probe_assert_array_has($decoded, 'socket_id', 'storage has socket_id');
    probe_assert_array_has($decoded, 'approved_value', 'storage has approved_value');
    probe_assert_array_has($decoded, 'previous_value', 'storage has previous_value');
    probe_assert_array_has($decoded, 'applied_at', 'storage has applied_at');
    probe_assert_array_has($decoded, 'provenance', 'storage has provenance');
    probe_assert_array_has($decoded['provenance'] ?? [], 'request_id', 'storage provenance has request_id');
    probe_assert_array_has($decoded['provenance'] ?? [], 'snapshot_id', 'storage provenance has snapshot_id');

    // 14. Contract integrity: isWritable matches allowlist
    probe_assert_same($registry->isWritable('radius.scale'), true, 'isWritable radius.scale true post-write');
    probe_assert_same($registry->isWritable('unknown.socket'), false, 'isWritable unknown.socket false post-write');

    // 15. Multiple reads are idempotent
    probe_assert_same($registry->getValue('radius.scale'), $registry->getValue('radius.scale'), 'getValue is idempotent');

    echo "\nRESULT: PASS Platform ApprovedStyleRegistry contract probe\n";
} finally {
    probe_remove_tree($tempRoot);
}
