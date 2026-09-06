<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\CustomizationStudio\Services;

use App\Core\DB;

final class VisualCustomizerSnapshotService
{
    private static ?bool $_tablesEnsured = null;

    private static function ensureTables(): void
    {
        if (self::$_tablesEnsured === true) {
            return;
        }
        self::$_tablesEnsured = true;

        DB::query("CREATE TABLE IF NOT EXISTS studio_visual_customizer_snapshots (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            snapshot_id VARCHAR(64) NOT NULL,
            request_id VARCHAR(64) NOT NULL,
            socket_id VARCHAR(100) NOT NULL,
            previous_value VARCHAR(50) NOT NULL,
            proposed_value VARCHAR(50) NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'snapshot_taken',
            created_by_user_id BIGINT UNSIGNED NOT NULL,
            created_by_handle VARCHAR(190) NOT NULL,
            created_at VARCHAR(40) NOT NULL,
            applied_at VARCHAR(40) NULL,
            notes TEXT NULL,
            snapshot_data JSON NULL,
            created_ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_vc_snap_snapshot_id (snapshot_id),
            KEY idx_vc_snap_request_id (request_id),
            KEY idx_vc_snap_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", []);
    }

    private static function dbRowToSnapshot(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }
        return [
            'snapshot_id' => $row['snapshot_id'],
            'request_id' => $row['request_id'],
            'socket_id' => $row['socket_id'],
            'created_at' => $row['created_at'],
            'created_by_user_id' => (int)$row['created_by_user_id'],
            'created_by_handle' => $row['created_by_handle'],
            'previous_value' => $row['previous_value'],
            'proposed_value' => $row['proposed_value'],
            'status' => $row['status'],
            'applied_at' => $row['applied_at'],
            'notes' => $row['notes'],
        ];
    }

    private static function insertSnapshot(array $snapshot): void
    {
        DB::query(
            "INSERT INTO studio_visual_customizer_snapshots
                (snapshot_id, request_id, socket_id, previous_value,
                 proposed_value, status, created_by_user_id, created_by_handle,
                 created_at, applied_at, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $snapshot['snapshot_id'] ?? '',
                $snapshot['request_id'] ?? '',
                $snapshot['socket_id'] ?? '',
                $snapshot['previous_value'] ?? '',
                $snapshot['proposed_value'] ?? '',
                $snapshot['status'] ?? 'snapshot_taken',
                (int)($snapshot['created_by_user_id'] ?? 0),
                $snapshot['created_by_handle'] ?? '',
                $snapshot['created_at'] ?? gmdate('c'),
                $snapshot['applied_at'] ?? null,
                $snapshot['notes'] ?? null,
            ]
        );
    }

    public static function takeSnapshot(array $request, array $user): array
    {
        self::ensureTables();

        $requestId = (string)($request['request_id'] ?? '');
        if ($requestId === '') {
            return ['ok' => false, 'error' => 'missing_request_id'];
        }

        $status = (string)($request['status'] ?? '');
        if ($status !== 'approved_for_future_apply') {
            return ['ok' => false, 'error' => 'invalid_status', 'current_status' => $status];
        }

        $existing = self::getSnapshotByRequestId($requestId);
        if ($existing !== null) {
            return ['ok' => false, 'error' => 'snapshot_exists', 'snapshot_id' => $existing['snapshot_id']];
        }

        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            return ['ok' => false, 'error' => 'invalid_user'];
        }

        $requestUserId = (int)($request['requested_by_user_id'] ?? 0);
        $actorUserId = (int)($user['id'] ?? 0);
        if ($requestUserId > 0 && $actorUserId > 0 && $requestUserId !== $actorUserId) {
            $actorHandle = (string)($user['handle'] ?? $user['username'] ?? '');
            $requesterHandle = (string)($request['requested_by'] ?? '');
            $isRequester = ($actorHandle !== '' && $actorHandle === $requesterHandle);
            if (!$isRequester) {
                return ['ok' => false, 'error' => 'not_requester'];
            }
        }

        $now = gmdate('c');
        $snapshotId = 'vc-snap-' . bin2hex(random_bytes(16));

        $snapshot = [
            'snapshot_id' => $snapshotId,
            'request_id' => $requestId,
            'socket_id' => (string)($request['selected_socket_id'] ?? ''),
            'created_at' => $now,
            'created_by_user_id' => $userId,
            'created_by_handle' => (string)($user['handle'] ?? $user['username'] ?? ''),
            'previous_value' => (string)($request['default_value'] ?? ''),
            'proposed_value' => (string)($request['proposed_value'] ?? ''),
            'status' => 'snapshot_taken',
            'applied_at' => null,
            'notes' => '',
        ];

        self::insertSnapshot($snapshot);

        return ['ok' => true, 'snapshot' => $snapshot, 'snapshot_id' => $snapshotId];
    }

    public static function getSnapshotByRequestId(string $requestId): ?array
    {
        self::ensureTables();

        if (trim($requestId) === '') {
            return null;
        }

        $row = DB::fetchOne(
            "SELECT * FROM studio_visual_customizer_snapshots WHERE request_id = ? LIMIT 1",
            [$requestId]
        );

        return self::dbRowToSnapshot($row);
    }

    /**
     * Update a snapshot's applied_at and status after successful Apply.
     */
    public static function updateSnapshotAfterApply(string $snapshotId, string $appliedAt): bool
    {
        self::ensureTables();

        if (trim($snapshotId) === '') {
            return false;
        }

        DB::query(
            "UPDATE studio_visual_customizer_snapshots
            SET status = 'applied', applied_at = ?
            WHERE snapshot_id = ?",
            [$appliedAt, $snapshotId]
        );

        return true;
    }

    /**
     * Get snapshot by its snapshot_id.
     */
    public static function getSnapshotById(string $snapshotId): ?array
    {
        self::ensureTables();

        if (trim($snapshotId) === '') {
            return null;
        }

        $row = DB::fetchOne(
            "SELECT * FROM studio_visual_customizer_snapshots WHERE snapshot_id = ? LIMIT 1",
            [$snapshotId]
        );

        return self::dbRowToSnapshot($row);
    }
}
