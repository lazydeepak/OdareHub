<?php
declare(strict_types=1);

namespace Platform\Engineering;

use Platform\Security\EngineeringWorkspaceContentContract;

final class EngineeringWorkspaceAgentBootstrap
{
    private const ENGINEERING_ROOT = APP_ROOT . '/engineering';

    private const DOCUMENTS = ['overview', 'work', 'rules', 'decisions'];

    private const RESOLUTION_EXPLICIT = 'explicit_owner_key';
    private const RESOLUTION_COVERAGE = 'coverage_assignment';
    private const RESOLUTION_DIRECT_DIR = 'direct_owner_workspace';
    private const RESOLUTION_UNRESOLVED = 'resolution_required';
    private const RESOLUTION_NONE = 'no_workspace_context';
    private const RESOLUTION_REJECTED = 'rejected';

    /**
     * Prepare implementation context for an agent.
     *
     * @param string $taskText The agent's task description
     * @param string|null $canonicalOwnerKey Explicit owner key when known
     * @param string[] $sourcePathHints Optional source path hints for owner resolution
     * @param array|null $actor Optional actor identity for authorization
     * @return array<string,mixed> Complete bootstrap result
     */
    public static function prepareImplementationContext(
        string $taskText,
        ?string $canonicalOwnerKey,
        array $sourcePathHints,
        ?array $actor
    ): array {
        // Step 1: Preflight evaluation
        $preflight = EngineeringWorkspaceAgentPreflightService::evaluateTask(
            $taskText,
            $canonicalOwnerKey,
            $sourcePathHints,
            $actor
        );

        $mode = (string)($preflight['mode'] ?? 'read_only');
        $ownerKey = (string)($preflight['resolved_owner_key'] ?? '');

        if (!$preflight['ok']) {
            return self::rejectedResult('Preflight evaluation failed: ' . $preflight['error'], $mode);
        }

        // Step 2: Check rejected states
        if ($preflight['resolution_source'] === self::RESOLUTION_REJECTED) {
            return self::rejectedResult($preflight['error'], $mode);
        }

        if ($preflight['resolution_source'] === self::RESOLUTION_UNRESOLVED) {
            return self::unresolvedResult($mode);
        }

        if ($ownerKey === '') {
            return self::noWorkspaceResult('No owner could be resolved from the task', $mode);
        }

        // Step 3: Resolve the workspace key from resolution source
        $workspaceKey = '';
        $resolutionSource = $preflight['resolution_source'];

        // 3a: Direct resolution from explicit owner key (only if dir exists)
        if ($resolutionSource === self::RESOLUTION_EXPLICIT || $resolutionSource === self::RESOLUTION_DIRECT_DIR) {
            $candidate = self::ownerToWorkspaceKey($ownerKey);
            if ($candidate !== '' && self::workspaceDirExists($candidate)) {
                $workspaceKey = $candidate;
            }
        }

        // 3b: Try coverage assignment if no valid workspace yet
        if ($workspaceKey === '') {
            $coverageResult = self::resolveFromCoverage($ownerKey);
            if ($coverageResult !== null) {
                $workspaceKey = $coverageResult;
                $resolutionSource = self::RESOLUTION_COVERAGE;
            }
        }

        // 3c: Try direct owner directory if still no valid workspace
        if ($workspaceKey === '') {
            $dirCandidate = self::ownerToWorkspaceKey($ownerKey);
            if ($dirCandidate !== '' && self::workspaceDirExists($dirCandidate)) {
                $workspaceKey = $dirCandidate;
                $resolutionSource = self::RESOLUTION_DIRECT_DIR;
            }
        }

        // 3d: No workspace found — allow creation in implementation mode
        if ($workspaceKey === '') {
            $dirCandidate = self::ownerToWorkspaceKey($ownerKey);
            if ($mode === 'implementation' && $dirCandidate !== '') {
                $workspaceKey = $dirCandidate;
                $resolutionSource = self::RESOLUTION_DIRECT_DIR;
            } else {
                return self::noWorkspaceResult('No workspace exists for owner "' . $ownerKey . '"', $mode);
            }
        }

        // Step 4: Validate workspace key safety
        $safety = EngineeringWorkspaceAgentPreflightService::checkOwnerKeySafety($workspaceKey);
        if (!$safety['ok']) {
            return self::rejectedResult('Workspace key failed safety check: ' . $safety['error'], $mode);
        }

        if (EngineeringWorkspaceContentContract::isReservedTemplateWorkspaceKey($workspaceKey)) {
            return self::rejectedResult('Workspace key is a reserved template root', $mode);
        }

        $workspaceDir = self::ENGINEERING_ROOT . '/' . $workspaceKey;

        // Step 5: In implementation mode, create directory and missing documents
        $createdMissing = [];
        $repairRequired = [];
        $documentResults = [];

        if ($mode === 'implementation') {
            // Create workspace directory if absent
            if (!is_dir($workspaceDir)) {
                if (!@mkdir($workspaceDir, 0755, true)) {
                    return self::rejectedResult('Could not create workspace directory: ' . $workspaceDir, $mode);
                }
            }

            // Check and create missing documents
            foreach (self::DOCUMENTS as $docType) {
                $filename = EngineeringWorkspaceContentContract::canonicalFilename($docType);
                if ($filename === null) {
                    continue;
                }

                $path = $workspaceDir . '/' . $filename;

                if (!is_file($path)) {
                    // Create from template
                    $content = EngineeringWorkspaceContentContract::initialContent($workspaceKey, $docType);

                    // Validate generated content
                    $validation = EngineeringWorkspaceContentContract::validateDocumentContent($docType, $content);
                    if (!$validation['ok']) {
                        continue;
                    }

                    // Atomic write
                    $tmpPath = $path . '.tmp.' . bin2hex(random_bytes(8));
                    $written = @file_put_contents($tmpPath, $content, LOCK_EX);
                    if ($written === false) {
                        @unlink($tmpPath);
                        continue;
                    }
                    if (!@rename($tmpPath, $path)) {
                        @unlink($tmpPath);
                        continue;
                    }
                    clearstatcache(true, $path);
                    if (function_exists('opcache_invalidate')) {
                        @opcache_invalidate($path, true);
                    }

                    $createdMissing[] = $docType;
                }
            }
        }

        // Step 6: Read, validate, and build document context
        foreach (self::DOCUMENTS as $docType) {
            $filename = EngineeringWorkspaceContentContract::canonicalFilename($docType);
            if ($filename === null) {
                continue;
            }

            $path = $workspaceDir . '/' . $filename;
            $exists = is_file($path) && is_readable($path);

            if (!$exists) {
                $documentResults[$docType] = [
                    'path' => '',
                    'validated' => false,
                    'content' => null,
                    'status' => 'missing',
                ];
                continue;
            }

            $content = file_get_contents($path);
            if ($content === false) {
                $documentResults[$docType] = [
                    'path' => self::relativePath($path),
                    'validated' => false,
                    'content' => null,
                    'status' => 'unreadable',
                ];
                continue;
            }

            $validation = EngineeringWorkspaceContentContract::validateDocumentContent($docType, $content);
            $valid = !empty($validation['ok']);

            $documentResults[$docType] = [
                'path' => self::relativePath($path),
                'validated' => $valid,
                'content' => $content,
                'status' => $valid ? 'valid' : 'contract_repair_required',
            ];

            if (!$valid) {
                $repairRequired[] = $docType;
            }
        }

        // Step 7: Determine overall context state
        $allPresent = count($documentResults) === 4;
        $allValid = $allPresent && $repairRequired === [];

        if ($mode === 'read_only') {
            $contextState = $allValid ? 'ready' : ($repairRequired !== [] ? 'degraded' : 'no_workspace_context');
        } elseif ($allPresent && $allValid) {
            $contextState = 'ready';
        } elseif ($allPresent) {
            $contextState = 'degraded';
        } elseif ($createdMissing !== [] || $mode === 'implementation') {
            // After creating missing docs, recheck — they should all exist now
            $contextState = $repairRequired !== [] ? 'degraded' : 'ready';
        } else {
            $contextState = 'no_workspace_context';
        }

        $instruction = '';
        if ($contextState === 'ready' || $contextState === 'degraded') {
            $instruction = self::CONTEXT_INSTRUCTION;
        }

        return [
            'workspace_key' => $workspaceKey,
            'resolution_source' => $resolutionSource,
            'workspace_context_state' => $contextState,
            'mode' => $mode,
            'created_missing_documents' => $createdMissing,
            'repair_required_documents' => $repairRequired,
            'documents' => $documentResults,
            'instruction' => $instruction,
        ];
    }

    private const CONTEXT_INSTRUCTION = <<<'INSTRUCTION'
This is the resolved Engineering Workspace context for the task.
Engineering Workspace is the authoritative contract for engineering intent, scope, boundaries, roadmap, rules, and decisions.
Repository code is implementation truth. The current user request is task truth. Probes, tests, browser evidence, and regression requirements are validation truth.
Execution lifecycle: Resolve Workspace → Load Contract → Understand Intent → Inspect Repository Truth → Compare Intent vs Reality → Implement / Review / Analyze.
There is no implementation, review, planning, or analysis path that skips the loaded workspace contract.
When workspace intent conflicts with repository truth or the task request, report the conflict instead of silently choosing one.
INSTRUCTION;

    /**
     * Convert an owner key to a workspace key.
     */
    private static function ownerToWorkspaceKey(string $ownerKey): string
    {
        return trim($ownerKey, "/ \t\n\r\0\x0B");
    }

    /**
     * Check if a workspace directory exists.
     */
    private static function workspaceDirExists(string $workspaceKey): bool
    {
        $dir = self::ENGINEERING_ROOT . '/' . $workspaceKey;
        return is_dir($dir);
    }

    /**
     * Try to resolve a workspace key from coverage assignment.
     */
    private static function resolveFromCoverage(string $ownerKey): ?string
    {
        $assignmentPath = APP_ROOT . '/storage/engineering-workspace-coverage-assignment.json';
        if (!is_file($assignmentPath) || !is_readable($assignmentPath)) {
            return null;
        }

        $raw = file_get_contents($assignmentPath);
        if ($raw === false) {
            return null;
        }

        $assignments = json_decode($raw, true);
        if (!is_array($assignments) || !isset($assignments[$ownerKey])) {
            return null;
        }

        $entry = $assignments[$ownerKey];
        if (!is_array($entry)) {
            return null;
        }

        $mode = (string)($entry['mode'] ?? '');

        if ($mode === 'dedicated') {
            $wsKey = (string)($entry['workspace_key'] ?? '');
            if ($wsKey !== '' && self::workspaceDirExists($wsKey)) {
                return $wsKey;
            }
        }

        if ($mode === 'covered_by') {
            $wsKey = (string)($entry['workspace_key'] ?? '');
            if ($wsKey !== '' && self::workspaceDirExists($wsKey)) {
                return $wsKey;
            }
        }

        return null;
    }

    /**
     * @return array<string,mixed>
     */
    private static function unresolvedResult(string $mode): array
    {
        return [
            'workspace_key' => '',
            'resolution_source' => self::RESOLUTION_UNRESOLVED,
            'workspace_context_state' => 'resolution_required',
            'mode' => $mode,
            'created_missing_documents' => [],
            'repair_required_documents' => [],
            'documents' => [],
            'instruction' => '',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function rejectedResult(string $reason, string $mode): array
    {
        return [
            'workspace_key' => '',
            'resolution_source' => self::RESOLUTION_REJECTED,
            'workspace_context_state' => 'rejected',
            'mode' => $mode,
            'created_missing_documents' => [],
            'repair_required_documents' => [],
            'documents' => [],
            'instruction' => '',
            'error' => $reason,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function noWorkspaceResult(string $reason, string $mode): array
    {
        return [
            'workspace_key' => '',
            'resolution_source' => self::RESOLUTION_NONE,
            'workspace_context_state' => 'no_workspace_context',
            'mode' => $mode,
            'created_missing_documents' => [],
            'repair_required_documents' => [],
            'documents' => [],
            'instruction' => '',
            'error' => $reason,
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
