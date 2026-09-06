<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\CustomizationStudio\Services;

use App\Core\DB;
use Apps\Platform\StyleRegistry\Services\ApprovedStyleRegistry;

require_once APP_ROOT . '/apps/Platform/StyleRegistry/Contracts/ApprovedStyleRegistryContract.php';
require_once APP_ROOT . '/apps/Platform/StyleRegistry/Services/ApprovedStyleRegistry.php';

final class VisualCustomizerApprovalRequestService
{
    private static ?bool $_tablesEnsured = null;

    private static function ensureTables(): void
    {
        if (self::$_tablesEnsured === true) {
            return;
        }
        self::$_tablesEnsured = true;

        DB::query("CREATE TABLE IF NOT EXISTS studio_visual_customizer_requests (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            request_id VARCHAR(64) NOT NULL,
            draft_id VARCHAR(64) NULL,
            requested_by_user_id BIGINT UNSIGNED NOT NULL,
            requested_by_handle VARCHAR(190) NOT NULL,
            selected_socket_id VARCHAR(100) NOT NULL DEFAULT 'radius.scale',
            default_value VARCHAR(50) NOT NULL,
            current_value VARCHAR(50) NULL,
            proposed_value VARCHAR(50) NOT NULL,
            diff_summary TEXT NULL,
            validation_status JSON NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'pending_review',
            status_updated_at VARCHAR(40) NULL,
            decision JSON NULL,
            non_runtime_flags JSON NULL,
            applied_at VARCHAR(40) NULL,
            applied_by_handle VARCHAR(190) NULL,
            applied_by_user_id BIGINT UNSIGNED NULL,
            registry_target JSON NULL,
            created_at VARCHAR(40) NOT NULL,
            created_ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_vc_req_request_id (request_id),
            KEY idx_vc_req_user_status (requested_by_user_id, status),
            KEY idx_vc_req_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", []);
    }

    private static function dbRowToRequest(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }
        return [
            'request_id' => $row['request_id'],
            'draft_id' => $row['draft_id'],
            'requested_by' => $row['requested_by_handle'],
            'requested_by_user_id' => (int)$row['requested_by_user_id'],
            'selected_socket_id' => $row['selected_socket_id'],
            'default_value' => $row['default_value'],
            'current_value' => $row['current_value'],
            'proposed_value' => $row['proposed_value'],
            'diff_summary' => $row['diff_summary'],
            'validation_status' => $row['validation_status'] !== null ? json_decode($row['validation_status'], true) : [],
            'status' => $row['status'],
            'status_updated_at' => $row['status_updated_at'],
            'decision' => $row['decision'] !== null ? json_decode($row['decision'], true) : null,
            'non_runtime_flags' => $row['non_runtime_flags'] !== null ? json_decode($row['non_runtime_flags'], true) : [
                'apply_enabled' => false,
                'registry_write' => false,
                'shell_consumption' => false,
                'public_assets_output' => false,
                'runtime_activation' => false,
            ],
            'applied_at' => $row['applied_at'],
            'applied_by' => $row['applied_by_handle'],
            'applied_by_user_id' => $row['applied_by_user_id'] !== null ? (int)$row['applied_by_user_id'] : null,
            'registry_target' => $row['registry_target'] !== null ? json_decode($row['registry_target'], true) : null,
            'created_at' => $row['created_at'],
        ];
    }

    private static function insertRequest(array $request): void
    {
        DB::query(
            "INSERT INTO studio_visual_customizer_requests
                (request_id, draft_id, requested_by_user_id, requested_by_handle,
                 selected_socket_id, default_value, current_value, proposed_value,
                 diff_summary, validation_status, status, status_updated_at,
                 decision, non_runtime_flags, applied_at, applied_by_handle,
                 applied_by_user_id, registry_target, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $request['request_id'] ?? '',
                $request['draft_id'] ?? null,
                (int)($request['requested_by_user_id'] ?? 0),
                $request['requested_by'] ?? '',
                $request['selected_socket_id'] ?? 'radius.scale',
                $request['default_value'] ?? '',
                $request['current_value'] ?? null,
                $request['proposed_value'] ?? '',
                $request['diff_summary'] ?? null,
                isset($request['validation_status']) ? json_encode($request['validation_status']) : null,
                $request['status'] ?? 'pending_review',
                $request['status_updated_at'] ?? null,
                isset($request['decision']) ? json_encode($request['decision']) : null,
                isset($request['non_runtime_flags']) ? json_encode($request['non_runtime_flags']) : null,
                $request['applied_at'] ?? null,
                $request['applied_by'] ?? null,
                isset($request['applied_by_user_id']) ? (int)$request['applied_by_user_id'] : null,
                isset($request['registry_target']) ? json_encode($request['registry_target']) : null,
                $request['created_at'] ?? gmdate('c'),
            ]
        );
    }

    private static function updateRequestRow(array $request): void
    {
        DB::query(
            "UPDATE studio_visual_customizer_requests SET
                draft_id = ?, requested_by_user_id = ?, requested_by_handle = ?,
                selected_socket_id = ?, default_value = ?, current_value = ?,
                proposed_value = ?, diff_summary = ?, validation_status = ?,
                status = ?, status_updated_at = ?, decision = ?,
                non_runtime_flags = ?, applied_at = ?, applied_by_handle = ?,
                applied_by_user_id = ?, registry_target = ?
            WHERE request_id = ?",
            [
                $request['draft_id'] ?? null,
                (int)($request['requested_by_user_id'] ?? 0),
                $request['requested_by'] ?? '',
                $request['selected_socket_id'] ?? 'radius.scale',
                $request['default_value'] ?? '',
                $request['current_value'] ?? null,
                $request['proposed_value'] ?? '',
                $request['diff_summary'] ?? null,
                isset($request['validation_status']) ? json_encode($request['validation_status']) : null,
                $request['status'] ?? 'pending_review',
                $request['status_updated_at'] ?? null,
                isset($request['decision']) ? json_encode($request['decision']) : null,
                isset($request['non_runtime_flags']) ? json_encode($request['non_runtime_flags']) : null,
                $request['applied_at'] ?? null,
                $request['applied_by'] ?? null,
                isset($request['applied_by_user_id']) ? (int)$request['applied_by_user_id'] : null,
                isset($request['registry_target']) ? json_encode($request['registry_target']) : null,
                $request['request_id'] ?? '',
            ]
        );
    }

    public static function createRequest(array $user, array $draft, array $socketConfig, array $validationResult): array
    {
        self::ensureTables();

        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            return ['ok' => false, 'error' => 'invalid_user'];
        }

        $existing = self::findPendingRequest($userId);
        if ($existing !== null) {
            return [
                'ok' => false,
                'error' => 'already_pending',
                'existing_request_id' => $existing['request_id'],
                'existing_request' => $existing,
            ];
        }

        $now = gmdate('c');
        $socketId = (string)($socketConfig['id'] ?? 'radius.scale');
        $defaultValue = (string)($socketConfig['default_value'] ?? 'soft');
        $socketLabel = (string)($socketConfig['label'] ?? 'Corner scale');

        $requestId = 'vc-req-' . bin2hex(random_bytes(16));

        $proposedValue = '';
        $draftValues = isset($draft['values']) && is_array($draft['values']) ? $draft['values'] : [];
        if (isset($draftValues[$socketId]['proposed_value'])) {
            $proposedValue = (string)$draftValues[$socketId]['proposed_value'];
        }

        $diffSummary = $socketLabel . ' changed from ' . $defaultValue . ' to ' . $proposedValue;

        $request = [
            'request_id' => $requestId,
            'draft_id' => (string)($draft['draft_id'] ?? ''),
            'requested_by' => (string)($user['handle'] ?? $user['username'] ?? ''),
            'requested_by_user_id' => $userId,
            'selected_socket_id' => $socketId,
            'default_value' => $defaultValue,
            'current_value' => null,
            'proposed_value' => $proposedValue,
            'diff_summary' => $diffSummary,
            'validation_status' => $validationResult,
            'status' => 'pending_review',
            'created_at' => $now,
            'non_runtime_flags' => [
                'apply_enabled' => false,
                'registry_write' => false,
                'shell_consumption' => false,
                'public_assets_output' => false,
                'runtime_activation' => false,
            ],
        ];

        self::insertRequest($request);

        return ['ok' => true, 'request' => $request, 'request_id' => $requestId];
    }

    /**
     * @param array<string,mixed> $user
     * @return array<int,array<string,mixed>>
     */
    public static function listRequestsForUser(array $user): array
    {
        self::ensureTables();

        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            return [];
        }

        $rows = DB::fetchAll(
            "SELECT * FROM studio_visual_customizer_requests
            WHERE requested_by_user_id = ?
            ORDER BY created_at DESC",
            [$userId]
        );

        $requests = [];
        foreach ($rows as $row) {
            $converted = self::dbRowToRequest($row);
            if ($converted !== null) {
                $requests[] = $converted;
            }
        }

        return $requests;
    }

    /**
     * @param array<string,mixed> $user
     * @return array<string,mixed>|null
     */
    public static function getRequestById(array $user, string $requestId): ?array
    {
        self::ensureTables();

        if (trim($requestId) === '') {
            return null;
        }

        $row = DB::fetchOne(
            "SELECT * FROM studio_visual_customizer_requests WHERE request_id = ? LIMIT 1",
            [$requestId]
        );

        return self::dbRowToRequest($row);
    }

    /**
     * Find a request artifact globally by request_id.
     * Returns ['request' => array, 'user_id' => int] or null.
     * @return array{request:array<string,mixed>,user_id:int}|null
     */
    public static function findRequestGlobally(string $requestId): ?array
    {
        self::ensureTables();

        if (trim($requestId) === '') {
            return null;
        }

        $row = DB::fetchOne(
            "SELECT * FROM studio_visual_customizer_requests WHERE request_id = ? LIMIT 1",
            [$requestId]
        );

        if ($row === null) {
            return null;
        }

        return [
            'request' => self::dbRowToRequest($row),
            'user_id' => (int)$row['requested_by_user_id'],
        ];
    }

    public static function approveRequest(string $requestId, array $reviewer): array
    {
        return self::updateRequestDecision($requestId, $reviewer, false, 'approved', '');
    }

    public static function rejectRequest(string $requestId, array $reviewer, string $reason): array
    {
        return self::updateRequestDecision($requestId, $reviewer, false, 'rejected', $reason);
    }

    public static function cancelRequest(string $requestId, array $actor, string $reason = ''): array
    {
        return self::updateRequestDecision($requestId, $actor, true, 'cancelled', $reason);
    }

    public static function applyRequest(string $requestId, array $actor): array
    {
        self::ensureTables();

        if (trim($requestId) === '') {
            return ['ok' => false, 'error' => 'missing_request_id'];
        }

        $found = self::findRequestGlobally($requestId);
        if ($found === null) {
            return ['ok' => false, 'error' => 'request_not_found'];
        }

        $request = $found['request'];
        $userId = $found['user_id'];

        $actorHandle = (string)($actor['handle'] ?? $actor['username'] ?? '');
        $requesterHandle = (string)($request['requested_by'] ?? '');
        $requesterUserId = (int)($request['requested_by_user_id'] ?? 0);
        $actorUserId = (int)($actor['id'] ?? 0);
        $isRequester = ($actorHandle !== '' && $actorHandle === $requesterHandle)
            || ($requesterUserId > 0 && $actorUserId > 0 && $actorUserId === $requesterUserId);

        if (!$isRequester) {
            return ['ok' => false, 'error' => 'not_requester'];
        }

        $status = (string)($request['status'] ?? '');
        if ($status !== 'approved_for_future_apply') {
            return ['ok' => false, 'error' => 'invalid_status', 'current_status' => $status];
        }

        $socketId = (string)($request['selected_socket_id'] ?? '');
        if ($socketId !== 'radius.scale') {
            return ['ok' => false, 'error' => 'invalid_socket', 'socket' => $socketId];
        }

        $proposedValue = (string)($request['proposed_value'] ?? '');
        $allowedValues = ['sharp', 'soft', 'round'];
        if ($proposedValue === '' || !in_array($proposedValue, $allowedValues, true)) {
            return ['ok' => false, 'error' => 'invalid_value', 'value' => $proposedValue];
        }

        if (isset($request['applied_at']) && $request['applied_at'] !== null) {
            return ['ok' => false, 'error' => 'already_applied', 'applied_at' => $request['applied_at']];
        }

        $snapshot = VisualCustomizerSnapshotService::getSnapshotByRequestId($requestId);
        if ($snapshot === null) {
            return ['ok' => false, 'error' => 'snapshot_not_found'];
        }

        $snapshotStatus = (string)($snapshot['status'] ?? '');
        if ($snapshotStatus !== 'snapshot_taken') {
            return ['ok' => false, 'error' => 'snapshot_status_invalid', 'snapshot_status' => $snapshotStatus];
        }

        $registry = new ApprovedStyleRegistry();
        if (!$registry->isWritable($socketId)) {
            return ['ok' => false, 'error' => 'socket_not_writable', 'socket' => $socketId];
        }

        $now = gmdate('c');
        $applyResult = $registry->setValue($socketId, $proposedValue, [
            'request_id' => $requestId,
            'snapshot_id' => (string)($snapshot['snapshot_id'] ?? ''),
            'applied_by_user_id' => $actorUserId,
        ]);

        if (empty($applyResult['ok'])) {
            return ['ok' => false, 'error' => 'registry_write_failed', 'registry_error' => $applyResult['error'] ?? ''];
        }

        $request['applied_at'] = $now;
        $request['applied_by'] = $actorHandle;
        $request['applied_by_user_id'] = $actorUserId;
        $request['registry_target'] = [
            'socket_id' => $socketId,
            'value' => $proposedValue,
            'previous_value' => (string)($request['default_value'] ?? ''),
        ];

        self::updateRequestRow($request);

        $snapshotWriteOk = VisualCustomizerSnapshotService::updateSnapshotAfterApply(
            (string)($snapshot['snapshot_id'] ?? ''),
            $now
        );
        if (!$snapshotWriteOk) {
            return ['ok' => false, 'error' => 'snapshot_artifact_update_failed', 'registry_updated' => true];
        }

        return [
            'ok' => true,
            'request_id' => $requestId,
            'status' => 'approved_for_future_apply',
            'applied_at' => $now,
            'socket' => $socketId,
            'value' => $proposedValue,
        ];
    }

    /**
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    private static function updateRequestDecision(string $requestId, array $actor, bool $isCancel, string $decisionType, string $reason): array
    {
        self::ensureTables();

        if (trim($requestId) === '') {
            return ['ok' => false, 'error' => 'missing_request_id'];
        }

        $found = self::findRequestGlobally($requestId);
        if ($found === null) {
            return ['ok' => false, 'error' => 'request_not_found'];
        }

        $request = $found['request'];

        $currentStatus = (string)($request['status'] ?? '');
        if ($currentStatus !== 'pending_review') {
            return [
                'ok' => false,
                'error' => 'invalid_status',
                'current_status' => $currentStatus,
            ];
        }

        $actorHandle = (string)($actor['handle'] ?? $actor['username'] ?? '');
        $requesterByHandle = (string)($request['requested_by'] ?? '');
        $requesterUserId = (int)($request['requested_by_user_id'] ?? 0);
        $actorUserId = (int)($actor['id'] ?? 0);

        $isRequester = ($actorHandle !== '' && $actorHandle === $requesterByHandle)
            || ($requesterUserId > 0 && $actorUserId > 0 && $actorUserId === $requesterUserId);

        if ($isCancel) {
            if (!$isRequester) {
                return ['ok' => false, 'error' => 'not_requester'];
            }
        } else {
            if ($isRequester) {
                return ['ok' => false, 'error' => 'self_approval_disallowed'];
            }
            if ($actorHandle === '') {
                return ['ok' => false, 'error' => 'invalid_reviewer'];
            }
        }

        $now = gmdate('c');

        $request['status'] = $decisionType === 'approved' ? 'approved_for_future_apply'
            : ($decisionType === 'rejected' ? 'review_rejected' : 'review_cancelled');
        $request['status_updated_at'] = $now;

        $decision = [
            'decision' => $decisionType,
            'reason' => $reason,
            'decided_at' => $now,
        ];

        if ($isCancel) {
            $decision['cancelled_by'] = $actorHandle;
        } else {
            $decision['reviewed_by'] = $actorHandle;
        }

        $request['decision'] = $decision;

        $request['non_runtime_flags'] = $request['non_runtime_flags'] ?? [
            'apply_enabled' => false,
            'registry_write' => false,
            'shell_consumption' => false,
            'public_assets_output' => false,
            'runtime_activation' => false,
        ];

        self::updateRequestRow($request);

        return ['ok' => true, 'request' => $request];
    }

    private static function findPendingRequest(int $userId): ?array
    {
        self::ensureTables();

        $row = DB::fetchOne(
            "SELECT * FROM studio_visual_customizer_requests
            WHERE requested_by_user_id = ? AND status = 'pending_review'
            LIMIT 1",
            [$userId]
        );

        return self::dbRowToRequest($row);
    }
}
