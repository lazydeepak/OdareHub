<?php
declare(strict_types=1);

/**
 * Offline behavioral probe for Hospitality operator action dispatch.
 *
 * Each child process defines only Auth, ACL, and domain-service doubles before
 * loading the real OperatorActions class. No application bootstrap, database,
 * route loader, migration, or HTTP runtime is loaded.
 */

namespace App\Core {
    final class Auth
    {
        public static function user(): array
        {
            return ['id' => 7, 'username' => 'worker'];
        }

        public static function requireCsrf(string $csrf, string $redirect): void
        {
            if ($csrf !== 'csrf-ok') {
                throw new \RuntimeException('CSRF_FAILED');
            }
        }
    }

    final class AclPolicy
    {
        public static function can(string $permission, array $user): bool
        {
            return (bool)($GLOBALS['allow'] ?? false);
        }
    }
}

namespace Apps\Hospitality\Services {
    final class ReservationsService
    {
        public static function create(array $post): int
        {
            if (($GLOBALS['reservation_service_error'] ?? false) === true) {
                throw new \RuntimeException('HOSPITALITY_RES_ROOM_CONFLICT');
            }
            $GLOBALS['calls'][] = ['method' => 'reservationCreate', 'post' => $post];
            return 42;
        }
    }

    final class HousekeepingService
    {
        public static function updateStatus(int $id, array $post): void
        {
            $GLOBALS['calls'][] = ['method' => 'housekeepingStatus', 'id' => $id, 'post' => $post];
        }
    }

    final class FrontDeskService
    {
        public static function addChargeForReservation(int $id, array $post): void
        {
            $GLOBALS['calls'][] = ['method' => 'frontDeskAddCharge', 'id' => $id, 'post' => $post];
        }

        public static function markReservationNoShow(int $id): void
        {
            $GLOBALS['calls'][] = ['method' => 'frontDeskMarkNoShow', 'id' => $id];
        }

        public static function voidCharge(int $id): void
        {
            $GLOBALS['calls'][] = ['method' => 'frontDeskVoidCharge', 'id' => $id];
        }

        public static function cancelReservation(int $id): void
        {
            $GLOBALS['calls'][] = ['method' => 'frontDeskCancelReservation', 'id' => $id];
        }

        public static function checkout(int $id): void
        {
            $GLOBALS['calls'][] = ['method' => 'frontDeskCheckOut', 'id' => $id];
        }

        public static function checkIn(int $id): void
        {
            $GLOBALS['calls'][] = ['method' => 'frontDeskCheckIn', 'id' => $id];
        }
    }
}

namespace {
    $root = dirname(__DIR__, 3);
    $handlerPath = $root . '/apps/Hospitality/Controllers/OperatorActions.php';
    $viewSource = (string)file_get_contents($root . '/apps/Hospitality/Views/operator/hospitality.php');
    $shellSource = (string)file_get_contents($root . '/apps/Shell/routes.php');

    $passed = 0;
    $failed = 0;
    $pass = static function (string $label) use (&$passed): void {
        $passed++;
        echo "  PASS [{$label}]\n";
    };
    $fail = static function (string $label, string $detail = '') use (&$failed): void {
        $failed++;
        echo "  FAIL [{$label}]" . ($detail !== '' ? ": {$detail}" : '') . "\n";
    };

    $operations = [
        'housekeepingStatus' => ['post_key' => 'room_id', 'service' => 'housekeepingStatus'],
        'frontDeskAddCharge' => ['post_key' => 'reservation_id', 'service' => 'frontDeskAddCharge'],
        'frontDeskMarkNoShow' => ['post_key' => 'reservation_id', 'service' => 'frontDeskMarkNoShow'],
        'frontDeskVoidCharge' => ['post_key' => 'charge_id', 'service' => 'frontDeskVoidCharge'],
        'frontDeskCancelReservation' => ['post_key' => 'reservation_id', 'service' => 'frontDeskCancelReservation'],
        'frontDeskCheckOut' => ['post_key' => 'reservation_id', 'service' => 'frontDeskCheckOut'],
        'frontDeskCheckIn' => ['post_key' => 'reservation_id', 'service' => 'frontDeskCheckIn'],
    ];

    if (($argv[1] ?? '') === 'child') {
        require_once $handlerPath;
        $operation = (string)($argv[2] ?? '');
        $mode = (string)($argv[3] ?? '');
        $definition = $operations[$operation] ?? ($operation === 'reservationCreate'
            ? ['post_key' => 'guest_id', 'service' => 'reservationCreate']
            : null);
        if (!is_array($definition)) {
            fwrite(STDERR, "unknown operation\n");
            exit(2);
        }

        $GLOBALS['allow'] = $mode !== 'unauthorized';
        $GLOBALS['calls'] = [];
        $_SESSION = [];
        $value = match ($mode) {
            'valid' => (string)PHP_INT_MAX,
            'malformed' => '12abc',
            'overflow' => (string)PHP_INT_MAX . '0',
            default => '7',
        };
        $_POST = [$definition['post_key'] => $value, 'csrf' => $mode === 'csrf' ? 'bad' : 'csrf-ok', 'payload_marker' => 'forwarded'];
        if ($operation === 'reservationCreate') {
            $GLOBALS['reservation_service_error'] = $mode === 'service_error';
            $_POST = [
                'guest_id' => $mode === 'malformed_guest' ? '7abc' : ($mode === 'csrf' || $mode === 'unauthorized' ? '7' : (string)PHP_INT_MAX),
                'room_id' => $mode === 'malformed_room' ? '8abc' : ($mode === 'unassigned' ? '' : (string)PHP_INT_MAX),
                'check_in_date' => '2030-01-01',
                'check_out_date' => '2030-01-03',
                'adults' => '2',
                'children' => '1',
                'rate' => '120.50',
                'note' => 'forwarded',
                'csrf' => $mode === 'csrf' ? 'bad' : 'csrf-ok',
            ];
        }

        register_shutdown_function(static function (): void {
            echo json_encode([
                'calls' => $GLOBALS['calls'] ?? [],
                'flash_error' => $_SESSION['operator_hospitality_flash_err'] ?? null,
                'flash_ok' => $_SESSION['operator_hospitality_flash_ok'] ?? null,
            ], JSON_THROW_ON_ERROR) . "\n";
        });

        $class = 'Apps\\Hospitality\\Controllers\\OperatorActions';
        $class::$operation(['username' => 'worker']);
        exit(3);
    }

    echo "== Hospitality operator behavioral dispatch ==\n";

    foreach ($operations as $operation => $definition) {
        foreach (['valid', 'malformed', 'overflow', 'unauthorized', 'csrf'] as $mode) {
            $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__)
                . ' child ' . escapeshellarg($operation) . ' ' . escapeshellarg($mode);
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $payload = json_decode((string)end($output), true);
            if (!is_array($payload)) {
                $fail("{$operation}/{$mode} produced behavioral result", implode("\n", $output));
                continue;
            }

            $calls = (array)($payload['calls'] ?? []);
            if ($mode === 'valid') {
                $call = $calls[0] ?? [];
                ($exitCode === 0 && count($calls) === 1 && ($call['method'] ?? '') === $definition['service']
                    && ($call['id'] ?? null) === PHP_INT_MAX)
                    ? $pass("{$operation}/valid preserves exact PHP_INT_MAX identity")
                    : $fail("{$operation}/valid dispatch", json_encode($payload));
                if ($operation === 'frontDeskAddCharge') {
                    (($call['post']['payload_marker'] ?? '') === 'forwarded')
                        ? $pass('frontDeskAddCharge forwards original POST payload')
                        : $fail('frontDeskAddCharge payload forwarding');
                }
                continue;
            }

            $expectedError = match ($mode) {
                'unauthorized' => 'HOSPITALITY_FORBIDDEN_MANAGE',
                'csrf' => 'CSRF_FAILED',
                default => 'HOSPITALITY_ID_INVALID',
            };
            (count($calls) === 0 && ($payload['flash_error'] ?? null) === $expectedError)
                ? $pass("{$operation}/{$mode} blocks before service dispatch")
                : $fail("{$operation}/{$mode} rejection", json_encode($payload));
        }
    }

    $reservationModes = ['valid', 'unassigned', 'malformed_guest', 'malformed_room', 'unauthorized', 'csrf', 'service_error'];
    foreach ($reservationModes as $mode) {
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__)
            . ' child reservationCreate ' . escapeshellarg($mode);
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $payload = json_decode((string)end($output), true);
        $calls = is_array($payload) ? (array)($payload['calls'] ?? []) : [];
        if ($mode === 'valid') {
            $call = $calls[0] ?? [];
            (count($calls) === 1 && ($call['method'] ?? '') === 'reservationCreate'
                && ($call['post']['guest_id'] ?? null) === PHP_INT_MAX
                && ($call['post']['room_id'] ?? null) === PHP_INT_MAX
                && ($call['post']['note'] ?? '') === 'forwarded')
                ? $pass('reservationCreate forwards normalized assigned reservation payload once')
                : $fail('reservationCreate valid delegation', json_encode($payload));
            continue;
        }
        if ($mode === 'unassigned') {
            $call = $calls[0] ?? [];
            (count($calls) === 1 && ($call['post']['guest_id'] ?? null) === PHP_INT_MAX
                && ($call['post']['room_id'] ?? null) === 0)
                ? $pass('reservationCreate preserves optional unassigned room')
                : $fail('reservationCreate unassigned delegation', json_encode($payload));
            continue;
        }
        $expectedError = in_array($mode, ['unauthorized'], true)
            ? 'HOSPITALITY_FORBIDDEN_MANAGE'
            : ($mode === 'csrf' ? 'CSRF_FAILED' : ($mode === 'service_error' ? 'HOSPITALITY_RES_ROOM_CONFLICT' : 'HOSPITALITY_ID_INVALID'));
        (count($calls) === 0 && ($payload['flash_error'] ?? null) === $expectedError)
            ? $pass("reservationCreate/{$mode} blocks before service dispatch")
            : $fail("reservationCreate/{$mode} rejection", json_encode($payload));
    }

    // Route/self-workspace and disclosure contracts are verified offline here;
    // executing the real route loader would bootstrap the application/DB path.
    foreach (['reservations/create', 'housekeeping/status', 'front-desk/check-in', 'front-desk/check-out', 'front-desk/charges/add', 'front-desk/charges/void', 'front-desk/cancel', 'front-desk/no-show'] as $route) {
        str_contains($shellSource, "\$router->post('/u/hospitality/{$route}'")
            ? $pass("route /u/hospitality/{$route} exists")
            : $fail("route /u/hospitality/{$route} exists");
    }
    str_contains($shellSource, 'WorkspaceWrapperRegistry::handleFromIdentity') && str_contains($shellSource, 'hash_equals')
        ? $pass('self-workspace binding remains source-enforced')
        : $fail('self-workspace binding source contract');
    str_contains($viewSource, 'hospitality.operator.flash.update_failed')
        && !preg_match('/echo\s+\$esc\(\$flashErr\)/', $viewSource)
        ? $pass('operator errors render through localized generic fallback')
        : $fail('operator error disclosure boundary');
    str_contains($viewSource, 'hospitality/reservations/create')
        && str_contains($viewSource, 'name="guest_id"')
        && str_contains($viewSource, 'name="room_id"')
        && str_contains($viewSource, 'name="check_in_date"')
        && str_contains($viewSource, 'name="check_out_date"')
        && str_contains($viewSource, 'name="adults"')
        && str_contains($viewSource, 'name="children"')
        && str_contains($viewSource, 'name="rate"')
        && str_contains($viewSource, 'name="note"')
        ? $pass('reservation form renders the complete existing field contract')
        : $fail('reservation form field contract');
    str_contains($viewSource, '$canManage && is_array($reservationOptions)')
        && str_contains($viewSource, 'HospitalityBoardAdapter::reservationFormOptions()')
        ? $pass('reservation form is manage-gated and uses owner adapter options')
        : $fail('reservation form authority/source boundary');

    echo "\nResult: {$passed} passed, {$failed} failed\n";
    exit($failed > 0 ? 1 : 0);
}
