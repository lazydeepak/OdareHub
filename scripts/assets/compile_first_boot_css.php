<?php
declare(strict_types=1);

require_once __DIR__ . '/first_boot_css_compiler.php';

$root = dirname(__DIR__, 2);
$apply = in_array('--apply', $argv, true);
$json = in_array('--json', $argv, true);
$unknown = array_values(array_filter(
    array_slice($argv, 1),
    static fn (string $arg): bool => !in_array($arg, ['--apply', '--json'], true)
));

if ($unknown !== []) {
    $result = [
        'schema' => 'odarehub.first_boot_css_compile.v1',
        'mode' => $apply ? 'apply' : 'dry-run',
        'manifest' => FIRST_BOOT_MANIFEST,
        'ok' => false,
        'assets' => [],
        'errors' => ['unsupported_arguments:' . implode(',', $unknown)],
    ];
    emitResult($result, $json);
}

$result = compileFirstBootCssAssets($root, $apply);
emitResult($result, $json);

/** @param array<string,mixed> $result */
function emitResult(array $result, bool $json): never
{
    if ($json) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        echo "First-boot CSS compiler\n";
        echo "Mode: " . ($result['mode'] ?? 'unknown') . "\n";
        foreach (($result['assets'] ?? []) as $asset) {
            echo '[' . strtoupper((string)($asset['status'] ?? 'unknown')) . '] '
                . (string)($asset['target'] ?? '') . "\n";
        }
        foreach (($result['errors'] ?? []) as $error) {
            fwrite(STDERR, "ERROR: {$error}\n");
        }
    }

    exit(($result['ok'] ?? false) ? 0 : 1);
}
