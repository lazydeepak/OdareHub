<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

require_once dirname(__DIR__, 3) . '/Services/StudioDeletionExecutionRequestService.php';

use Apps\Studio\Services\StudioDeletionExecutionRequestService;

/** Owner Structure adapter for immutable deletion execution requests. */
final class OwnerStructureDeletionExecutionRequestService
{
    /**
     * @param array<string,mixed> $selectedOwner
     * @param array<string,mixed> $changeSet
     * @param array<string,mixed> $readiness
     * @param array<string,mixed> $dryRun
     * @param array<string,mixed> $input
     * @param array<string,mixed>|null $requester
     * @return array<string,mixed>
     */
    public static function record(
        array $selectedOwner,
        array $changeSet,
        array $readiness,
        array $dryRun,
        array $input,
        ?array $requester,
        ?string $root = null
    ): array {
        $ownerKey = trim((string)($selectedOwner['owner_key'] ?? ''));
        $packetOwnerKey = trim((string)($changeSet['target']['owner_key'] ?? ''));
        if ($ownerKey === '' || $ownerKey !== $packetOwnerKey) {
            return [
                'status' => 'error',
                'recorded' => 'no',
                'can_execute' => 'no',
                'execution_authorized' => 'no',
                'grants_execution_authority' => 'no',
                'diagnostics' => [[
                    'code' => 'OSS_DELETION_EXECUTION_REQUEST_OWNER_MISMATCH',
                    'severity' => 'error',
                    'message' => 'Selected owner does not match the deletion execution request target.',
                    'path' => $ownerKey,
                ]],
            ];
        }
        return StudioDeletionExecutionRequestService::record($changeSet, $readiness, $dryRun, $input, $requester, $root);
    }

    /** @return array<string,mixed>|null */
    public static function latest(array $changeSet, array $dryRun, ?string $root = null): ?array
    {
        return StudioDeletionExecutionRequestService::latest($changeSet, $dryRun, $root);
    }
}
