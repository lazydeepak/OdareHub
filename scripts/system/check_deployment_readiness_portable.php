<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

echo "[system] check_deployment_readiness_portable\n";

$bash = findExecutable(PHP_OS_FAMILY === 'Windows' ? ['bash.exe', 'bash'] : ['bash']);
if ($bash !== null) {
    echo "- bash detected; delegating to authoritative shell readiness orchestrator\n\n";
    passthru(escapeshellarg($bash) . ' scripts/system/check_deployment_readiness.sh', $exitCode);
    exit($exitCode);
}

echo "- bash unavailable; running portable local readiness subset\n";
echo "- shell-only diagnostics are reported as skipped in this mode\n";

$failures = 0;

runStep('PHP syntax', [
    [PHP_BINARY . ' -l scripts/assets/publish_registered_css.php', true],
    [PHP_BINARY . ' -l scripts/assets/compile_first_boot_css.php', true],
], $failures);

runStep('Publish registered CSS assets', [
    [PHP_BINARY . ' scripts/assets/publish_registered_css.php --apply', true],
], $failures);

runStep('Publish first-boot CSS assets', [
    [PHP_BINARY . ' scripts/assets/compile_first_boot_css.php --apply', true],
], $failures);

echo "\n== Shell-only diagnostics ==\n";
runSystemToolsInventory();
echo "";
runBackfillUtilityAging();
echo "";
runArchitectureGatesSummary();

$git = findExecutable(PHP_OS_FAMILY === 'Windows' ? ['git.exe', 'git'] : ['git']);
if ($git === null) {
    echo "\n== Git checks ==\n";
    echo "SKIP: git diff checks (git unavailable)\n";
} else {
    runStep('Diff hygiene', [
        [escapeshellarg($git) . ' diff --check', true],
    ], $failures);

    echo "\n== Generated asset cleanliness ==\n";
    exec(escapeshellarg($git) . ' diff --quiet -- public/assets/apps 2>&1', $output, $exitCode);
    if ($exitCode === 0) {
        echo "RESULT: PASS\n";
    } else {
        echo "RESULT: FAIL (generated public app assets changed during readiness)\n";
        passthru(escapeshellarg($git) . ' diff --name-only -- public/assets/apps');
        $failures++;
    }
}

echo "\n";
if ($failures === 0) {
    echo "DEPLOYMENT READINESS: PASS (portable-local mode)\n";
    exit(0);
}

echo "DEPLOYMENT READINESS: FAIL (portable-local mode, {$failures} failure(s))\n";
exit(1);

function runSystemToolsInventory(): void
{
    echo "== System tools inventory (portable) ==\n";
    $failures = 0;
    $registry = 'scripts/system/tools.registry.json';
    $root = getcwd();

    // Check registry exists
    if (!is_file($registry)) {
        echo "  fail: missing system tools registry ($registry)\n";
        $failures++;
    } else {
        echo "  ok: system tools registry ($registry)\n";

        // Validate JSON
        $raw = file_get_contents($registry);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            echo "  fail: invalid registry JSON\n";
            $failures++;
        } else {
            echo "  ok: registry JSON parses\n";

            $schema = $data['schema'] ?? '';
            if ($schema === 'odarehub.system_tools_registry.v1') {
                echo "  ok: registry schema $schema\n";
            } else {
                echo "  fail: unexpected registry schema\n";
                $failures++;
            }

            $tools = $data['tools'] ?? [];
            if (!is_array($tools) || $tools === []) {
                echo "  fail: registry tools list missing or empty\n";
                $failures++;
            } else {
                $keys = [];
                $hasPreferred = false;
                foreach ($tools as $idx => $tool) {
                    if (!is_array($tool)) { echo "  fail: tool $idx not an object\n"; $failures++; continue; }
                    $key = trim((string)($tool['key'] ?? ''));
                    $path = trim((string)($tool['path'] ?? ''));
                    if ($key === '' || $path === '') { echo "  fail: tool $idx missing key/path\n"; $failures++; continue; }
                    if (isset($keys[$key])) { echo "  fail: duplicate key $key\n"; $failures++; }
                    $keys[$key] = true;
                    if (!is_file($root . '/' . $path)) { echo "  fail: missing file for $key ($path)\n"; $failures++; }
                    else { echo "  ok: $key -> $path\n"; }
                    if (!empty($tool['preferred_entrypoint'])) $hasPreferred = true;
                }
                if (!$hasPreferred) { echo "  fail: no preferred entrypoint found\n"; $failures++; }
                else { echo "  ok: preferred entrypoint present\n"; }
            }
        }
    }

    // PHP syntax check on registered tools
    echo "  -- PHP syntax checks (registered tools) --\n";
    if (is_file($registry)) {
        $raw = file_get_contents($registry);
        $data = json_decode($raw, true);
        $tools = $data['tools'] ?? [];
        foreach ($tools as $tool) {
            $path = trim((string)($tool['path'] ?? ''));
            if (!str_ends_with($path, '.php')) continue;
            if (!is_file($root . '/' . $path)) { echo "  fail: missing $path\n"; $failures++; continue; }
            exec(PHP_BINARY . ' -l ' . escapeshellarg($root . '/' . $path) . ' 2>&1', $out, $code);
            if ($code === 0) { echo "  ok: PHP syntax $path\n"; }
            else { echo "  fail: PHP syntax $path\n"; $failures++; }
        }
    }

    // Documentation files
    $docs = [
        'scripts/system/README.md' => 'system scripts README',
        'scripts/system/deployment-readiness-portability.md' => 'deployment readiness portability note',
        'scripts/system/deployment-readiness-orchestration-contract.md' => 'deployment readiness orchestration contract',
        'scripts/architecture/README.md' => 'architecture gates README',
        'scripts/assets/README.md' => 'asset tools README',
    ];
    foreach ($docs as $path => $label) {
        if (is_file($path)) { echo "  ok: $label ($path)\n"; }
        else { echo "  fail: missing $label ($path)\n"; $failures++; }
    }

    // README text anchor checks
    $readmePath = 'scripts/system/README.md';
    if (is_file($readmePath)) {
        $readme = file_get_contents($readmePath);
        $anchors = [
            'scripts/system/tools.registry.json' => 'system README lists tools registry',
            'System Tools are governed maintenance/validation/diagnostic workers, not owners.' => 'system README documents System Tools as governed non-owners',
            'System Tools must not bypass Core, ACL, ownership boundaries' => 'system README documents no-bypass governance',
            'System Tools must not become phpMyAdmin-style direct DB editors' => 'system README blocks direct DB/admin shortcuts',
            'Runtime must not consume temporary System Tool working state as source of truth' => 'system README blocks temporary System Tool runtime truth',
        ];
        foreach ($anchors as $needle => $label) {
            if (str_contains($readme, $needle)) { echo "  ok: $label\n"; }
            else { echo "  fail: $label\n"; $failures++; }
        }
    } else {
        echo "  skip: README text anchors (README missing)\n";
    }

    if ($failures === 0) { echo "RESULT: PASS\n"; }
    else { echo "RESULT: FAIL ($failures issue(s))\n"; }
}

function runBackfillUtilityAging(): void
{
    echo "== Backfill utility aging (portable) ==\n";
    $failures = 0;
    $root = getcwd();
    $registry = 'scripts/system/tools.registry.json';
    $registryData = is_file($registry) ? json_decode(file_get_contents($registry), true) : [];
    $registeredPaths = [];
    foreach (($registryData['tools'] ?? []) as $tool) {
        $p = trim((string)($tool['path'] ?? ''));
        if ($p !== '') $registeredPaths[$p] = true;
    }

    $backfills = glob('scripts/backfill_*.php');
    if (!$backfills || count($backfills) === 0) {
        echo "  ok: no root-level backfill utilities discovered\n";
    } else {
        foreach ($backfills as $path) {
            $relPath = str_replace($root . '/', '', $path);
            echo "  ok: task-specific utility present: $relPath\n";

            // Check not referenced by deployment readiness
            $readiness = 'scripts/system/check_deployment_readiness.sh';
            if (is_file($readiness)) {
                $content = file_get_contents($readiness);
                if (str_contains($content, $relPath)) {
                    echo "  fail: $relPath is referenced by deployment readiness script\n";
                    $failures++;
                } else {
                    echo "  ok: $relPath is not referenced by deployment readiness\n";
                }
            }

            // Check not registered
            if (isset($registeredPaths[$relPath])) {
                echo "  fail: $relPath is registered as an active System Tool\n";
                $failures++;
            } else {
                echo "  ok: $relPath is not registered as an active System Tool\n";
            }
        }
    }

    if ($failures === 0) { echo "RESULT: PASS\n"; }
    else { echo "RESULT: FAIL ($failures issue(s))\n"; }
}

function runArchitectureGatesSummary(): void
{
    echo "== Architecture gates (portable summary) ==\n";
    $gatesDir = 'scripts/architecture';
    $coverageIndex = 'docs/architecture/architecture-gate-coverage-index.md';

    $files = glob($gatesDir . '/check_*.sh');
    if ($files) {
        sort($files);
        $count = count($files);
        echo "  ok: $count architecture gate scripts found under $gatesDir\n";
        echo "  note: gates require bash — not runnable on Windows without WSL\n";
        echo "  note: see $coverageIndex for full gate descriptions\n";
        if (is_file($coverageIndex)) {
            $content = file_get_contents($coverageIndex);
            if (str_contains($content, '## Aggregate Gate Order')) {
                echo "  ok: coverage index documents aggregate gate order\n";
            }
            if (str_contains($content, '## Coverage Map')) {
                echo "  ok: coverage index documents coverage map\n";
            }
        }
    } else {
        echo "  skip: no architecture gate scripts found\n";
    }
    echo "RESULT: PASS (informational)\n";
}

function runStep(string $label, array $commands, int &$failures): void
{
    echo "\n== {$label} ==\n";
    foreach ($commands as [$command, $required]) {
        passthru($command, $exitCode);
        if ($exitCode !== 0 && $required) {
            $failures++;
        }
    }
}

function findExecutable(array $names): ?string
{
    $pathEnv = (string)getenv('PATH');
    if ($pathEnv === '') {
        return null;
    }

    $separator = PHP_OS_FAMILY === 'Windows' ? ';' : ':';
    $directories = array_filter(array_map('trim', explode($separator, $pathEnv)));
    foreach ($directories as $directory) {
        foreach ($names as $name) {
            $candidate = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }
    }

    return null;
}