<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$asJson = in_array('--json', $argv, true);

$servicePath = $root . '/plugins/AdminTools/Services/ModuleHealthReportService.php';
require_once $servicePath;

$report = \Plugins\AdminTools\Services\ModuleHealthReportService::generate($root);
$results = (array)($report['modules'] ?? []);

// CI failure bands: modules in these bands cause a non-zero exit code.
$failBands = ['undeclared_or_non_functional', 'invalid_manifest'];
$warnBands = ['baby_module_or_shell'];

$hasFail = false;
$hasWarn = false;
foreach ($results as $row) {
    $band = (string)($row['band'] ?? '');
    if (in_array($band, $failBands, true)) {
        $hasFail = true;
    } elseif (in_array($band, $warnBands, true)) {
        $hasWarn = true;
    }
}

if ($asJson) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit($hasFail ? 2 : ($hasWarn ? 1 : 0));
}

echo '# Module Health Check' . PHP_EOL . PHP_EOL;
echo '| Module | Suite | Type | Target | Score | Band | Missing | Partial | Type Gaps |' . PHP_EOL;
echo '| --- | --- | --- | --- | ---: | --- | --- | --- | --- |' . PHP_EOL;

foreach ($results as $row) {
    echo '| '
        . cell((string)$row['module']) . ' | '
        . cell((string)$row['suite']) . ' | '
        . cell((string)$row['module_type']) . ' | '
        . cell((string)$row['target_maturity_level']) . ' | '
        . (int)$row['score_percent'] . '% | '
        . cell((string)$row['band']) . ' | '
        . cell(implode(', ', (array)$row['missing']) ?: '-') . ' | '
        . cell(implode(', ', (array)$row['partial']) ?: '-') . ' |'
        . ' ' . cell(implode(', ', (array)($row['required_capability_gaps'] ?? [])) ?: '-') . ' |'
        . PHP_EOL;
}

$summary = (array)($report['summary'] ?? []);
echo PHP_EOL;
if ($hasFail) {
    $failCount = 0;
    foreach ($failBands as $b) {
        $failCount += (int)($summary[$b] ?? 0);
    }
    echo "FAIL: {$failCount} module(s) in critical band (undeclared/invalid_manifest)" . PHP_EOL;
} elseif ($hasWarn) {
    $warnCount = (int)($summary['baby_module_or_shell'] ?? 0);
    echo "WARN: {$warnCount} module(s) in baby/shell band" . PHP_EOL;
} else {
    echo 'OK: all modules meet minimum health threshold' . PHP_EOL;
}

exit($hasFail ? 2 : ($hasWarn ? 1 : 0));

function cell(string $value): string
{
    return str_replace('|', '\\|', $value);
}
