<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

require_once __DIR__ . '/OwnerStructureDeletionExecutionClaimService.php';
require_once dirname(__DIR__, 3) . '/Services/StudioDeletionExecutionClaimStore.php';

use Apps\Studio\Services\StudioDeletionExecutionClaimStore;

/** Presenter for the atomic, non-executing deletion request claim workspace. */
final class OwnerStructureDeletionExecutionClaimWorkspaceService
{
    /**
     * @param array<int,array<string,mixed>> $owners
     * @param array<string,mixed>|null $executorReadiness
     * @param array<string,mixed>|null $flash
     * @param callable(string,?string):array<string,mixed>|null|null $claimReader
     * @return array<string,mixed>
     */
    public static function build(
        array $owners,
        string $selectedOwnerKey,
        bool $requested,
        ?array $executorReadiness,
        ?array $flash = null,
        ?string $root = null,
        ?callable $claimReader = null
    ): array {
        $selectedOwner = self::findOwner($owners, $selectedOwnerKey);
        $base = [
            'requested' => $requested,
            'status' => $requested ? 'loading' : 'idle',
            'selected_owner_key' => $selectedOwnerKey,
            'selected_owner' => $selectedOwner,
            'executor_readiness' => $executorReadiness,
            'can_claim' => 'no',
            'source' => [],
            'request_evidence' => [],
            'actor_evidence' => [],
            'latest_claim' => null,
            'flash' => $flash,
            'diagnostics' => [],
        ];
        if (!$requested) {
            return $base;
        }
        if ($selectedOwner === null) {
            return self::error($base, 'OSS_DELETION_EXECUTION_CLAIM_OWNER_UNAVAILABLE', 'The selected owner is unavailable for request claiming.', $selectedOwnerKey);
        }
        if ($executorReadiness === null) {
            return self::error($base, 'OSS_DELETION_EXECUTION_CLAIM_READINESS_REQUIRED', 'A current executor-readiness assessment is required before claiming.', $selectedOwnerKey);
        }

        $currentPacket = self::arrayValue($executorReadiness, 'current_packet');
        $requestEvidence = self::arrayValue($executorReadiness, 'request_evidence');
        $actorEvidence = self::arrayValue($executorReadiness, 'actor_evidence');
        $ownerKey = trim((string)($currentPacket['target']['owner_key'] ?? ''));
        $requestId = trim((string)($requestEvidence['request_id'] ?? ''));
        $requestFingerprint = trim((string)($requestEvidence['request_fingerprint'] ?? ''));
        $readinessFingerprint = trim((string)($executorReadiness['executor_readiness_fingerprint'] ?? ''));
        if ($ownerKey !== $selectedOwnerKey || $requestId === '' || $requestFingerprint === '' || $readinessFingerprint === '') {
            return self::error($base, 'OSS_DELETION_EXECUTION_CLAIM_PACKET_INVALID', 'The executor-readiness result is missing identity or targets a different owner.', $selectedOwnerKey);
        }

        try {
            $latestClaim = $claimReader !== null
                ? $claimReader($requestId, $root)
                : StudioDeletionExecutionClaimStore::latest($requestId, $root);
        } catch (\Throwable $exception) {
            return self::error(
                $base,
                'OSS_DELETION_EXECUTION_CLAIM_HISTORY_FAILED',
                $exception->getMessage() !== '' ? $exception->getMessage() : 'Claim history could not be read.',
                $selectedOwnerKey
            );
        }

        $canClaim = (string)($executorReadiness['executor_readiness'] ?? '') === 'ready'
            && (string)($executorReadiness['executor_eligible'] ?? 'no') === 'yes'
            && (string)($executorReadiness['request_valid'] ?? 'no') === 'yes'
            && (string)($executorReadiness['executor_identity_valid'] ?? 'no') === 'yes'
            && (string)($executorReadiness['single_use_available'] ?? 'no') === 'yes'
            && self::stringList($executorReadiness['blocking_reasons'] ?? []) === []
            && !is_array($latestClaim);

        $base['status'] = 'ready';
        $base['can_claim'] = $canClaim ? 'yes' : 'no';
        $base['source'] = [
            'request_id' => $requestId,
            'request_fingerprint' => $requestFingerprint,
            'executor_readiness_fingerprint' => $readinessFingerprint,
        ];
        $base['request_evidence'] = $requestEvidence;
        $base['actor_evidence'] = $actorEvidence;
        $base['latest_claim'] = is_array($latestClaim) ? $latestClaim : null;
        if (!$canClaim) {
            $base['diagnostics'][] = [
                'code' => is_array($latestClaim)
                    ? 'OSS_DELETION_EXECUTION_CLAIM_ALREADY_CONSUMED'
                    : 'OSS_DELETION_EXECUTION_CLAIM_NOT_READY',
                'severity' => 'error',
                'message' => is_array($latestClaim)
                    ? 'This single-use execution request already has claim or use evidence.'
                    : 'The current actor and execution request are not ready for an atomic claim.',
                'path' => $selectedOwnerKey,
            ];
        }
        return $base;
    }

    /** @param array<string,mixed> $base @return array<string,mixed> */
    private static function error(array $base, string $code, string $message, string $path): array
    {
        $base['status'] = 'error';
        $base['diagnostics'][] = ['code' => $code, 'severity' => 'error', 'message' => $message, 'path' => $path];
        return $base;
    }

    /** @param array<int,array<string,mixed>> $owners @return array<string,mixed>|null */
    private static function findOwner(array $owners, string $selectedOwnerKey): ?array
    {
        foreach ($owners as $owner) {
            if (is_array($owner) && (string)($owner['owner_key'] ?? '') === $selectedOwnerKey) {
                return $owner;
            }
        }
        return null;
    }

    /** @return array<string,mixed> */
    private static function arrayValue(array $source, string $key): array
    {
        return isset($source[$key]) && is_array($source[$key]) ? $source[$key] : [];
    }

    /** @return array<int,string> */
    private static function stringList($value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $items = [];
        foreach ($value as $item) {
            $item = trim((string)$item);
            if ($item !== '') {
                $items[$item] = true;
            }
        }
        return array_map('strval', array_keys($items));
    }
}
