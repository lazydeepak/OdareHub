<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\CustomizationStudio\Diagnose\StyleCompliance\Services;

require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Services/StyleComplianceRepairReadinessService.php';

/**
 * Server-side bounded declaration replacement engine for Style Compliance.
 *
 * The browser submits only a stable proposal id + CSRF. This service:
 *   1. Resolves the full proposal from a fresh trusted scan.
 *   2. Re-runs the 14 readiness checks (source, fingerprint, value, domain).
 *   3. Creates a pre-mutation source snapshot.
 *   4. Performs surgical value-only replacement of exactly one declaration.
 *   5. Writes atomically (tempfile + rename).
 *   6. Verifies the modified file is valid (PHP lint for .php sources).
 *   7. Re-scans and confirm the proposal is resolved or honestly reports
 *      why it is not.
 *
 * The browser must never supply file paths, selectors, values, or owner
 * authority — only proposal_id and csrf.
 */
final class StyleComplianceRepairEngineService
{
    private const SNAPSHOT_ROOT = '/storage/studio-snapshots/style-compliance';

    /**
     * Execute a single guarded repair for one proposal.
     *
     * @param array<string,mixed> $proposal Full proposal record from a trusted fresh scan.
     * @param array<string,mixed> $options  Optional override: expected_source_fingerprint, source_contents, scan_result for post-apply re-scan.
     * @return array{ok:bool,proposal_id:string,state:string,reason:string,snapshot_path?:string,evidence:array<string,mixed>}
     */
    public static function executeRepair(array $proposal, array $options = []): array
    {
        $proposalId = (string)($proposal['proposal_id'] ?? '');
        $filePath = (string)($proposal['file_path'] ?? '');
        $property = strtolower(trim((string)($proposal['property'] ?? '')));
        $currentValue = trim((string)($proposal['current_value'] ?? $proposal['expected_current_value'] ?? ''));
        $replacementValue = trim((string)($proposal['replacement_value'] ?? ''));
        $replacementToken = (string)($proposal['replacement_token'] ?? '');

        // --- Phase 1: Input integrity ---
        if ($proposalId === '' || $filePath === '' || $property === '' || $currentValue === '' || $replacementValue === '') {
            return self::result($proposalId, 'blocked', 'Incomplete proposal data.', [], []);
        }

        // --- Phase 2: Readiness verification ---
        $sourceContents = isset($options['source_contents']) && is_string($options['source_contents'])
            ? $options['source_contents'] : null;
        $readinessOptions = [
            'expected_source_fingerprint' => (string)($options['expected_source_fingerprint'] ?? ''),
        ];
        $readiness = StyleComplianceRepairReadinessService::checkProposalForReadiness($proposal, $sourceContents, $readinessOptions);
        $readinessState = (string)($readiness['state'] ?? 'blocked');
        if ($readinessState !== StyleComplianceRepairReadinessService::STATE_READY) {
            $reason = (string)($readiness['reason'] ?? 'Readiness preflight did not pass.');
            return self::result($proposalId, $readinessState, $reason, [], $readiness['checks'] ?? []);
        }

        // --- Phase 3: Source resolution ---
        $absolute = self::resolveAbsolutePath($filePath);
        if ($absolute === null) {
            return self::result($proposalId, 'stale', 'Source file cannot be resolved to an absolute path.', [], []);
        }
        if (!is_file($absolute)) {
            return self::result($proposalId, 'stale', 'Source file no longer exists at the expected path.', [], []);
        }

        $css = $sourceContents ?? @file_get_contents($absolute);
        if (!is_string($css) || $css === '') {
            return self::result($proposalId, 'stale', 'Source file could not be read.', [], []);
        }

        // --- Phase 4: Surgical replacement ---
        $modified = self::surgicalReplaceDeclarationValue($css, $property, $currentValue, $replacementValue, (string)($proposal['selector'] ?? ''));
        if ($modified === null) {
            return self::result($proposalId, 'stale', 'Declaration value could not be matched in the current source. The file may have changed since the scan.', [], []);
        }
        if ($modified === $css) {
            return self::result($proposalId, 'ambiguous', 'Source content is identical after replacement — the value may already have been applied.', [], []);
        }

        // Verify the NEW value appears where the OLD value was (sanity check)
        $newValueCount = self::countDeclarationMatches($modified, $property, $replacementValue);
        if ($newValueCount < 1) {
            return self::result($proposalId, 'blocked', 'Surgical replacement succeeded but the new value cannot be found in the result.', [], []);
        }

        // --- Phase 5: Snapshot before write ---
        $snapshotPath = self::createSnapshot($filePath, $css);
        if ($snapshotPath === null) {
            return self::result($proposalId, 'blocked', 'Failed to create pre-mutation snapshot.', [], []);
        }

        // --- Phase 6: Atomic write ---
        $writeResult = self::atomicWrite($absolute, $modified);
        if ($writeResult !== true) {
            return self::result($proposalId, 'blocked', 'Failed to write modified source file: ' . $writeResult, ['snapshot_path' => $snapshotPath], []);
        }

        // --- Phase 7: Post-apply validation ---
        // 7a. PHP lint for .php sources
        if (str_ends_with(strtolower($filePath), '.php')) {
            $lintOk = self::checkPhpLint($absolute);
            if (!$lintOk) {
                self::restoreFromSnapshot($snapshotPath, $absolute);
                return self::result($proposalId, 'blocked', 'Post-apply PHP lint failed — source was rolled back from snapshot.', ['snapshot_path' => $snapshotPath, 'rolled_back' => true], []);
            }
        }

        // 7b. Re-scan to verify proposal resolution
        $scanScope = (string)($options['scan_scope'] ?? '');
        $scanOwner = (string)($options['scan_owner_key'] ?? '');
        $rescanResult = self::verifyProposalResolved($proposalId, $scanScope, $scanOwner);

        // 7c. Check the modified post-write source fingerprint
        $postWriteContents = @file_get_contents($absolute);
        $postWriteFingerprint = is_string($postWriteContents) ? self::sourceFingerprint($postWriteContents) : '';

        $evidence = [
            'snapshot_path' => $snapshotPath,
            'post_write_source_fingerprint' => $postWriteFingerprint,
            'rescanned' => $rescanResult['rescanned'],
            'resolved' => $rescanResult['resolved'],
            'rescan_finding' => $rescanResult['finding'],
        ];

        if ($rescanResult['rescanned'] && $rescanResult['resolved']) {
            return self::result($proposalId, 'resolved', 'Repair applied, source written atomically, and re-scan confirms the proposal is resolved.', $evidence, []);
        }

        if ($rescanResult['rescanned'] && !$rescanResult['resolved']) {
            // The mutation happened but verification failed — not a rollback scenario,
            // but we must report it honestly.
            return self::result($proposalId, 'unverified', 'Source was written but re-scan could not confirm the exact proposal is resolved. Manual review recommended.', $evidence, []);
        }

        return self::result($proposalId, 'applied_unverified', 'Source was written atomically with snapshot available. Automated re-scan was not completed.', $evidence, []);
    }

    /**
     * Create a timestamped pre-mutation snapshot of the source file.
     *
     * Returns the relative snapshot path (for UI display) or null on failure.
     */
    public static function createSnapshot(string $filePath, string $contents): ?string
    {
        $snapshotDir = APP_ROOT . self::SNAPSHOT_ROOT;
        if (!is_dir($snapshotDir)) {
            if (!@mkdir($snapshotDir, 0755, true) && !is_dir($snapshotDir)) {
                return null;
            }
        }

        $slug = preg_replace('/[^a-z0-9._-]+/i', '_', ltrim($filePath, '/'));
        $slug = trim((string)$slug, '_');
        if ($slug === '') {
            $slug = 'source';
        }

        $timestamp = gmdate('Ymd_His');
        $snapshotFilename = 'repair_' . $slug . '_' . $timestamp . '_' . substr(sha1($filePath . microtime(true)), 0, 8) . '.bak';
        $snapshotPath = $snapshotDir . '/' . $snapshotFilename;

        $written = @file_put_contents($snapshotPath, $contents);
        if ($written === false) {
            return null;
        }

        // Write sidecar manifest for traceability
        $manifest = [
            'source_path' => $filePath,
            'snapshot_path' => $snapshotFilename,
            'created_at' => gmdate('c'),
            'sha256' => hash('sha256', $contents),
            'tool' => 'style_compliance',
            'action' => 'repair_execute',
        ];
        $manifestPath = $snapshotPath . '.json';
        @file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SNAPSHOT_ROOT . '/' . $snapshotFilename;
    }

    /**
     * Perform surgical value-only replacement of one declaration.
     *
     * Uses the same anchor as StyleComplianceRepairReadinessService::resolveDeclarationTarget()
     * so preflight and execution share the same matching logic.
     *
     * @param string $css Full CSS source content.
     * @param string $property CSS property name (lowercase).
     * @param string $oldValue Current value to replace.
     * @param string $newValue Replacement value.
     * @return string|null Modified CSS, or null if the declaration was not found.
     */
    private static function surgicalReplaceDeclarationValue(string $css, string $property, string $oldValue, string $newValue, string $selector = ''): ?string
    {
        $escapedProperty = preg_quote($property, '/');
        $escapedOldValue = preg_quote($oldValue, '/');
        $escapedNewValue = preg_quote($newValue, '/');

        // When a real CSS selector is available, use selector-scoped replacement
        // so the correct declaration block is targeted (not just the first global match).
        if ($selector !== '' && !str_starts_with($selector, '[inline style')) {
            $escapedSelector = preg_quote($selector, '/');
            $pattern = '/(' . $escapedSelector . '\s*\{[^}]*' . $escapedProperty . '\s*:\s*)(' . $escapedOldValue . ')(\s*(?:!important\s*)?[;}])/i';
            $replacement = '$1' . $newValue . '$3';
            $count = 0;
            $result = preg_replace($pattern, $replacement, $css, 1, $count);
            if ($result !== null && $count === 1) {
                return $result;
            }
            // Selector-scoped match failed — verify blanket is safe before falling back
            preg_match_all('/(?<![-\w])(' . $escapedProperty . '\s*:\s*)(' . $escapedOldValue . ')(\s*(?:!important\s*)?[;}])/i', $css, $blanketMatches);
            if (count($blanketMatches[0]) !== 1) {
                return null;
            }
        }

        // Capture groups: $1 = "property: ", $2 = value, $3 = trailing whitespace+!important+semicolon/brace
        $pattern = '/(?<![-\w])(' . $escapedProperty . '\s*:\s*)(' . $escapedOldValue . ')(\s*(?:!important\s*)?[;}])/i';
        $replacement = '$1' . $newValue . '$3';

        $count = 0;
        $result = preg_replace($pattern, $replacement, $css, 1, $count);

        if ($result === null || $count === 0) {
            // Fallback: try without the trailing delimiter (edge-of-block with no semicolon)
            $patternFallback = '/(?<![-\w])(' . $escapedProperty . '\s*:\s*)(' . $escapedOldValue . ')(\s*(?:!important\s*)?[}])/i';
            $result = preg_replace($patternFallback, '$1' . $newValue . '$3', $css, 1, $count);
        }

        if ($result === null || $count === 0) {
            return null;
        }

        return $result;
    }

    /**
     * Count how many times property: value appears in CSS content.
     * Uses the same matching anchor as the readiness service.
     */
    private static function countDeclarationMatches(string $css, string $property, string $value): int
    {
        $escapedProperty = preg_quote($property, '/');
        $escapedValue = preg_quote($value, '/');
        preg_match_all('/(?<![-\w])' . $escapedProperty . '\s*:\s*' . $escapedValue . '\s*(?:!important\s*)?[;}]/i', $css, $matches);
        return count($matches[0]);
    }

    /**
     * Atomic tempfile + rename write.
     */
    private static function atomicWrite(string $absolutePath, string $contents): true|string
    {
        $tmp = $absolutePath . '.tmp.' . getmypid();
        $written = @file_put_contents($tmp, $contents);
        if ($written === false) {
            @unlink($tmp);
            return 'temp_write_failed';
        }

        if (!@rename($tmp, $absolutePath)) {
            @unlink($tmp);
            return 'rename_failed';
        }

        // Clear opcache for PHP files
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($absolutePath, true);
        }

        return true;
    }

    /**
     * Run PHP lint on a .php source file.
     */
    private static function checkPhpLint(string $absolutePath): bool
    {
        $output = [];
        $exitCode = 0;
        $escaped = escapeshellarg($absolutePath);
        exec('php -l ' . $escaped . ' 2>&1', $output, $exitCode);
        return $exitCode === 0;
    }

    /**
     * Restore source from snapshot backup.
     */
    private static function restoreFromSnapshot(string $snapshotPath, string $targetPath): bool
    {
        $absoluteSnapshot = APP_ROOT . $snapshotPath;
        if (!is_file($absoluteSnapshot)) {
            return false;
        }
        $contents = @file_get_contents($absoluteSnapshot);
        if (!is_string($contents)) {
            return false;
        }
        return @file_put_contents($targetPath, $contents) !== false;
    }

    /**
     * Re-scan the relevant scope and verify the proposal is no longer present
     * in the ready queue.
     *
     * @return array{rescanned:bool,resolved:bool,finding:string}
     */
    private static function verifyProposalResolved(string $proposalId, string $scope, string $ownerKey): array
    {
        try {
            if ($scope === '' || $ownerKey === '') {
                // If we do not know the scan context, try the readiness service resolution.
                // This requires a fresh scan of all contexts.
                $fallbackResult = StyleComplianceRepairReadinessService::checkByProposalId($proposalId);
                $fallbackState = (string)($fallbackResult['state'] ?? '');
                if ($fallbackState === 'stale' || $fallbackState === 'blocked') {
                    // Proposal not found in current scan — likely resolved
                    // But we need to be sure: the state should not be 'ready_for_guarded_repair'
                    return [
                        'rescanned' => true,
                        'resolved' => $fallbackState !== StyleComplianceRepairReadinessService::STATE_READY,
                        'finding' => 'Readiness check after repair returned: ' . $fallbackState,
                    ];
                }
                return ['rescanned' => true, 'resolved' => false, 'finding' => 'Proposal still present or state ambiguous: ' . $fallbackState];
            }

            $rescan = StyleComplianceScannerService::scan($scope, $ownerKey);
            $queues = isset($rescan['theme_repair_proposals']['queues']) && is_array($rescan['theme_repair_proposals']['queues'])
                ? $rescan['theme_repair_proposals']['queues'] : [];
            $ready = isset($queues['ready_for_future_guarded_apply']) && is_array($queues['ready_for_future_guarded_apply'])
                ? $queues['ready_for_future_guarded_apply'] : [];

            foreach ($ready as $p) {
                if (is_array($p) && (string)($p['proposal_id'] ?? '') === $proposalId) {
                    return ['rescanned' => true, 'resolved' => false, 'finding' => 'Proposal still present in the ready queue after repair.'];
                }
            }

            return ['rescanned' => true, 'resolved' => true, 'finding' => 'Proposal is no longer present in the ready queue.'];
        } catch (\Throwable $e) {
            return ['rescanned' => false, 'resolved' => false, 'finding' => 'Re-scan failed: ' . $e->getMessage()];
        }
    }

    /**
     * SHA-256 source fingerprint matching the scanner's format.
     */
    private static function sourceFingerprint(string $contents): string
    {
        return $contents !== '' ? 'sha256:' . hash('sha256', $contents) : '';
    }

    /**
     * Resolve a relative file path to an absolute path with traversal protection.
     */
    private static function resolveAbsolutePath(string $filePath): ?string
    {
        $trimmed = ltrim($filePath, '/');
        if ($trimmed === '' || str_contains($trimmed, '..')) {
            return null;
        }
        $absolute = APP_ROOT . '/' . $trimmed;
        $real = realpath($absolute);
        $root = realpath(APP_ROOT);
        if (!is_string($real) || !is_string($root)) {
            return null;
        }
        if (!str_starts_with($real, $root . DIRECTORY_SEPARATOR) && $real !== $root) {
            return null;
        }
        return $real;
    }

    /**
     * @return array{ok:bool,proposal_id:string,state:string,reason:string,snapshot_path?:string,evidence:array<string,mixed>,checks:array<int,array<string,string>>}
     */
    private static function result(string $proposalId, string $state, string $reason, array $evidence, array $checks): array
    {
        $result = [
            'ok' => $state === 'resolved',
            'proposal_id' => $proposalId,
            'state' => $state,
            'reason' => $reason,
            'evidence' => $evidence,
            'checks' => $checks,
        ];
        if (isset($evidence['snapshot_path'])) {
            $result['snapshot_path'] = $evidence['snapshot_path'];
        }
        return $result;
    }
}
