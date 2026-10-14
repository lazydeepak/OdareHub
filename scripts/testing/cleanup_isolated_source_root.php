<?php
declare(strict_types=1);

/** OH-AUTO1-044 runner-owned cleanup; no application or DB bootstrap. */

require_once __DIR__ . '/IsolatedSourceRootTestRunner.php';
$workspace = $argv[1] ?? '';
try {
    IsolatedSourceRootTestRunner::cleanup($workspace);
    echo "ISOLATED_SOURCE_CLEANUP=PASS\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "ISOLATED_SOURCE_CLEANUP_BLOCKED: {$e->getMessage()}\n");
    exit(2);
}
