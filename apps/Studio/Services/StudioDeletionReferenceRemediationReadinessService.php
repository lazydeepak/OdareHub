<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

require_once __DIR__ . '/StudioDeletionImpactDiscoveryService.php';

/**
 * Read-only precondition builder for planned deletion-reference remediation.
 * It re-runs canonical reference discovery, verifies exact plan parity, and
 * freezes current file, line, and context hashes. It never writes patches.
 */
final class StudioDeletionReferenceRemediationReadinessService
{
    public const EFFECT = 'verify';
    public const VERSION = 'studio.deletion-reference-remediation-readiness.v1';

    public const STATE_READY = 'ready';
    public const STATE_REVIEW_REQUIRED = 'review_required';
    public const STATE_NO_CHANGES = 'no_changes';
    public const STATE_DRIFTED = 'drifted';
    public const STATE_BLOCKED = 'blocked';
    public const STATE_UNKNOWN = 'unknown';

    /**
     * @param array<string,mixed> $changeSet
     * @param array<string,mixed> $snapshotIntegrity
     * @param callable(array<string,mixed>,?string):array<string,mixed>|null $referenceResolver
     * @param callable(string,?string):array<string,mixed>|null $fileInspector
     * @return array<string,mixed>
     */
    public static function assess(
        array $changeSet,
        array $snapshotIntegrity,
        ?string $root = null,
        ?callable $referenceResolver = null,
        ?callable $fileInspector = null
    ): array {
        $target = self::arr($changeSet, 'target');
        $targetPath = self::path((string)($target['target_path'] ?? ''));
        $ownerKey = trim((string)($target['owner_key'] ?? ''));
        $changeSetFingerprint = trim((string)($changeSet['change_set_fingerprint'] ?? ''));
        $planFingerprint = trim((string)($changeSet['source_plan']['fingerprint'] ?? ''));
        $snapshotIntegrityFingerprint = trim((string)($snapshotIntegrity['snapshot_integrity_fingerprint'] ?? ''));
        $plannedChanges = self::arrays(self::arr($changeSet, 'proposed_changes')['file_changes'] ?? []);
        $source = [
            'change_set_fingerprint' => $changeSetFingerprint,
            'plan_fingerprint' => $planFingerprint,
            'snapshot_integrity_fingerprint' => $snapshotIntegrityFingerprint,
        ];

        $packetProblems = self::packetProblems(
            $changeSet,
            $snapshotIntegrity,
            $source,
            $target,
            $ownerKey,
            $targetPath
        );
        if ($packetProblems !== []) {
            return self::result(
                self::STATE_UNKNOWN,
                $source,
                $target,
                [],
                [],
                [],
                $packetProblems,
                [self::diag(
                    'DELETION_REFERENCE_REMEDIATION_PACKET_INVALID',
                    'Reference-remediation readiness requires the exact current post-snapshot packet.',
                    ['reasons' => $packetProblems]
                )]
            );
        }

        if ($plannedChanges === []) {
            return self::result(self::STATE_NO_CHANGES, $source, $target, [], [], [], [], []);
        }

        $request = [
            'owner_key' => $ownerKey,
            'target_type' => (string)($target['target_type'] ?? 'owner'),
        ];
        try {
            $impact = $referenceResolver !== null
                ? $referenceResolver($request, $root)
                : StudioDeletionImpactDiscoveryService::discover($request, $root);
        } catch (\Throwable $exception) {
            return self::result(
                self::STATE_UNKNOWN,
                $source,
                $target,
                [],
                [],
                [],
                ['REFERENCE_DISCOVERY_FAILED'],
                [self::diag(
                    'DELETION_REFERENCE_REMEDIATION_DISCOVERY_FAILED',
                    $exception->getMessage() !== '' ? $exception->getMessage() : 'Reference discovery failed.'
                )]
            );
        }
        if (!is_array($impact)) {
            return self::result(
                self::STATE_UNKNOWN,
                $source,
                $target,
                [],
                [],
                [],
                ['REFERENCE_DISCOVERY_RESULT_INVALID'],
                [self::diag('DELETION_REFERENCE_REMEDIATION_DISCOVERY_INVALID', 'Reference discovery returned an invalid result.')]
            );
        }

        $impactTarget = self::arr($impact, 'target');
        if ((string)($impact['status'] ?? '') === 'error'
            || trim((string)($impactTarget['owner_key'] ?? '')) !== $ownerKey
            || self::path((string)($impactTarget['target_path'] ?? '')) !== $targetPath) {
            return self::result(
                self::STATE_UNKNOWN,
                $source,
                $target,
                [],
                [],
                [],
                ['CURRENT_REFERENCE_EVIDENCE_INVALID'],
                [self::diag(
                    'DELETION_REFERENCE_REMEDIATION_EVIDENCE_INVALID',
                    'Current reference evidence is unavailable or targets a different owner.'
                )]
            );
        }

        $groups = self::currentReferenceGroups(self::arrays($impact['references'] ?? []));
        $preconditions = [];
        $fileChecks = [];
        $driftReasons = [];
        $blockingReasons = [];
        $diagnostics = [];
        $seenPlannedKeys = [];
        $hasReview = false;

        foreach ($plannedChanges as $change) {
            $changeId = trim((string)($change['change_id'] ?? ''));
            $operationId = trim((string)($change['operation_id'] ?? ''));
            $path = self::path((string)($change['path'] ?? ''));
            $severity = trim((string)($change['severity'] ?? 'review'));
            $lineNumbers = self::ints($change['line_numbers'] ?? []);
            $referenceCount = (int)($change['reference_count'] ?? 0);
            $key = $severity . '|' . $path;

            if ($changeId === '' || $operationId === '' || $path === '' || !in_array($severity, ['blocking', 'review', 'cleanup'], true)) {
                $blockingReasons[] = 'PLANNED_REFERENCE_CHANGE_INVALID';
                continue;
            }
            if (isset($seenPlannedKeys[$key])) {
                $blockingReasons[] = 'PLANNED_REFERENCE_CHANGE_DUPLICATE:' . $path;
                continue;
            }
            $seenPlannedKeys[$key] = true;
            if (self::within($path, $targetPath)) {
                $blockingReasons[] = 'REFERENCE_FILE_INSIDE_DELETION_TARGET:' . $path;
                continue;
            }
            if ($severity === 'review') {
                $hasReview = true;
            }

            $current = $groups[$key] ?? null;
            if (!is_array($current)) {
                $driftReasons[] = 'REFERENCE_GROUP_MISSING:' . $path . ':' . $severity;
                continue;
            }
            $currentLines = self::ints($current['line_numbers'] ?? []);
            if ($currentLines !== $lineNumbers) {
                $driftReasons[] = 'REFERENCE_LINE_SET_DRIFTED:' . $path;
            }
            if ((int)($current['reference_count'] ?? 0) !== $referenceCount) {
                $driftReasons[] = 'REFERENCE_COUNT_DRIFTED:' . $path;
            }
            if (trim((string)($current['owner_key'] ?? '')) !== trim((string)($change['owner_key'] ?? ''))) {
                $driftReasons[] = 'REFERENCE_OWNER_DRIFTED:' . $path;
            }

            try {
                $file = $fileInspector !== null
                    ? $fileInspector($path, $root)
                    : self::inspectFile($path, $root);
            } catch (\Throwable $exception) {
                $blockingReasons[] = 'REFERENCE_FILE_INSPECTION_FAILED:' . $path;
                $diagnostics[] = self::diag(
                    'DELETION_REFERENCE_REMEDIATION_FILE_INSPECTION_FAILED',
                    $exception->getMessage() !== '' ? $exception->getMessage() : 'Reference file inspection failed.',
                    ['path' => $path]
                );
                continue;
            }
            if (!is_array($file)) {
                $blockingReasons[] = 'REFERENCE_FILE_INSPECTION_INVALID:' . $path;
                continue;
            }

            $fileChecks[] = [
                'path' => $path,
                'exists' => (string)($file['exists'] ?? 'no'),
                'is_file' => (string)($file['is_file'] ?? 'no'),
                'is_link' => (string)($file['is_link'] ?? 'yes'),
                'readable' => (string)($file['readable'] ?? 'no'),
                'text_file' => (string)($file['text_file'] ?? 'no'),
                'sha256' => (string)($file['sha256'] ?? ''),
                'size_bytes' => (int)($file['size_bytes'] ?? -1),
                'line_count' => (int)($file['line_count'] ?? -1),
            ];
            if ((string)($file['exists'] ?? 'no') !== 'yes'
                || (string)($file['is_file'] ?? 'no') !== 'yes'
                || (string)($file['is_link'] ?? 'yes') !== 'no'
                || (string)($file['readable'] ?? 'no') !== 'yes'
                || (string)($file['text_file'] ?? 'no') !== 'yes'
                || !preg_match('/^[a-f0-9]{64}$/', (string)($file['sha256'] ?? ''))
                || !is_array($file['lines'] ?? null)) {
                $blockingReasons[] = 'REFERENCE_FILE_UNSAFE_OR_UNAVAILABLE:' . $path;
                continue;
            }

            $lines = array_values(array_map('strval', $file['lines']));
            $evidenceByLine = self::evidenceByLine(self::arrays($current['references'] ?? []));
            $lineEvidence = [];
            foreach ($lineNumbers as $lineNumber) {
                $index = $lineNumber - 1;
                if ($lineNumber < 1 || !array_key_exists($index, $lines)) {
                    $driftReasons[] = 'REFERENCE_LINE_MISSING:' . $path . ':' . $lineNumber;
                    continue;
                }
                $line = (string)$lines[$index];
                $evidence = $evidenceByLine[$lineNumber] ?? [];
                if ($evidence === []) {
                    $driftReasons[] = 'REFERENCE_LINE_EVIDENCE_MISSING:' . $path . ':' . $lineNumber;
                    continue;
                }
                $patterns = [];
                $matchTypes = [];
                $relevance = [];
                $confidence = [];
                $excerpts = [];
                $proposed = [];
                $lineMatches = true;
                foreach ($evidence as $reference) {
                    $pattern = (string)($reference['matched_pattern'] ?? '');
                    if ($pattern === '' || !str_contains($line, $pattern)) {
                        $lineMatches = false;
                    }
                    self::add($patterns, $pattern);
                    self::add($matchTypes, (string)($reference['match_type'] ?? ''));
                    self::add($relevance, (string)($reference['relevance'] ?? ''));
                    self::add($confidence, (string)($reference['confidence'] ?? ''));
                    self::add($excerpts, (string)($reference['matched_text_excerpt'] ?? ''));
                    self::add($proposed, (string)($reference['proposed_replacement'] ?? ''));
                }
                if (!$lineMatches) {
                    $driftReasons[] = 'REFERENCE_PATTERN_DRIFTED:' . $path . ':' . $lineNumber;
                }
                $previous = $index > 0 ? (string)$lines[$index - 1] : '';
                $next = $index + 1 < count($lines) ? (string)$lines[$index + 1] : '';
                $lineEvidence[] = [
                    'line_number' => $lineNumber,
                    'line_sha256' => hash('sha256', $line),
                    'trimmed_line_sha256' => hash('sha256', trim($line)),
                    'context_sha256' => hash('sha256', $previous . "\n" . $line . "\n" . $next),
                    'matched_patterns' => self::sortedKeys($patterns),
                    'match_types' => self::sortedKeys($matchTypes),
                    'relevance' => self::sortedKeys($relevance),
                    'confidence' => self::sortedKeys($confidence),
                    'matched_text_excerpts' => self::sortedKeys($excerpts),
                    'proposed_replacements' => self::sortedKeys($proposed),
                    'pattern_present' => $lineMatches ? 'yes' : 'no',
                ];
            }

            $preconditionMaterial = [
                'version' => self::VERSION,
                'snapshot_integrity_fingerprint' => $snapshotIntegrityFingerprint,
                'change_id' => $changeId,
                'operation_id' => $operationId,
                'path' => $path,
                'severity' => $severity,
                'file_sha256' => (string)$file['sha256'],
                'line_evidence' => $lineEvidence,
            ];
            $preconditions[] = [
                'precondition_id' => 'reference-remediation-precondition:' . substr(sha1(self::json($preconditionMaterial)), 0, 24),
                'change_id' => $changeId,
                'operation_id' => $operationId,
                'owner_key' => (string)($change['owner_key'] ?? ''),
                'path' => $path,
                'severity' => $severity,
                'required' => (string)($change['required'] ?? 'no'),
                'decision_required' => (string)($change['decision_required'] ?? 'no'),
                'change_type' => (string)($change['change_type'] ?? ''),
                'expected_reference_count' => $referenceCount,
                'expected_line_numbers' => $lineNumbers,
                'expected_file_sha256' => (string)$file['sha256'],
                'expected_size_bytes' => (int)($file['size_bytes'] ?? 0),
                'expected_line_count' => (int)($file['line_count'] ?? count($lines)),
                'line_evidence' => $lineEvidence,
                'patch_policy' => [
                    'compare_exact_file_sha256_before_apply' => 'yes',
                    'compare_exact_line_sha256_before_apply' => 'yes',
                    'compare_context_sha256_before_apply' => 'yes',
                    'fuzzy_apply_allowed' => 'no',
                    'force_apply_allowed' => 'no',
                    'file_create_allowed' => 'no',
                    'file_delete_allowed' => 'no',
                    'must_preserve_unrelated_content' => 'yes',
                    'must_bind_snapshot_integrity_fingerprint' => $snapshotIntegrityFingerprint,
                ],
            ];
        }

        foreach ($groups as $key => $group) {
            if (!isset($seenPlannedKeys[$key])) {
                $driftReasons[] = 'UNPLANNED_REFERENCE_GROUP:' . (string)($group['path'] ?? '');
            }
        }

        usort($preconditions, static fn(array $a, array $b): int => strcmp((string)$a['path'], (string)$b['path']));
        usort($fileChecks, static fn(array $a, array $b): int => strcmp((string)$a['path'], (string)$b['path']));
        $driftReasons = array_values(array_unique($driftReasons));
        $blockingReasons = array_values(array_unique($blockingReasons));

        if ($blockingReasons !== []) {
            return self::result(
                self::STATE_BLOCKED,
                $source,
                $target,
                $preconditions,
                $fileChecks,
                self::arrays($impact['references'] ?? []),
                $blockingReasons,
                array_merge($diagnostics, [self::diag(
                    'DELETION_REFERENCE_REMEDIATION_BLOCKED',
                    'One or more planned reference files are unsafe or unavailable.',
                    ['reasons' => $blockingReasons]
                )])
            );
        }
        if ($driftReasons !== []) {
            return self::result(
                self::STATE_DRIFTED,
                $source,
                $target,
                $preconditions,
                $fileChecks,
                self::arrays($impact['references'] ?? []),
                $driftReasons,
                [self::diag(
                    'DELETION_REFERENCE_REMEDIATION_DRIFTED',
                    'Current reference evidence no longer matches the approved deletion change set.',
                    ['reasons' => $driftReasons]
                )]
            );
        }

        return self::result(
            $hasReview ? self::STATE_REVIEW_REQUIRED : self::STATE_READY,
            $source,
            $target,
            $preconditions,
            $fileChecks,
            self::arrays($impact['references'] ?? []),
            [],
            []
        );
    }

    /** @return array<int,string> */
    private static function packetProblems(array $changeSet, array $integrity, array $source, array $target, string $ownerKey, string $targetPath): array
    {
        $problems = [];
        if ($ownerKey === '' || $targetPath === '' || in_array('', array_values($source), true)) {
            $problems[] = 'CURRENT_PACKET_IDENTITY_MISSING';
        }
        if ((string)($changeSet['immutable'] ?? 'no') !== 'yes'
            || (string)($changeSet['can_execute'] ?? 'yes') !== 'no'
            || (string)($changeSet['can_apply'] ?? 'yes') !== 'no') {
            $problems[] = 'CHANGE_SET_CONTRACT_INVALID';
        }
        if ((string)($integrity['effect'] ?? '') !== 'verify'
            || (string)($integrity['snapshot_integrity'] ?? '') !== 'ready'
            || (string)($integrity['snapshot_integrity_valid'] ?? 'no') !== 'yes'
            || (string)($integrity['post_snapshot_execution_ready'] ?? 'no') !== 'yes'
            || (string)($integrity['source_matches_snapshot'] ?? 'no') !== 'yes') {
            $problems[] = 'SNAPSHOT_INTEGRITY_NOT_READY';
        }
        foreach (['can_execute', 'can_apply', 'can_archive', 'can_delete', 'grants_execution_authority'] as $field) {
            if ((string)($integrity[$field] ?? 'yes') !== 'no') {
                $problems[] = 'SNAPSHOT_INTEGRITY_AUTHORITY_INVALID:' . $field;
            }
        }
        if ((string)($integrity['requires_separate_execution_capability'] ?? '') !== 'yes') {
            $problems[] = 'SNAPSHOT_INTEGRITY_SEPARATION_INVALID';
        }
        $current = self::arr($integrity, 'current_packet');
        if ((string)($current['change_set_fingerprint'] ?? '') !== $source['change_set_fingerprint']
            || (string)($current['plan_fingerprint'] ?? '') !== $source['plan_fingerprint']) {
            $problems[] = 'SNAPSHOT_INTEGRITY_PACKET_MISMATCH';
        }
        $integrityTarget = self::arr($current, 'target');
        if ((string)($integrityTarget['owner_key'] ?? '') !== $ownerKey
            || self::path((string)($integrityTarget['target_path'] ?? '')) !== $targetPath
            || (string)($target['owner_key'] ?? '') !== $ownerKey) {
            $problems[] = 'TARGET_IDENTITY_MISMATCH';
        }
        return array_values(array_unique($problems));
    }

    /** @param array<int,array<string,mixed>> $references @return array<string,array<string,mixed>> */
    private static function currentReferenceGroups(array $references): array
    {
        $groups = [];
        foreach ($references as $reference) {
            $path = self::path((string)($reference['file_path'] ?? ''));
            $severity = trim((string)($reference['impact_severity'] ?? 'review'));
            if ($path === '' || !in_array($severity, ['blocking', 'review', 'cleanup'], true)) {
                continue;
            }
            $key = $severity . '|' . $path;
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'path' => $path,
                    'severity' => $severity,
                    'owner_key' => (string)($reference['referencing_owner_key'] ?? ''),
                    'line_numbers' => [],
                    'reference_count' => 0,
                    'references' => [],
                ];
            }
            $line = (int)($reference['line_number'] ?? 0);
            if ($line > 0) {
                $groups[$key]['line_numbers'][$line] = true;
            }
            $groups[$key]['reference_count']++;
            $groups[$key]['references'][] = $reference;
        }
        foreach ($groups as &$group) {
            $lines = array_map('intval', array_keys($group['line_numbers']));
            sort($lines, SORT_NUMERIC);
            $group['line_numbers'] = $lines;
            usort($group['references'], static function (array $a, array $b): int {
                $left = (int)($a['line_number'] ?? 0) . '|' . (string)($a['matched_pattern'] ?? '');
                $right = (int)($b['line_number'] ?? 0) . '|' . (string)($b['matched_pattern'] ?? '');
                return strcmp($left, $right);
            });
        }
        unset($group);
        ksort($groups);
        return $groups;
    }

    /** @param array<int,array<string,mixed>> $references @return array<int,array<int,array<string,mixed>>> */
    private static function evidenceByLine(array $references): array
    {
        $grouped = [];
        foreach ($references as $reference) {
            $line = (int)($reference['line_number'] ?? 0);
            if ($line > 0) {
                $grouped[$line][] = $reference;
            }
        }
        ksort($grouped, SORT_NUMERIC);
        return $grouped;
    }

    /** @return array<string,mixed> */
    private static function inspectFile(string $path, ?string $root): array
    {
        $repositoryRoot = $root !== null && trim($root) !== ''
            ? rtrim($root, DIRECTORY_SEPARATOR)
            : (defined('APP_ROOT') ? rtrim((string)APP_ROOT, DIRECTORY_SEPARATOR) : dirname(__DIR__, 3));
        $absolute = $repositoryRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        $exists = file_exists($absolute);
        $contents = $exists && is_file($absolute) && is_readable($absolute) ? file_get_contents($absolute) : false;
        $text = is_string($contents) && !str_contains($contents, "\0");
        $lines = $text ? preg_split('/\R/', $contents) : [];
        return [
            'path' => $path,
            'exists' => $exists ? 'yes' : 'no',
            'is_file' => is_file($absolute) ? 'yes' : 'no',
            'is_link' => is_link($absolute) ? 'yes' : 'no',
            'readable' => is_readable($absolute) ? 'yes' : 'no',
            'text_file' => $text ? 'yes' : 'no',
            'sha256' => $text ? (string)(hash_file('sha256', $absolute) ?: '') : '',
            'size_bytes' => is_file($absolute) && filesize($absolute) !== false ? (int)filesize($absolute) : -1,
            'line_count' => is_array($lines) ? count($lines) : -1,
            'lines' => is_array($lines) ? array_values(array_map('strval', $lines)) : [],
        ];
    }

    /** @return array<string,mixed> */
    private static function result(string $state, array $source, array $target, array $preconditions, array $fileChecks, array $references, array $reasons, array $diagnostics): array
    {
        $preconditionsReady = in_array($state, [self::STATE_READY, self::STATE_REVIEW_REQUIRED, self::STATE_NO_CHANGES], true);
        $remediationReady = in_array($state, [self::STATE_READY, self::STATE_NO_CHANGES], true);
        $summary = [
            'planned_file_change_count' => count($preconditions),
            'verified_file_count' => count($fileChecks),
            'current_reference_count' => count($references),
            'blocking_change_count' => self::countSeverity($preconditions, 'blocking'),
            'review_change_count' => self::countSeverity($preconditions, 'review'),
            'cleanup_change_count' => self::countSeverity($preconditions, 'cleanup'),
        ];
        $material = [
            'version' => self::VERSION,
            'state' => $state,
            'source' => $source,
            'target' => $target,
            'preconditions' => $preconditions,
            'blocking_reasons' => array_values(array_unique($reasons)),
        ];
        return [
            'status' => in_array($state, [self::STATE_BLOCKED, self::STATE_UNKNOWN, self::STATE_DRIFTED], true) ? 'partial' : 'ok',
            'effect' => self::EFFECT,
            'reference_remediation_readiness_version' => self::VERSION,
            'reference_remediation_readiness' => $state,
            'preconditions_ready' => $preconditionsReady ? 'yes' : 'no',
            'reference_remediation_ready' => $remediationReady ? 'yes' : 'no',
            'requires_review_decision' => $state === self::STATE_REVIEW_REQUIRED ? 'yes' : 'no',
            'can_write' => 'no',
            'can_apply' => 'no',
            'can_execute' => 'no',
            'can_archive' => 'no',
            'can_delete' => 'no',
            'grants_execution_authority' => 'no',
            'requires_separate_patch_capability' => 'yes',
            'requires_separate_execution_capability' => 'yes',
            'source' => $source,
            'target' => $target,
            'summary' => $summary,
            'patch_preconditions' => $preconditions,
            'file_checks' => $fileChecks,
            'blocking_reasons' => array_values(array_unique($reasons)),
            'reference_remediation_readiness_fingerprint' => 'deletion-reference-remediation-readiness:' . substr(sha1(self::json($material)), 0, 24),
            'diagnostics' => $diagnostics,
        ];
    }

    private static function countSeverity(array $items, string $severity): int
    {
        $count = 0;
        foreach ($items as $item) {
            if ((string)($item['severity'] ?? '') === $severity) {
                $count++;
            }
        }
        return $count;
    }

    private static function within(string $path, string $root): bool
    {
        return $path === $root || str_starts_with($path . '/', $root . '/');
    }

    private static function add(array &$set, string $value): void
    {
        if ($value !== '') {
            $set[$value] = true;
        }
    }

    /** @return array<int,string> */
    private static function sortedKeys(array $set): array
    {
        $values = array_map('strval', array_keys($set));
        sort($values, SORT_NATURAL | SORT_FLAG_CASE);
        return $values;
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

    /** @return array<int,int> */
    private static function ints($value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $result = [];
        foreach ($value as $item) {
            $number = (int)$item;
            if ($number > 0) {
                $result[$number] = true;
            }
        }
        $numbers = array_map('intval', array_keys($result));
        sort($numbers, SORT_NUMERIC);
        return $numbers;
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
