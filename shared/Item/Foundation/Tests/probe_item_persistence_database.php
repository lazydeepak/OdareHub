<?php
declare(strict_types=1);

/**
 * Disposable-database-only Shared Item persistence probe.
 *
 * Exit 0 = pass, 1 = implementation failure, 2 = missing/invalid disposable
 * authorization. No installed or development database is an accepted target.
 */
$repositoryRoot = trim((string)(getenv('OH_IPM_REPO') ?: dirname(__DIR__, 4)));
require_once $repositoryRoot . '/scripts/testing/DisposableDbTestEntryPointGuard.php';

try {
    $context = DisposableDbTestEntryPointGuard::authorize('OH_IPM', $repositoryRoot);
} catch (Throwable $e) {
    echo "INCONCLUSIVE: disposable Shared Item database authorization unavailable: {$e->getMessage()}\n";
    exit(2);
}

define('APP_ROOT', $context['app_root']);
require_once APP_ROOT . '/vendor/autoload.php';

use App\Core\DB;
use App\Services\AppMigrationService;
use Shared\Item\Foundation\Services\DatabaseItemIdentityService;

$passed = 0;
$failed = 0;
$pass = static function (string $label) use (&$passed): void {
    $passed++;
    echo "PASS: {$label}\n";
};
$fail = static function (string $label, string $detail = '') use (&$failed): void {
    $failed++;
    echo "FAIL: {$label}" . ($detail !== '' ? ": {$detail}" : '') . "\n";
};
$check = static function (bool $condition, string $label, string $detail = '') use ($pass, $fail): void {
    $condition ? $pass($label) : $fail($label, $detail);
};
$tableExists = static function (): bool {
    $row = DB::fetchOne(
        "SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'shared_items'"
    );
    return is_array($row) && (int)($row['c'] ?? 0) > 0;
};

if (getenv('OH_IPM_CONCURRENT') === '1') {
    try {
        $service = new DatabaseItemIdentityService();
        $item = $service->create([
            'code_ref' => (string)getenv('OH_IPM_CONCURRENT_CODE'),
            'name' => 'Concurrent Shared Item',
            'status' => 'draft',
        ]);
        echo json_encode(['ok' => true, 'item_id' => $item['item_id']], JSON_THROW_ON_ERROR) . "\n";
        exit(0);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_THROW_ON_ERROR) . "\n";
        exit(1);
    }
}

if ($tableExists()) {
    echo "INCONCLUSIVE: disposable database already contains shared_items; refusing to alter it.\n";
    exit(2);
}

$migrationPath = APP_ROOT . '/apps/Shared/migrations/20261123_000001_create_shared_items.sql';
$migrationService = new AppMigrationService();
try {
    $migrationService->ensureCoreTables();
    $first = $migrationService->runMigrations('shared', '0.1.0', dirname($migrationPath));
    $second = $migrationService->runMigrations('shared', '0.1.0', dirname($migrationPath));
    $checksum = hash('sha256', (string)file_get_contents($migrationPath));
    $ledger = DB::fetchOne(
        'SELECT status, checksum FROM core_app_migrations WHERE app_key=? AND migration_name=? LIMIT 1',
        ['shared', basename($migrationPath)]
    );
    $check($first === [basename($migrationPath)], 'first migration application is registered');
    $check($second === [], 'migration replay is skipped by ledger');
    $check(is_array($ledger) && $ledger['status'] === 'applied' && $ledger['checksum'] === $checksum, 'migration ledger preserves applied checksum');
    $check($tableExists(), 'shared_items table exists after migration');

    $service = new DatabaseItemIdentityService();
    $one = $service->create(['code_ref' => 'IPM-PERSIST-001', 'name' => 'Persistent Item', 'status' => 'draft', 'category_ref' => 'product']);
    $two = $service->create(['code_ref' => 'IPM-PERSIST-002', 'name' => 'Second Item', 'status' => 'active']);
    $three = $service->create(['code_ref' => 'IPM-PERSIST-003', 'name' => 'Third Item', 'status' => 'draft']);
    $check((int)$one['item_id'] > 0 && (int)$two['item_id'] > (int)$one['item_id'], 'create returns verified BIGINT identity');
    $check($service->getById((int)$one['item_id'])['code_ref'] === 'IPM-PERSIST-001', 'retrieve by id returns exact row');
    $check($service->getByCode('IPM-PERSIST-002')['item_id'] === $two['item_id'], 'retrieve by globally unique code returns exact row');
    $page = $service->list(['limit' => 2, 'offset' => 1]);
    $check(count($page) === 2 && (int)$page[0]['item_id'] === (int)$two['item_id'], 'bounded listing supports deterministic pagination');
    $check(count($service->list(['status' => 'draft'])) === 2, 'status filter is applied');

    $duplicateRejected = false;
    try {
        $service->create(['code_ref' => 'IPM-PERSIST-001', 'name' => 'Duplicate', 'status' => 'draft']);
    } catch (Throwable) {
        $duplicateRejected = true;
    }
    $check($duplicateRejected, 'duplicate code is rejected by service/database');
    $check(count($service->list(['limit' => 100])) === 3, 'duplicate rollback leaves no extra row');

    $active = $service->update((int)$one['item_id'], ['status' => 'active']);
    $deprecated = $service->deactivate((int)$active['item_id']);
    $reactivated = $service->activate((int)$deprecated['item_id']);
    $archived = $service->update((int)$reactivated['item_id'], ['status' => 'archived']);
    $check($active['status'] === 'active' && $deprecated['status'] === 'deprecated', 'valid status transitions are persisted');
    $check($reactivated['status'] === 'active' && $archived['status'] === 'archived', 'deprecation/reactivation/archive lifecycle is persisted');

    $invalidTransition = false;
    try {
        $service->activate((int)$archived['item_id']);
    } catch (Throwable) {
        $invalidTransition = true;
    }
    $check($invalidTransition, 'archived item cannot be reactivated');
    $forbiddenRejected = false;
    try {
        $service->create(['code_ref' => 'IPM-FORBIDDEN', 'name' => 'No', 'model' => 'manufacturing']);
    } catch (Throwable) {
        $forbiddenRejected = true;
    }
    $check($forbiddenRejected, 'Manufacturing-specific fields are rejected before persistence');

    $concurrentCode = 'IPM-CONCURRENT-' . bin2hex(random_bytes(4));
    $children = [];
    $env = getenv();
    $env['OH_IPM_CONCURRENT'] = '1';
    $env['OH_IPM_CONCURRENT_CODE'] = $concurrentCode;
    for ($i = 0; $i < 2; $i++) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, __FILE__], [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, null, $env);
        if (!is_resource($process)) {
            throw new RuntimeException('could not start concurrency child');
        }
        $children[] = [$process, $pipes];
    }
    $results = [];
    foreach ($children as [$process, $pipes]) {
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        $results[] = ['json' => json_decode(trim((string)$stdout), true), 'exit' => $exit, 'stderr' => $stderr];
    }
    $successes = count(array_filter($results, static fn(array $result): bool => ($result['json']['ok'] ?? false) === true));
    $check($successes === 1, 'concurrent duplicate creation has exactly one winner');
    $check(count($service->list(['limit' => 100])) === 4, 'concurrent loser does not create a duplicate row');
} catch (Throwable $e) {
    $fail('database persistence probe completed', $e->getMessage());
}

echo json_encode(['passed' => $passed, 'failed' => $failed], JSON_THROW_ON_ERROR) . "\n";
exit($failed === 0 ? 0 : 1);
