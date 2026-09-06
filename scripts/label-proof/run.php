<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 2));

require APP_ROOT . '/vendor/autoload.php';

use Platform\Labels\Pipeline\LabelRuntimeRequest;
use Platform\Labels\Pipeline\PipelineCoordinator;

$exitCode = 0;
$outputDir = APP_ROOT . '/scripts/label-proof/output';

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

$scenarios = [
    'manufacturing-product-label' => [
        'label' => 'Manufacturing Product Label',
        'request' => LabelRuntimeRequest::fromArray([
            'owner_key' => 'Manufacturing/Products',
            'context_key' => 'manufacturing.product.label',
            'template_key' => 'manufacturing.product.label.100x50_mm',
            'data_payload' => [
                'product_name' => 'Precision Widget A',
                'product_code' => 'PW-A-001',
                'batch_number' => 'BATCH-2026-06-001',
                'manufacturing_date' => '2026-06-10',
            ],
            'output_target' => 'html_preview',
        ]),
    ],
    'empty-data' => [
        'label' => 'Manufacturing Product Label (empty data)',
        'request' => LabelRuntimeRequest::fromArray([
            'owner_key' => 'Manufacturing/Products',
            'context_key' => 'manufacturing.product.label',
            'template_key' => 'manufacturing.product.label.100x50_mm',
            'data_payload' => [],
            'output_target' => 'html_preview',
        ]),
    ],
    'validation-failure-empty-owner' => [
        'label' => 'Validation Failure (empty owner)',
        'request' => LabelRuntimeRequest::fromArray([
            'owner_key' => '',
            'context_key' => 'manufacturing.product.label',
            'template_key' => 'manufacturing.product.label.100x50_mm',
            'data_payload' => [],
            'output_target' => 'html_preview',
        ]),
    ],
    'validation-failure-forbidden-target' => [
        'label' => 'Validation Failure (forbidden output target)',
        'request' => LabelRuntimeRequest::fromArray([
            'owner_key' => 'Manufacturing/Products',
            'context_key' => 'manufacturing.product.label',
            'template_key' => 'manufacturing.product.label.100x50_mm',
            'data_payload' => [],
            'output_target' => 'pdf',
        ]),
    ],
    'resolution-failure-unknown-owner' => [
        'label' => 'Resolution Failure (unknown owner)',
        'request' => LabelRuntimeRequest::fromArray([
            'owner_key' => 'NonExistent/Module',
            'context_key' => 'test',
            'template_key' => 'test',
            'data_payload' => [],
            'output_target' => 'html_preview',
        ]),
    ],
];

echo "================================================================\n";
echo "  Label Designer Phase 1 Runtime Proof\n";
echo "  Pipeline: RequestValidator → ResourceResolver → ModelBuilder → HtmlPreviewAdapter\n";
echo "================================================================\n";
echo "\n";

$allPass = true;

foreach ($scenarios as $key => $scenario) {
    echo "--- {$scenario['label']} ---\n";
    echo "  Scenario key: {$key}\n";

    $result = PipelineCoordinator::run($scenario['request']);
    $model = $result['model'];
    $html = $result['html'];
    $diagnostics = $result['diagnostics'];

    $hasModel = $model !== null ? 'YES' : 'NO';
    $hasHtml = strlen($html) > 0 ? 'YES' : 'NO';
    echo "  Model built: {$hasModel}\n";
    echo "  HTML output: {$hasHtml} (" . strlen($html) . " bytes)\n";
    echo "  Diagnostics: " . count($diagnostics) . "\n";

    $errors = array_filter($diagnostics, fn($d) => ($d['severity'] ?? '') === 'ERROR');
    $fails = array_filter($diagnostics, fn($d) => ($d['severity'] ?? '') === 'FAIL');
    $warnings = array_filter($diagnostics, fn($d) => ($d['severity'] ?? '') === 'WARN');
    $passes = array_filter($diagnostics, fn($d) => ($d['severity'] ?? '') === 'PASS');
    $infos = array_filter($diagnostics, fn($d) => ($d['severity'] ?? '') === 'INFO');

    if (count($errors) > 0) {
        echo "  ❌ ERRORS: " . count($errors) . "\n";
        foreach ($errors as $e) {
            echo "    [{$e['code']}] {$e['message']}" . ($e['field'] ? " (field: {$e['field']})" : "") . "\n";
        }
    }
    if (count($fails) > 0) {
        echo "  ⚠ FAILS: " . count($fails) . "\n";
        foreach ($fails as $f) {
            echo "    [{$f['code']}] {$f['message']}" . ($f['field'] ? " (field: {$f['field']})" : "") . "\n";
        }
    }
    if (count($warnings) > 0) {
        echo "  ⚠ WARNINGS: " . count($warnings) . "\n";
    }
    if (count($passes) > 0) {
        echo "  ✓ PASS: " . count($passes) . "\n";
    }
    if (count($infos) > 0) {
        echo "  ℹ INFO: " . count($infos) . "\n";
    }

    $outputFile = $outputDir . '/' . $key . '.html';
    $writeResult = file_put_contents($outputFile, $html);
    if ($writeResult !== false) {
        echo "  HTML written: {$outputFile}\n";
    } else {
        echo "  ⚠ Failed to write HTML output\n";
    }

    $scenarioPass = $model !== null || count($errors) > 0 || count($fails) > 0;
    if ($model === null && count($errors) === 0 && count($fails) === 0) {
        echo "  ⚠ No model AND no blocking diagnostics — unexpected state\n";
        $allPass = false;
    }

    echo "\n";
}

echo "================================================================\n";
$outputFiles = glob($outputDir . '/*.html');
echo "Output files (" . count($outputFiles) . "):\n";
foreach ($outputFiles as $f) {
    echo "  - {$f}\n";
}

echo "\n";
if ($allPass) {
    echo "RESULT: ALL SCENARIOS PROCESSED\n";
    exit(0);
} else {
    echo "RESULT: SOME SCENARIOS FAILED\n";
    exit(1);
}
