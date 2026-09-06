<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

use App\Core\DB;
use Plugins\Base\Services\NotificationService;

final class EscalationContributionService
{
    private const ENGINE_KEY = 'notification_escalation';
    private const RUN_INTERVAL_SECONDS = 300;

    public static function runSweep(bool $force = false): void
    {
        self::ensureSchema();

        if (!$force && !self::dueForRun()) {
            return;
        }

        self::markRun();
        self::sweepSlaSignals();
        self::sweepMissingHandoffCompletion();
        self::sweepWorkflowInconsistency();
    }

    private static function ensureSchema(): void
    {
        DB::query(
            "CREATE TABLE IF NOT EXISTS workflow_escalation_runs (
                engine_key VARCHAR(80) PRIMARY KEY,
                last_run_at DATETIME NULL,
                updated_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    private static function dueForRun(): bool
    {
        $row = DB::fetchOne(
            'SELECT last_run_at FROM workflow_escalation_runs WHERE engine_key = ? LIMIT 1',
            [self::ENGINE_KEY]
        );
        $last = trim((string)($row['last_run_at'] ?? ''));
        if ($last === '') {
            return true;
        }

        try {
            $lastTs = (new \DateTime($last))->getTimestamp();
        } catch (\Throwable $e) {
            return true;
        }

        return (time() - $lastTs) >= self::RUN_INTERVAL_SECONDS;
    }

    private static function markRun(): void
    {
        $now = date('Y-m-d H:i:s');
        DB::query(
            'INSERT INTO workflow_escalation_runs (engine_key, last_run_at, updated_at)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE last_run_at = VALUES(last_run_at), updated_at = VALUES(updated_at)',
            [self::ENGINE_KEY, $now, $now]
        );
    }

    private static function sweepSlaSignals(): void
    {
        if (!self::tableExists('handoff_tracking')) {
            return;
        }

        $rows = DB::fetchAll(
            'SELECT entity_type, entity_id, stage, owner_role, sla_state, escalated_at
             FROM handoff_tracking
             WHERE released_since IS NULL
               AND (LOWER(COALESCE(sla_state, "")) IN ("overdue", "breached") OR COALESCE(escalated_at, "") <> "")
             ORDER BY updated_at DESC
             LIMIT 300'
        );

        foreach ($rows as $row) {
            $entityType = trim((string)($row['entity_type'] ?? ''));
            $entityId = (int)($row['entity_id'] ?? 0);
            $stage = trim((string)($row['stage'] ?? ''));
            $ownerRole = trim((string)($row['owner_role'] ?? ''));
            $slaState = strtolower(trim((string)($row['sla_state'] ?? '')));
            $isEscalated = trim((string)($row['escalated_at'] ?? '')) !== '';

            if ($entityType === '' || $entityId <= 0) {
                continue;
            }

            if ($ownerRole === '') {
                $ownerRole = 'App Admin';
            }

            $severity = NotificationService::SEVERITY_WARNING;
            $eventType = NotificationService::EVENT_SLA_OVERDUE;
            $title = 'SLA overdue';
            $message = 'Item is overdue and requires immediate action.';
            $token = 'overdue|' . date('YmdH');

            if ($slaState === 'breached' || $isEscalated) {
                $severity = NotificationService::SEVERITY_CRITICAL;
                $eventType = NotificationService::EVENT_SLA_BREACH;
                $title = 'SLA breached';
                $message = 'Item has breached SLA and has been escalated in visibility.';
                $token = 'breached|' . (string)intdiv(time(), 4 * 3600);
            }

            NotificationService::create([
                'recipient_role' => $ownerRole,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'stage' => $stage,
                'event_type' => $eventType,
                'severity' => $severity,
                'title' => $title,
                'message' => $message,
                'action_url' => self::actionUrl($entityType, $entityId),
                'extra_token' => $token,
            ]);
        }
    }

    private static function sweepMissingHandoffCompletion(): void
    {
        if (!self::tableExists('handoff_tracking')) {
            return;
        }

        $checks = [
            [
                'sql' => 'SELECT ht.entity_type, ht.entity_id, ht.stage, ht.owner_role
                          FROM handoff_tracking ht
                          JOIN production_entries pe ON pe.id = ht.entity_id
                          WHERE ht.entity_type = "production_entry"
                            AND ht.released_since IS NULL
                            AND LOWER(COALESCE(pe.status, "")) IN ("completed", "closed")
                          LIMIT 80',
            ],
            [
                'sql' => 'SELECT ht.entity_type, ht.entity_id, ht.stage, ht.owner_role
                          FROM handoff_tracking ht
                          JOIN dispatch_entries de ON de.id = ht.entity_id
                          WHERE ht.entity_type = "dispatch_entry"
                            AND ht.released_since IS NULL
                            AND LOWER(COALESCE(de.dispatch_status, "")) IN ("dispatched", "completed")
                          LIMIT 80',
            ],
            [
                'sql' => 'SELECT ht.entity_type, ht.entity_id, ht.stage, ht.owner_role
                          FROM handoff_tracking ht
                          JOIN daily_orders doo ON doo.id = ht.entity_id
                          WHERE ht.entity_type = "daily_order"
                            AND ht.released_since IS NULL
                            AND LOWER(COALESCE(doo.status, "")) IN ("completed", "closed", "fulfilled")
                          LIMIT 80',
            ],
        ];

        foreach ($checks as $spec) {
            $rows = DB::fetchAll((string)$spec['sql']);
            foreach ($rows as $row) {
                $entityType = trim((string)($row['entity_type'] ?? ''));
                $entityId = (int)($row['entity_id'] ?? 0);
                $stage = trim((string)($row['stage'] ?? ''));
                if ($entityType === '' || $entityId <= 0) {
                    continue;
                }

                NotificationService::create([
                    'recipient_role' => 'App Admin',
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'stage' => $stage,
                    'event_type' => NotificationService::EVENT_MISSING_HANDOFF_COMPLETION,
                    'severity' => NotificationService::SEVERITY_CRITICAL,
                    'title' => 'Missing handoff completion',
                    'message' => 'Workflow item appears completed but handoff stage is still open.',
                    'action_url' => self::actionUrl($entityType, $entityId),
                    'extra_token' => 'missing_handoff|' . date('Ymd'),
                ]);
            }
        }
    }

    private static function sweepWorkflowInconsistency(): void
    {
        $specs = [
            ['table' => 'production_plans', 'entity' => 'production_plan', 'id' => 'id'],
            ['table' => 'qc_entries', 'entity' => 'qc_entry', 'id' => 'id'],
            ['table' => 'dispatch_entries', 'entity' => 'dispatch_entry', 'id' => 'id'],
        ];

        foreach ($specs as $spec) {
            if (!self::tableExists((string)$spec['table'])) {
                continue;
            }

            $rows = DB::fetchAll(
                'SELECT ' . $spec['id'] . ' AS id, workflow_state, approval_status
                 FROM ' . $spec['table'] . '
                 WHERE LOWER(COALESCE(approval_status, "")) = "approved"
                   AND LOWER(COALESCE(workflow_state, "draft")) IN ("draft", "submitted", "reopened")
                 ORDER BY ' . $spec['id'] . ' DESC
                 LIMIT 120'
            );

            foreach ($rows as $row) {
                $entityId = (int)($row['id'] ?? 0);
                if ($entityId <= 0) {
                    continue;
                }

                NotificationService::create([
                    'recipient_role' => 'App Admin',
                    'entity_type' => (string)$spec['entity'],
                    'entity_id' => $entityId,
                    'stage' => trim((string)($row['workflow_state'] ?? 'draft')),
                    'event_type' => NotificationService::EVENT_WORKFLOW_STATE_INCONSISTENT,
                    'severity' => NotificationService::SEVERITY_WARNING,
                    'title' => 'Workflow state inconsistency',
                    'message' => 'Approval status is Approved but workflow state is not aligned.',
                    'action_url' => self::actionUrl((string)$spec['entity'], $entityId),
                    'extra_token' => 'workflow_inconsistent|' . date('Ymd'),
                ]);
            }
        }
    }

    private static function actionUrl(string $entityType, int $entityId): string
    {
        return match ($entityType) {
            'daily_order' => '/apps/manufacturing/daily-orders/360?id=' . $entityId,
            'production_plan' => '/apps/manufacturing/production-plans/edit?id=' . $entityId,
            'production_entry' => '/production-entries/edit?id=' . $entityId,
            'assembly_plan' => '/manufacturing/assembly-plans/detail?id=' . $entityId,
            'qc_entry' => '/qc-entries/edit?id=' . $entityId,
            'dispatch_entry' => '/dispatch-entries/edit?id=' . $entityId,
            default => '/ops/notifications',
        };
    }

    private static function tableExists(string $table): bool
    {
        return DB::fetchOne(
            'SELECT 1 AS present FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1',
            [$table]
        ) !== null;
    }
}
