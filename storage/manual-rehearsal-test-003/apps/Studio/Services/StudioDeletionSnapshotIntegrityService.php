<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

/**
 * Read-only post-snapshot verifier. It validates the immutable manifest,
 * every copied payload checksum, the live source tree, packet bindings,
 * claim provenance, request validity, and executor identity.
 */
final class StudioDeletionSnapshotIntegrityService
{
    public const EFFECT = 'verify';
    public const VERSION = 'studio.deletion-snapshot-integrity.v1';

    public const STATE_READY = 'ready';
    public const STATE_SNAPSHOT_MISSING = 'snapshot_missing';
    public const STATE_STALE = 'stale';
    public const STATE_EXPIRED = 'expired';
    public const STATE_WRONG_EXECUTOR = 'wrong_executor';
    public const STATE_MANIFEST_INVALID = 'manifest_invalid';
    public const STATE_CHECKSUM_MISMATCH = 'checksum_mismatch';
    public const STATE_SOURCE_CHANGED = 'source_changed';
    public const STATE_BLOCKED = 'blocked';
    public const STATE_UNKNOWN = 'unknown';

    /**
     * @param array<string,mixed> $changeSet
     * @param array<string,mixed> $approvalReadiness
     * @param array<string,mixed> $dryRun
     * @param array<string,mixed> $executorReadiness
     * @param array<string,mixed>|null $claim
     * @param array<string,mixed>|null $manifest
     * @param array<string,mixed>|null $actor
     * @param callable(string,string):array<string,mixed>|null $inspector
     * @param callable():mixed|null $clock
     * @return array<string,mixed>
     */
    public static function assess(
        array $changeSet,
        array $approvalReadiness,
        array $dryRun,
        array $executorReadiness,
        ?array $claim,
        ?array $manifest,
        ?array $actor,
        ?string $root = null,
        ?callable $inspector = null,
        ?callable $clock = null
    ): array {
        $target = self::arr($changeSet, 'target');
        $snapshotPlan = self::arr($dryRun, 'snapshot_plan');
        $request = self::arr($executorReadiness, 'request_evidence');
        $currentPacket = self::arr($executorReadiness, 'current_packet');
        $targetPath = self::path((string)($target['target_path'] ?? ''));
        $destinationPath = self::path((string)($snapshotPlan['destination_path'] ?? ''));
        $ownerKey = trim((string)($target['owner_key'] ?? ''));
        $source = [
            'change_set_fingerprint' => trim((string)($changeSet['change_set_fingerprint'] ?? '')),
            'plan_fingerprint' => trim((string)($changeSet['source_plan']['fingerprint'] ?? '')),
            'readiness_fingerprint' => trim((string)($approvalReadiness['readiness_fingerprint'] ?? '')),
            'dry_run_fingerprint' => trim((string)($dryRun['dry_run_fingerprint'] ?? '')),
            'approval_record_id' => trim((string)($approvalReadiness['approval_evidence']['record_id'] ?? '')),
            'approval_record_fingerprint' => trim((string)($approvalReadiness['approval_evidence']['record_fingerprint'] ?? '')),
            'request_id' => trim((string)($request['request_id'] ?? '')),
            'request_fingerprint' => trim((string)($request['request_fingerprint'] ?? '')),
            'claim_id' => trim((string)($claim['claim_id'] ?? '')),
            'claim_fingerprint' => trim((string)($claim['claim_fingerprint'] ?? '')),
        ];

        $packetProblems = self::packetProblems(
            $changeSet,
            $approvalReadiness,
            $dryRun,
            $executorReadiness,
            $claim,
            $source,
            $currentPacket,
            $target,
            $ownerKey,
            $targetPath,
            $destinationPath
        );
        if ($packetProblems !== []) {
            return self::result(
                self::STATE_UNKNOWN,
                $source,
                $target,
                $destinationPath,
                $manifest,
                $claim,
                $actor,
                [],
                [],
                $packetProblems,
                [self::diag('DELETION_SNAPSHOT_INTEGRITY_PACKET_INVALID', 'Snapshot integrity requires the exact current approved, claimed, and simulated deletion packet.', ['reasons' => $packetProblems])]
            );
        }

        if ($manifest === null) {
            return self::result(
                self::STATE_SNAPSHOT_MISSING,
                $source,
                $target,
                $destinationPath,
                null,
                $claim,
                $actor,
                [],
                [],
                ['SNAPSHOT_MANIFEST_REQUIRED'],
                [self::diag('DELETION_SNAPSHOT_INTEGRITY_MANIFEST_MISSING', 'No immutable snapshot manifest exists at the deterministic destination.')]
            );
        }

        $manifestProblems = self::manifestProblems($manifest, $source, $target, $destinationPath, $claim);
        if ($manifestProblems !== []) {
            $stale = self::hasPrefix($manifestProblems, 'MANIFEST_SOURCE_MISMATCH:')
                || in_array('MANIFEST_TARGET_MISMATCH', $manifestProblems, true)
                || in_array('MANIFEST_CLAIM_MISMATCH', $manifestProblems, true);
            return self::result(
                $stale ? self::STATE_STALE : self::STATE_MANIFEST_INVALID,
                $source,
                $target,
                $destinationPath,
                $manifest,
                $claim,
                $actor,
                [],
                [],
                $manifestProblems,
                [self::diag($stale ? 'DELETION_SNAPSHOT_INTEGRITY_MANIFEST_STALE' : 'DELETION_SNAPSHOT_INTEGRITY_MANIFEST_INVALID', $stale ? 'The snapshot manifest is bound to an older deletion packet or claim.' : 'The snapshot manifest failed integrity, provenance, or authority-boundary validation.', ['reasons' => $manifestProblems])]
            );
        }

        $now = self::now($clock);
        $expiresAt = self::utc((string)($request['expires_at_utc'] ?? ''));
        if ($expiresAt === null) {
            return self::result(self::STATE_BLOCKED, $source, $target, $destinationPath, $manifest, $claim, $actor, [], [], ['EXECUTION_REQUEST_EXPIRY_INVALID'], [self::diag('DELETION_SNAPSHOT_INTEGRITY_EXPIRY_INVALID', 'The execution request expiry is missing or invalid.')]);
        }
        if ($expiresAt <= $now) {
            return self::result(self::STATE_EXPIRED, $source, $target, $destinationPath, $manifest, $claim, $actor, [], [], ['EXECUTION_REQUEST_EXPIRED'], [self::diag('DELETION_SNAPSHOT_INTEGRITY_REQUEST_EXPIRED', 'The claimed execution request expired before post-snapshot execution readiness was verified.')]);
        }

        $actorRecord = self::actor($actor ?? []);
        $claimExecutor = self::arr($claim ?? [], 'executor');
        $manifestExecutor = self::arr($manifest, 'executor');
        $executorMatches = $actorRecord['actor_id'] !== ''
            && (string)($claimExecutor['actor_id'] ?? '') !== ''
            && hash_equals((string)$claimExecutor['actor_id'], $actorRecord['actor_id'])
            && (string)($manifestExecutor['actor_id'] ?? '') !== ''
            && hash_equals((string)$manifestExecutor['actor_id'], $actorRecord['actor_id']);
        if (!self::isPlatformAdmin($actor) || !$executorMatches) {
            $reasons = [];
            if (!self::isPlatformAdmin($actor)) {
                $reasons[] = 'CURRENT_ACTOR_NOT_PLATFORM_ADMIN';
            }
            if (!$executorMatches) {
                $reasons[] = 'CURRENT_ACTOR_NOT_SNAPSHOT_EXECUTOR';
            }
            return self::result(self::STATE_WRONG_EXECUTOR, $source, $target, $destinationPath, $manifest, $claim, $actor, [], [], $reasons, [self::diag('DELETION_SNAPSHOT_INTEGRITY_WRONG_EXECUTOR', 'The current actor is not the platform-admin executor bound to the claim and snapshot manifest.')]);
        }

        $repositoryRoot = self::root($root);
        $inspect = $inspector ?? static function (string $relativePath, string $kind) use ($repositoryRoot): array {
            $absolute = $repositoryRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            return [
                'path' => $relativePath,
                'kind' => $kind,
                'exists' => file_exists($absolute) ? 'yes' : 'no',
                'is_file' => is_file($absolute) ? 'yes' : 'no',
                'is_dir' => is_dir($absolute) ? 'yes' : 'no',
                'is_link' => is_link($absolute) ? 'yes' : 'no',
                'readable' => is_readable($absolute) ? 'yes' : 'no',
                'size_bytes' => is_file($absolute) && filesize($absolute) !== false ? (int)filesize($absolute) : null,
                'sha256' => is_file($absolute) && is_readable($absolute) ? (string)(hash_file('sha256', $absolute) ?: '') : '',
            ];
        };

        [$snapshotChecks, $snapshotProblems, $actualSnapshotFiles] = self::verifySnapshotPayload($manifest, $destinationPath, $inspect, $repositoryRoot);
        if ($snapshotProblems !== []) {
            return self::result(
                self::STATE_CHECKSUM_MISMATCH,
                $source,
                $target,
                $destinationPath,
                $manifest,
                $claim,
                $actor,
                $snapshotChecks,
                [],
                $snapshotProblems,
                [self::diag('DELETION_SNAPSHOT_INTEGRITY_CHECKSUM_MISMATCH', 'Snapshot payload files do not match the immutable manifest.', ['reasons' => $snapshotProblems])],
                $actualSnapshotFiles
            );
        }

        [$sourceChecks, $sourceProblems, $actualSourceFiles] = self::verifySourceStillMatches($manifest, $targetPath, $destinationPath, $inspect, $repositoryRoot);
        if ($sourceProblems !== []) {
            return self::result(
                self::STATE_SOURCE_CHANGED,
                $source,
                $target,
                $destinationPath,
                $manifest,
                $claim,
                $actor,
                $snapshotChecks,
                $sourceChecks,
                $sourceProblems,
                [self::diag('DELETION_SNAPSHOT_INTEGRITY_SOURCE_CHANGED', 'The live deletion target no longer matches the rollback snapshot.', ['reasons' => $sourceProblems])],
                $actualSnapshotFiles,
                $actualSourceFiles
            );
        }

        return self::result(
            self::STATE_READY,
            $source,
            $target,
            $destinationPath,
            $manifest,
            $claim,
            $actor,
            $snapshotChecks,
            $sourceChecks,
            [],
            [],
            $actualSnapshotFiles,
            $actualSourceFiles
        );
    }

    /** @return array<int,string> */
    private static function packetProblems(array $changeSet, array $approval, array $dryRun, array $executor, ?array $claim, array $source, array $current, array $target, string $ownerKey, string $targetPath, string $destinationPath): array
    {
        $problems = [];
        if ($ownerKey === '' || $targetPath === '' || $destinationPath === '' || in_array('', array_values($source), true)) {
            $problems[] = 'CURRENT_PACKET_IDENTITY_MISSING';
        }
        if ((string)($changeSet['immutable'] ?? 'no') !== 'yes' || (string)($changeSet['can_execute'] ?? 'yes') !== 'no' || (string)($changeSet['can_apply'] ?? 'yes') !== 'no') {
            $problems[] = 'CHANGE_SET_CONTRACT_INVALID';
        }
        if ((string)($approval['effect'] ?? '') !== 'verify' || (string)($approval['execution_readiness'] ?? '') !== 'ready' || (string)($approval['execution_eligible'] ?? 'no') !== 'yes' || (string)($approval['approval_valid'] ?? 'no') !== 'yes') {
            $problems[] = 'APPROVAL_READINESS_INVALID';
        }
        foreach (['can_execute', 'can_apply', 'grants_execution_authority'] as $field) {
            if ((string)($approval[$field] ?? 'yes') !== 'no') {
                $problems[] = 'APPROVAL_READINESS_AUTHORITY_INVALID:' . $field;
            }
        }
        if ((string)($dryRun['effect'] ?? '') !== 'simulate' || (string)($dryRun['dry_run_state'] ?? '') !== 'ready' || (string)($dryRun['simulation_complete'] ?? 'no') !== 'yes' || (string)($dryRun['execution_eligible_at_simulation'] ?? 'no') !== 'yes') {
            $problems[] = 'DRY_RUN_INVALID';
        }
        foreach (['would_execute', 'would_apply', 'would_write', 'would_archive', 'would_delete', 'can_execute', 'can_apply', 'grants_execution_authority'] as $field) {
            if ((string)($dryRun[$field] ?? 'yes') !== 'no') {
                $problems[] = 'DRY_RUN_AUTHORITY_INVALID:' . $field;
            }
        }
        if (self::strings($dryRun['blocking_reasons'] ?? []) !== []) {
            $problems[] = 'DRY_RUN_BLOCKERS_PRESENT';
        }
        if ((string)($executor['effect'] ?? '') !== 'verify' || (string)($executor['executor_readiness'] ?? '') !== 'consumed' || (string)($executor['request_valid'] ?? 'no') !== 'yes' || (string)($executor['single_use_available'] ?? 'yes') !== 'no') {
            $problems[] = 'CLAIM_CONSUMPTION_STATE_INVALID';
        }
        foreach (['can_claim', 'can_execute', 'can_apply', 'can_archive', 'can_delete', 'grants_execution_authority'] as $field) {
            if ((string)($executor[$field] ?? 'yes') !== 'no') {
                $problems[] = 'EXECUTOR_READINESS_AUTHORITY_INVALID:' . $field;
            }
        }
        if (!is_array($claim) || (string)($claim['status'] ?? '') !== 'recorded' || (string)($claim['claim_state'] ?? '') !== 'claimed' || (string)($claim['immutable'] ?? 'no') !== 'yes' || (string)($claim['append_only'] ?? 'no') !== 'yes') {
            $problems[] = 'CLAIM_INVALID';
        }
        foreach ($source as $field => $value) {
            if (in_array($field, ['claim_id', 'claim_fingerprint'], true)) {
                continue;
            }
            if ((string)($current[$field] ?? '') !== $value) {
                $problems[] = 'CURRENT_PACKET_MISMATCH:' . strtoupper($field);
            }
        }
        if ((string)($claim['claim_id'] ?? '') !== $source['claim_id'] || (string)($claim['claim_fingerprint'] ?? '') !== $source['claim_fingerprint']) {
            $problems[] = 'CLAIM_IDENTITY_MISMATCH';
        }
        if ((string)($target['owner_key'] ?? '') !== $ownerKey || self::path((string)($target['target_path'] ?? '')) !== $targetPath) {
            $problems[] = 'TARGET_IDENTITY_INVALID';
        }
        $snapshotPlan = self::arr($dryRun, 'snapshot_plan');
        if ((string)($snapshotPlan['required'] ?? 'no') !== 'yes' || self::path((string)($snapshotPlan['source_path'] ?? '')) !== $targetPath || self::path((string)($snapshotPlan['destination_path'] ?? '')) !== $destinationPath) {
            $problems[] = 'SNAPSHOT_PLAN_INVALID';
        }
        if (!str_starts_with($destinationPath, 'storage/studio/deletion-execution-snapshots/') || $destinationPath === $targetPath || str_starts_with($destinationPath . '/', $targetPath . '/') || str_starts_with($targetPath . '/', $destinationPath . '/')) {
            $problems[] = 'SNAPSHOT_DESTINATION_UNSAFE';
        }
        return array_values(array_unique($problems));
    }

    /** @return array<int,string> */
    private static function manifestProblems(array $manifest, array $source, array $target, string $destinationPath, ?array $claim): array
    {
        $problems = [];
        $manifestSource = self::arr($manifest, 'source');
        $manifestTarget = self::arr($manifest, 'target');
        $snapshot = self::arr($manifest, 'snapshot');
        $creation = self::arr($manifest, 'creation_contract');
        $storage = self::arr($manifest, 'storage');
        $summary = self::arr($manifest, 'summary');
        $files = self::arrays($manifest['files'] ?? []);

        if ((string)($manifest['status'] ?? '') !== 'recorded' || (string)($manifest['snapshot_state'] ?? '') !== 'created' || (string)($manifest['manifest_version'] ?? '') !== 'studio.deletion-snapshot-manifest.v1') {
            $problems[] = 'MANIFEST_STATE_INVALID';
        }
        if ((string)($manifest['immutable'] ?? 'no') !== 'yes' || (string)($manifest['append_only'] ?? 'no') !== 'yes' || (string)($manifest['atomic_publish'] ?? 'no') !== 'yes') {
            $problems[] = 'MANIFEST_MUTABILITY_INVALID';
        }
        foreach (['can_execute', 'can_apply', 'can_archive', 'can_delete', 'execution_authorized', 'grants_execution_authority'] as $field) {
            if ((string)($manifest[$field] ?? 'yes') !== 'no') {
                $problems[] = 'MANIFEST_AUTHORITY_INVALID:' . $field;
            }
        }
        if ((string)($manifest['requires_separate_execution_capability'] ?? '') !== 'yes') {
            $problems[] = 'MANIFEST_EXECUTION_SEPARATION_INVALID';
        }
        foreach ($source as $field => $value) {
            if ((string)($manifestSource[$field] ?? '') !== $value) {
                $problems[] = 'MANIFEST_SOURCE_MISMATCH:' . strtoupper($field);
            }
        }
        if ((string)($manifestTarget['owner_key'] ?? '') !== (string)($target['owner_key'] ?? '') || self::path((string)($manifestTarget['target_path'] ?? '')) !== self::path((string)($target['target_path'] ?? ''))) {
            $problems[] = 'MANIFEST_TARGET_MISMATCH';
        }
        if ((string)($manifestSource['claim_id'] ?? '') !== (string)($claim['claim_id'] ?? '') || (string)($manifestSource['claim_fingerprint'] ?? '') !== (string)($claim['claim_fingerprint'] ?? '')) {
            $problems[] = 'MANIFEST_CLAIM_MISMATCH';
        }
        if (self::path((string)($snapshot['destination_path'] ?? '')) !== $destinationPath || self::path((string)($storage['relative_path'] ?? '')) !== $destinationPath || self::path((string)($snapshot['manifest_path'] ?? '')) !== $destinationPath . '/.studio-snapshot-manifest.json') {
            $problems[] = 'MANIFEST_STORAGE_PATH_INVALID';
        }
        if (!str_starts_with(self::path((string)($snapshot['payload_path'] ?? '')), $destinationPath . '/payload/')) {
            $problems[] = 'MANIFEST_PAYLOAD_PATH_INVALID';
        }
        if ((string)($snapshot['copy_mode'] ?? '') !== 'copy_only' || (string)($snapshot['overwrite_allowed'] ?? 'yes') !== 'no' || (string)($snapshot['checksum_algorithm'] ?? '') !== 'sha256') {
            $problems[] = 'MANIFEST_SNAPSHOT_CONTRACT_INVALID';
        }
        if ((string)($creation['claim_required'] ?? '') !== 'yes' || (string)($creation['snapshot_readiness_required'] ?? '') !== 'yes' || (string)($creation['copy_only'] ?? '') !== 'yes' || (string)($creation['atomic_publish_required'] ?? '') !== 'yes' || (string)($creation['overwrite_allowed'] ?? 'yes') !== 'no' || (string)($creation['manifest_required'] ?? '') !== 'yes' || (string)($creation['checksum_algorithm'] ?? '') !== 'sha256' || (string)($creation['snapshot_is_execution_authority'] ?? 'yes') !== 'no') {
            $problems[] = 'MANIFEST_CREATION_CONTRACT_INVALID';
        }
        if ((string)($storage['storage_scope'] ?? '') !== 'studio_snapshot' || (string)($storage['immutable'] ?? 'no') !== 'yes' || (string)($storage['append_only'] ?? 'no') !== 'yes' || (string)($storage['atomic_publish'] ?? 'no') !== 'yes') {
            $problems[] = 'MANIFEST_STORAGE_PROVENANCE_INVALID';
        }
        if ((int)($summary['file_count'] ?? -1) !== count($files) || (int)($summary['directory_count'] ?? -1) < 0 || (int)($summary['total_bytes'] ?? -1) < 0 || !preg_match('/^[a-f0-9]{64}$/', (string)($summary['tree_checksum_sha256'] ?? ''))) {
            $problems[] = 'MANIFEST_SUMMARY_INVALID';
        }
        $paths = [];
        foreach ($files as $file) {
            $path = self::path((string)($file['path'] ?? ''));
            if ($path === '' || !str_starts_with($path, 'payload/') || isset($paths[$path]) || (int)($file['size_bytes'] ?? -1) < 0 || !preg_match('/^[a-f0-9]{64}$/', (string)($file['sha256'] ?? ''))) {
                $problems[] = 'MANIFEST_FILE_ENTRY_INVALID';
                break;
            }
            $paths[$path] = true;
        }
        $orderedFiles = $files;
        usort($orderedFiles, static fn(array $left, array $right): int => strcmp((string)($left['path'] ?? ''), (string)($right['path'] ?? '')));
        if ($orderedFiles !== $files) {
            $problems[] = 'MANIFEST_FILES_NOT_SORTED';
        }
        if (!hash_equals((string)($summary['tree_checksum_sha256'] ?? ''), hash('sha256', self::json($files)))) {
            $problems[] = 'MANIFEST_TREE_CHECKSUM_INVALID';
        }
        if (!hash_equals(self::manifestFingerprint($manifest), (string)($manifest['manifest_fingerprint'] ?? ''))) {
            $problems[] = 'MANIFEST_FINGERPRINT_INVALID';
        }
        return array_values(array_unique($problems));
    }

    /** @return array{0:array<int,array<string,mixed>>,1:array<int,string>,2:array<int,array<string,mixed>>} */
    private static function verifySnapshotPayload(array $manifest, string $destinationPath, callable $inspect, string $root): array
    {
        $checks = [];
        $problems = [];
        $expectedFiles = self::arrays($manifest['files'] ?? []);
        $actualFiles = self::enumerateFiles($root, $destinationPath, true);
        $actualMap = [];
        foreach ($actualFiles as $file) {
            $actualMap[(string)$file['path']] = $file;
        }
        foreach ($expectedFiles as $file) {
            $relative = self::path((string)($file['path'] ?? ''));
            $fullPath = $destinationPath . '/' . $relative;
            $state = $inspect($fullPath, 'snapshot_payload_file');
            $passed = (string)($state['exists'] ?? 'no') === 'yes'
                && (string)($state['is_file'] ?? 'no') === 'yes'
                && (string)($state['is_link'] ?? 'yes') === 'no'
                && (string)($state['readable'] ?? 'no') === 'yes'
                && (int)($state['size_bytes'] ?? -1) === (int)($file['size_bytes'] ?? -2)
                && hash_equals((string)($file['sha256'] ?? ''), (string)($state['sha256'] ?? ''));
            $checks[] = ['check_id' => 'snapshot-integrity:file:' . $relative, 'check_type' => 'snapshot_file_checksum', 'path' => $fullPath, 'expected_sha256' => (string)($file['sha256'] ?? ''), 'actual_sha256' => (string)($state['sha256'] ?? ''), 'passed' => $passed ? 'yes' : 'no', 'blocking' => 'yes'];
            if (!$passed) {
                $problems[] = 'SNAPSHOT_FILE_MISMATCH:' . $relative;
            }
            unset($actualMap[$relative]);
        }
        foreach (array_keys($actualMap) as $extra) {
            $problems[] = 'SNAPSHOT_EXTRA_FILE:' . $extra;
        }
        $summary = self::arr($manifest, 'summary');
        $actualBytes = array_sum(array_map(static fn(array $file): int => (int)($file['size_bytes'] ?? 0), $actualFiles));
        if (count($actualFiles) !== (int)($summary['file_count'] ?? -1)) {
            $problems[] = 'SNAPSHOT_FILE_COUNT_MISMATCH';
        }
        if ($actualBytes !== (int)($summary['total_bytes'] ?? -1)) {
            $problems[] = 'SNAPSHOT_TOTAL_BYTES_MISMATCH';
        }
        $actualTree = array_map(static fn(array $file): array => ['path' => $file['path'], 'size_bytes' => $file['size_bytes'], 'sha256' => $file['sha256']], $actualFiles);
        if (!hash_equals((string)($summary['tree_checksum_sha256'] ?? ''), hash('sha256', self::json($actualTree)))) {
            $problems[] = 'SNAPSHOT_TREE_CHECKSUM_MISMATCH';
        }
        $actualDirs = self::countPayloadDirectories($root, $destinationPath);
        if ($actualDirs !== (int)($summary['directory_count'] ?? -1)) {
            $problems[] = 'SNAPSHOT_DIRECTORY_COUNT_MISMATCH';
        }
        return [$checks, array_values(array_unique($problems)), $actualFiles];
    }

    /** @return array{0:array<int,array<string,mixed>>,1:array<int,string>,2:array<int,array<string,mixed>>} */
    private static function verifySourceStillMatches(array $manifest, string $targetPath, string $destinationPath, callable $inspect, string $root): array
    {
        $checks = [];
        $problems = [];
        $snapshot = self::arr($manifest, 'snapshot');
        $payloadPath = self::path((string)($snapshot['payload_path'] ?? ''));
        $payloadPrefix = $destinationPath . '/';
        $payloadRelative = str_starts_with($payloadPath, $payloadPrefix) ? substr($payloadPath, strlen($payloadPrefix)) : '';
        $payloadName = basename($payloadRelative);
        $expectedFiles = self::arrays($manifest['files'] ?? []);
        $sourceFiles = self::enumerateFiles($root, $targetPath, false);
        $sourceMap = [];
        foreach ($sourceFiles as $file) {
            $manifestPath = 'payload/' . $payloadName . ($file['path'] !== '' ? '/' . $file['path'] : '');
            $sourceMap[$manifestPath] = $file;
        }
        foreach ($expectedFiles as $file) {
            $path = (string)($file['path'] ?? '');
            $sourceFile = $sourceMap[$path] ?? null;
            $passed = is_array($sourceFile)
                && (int)($sourceFile['size_bytes'] ?? -1) === (int)($file['size_bytes'] ?? -2)
                && hash_equals((string)($file['sha256'] ?? ''), (string)($sourceFile['sha256'] ?? ''));
            $checks[] = ['check_id' => 'snapshot-integrity:source:' . $path, 'check_type' => 'live_source_matches_snapshot', 'path' => $targetPath, 'expected_sha256' => (string)($file['sha256'] ?? ''), 'actual_sha256' => is_array($sourceFile) ? (string)($sourceFile['sha256'] ?? '') : '', 'passed' => $passed ? 'yes' : 'no', 'blocking' => 'yes'];
            if (!$passed) {
                $problems[] = 'SOURCE_FILE_CHANGED:' . $path;
            }
            unset($sourceMap[$path]);
        }
        foreach (array_keys($sourceMap) as $extra) {
            $problems[] = 'SOURCE_EXTRA_FILE:' . $extra;
        }
        return [$checks, array_values(array_unique($problems)), $sourceFiles];
    }

    /** @return array<int,array<string,mixed>> */
    private static function enumerateFiles(string $root, string $relativeRoot, bool $snapshot): array
    {
        $absoluteRoot = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeRoot);
        if (!file_exists($absoluteRoot) || is_link($absoluteRoot)) {
            return [];
        }
        if (is_file($absoluteRoot)) {
            $hash = hash_file('sha256', $absoluteRoot);
            $size = filesize($absoluteRoot);
            return [['path' => '', 'size_bytes' => $size === false ? -1 : (int)$size, 'sha256' => is_string($hash) ? $hash : '']];
        }
        if (!is_dir($absoluteRoot)) {
            return [];
        }
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($absoluteRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iterator as $item) {
            $absolute = $item->getPathname();
            if ($item->isLink()) {
                $relative = str_replace('\\', '/', substr($absolute, strlen($absoluteRoot) + 1));
                $files[] = ['path' => $relative, 'size_bytes' => -1, 'sha256' => ''];
                continue;
            }
            if (!$item->isFile()) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($absolute, strlen($absoluteRoot) + 1));
            if ($snapshot && $relative === '.studio-snapshot-manifest.json') {
                continue;
            }
            $hash = hash_file('sha256', $absolute);
            $size = filesize($absolute);
            $files[] = ['path' => $relative, 'size_bytes' => $size === false ? -1 : (int)$size, 'sha256' => is_string($hash) ? $hash : ''];
        }
        usort($files, static fn(array $left, array $right): int => strcmp((string)$left['path'], (string)$right['path']));
        return $files;
    }

    private static function countPayloadDirectories(string $root, string $destinationPath): int
    {
        $payloadRoot = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $destinationPath) . DIRECTORY_SEPARATOR . 'payload';
        if (!is_dir($payloadRoot)) {
            return 0;
        }
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($payloadRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isDir() && !$item->isLink()) {
                $count++;
            }
        }
        return $count;
    }

    private static function manifestFingerprint(array $manifest): string
    {
        $material = $manifest;
        unset($material['manifest_fingerprint'], $material['storage']);
        return 'deletion-snapshot-manifest:' . substr(sha1(self::json($material)), 0, 24);
    }

    /** @return array<string,mixed> */
    private static function result(string $state, array $source, array $target, string $destinationPath, ?array $manifest, ?array $claim, ?array $actor, array $snapshotChecks, array $sourceChecks, array $reasons, array $diagnostics, array $actualSnapshotFiles = [], array $actualSourceFiles = []): array
    {
        $ready = $state === self::STATE_READY;
        $manifestSummary = is_array($manifest) ? self::arr($manifest, 'summary') : [];
        $actorRecord = self::actor($actor ?? []);
        $material = [
            'version' => self::VERSION,
            'state' => $state,
            'source' => $source,
            'target' => $target,
            'destination_path' => $destinationPath,
            'manifest_fingerprint' => (string)($manifest['manifest_fingerprint'] ?? ''),
            'claim_fingerprint' => (string)($claim['claim_fingerprint'] ?? ''),
            'actor' => $actorRecord,
            'blocking_reasons' => array_values(array_unique($reasons)),
        ];
        return [
            'status' => in_array($state, [self::STATE_BLOCKED, self::STATE_UNKNOWN, self::STATE_MANIFEST_INVALID, self::STATE_CHECKSUM_MISMATCH], true) ? 'partial' : 'ok',
            'effect' => self::EFFECT,
            'snapshot_integrity_version' => self::VERSION,
            'snapshot_integrity' => $state,
            'snapshot_integrity_valid' => $ready ? 'yes' : 'no',
            'post_snapshot_execution_ready' => $ready ? 'yes' : 'no',
            'manifest_valid' => $ready ? 'yes' : (in_array($state, [self::STATE_CHECKSUM_MISMATCH, self::STATE_SOURCE_CHANGED, self::STATE_WRONG_EXECUTOR, self::STATE_EXPIRED], true) ? 'yes' : 'no'),
            'checksums_valid' => $ready ? 'yes' : ($state === self::STATE_SOURCE_CHANGED ? 'yes' : 'no'),
            'source_matches_snapshot' => $ready ? 'yes' : 'no',
            'claim_valid' => in_array($state, [self::STATE_READY, self::STATE_CHECKSUM_MISMATCH, self::STATE_SOURCE_CHANGED, self::STATE_WRONG_EXECUTOR, self::STATE_EXPIRED], true) ? 'yes' : 'no',
            'executor_identity_valid' => $ready ? 'yes' : ($state === self::STATE_WRONG_EXECUTOR ? 'no' : 'unknown'),
            'can_execute' => 'no',
            'can_apply' => 'no',
            'can_archive' => 'no',
            'can_delete' => 'no',
            'grants_execution_authority' => 'no',
            'requires_separate_execution_capability' => 'yes',
            'current_packet' => array_merge($source, ['target' => $target]),
            'claim_evidence' => [
                'present' => is_array($claim) ? 'yes' : 'no',
                'claim_id' => (string)($claim['claim_id'] ?? ''),
                'claim_fingerprint' => (string)($claim['claim_fingerprint'] ?? ''),
                'executor' => is_array($claim) ? self::arr($claim, 'executor') : [],
            ],
            'actor_evidence' => $actorRecord,
            'snapshot_evidence' => [
                'present' => is_array($manifest) ? 'yes' : 'no',
                'snapshot_id' => (string)($manifest['snapshot_id'] ?? ''),
                'manifest_fingerprint' => (string)($manifest['manifest_fingerprint'] ?? ''),
                'destination_path' => $destinationPath,
                'storage' => is_array($manifest) ? self::arr($manifest, 'storage') : [],
                'summary' => $manifestSummary,
                'actual_snapshot_file_count' => count($actualSnapshotFiles),
                'actual_source_file_count' => count($actualSourceFiles),
            ],
            'snapshot_checks' => $snapshotChecks,
            'source_checks' => $sourceChecks,
            'blocking_reasons' => array_values(array_unique($reasons)),
            'snapshot_integrity_fingerprint' => 'deletion-snapshot-integrity:' . substr(sha1(self::json($material)), 0, 24),
            'diagnostics' => $diagnostics,
        ];
    }

    /** @return array<string,mixed> */
    private static function arr(array $source, string $key): array
    {
        return isset($source[$key]) && is_array($source[$key]) ? $source[$key] : [];
    }

    /** @return array<int,array<string,mixed>> */
    private static function arrays($value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }

    /** @return array<int,string> */
    private static function strings($value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $result = [];
        foreach ($value as $item) {
            $item = trim((string)$item);
            if ($item !== '') {
                $result[$item] = true;
            }
        }
        return array_map('strval', array_keys($result));
    }

    private static function path(string $path): string
    {
        $parts = [];
        foreach (explode('/', ltrim(str_replace('\\', '/', trim($path)), '/')) as $part) {
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

    private static function root(?string $root): string
    {
        return $root !== null && trim($root) !== ''
            ? rtrim($root, DIRECTORY_SEPARATOR)
            : (defined('APP_ROOT') ? rtrim((string)APP_ROOT, DIRECTORY_SEPARATOR) : dirname(__DIR__, 3));
    }

    private static function isPlatformAdmin(?array $actor): bool
    {
        return is_array($actor) && strtolower(trim((string)($actor['authority_role'] ?? ''))) === 'platform_admin';
    }

    /** @return array<string,string> */
    private static function actor(array $actor): array
    {
        $id = trim((string)($actor['user_id'] ?? $actor['id'] ?? $actor['actor_id'] ?? ''));
        return [
            'actor_id' => $id,
            'display_name' => trim((string)($actor['display_name'] ?? $actor['name'] ?? $actor['email'] ?? $actor['username'] ?? $id)),
            'authority_role' => strtolower(trim((string)($actor['authority_role'] ?? ''))),
        ];
    }

    private static function utc(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        try {
            $date = new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
        return $date->getOffset() === 0 ? $date->setTimezone(new \DateTimeZone('UTC')) : null;
    }

    private static function now(?callable $clock): \DateTimeImmutable
    {
        $value = $clock !== null ? $clock() : new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if ($value instanceof \DateTimeImmutable) {
            return $value->setTimezone(new \DateTimeZone('UTC'));
        }
        if ($value instanceof \DateTime) {
            return \DateTimeImmutable::createFromMutable($value)->setTimezone(new \DateTimeZone('UTC'));
        }
        try {
            return (new \DateTimeImmutable(trim((string)$value)))->setTimezone(new \DateTimeZone('UTC'));
        } catch (\Throwable) {
            return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        }
    }

    private static function hasPrefix(array $values, string $prefix): bool
    {
        foreach ($values as $value) {
            if (str_starts_with((string)$value, $prefix)) {
                return true;
            }
        }
        return false;
    }

    /** @return array<string,mixed> */
    private static function diag(string $code, string $message, array $extra = []): array
    {
        return array_merge(['code' => $code, 'severity' => 'error', 'message' => $message], $extra);
    }

    private static function json(array $material): string
    {
        $normalized = self::sortRecursive($material);
        $encoded = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return is_string($encoded) ? $encoded : serialize($normalized);
    }

    private static function sortRecursive($value)
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map([self::class, 'sortRecursive'], $value);
        }
        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = self::sortRecursive($item);
        }
        return $value;
    }
}
