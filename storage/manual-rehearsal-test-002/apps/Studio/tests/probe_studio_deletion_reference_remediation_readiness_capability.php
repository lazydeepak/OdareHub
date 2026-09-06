<?php
declare(strict_types=1);

use Apps\Studio\Services\StudioDeletionReferenceRemediationReadinessService;

require_once dirname(__DIR__) . '/Services/StudioDeletionReferenceRemediationReadinessService.php';

$pass = 0;
$fail = 0;
$assert = static function (bool $condition, string $message) use (&$pass, &$fail): void {
    if ($condition) { $pass++; echo "PASS: {$message}\n"; return; }
    $fail++; echo "FAIL: {$message}\n";
};

$root = sys_get_temp_dir() . '/studio-reference-remediation-' . bin2hex(random_bytes(4));
$referencePath = 'apps/Consumer/Module/config.php';
$absolute = $root . '/' . $referencePath;
mkdir(dirname($absolute), 0777, true);
file_put_contents($absolute, "<?php\nreturn ['owner' => 'Manufacturing/Products'];\n");

$changeSet = [
    'immutable' => 'yes', 'can_execute' => 'no', 'can_apply' => 'no',
    'change_set_fingerprint' => 'deletion-change-set:current',
    'source_plan' => ['fingerprint' => 'deletion-plan:current'],
    'target' => ['owner_key' => 'Manufacturing/Products', 'target_type' => 'owner', 'target_path' => 'apps/Manufacturing/modules/Products'],
    'proposed_changes' => ['file_changes' => [[
        'change_id' => 'change-set:file:one', 'operation_id' => 'operation:one',
        'owner_key' => 'Consumer/Module', 'path' => $referencePath,
        'change_type' => 'remove_blocking_reference', 'line_numbers' => [2],
        'reference_count' => 1, 'severity' => 'blocking', 'required' => 'yes',
        'decision_required' => 'yes', 'reason' => 'Runtime reference must be removed.',
    ]]],
];
$snapshotIntegrity = [
    'effect' => 'verify', 'snapshot_integrity' => 'ready', 'snapshot_integrity_valid' => 'yes',
    'post_snapshot_execution_ready' => 'yes', 'source_matches_snapshot' => 'yes',
    'can_execute' => 'no', 'can_apply' => 'no', 'can_archive' => 'no', 'can_delete' => 'no',
    'grants_execution_authority' => 'no', 'requires_separate_execution_capability' => 'yes',
    'snapshot_integrity_fingerprint' => 'deletion-snapshot-integrity:current',
    'current_packet' => [
        'change_set_fingerprint' => 'deletion-change-set:current', 'plan_fingerprint' => 'deletion-plan:current',
        'target' => ['owner_key' => 'Manufacturing/Products', 'target_path' => 'apps/Manufacturing/modules/Products'],
    ],
];
$reference = [
    'file_path' => $referencePath, 'line_number' => 2,
    'matched_pattern' => 'Manufacturing/Products', 'current_reference' => 'Manufacturing/Products',
    'proposed_replacement' => '', 'matched_text_excerpt' => "'Manufacturing/Products'",
    'match_type' => 'exact path', 'confidence' => 'high', 'relevance' => 'runtime_php',
    'referencing_owner_key' => 'Consumer/Module', 'referencing_owner_type' => 'module',
    'impact_severity' => 'blocking',
];
$impact = [
    'status' => 'ok',
    'target' => ['owner_key' => 'Manufacturing/Products', 'target_path' => 'apps/Manufacturing/modules/Products'],
    'references' => [$reference],
];
$resolver = static fn(array $request, ?string $repoRoot): array => $impact;

$ready = StudioDeletionReferenceRemediationReadinessService::assess($changeSet, $snapshotIntegrity, $root, $resolver);
$assert(($ready['status'] ?? '') === 'ok', 'ready assessment returns ok');
$assert(($ready['effect'] ?? '') === 'verify', 'capability declares verify effect');
$assert(($ready['reference_remediation_readiness_version'] ?? '') === StudioDeletionReferenceRemediationReadinessService::VERSION, 'version is explicit');
$assert(($ready['reference_remediation_readiness'] ?? '') === 'ready', 'exact current evidence is ready');
$assert(($ready['preconditions_ready'] ?? '') === 'yes', 'patch preconditions are ready');
$assert(($ready['reference_remediation_ready'] ?? '') === 'yes', 'blocking remediation is ready');
$assert(($ready['requires_review_decision'] ?? '') === 'no', 'blocking change does not create review state');
foreach (['can_write','can_apply','can_execute','can_archive','can_delete','grants_execution_authority'] as $field) {
    $assert(($ready[$field] ?? '') === 'no', $field . ' remains denied');
}
$assert(($ready['requires_separate_patch_capability'] ?? '') === 'yes', 'separate patch capability is required');
$assert(($ready['requires_separate_execution_capability'] ?? '') === 'yes', 'separate execution capability is required');
$assert(($ready['source']['snapshot_integrity_fingerprint'] ?? '') === 'deletion-snapshot-integrity:current', 'result binds snapshot integrity');
$assert(($ready['summary']['planned_file_change_count'] ?? -1) === 1, 'summary counts planned file change');
$assert(($ready['summary']['blocking_change_count'] ?? -1) === 1, 'summary counts blocking change');
$assert(count($ready['patch_preconditions'] ?? []) === 1, 'one deterministic precondition is emitted');
$precondition = $ready['patch_preconditions'][0] ?? [];
$assert(str_starts_with((string)($precondition['precondition_id'] ?? ''), 'reference-remediation-precondition:'), 'precondition id is deterministic');
$assert(($precondition['expected_file_sha256'] ?? '') === hash_file('sha256', $absolute), 'precondition freezes exact file hash');
$assert(($precondition['expected_line_numbers'] ?? []) === [2], 'precondition preserves exact line set');
$assert(($precondition['expected_reference_count'] ?? 0) === 1, 'precondition preserves reference count');
$assert(($precondition['patch_policy']['fuzzy_apply_allowed'] ?? '') === 'no', 'fuzzy apply is prohibited');
$assert(($precondition['patch_policy']['force_apply_allowed'] ?? '') === 'no', 'force apply is prohibited');
$assert(($precondition['patch_policy']['must_bind_snapshot_integrity_fingerprint'] ?? '') === 'deletion-snapshot-integrity:current', 'patch policy binds snapshot integrity');
$line = $precondition['line_evidence'][0] ?? [];
$assert(($line['line_number'] ?? 0) === 2, 'line evidence preserves line number');
$assert(preg_match('/^[a-f0-9]{64}$/', (string)($line['line_sha256'] ?? '')) === 1, 'line SHA-256 is emitted');
$assert(preg_match('/^[a-f0-9]{64}$/', (string)($line['context_sha256'] ?? '')) === 1, 'context SHA-256 is emitted');
$assert(($line['matched_patterns'] ?? []) === ['Manufacturing/Products'], 'matched pattern is preserved');
$assert(($line['pattern_present'] ?? '') === 'yes', 'matched pattern is present in current line');
$assert(str_starts_with((string)($ready['reference_remediation_readiness_fingerprint'] ?? ''), 'deletion-reference-remediation-readiness:'), 'readiness fingerprint is emitted');
$assert(($ready['blocking_reasons'] ?? []) === [], 'ready state has no blockers');

$repeat = StudioDeletionReferenceRemediationReadinessService::assess($changeSet, $snapshotIntegrity, $root, $resolver);
$assert(($repeat['reference_remediation_readiness_fingerprint'] ?? '') === ($ready['reference_remediation_readiness_fingerprint'] ?? ''), 'readiness fingerprint is deterministic');
$assert(($repeat['patch_preconditions'][0]['precondition_id'] ?? '') === ($precondition['precondition_id'] ?? ''), 'precondition id is deterministic across runs');

$reviewChangeSet = $changeSet;
$reviewChangeSet['proposed_changes']['file_changes'][0]['severity'] = 'review';
$reviewChangeSet['proposed_changes']['file_changes'][0]['change_type'] = 'review_reference';
$reviewImpact = $impact;
$reviewImpact['references'][0]['impact_severity'] = 'review';
$review = StudioDeletionReferenceRemediationReadinessService::assess($reviewChangeSet, $snapshotIntegrity, $root, static fn(): array => $reviewImpact);
$assert(($review['reference_remediation_readiness'] ?? '') === 'review_required', 'review evidence yields review-required state');
$assert(($review['preconditions_ready'] ?? '') === 'yes', 'review preconditions are still frozen');
$assert(($review['reference_remediation_ready'] ?? '') === 'no', 'review decision is required before remediation');
$assert(($review['requires_review_decision'] ?? '') === 'yes', 'review decision requirement is explicit');

$noChanges = $changeSet;
$noChanges['proposed_changes']['file_changes'] = [];
$none = StudioDeletionReferenceRemediationReadinessService::assess($noChanges, $snapshotIntegrity, $root, $resolver);
$assert(($none['reference_remediation_readiness'] ?? '') === 'no_changes', 'empty file-change plan yields no-changes state');
$assert(($none['reference_remediation_ready'] ?? '') === 'yes', 'no changes require no patch work');

$driftImpact = $impact;
$driftImpact['references'][0]['line_number'] = 1;
$drifted = StudioDeletionReferenceRemediationReadinessService::assess($changeSet, $snapshotIntegrity, $root, static fn(): array => $driftImpact);
$assert(($drifted['reference_remediation_readiness'] ?? '') === 'drifted', 'changed reference line yields drifted state');
$assert(in_array('REFERENCE_LINE_SET_DRIFTED:' . $referencePath, $drifted['blocking_reasons'] ?? [], true), 'line-set drift reason is explicit');

$extraImpact = $impact;
$extraImpact['references'][] = array_merge($reference, ['file_path' => 'docs/reference.md', 'line_number' => 1, 'impact_severity' => 'cleanup', 'referencing_owner_key' => '']);
$extra = StudioDeletionReferenceRemediationReadinessService::assess($changeSet, $snapshotIntegrity, $root, static fn(): array => $extraImpact);
$assert(($extra['reference_remediation_readiness'] ?? '') === 'drifted', 'unplanned current reference group yields drift');
$assert(in_array('UNPLANNED_REFERENCE_GROUP:docs/reference.md', $extra['blocking_reasons'] ?? [], true), 'unplanned group drift is explicit');

$unsafe = StudioDeletionReferenceRemediationReadinessService::assess(
    $changeSet,
    $snapshotIntegrity,
    $root,
    $resolver,
    static fn(): array => ['exists'=>'yes','is_file'=>'yes','is_link'=>'yes','readable'=>'yes','text_file'=>'yes','sha256'=>str_repeat('a',64),'size_bytes'=>1,'line_count'=>1,'lines'=>['Manufacturing/Products']]
);
$assert(($unsafe['reference_remediation_readiness'] ?? '') === 'blocked', 'symlink reference file fails closed');
$assert(in_array('REFERENCE_FILE_UNSAFE_OR_UNAVAILABLE:' . $referencePath, $unsafe['blocking_reasons'] ?? [], true), 'unsafe file blocker is explicit');

$staleIntegrity = $snapshotIntegrity;
$staleIntegrity['snapshot_integrity'] = 'source_changed';
$staleIntegrity['snapshot_integrity_valid'] = 'no';
$unknown = StudioDeletionReferenceRemediationReadinessService::assess($changeSet, $staleIntegrity, $root, $resolver);
$assert(($unknown['reference_remediation_readiness'] ?? '') === 'unknown', 'non-ready snapshot integrity fails packet validation');
$assert(in_array('SNAPSHOT_INTEGRITY_NOT_READY', $unknown['blocking_reasons'] ?? [], true), 'snapshot-integrity blocker is explicit');

$wrongPacket = $snapshotIntegrity;
$wrongPacket['current_packet']['change_set_fingerprint'] = 'stale';
$unknown = StudioDeletionReferenceRemediationReadinessService::assess($changeSet, $wrongPacket, $root, $resolver);
$assert(in_array('SNAPSHOT_INTEGRITY_PACKET_MISMATCH', $unknown['blocking_reasons'] ?? [], true), 'packet mismatch is explicit');

$error = StudioDeletionReferenceRemediationReadinessService::assess($changeSet, $snapshotIntegrity, $root, static function (): array { throw new RuntimeException('reference resolver failure'); });
$assert(($error['reference_remediation_readiness'] ?? '') === 'unknown', 'resolver exception fails safely');
$assert(($error['diagnostics'][0]['code'] ?? '') === 'DELETION_REFERENCE_REMEDIATION_DISCOVERY_FAILED', 'resolver failure diagnostic is explicit');

$sourceCode = file_get_contents(dirname(__DIR__) . '/Services/StudioDeletionReferenceRemediationReadinessService.php');
$assert(is_string($sourceCode) && !preg_match('/file_put_contents|fwrite|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|copy\s*\(|PDO|INSERT\s+INTO|UPDATE\s+.+SET|DELETE\s+FROM/i', $sourceCode), 'capability contains no mutation implementation');

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($iterator as $item) { $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); }
@rmdir($root);

echo "\nResults: {$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
