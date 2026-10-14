<?php
declare(strict_types=1);

/**
 * OH-AUTO1-041 isolated reservation-create concurrency regression.
 *
 * This probe requires an explicitly attested disposable MySQL instance. It
 * refuses to run while the installed storage/db_config.php exists, and it
 * never discovers or uses installed database credentials.
 *
 * The temporary INSERT trigger widens the check/insert window only inside the
 * disposable database. Before the room-row lock correction, both workers can
 * pass the overlap check and create durable conflicts. After the correction,
 * one worker commits and the other receives HOSPITALITY_RES_ROOM_CONFLICT.
 */

$env = static function (string $key): string {
    $value = getenv($key);
    return $value === false ? '' : trim((string)$value);
};

$required = [
    'OH_HOSP_REPO',
    'OH_HOSP_APP_ROOT',
    'OH_HOSP_DB_PID',
    'OH_HOSP_DB_SOCKET',
    'OH_HOSP_DB_DATADIR',
    'OH_HOSP_DB_RESOURCE_ROOT',
    'OH_HOSP_DB_PORT',
    'OH_HOSP_DB_NAME',
    'OH_HOSP_DB_USER',
];
foreach ($required as $key) {
    if ($env($key) === '') {
        fwrite(STDERR, "Missing explicit test input: {$key}\n");
        exit(2);
    }
}

$repo = $env('OH_HOSP_REPO');
$appRoot = $env('OH_HOSP_APP_ROOT');
if (!is_file($repo . '/scripts/testing/DisposableDbExecutionAuthorizer.php')) {
    fwrite(STDERR, "Disposable authorizer is unavailable.\n");
    exit(2);
}

require_once $repo . '/scripts/testing/DisposableDbTestConfigurationGuard.php';
$configuration = DisposableDbTestConfigurationGuard::authorize([
    'repo' => $repo,
    'app_root' => $appRoot,
    'resource_root' => $env('OH_HOSP_DB_RESOURCE_ROOT'),
    'host' => '127.0.0.1',
    'port' => $env('OH_HOSP_DB_PORT'),
    'name' => $env('OH_HOSP_DB_NAME'),
    'user' => $env('OH_HOSP_DB_USER'),
]);
require_once $repo . '/scripts/testing/DisposableDbExecutionAuthorizer.php';
$authorization = DisposableDbExecutionAuthorizer::authorize(
    (int)$env('OH_HOSP_DB_PID'),
    $env('OH_HOSP_DB_SOCKET'),
    $env('OH_HOSP_DB_DATADIR'),
    $env('OH_HOSP_DB_RESOURCE_ROOT'),
    (int)$env('OH_HOSP_DB_PORT')
);
if (!$authorization['authorized']) {
    fwrite(STDERR, "Disposable database authorization failed.\n");
    exit(2);
}
DisposableDbTestConfigurationGuard::assertUnchanged($configuration);

define('APP_ROOT', $appRoot);
require $repo . '/vendor/autoload.php';

use App\Core\DB;
use Apps\Hospitality\Services\GuestsService;
use Apps\Hospitality\Services\ReservationsService;
use Apps\Hospitality\Services\RoomsService;

if (($argv[1] ?? '') === '--worker') {
    try {
        ReservationsService::create([
            'guest_id' => (int)($argv[2] ?? 0),
            'room_id' => (int)($argv[3] ?? 0),
            'check_in_date' => '2031-06-10',
            'check_out_date' => '2031-06-15',
            'note' => 'OH-AUTO1-041 worker',
        ]);
        echo json_encode(['ok' => true], JSON_UNESCAPED_SLASHES) . "\n";
        exit(0);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_SLASHES) . "\n";
        exit(1);
    }
}

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$suffix = strtoupper(bin2hex(random_bytes(4)));
$trigger = 'oh041_reservation_pause_' . strtolower($suffix);
$guestId = 0;
$roomId = 0;
$children = [];

try {
    $guestId = GuestsService::create(['full_name' => 'OH-AUTO1-041 ' . $suffix]);
    $roomId = RoomsService::create(['room_number' => 'OH041-' . $suffix, 'room_type' => 'double']);
    DB::query("CREATE TRIGGER {$trigger} BEFORE INSERT ON hosp_reservations FOR EACH ROW BEGIN DO SLEEP(1); END");

    foreach (range(1, 2) as $worker) {
        $command = implode(' ', array_map('escapeshellarg', [PHP_BINARY, __FILE__, '--worker', (string)$guestId, (string)$roomId]));
        $pipes = [];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start reservation worker.');
        }
        $children[] = [$process, $pipes];
    }

    $results = [];
    foreach ($children as [$process, $pipes]) {
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $results[] = [
            'code' => proc_close($process),
            'output' => trim((string)$stdout . "\n" . (string)$stderr),
        ];
    }

    $successes = count(array_filter($results, static fn(array $result): bool => $result['code'] === 0));
    $conflictFailures = 0;
    foreach ($results as $result) {
        $payload = json_decode($result['output'], true);
        if (is_array($payload) && ($payload['error'] ?? '') === 'HOSPITALITY_RES_ROOM_CONFLICT') {
            $conflictFailures++;
        }
    }
    $durable = DB::fetchOne(
        "SELECT COUNT(*) AS c FROM hosp_reservations WHERE room_id=? AND check_in_date='2031-06-10' AND check_out_date='2031-06-15' AND reservation_status IN ('booked','checked_in')",
        [$roomId]
    );
    $durableCount = (int)($durable['c'] ?? 0);

    $check($successes === 1, 'exactly one concurrent reservation create succeeds');
    $check($conflictFailures === 1, 'losing concurrent create returns room conflict');
    $check($durableCount === 1, 'concurrent overlap leaves one durable active reservation');
} catch (Throwable $e) {
    $failed++;
    echo 'FAIL probe execution: ' . $e->getMessage() . "\n";
} finally {
    try {
        DB::query("DROP TRIGGER IF EXISTS {$trigger}");
    } catch (Throwable) {
        $failed++;
        echo "FAIL cleanup trigger\n";
    }
    if ($roomId > 0) {
        try { DB::query('DELETE FROM hosp_reservations WHERE room_id=?', [$roomId]); } catch (Throwable) { $failed++; }
        try { DB::query('DELETE FROM hosp_rooms WHERE id=?', [$roomId]); } catch (Throwable) { $failed++; }
    }
    if ($guestId > 0) {
        try { DB::query('DELETE FROM hosp_guests WHERE id=?', [$guestId]); } catch (Throwable) { $failed++; }
    }
}

echo "RESULT passed={$passed} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
