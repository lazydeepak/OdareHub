<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

/**
 * Canonical read-only simulation of an approved deletion change set.
 * It inspects current paths and emits deterministic would_* actions only.
 */
final class StudioDeletionExecutionDryRunService
{
    public const EFFECT = 'simulate';
    public const VERSION = 'studio.deletion-execution-dry-run.v1';

    public const STATE_READY = 'ready';
    public const STATE_BLOCKED = 'blocked';
    public const STATE_STALE = 'stale';
    public const STATE_UNKNOWN = 'unknown';

    /**
     * @param array<string,mixed> $changeSet
     * @param array<string,mixed> $readiness
     * @param callable(string,string):array<string,mixed>|null $inspector
     * @return array<string,mixed>
     */
    public static function simulate(array $changeSet, array $readiness, ?string $root = null, ?callable $inspector = null): array
    {
        $target = self::arrayValue($changeSet, 'target');
        $ownerKey = trim((string)($target['owner_key'] ?? ''));
        $targetType = trim((string)($target['target_type'] ?? 'owner'));
        $targetPath = self::normalizePath((string)($target['target_path'] ?? ''));
        $changeSetFingerprint = trim((string)($changeSet['change_set_fingerprint'] ?? ''));
        $planFingerprint = trim((string)($changeSet['source_plan']['fingerprint'] ?? ''));
        $operationManifest = self::arrayList($changeSet['operation_manifest'] ?? []);
        $snapshotContract = self::arrayValue($changeSet, 'snapshot_contract');
        $verificationContract = self::arrayValue($changeSet, 'verification_contract');
        $currentPacket = self::arrayValue($readiness, 'current_packet');
        $approvalEvidence = self::arrayValue($readiness, 'approval_evidence');
        $blockingReasons = [];
        $diagnostics = [];

        $packetProblems = self::packetProblems(
            $changeSet,
            $readiness,
            $changeSetFingerprint,
            $planFingerprint,
            $ownerKey,
            $targetPath,
            $operationManifest,
            $currentPacket,
            $approvalEvidence
        );
        if ($packetProblems !== []) {
            $blockingReasons = array_merge($blockingReasons, $packetProblems);
            $state = self::readinessIsStale($readiness, $currentPacket, $changeSetFingerprint, $planFingerprint)
                ? self::STATE_STALE
                : self::STATE_BLOCKED;
            $diagnostics[] = self::diagnostic(
                'DELETION_EXECUTION_DRY_RUN_PREREQUISITE_FAILED',
                'Dry-run simulation requires an exact, currently approved, execution-eligible deletion packet.',
                ['reasons' => $packetProblems]
            );
            return self::result(
                $state,
                $target,
                $changeSetFingerprint,
                $planFingerprint,
                $readiness,
                [],
                [],
                [],
                $blockingReasons,
                $diagnostics
            );
        }

        [$orderedOperations, $manifestProblems] = self::validateAndOrderManifest($operationManifest);
        if ($manifestProblems !== []) {
            $blockingReasons = array_merge($blockingReasons, $manifestProblems);
            $diagnostics[] = self::diagnostic(
                'DELETION_EXECUTION_DRY_RUN_MANIFEST_INVALID',
                'The approved operation manifest is not deterministic or dependency-safe.',
                ['reasons' => $manifestProblems]
            );
            return self::result(
                self::STATE_BLOCKED,
                $target,
                $changeSetFingerprint,
                $planFingerprint,
                $readiness,
                [],
                [],
                [],
                $blockingReasons,
                $diagnostics
            );
        }

        $repositoryRoot = self::repositoryRoot($root);
        $pathInspector = $inspector ?? static function (string $relativePath, string $kind) use ($repositoryRoot): array {
            $absolutePath = $repositoryRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            return [
                'path' => $relativePath,
                'kind' => $kind,
                'exists' => file_exists($absolutePath) ? 'yes' : 'no',
                'is_file' => is_file($absolutePath) ? 'yes' : 'no',
                'is_dir' => is_dir($absolutePath) ? 'yes' : 'no',
                'readable' => is_readable($absolutePath) ? 'yes' : 'no',
            ];
        };

        $snapshotDestination = self::snapshotDestination($ownerKey, $changeSetFingerprint, $targetPath);
        $snapshotState = $pathInspector($snapshotDestination, 'snapshot_destination');
        $targetState = $pathInspector($targetPath, 'target');
        $preflightChecks = [[
            'check_id' => 'dry-run:target-exists',
            'check_type' => 'target_exists',
            'path' => $targetPath,
            'expected' => 'yes',
            'actual' => (string)($targetState['exists'] ?? 'unknown'),
            'passed' => (string)($targetState['exists'] ?? 'no') === 'yes' ? 'yes' : 'no',
            'blocking' => 'yes',
        ], [
            'check_id' => 'dry-run:snapshot-destination-available',
            'check_type' => 'snapshot_destination_available',
            'path' => $snapshotDestination,
            'expected' => 'absent',
            'actual' => (string)($snapshotState['exists'] ?? 'unknown') === 'yes' ? 'exists' : 'absent',
            'passed' => (string)($snapshotState['exists'] ?? 'no') === 'yes' ? 'no' : 'yes',
            'blocking' => 'yes',
        ]];

        $simulatedOperations = [];
        $knownIds = [];
        foreach ($orderedOperations as $operation) {
            $operationId = (string)$operation['operation_id'];
            $operationType = (string)$operation['operation_type'];
            $path = self::normalizePath((string)($operation['path'] ?? ''));
            $pathState = $path !== '' ? $pathInspector($path, self::inspectionKind($operationType)) : [];
            $simulation = self::simulateOperation($operation, $pathState, $snapshotDestination, $knownIds);
            if ((string)($simulation['supported'] ?? 'no') !== 'yes') {
                $blockingReasons[] = 'UNSUPPORTED_OPERATION:' . $operationType;
            }
            if ((string)($operation['phase'] ?? '') === 'prepare'
                && (string)($operation['required'] ?? 'no') === 'yes'
                && (string)($pathState['exists'] ?? 'no') !== 'yes') {
                $blockingReasons[] = 'OPERATION_PATH_MISSING:' . $operationId;
                $simulation['simulation_state'] = 'blocked';
                $simulation['precondition'] = 'required_source_path_missing';
            }
            if ((string)($operation['phase'] ?? '') === 'prepare'
                && (string)($operation['required'] ?? 'no') === 'yes'
                && (string)($pathState['readable'] ?? 'no') !== 'yes') {
                $blockingReasons[] = 'OPERATION_PATH_UNREADABLE:' . $operationId;
                $simulation['simulation_state'] = 'blocked';
                $simulation['precondition'] = 'required_source_path_unreadable';
            }
            $simulatedOperations[] = $simulation;
            $knownIds[$operationId] = true;
        }

        foreach ($preflightChecks as $check) {
            if ((string)$check['blocking'] === 'yes' && (string)$check['passed'] !== 'yes') {
                $blockingReasons[] = strtoupper((string)$check['check_type']);
            }
        }

        $verificationCommands = self::verificationCommands($verificationContract, $orderedOperations, $ownerKey, $targetType, $targetPath);
        $snapshotPlan = [
            'required' => (string)($snapshotContract['required'] ?? 'yes'),
            'source_path' => $targetPath,
            'destination_path' => $snapshotDestination,
            'destination_exists' => (string)($snapshotState['exists'] ?? 'unknown'),
            'would_create' => 'yes',
            'would_write' => 'no',
            'rollback_source' => 'simulated_only',
        ];

        $state = $blockingReasons === [] ? self::STATE_READY : self::STATE_BLOCKED;
        if ($state === self::STATE_BLOCKED) {
            $diagnostics[] = self::diagnostic(
                'DELETION_EXECUTION_DRY_RUN_BLOCKED',
                'The dry run found conditions that would prevent safe execution.',
                ['reasons' => array_values(array_unique($blockingReasons))]
            );
        }

        return self::result(
            $state,
            $target,
            $changeSetFingerprint,
            $planFingerprint,
            $readiness,
            $simulatedOperations,
            $preflightChecks,
            $verificationCommands,
            array_values(array_unique($blockingReasons)),
            $diagnostics,
            $snapshotPlan
        );
    }

    /** @return array<int,string> */
    private static function packetProblems(
        array $changeSet,
        array $readiness,
        string $changeSetFingerprint,
        string $planFingerprint,
        string $ownerKey,
        string $targetPath,
        array $operationManifest,
        array $currentPacket,
        array $approvalEvidence
    ): array {
        $problems = [];
        if ($changeSetFingerprint === '' || $planFingerprint === '' || $ownerKey === '' || $targetPath === '') {
            $problems[] = 'PACKET_IDENTITY_MISSING';
        }
        if ($operationManifest === []) {
            $problems[] = 'OPERATION_MANIFEST_MISSING';
        }
        if ((string)($changeSet['immutable'] ?? 'no') !== 'yes') {
            $problems[] = 'CHANGE_SET_NOT_IMMUTABLE';
        }
        if ((string)($changeSet['can_execute'] ?? 'yes') !== 'no' || (string)($changeSet['can_apply'] ?? 'yes') !== 'no') {
            $problems[] = 'CHANGE_SET_AUTHORITY_INVALID';
        }
        if ((string)($readiness['effect'] ?? '') !== 'verify') {
            $problems[] = 'READINESS_EFFECT_INVALID';
        }
        if ((string)($readiness['execution_readiness'] ?? '') !== 'ready') {
            $problems[] = 'READINESS_NOT_READY';
        }
        if ((string)($readiness['execution_eligible'] ?? 'no') !== 'yes' || (string)($readiness['approval_valid'] ?? 'no') !== 'yes') {
            $problems[] = 'APPROVAL_NOT_VALID';
        }
        if ((string)($readiness['can_execute'] ?? 'yes') !== 'no' || (string)($readiness['can_apply'] ?? 'yes') !== 'no' || (string)($readiness['grants_execution_authority'] ?? 'yes') !== 'no') {
            $problems[] = 'READINESS_AUTHORITY_INVALID';
        }
        if ((string)($currentPacket['change_set_fingerprint'] ?? '') !== $changeSetFingerprint) {
            $problems[] = 'CHANGE_SET_FINGERPRINT_MISMATCH';
        }
        if ((string)($currentPacket['plan_fingerprint'] ?? '') !== $planFingerprint) {
            $problems[] = 'PLAN_FINGERPRINT_MISMATCH';
        }
        if ((string)($approvalEvidence['exact_packet_match'] ?? 'no') !== 'yes' || (string)($approvalEvidence['decision'] ?? '') !== 'approved') {
            $problems[] = 'EXACT_APPROVAL_REQUIRED';
        }
        if ((string)($approvalEvidence['record_id'] ?? '') === '' || (string)($approvalEvidence['record_fingerprint'] ?? '') === '') {
            $problems[] = 'APPROVAL_EVIDENCE_IDENTITY_MISSING';
        }
        return $problems;
    }

    private static function readinessIsStale(array $readiness, array $currentPacket, string $changeSetFingerprint, string $planFingerprint): bool
    {
        if ((string)($readiness['execution_readiness'] ?? '') === 'stale') {
            return true;
        }
        return ((string)($currentPacket['change_set_fingerprint'] ?? '') !== ''
                && (string)$currentPacket['change_set_fingerprint'] !== $changeSetFingerprint)
            || ((string)($currentPacket['plan_fingerprint'] ?? '') !== ''
                && (string)$currentPacket['plan_fingerprint'] !== $planFingerprint);
    }

    /** @return array{0:array<int,array<string,mixed>>,1:array<int,string>} */
    private static function validateAndOrderManifest(array $manifest): array
    {
        $problems = [];
        $bySequence = [];
        $ids = [];
        foreach ($manifest as $operation) {
            $id = trim((string)($operation['operation_id'] ?? ''));
            $sequence = (int)($operation['sequence'] ?? 0);
            $type = trim((string)($operation['operation_type'] ?? ''));
            $path = self::normalizePath((string)($operation['path'] ?? ''));
            if ($id === '' || $sequence < 1 || $type === '' || $path === '') {
                $problems[] = 'OPERATION_IDENTITY_INVALID';
                continue;
            }
            if (isset($ids[$id])) {
                $problems[] = 'OPERATION_ID_DUPLICATE:' . $id;
                continue;
            }
            if (isset($bySequence[$sequence])) {
                $problems[] = 'OPERATION_SEQUENCE_DUPLICATE:' . $sequence;
                continue;
            }
            $ids[$id] = $sequence;
            $bySequence[$sequence] = $operation;
        }
        ksort($bySequence, SORT_NUMERIC);
        $ordered = array_values($bySequence);
        foreach ($ordered as $index => $operation) {
            $expected = $index + 1;
            if ((int)($operation['sequence'] ?? 0) !== $expected) {
                $problems[] = 'OPERATION_SEQUENCE_GAP:' . $expected;
            }
            $sequence = (int)$operation['sequence'];
            foreach (self::stringList($operation['depends_on'] ?? []) as $dependency) {
                if (!isset($ids[$dependency])) {
                    $problems[] = 'OPERATION_DEPENDENCY_MISSING:' . $dependency;
                } elseif ($ids[$dependency] >= $sequence) {
                    $problems[] = 'OPERATION_DEPENDENCY_ORDER_INVALID:' . $dependency;
                }
            }
        }
        return [$ordered, array_values(array_unique($problems))];
    }

    /** @return array<string,mixed> */
    private static function simulateOperation(array $operation, array $pathState, string $snapshotDestination, array $completedIds): array
    {
        $id = (string)$operation['operation_id'];
        $type = (string)$operation['operation_type'];
        $dependencies = self::stringList($operation['depends_on'] ?? []);
        $dependenciesSatisfied = array_diff($dependencies, array_keys($completedIds)) === [];
        $base = [
            'sequence' => (int)$operation['sequence'],
            'operation_id' => $id,
            'phase' => (string)($operation['phase'] ?? ''),
            'operation_type' => $type,
            'owner_key' => (string)($operation['owner_key'] ?? ''),
            'path' => (string)($operation['path'] ?? ''),
            'depends_on' => $dependencies,
            'dependencies_satisfied' => $dependenciesSatisfied ? 'yes' : 'no',
            'path_state' => $pathState,
            'supported' => 'yes',
            'simulation_state' => $dependenciesSatisfied ? 'would_run' : 'blocked',
            'would_mutate' => 'no',
        ];

        return match ($type) {
            'remove_blocking_reference' => array_merge($base, [
                'simulation_action' => 'would_remove_blocking_reference',
                'expected_effect' => 'reference_removed',
                'line_numbers' => self::integerList($operation['line_numbers'] ?? []),
                'reference_count' => (int)($operation['reference_count'] ?? 0),
            ]),
            'review_reference' => array_merge($base, [
                'simulation_action' => 'would_require_reference_decision',
                'expected_effect' => 'explicit_review_decision',
                'line_numbers' => self::integerList($operation['line_numbers'] ?? []),
                'reference_count' => (int)($operation['reference_count'] ?? 0),
            ]),
            'cleanup_reference' => array_merge($base, [
                'simulation_action' => 'would_cleanup_reference',
                'expected_effect' => 'non_runtime_reference_removed',
                'line_numbers' => self::integerList($operation['line_numbers'] ?? []),
                'reference_count' => (int)($operation['reference_count'] ?? 0),
            ]),
            'archive_target' => array_merge($base, [
                'simulation_action' => 'would_archive_target',
                'snapshot_destination' => $snapshotDestination,
                'expected_effect' => 'rollback_source_created',
            ]),
            'delete_target' => array_merge($base, [
                'simulation_action' => 'would_delete_target',
                'expected_effect' => 'target_absent',
            ]),
            'verify_target_absent' => array_merge($base, [
                'simulation_action' => 'would_verify_target_absent',
                'expected_effect' => 'path_absent',
            ]),
            'verify_no_inbound_references' => array_merge($base, [
                'simulation_action' => 'would_rerun_reference_discovery',
                'expected_effect' => 'zero_inbound_references',
            ]),
            'verify_owner_unregistered' => array_merge($base, [
                'simulation_action' => 'would_rerun_owner_discovery',
                'expected_effect' => 'owner_not_discovered',
            ]),
            default => array_merge($base, [
                'supported' => 'no',
                'simulation_state' => 'blocked',
                'simulation_action' => 'unsupported_operation',
                'expected_effect' => 'unknown',
            ]),
        };
    }

    /** @return array<int,array<string,mixed>> */
    private static function verificationCommands(array $contract, array $operations, string $ownerKey, string $targetType, string $targetPath): array
    {
        $commands = [];
        $checks = self::arrayList($contract['checks'] ?? []);
        foreach ($checks as $check) {
            $checkType = trim((string)($check['check_type'] ?? ''));
            $commands[] = [
                'check_id' => trim((string)($check['check_id'] ?? 'dry-run:verify:' . count($commands))),
                'check_type' => $checkType,
                'path' => self::normalizePath((string)($check['path'] ?? $targetPath)),
                'command' => self::verificationCommand($checkType, $ownerKey, $targetType, $targetPath),
                'expected_evidence' => (string)($check['expected_evidence'] ?? self::expectedEvidence($checkType)),
                'would_execute' => 'no',
            ];
        }
        if ($commands === []) {
            foreach ($operations as $operation) {
                if ((string)($operation['phase'] ?? '') !== 'verify') {
                    continue;
                }
                $type = (string)$operation['operation_type'];
                $commands[] = [
                    'check_id' => (string)$operation['operation_id'],
                    'check_type' => $type,
                    'path' => (string)$operation['path'],
                    'command' => self::verificationCommand($type, $ownerKey, $targetType, $targetPath),
                    'expected_evidence' => self::expectedEvidence($type),
                    'would_execute' => 'no',
                ];
            }
        }
        return $commands;
    }

    private static function verificationCommand(string $type, string $ownerKey, string $targetType, string $targetPath): string
    {
        return match ($type) {
            'verify_target_absent' => 'assert_path_absent ' . $targetPath,
            'verify_no_inbound_references' => 'studio_reference_discovery --target=' . $ownerKey,
            'verify_owner_unregistered' => 'studio_owner_discovery --expect-missing=' . $ownerKey,
            default => 'verify_contract_check --type=' . ($type !== '' ? $type : 'unknown') . ' --target=' . $targetPath,
        };
    }

    private static function expectedEvidence(string $type): string
    {
        return match ($type) {
            'verify_target_absent' => 'target_path_absent',
            'verify_no_inbound_references' => 'inbound_reference_count_zero',
            'verify_owner_unregistered' => 'owner_resolution_not_found',
            default => 'check_passed',
        };
    }

    private static function inspectionKind(string $operationType): string
    {
        return str_contains($operationType, 'reference') ? 'reference_file' : 'target';
    }

    private static function snapshotDestination(string $ownerKey, string $changeSetFingerprint, string $targetPath): string
    {
        $ownerSlug = self::slug($ownerKey);
        $packetSlug = self::slug($changeSetFingerprint);
        $targetSlug = self::slug(str_replace('/', '__', $targetPath));
        return 'storage/studio/deletion-execution-snapshots/' . $ownerSlug . '/' . $packetSlug . '/' . $targetSlug;
    }

    /** @return array<string,mixed> */
    private static function result(
        string $state,
        array $target,
        string $changeSetFingerprint,
        string $planFingerprint,
        array $readiness,
        array $operations,
        array $preflightChecks,
        array $verificationCommands,
        array $blockingReasons,
        array $diagnostics,
        array $snapshotPlan = []
    ): array {
        $material = [
            'version' => self::VERSION,
            'state' => $state,
            'target' => $target,
            'change_set_fingerprint' => $changeSetFingerprint,
            'plan_fingerprint' => $planFingerprint,
            'readiness_fingerprint' => (string)($readiness['readiness_fingerprint'] ?? ''),
            'operations' => $operations,
            'snapshot_plan' => $snapshotPlan,
            'preflight_checks' => $preflightChecks,
            'verification_commands' => $verificationCommands,
            'blocking_reasons' => $blockingReasons,
        ];
        $ready = $state === self::STATE_READY;
        return [
            'status' => in_array($state, [self::STATE_BLOCKED, self::STATE_UNKNOWN], true) ? 'partial' : 'ok',
            'effect' => self::EFFECT,
            'dry_run_version' => self::VERSION,
            'dry_run_state' => $state,
            'simulation_complete' => $operations !== [] ? 'yes' : 'no',
            'would_execute' => 'no',
            'would_apply' => 'no',
            'would_write' => 'no',
            'would_archive' => 'no',
            'would_delete' => 'no',
            'can_execute' => 'no',
            'can_apply' => 'no',
            'grants_execution_authority' => 'no',
            'execution_eligible_at_simulation' => $ready ? 'yes' : 'no',
            'source' => [
                'change_set_fingerprint' => $changeSetFingerprint,
                'plan_fingerprint' => $planFingerprint,
                'readiness_fingerprint' => (string)($readiness['readiness_fingerprint'] ?? ''),
                'approval_record_id' => (string)($readiness['approval_evidence']['record_id'] ?? ''),
                'approval_record_fingerprint' => (string)($readiness['approval_evidence']['record_fingerprint'] ?? ''),
            ],
            'target' => $target,
            'summary' => [
                'operation_count' => count($operations),
                'preflight_check_count' => count($preflightChecks),
                'verification_command_count' => count($verificationCommands),
                'blocking_reason_count' => count($blockingReasons),
            ],
            'preflight_checks' => $preflightChecks,
            'snapshot_plan' => $snapshotPlan,
            'simulated_operations' => $operations,
            'verification_commands' => $verificationCommands,
            'blocking_reasons' => array_values(array_unique($blockingReasons)),
            'dry_run_fingerprint' => 'deletion-execution-dry-run:' . substr(sha1(self::canonicalJson($material)), 0, 24),
            'diagnostics' => $diagnostics,
        ];
    }

    private static function repositoryRoot(?string $root): string
    {
        return $root !== null && trim($root) !== ''
            ? rtrim($root, DIRECTORY_SEPARATOR)
            : (defined('APP_ROOT') ? rtrim((string)APP_ROOT, DIRECTORY_SEPARATOR) : dirname(__DIR__, 3));
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

    private static function slug(string $value): string
    {
        $slug = preg_replace('/[^A-Za-z0-9._-]+/', '__', trim($value));
        $slug = trim((string)$slug, '._-');
        return $slug !== '' ? substr($slug, 0, 180) : 'unknown';
    }

    private static function arrayValue(array $source, string $key): array
    {
        return isset($source[$key]) && is_array($source[$key]) ? $source[$key] : [];
    }

    private static function arrayList($value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
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

    private static function integerList($value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $items = [];
        foreach ($value as $item) {
            $number = (int)$item;
            if ($number > 0) {
                $items[$number] = true;
            }
        }
        $numbers = array_map('intval', array_keys($items));
        sort($numbers, SORT_NUMERIC);
        return $numbers;
    }

    private static function diagnostic(string $code, string $message, array $extra = []): array
    {
        return array_merge(['code' => $code, 'severity' => 'error', 'message' => $message], $extra);
    }

    private static function canonicalJson(array $material): string
    {
        $normalized = self::sortRecursively($material);
        $encoded = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return is_string($encoded) ? $encoded : serialize($normalized);
    }

    private static function sortRecursively($value)
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map([self::class, 'sortRecursively'], $value);
        }
        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = self::sortRecursively($item);
        }
        return $value;
    }
}
