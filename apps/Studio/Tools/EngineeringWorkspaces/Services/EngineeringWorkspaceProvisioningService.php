<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\EngineeringWorkspaces\Services;

use Platform\Security\EngineeringWorkspaceContentContract;
use Platform\Security\EngineeringWorkspaceResolver;

final class EngineeringWorkspaceProvisioningService
{
    private const SNAPSHOT_ROOT = APP_ROOT . '/storage/studio-snapshots/engineering-workspaces';
    private const PLAN_TTL = 300;

    private const ELIGIBLE_STATES = ['linked_valid', 'initialization_required'];
    private const FORBIDDEN_STATES = [
        'coverage_decision_required',
        'unregistered_workspace_discovered',
        'mapping_review_required',
        'resolution_failed',
        'contract_repair_required',
    ];

    private const LANE_INITIALIZE = 'initialize_missing';
    private const LANE_ARCHIVE_RESET = 'archive_and_reset';

    private const ALLOWED_DOCUMENTS = ['overview', 'work', 'rules', 'decisions'];

    // ─────────────────────────────────────────────────────
    //  1. Build provision model from existing coverage
    // ─────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $coverage
     * @return array<string,mixed>
     */
    public static function buildProvisionModel(array $coverage): array
    {
        $rows = isset($coverage['rows']) && is_array($coverage['rows']) ? $coverage['rows'] : [];
        $eligible = [];

        foreach ($rows as $row) {
            $state = (string)($row['state'] ?? '');
            if (!in_array($state, self::ELIGIBLE_STATES, true)) {
                continue;
            }

            $workspaceKey = (string)($row['workspace_key'] ?? '');
            $documents = self::resolveDocumentStates($workspaceKey, $row);
            $laneActions = self::computeLaneActions($documents);

            $eligible[] = [
                'context_label' => $row['context_label'] ?? $workspaceKey,
                'workspace_key' => $workspaceKey,
                'evidence_type' => $row['evidence_type'] ?? 'registered_workspace',
                'state' => $state,
                'documents' => $documents,
                'lane_actions' => $laneActions,
                'has_missing' => $laneActions['has_missing'],
                'has_valid' => $laneActions['has_valid'],
                'readiness_fingerprint' => $row['readiness_fingerprint'] ?? '',
            ];
        }

        $lastTransactions = [];
        foreach ($eligible as $er) {
            $tx = self::readLastTransaction($er['workspace_key']);
            if ($tx !== null) {
                $lastTransactions[$er['workspace_key']] = $tx;
            }
        }

        return [
            'eligible_rows' => $eligible,
            'eligible_count' => count($eligible),
            'last_transactions' => $lastTransactions,
        ];
    }

    // ─────────────────────────────────────────────────────
    //  2. Generate immutable provisioning plan
    // ─────────────────────────────────────────────────────

    /**
     * @param string[] $selectedDocuments
     * @param array<string,mixed> $coverage
     * @param array<string,mixed>|null $actor
     * @return array<string,mixed>
     */
    public static function generatePlan(
        string $workspaceKey,
        array $selectedDocuments,
        string $lane,
        array $coverage,
        ?array $actor
    ): array {
        $row = self::findCoverageRow($coverage, $workspaceKey);
        if ($row === null) {
            return ['ok' => false, 'error' => 'Workspace not found in coverage'];
        }

        $state = (string)($row['state'] ?? '');
        if (!in_array($state, self::ELIGIBLE_STATES, true)) {
            return ['ok' => false, 'error' => 'Workspace is not eligible for provisioning'];
        }

        if (!in_array($lane, [self::LANE_INITIALIZE, self::LANE_ARCHIVE_RESET], true)) {
            return ['ok' => false, 'error' => 'Invalid provisioning lane'];
        }

        if ($selectedDocuments === []) {
            return ['ok' => false, 'error' => 'No documents selected'];
        }

        $invalidDocs = array_diff($selectedDocuments, self::ALLOWED_DOCUMENTS);
        if ($invalidDocs !== []) {
            return ['ok' => false, 'error' => 'Invalid document keys: ' . implode(', ', $invalidDocs)];
        }

        $docStates = self::resolveDocumentStates($workspaceKey, $row);
        $operations = [];

        foreach ($selectedDocuments as $docKey) {
            $docState = $docStates[$docKey] ?? ['state' => 'unavailable', 'content_hash' => null];

            if ($lane === self::LANE_INITIALIZE) {
                if ($docState['state'] !== 'missing') {
                    return [
                        'ok' => false,
                        'error' => 'Document "' . $docKey . '" already exists. Use archive_and_reset lane for existing documents.',
                    ];
                }
                $operations[] = [
                    'document_key' => $docKey,
                    'operation' => 'CREATE_WORKSPACE_DOCUMENT_FROM_TEMPLATE',
                    'current_state' => 'missing',
                    'current_hash' => null,
                ];
            } elseif ($lane === self::LANE_ARCHIVE_RESET) {
                if ($docState['state'] !== 'valid') {
                    return [
                        'ok' => false,
                        'error' => 'Document "' . $docKey . '" is not valid and cannot be reset.',
                    ];
                }
                $operations[] = [
                    'document_key' => $docKey,
                    'operation' => 'ARCHIVE_AND_RESET',
                    'current_state' => 'valid',
                    'current_hash' => $docState['content_hash'],
                ];
            }
        }

        if ($operations === []) {
            return ['ok' => false, 'error' => 'No operations to perform'];
        }

        $templateFingerprints = [];
        foreach (self::ALLOWED_DOCUMENTS as $docKey) {
            $path = EngineeringWorkspaceContentContract::templatePath($docKey);
            if ($path !== null && is_file($path)) {
                $content = file_get_contents($path);
                $templateFingerprints[$docKey] = $content !== false ? hash('sha256', $content) : 'unavailable';
            } else {
                $templateFingerprints[$docKey] = 'unavailable';
            }
        }

        // Render content for each operation
        $renderedContents = [];
        foreach ($operations as $op) {
            $renderedContents[$op['document_key']] = self::renderTemplateContent($op['document_key'], $workspaceKey);
        }

        $sessionId = session_id();
        $planId = 'ewp-' . bin2hex(random_bytes(16));
        $expiresAt = time() + self::PLAN_TTL;
        $workspaceDir = self::resolveWorkspaceDir($workspaceKey);

        return [
            'ok' => true,
            'plan_id' => $planId,
            'workspace_key' => $workspaceKey,
            'lane' => $lane,
            'session_binding' => $sessionId !== '' ? $sessionId : 'cli',
            'actor' => $actor['login'] ?? 'unknown',
            'expires_at' => $expiresAt,
            'operations' => $operations,
            'rendered_contents' => $renderedContents,
            'template_fingerprints' => $templateFingerprints,
            'mapping_readiness_fingerprint' => $row['readiness_fingerprint'] ?? '',
            'workspace_dir' => self::relativePath($workspaceDir),
        ];
    }

    // ─────────────────────────────────────────────────────
    //  3. Render template content with variable resolution
    // ─────────────────────────────────────────────────────

    public static function renderTemplateContent(string $documentKey, string $workspaceKey): string
    {
        return EngineeringWorkspaceContentContract::initialContent($workspaceKey, $documentKey);
    }

    // ─────────────────────────────────────────────────────
    //  4. Preview plan — compute per-document preview data
    // ─────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $plan
     * @return array<string,mixed>
     */
    public static function previewPlan(array $plan): array
    {
        if (empty($plan['plan_id'])) {
            return ['ok' => false, 'error' => 'Invalid plan'];
        }

        if (time() > ($plan['expires_at'] ?? 0)) {
            return ['ok' => false, 'error' => 'Plan has expired'];
        }

        $workspaceKey = $plan['workspace_key'] ?? '';
        $previews = [];

        foreach ($plan['operations'] as $op) {
            $docKey = $op['document_key'];
            $rendered = $plan['rendered_contents'][$docKey] ?? '';

            $previews[] = [
                'document_key' => $docKey,
                'operation' => $op['operation'],
                'current_state' => $op['current_state'],
                'current_hash' => $op['current_hash'],
                'rendered_content' => $rendered,
                'rendered_fingerprint' => hash('sha256', $rendered),
                'template_fingerprint' => $plan['template_fingerprints'][$docKey] ?? 'unavailable',
            ];
        }

        // Validate generated content against content contract
        $validationResults = [];
        foreach ($plan['operations'] as $op) {
            $content = $plan['rendered_contents'][$op['document_key']] ?? '';
            $validationResults[$op['document_key']] = EngineeringWorkspaceContentContract::validateDocumentContent(
                $op['document_key'],
                $content
            );
        }

        return [
            'ok' => true,
            'plan_id' => $plan['plan_id'],
            'workspace_key' => $workspaceKey,
            'lane' => $plan['lane'] ?? '',
            'session_binding' => $plan['session_binding'] ?? '',
            'expires_at' => $plan['expires_at'] ?? 0,
            'expires_in' => max(0, ($plan['expires_at'] ?? 0) - time()),
            'previews' => $previews,
            'validation_results' => $validationResults,
            'all_validated' => self::allValidated($validationResults),
            'mapping_readiness_fingerprint' => $plan['mapping_readiness_fingerprint'] ?? '',
        ];
    }

    // ─────────────────────────────────────────────────────
    //  5. Apply plan — execute provisioning transaction
    // ─────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $plan
     * @param array<string,mixed>|null $actor
     * @return array<string,mixed>
     */
    public static function applyPlan(array $plan, ?array $actor): array
    {
        // ── Pre-flight checks ──
        $sessionId = session_id();
        $planSession = $plan['session_binding'] ?? '';
        if ($sessionId !== '' && $planSession !== 'cli' && $sessionId !== $planSession) {
            return ['ok' => false, 'error' => 'Plan was created in a different session'];
        }

        if (time() > ($plan['expires_at'] ?? 0)) {
            return ['ok' => false, 'error' => 'Plan has expired'];
        }

        $workspaceKey = $plan['workspace_key'] ?? '';
        if ($workspaceKey === '') {
            return ['ok' => false, 'error' => 'Plan has no workspace key'];
        }

        if (!isset($plan['operations']) || !is_array($plan['operations']) || $plan['operations'] === []) {
            return ['ok' => false, 'error' => 'Plan has no operations'];
        }

        // Re-resolve workspace eligibility against current state
        $resolved = EngineeringWorkspaceContentContract::documentPath($workspaceKey, 'overview');
        if ($resolved === null) {
            return ['ok' => false, 'error' => 'Workspace is not supported'];
        }

        // Re-validate template fingerprints haven't changed
        foreach ($plan['template_fingerprints'] as $docKey => $expectedFp) {
            $path = EngineeringWorkspaceContentContract::templatePath($docKey);
            if ($path === null || !is_file($path)) {
                if ($expectedFp !== 'unavailable') {
                    return ['ok' => false, 'error' => 'Template source for "' . $docKey . '" is no longer available'];
                }
                continue;
            }
            $content = file_get_contents($path);
            $currentFp = $content !== false ? hash('sha256', $content) : 'unavailable';
            if ($currentFp !== $expectedFp) {
                return ['ok' => false, 'error' => 'Template "' . $docKey . '" has changed since plan generation'];
            }
        }

        // ── Phase 1: Render and contract-validate all generated content ──
        $renderedBatch = [];
        $validationBatch = [];
        foreach ($plan['operations'] as $op) {
            $docKey = $op['document_key'];
            $content = self::renderTemplateContent($docKey, $workspaceKey);
            $validation = EngineeringWorkspaceContentContract::validateDocumentContent($docKey, $content);

            if (!$validation['ok']) {
                return [
                    'ok' => false,
                    'error' => 'Generated content for "' . $docKey . '" failed content contract: ' . $validation['error'],
                ];
            }

            $renderedBatch[$docKey] = $content;
            $validationBatch[$docKey] = $validation;
        }

        // ── Phase 2: Create transaction archive ──
        $transactionId = 'txn-' . date('Ymd_His') . '-' . bin2hex(random_bytes(8));
        $archiveDir = self::SNAPSHOT_ROOT . '/' . $workspaceKey . '/' . $transactionId;
        $beforeDir = $archiveDir . '/before';
        $afterDir = $archiveDir . '/after';

        $before = [];
        foreach ($plan['operations'] as $op) {
            $docKey = $op['document_key'];
            $path = self::resolveCanonicalPath($workspaceKey, $docKey);
            $before[$docKey] = [
                'path' => $path !== null ? self::relativePath($path) : null,
                'existed' => $path !== null && is_file($path),
                'hash' => ($path !== null && is_file($path)) ? hash_file('sha256', $path) : null,
            ];
        }

        // Create archive directories
        if (!is_dir($beforeDir) && !@mkdir($beforeDir, 0755, true)) {
            return ['ok' => false, 'error' => 'Could not create archive directory'];
        }

        // Save original content or nonexistence markers
        $postApplyHashes = [];
        $operationsCompleted = [];
        $appliedFiles = [];
        $restoreMap = [];

        try {
            foreach ($plan['operations'] as $op) {
                $docKey = $op['document_key'];
                $path = self::resolveCanonicalPath($workspaceKey, $docKey);

                if ($path === null) {
                    throw new \RuntimeException('Cannot resolve path for "' . $docKey . '"');
                }

                $workspaceDir = dirname($path);

                // Save original content to before/
                if ($op['operation'] === 'ARCHIVE_AND_RESET') {
                    $original = is_file($path) ? file_get_contents($path) : null;
                    if ($original !== false && $original !== null) {
                        file_put_contents($beforeDir . '/' . EngineeringWorkspaceContentContract::canonicalFilename($docKey), $original);
                    }
                } elseif ($op['operation'] === 'CREATE_WORKSPACE_DOCUMENT_FROM_TEMPLATE') {
                    // Create workspace directory if needed
                    if (!is_dir($workspaceDir) && !@mkdir($workspaceDir, 0755, true)) {
                        throw new \RuntimeException('Could not create workspace directory for "' . $workspaceKey . '"');
                    }
                }

                // Write new content atomically
                $tmpPath = $path . '.tmp.' . bin2hex(random_bytes(8));
                $newContent = $renderedBatch[$docKey];

                $written = @file_put_contents($tmpPath, $newContent, LOCK_EX);
                if ($written === false) {
                    @unlink($tmpPath);
                    throw new \RuntimeException('Write failed for "' . $docKey . '"');
                }

                if (!@rename($tmpPath, $path)) {
                    @unlink($tmpPath);
                    throw new \RuntimeException('Atomic rename failed for "' . $docKey . '"');
                }

                clearstatcache(true, $path);
                if (function_exists('opcache_invalidate')) {
                    @opcache_invalidate($path, true);
                }

                // Re-read and validate
                $stored = file_get_contents($path);
                if ($stored === false) {
                    throw new \RuntimeException('Could not re-read written document "' . $docKey . '"');
                }

                $postHash = hash('sha256', $stored);
                $postApplyHashes[$docKey] = $postHash;
                $appliedFiles[] = $path;
                $restoreMap[$path] = $before[$docKey];

                // Save post-apply copy
                file_put_contents($afterDir . '/' . EngineeringWorkspaceContentContract::canonicalFilename($docKey), $stored);
                file_put_contents($afterDir . '/' . EngineeringWorkspaceContentContract::canonicalFilename($docKey) . '.fingerprint', $postHash);

                $operationsCompleted[] = [
                    'document_key' => $docKey,
                    'operation' => $op['operation'],
                    'before_hash' => $before[$docKey]['hash'],
                    'after_hash' => $postHash,
                    'state' => 'completed',
                ];
            }

            // Validate all affected documents post-apply
            foreach ($plan['operations'] as $op) {
                $path = self::resolveCanonicalPath($workspaceKey, $op['document_key']);
                if ($path === null || !is_file($path)) {
                    throw new \RuntimeException('Document "' . $op['document_key'] . '" is missing after apply');
                }
                $content = file_get_contents($path);
                if ($content === false) {
                    throw new \RuntimeException('Could not read "' . $op['document_key'] . '" after apply');
                }
                $validation = EngineeringWorkspaceContentContract::validateDocumentContent($op['document_key'], $content);
                if (!$validation['ok']) {
                    throw new \RuntimeException(
                        'Post-apply validation failed for "' . $op['document_key'] . '": ' . $validation['error']
                    );
                }
            }

            // Write manifest
            $manifest = [
                'transaction_id' => $transactionId,
                'workspace_key' => $workspaceKey,
                'timestamp' => time(),
                'datetime' => date('c'),
                'actor' => $actor['login'] ?? 'unknown',
                'before' => $before,
                'after' => $postApplyHashes,
                'operations' => $operationsCompleted,
                'template_fingerprints' => $plan['template_fingerprints'],
                'mapping_readiness_fingerprint' => $plan['mapping_readiness_fingerprint'] ?? '',
            ];

            file_put_contents($archiveDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return [
                'ok' => true,
                'transaction_id' => $transactionId,
                'workspace_key' => $workspaceKey,
                'timestamp' => $manifest['timestamp'],
                'datetime' => $manifest['datetime'],
                'operations' => $operationsCompleted,
                'archive_path' => self::relativePath($archiveDir),
                'rollback_possible' => true,
                'error' => '',
            ];
        } catch (\RuntimeException $e) {
            // Rollback on failure — restore already-changed files
            foreach ($appliedFiles as $appliedPath) {
                $relPath = self::relativePath($appliedPath);
                if (isset($restoreMap[$appliedPath])) {
                    $beforeData = $restoreMap[$appliedPath];
                    if ($beforeData['existed'] && $beforeData['hash'] !== null) {
                        $beforeContentPath = $beforeDir . '/' . basename($appliedPath);
                        if (is_file($beforeContentPath)) {
                            $originalContent = file_get_contents($beforeContentPath);
                            if ($originalContent !== false) {
                                file_put_contents($appliedPath, $originalContent);
                                clearstatcache(true, $appliedPath);
                                if (function_exists('opcache_invalidate')) {
                                    @opcache_invalidate($appliedPath, true);
                                }
                            }
                        }
                    } else {
                        // Was created, so delete
                        @unlink($appliedPath);
                        clearstatcache(true, $appliedPath);
                    }
                }
            }

            // Remove empty created directories
            foreach ($plan['operations'] as $op) {
                $path = self::resolveCanonicalPath($workspaceKey, $op['document_key']);
                if ($path !== null) {
                    $dir = dirname($path);
                    if (is_dir($dir) && count(scandir($dir)) <= 2) {
                        @rmdir($dir);
                    }
                }
            }

            // Write failure manifest
            $failureManifest = [
                'transaction_id' => $transactionId,
                'workspace_key' => $workspaceKey,
                'timestamp' => time(),
                'datetime' => date('c'),
                'actor' => $actor['login'] ?? 'unknown',
                'error' => $e->getMessage(),
                'rollback_completed' => true,
            ];
            if (!is_dir($archiveDir)) {
                @mkdir($archiveDir, 0755, true);
            }
            file_put_contents($archiveDir . '/failure.json', json_encode($failureManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return [
                'ok' => false,
                'transaction_id' => $transactionId,
                'error' => 'Apply failed and rolled back: ' . $e->getMessage(),
                'rollback_completed' => true,
            ];
        }
    }

    // ─────────────────────────────────────────────────────
    //  6. Rollback — restore last successful transaction
    // ─────────────────────────────────────────────────────

    /**
     * @return array<string,mixed>
     */
    public static function rollbackTransaction(string $workspaceKey, ?array $actor): array
    {
        $transaction = self::readLastTransaction($workspaceKey);
        if ($transaction === null) {
            return ['ok' => false, 'error' => 'No transaction found for this workspace'];
        }

        $archiveDir = self::SNAPSHOT_ROOT . '/' . $workspaceKey . '/' . $transaction['transaction_id'];
        $manifestPath = $archiveDir . '/manifest.json';

        if (!is_file($manifestPath)) {
            return ['ok' => false, 'error' => 'Transaction manifest not found'];
        }

        $manifest = json_decode(file_get_contents($manifestPath) ?: '{}', true);
        if (!is_array($manifest)) {
            return ['ok' => false, 'error' => 'Invalid transaction manifest'];
        }

        $operations = $manifest['operations'] ?? [];
        if (!is_array($operations) || $operations === []) {
            return ['ok' => false, 'error' => 'Transaction has no operations'];
        }

        $beforeDir = $archiveDir . '/before';
        $afterDir = $archiveDir . '/after';

        // Verify post-apply hashes still match
        foreach ($operations as $op) {
            $docKey = $op['document_key'] ?? '';
            $path = self::resolveCanonicalPath($workspaceKey, $docKey);
            if ($path === null) {
                return [
                    'ok' => false,
                    'error' => 'Cannot resolve path for "' . $docKey . '"',
                ];
            }

            $expectedHash = $op['after_hash'] ?? null;
            if ($expectedHash === null) {
                // Document was created — verify it still exists and matches
                if (!is_file($path)) {
                    return [
                        'ok' => false,
                        'error' => 'Document "' . $docKey . '" no longer exists. Rollback rejected.',
                    ];
                }
                $currentContent = file_get_contents($path);
                if ($currentContent === false) {
                    return [
                        'ok' => false,
                        'error' => 'Could not read "' . $docKey . '" for rollback verification',
                    ];
                }
                if (hash('sha256', $currentContent) !== $expectedHash) {
                    return [
                        'ok' => false,
                        'error' => 'Document "' . $docKey . '" has changed since apply. Rollback rejected to protect newer content.',
                    ];
                }
            } elseif ($op['operation'] === 'ARCHIVE_AND_RESET') {
                // Document was replaced — verify current content matches post-apply hash
                if (!is_file($path)) {
                    return [
                        'ok' => false,
                        'error' => 'Document "' . $docKey . '" has been deleted since apply. Rollback rejected.',
                    ];
                }
                $currentContent = file_get_contents($path);
                if ($currentContent === false) {
                    return [
                        'ok' => false,
                        'error' => 'Could not read "' . $docKey . '" for rollback verification',
                    ];
                }
                $currentHash = hash('sha256', $currentContent);
                if ($currentHash !== $expectedHash) {
                    return [
                        'ok' => false,
                        'error' => 'Document "' . $docKey . '" has changed since apply (hash mismatch). Rollback rejected to protect newer content.',
                        'current_hash' => $currentHash,
                        'expected_hash' => $expectedHash,
                    ];
                }
            }
        }

        // ── Execute rollback ──
        $rollbackOps = [];
        $removedDirs = [];

        foreach ($operations as $op) {
            $docKey = $op['document_key'] ?? '';
            $path = self::resolveCanonicalPath($workspaceKey, $docKey);
            if ($path === null) {
                continue;
            }

            $beforeHash = $op['before_hash'] ?? null;

            if ($op['operation'] === 'ARCHIVE_AND_RESET' && $beforeHash !== null) {
                // Restore from before archive
                $beforeFilePath = $beforeDir . '/' . EngineeringWorkspaceContentContract::canonicalFilename($docKey);
                if (!is_file($beforeFilePath)) {
                    return [
                        'ok' => false,
                        'error' => 'Archive content missing for "' . $docKey . '"',
                    ];
                }

                $originalContent = file_get_contents($beforeFilePath);
                if ($originalContent === false) {
                    return [
                        'ok' => false,
                        'error' => 'Could not read archived content for "' . $docKey . '"',
                    ];
                }

                // Atomic restore
                $tmpPath = $path . '.tmp.' . bin2hex(random_bytes(8));
                $written = @file_put_contents($tmpPath, $originalContent, LOCK_EX);
                if ($written === false) {
                    @unlink($tmpPath);
                    return ['ok' => false, 'error' => 'Rollback write failed for "' . $docKey . '"'];
                }
                if (!@rename($tmpPath, $path)) {
                    @unlink($tmpPath);
                    return ['ok' => false, 'error' => 'Rollback rename failed for "' . $docKey . '"'];
                }

                clearstatcache(true, $path);
                if (function_exists('opcache_invalidate')) {
                    @opcache_invalidate($path, true);
                }

                $rollbackOps[] = [
                    'document_key' => $docKey,
                    'operation' => 'restored',
                    'restored_hash' => $beforeHash,
                ];

            } elseif (in_array($op['operation'] ?? '', ['CREATE_WORKSPACE_DOCUMENT_FROM_TEMPLATE'], true)) {
                // Delete the created document
                if (is_file($path) && @unlink($path)) {
                    clearstatcache(true, $path);
                    $rollbackOps[] = [
                        'document_key' => $docKey,
                        'operation' => 'deleted',
                    ];

                    // Track directory for potential cleanup
                    $dir = dirname($path);
                    $dirKey = $dir;
                    if (!isset($removedDirs[$dirKey])) {
                        $removedDirs[$dirKey] = 0;
                    }
                    $removedDirs[$dirKey]++;
                }
            }
        }

        // Remove empty created directories
        foreach ($removedDirs as $dir => $count) {
            if (is_dir($dir) && count(scandir($dir)) <= 2) {
                @rmdir($dir);
            }
        }

        return [
            'ok' => true,
            'workspace_key' => $workspaceKey,
            'transaction_id' => $transaction['transaction_id'],
            'operations' => $rollbackOps,
            'error' => '',
        ];
    }

    // ─────────────────────────────────────────────────────
    //  7. Build deploy model (simplified, broader than provision)
    // ─────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $coverage
     * @return array<string,mixed>
     */
    public static function buildDeployModel(array $coverage): array
    {
        $rows = isset($coverage['rows']) && is_array($coverage['rows']) ? $coverage['rows'] : [];
        $workspaces = [];

        foreach ($rows as $row) {
            $workspaceKey = (string)($row['workspace_key'] ?? '');
            if ($workspaceKey === '') {
                continue;
            }

            $documents = self::resolveDocumentStates($workspaceKey, $row);
            $hasMissing = false;
            $hasValid = false;
            foreach ($documents as $doc) {
                if ($doc['state'] === 'missing') {
                    $hasMissing = true;
                }
                if ($doc['state'] === 'valid') {
                    $hasValid = true;
                }
            }

            // Also scan for any engineering/ directories not in coverage
            $workspaces[] = [
                'context_label' => $row['context_label'] ?? $workspaceKey,
                'workspace_key' => $workspaceKey,
                'evidence_type' => $row['evidence_type'] ?? 'registered_workspace',
                'state' => (string)($row['state'] ?? ''),
                'documents' => $documents,
                'has_missing' => $hasMissing,
                'has_valid' => $hasValid,
            ];
        }

        // Discover additional workspace directories under engineering/ that are
        // not in coverage rows but are safe to deploy to (not reserved template dirs)
        $engineeringRoot = APP_ROOT . '/engineering';
        if (is_dir($engineeringRoot)) {
            $existingKeys = array_map(static fn(array $w): string => $w['workspace_key'], $workspaces);
            $iterator = new \RecursiveDirectoryIterator($engineeringRoot, \RecursiveDirectoryIterator::SKIP_DOTS);
            $dirs = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::SELF_FIRST);

            /** @var \SplFileInfo $fileInfo */
            foreach ($dirs as $fileInfo) {
                if (!$fileInfo->isDir()) {
                    continue;
                }
                $realPath = $fileInfo->getRealPath();
                if ($realPath === false) {
                    continue;
                }
                $relPath = substr($realPath, strlen($engineeringRoot) + 1);

                // Skip reserved template directories (_templates, _template)
                if (EngineeringWorkspaceContentContract::isReservedTemplateWorkspaceKey($relPath)) {
                    continue;
                }

                // Skip first level dirs that have no workspace key pattern
                if ($relPath === '' || in_array($relPath, $existingKeys, true)) {
                    continue;
                }

                // Check if any markdown document exists
                $hasAnyDoc = false;
                $docScan = scandir($realPath);
                if (is_array($docScan)) {
                    foreach ($docScan as $entry) {
                        if (str_ends_with($entry, '.md')) {
                            $hasAnyDoc = true;
                            break;
                        }
                    }
                }

                $workspaceDocuments = [];
                foreach (self::ALLOWED_DOCUMENTS as $docKey) {
                    $filename = EngineeringWorkspaceContentContract::canonicalFilename($docKey);
                    $docPath = $realPath . '/' . $filename;
                    $state = is_file($docPath) ? 'valid' : 'missing';
                    $content = is_file($docPath) ? file_get_contents($docPath) : false;
                    $workspaceDocuments[$docKey] = [
                        'state' => $state,
                        'content_hash' => $content !== false ? hash('sha256', $content) : null,
                        'provisioning_readiness' => $state === 'valid' ? 'preserve' : 'initialize_from_template',
                    ];
                }

                $hasMissing = false;
                $hasValid = false;
                foreach ($workspaceDocuments as $doc) {
                    if ($doc['state'] === 'missing') {
                        $hasMissing = true;
                    }
                    if ($doc['state'] === 'valid') {
                        $hasValid = true;
                    }
                }

                $workspaces[] = [
                    'context_label' => $relPath,
                    'workspace_key' => $relPath,
                    'evidence_type' => $hasAnyDoc ? 'discovered_workspace_directory' : 'empty_workspace_directory',
                    'state' => $hasAnyDoc ? 'discovered' : 'empty',
                    'documents' => $workspaceDocuments,
                    'has_missing' => $hasMissing,
                    'has_valid' => $hasValid,
                ];
            }
        }

        usort($workspaces, static fn(array $a, array $b): int => strcmp($a['workspace_key'], $b['workspace_key']));

        return [
            'workspaces' => $workspaces,
            'workspace_count' => count($workspaces),
        ];
    }

    // ─────────────────────────────────────────────────────
    //  8. Simplified Deploy — Create Missing Documents
    // ─────────────────────────────────────────────────────

    /**
     * Create missing documents for one workspace via template rendering.
     * All-or-nothing per workspace: if any write fails, all are rolled back.
     *
     * @param string[] $documentKeys
     * @param array<string,mixed>|null $actor
     * @return array<string,mixed>
     */
    public static function createMissingDocuments(
        string $workspaceKey,
        array $documentKeys,
        ?array $actor
    ): array {
        $validated = self::validateDeployWorkspaceKey($workspaceKey);
        if (!$validated['ok']) {
            return ['ok' => false, 'error' => $validated['error']];
        }

        if ($documentKeys === []) {
            return ['ok' => false, 'error' => 'No documents selected'];
        }

        $invalidKeys = array_diff($documentKeys, self::ALLOWED_DOCUMENTS);
        if ($invalidKeys !== []) {
            return ['ok' => false, 'error' => 'Invalid document keys: ' . implode(', ', $invalidKeys)];
        }

        // Verify all are actually missing
        $toCreate = [];
        foreach ($documentKeys as $docKey) {
            $path = self::resolveCanonicalPath($workspaceKey, $docKey);
            if ($path === null) {
                return ['ok' => false, 'error' => 'Cannot resolve path for "' . $docKey . '"'];
            }
            if (is_file($path)) {
                return ['ok' => false, 'error' => 'Document "' . $docKey . '" already exists. Use Replace Selected Files to overwrite.'];
            }
            $toCreate[$docKey] = $path;
        }

        // Render and validate all content first
        $rendered = [];
        foreach ($documentKeys as $docKey) {
            $content = self::renderTemplateContent($docKey, $workspaceKey);
            $validation = EngineeringWorkspaceContentContract::validateDocumentContent($docKey, $content);
            if (!$validation['ok']) {
                return ['ok' => false, 'error' => 'Generated content for "' . $docKey . '" failed validation: ' . $validation['error']];
            }
            $rendered[$docKey] = $content;
        }

        // ── Execute ──
        $transactionId = 'dep-' . date('Ymd_His') . '-' . bin2hex(random_bytes(8));
        $archiveDir = self::SNAPSHOT_ROOT . '/' . $workspaceKey . '/' . $transactionId;
        $appliedFiles = [];
        $restoreMap = [];

        try {
            foreach ($toCreate as $docKey => $path) {
                $workspaceDir = dirname($path);

                if (!is_dir($workspaceDir) && !@mkdir($workspaceDir, 0755, true)) {
                    throw new \RuntimeException('Could not create workspace directory for "' . $workspaceKey . '"');
                }

                // Atomic write
                $tmpPath = $path . '.tmp.' . bin2hex(random_bytes(8));
                $written = @file_put_contents($tmpPath, $rendered[$docKey], LOCK_EX);
                if ($written === false) {
                    @unlink($tmpPath);
                    throw new \RuntimeException('Write failed for "' . $docKey . '"');
                }

                if (!@rename($tmpPath, $path)) {
                    @unlink($tmpPath);
                    throw new \RuntimeException('Atomic rename failed for "' . $docKey . '"');
                }

                clearstatcache(true, $path);
                if (function_exists('opcache_invalidate')) {
                    @opcache_invalidate($path, true);
                }

                $appliedFiles[] = $path;
                $restoreMap[$path] = ['existed' => false, 'hash' => null];

                // Post-apply verification
                $stored = file_get_contents($path);
                if ($stored === false) {
                    throw new \RuntimeException('Could not re-read "' . $docKey . '" after write');
                }
                $postHash = hash('sha256', $stored);

                // Save to archive
                $afterDir = $archiveDir . '/after';
                if (!is_dir($afterDir)) {
                    @mkdir($afterDir, 0755, true);
                }
                $filename = EngineeringWorkspaceContentContract::canonicalFilename($docKey);
                file_put_contents($afterDir . '/' . $filename, $stored);
                file_put_contents($afterDir . '/' . $filename . '.hash', $postHash);
            }

            // Write manifest
            if (!is_dir($archiveDir)) {
                @mkdir($archiveDir, 0755, true);
            }
            $manifest = [
                'transaction_id' => $transactionId,
                'workspace_key' => $workspaceKey,
                'action' => 'create_missing',
                'timestamp' => time(),
                'datetime' => date('c'),
                'actor' => $actor['login'] ?? 'unknown',
                'documents' => $documentKeys,
            ];
            file_put_contents($archiveDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return [
                'ok' => true,
                'transaction_id' => $transactionId,
                'workspace_key' => $workspaceKey,
                'action' => 'create_missing',
                'documents' => $documentKeys,
                'count' => count($documentKeys),
                'archive_path' => self::relativePath($archiveDir),
                'error' => '',
            ];

        } catch (\RuntimeException $e) {
            // Rollback created files
            foreach ($appliedFiles as $appliedPath) {
                @unlink($appliedPath);
                clearstatcache(true, $appliedPath);
                $dir = dirname($appliedPath);
                if (is_dir($dir) && count(scandir($dir)) <= 2) {
                    @rmdir($dir);
                }
            }

            return [
                'ok' => false,
                'error' => 'Create failed and rolled back: ' . $e->getMessage(),
                'rollback_completed' => true,
            ];
        }
    }

    // ─────────────────────────────────────────────────────
    //  8. Simplified Deploy — Replace Selected Documents
    // ─────────────────────────────────────────────────────

    /**
     * Replace existing documents with fresh template content.
     * Archives originals before replacement. Single-workspace only.
     *
     * @param string[] $documentKeys
     * @param array<string,mixed>|null $actor
     * @return array<string,mixed>
     */
    public static function replaceDocuments(
        string $workspaceKey,
        array $documentKeys,
        ?array $actor
    ): array {
        $validated = self::validateDeployWorkspaceKey($workspaceKey);
        if (!$validated['ok']) {
            return ['ok' => false, 'error' => $validated['error']];
        }

        if ($documentKeys === []) {
            return ['ok' => false, 'error' => 'No documents selected'];
        }

        $invalidKeys = array_diff($documentKeys, self::ALLOWED_DOCUMENTS);
        if ($invalidKeys !== []) {
            return ['ok' => false, 'error' => 'Invalid document keys: ' . implode(', ', $invalidKeys)];
        }

        // Verify all exist and resolve paths
        $existing = [];
        foreach ($documentKeys as $docKey) {
            $path = self::resolveCanonicalPath($workspaceKey, $docKey);
            if ($path === null) {
                return ['ok' => false, 'error' => 'Cannot resolve path for "' . $docKey . '"'];
            }
            if (!is_file($path)) {
                return ['ok' => false, 'error' => 'Document "' . $docKey . '" does not exist. Use Create Missing Files to initialize.'];
            }
            $existing[$docKey] = $path;
        }

        // Render and validate all content first
        $rendered = [];
        foreach ($documentKeys as $docKey) {
            $content = self::renderTemplateContent($docKey, $workspaceKey);
            $validation = EngineeringWorkspaceContentContract::validateDocumentContent($docKey, $content);
            if (!$validation['ok']) {
                return ['ok' => false, 'error' => 'Generated content for "' . $docKey . '" failed validation: ' . $validation['error']];
            }
            $rendered[$docKey] = $content;
        }

        // ── Execute ──
        $transactionId = 'rep-' . date('Ymd_His') . '-' . bin2hex(random_bytes(8));
        $archiveDir = self::SNAPSHOT_ROOT . '/' . $workspaceKey . '/' . $transactionId;
        $beforeDir = $archiveDir . '/before';
        $afterDir = $archiveDir . '/after';

        if (!is_dir($beforeDir) && !@mkdir($beforeDir, 0755, true)) {
            return ['ok' => false, 'error' => 'Could not create archive directory'];
        }

        $appliedFiles = [];
        $restoreMap = [];
        $operationsCompleted = [];

        try {
            foreach ($existing as $docKey => $path) {
                $filename = EngineeringWorkspaceContentContract::canonicalFilename($docKey);

                // Archive original
                $originalContent = file_get_contents($path);
                if ($originalContent !== false) {
                    file_put_contents($beforeDir . '/' . $filename, $originalContent);
                }
                $beforeHash = $originalContent !== false ? hash('sha256', $originalContent) : null;

                // Atomic replace
                $tmpPath = $path . '.tmp.' . bin2hex(random_bytes(8));
                $written = @file_put_contents($tmpPath, $rendered[$docKey], LOCK_EX);
                if ($written === false) {
                    @unlink($tmpPath);
                    throw new \RuntimeException('Write failed for "' . $docKey . '"');
                }

                if (!@rename($tmpPath, $path)) {
                    @unlink($tmpPath);
                    throw new \RuntimeException('Atomic rename failed for "' . $docKey . '"');
                }

                clearstatcache(true, $path);
                if (function_exists('opcache_invalidate')) {
                    @opcache_invalidate($path, true);
                }

                $appliedFiles[] = $path;
                $restoreMap[$path] = ['existed' => true, 'hash' => $beforeHash, 'before_dir' => $beforeDir, 'filename' => $filename];

                // Post-apply
                $stored = file_get_contents($path);
                $postHash = $stored !== false ? hash('sha256', $stored) : 'unknown';

                if (!is_dir($afterDir)) {
                    @mkdir($afterDir, 0755, true);
                }
                file_put_contents($afterDir . '/' . $filename, $stored ?? '');
                file_put_contents($afterDir . '/' . $filename . '.hash', $postHash);

                $operationsCompleted[] = [
                    'document_key' => $docKey,
                    'operation' => 'REPLACE_WITH_TEMPLATE',
                    'before_hash' => $beforeHash,
                    'after_hash' => $postHash,
                    'state' => 'completed',
                ];
            }

            // Write manifest
            $manifest = [
                'transaction_id' => $transactionId,
                'workspace_key' => $workspaceKey,
                'action' => 'replace_selected',
                'timestamp' => time(),
                'datetime' => date('c'),
                'actor' => $actor['login'] ?? 'unknown',
                'documents' => $documentKeys,
                'operations' => $operationsCompleted,
            ];
            file_put_contents($archiveDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return [
                'ok' => true,
                'transaction_id' => $transactionId,
                'workspace_key' => $workspaceKey,
                'action' => 'replace_selected',
                'operations' => $operationsCompleted,
                'count' => count($documentKeys),
                'archive_path' => self::relativePath($archiveDir),
                'rollback_possible' => true,
                'error' => '',
            ];

        } catch (\RuntimeException $e) {
            // Rollback replaced files
            foreach ($appliedFiles as $appliedPath) {
                $relPath = self::relativePath($appliedPath);
                if (isset($restoreMap[$appliedPath])) {
                    $rm = $restoreMap[$appliedPath];
                    $beforeFilePath = $rm['before_dir'] . '/' . $rm['filename'];
                    if ($rm['existed'] && $rm['hash'] !== null && is_file($beforeFilePath)) {
                        $orig = file_get_contents($beforeFilePath);
                        if ($orig !== false) {
                            file_put_contents($appliedPath, $orig);
                            clearstatcache(true, $appliedPath);
                            if (function_exists('opcache_invalidate')) {
                                @opcache_invalidate($appliedPath, true);
                            }
                        }
                    }
                }
            }

            return [
                'ok' => false,
                'error' => 'Replace failed and rolled back: ' . $e->getMessage(),
                'rollback_completed' => true,
            ];
        }
    }

    // ─────────────────────────────────────────────────────
    //  9. Simplified Deploy — Restore Last Backup
    // ─────────────────────────────────────────────────────

    /**
     * Restore a workspace's last snapshot.
     * Hash-protected: rejects if current content differs from post-apply state.
     *
     * @param array<string,mixed>|null $actor
     * @return array<string,mixed>
     */
    public static function restoreLastBackup(string $workspaceKey, ?array $actor): array
    {
        $validated = self::validateDeployWorkspaceKey($workspaceKey);
        if (!$validated['ok']) {
            return ['ok' => false, 'error' => $validated['error']];
        }

        $transaction = self::readLastTransaction($workspaceKey);
        if ($transaction === null) {
            return ['ok' => false, 'error' => 'No backup found for this workspace'];
        }

        $txId = $transaction['transaction_id'];
        $archiveDir = self::SNAPSHOT_ROOT . '/' . $workspaceKey . '/' . $txId;
        $beforeDir = $archiveDir . '/before';
        $afterDir = $archiveDir . '/after';
        $manifestPath = $archiveDir . '/manifest.json';

        if (!is_file($manifestPath)) {
            return ['ok' => false, 'error' => 'Backup manifest not found'];
        }

        $manifest = json_decode(file_get_contents($manifestPath) ?: '{}', true);
        if (!is_array($manifest)) {
            return ['ok' => false, 'error' => 'Invalid backup manifest'];
        }

        // Determine which documents to restore
        $documents = $manifest['documents'] ?? [];
        if (!is_array($documents) || $documents === []) {
            // Try operations as fallback for legacy format
            $ops = $manifest['operations'] ?? [];
            if (is_array($ops) && $ops !== []) {
                $documents = array_map(static fn(array $op): string => $op['document_key'] ?? '', $ops);
            }
        }

        if (!is_array($documents) || $documents === []) {
            return ['ok' => false, 'error' => 'No documents in backup'];
        }

        // Verify post-apply hashes still match (hash protection)
        foreach ($documents as $docKey) {
            $path = self::resolveCanonicalPath($workspaceKey, $docKey);
            if ($path === null) {
                return ['ok' => false, 'error' => 'Cannot resolve path for "' . $docKey . '"'];
            }

            $hashFilename = EngineeringWorkspaceContentContract::canonicalFilename($docKey) . '.hash';
            $hashPath = $afterDir . '/' . $hashFilename;

            if (is_file($hashPath)) {
                $expectedHash = trim(file_get_contents($hashPath) ?: '');
                if ($expectedHash !== '' && is_file($path)) {
                    $currentContent = file_get_contents($path);
                    if ($currentContent !== false) {
                        $currentHash = hash('sha256', $currentContent);
                        if ($currentHash !== $expectedHash) {
                            return [
                                'ok' => false,
                                'error' => 'Document "' . $docKey . '" has changed since backup. Restore rejected to protect newer content.',
                            ];
                        }
                    }
                }
            }
        }

        // Execute restore
        $restored = [];

        foreach ($documents as $docKey) {
            $path = self::resolveCanonicalPath($workspaceKey, $docKey);
            if ($path === null) {
                continue;
            }

            $filename = EngineeringWorkspaceContentContract::canonicalFilename($docKey);
            $beforeFilePath = $beforeDir . '/' . $filename;

            if (!is_file($beforeFilePath)) {
                // Maybe was a create (no before content) — skip or delete
                if (is_file($path) && $manifest['action'] ?? '' === 'create_missing') {
                    // This was a creation, not a replacement; skip restore for this doc
                    continue;
                }
                continue;
            }

            $originalContent = file_get_contents($beforeFilePath);
            if ($originalContent === false) {
                continue;
            }

            $tmpPath = $path . '.tmp.' . bin2hex(random_bytes(8));
            $written = @file_put_contents($tmpPath, $originalContent, LOCK_EX);
            if ($written === false) {
                @unlink($tmpPath);
                return ['ok' => false, 'error' => 'Restore write failed for "' . $docKey . '"'];
            }

            if (!@rename($tmpPath, $path)) {
                @unlink($tmpPath);
                return ['ok' => false, 'error' => 'Restore rename failed for "' . $docKey . '"'];
            }

            clearstatcache(true, $path);
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($path, true);
            }

            $restored[] = [
                'document_key' => $docKey,
                'operation' => 'restored',
                'source' => 'transaction-' . $txId,
            ];
        }

        if ($restored === []) {
            return ['ok' => false, 'error' => 'No documents could be restored from backup'];
        }

        return [
            'ok' => true,
            'workspace_key' => $workspaceKey,
            'transaction_id' => $txId,
            'restored' => $restored,
            'count' => count($restored),
            'error' => '',
        ];
    }

    // ─────────────────────────────────────────────────────
    // 10. Simplified Deploy — Create New Workspace
    // ─────────────────────────────────────────────────────

    /**
     * Create a new workspace directory with all 4 starter documents.
     *
     * @param array<string,mixed>|null $actor
     * @return array<string,mixed>
     */
    public static function newWorkspace(string $workspaceKey, ?array $actor): array
    {
        $validated = self::validateDeployWorkspaceKey($workspaceKey);
        if (!$validated['ok']) {
            return ['ok' => false, 'error' => $validated['error']];
        }

        $workspaceDir = self::resolveWorkspaceDir($workspaceKey);

        // Check not a reserved template directory
        if (EngineeringWorkspaceContentContract::isReservedTemplateWorkspaceKey($workspaceKey)) {
            return ['ok' => false, 'error' => 'Reserved template workspace key is not a valid workspace'];
        }

        // Verify directory doesn't already exist (or exists with content)
        if (is_dir($workspaceDir)) {
            $existing = array_diff(scandir($workspaceDir) ?: [], ['.', '..']);
            if ($existing !== []) {
                return ['ok' => false, 'error' => 'Workspace already exists with content'];
            }
        }

        $root = realpath(APP_ROOT . '/engineering');
        if ($root === false) {
            return ['ok' => false, 'error' => 'Engineering root directory not found'];
        }

        // Create directory
        if (!is_dir($workspaceDir) && !@mkdir($workspaceDir, 0755, true)) {
            return ['ok' => false, 'error' => 'Could not create workspace directory'];
        }

        $realDir = realpath($workspaceDir);
        if ($realDir === false) {
            return ['ok' => false, 'error' => 'Could not resolve workspace directory'];
        }

        $normalizedDir = rtrim(str_replace('\\', '/', $realDir), '/');
        $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/');
        if (!str_starts_with($normalizedDir, $normalizedRoot . '/')) {
            @rmdir($workspaceDir);
            return ['ok' => false, 'error' => 'Workspace directory escapes engineering root'];
        }

        // Create all 4 starter documents
        $created = [];
        foreach (self::ALLOWED_DOCUMENTS as $docKey) {
            $path = $workspaceDir . '/' . EngineeringWorkspaceContentContract::canonicalFilename($docKey);
            $content = self::renderTemplateContent($docKey, $workspaceKey);

            $validation = EngineeringWorkspaceContentContract::validateDocumentContent($docKey, $content);
            if (!$validation['ok']) {
                // Clean up already-created files
                foreach ($created as $createdPath) {
                    @unlink($createdPath);
                }
                @rmdir($workspaceDir);
                return ['ok' => false, 'error' => 'Generated content for "' . $docKey . '" failed validation: ' . $validation['error']];
            }

            $written = @file_put_contents($path, $content, LOCK_EX);
            if ($written === false) {
                // Clean up
                foreach ($created as $createdPath) {
                    @unlink($createdPath);
                }
                @rmdir($workspaceDir);
                return ['ok' => false, 'error' => 'Could not write "' . $docKey . '"'];
            }

            $created[] = $path;
        }

        // Log creation as a transaction
        $transactionId = 'new-' . date('Ymd_His') . '-' . bin2hex(random_bytes(8));
        $archiveDir = self::SNAPSHOT_ROOT . '/' . $workspaceKey . '/' . $transactionId;
        if (!is_dir($archiveDir) && !@mkdir($archiveDir, 0755, true)) {
            // Non-fatal — workspace still created
        } else {
            $afterDir = $archiveDir . '/after';
            @mkdir($afterDir, 0755, true);
            foreach (self::ALLOWED_DOCUMENTS as $docKey) {
                $path = $workspaceDir . '/' . EngineeringWorkspaceContentContract::canonicalFilename($docKey);
                $content = is_file($path) ? file_get_contents($path) : '';
                if ($content !== false) {
                    $filename = EngineeringWorkspaceContentContract::canonicalFilename($docKey);
                    file_put_contents($afterDir . '/' . $filename, $content);
                    file_put_contents($afterDir . '/' . $filename . '.hash', hash('sha256', $content));
                }
            }
            $manifest = [
                'transaction_id' => $transactionId,
                'workspace_key' => $workspaceKey,
                'action' => 'new_workspace',
                'timestamp' => time(),
                'datetime' => date('c'),
                'actor' => $actor['login'] ?? 'unknown',
                'documents' => self::ALLOWED_DOCUMENTS,
            ];
            file_put_contents($archiveDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return [
            'ok' => true,
            'workspace_key' => $workspaceKey,
            'transaction_id' => $transactionId,
            'documents' => self::ALLOWED_DOCUMENTS,
            'count' => count(self::ALLOWED_DOCUMENTS),
            'path' => self::relativePath($workspaceDir),
            'error' => '',
        ];
    }

    // ─────────────────────────────────────────────────────
    // 11. Deploy Workspace Key Validation
    // ─────────────────────────────────────────────────────

    /**
     * Validate a workspace key for the simplified deploy flow.
     * Checks: non-empty, no traversal, no absolute path, not reserved template directories,
     * resolves inside engineering/ root.
     *
     * @return array{ok:bool, error:string, normalized:string}
     */
    public static function validateDeployWorkspaceKey(string $key): array
    {
        // Check absolute path before trimming
        if (str_starts_with($key, '/')) {
            return ['ok' => false, 'error' => 'Workspace key must not be an absolute path', 'normalized' => ''];
        }

        $trimmed = trim($key, "/ \t\n\r\0\x0B");

        if ($trimmed === '') {
            return ['ok' => false, 'error' => 'Workspace key is required', 'normalized' => ''];
        }

        // Reject path traversal
        if (str_contains($trimmed, '..')) {
            return ['ok' => false, 'error' => 'Workspace key must not contain path traversal (..)', 'normalized' => ''];
        }

        // Reject reserved template directories (_templates, _template)
        if (EngineeringWorkspaceContentContract::isReservedTemplateWorkspaceKey($trimmed)) {
            return ['ok' => false, 'error' => 'Reserved template workspace key is not a valid workspace', 'normalized' => ''];
        }

        // Verify directory resolves inside engineering root
        $root = realpath(APP_ROOT . '/engineering');
        if ($root === false) {
            return ['ok' => false, 'error' => 'Engineering root directory not found', 'normalized' => $trimmed];
        }

        $dir = APP_ROOT . '/engineering/' . $trimmed;
        $realDir = realpath($dir);

        if ($realDir !== false) {
            // Path exists — verify inside root
            $normalizedDir = rtrim(str_replace('\\', '/', $realDir), '/');
            $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/');
            if ($normalizedDir !== $normalizedRoot && !str_starts_with($normalizedDir, $normalizedRoot . '/')) {
                return ['ok' => false, 'error' => 'Workspace key resolves outside engineering root', 'normalized' => $trimmed];
            }
        } else {
            // Path doesn't exist — verify parent is inside root
            $parent = realpath(dirname($dir));
            if ($parent === false) {
                $parent = realpath($root);
            }
            if ($parent === false) {
                return ['ok' => false, 'error' => 'Could not resolve parent directory', 'normalized' => $trimmed];
            }
            $normalizedParent = rtrim(str_replace('\\', '/', $parent), '/');
            $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/');
            if ($normalizedParent !== $normalizedRoot && !str_starts_with($normalizedParent, $normalizedRoot . '/')) {
                return ['ok' => false, 'error' => 'Parent directory escapes engineering root', 'normalized' => $trimmed];
            }
        }

        return ['ok' => true, 'error' => '', 'normalized' => $trimmed];
    }

    // ─────────────────────────────────────────────────────
    //  7. Read last transaction
    // ─────────────────────────────────────────────────────

    /**
     * @return array<string,mixed>|null
     */
    public static function getLastTransaction(string $workspaceKey): ?array
    {
        return self::readLastTransaction($workspaceKey);
    }

    // ─────────────────────────────────────────────────────
    //  8. Eligibility check
    // ─────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $row
     */
    public static function isEligibleForProvisioning(array $row): bool
    {
        $state = (string)($row['state'] ?? '');
        return in_array($state, self::ELIGIBLE_STATES, true);
    }

    /**
     * @return string[]
     */
    public static function eligibleStateKeys(): array
    {
        return self::ELIGIBLE_STATES;
    }

    // ─────────────────────────────────────────────────────
    //  Internal helpers
    // ─────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $row
     * @return array<string, array{state:string, content_hash:string|null}>
     */
    private static function resolveDocumentStates(string $workspaceKey, array $row): array
    {
        $documents = isset($row['documents']) && is_array($row['documents']) ? $row['documents'] : [];
        $result = [];

        foreach (self::ALLOWED_DOCUMENTS as $docKey) {
            $docInfo = $documents[$docKey] ?? null;
            $state = 'missing';
            $hash = null;

            if (is_array($docInfo)) {
                $state = (string)($docInfo['state'] ?? 'missing');
            }

            // Compute actual hash from file system
            $path = self::resolveCanonicalPath($workspaceKey, $docKey);
            if ($path !== null && is_file($path)) {
                $content = file_get_contents($path);
                if ($content !== false) {
                    $hash = hash('sha256', $content);
                    if ($state === 'missing' || $state === 'unavailable') {
                        $state = 'valid';
                    }
                }
            } else {
                $state = 'missing';
                $hash = null;
            }

            $provisioningReadiness = match ($state) {
                'valid' => 'preserve',
                'missing' => 'initialize_from_template',
                default => 'investigate',
            };

            $result[$docKey] = [
                'state' => $state,
                'content_hash' => $hash,
                'provisioning_readiness' => $provisioningReadiness,
            ];
        }

        return $result;
    }

    /**
     * @param array<string, array{state:string, content_hash:string|null}> $documents
     * @return array<string, mixed>
     */
    private static function computeLaneActions(array $documents): array
    {
        $hasMissing = false;
        $hasValid = false;

        foreach ($documents as $doc) {
            if ($doc['state'] === 'missing') {
                $hasMissing = true;
            }
            if ($doc['state'] === 'valid') {
                $hasValid = true;
            }
        }

        return [
            'has_missing' => $hasMissing,
            'has_valid' => $hasValid,
            'can_initialize' => $hasMissing,
            'can_archive_reset' => $hasValid,
        ];
    }

    /**
     * @param array<string,mixed> $coverage
     * @return array<string,mixed>|null
     */
    private static function findCoverageRow(array $coverage, string $workspaceKey): ?array
    {
        $rows = isset($coverage['rows']) && is_array($coverage['rows']) ? $coverage['rows'] : [];
        foreach ($rows as $row) {
            if ((string)($row['workspace_key'] ?? '') === $workspaceKey) {
                return $row;
            }
        }
        return null;
    }

    private static function resolveCanonicalPath(string $workspaceKey, string $documentKey): ?string
    {
        if (!in_array($documentKey, self::ALLOWED_DOCUMENTS, true)) {
            return null;
        }

        $filename = EngineeringWorkspaceContentContract::canonicalFilename($documentKey);
        if ($filename === null) {
            return null;
        }

        $dir = self::resolveWorkspaceDir($workspaceKey);
        $path = $dir . '/' . $filename;

        $root = realpath(APP_ROOT . '/engineering');
        $realPath = realpath($path);

        $pathInsideRoot = function (string $p, string $r): bool {
            $normalizedP = rtrim(str_replace('\\', '/', $p), '/');
            $normalizedR = rtrim(str_replace('\\', '/', $r), '/');
            return $normalizedP === $normalizedR || str_starts_with($normalizedP, $normalizedR . '/');
        };

        // If file doesn't exist yet, check the parent directory is safe
        if ($realPath === false) {
            $realDir = realpath($dir);
            if ($realDir === false || $root === false) {
                return null;
            }
            if (!$pathInsideRoot($realDir, $root)) {
                return null;
            }
            return $dir . '/' . $filename;
        }

        if ($root === false) {
            return null;
        }
        if (!$pathInsideRoot($realPath, $root)) {
            return null;
        }

        return $realPath;
    }

    private static function resolveWorkspaceDir(string $workspaceKey): string
    {
        return APP_ROOT . '/engineering/' . trim($workspaceKey);
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function readLastTransaction(string $workspaceKey): ?array
    {
        $workspaceSnapshotDir = self::SNAPSHOT_ROOT . '/' . $workspaceKey;

        if (!is_dir($workspaceSnapshotDir)) {
            return null;
        }

        $entries = scandir($workspaceSnapshotDir);
        if (!is_array($entries)) {
            return null;
        }

        $txDirs = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $txDir = $workspaceSnapshotDir . '/' . $entry;
            if (!is_dir($txDir)) {
                continue;
            }
            $manifestPath = $txDir . '/manifest.json';
            if (!is_file($manifestPath)) {
                continue;
            }
            $manifest = json_decode(file_get_contents($manifestPath) ?: '{}', true);
            if (!is_array($manifest) || empty($manifest['timestamp'])) {
                continue;
            }
            $txDirs[] = [
                'transaction_id' => $entry,
                'timestamp' => $manifest['timestamp'],
                'manifest' => $manifest,
            ];
        }

        if ($txDirs === []) {
            return null;
        }

        usort($txDirs, static fn(array $a, array $b): int => $b['timestamp'] - $a['timestamp']);

        $latest = $txDirs[0];

        return [
            'transaction_id' => $latest['transaction_id'],
            'workspace_key' => $workspaceKey,
            'timestamp' => $latest['timestamp'],
            'datetime' => $latest['manifest']['datetime'] ?? date('c', $latest['timestamp']),
            'operations' => $latest['manifest']['operations'] ?? [],
            'manifest' => $latest['manifest'],
        ];
    }

    /**
     * @param array<string, array{ok:bool, missing_headings:string[], error:string}> $results
     */
    private static function allValidated(array $results): bool
    {
        foreach ($results as $result) {
            if (!$result['ok']) {
                return false;
            }
        }
        return $results !== [];
    }

    /**
     * @param array<int,array<string,string>> $ownerKeys
     * @param array<int,array<string,mixed>>|null $coverageRows
     * @return array<int,string>
     */
    public static function collectApplicableWorkspaceKeys(array $ownerKeys, ?array $coverageRows = null): array
    {
        $seen = [];
        $keys = [];

        // 1) Owner discovery keys as primary source
        foreach ($ownerKeys as $owner) {
            $key = (string)($owner['owner_key'] ?? '');
            if ($key === '') {
                continue;
            }
            if (EngineeringWorkspaceContentContract::isReservedTemplateWorkspaceKey($key)) {
                continue;
            }
            $normalized = strtolower(trim($key));
            if (!isset($seen[$normalized])) {
                $seen[$normalized] = true;
                $keys[] = $key;
            }
        }

        // 2) Coverage row workspace keys (supplementary)
        if ($coverageRows !== null) {
            foreach ($coverageRows as $row) {
                $key = (string)($row['workspace_key'] ?? '');
                if ($key === '') {
                    continue;
                }
                if (EngineeringWorkspaceContentContract::isReservedTemplateWorkspaceKey($key)) {
                    continue;
                }
                $normalized = strtolower(trim($key));
                if (!isset($seen[$normalized])) {
                    $seen[$normalized] = true;
                    $keys[] = $key;
                }
            }
        }

        // 3) Scan engineering/ for directories not yet covered
        $engineeringRoot = APP_ROOT . '/engineering';
        if (is_dir($engineeringRoot)) {
            $iterator = new \RecursiveDirectoryIterator($engineeringRoot, \RecursiveDirectoryIterator::SKIP_DOTS);
            $dirs = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::SELF_FIRST);
            /** @var \SplFileInfo $fileInfo */
            foreach ($dirs as $fileInfo) {
                if (!$fileInfo->isDir()) {
                    continue;
                }
                $realPath = $fileInfo->getRealPath();
                if ($realPath === false) {
                    continue;
                }
                $relPath = substr($realPath, strlen($engineeringRoot) + 1);
                if ($relPath === '' || $relPath === false) {
                    continue;
                }
                if (EngineeringWorkspaceContentContract::isReservedTemplateWorkspaceKey($relPath)) {
                    continue;
                }
                $normalized = strtolower(trim($relPath));
                if (!isset($seen[$normalized])) {
                    $seen[$normalized] = true;
                    $keys[] = $relPath;
                }
            }
        }

        // Deduplicate by exact-case key (preserve first occurrence)
        $exactSeen = [];
        $unique = [];
        foreach ($keys as $k) {
            $nk = strtolower(trim($k));
            if (!isset($exactSeen[$nk])) {
                $exactSeen[$nk] = true;
                $unique[] = $k;
            }
        }

        sort($unique);
        return $unique;
    }

    /**
     * Create missing documents for all applicable workspaces in one batch.
     * Each workspace is processed independently — one failure does not block others.
     *
     * @param string[] $workspaceKeys
     * @param array<string,mixed>|null $actor
     * @return array<string,mixed>
     */
    public static function bulkCreateMissingDocuments(array $workspaceKeys, ?array $actor): array
    {
        $created = [];
        $alreadyComplete = [];
        $failed = [];
        $skipped = [];

        foreach ($workspaceKeys as $workspaceKey) {
            $validated = self::validateDeployWorkspaceKey($workspaceKey);
            if (!$validated['ok']) {
                $skipped[] = [
                    'workspace_key' => $workspaceKey,
                    'reason' => $validated['error'],
                ];
                continue;
            }

            // Ensure workspace directory exists (createMissingDocuments requires it)
            $workspaceDir = self::resolveWorkspaceDir($workspaceKey);
            if (!is_dir($workspaceDir) && !@mkdir($workspaceDir, 0755, true)) {
                $failed[] = [
                    'workspace_key' => $workspaceKey,
                    'error' => 'Could not create workspace directory',
                ];
                continue;
            }

            // Determine which docs are missing
            $missingKeys = [];
            foreach (self::ALLOWED_DOCUMENTS as $docKey) {
                $path = self::resolveCanonicalPath($workspaceKey, $docKey);
                if ($path !== null && !is_file($path)) {
                    $missingKeys[] = $docKey;
                }
            }

            if ($missingKeys === []) {
                $alreadyComplete[] = [
                    'workspace_key' => $workspaceKey,
                    'count' => count(self::ALLOWED_DOCUMENTS),
                ];
                continue;
            }

            // Create missing docs for this workspace
            $result = self::createMissingDocuments($workspaceKey, $missingKeys, $actor);
            if ($result['ok']) {
                $created[] = [
                    'workspace_key' => $workspaceKey,
                    'count' => $result['count'],
                    'documents' => $result['documents'],
                    'transaction_id' => $result['transaction_id'],
                    'archive_path' => $result['archive_path'] ?? '',
                ];
            } else {
                $failed[] = [
                    'workspace_key' => $workspaceKey,
                    'error' => $result['error'] ?? 'Unknown error',
                ];
            }
        }

        return [
            'ok' => true,
            'created' => $created,
            'already_complete' => $alreadyComplete,
            'failed' => $failed,
            'skipped' => $skipped,
            'total_requested' => count($workspaceKeys),
            'total_created' => count($created),
            'total_already_complete' => count($alreadyComplete),
            'total_failed' => count($failed),
            'total_skipped' => count($skipped),
        ];
    }

    private static function relativePath(string $absolutePath): string
    {
        $root = rtrim(APP_ROOT, '/');
        if (strncmp($absolutePath, $root, strlen($root)) === 0) {
            return ltrim(substr($absolutePath, strlen($root)), '/');
        }
        return $absolutePath;
    }
}
