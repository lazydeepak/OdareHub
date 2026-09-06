<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

/**
 * Read-only deterministic patch proposal builder for deletion-reference remediation.
 * It never writes files and never turns ambiguous review evidence into an automatic patch.
 */
final class StudioDeletionReferenceRemediationPatchProposalService
{
    public const EFFECT = 'plan';
    public const VERSION = 'studio.deletion-reference-remediation-patch-proposal.v1';

    public const STATE_READY = 'ready';
    public const STATE_DECISION_REQUIRED = 'decision_required';
    public const STATE_NO_CHANGES = 'no_changes';
    public const STATE_DRIFTED = 'drifted';
    public const STATE_BLOCKED = 'blocked';
    public const STATE_UNKNOWN = 'unknown';

    /**
     * @param array<string,mixed> $readiness
     * @param callable(string,?string):array<string,mixed>|null $fileReader
     * @return array<string,mixed>
     */
    public static function propose(array $readiness, ?string $root = null, ?callable $fileReader = null): array
    {
        $source = [
            'reference_remediation_readiness_fingerprint' => trim((string)($readiness['reference_remediation_readiness_fingerprint'] ?? '')),
            'snapshot_integrity_fingerprint' => trim((string)($readiness['current_packet']['snapshot_integrity_fingerprint'] ?? '')),
            'change_set_fingerprint' => trim((string)($readiness['current_packet']['change_set_fingerprint'] ?? '')),
            'plan_fingerprint' => trim((string)($readiness['current_packet']['plan_fingerprint'] ?? '')),
        ];
        $target = self::arr($readiness, 'target');
        $preconditions = self::arrays($readiness['patch_preconditions'] ?? []);
        $packetProblems = self::packetProblems($readiness, $source);
        if ($packetProblems !== []) {
            return self::result(self::STATE_UNKNOWN, $source, $target, [], [], $packetProblems, [
                self::diag('DELETION_REFERENCE_PATCH_PROPOSAL_PACKET_INVALID', 'Patch proposal requires an exact current reference-remediation readiness packet.', ['reasons' => $packetProblems]),
            ]);
        }
        if ($preconditions === []) {
            return self::result(self::STATE_NO_CHANGES, $source, $target, [], [], [], []);
        }

        $reader = $fileReader ?? static fn(string $path, ?string $root): array => self::readFile($path, $root);
        $proposals = [];
        $decisionItems = [];
        $blockingReasons = [];
        $diagnostics = [];

        foreach ($preconditions as $precondition) {
            $path = self::path((string)($precondition['path'] ?? ''));
            $severity = trim((string)($precondition['severity'] ?? 'review'));
            $preconditionId = trim((string)($precondition['precondition_id'] ?? ''));
            if ($path === '' || $preconditionId === '' || !in_array($severity, ['blocking', 'review', 'cleanup'], true)) {
                $blockingReasons[] = 'PATCH_PRECONDITION_INVALID';
                continue;
            }

            try {
                $file = $reader($path, $root);
            } catch (\Throwable $exception) {
                $blockingReasons[] = 'PATCH_FILE_READ_FAILED:' . $path;
                $diagnostics[] = self::diag('DELETION_REFERENCE_PATCH_FILE_READ_FAILED', $exception->getMessage() !== '' ? $exception->getMessage() : 'Reference file could not be read.', ['path' => $path]);
                continue;
            }
            if (!is_array($file) || !self::fileMatchesPrecondition($file, $precondition)) {
                $blockingReasons[] = 'PATCH_FILE_PRECONDITION_DRIFTED:' . $path;
                continue;
            }

            $lines = array_values(array_map('strval', $file['lines'] ?? []));
            if ($severity === 'review') {
                $decisionItems[] = self::decisionItem($precondition, $lines);
                continue;
            }

            $edits = [];
            foreach (self::arrays($precondition['line_evidence'] ?? []) as $lineEvidence) {
                $lineNumber = (int)($lineEvidence['line_number'] ?? 0);
                $index = $lineNumber - 1;
                if ($lineNumber < 1 || !array_key_exists($index, $lines)) {
                    $blockingReasons[] = 'PATCH_LINE_MISSING:' . $path . ':' . $lineNumber;
                    continue;
                }
                $before = (string)$lines[$index];
                if (!self::lineMatchesEvidence($lines, $index, $lineEvidence)) {
                    $blockingReasons[] = 'PATCH_LINE_PRECONDITION_DRIFTED:' . $path . ':' . $lineNumber;
                    continue;
                }
                $patterns = self::strings($lineEvidence['matched_patterns'] ?? []);
                if ($patterns === []) {
                    $blockingReasons[] = 'PATCH_PATTERN_MISSING:' . $path . ':' . $lineNumber;
                    continue;
                }
                usort($patterns, static fn(string $left, string $right): int => strlen($right) <=> strlen($left) ?: strcmp($left, $right));
                $after = $before;
                $safe = true;
                foreach ($patterns as $pattern) {
                    if ($pattern === '' || substr_count($after, $pattern) !== 1) {
                        $safe = false;
                        break;
                    }
                    $after = str_replace($pattern, '', $after);
                }
                if (!$safe || $after === $before) {
                    $blockingReasons[] = 'PATCH_PATTERN_AMBIGUOUS:' . $path . ':' . $lineNumber;
                    continue;
                }
                $edits[] = [
                    'edit_id' => 'reference-patch-edit:' . substr(sha1($preconditionId . '|' . $lineNumber . '|' . $before . '|' . $after), 0, 24),
                    'line_number' => $lineNumber,
                    'action' => trim($after) === '' ? 'replace_with_blank_line' : 'replace_line',
                    'before' => $before,
                    'after' => $after,
                    'before_sha256' => hash('sha256', $before),
                    'after_sha256' => hash('sha256', $after),
                    'context_sha256' => (string)($lineEvidence['context_sha256'] ?? ''),
                    'removed_patterns' => $patterns,
                    'unified_hunk' => '@@ -' . $lineNumber . ',1 +' . $lineNumber . ",1 @@\n-" . $before . "\n+" . $after,
                ];
            }
            if ($edits === []) {
                $blockingReasons[] = 'PATCH_CANDIDATE_EMPTY:' . $path;
                continue;
            }
            usort($edits, static fn(array $left, array $right): int => ((int)$left['line_number']) <=> ((int)$right['line_number']));
            $material = [
                'version' => self::VERSION,
                'readiness_fingerprint' => $source['reference_remediation_readiness_fingerprint'],
                'precondition_id' => $preconditionId,
                'path' => $path,
                'expected_file_sha256' => (string)($precondition['expected_file_sha256'] ?? ''),
                'edits' => $edits,
            ];
            $proposals[] = [
                'proposal_id' => 'reference-patch-proposal:' . substr(sha1(self::json($material)), 0, 24),
                'precondition_id' => $preconditionId,
                'change_id' => (string)($precondition['change_id'] ?? ''),
                'operation_id' => (string)($precondition['operation_id'] ?? ''),
                'owner_key' => (string)($precondition['owner_key'] ?? ''),
                'path' => $path,
                'severity' => $severity,
                'proposal_state' => 'candidate_ready',
                'expected_file_sha256' => (string)($precondition['expected_file_sha256'] ?? ''),
                'expected_line_count' => (int)($precondition['expected_line_count'] ?? count($lines)),
                'edit_count' => count($edits),
                'edits' => $edits,
                'unified_diff' => "--- a/{$path}\n+++ b/{$path}\n" . implode("\n", array_column($edits, 'unified_hunk')) . "\n",
                'apply_policy' => [
                    'exact_file_hash_required' => 'yes',
                    'exact_line_hash_required' => 'yes',
                    'exact_context_hash_required' => 'yes',
                    'fuzzy_apply_allowed' => 'no',
                    'force_apply_allowed' => 'no',
                    'preserve_unrelated_content' => 'yes',
                ],
            ];
        }

        usort($proposals, static fn(array $left, array $right): int => strcmp((string)$left['path'], (string)$right['path']));
        usort($decisionItems, static fn(array $left, array $right): int => strcmp((string)$left['path'], (string)$right['path']));
        $blockingReasons = array_values(array_unique($blockingReasons));
        if ($blockingReasons !== []) {
            return self::result(self::STATE_DRIFTED, $source, $target, $proposals, $decisionItems, $blockingReasons, array_merge($diagnostics, [
                self::diag('DELETION_REFERENCE_PATCH_PROPOSAL_DRIFTED', 'One or more exact patch preconditions no longer match current file content.', ['reasons' => $blockingReasons]),
            ]));
        }
        return self::result($decisionItems !== [] ? self::STATE_DECISION_REQUIRED : self::STATE_READY, $source, $target, $proposals, $decisionItems, [], []);
    }

    private static function packetProblems(array $readiness, array $source): array
    {
        $problems = [];
        if (in_array('', array_values($source), true)) {
            $problems[] = 'SOURCE_FINGERPRINT_MISSING';
        }
        if ((string)($readiness['effect'] ?? '') !== 'verify'
            || !in_array((string)($readiness['reference_remediation_readiness'] ?? ''), ['ready', 'review_required', 'no_changes'], true)
            || (string)($readiness['preconditions_ready'] ?? 'no') !== 'yes') {
            $problems[] = 'REMEDIATION_READINESS_INVALID';
        }
        foreach (['can_write', 'can_apply', 'can_execute', 'can_archive', 'can_delete', 'grants_execution_authority'] as $field) {
            if ((string)($readiness[$field] ?? 'yes') !== 'no') {
                $problems[] = 'REMEDIATION_AUTHORITY_INVALID:' . $field;
            }
        }
        if ((string)($readiness['requires_separate_patch_capability'] ?? '') !== 'yes') {
            $problems[] = 'PATCH_CAPABILITY_SEPARATION_INVALID';
        }
        return array_values(array_unique($problems));
    }

    private static function fileMatchesPrecondition(array $file, array $precondition): bool
    {
        return (string)($file['exists'] ?? 'no') === 'yes'
            && (string)($file['is_file'] ?? 'no') === 'yes'
            && (string)($file['is_link'] ?? 'yes') === 'no'
            && (string)($file['readable'] ?? 'no') === 'yes'
            && (string)($file['text_file'] ?? 'no') === 'yes'
            && hash_equals((string)($precondition['expected_file_sha256'] ?? ''), (string)($file['sha256'] ?? ''))
            && (int)($precondition['expected_size_bytes'] ?? -1) === (int)($file['size_bytes'] ?? -2)
            && (int)($precondition['expected_line_count'] ?? -1) === (int)($file['line_count'] ?? -2)
            && is_array($file['lines'] ?? null);
    }

    private static function lineMatchesEvidence(array $lines, int $index, array $evidence): bool
    {
        $line = (string)$lines[$index];
        $previous = $index > 0 ? (string)$lines[$index - 1] : '';
        $next = $index + 1 < count($lines) ? (string)$lines[$index + 1] : '';
        return hash_equals((string)($evidence['line_sha256'] ?? ''), hash('sha256', $line))
            && hash_equals((string)($evidence['trimmed_line_sha256'] ?? ''), hash('sha256', trim($line)))
            && hash_equals((string)($evidence['context_sha256'] ?? ''), hash('sha256', $previous . "\n" . $line . "\n" . $next));
    }

    private static function decisionItem(array $precondition, array $lines): array
    {
        $lineItems = [];
        foreach (self::arrays($precondition['line_evidence'] ?? []) as $evidence) {
            $lineNumber = (int)($evidence['line_number'] ?? 0);
            $lineItems[] = [
                'line_number' => $lineNumber,
                'current_line' => $lineNumber > 0 && array_key_exists($lineNumber - 1, $lines) ? (string)$lines[$lineNumber - 1] : '',
                'matched_patterns' => self::strings($evidence['matched_patterns'] ?? []),
                'match_types' => self::strings($evidence['match_types'] ?? []),
                'relevance' => self::strings($evidence['relevance'] ?? []),
                'confidence' => self::strings($evidence['confidence'] ?? []),
            ];
        }
        $material = [
            'version' => self::VERSION,
            'precondition_id' => (string)($precondition['precondition_id'] ?? ''),
            'path' => (string)($precondition['path'] ?? ''),
            'line_items' => $lineItems,
        ];
        return [
            'decision_id' => 'reference-patch-decision:' . substr(sha1(self::json($material)), 0, 24),
            'precondition_id' => (string)($precondition['precondition_id'] ?? ''),
            'change_id' => (string)($precondition['change_id'] ?? ''),
            'operation_id' => (string)($precondition['operation_id'] ?? ''),
            'path' => (string)($precondition['path'] ?? ''),
            'severity' => 'review',
            'decision_state' => 'decision_required',
            'line_items' => $lineItems,
            'alternatives' => [
                ['decision' => 'remove_exact_reference', 'automatic_candidate_available' => 'no', 'requires_manual_replacement_text' => 'no'],
                ['decision' => 'replace_reference_manually', 'automatic_candidate_available' => 'no', 'requires_manual_replacement_text' => 'yes'],
                ['decision' => 'retain_reference_with_justification', 'automatic_candidate_available' => 'no', 'requires_manual_replacement_text' => 'no'],
            ],
        ];
    }

    private static function readFile(string $path, ?string $root): array
    {
        $repositoryRoot = $root !== null && trim($root) !== '' ? rtrim($root, DIRECTORY_SEPARATOR) : (defined('APP_ROOT') ? rtrim((string)APP_ROOT, DIRECTORY_SEPARATOR) : dirname(__DIR__, 3));
        $absolute = $repositoryRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        $exists = file_exists($absolute);
        $contents = $exists && is_file($absolute) && is_readable($absolute) ? file_get_contents($absolute) : false;
        $text = is_string($contents) && !str_contains($contents, "\0");
        $lines = $text ? preg_split('/\R/', $contents) : [];
        return [
            'exists' => $exists ? 'yes' : 'no', 'is_file' => is_file($absolute) ? 'yes' : 'no', 'is_link' => is_link($absolute) ? 'yes' : 'no',
            'readable' => is_readable($absolute) ? 'yes' : 'no', 'text_file' => $text ? 'yes' : 'no',
            'sha256' => $text ? (string)(hash_file('sha256', $absolute) ?: '') : '',
            'size_bytes' => is_file($absolute) && filesize($absolute) !== false ? (int)filesize($absolute) : -1,
            'line_count' => is_array($lines) ? count($lines) : -1, 'lines' => is_array($lines) ? array_values(array_map('strval', $lines)) : [],
        ];
    }

    private static function result(string $state, array $source, array $target, array $proposals, array $decisions, array $reasons, array $diagnostics): array
    {
        $material = ['version' => self::VERSION, 'state' => $state, 'source' => $source, 'target' => $target, 'proposals' => $proposals, 'decisions' => $decisions, 'blocking_reasons' => $reasons];
        return [
            'status' => in_array($state, [self::STATE_DRIFTED, self::STATE_BLOCKED, self::STATE_UNKNOWN], true) ? 'partial' : 'ok',
            'effect' => self::EFFECT,
            'patch_proposal_version' => self::VERSION,
            'patch_proposal_state' => $state,
            'proposals_ready' => in_array($state, [self::STATE_READY, self::STATE_DECISION_REQUIRED, self::STATE_NO_CHANGES], true) ? 'yes' : 'no',
            'all_decisions_resolved' => $decisions === [] ? 'yes' : 'no',
            'can_write' => 'no', 'can_apply' => 'no', 'can_execute' => 'no', 'can_archive' => 'no', 'can_delete' => 'no', 'grants_execution_authority' => 'no',
            'requires_separate_decision_capability' => $decisions === [] ? 'no' : 'yes',
            'requires_separate_patch_apply_capability' => 'yes',
            'requires_separate_execution_capability' => 'yes',
            'current_packet' => $source,
            'target' => $target,
            'summary' => ['proposal_count' => count($proposals), 'decision_required_count' => count($decisions), 'edit_count' => array_sum(array_map(static fn(array $proposal): int => (int)($proposal['edit_count'] ?? 0), $proposals))],
            'patch_proposals' => $proposals,
            'decision_items' => $decisions,
            'blocking_reasons' => array_values(array_unique($reasons)),
            'patch_proposal_fingerprint' => 'deletion-reference-patch-proposal:' . substr(sha1(self::json($material)), 0, 24),
            'diagnostics' => $diagnostics,
        ];
    }

    private static function arr(array $source, string $key): array { return isset($source[$key]) && is_array($source[$key]) ? $source[$key] : []; }
    private static function arrays($value): array { return is_array($value) ? array_values(array_filter($value, 'is_array')) : []; }
    private static function strings($value): array { $out = []; if (is_array($value)) { foreach ($value as $item) { $item = trim((string)$item); if ($item !== '') { $out[$item] = true; } } } $items = array_keys($out); sort($items, SORT_NATURAL | SORT_FLAG_CASE); return array_map('strval', $items); }
    private static function path(string $path): string { $parts = []; foreach (explode('/', ltrim(str_replace('\\', '/', trim($path)), '/')) as $part) { if ($part === '' || $part === '.') { continue; } if ($part === '..') { return ''; } $parts[] = $part; } return implode('/', $parts); }
    private static function diag(string $code, string $message, array $extra = []): array { return array_merge(['code' => $code, 'severity' => 'error', 'message' => $message], $extra); }
    private static function json(array $material): string { $normalized = self::sortRecursive($material); $encoded = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); return is_string($encoded) ? $encoded : serialize($normalized); }
    private static function sortRecursive($value) { if (!is_array($value)) { return $value; } if (array_is_list($value)) { return array_map([self::class, 'sortRecursive'], $value); } ksort($value); foreach ($value as $key => $item) { $value[$key] = self::sortRecursive($item); } return $value; }
}
