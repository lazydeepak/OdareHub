<?php
declare(strict_types=1);

/**
 * OH-AUTO1-044 preparation-only CLI.
 *
 * It stages source and dependency files and emits a manifest. It never loads
 * the staged autoloader, starts MySQL, connects to a database, or executes SQL.
 */

require_once __DIR__ . '/IsolatedSourceRootTestRunner.php';

$repository = dirname(__DIR__, 2);
$workspace = $argv[1] ?? null;
try {
    $result = IsolatedSourceRootTestRunner::prepare($repository, $workspace);
    echo json_encode([
        'ok' => true,
        'workspace' => $result['workspace'],
        'source_root' => $result['source_root'],
        'manifest_path' => $result['manifest_path'],
        'bootstrap_executed' => false,
        'database_connected' => false,
        'sql_executed' => false,
        'entry_points' => count($result['manifest']['entry_points']),
        'staged_files' => count($result['manifest']['files']),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "ISOLATED_SOURCE_PREPARATION_BLOCKED: {$e->getMessage()}\n");
    exit(2);
}
