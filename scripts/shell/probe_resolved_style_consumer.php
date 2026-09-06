<?php
declare(strict_types=1);

/**
 * Read-only probe for the inert Shell ResolvedStyleConsumer placeholder.
 *
 * Platform owns approved-style reads and resolution. Shell must not read the
 * registry directly or expose a runtime resolve API before the authorized
 * Platform consumption surface is implemented.
 */

$root = dirname(__DIR__, 2);
require_once $root . '/apps/Shell/DesignSystem/Services/ResolvedStyleConsumer.php';

use Apps\Shell\DesignSystem\Services\ResolvedStyleConsumer;

function probe_assert(mixed $actual, mixed $expected, string $message): void
{
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL: {$message}\n");
        fwrite(STDERR, '  Expected: ' . json_encode($expected, JSON_UNESCAPED_SLASHES) . "\n");
        fwrite(STDERR, '  Actual:   ' . json_encode($actual, JSON_UNESCAPED_SLASHES) . "\n");
        exit(1);
    }

    echo "  ok: {$message}\n";
}

echo "[probe] Shell ResolvedStyleConsumer placeholder\n";

$consumer = new ResolvedStyleConsumer();
$status = $consumer->status();
probe_assert($status['runtime_status'] ?? null, 'placeholder_only_not_consumed', 'runtime remains disabled');
probe_assert($status['owner'] ?? null, 'platform', 'approved-style resolution remains Platform-owned');

$consumerFile = $root . '/apps/Shell/DesignSystem/Services/ResolvedStyleConsumer.php';
$consumerContent = (string)file_get_contents($consumerFile);
$forbiddenPatterns = [
    'Apps\\Platform\\StyleRegistry' => 'Platform registry import',
    'ApprovedStyleRegistry' => 'registry implementation reference',
    'getValue(' => 'direct registry read',
    'readValue(' => 'direct reader call',
    'function probe(' => 'diagnostic registry probe API',
    'function resolve(' => 'runtime resolution API',
    'file_put_contents' => 'file write',
    '<style' => 'runtime style output',
];

foreach ($forbiddenPatterns as $pattern => $label) {
    probe_assert(str_contains($consumerContent, $pattern), false, "placeholder has no {$label}");
}

echo "RESULT: PASS Shell ResolvedStyleConsumer is inert\n";
