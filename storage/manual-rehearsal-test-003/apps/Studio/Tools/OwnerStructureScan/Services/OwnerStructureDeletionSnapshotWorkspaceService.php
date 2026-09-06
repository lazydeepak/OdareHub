<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

require_once __DIR__ . '/OwnerStructureDeletionSnapshotService.php';
require_once dirname(__DIR__, 3) . '/Services/StudioDeletionSnapshotStore.php';

use Apps\Studio\Services\StudioDeletionSnapshotStore;

/** Presenter for the governed snapshot creation workspace. */
final class OwnerStructureDeletionSnapshotWorkspaceService
{
    /** @return array<string,mixed> */
    public static function build(
        array $owners,
        string $selectedOwnerKey,
        bool $requested,
        ?array $snapshotReadiness,
        ?array $flash = null,
        ?string $root = null,
        ?callable $reader = null
    ): array {
        $selectedOwner = self::findOwner($owners, $selectedOwnerKey);
        $base = [
            'requested' => $requested,
            'status' => $requested ? 'loading' : 'idle',
            'selected_owner_key' => $selectedOwnerKey,
            'selected_owner' => $selectedOwner,
            'snapshot_readiness' => $snapshotReadiness,
            'can_create' => 'no',
            'source' => [],
            'snapshot_contract' => [],
            'actor_evidence' => [],
            'claim_evidence' => [],
            'existing_snapshot' => null,
            'flash' => $flash,
            'diagnostics' => [],
        ];
        if (!$requested) {
            return $base;
        }
        if ($selectedOwner === null) {
            return self::error($base, 'OSS_DELETION_SNAPSHOT_OWNER_UNAVAILABLE', 'The selected owner is unavailable for snapshot creation.', $selectedOwnerKey);
        }
        if ($snapshotReadiness === null) {
            return self::error($base, 'OSS_DELETION_SNAPSHOT_READINESS_REQUIRED', 'Current snapshot readiness is required before creating the snapshot.', $selectedOwnerKey);
        }

        $currentPacket = self::arrayValue($snapshotReadiness, 'current_packet');
        $target = self::arrayValue($currentPacket, 'target');
        $claim = self::arrayValue($snapshotReadiness, 'claim_evidence');
        $contract = self::arrayValue($snapshotReadiness, 'snapshot_contract');
        $actorEvidence = self::arrayValue($snapshotReadiness, 'actor_evidence');
        $ownerKey = trim((string)($target['owner_key'] ?? ''));
        $destinationPath = self::normalizePath((string)($contract['destination_path'] ?? ''));
        $readinessFingerprint = trim((string)($snapshotReadiness['snapshot_readiness_fingerprint'] ?? ''));
        $claimId = trim((string)($claim['claim_id'] ?? ''));
        $claimFingerprint = trim((string)($claim['claim_fingerprint'] ?? ''));
        if ($ownerKey !== $selectedOwnerKey || $destinationPath === '' || $readinessFingerprint === '' || $claimId === '' || $claimFingerprint === '') {
            return self::error($base, 'OSS_DELETION_SNAPSHOT_PACKET_INVALID', 'Snapshot readiness is missing identity or targets a different owner.', $selectedOwnerKey);
        }

        try {
            $existing = $reader !== null
                ? $reader($destinationPath, $root)
                : StudioDeletionSnapshotStore::read($destinationPath, $root);
        } catch (\Throwable $exception) {
            return self::error($base, 'OSS_DELETION_SNAPSHOT_HISTORY_FAILED', $exception->getMessage() !== '' ? $exception->getMessage() : 'Snapshot evidence could not be read.', $selectedOwnerKey);
        }

        $canCreate = (string)($snapshotReadiness['snapshot_readiness'] ?? '') === 'ready'
            && (string)($snapshotReadiness['snapshot_ready'] ?? 'no') === 'yes'
            && (string)($snapshotReadiness['claim_valid'] ?? 'no') === 'yes'
            && (string)($snapshotReadiness['executor_identity_valid'] ?? 'no') === 'yes'
            && (string)($snapshotReadiness['source_available'] ?? 'no') === 'yes'
            && (string)($snapshotReadiness['destination_available'] ?? 'no') === 'yes'
            && self::stringList($snapshotReadiness['blocking_reasons'] ?? []) === []
            && !is_array($existing);

        $base['status'] = 'ready';
        $base['can_create'] = $canCreate ? 'yes' : 'no';
        $base['source'] = [
            'snapshot_readiness_fingerprint' => $readinessFingerprint,
            'claim_id' => $claimId,
            'claim_fingerprint' => $claimFingerprint,
        ];
        $base['snapshot_contract'] = $contract;
        $base['actor_evidence'] = $actorEvidence;
        $base['claim_evidence'] = $claim;
        $base['existing_snapshot'] = is_array($existing) ? $existing : null;
        if (!$canCreate) {
            $base['diagnostics'][] = [
                'code' => is_array($existing) ? 'OSS_DELETION_SNAPSHOT_ALREADY_EXISTS' : 'OSS_DELETION_SNAPSHOT_NOT_READY',
                'severity' => 'error',
                'message' => is_array($existing)
                    ? 'The deterministic snapshot already exists and cannot be overwritten.'
                    : 'The current claim and snapshot preflight are not ready for creation.',
                'path' => $selectedOwnerKey,
            ];
        }
        return $base;
    }

    private static function error(array $base, string $code, string $message, string $path): array
    {
        $base['status'] = 'error';
        $base['diagnostics'][] = ['code' => $code, 'severity' => 'error', 'message' => $message, 'path' => $path];
        return $base;
    }

    private static function findOwner(array $owners, string $selectedOwnerKey): ?array
    {
        foreach ($owners as $owner) {
            if (is_array($owner) && (string)($owner['owner_key'] ?? '') === $selectedOwnerKey) {
                return $owner;
            }
        }
        return null;
    }

    private static function arrayValue(array $source, string $key): array
    {
        return isset($source[$key]) && is_array($source[$key]) ? $source[$key] : [];
    }

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

    private static function normalizePath(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', trim($path)), '/');
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                return '';
            }
            $parts[] = $part;
        }
        return implode('/', $parts);
    }
}
