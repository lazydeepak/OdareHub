<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

require_once dirname(__DIR__, 3) . '/Services/StudioDeletionSnapshotService.php';

use Apps\Studio\Services\StudioDeletionSnapshotService;

/** Owner Structure adapter for canonical deletion snapshot creation. */
final class OwnerStructureDeletionSnapshotService
{
    /** @return array<string,mixed> */
    public static function create(
        array $selectedOwner,
        array $snapshotReadiness,
        array $input,
        ?array $actor,
        ?string $root = null
    ): array {
        $ownerKey = trim((string)($selectedOwner['owner_key'] ?? ''));
        $packetOwnerKey = trim((string)($snapshotReadiness['current_packet']['target']['owner_key'] ?? ''));
        if ($ownerKey === '' || $packetOwnerKey === '' || $ownerKey !== $packetOwnerKey) {
            return [
                'status' => 'error',
                'effect' => StudioDeletionSnapshotService::EFFECT,
                'snapshot_version' => StudioDeletionSnapshotService::VERSION,
                'snapshot_state' => 'not_created',
                'recorded' => 'no',
                'immutable' => 'yes',
                'append_only' => 'yes',
                'atomic_publish' => 'yes',
                'can_execute' => 'no',
                'can_apply' => 'no',
                'can_archive' => 'no',
                'can_delete' => 'no',
                'execution_authorized' => 'no',
                'grants_execution_authority' => 'no',
                'requires_separate_execution_capability' => 'yes',
                'diagnostics' => [[
                    'code' => 'OSS_DELETION_SNAPSHOT_OWNER_MISMATCH',
                    'severity' => 'error',
                    'message' => 'Selected owner does not match the claimed snapshot target.',
                    'path' => $ownerKey,
                ]],
            ];
        }
        return StudioDeletionSnapshotService::create($snapshotReadiness, $input, $actor, $root);
    }
}
