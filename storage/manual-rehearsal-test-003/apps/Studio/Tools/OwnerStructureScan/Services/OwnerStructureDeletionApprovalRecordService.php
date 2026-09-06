<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

require_once dirname(__DIR__, 3) . '/Services/StudioDeletionApprovalRecordService.php';

use Apps\Studio\Services\StudioDeletionApprovalRecordService;

/** Owner Structure adapter for canonical deletion approval recording. */
final class OwnerStructureDeletionApprovalRecordService
{
    /**
     * @param array<string,mixed> $selectedOwner
     * @param array<string,mixed> $changeSet
     * @param array<string,mixed> $input
     * @param array<string,mixed>|null $actor
     * @return array<string,mixed>
     */
    public static function record(
        array $selectedOwner,
        array $changeSet,
        array $input,
        ?array $actor,
        ?string $root = null
    ): array {
        $ownerKey = trim((string)($selectedOwner['owner_key'] ?? ''));
        $packetOwnerKey = trim((string)($changeSet['target']['owner_key'] ?? ''));
        if ($ownerKey === '' || $ownerKey !== $packetOwnerKey) {
            return [
                'status' => 'error',
                'recorded' => 'no',
                'can_execute' => 'no',
                'grants_execution_authority' => 'no',
                'diagnostics' => [[
                    'code' => 'OSS_DELETION_APPROVAL_OWNER_MISMATCH',
                    'severity' => 'error',
                    'message' => 'Selected owner does not match the deletion change-set target.',
                    'path' => $ownerKey,
                ]],
            ];
        }
        return StudioDeletionApprovalRecordService::record($changeSet, $input, $actor, $root);
    }

    /** @param array<string,mixed> $changeSet @return array<string,mixed>|null */
    public static function latest(array $changeSet, ?string $root = null): ?array
    {
        return StudioDeletionApprovalRecordService::latest($changeSet, $root);
    }
}
