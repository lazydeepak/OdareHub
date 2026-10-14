<?php
declare(strict_types=1);

/**
 * Disposable regression probe for the read-only CSS publication contract.
 * It runs copied tools against a temporary fixture root; the repository's
 * published assets and sources are never used as mutation targets.
 */

$tmpBase = getenv('TMPDIR') ?: sys_get_temp_dir();
$root = rtrim($tmpBase, '/') . '/odarehub-css-readiness-' . bin2hex(random_bytes(5));
$pass = 0;
$fail = 0;

function checkProbe(bool $condition, string $label): void
{
    global $pass, $fail;
    if ($condition) {
        $pass++;
        echo "PASS: {$label}\n";
    } else {
        $fail++;
        echo "FAIL: {$label}\n";
    }
}

function runProbe(string $command): array
{
    $output = [];
    exec($command . ' 2>&1', $output, $exitCode);
    return [$exitCode, implode("\n", $output)];
}

function writeProbe(string $path, string $contents): void
{
    $directory = dirname($path);
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }
    file_put_contents($path, $contents);
}

try {
    mkdir($root . '/scripts/assets', 0775, true);
    mkdir($root . '/apps/Foo', 0775, true);
    mkdir($root . '/apps/Shell/Resources/css', 0775, true);
    mkdir($root . '/public/assets/apps/foo/styles', 0775, true);
    mkdir($root . '/public/assets/system', 0775, true);

    copy(__DIR__ . '/../publish_registered_css.php', $root . '/scripts/assets/publish_registered_css.php');
    copy(__DIR__ . '/../first_boot_css_compiler.php', $root . '/scripts/assets/first_boot_css_compiler.php');
    copy(__DIR__ . '/../theme_source_fingerprint.php', $root . '/scripts/assets/theme_source_fingerprint.php');
    copy(__DIR__ . '/../compile_first_boot_css.php', $root . '/scripts/assets/compile_first_boot_css.php');

    writeProbe($root . '/apps/Foo/manifest.json', json_encode([
        'app_key' => 'Foo',
        'styles' => [['key' => 'foo', 'path' => 'styles.css', 'scope' => 'app']],
    ], JSON_PRETTY_PRINT));
    $ownerCss = '.fixture { color: red; }\n';
    writeProbe($root . '/apps/Foo/styles.css', $ownerCss);
    $publisher = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/scripts/assets/publish_registered_css.php');
    [$code, $output] = runProbe($publisher . ' --verify --json');
    checkProbe($code !== 0 && str_contains($output, 'missing or stale'), 'publisher rejects missing target');
    writeProbe($root . '/public/assets/apps/foo/styles/styles.css', $ownerCss);
    [$code] = runProbe($publisher . ' --verify --json');
    checkProbe($code === 0, 'publisher accepts current target');
    writeProbe($root . '/public/assets/apps/foo/styles/styles.css', '.fixture { color: blue; }\n');
    [$code] = runProbe($publisher . ' --verify --json');
    checkProbe($code !== 0, 'publisher rejects stale target');
    writeProbe($root . '/apps/Foo/styles.css', '.fixture { color: red;\n');
    [$code] = runProbe($publisher . ' --verify --json');
    checkProbe($code !== 0, 'publisher rejects structurally invalid CSS');

    $firstBootManifest = json_encode([
        'schema' => 'fixture',
        'assets' => [['target' => 'public/assets/system/fixture.css', 'sources' => ['apps/Shell/Resources/css/fixture.css']]],
    ], JSON_PRETTY_PRINT);
    writeProbe($root . '/scripts/assets/first_boot_css_manifest.json', $firstBootManifest);
    $bootCss = '.boot { display: block; }\n';
    writeProbe($root . '/apps/Shell/Resources/css/fixture.css', $bootCss);
    $compiler = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/scripts/assets/compile_first_boot_css.php');
    [$code] = runProbe($compiler . ' --verify --json');
    checkProbe($code !== 0, 'first-boot verifier rejects missing target');
    writeProbe($root . '/public/assets/system/fixture.css', "/* GENERATED FILE: public/assets/system/fixture.css */\n/* Source manifest: scripts/assets/first_boot_css_manifest.json */\n\n/* source: apps/Shell/Resources/css/fixture.css */\n" . rtrim($bootCss) . "\n\n");
    [$code] = runProbe($compiler . ' --verify --json');
    checkProbe($code === 0, 'first-boot verifier accepts current target');
    writeProbe($root . '/apps/Shell/Resources/css/fixture.css', '.boot { display: none; }\n');
    [$code] = runProbe($compiler . ' --verify --json');
    checkProbe($code !== 0, 'first-boot verifier rejects stale target');
    writeProbe($root . '/apps/Shell/Resources/css/fixture.css', '.boot { display: block;\n');
    [$code] = runProbe($compiler . ' --verify --json');
    checkProbe($code !== 0, 'first-boot verifier rejects invalid CSS');

    $before = hashDirectory($root);
    [$code] = runProbe($publisher . ' --verify --json');
    $after = hashDirectory($root);
    checkProbe($before === $after, 'verify mode leaves fixture files unchanged');
    checkProbe($code !== 0, 'invalid fixture remains a verification failure');
} finally {
    removeProbeTree($root);
}

echo "CSS publication readiness probe: {$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);

function hashDirectory(string $root): string
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $relative = substr($file->getPathname(), strlen($root) + 1);
            $files[$relative] = hash_file('sha256', $file->getPathname());
        }
    }
    ksort($files);
    return hash('sha256', json_encode($files));
}

function removeProbeTree(string $root): void
{
    if (!is_dir($root)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($root);
}
