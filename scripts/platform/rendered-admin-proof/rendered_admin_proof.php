#!/usr/bin/env php
<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));

$autoloadPath = APP_ROOT . '/vendor/auto' . 'load' . '.' . 'php';
require_once $autoloadPath;

use Platform\Style\ResolvedStyleConsumer;
use Platform\Style\Consumption\StyleConsumptionSurface;
use Platform\Style\Runtime\RuntimeStyleApplication;
use Platform\Style\ShellInsertion\ShellInsertion;
use Platform\Style\Proof\RenderedAdminProof;

echo "[rendered-admin-proof] Rendered Admin Proof Skeleton\n";
echo "====================\n";

$consumer = new ResolvedStyleConsumer();
$surface = new StyleConsumptionSurface($consumer);
$runtime = new RuntimeStyleApplication($surface);
$insertion = new ShellInsertion($runtime);
$proof = new RenderedAdminProof($runtime, $insertion);

echo "render() output:\n";
echo $proof->render() . "\n";

echo "\ndiagnostics:\n";
$diag = $proof->diagnostics();
echo json_encode($diag, JSON_PRETTY_PRINT) . "\n";

echo "\nflags:\n";
echo "  isProofEnabled: " . ($proof->isProofEnabled() ? 'true' : 'false') . "\n";
echo "  RuntimeStyleApplication::isRuntimeApplicationEnabled: " . ($runtime->isRuntimeApplicationEnabled() ? 'true' : 'false') . "\n";
echo "  ShellInsertion::isShellInsertionEnabled: " . ($insertion->isShellInsertionEnabled() ? 'true' : 'false') . "\n";

echo "\n====================\n";
echo "RESULT: skeleton (no active style emitted)\n";
