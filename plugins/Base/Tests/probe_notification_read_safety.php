<?php
declare(strict_types=1);

/**
 * OH-AUTO2-101 isolated notification read proof.
 *
 * The fake DB rejects/records writes and supplies only authorized notification
 * rows. NotificationService is loaded against these fakes without application
 * bootstrap or an installed database.
 */

namespace App\Core {
    final class DB
    {
        public static bool $available = true;
        public static int $writes = 0;
        /** @var array<int,array{sql:string,params:array<int,mixed>}> */
        public static array $reads = [];

        public static function reset(): void
        {
            self::$available = true;
            self::$writes = 0;
            self::$reads = [];
        }

        public static function query(string $sql, array $params = []): void
        {
            self::$writes++;
            throw new \RuntimeException('notification read attempted a database write');
        }

        /** @return array<string,mixed>|null */
        public static function fetchOne(string $sql, array $params = []): ?array
        {
            self::$reads[] = ['sql' => $sql, 'params' => $params];
            if (!self::$available) {
                throw new \RuntimeException('notification table unavailable');
            }
            return [
                'total' => 2,
                'unread' => 1,
                'critical' => 1,
                'warning' => 1,
                'action_required' => 0,
                'approval_required' => 0,
            ];
        }

        /** @return array<int,array<string,mixed>> */
        public static function fetchAll(string $sql, array $params = []): array
        {
            self::$reads[] = ['sql' => $sql, 'params' => $params];
            if (!self::$available) {
                throw new \RuntimeException('notification table unavailable');
            }
            return [[
                'id' => 9,
                'severity' => 'critical',
                'status' => 'new',
                'event_type' => 'sla_breach',
                'title' => 'SLA breach',
                'message' => 'Authorized notification',
                'entity_type' => 'production_entry',
                'entity_id' => 12,
                'stage' => 'production_to_qc',
                'action_url' => '/production-entries/edit?id=12',
                'created_at' => '2026-10-16 10:00:00',
            ]];
        }
    }
}

namespace Plugins\Base\Services {
    final class EscalationService
    {
        public static int $runs = 0;

        public static function runSweep(bool $force = false): void
        {
            self::$runs++;
        }
    }
}

namespace {
    const APP_ROOT = __DIR__ . '/notification-read-fixture-root';
    use App\Core\DB;
    use Plugins\Base\Services\NotificationService;

    require_once __DIR__ . '/../Services/NotificationService.php';

    $assertions = 0;
    $failures = [];
    $assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
        $assertions++;
        if (!$condition) {
            $failures[] = $message;
        }
    };

    $user = ['id' => 42, 'role' => 'QC'];
    DB::reset();

    $summaryRead = NotificationService::summaryReadForUser($user);
    $recentRead = NotificationService::recentReadForUser($user, 6, true);
    $assert(($summaryRead['available'] ?? false) === true, 'summary source is available');
    $assert(($recentRead['available'] ?? false) === true, 'recent source is available');
    $assert(($summaryRead['summary']['unread'] ?? 0) === 1, 'authorized unread count remains available');
    $assert(count($recentRead['rows'] ?? []) === 1, 'authorized recent notification remains available');
    $assert((($recentRead['rows'][0]['entity_id'] ?? 0) === 12), 'authorized entity reference remains available');
    $assert(DB::$writes === 0, 'summary/recent reads perform zero database writes');
    $assert(containsRecipientFilter(DB::$reads), 'recipient role/user filtering remains in both read queries');
    $assert(
        \Plugins\Base\Services\EscalationService::$runs === 0,
        'notification reads do not invoke escalation sweeps'
    );

    DB::reset();
    $legacySummary = NotificationService::summaryForUser($user);
    $legacyRecent = NotificationService::recentForUser($user, 6, true);
    $assert(($legacySummary['unread'] ?? 0) === 1, 'legacy summary API preserves valid read shape');
    $assert(count($legacyRecent) === 1, 'legacy recent API preserves valid read shape');
    $assert(DB::$writes === 0, 'legacy read APIs perform zero database writes');

    DB::reset();
    DB::$available = false;
    $missingSummary = NotificationService::summaryReadForUser($user);
    $missingRecent = NotificationService::recentReadForUser($user, 6, true);
    $assert(($missingSummary['available'] ?? true) === false, 'missing notification storage is explicit for summary');
    $assert(($missingRecent['available'] ?? true) === false, 'missing notification storage is explicit for recent list');
    $assert(DB::$writes === 0, 'missing notification storage still performs zero writes');

    $assert(is_callable(['Plugins\\Base\\Services\\EscalationService', 'runSweep']), 'explicit escalation entry point remains callable');
    \Plugins\Base\Services\EscalationService::runSweep(true);
    $assert(\Plugins\Base\Services\EscalationService::$runs === 1, 'explicit escalation execution remains separate from reads');

    if ($failures !== []) {
        fwrite(STDERR, "FAIL\n" . implode("\n", $failures) . "\n");
        exit(1);
    }

    fwrite(STDOUT, "PASS {$assertions}/{$assertions}\n");

    /** @param array<int,array{sql:string,params:array<int,mixed>}> $reads */
    function containsRecipientFilter(array $reads): bool
    {
        if (count($reads) < 2) {
            return false;
        }
        foreach ($reads as $read) {
            if (strpos($read['sql'], 'recipient_role = ? OR recipient_user_id = ? OR user_id = ?') === false) {
                return false;
            }
            if ($read['params'] !== ['QC', 42, 42] && $read['params'] !== ['QC', 42, 42, 'new']) {
                return false;
            }
        }
        return true;
    }
}
