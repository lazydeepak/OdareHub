<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

final class OwnerStructureMigrationPlannerService
{
    private const OP_RENAME_FILE = 'RENAME_FILE';
    private const OP_MOVE_FILE = 'MOVE_FILE';
    private const OP_CREATE_FOLDER = 'CREATE_FOLDER';
    private const OP_MOVE_FOLDER = 'MOVE_FOLDER';
    private const OP_DELETE_FILE = 'DELETE_FILE';
    private const OP_CREATE_PLACEHOLDER = 'CREATE_PLACEHOLDER';
    private const OP_REFERENCE_DISCOVERY_REQUIRED = 'REFERENCE_DISCOVERY_REQUIRED';
    private const OP_MANUAL_CONTRACT_DECISION = 'MANUAL_CONTRACT_DECISION';
    private const OP_OBSERVATION_ONLY = 'OBSERVATION_ONLY';

    private const OP_CREATE_ENGINEERING_WORKSPACE_FOLDER = 'CREATE_ENGINEERING_WORKSPACE_FOLDER';
    private const OP_CREATE_WORKSPACE_DOCUMENT_FROM_TEMPLATE = 'CREATE_WORKSPACE_DOCUMENT_FROM_TEMPLATE';
    private const OP_RENAME_WORKSPACE_DOCUMENT = 'RENAME_WORKSPACE_DOCUMENT';
    private const OP_MOVE_WORKSPACE_DOCUMENT = 'MOVE_WORKSPACE_DOCUMENT';
    private const OP_REPAIR_WORKSPACE_DOCUMENT_CONTRACT = 'REPAIR_WORKSPACE_DOCUMENT_CONTRACT';
    private const OP_ARCHIVE_ORPHAN_WORKSPACE_DOCUMENT = 'ARCHIVE_ORPHAN_WORKSPACE_DOCUMENT';

    private const BLOCKER_EXECUTABLE = 'EXECUTABLE_NOW';
    private const BLOCKER_REFERENCE = 'BLOCKED_BY_REFERENCE_DISCOVERY';
    private const BLOCKER_RUNTIME = 'BLOCKED_BY_RUNTIME_SUPPORT';
    private const BLOCKER_CONTRACT = 'BLOCKED_BY_CONTRACT_DECISION';
    private const BLOCKER_MISSING_TARGET = 'BLOCKED_BY_MISSING_TARGET_CONTRACT';
    private const BLOCKER_FUTURE = 'FUTURE_PHASE';
    private const BLOCKER_OBSERVATION = 'OBSERVATION_ONLY';

    /**
     * @param array<string,mixed> $selectedOwner
     * @param array<string,mixed> $scanResult
     * @param array<string,mixed> $classification
     * @param array<string,mixed> $diagnosis
     * @param array<string,mixed> $engineeringWorkspaceArtifacts
     * @return array<string,mixed>
     */
    public static function plan(array $selectedOwner, array $scanResult, array $classification, array $diagnosis, array $engineeringWorkspaceArtifacts = []): array
    {
        unset($scanResult, $classification);

        $ownerRoot = (string)($selectedOwner['owner_root_relative_path'] ?? '');
        $findings = isset($diagnosis['findings']) && is_array($diagnosis['findings']) ? $diagnosis['findings'] : [];
        $ops = [];
        $folderOps = [];

        foreach ($findings as $finding) {
            if (is_array($finding)) {
                self::appendFindingOperations($ops, $folderOps, $ownerRoot, $finding);
            }
        }

        $ewOps = self::planEngineeringWorkspace($engineeringWorkspaceArtifacts);
        $ops = array_merge($ops, $ewOps);

        $ops = self::dedupeOperations($ops);
        $ops = self::assignExecutionOrder($ops);

        return [
            'planner_version' => 'Owner Structure Migration Planner v1',
            'owner_key' => (string)($selectedOwner['owner_key'] ?? ''),
            'owner_root' => $ownerRoot,
            'summary' => self::summary($ops),
            'operations' => $ops,
            'engineering_workspace_operations' => $ewOps,
        ];
    }

    /**
     * @param array<string,mixed> $artifacts
     * @return array<int,array<string,mixed>>
     */
    private static function planEngineeringWorkspace(array $artifacts): array
    {
        $ops = [];
        $overallState = (string)($artifacts['overall_state'] ?? 'Not applicable');

        if ($overallState === 'Not applicable') {
            return [];
        }

        $workspaceKey = (string)($artifacts['workspace_key'] ?? '');
        $canonicalRoot = (string)($artifacts['canonical_relative_path'] ?? '');
        $documents = isset($artifacts['documents']) && is_array($artifacts['documents']) ? $artifacts['documents'] : [];

        $workspaceFolderExists = is_dir(APP_ROOT . '/' . $canonicalRoot);
        $workspaceFolderOpId = '';

        if (!$workspaceFolderExists) {
            $folderOp = self::engineeringOperation(
                self::OP_CREATE_ENGINEERING_WORKSPACE_FOLDER,
                'info',
                '',
                $canonicalRoot,
                'Engineering workspace folder does not exist.',
                'Create engineering/' . $workspaceKey . '/ directory.',
                [],
                'yes',
                'no',
                'yes',
                self::BLOCKER_MISSING_TARGET,
                'Workspace folder must exist before creating documents.',
                'None.',
                'Owner Structure Scan',
                '',
                '',
                '',
                '',
                '',
                'yes'
            );
            $workspaceFolderOpId = (string)$folderOp['operation_id'];
            $ops[] = $folderOp;
        }

        foreach ($documents as $doc) {
            if (!is_array($doc)) {
                continue;
            }

            $docKey = (string)($doc['document_key'] ?? '');
            $docLabel = (string)($doc['document_label'] ?? '');
            $docPath = (string)($doc['canonical_path'] ?? '');
            $state = (string)($doc['state'] ?? 'missing');
            $filename = (string)($doc['canonical_filename'] ?? '');
            $target = $docPath !== '' ? $docPath : self::joinPath($canonicalRoot, $filename);
            $templateSource = (string)($doc['template_source'] ?? ('engineering/_templates/' . $filename));
            $currentPath = (string)($doc['current_path'] ?? $doc['source_path'] ?? '');
            $deps = $workspaceFolderOpId !== '' ? [$workspaceFolderOpId] : [];

            if (in_array($state, ['missing', 'initialization_required'], true)) {
                $ops[] = self::engineeringOperation(
                    self::OP_CREATE_WORKSPACE_DOCUMENT_FROM_TEMPLATE,
                    'info',
                    $templateSource,
                    $target,
                    $docLabel . ' document is missing from ' . $canonicalRoot . '.',
                    'Create from template: ' . $docLabel,
                    $deps,
                    'yes',
                    'no',
                    'yes',
                    $workspaceFolderExists ? self::BLOCKER_EXECUTABLE : self::BLOCKER_MISSING_TARGET,
                    $workspaceFolderExists ? 'Document can be created from template.' : 'Workspace folder must be created first.',
                    'None.',
                    'Owner Structure Scan',
                    '',
                    '',
                    '',
                    '',
                    $workspaceFolderExists ? 'yes' : '',
                    $workspaceFolderExists ? 'yes' : 'no'
                );
            } elseif ($state === 'contract_repair_required') {
                $ops[] = self::engineeringOperation(
                    self::OP_REPAIR_WORKSPACE_DOCUMENT_CONTRACT,
                    'medium',
                    $target,
                    $target,
                    $docLabel . ' is missing required sections per Engineering Workspace Content Contract.',
                    'Repair contract violations in ' . $docLabel . '.',
                    [],
                    'no',
                    'no',
                    'yes',
                    self::BLOCKER_CONTRACT,
                    'Repair must be reviewed before execution.',
                    'Platform Admin',
                    'Owner Structure Scan',
                    '',
                    '',
                    '',
                    '',
                    'no'
                );
            } elseif ($state === 'migration_required') {
                $operationType = basename($currentPath) !== basename($target)
                    ? self::OP_RENAME_WORKSPACE_DOCUMENT
                    : self::OP_MOVE_WORKSPACE_DOCUMENT;
                $ops[] = self::engineeringOperation(
                    $operationType,
                    'medium',
                    $currentPath,
                    $target,
                    $docLabel . ' document is present outside the canonical engineering workspace path.',
                    'Review and migrate the document to the canonical workspace document path.',
                    [],
                    'no',
                    'no',
                    'yes',
                    self::BLOCKER_CONTRACT,
                    'Workspace document movement requires review before execution.',
                    'Confirm the legacy document is the canonical source for this workspace document.',
                    'Platform Admin',
                    $target,
                    $operationType,
                    $currentPath,
                    $target,
                    'yes'
                );
            } elseif ($state === 'conflict_review_required') {
                $ops[] = self::engineeringOperation(
                    self::OP_ARCHIVE_ORPHAN_WORKSPACE_DOCUMENT,
                    'medium',
                    $currentPath,
                    '',
                    $docLabel . ' has a duplicate or conflicting workspace document candidate.',
                    'Review duplicate workspace document content before archive/removal planning.',
                    [],
                    'no',
                    'no',
                    'yes',
                    self::BLOCKER_CONTRACT,
                    'Conflicting workspace artifacts require human review.',
                    'Decide which document is canonical and define the orphan handling policy.',
                    'Platform Admin',
                    $currentPath,
                    self::OP_ARCHIVE_ORPHAN_WORKSPACE_DOCUMENT,
                    $currentPath,
                    '',
                    'no'
                );
            }
        }

        return $ops;
    }

    /**
     * @param array<string,mixed> $deps
     * @return array<string,mixed>
     */
    private static function engineeringOperation(
        string $opType,
        string $severity,
        string $source,
        string $target,
        string $reason,
        string $recommendation,
        array $deps,
        string $automatic,
        string $destructive,
        string $validationRequired,
        string $blockerStatus,
        string $blockerReason,
        string $preparationRequired,
        string $preparationOwner,
        string $preparationTarget,
        string $afterOpType,
        string $afterSource,
        string $afterTarget,
        string $canBecomeExecutable
    ): array {
        return self::operation(
            $opType,
            $severity,
            $source,
            $target,
            $reason,
            $recommendation,
            $deps,
            $automatic,
            $destructive,
            $validationRequired,
            $blockerStatus,
            $blockerReason,
            $preparationRequired,
            $preparationOwner,
            $preparationTarget,
            $afterOpType,
            $afterSource,
            $afterTarget,
            $canBecomeExecutable
        );
    }

    /**
     * @param array<int,array<string,mixed>> $ops
     * @param array<string,string> $folderOps
     * @param array<string,mixed> $finding
     */
    private static function appendFindingOperations(array &$ops, array &$folderOps, string $ownerRoot, array $finding): void
    {
        $status = (string)($finding['status'] ?? '');
        $severity = (string)($finding['severity'] ?? 'info');
        $source = (string)($finding['current_path'] ?? '');
        $target = (string)($finding['target_path'] ?? '');
        $reason = (string)($finding['reason'] ?? '');
        $recommendation = (string)($finding['recommendation'] ?? '');

        if ($status === 'Native v2') {
            return;
        }

        if ($status === 'Current Legacy') {
            if (str_contains($reason, 'Large file detected')) {
                $ops[] = self::operation(
                    self::OP_OBSERVATION_ONLY,
                    $severity,
                    $source,
                    '',
                    $reason,
                    'Large-file decomposition is not part of owner-structure migration.',
                    [],
                    'no',
                    'no',
                    'no',
                    self::BLOCKER_OBSERVATION,
                    'Observation only; no owner-structure operation should be generated.',
                    'None.',
                    'Owner Structure Scan',
                    '',
                    '',
                    '',
                    '',
                    'no'
                );
                return;
            }

            $ops[] = self::operation(
                self::OP_MANUAL_CONTRACT_DECISION,
                $severity,
                $source,
                '',
                $reason,
                'Namespace migration belongs to a later Runtime Migration phase.',
                [],
                'no',
                'no',
                'yes',
                self::BLOCKER_FUTURE,
                'Legacy namespace migration is runtime/application migration work, not owner-structure migration.',
                'Create a runtime-safe namespace migration plan in a later phase.',
                'Runtime Migration',
                'Runtime namespace compatibility plan',
                '',
                '',
                '',
                'no'
            );
            return;
        }

        if ($status === 'Cleanup required') {
            if (str_ends_with($source, '/.DS_Store')) {
                $ops[] = self::executable(
                    self::OP_DELETE_FILE,
                    $severity,
                    $source,
                    '',
                    $reason,
                    $recommendation,
                    [],
                    'no',
                    'yes'
                );
                return;
            }

            $ops[] = self::operation(
                self::OP_MANUAL_CONTRACT_DECISION,
                $severity,
                $source,
                '',
                $reason,
                'Define backup archive policy or deletion policy before execution.',
                [],
                'no',
                'no',
                'yes',
                self::BLOCKER_MISSING_TARGET,
                'Cleanup target is not finalized in Owner Contract v2.',
                'Define backup archive policy or deletion policy.',
                'Owner Contract',
                'Backup cleanup target contract',
                'ARCHIVE_FILE or DELETE_FILE',
                $source,
                '',
                'yes'
            );
            return;
        }

        if ($status === 'Migration required') {
            if ($source === '' && $target !== '') {
                $parentId = self::ensureParentFolders($ops, $folderOps, $ownerRoot, $target);
                $deps = $parentId !== '' ? [$parentId] : [];
                $isLifecycle = self::isLifecycleTarget($ownerRoot, $target);
                $ops[] = $isLifecycle
                    ? self::blockedRuntime(
                        self::OP_CREATE_PLACEHOLDER,
                        $severity,
                        '',
                        $target,
                        $reason,
                        $recommendation,
                        $deps
                    )
                    : self::executable(self::OP_CREATE_PLACEHOLDER, $severity, '', $target, $reason, $recommendation, $deps, 'no', 'no');
                return;
            }

            if (str_ends_with($source, '/dashboard.php')) {
                $ops[] = self::operation(
                    self::OP_MANUAL_CONTRACT_DECISION,
                    $severity,
                    $source,
                    '',
                    'Contract decision required: dashboard.php may be a root runtime hook.',
                    'Define whether dashboard.php is a runtime hook, route/view target, or obsolete legacy file.',
                    [],
                    'no',
                    'no',
                    'yes',
                    self::BLOCKER_CONTRACT,
                    'The future runtime contract for dashboard.php is not decided.',
                    'Decide whether root dashboard.php is a runtime hook, a route/view target, or a legacy file.',
                    'Owner Contract',
                    'dashboard.php runtime contract',
                    'MOVE_FILE or MARK_AS_RUNTIME_HOOK or DELETE_FILE',
                    $source,
                    '',
                    'yes'
                );
                return;
            }

            if ($target !== '') {
                $operationType = self::migrationOperationType($source, $target);
                $parentId = self::ensureParentFolders($ops, $folderOps, $ownerRoot, $target);
                $deps = $parentId !== '' ? [$parentId] : [];

                if (self::isLifecycleTarget($ownerRoot, $target)) {
                    $ops[] = self::blockedRuntime($operationType, $severity, $source, $target, $reason, $recommendation, $deps);
                    return;
                }

                if (in_array($operationType, [self::OP_RENAME_FILE, self::OP_MOVE_FOLDER], true)) {
                    $referenceDiscovery = self::referenceDiscovery($severity, $source, $target);
                    $ops[] = $referenceDiscovery;
                    $ops[] = self::blockedByReferenceDiscovery(
                        $operationType,
                        $severity,
                        $source,
                        $target,
                        $reason,
                        $recommendation,
                        array_merge($deps, [(string)$referenceDiscovery['operation_id']])
                    );
                    return;
                }
                $ops[] = self::executable($operationType, $severity, $source, $target, $reason, $recommendation, $deps, 'yes', 'no');
                return;
            }
        }

        if ($status === 'Invalid/unknown') {
            $ops[] = self::operation(
                self::OP_MANUAL_CONTRACT_DECISION,
                $severity,
                $source,
                '',
                $reason,
                $recommendation,
                [],
                'no',
                'no',
                'yes',
                self::BLOCKER_CONTRACT,
                'Artifact cannot be migrated until its owner-structure contract role is decided.',
                'Classify the artifact as contract-owned, legacy, generated, runtime, or cleanup.',
                'Owner Contract',
                $source,
                '',
                $source,
                '',
                'yes'
            );
        }
    }

    /**
     * @param array<int,string> $dependsOn
     * @return array<string,mixed>
     */
    private static function executable(
        string $operationType,
        string $severity,
        string $sourcePath,
        string $targetPath,
        string $reason,
        string $recommendation,
        array $dependsOn,
        string $automatic,
        string $destructive
    ): array {
        return self::operation(
            $operationType,
            $severity,
            $sourcePath,
            $targetPath,
            $reason,
            $recommendation,
            $dependsOn,
            $automatic,
            $destructive,
            'yes',
            self::BLOCKER_EXECUTABLE,
            'Ready for future execution from this canonical plan.',
            'Run standard preflight validation before execution.',
            'Migration Engine',
            $targetPath !== '' ? $targetPath : $sourcePath,
            $operationType,
            $sourcePath,
            $targetPath,
            'yes'
        );
    }

    /**
     * @param array<int,string> $dependsOn
     * @return array<string,mixed>
     */
    private static function blockedRuntime(
        string $operationType,
        string $severity,
        string $sourcePath,
        string $targetPath,
        string $reason,
        string $recommendation,
        array $dependsOn
    ): array {
        return self::operation(
            $operationType,
            $severity,
            $sourcePath,
            $targetPath,
            $reason,
            $recommendation,
            $dependsOn,
            'no',
            'no',
            'yes',
            self::BLOCKER_RUNTIME,
            'Runtime loader support for lifecycle/*.php is not confirmed.',
            'Update module loader/install/uninstall/bootstrap discovery to use lifecycle/*.php.',
            'Runtime Loader',
            'lifecycle/*.php discovery',
            $operationType,
            $sourcePath,
            $targetPath,
            'yes'
        );
    }

    /**
     * @return array<string,mixed>
     */
    /**
     * @param array<int,string> $dependsOn
     * @return array<string,mixed>
     */
    private static function blockedByReferenceDiscovery(
        string $operationType,
        string $severity,
        string $sourcePath,
        string $targetPath,
        string $reason,
        string $recommendation,
        array $dependsOn
    ): array {
        return self::operation(
            $operationType,
            $severity,
            $sourcePath,
            $targetPath,
            $reason,
            $recommendation,
            $dependsOn,
            'no',
            'no',
            'yes',
            self::BLOCKER_REFERENCE,
            'Reference discovery must complete before this owner-structure operation can be executed.',
            'Identify exact consuming files and references, then promote this operation into the executable set.',
            'Owner Structure Scan',
            $sourcePath . ' -> ' . $targetPath,
            $operationType,
            $sourcePath,
            $targetPath,
            'yes'
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function referenceDiscovery(string $severity, string $oldReference, string $newReference): array
    {
        return self::operation(
            self::OP_REFERENCE_DISCOVERY_REQUIRED,
            $severity,
            $oldReference,
            $newReference,
            'Reference discovery is required before execution because exact consuming files are not known yet.',
            'Discover exact files and exact references before planning content updates.',
            [],
            'no',
            'no',
            'yes',
            self::BLOCKER_REFERENCE,
            'Planner cannot determine exact target file, old reference, and new reference.',
            'Add reference discovery that identifies exact files and exact references to update.',
            'Owner Structure Scan',
            $oldReference . ' -> ' . $newReference,
            'CONTENT_UPDATE',
            $oldReference,
            $newReference,
            'yes'
        );
    }

    /**
     * @param array<int,array<string,mixed>> $ops
     * @param array<string,string> $folderOps
     * @param array<int,string> $dependsOn
     */
    private static function ensureFolderOperation(array &$ops, array &$folderOps, string $targetFolder, string $severity, string $reason, array $dependsOn, string $blockerStatus = self::BLOCKER_EXECUTABLE): string
    {
        if ($targetFolder === '') {
            return '';
        }
        if (isset($folderOps[$targetFolder])) {
            return $folderOps[$targetFolder];
        }

        $op = $blockerStatus === self::BLOCKER_RUNTIME
            ? self::blockedRuntime(self::OP_CREATE_FOLDER, $severity, '', $targetFolder, $reason, 'Create lifecycle folder after runtime support is confirmed.', $dependsOn)
            : self::executable(self::OP_CREATE_FOLDER, $severity, '', $targetFolder, $reason, 'Create folder in a future migration execution before dependent moves.', $dependsOn, 'yes', 'no');

        $folderOps[$targetFolder] = (string)$op['operation_id'];
        $ops[] = $op;
        return (string)$op['operation_id'];
    }

    /**
     * @param array<int,array<string,mixed>> $ops
     * @param array<string,string> $folderOps
     */
    private static function ensureParentFolders(array &$ops, array &$folderOps, string $ownerRoot, string $targetPath): string
    {
        $parent = self::dirnamePath($targetPath);
        if ($parent === '' || $parent === '.') {
            return '';
        }

        $ownerRelativeParent = self::ownerRelativePath($ownerRoot, $parent);
        if ($ownerRelativeParent === '') {
            return '';
        }

        $segments = explode('/', $ownerRelativeParent);
        $builtRelative = [];
        $lastId = '';
        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }
            $builtRelative[] = $segment;
            $ownerRelative = implode('/', $builtRelative);
            if (!in_array($ownerRelative, ['lifecycle', 'Database'], true)) {
                continue;
            }
            $path = self::joinPath($ownerRoot, $ownerRelative);
            $lastId = self::ensureFolderOperation(
                $ops,
                $folderOps,
                $path,
                'medium',
                'Target parent folder is required by the migration target path.',
                $lastId !== '' ? [$lastId] : [],
                $ownerRelative === 'lifecycle' ? self::BLOCKER_RUNTIME : self::BLOCKER_EXECUTABLE
            );
        }
        return $lastId;
    }

    private static function migrationOperationType(string $source, string $target): string
    {
        $sourceBase = basename($source);
        $targetBase = basename($target);
        $sourceParent = self::dirnamePath($source);
        $targetParent = self::dirnamePath($target);

        if ($sourceBase === 'migrations' && $targetBase === 'migrations') {
            return self::OP_MOVE_FOLDER;
        }
        if ($sourceParent === $targetParent && $sourceBase !== $targetBase) {
            return self::OP_RENAME_FILE;
        }
        return self::OP_MOVE_FILE;
    }

    /**
     * @param array<int,string> $dependsOn
     * @return array<string,mixed>
     */
    private static function operation(
        string $operationType,
        string $severity,
        string $sourcePath,
        string $targetPath,
        string $reason,
        string $recommendation,
        array $dependsOn,
        string $automatic,
        string $destructive,
        string $validationRequired,
        string $blockerStatus,
        string $blockerReason,
        string $preparationRequired,
        string $preparationOwner,
        string $preparationTarget,
        string $afterPreparationOperationType,
        string $afterPreparationSourcePath,
        string $afterPreparationTargetPath,
        string $canBecomeExecutable
    ): array {
        $dependsOn = array_values(array_filter(array_unique($dependsOn)));
        $operationId = self::operationId($operationType, $sourcePath, $targetPath, $blockerStatus);
        return [
            'operation_id' => $operationId,
            'operation_type' => $operationType,
            'severity' => $severity,
            'source_path' => $sourcePath,
            'target_path' => $targetPath,
            'reason' => $reason,
            'recommendation' => $recommendation,
            'execution_order' => 0,
            'depends_on' => $dependsOn,
            'automatic' => $automatic,
            'destructive' => $destructive,
            'validation_required' => $validationRequired,
            'blocker_status' => $blockerStatus,
            'blocker_reason' => $blockerReason,
            'preparation_required' => $preparationRequired,
            'preparation_owner' => $preparationOwner,
            'preparation_target' => $preparationTarget,
            'after_preparation_operation_type' => $afterPreparationOperationType,
            'after_preparation_source_path' => $afterPreparationSourcePath,
            'after_preparation_target_path' => $afterPreparationTargetPath,
            'can_become_executable' => $canBecomeExecutable,
        ];
    }

    private static function operationId(string $operationType, string $sourcePath, string $targetPath, string $blockerStatus): string
    {
        return 'oss_' . substr(sha1($operationType . '|' . $sourcePath . '|' . $targetPath . '|' . $blockerStatus), 0, 12);
    }

    /**
     * @param array<int,array<string,mixed>> $ops
     * @return array<int,array<string,mixed>>
     */
    private static function dedupeOperations(array $ops): array
    {
        $deduped = [];
        foreach ($ops as $op) {
            if (!is_array($op)) {
                continue;
            }
            $id = (string)($op['operation_id'] ?? '');
            if ($id === '') {
                continue;
            }
            if (!isset($deduped[$id])) {
                $deduped[$id] = $op;
                continue;
            }
            $existingDeps = isset($deduped[$id]['depends_on']) && is_array($deduped[$id]['depends_on']) ? $deduped[$id]['depends_on'] : [];
            $newDeps = isset($op['depends_on']) && is_array($op['depends_on']) ? $op['depends_on'] : [];
            $deduped[$id]['depends_on'] = array_values(array_unique(array_merge($existingDeps, $newDeps)));
        }
        return array_values($deduped);
    }

    /**
     * @param array<int,array<string,mixed>> $ops
     * @return array<int,array<string,mixed>>
     */
    private static function assignExecutionOrder(array $ops): array
    {
        $ordered = [];
        $executionOrder = 1;
        foreach ($ops as $op) {
            if ((string)($op['blocker_status'] ?? '') === self::BLOCKER_EXECUTABLE) {
                $op['execution_order'] = $executionOrder;
                $executionOrder++;
            } else {
                $op['execution_order'] = 0;
            }
            $ordered[] = $op;
        }
        return $ordered;
    }

    /**
     * @param array<int,array<string,mixed>> $ops
     * @return array<string,int>
     */
    private static function summary(array $ops): array
    {
        $summary = [
            'total_planned_operations' => count($ops),
            'executable_now' => 0,
            'blocked_by_reference_discovery' => 0,
            'blocked_by_runtime_support' => 0,
            'blocked_by_contract_decision' => 0,
            'blocked_by_missing_target_contract' => 0,
            'future_phase' => 0,
            'observations' => 0,
            'file_renames' => 0,
            'file_moves' => 0,
            'folder_creations' => 0,
            'folder_moves' => 0,
            'cleanup_operations' => 0,
        ];

        foreach ($ops as $op) {
            $status = (string)($op['blocker_status'] ?? '');
            if ($status === self::BLOCKER_EXECUTABLE) {
                $summary['executable_now']++;
            } elseif ($status === self::BLOCKER_REFERENCE) {
                $summary['blocked_by_reference_discovery']++;
            } elseif ($status === self::BLOCKER_RUNTIME) {
                $summary['blocked_by_runtime_support']++;
            } elseif ($status === self::BLOCKER_CONTRACT) {
                $summary['blocked_by_contract_decision']++;
            } elseif ($status === self::BLOCKER_MISSING_TARGET) {
                $summary['blocked_by_missing_target_contract']++;
            } elseif ($status === self::BLOCKER_FUTURE) {
                $summary['future_phase']++;
            } elseif ($status === self::BLOCKER_OBSERVATION) {
                $summary['observations']++;
            }

            $type = (string)($op['operation_type'] ?? '');
            if ($type === self::OP_RENAME_FILE) {
                $summary['file_renames']++;
            } elseif ($type === self::OP_MOVE_FILE) {
                $summary['file_moves']++;
            } elseif ($type === self::OP_CREATE_FOLDER) {
                $summary['folder_creations']++;
            } elseif ($type === self::OP_MOVE_FOLDER) {
                $summary['folder_moves']++;
            } elseif ($type === self::OP_DELETE_FILE) {
                $summary['cleanup_operations']++;
            }
        }

        return $summary;
    }

    private static function isLifecycleTarget(string $ownerRoot, string $target): bool
    {
        return str_starts_with(self::ownerRelativePath($ownerRoot, $target), 'lifecycle/');
    }

    private static function ownerRelativePath(string $ownerRoot, string $path): string
    {
        $ownerRoot = rtrim(str_replace('\\', '/', $ownerRoot), '/');
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($ownerRoot === '' || $path === $ownerRoot) {
            return '';
        }
        $prefix = $ownerRoot . '/';
        if (str_starts_with($path, $prefix)) {
            return substr($path, strlen($prefix));
        }
        return '';
    }

    private static function dirnamePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $dir = dirname($path);
        return $dir === '.' ? '' : $dir;
    }

    private static function joinPath(string $root, string $path): string
    {
        if ($root === '') {
            return $path;
        }
        if ($path === '') {
            return $root;
        }
        return rtrim($root, '/') . '/' . ltrim($path, '/');
    }
}
