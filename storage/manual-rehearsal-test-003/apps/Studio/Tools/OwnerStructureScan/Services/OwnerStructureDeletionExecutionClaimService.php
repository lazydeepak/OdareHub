<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

require_once dirname(__DIR__, 3) . '/Services/StudioDeletionExecutionClaimService.php';

use Apps\Studio\Services\StudioDeletionExecutionClaimService;

/** Owner Structure adapter for atomic deletion execution request claims. */
final class OwnerStructureDeletionExecutionClaimService
{
    /**
     * @param array<string,mixed> $selectedOwner
     * @param array<string,mixed> $executorReadiness
     * @param array<string,mixed> $input
     * @param array<string,mixed>|null $actor
     * @return array<string,mixed>
     */
    public static function claim(
        array $selectedOwner,
        array $executorReadiness,
        array $input,
        ?array $actor,
        ?string $root = null
    ): array {
        $ownerKey = trim((string)($selectedOwner['owner_key'] ?? ''));
        $packetOwnerKey = trim((string)($executorReadiness['current_packet']['target']['owner_key'] ?? ''));
        if ($ownerKey === '' || $packetOwnerKey === '' || $ownerKey !== $packetOwnerKey) {
            return [
                'status' => 'error',
                'effect' => StudioDeletionExecutionClaimService::EFFECT,
                'claim_version' => StudioDeletionExecutionClaimService::VERSION,
                'recorded' => 'no',
                'immutable' => 'yes',
                'append_only' => 'yes',
                'atomic_single_use' => 'yes',
                'can_execute' => 'no',
                'can_apply' => 'no',
                'can_archive' => 'no',
                'can_delete' => 'no',
                'execution_authorized' => 'no',
                'grants_execution_authority' => 'no',
                'requires_separate_execution_capability' => 'yes',
                'diagnostics' => [[
                    'code' => 'OSS_DELETION_EXECUTION_CLAIM_OWNER_MISMATCH',
                    'severity' => 'error',
                    'message' => 'Selected owner does not match the current execution request target.',
                    'path' => $ownerKey,
                ]],
            ];
        }

        return StudioDeletionExecutionClaimService::claim(
            $executorReadiness,
            $input,
            $actor,
            $root
        );
    }
}
