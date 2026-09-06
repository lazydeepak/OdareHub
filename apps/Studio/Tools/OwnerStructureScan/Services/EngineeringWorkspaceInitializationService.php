<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureOwnerDiscoveryService.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureFilesystemScannerService.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureArtifactClassifierService.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureContractV2DiagnosisService.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/EngineeringWorkspaceArtifactService.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureMigrationPlannerService.php';

use Platform\Security\EngineeringWorkspaceContentContract;
use Platform\Security\EngineeringWorkspaceResolver;
use Platform\Security\PlatformAuthority;

final class EngineeringWorkspaceInitializationService
{
    private const OP_CREATE_FOLDER = 'CREATE_ENGINEERING_WORKSPACE_FOLDER';
    private const OP_CREATE_DOCUMENT = 'CREATE_WORKSPACE_DOCUMENT_FROM_TEMPLATE';
    private const EVIDENCE_PATH = APP_ROOT . '/storage/studio/owner-structure-scan/engineering-workspace-initialization.jsonl';

    /**
     * @param array<string,mixed> $migrationPlan
     * @return array<string,mixed>
     */
    public static function buildInitializationPlan(string $ownerKey, array $migrationPlan): array
    {
        $workspaceKey = EngineeringWorkspaceResolver::ownerKeyToWorkspaceKey($ownerKey);
        $operations = isset($migrationPlan['engineering_workspace_operations']) && is_array($migrationPlan['engineering_workspace_operations'])
            ? $migrationPlan['engineering_workspace_operations']
            : [];
        $eligible = [];

        foreach ($operations as $operation) {
            if (!is_array($operation)) {
                continue;
            }
            $type = (string)($operation['operation_type'] ?? '');
            if (!in_array($type, [self::OP_CREATE_FOLDER, self::OP_CREATE_DOCUMENT], true)) {
                continue;
            }

            $normalized = self::normalizeEligibleOperation($workspaceKey, $operation);
            if ($normalized !== null) {
                $eligible[] = $normalized;
            }
        }

        usort($eligible, static function (array $a, array $b): int {
            $aOrder = (int)($a['execution_order'] ?? 0);
            $bOrder = (int)($b['execution_order'] ?? 0);
            if ($aOrder !== $bOrder) {
                if ($aOrder === 0) {
                    return 1;
                }
                if ($bOrder === 0) {
                    return -1;
                }
                return $aOrder <=> $bOrder;
            }
            return strcmp((string)($a['operation_id'] ?? ''), (string)($b['operation_id'] ?? ''));
        });

        return [
            'owner_key' => $ownerKey,
            'workspace_key' => $workspaceKey,
            'eligible_operations' => $eligible,
            'fingerprint' => self::fingerprint($ownerKey, $workspaceKey, $eligible),
        ];
    }

    /**
     * @param string[] $selectedOperationIds
     * @param array<string,mixed>|null $actor
     * @return array<string,mixed>
     */
    public static function initialize(string $ownerKey, array $selectedOperationIds, string $submittedFingerprint, ?array $actor): array
    {
        $ownerKey = trim($ownerKey);
        $selectedOperationIds = self::cleanOperationIds($selectedOperationIds);
        $submittedFingerprint = trim($submittedFingerprint);

        if (!PlatformAuthority::canManageEngineeringWorkspaces($actor)) {
            return self::rejected($ownerKey, '', $selectedOperationIds, 'unauthorized');
        }
        if ($ownerKey === '') {
            return self::rejected($ownerKey, '', $selectedOperationIds, 'owner_required');
        }
        if ($selectedOperationIds === []) {
            return self::rejected($ownerKey, '', $selectedOperationIds, 'no_operations_selected');
        }

        $bundle = self::freshPlanBundle($ownerKey);
        if (empty($bundle['ok'])) {
            return self::rejected($ownerKey, (string)($bundle['workspace_key'] ?? ''), $selectedOperationIds, (string)($bundle['reason_code'] ?? 'plan_unavailable'));
        }

        $initializationPlan = isset($bundle['initialization_plan']) && is_array($bundle['initialization_plan'])
            ? $bundle['initialization_plan']
            : [];
        $workspaceKey = (string)($initializationPlan['workspace_key'] ?? '');
        $currentFingerprint = (string)($initializationPlan['fingerprint'] ?? '');

        if ($submittedFingerprint === '' || !hash_equals($currentFingerprint, $submittedFingerprint)) {
            return self::rejected($ownerKey, $workspaceKey, $selectedOperationIds, 'stale_or_mismatched_plan', [
                'current_fingerprint' => $currentFingerprint,
            ]);
        }

        $eligible = isset($initializationPlan['eligible_operations']) && is_array($initializationPlan['eligible_operations'])
            ? $initializationPlan['eligible_operations']
            : [];
        $eligibleById = [];
        foreach ($eligible as $operation) {
            if (is_array($operation)) {
                $eligibleById[(string)($operation['operation_id'] ?? '')] = $operation;
            }
        }

        $results = [];
        $createdPaths = [];
        $createdHashes = [];

        foreach ($selectedOperationIds as $operationId) {
            if (!isset($eligibleById[$operationId])) {
                $results[] = self::operationResult($operationId, '', '', 'Rejected', 'operation_not_in_current_plan');
                continue;
            }

            $operation = $eligibleById[$operationId];
            $type = (string)($operation['operation_type'] ?? '');
            $result = $type === self::OP_CREATE_FOLDER
                ? self::createWorkspaceFolder($workspaceKey, $operation)
                : self::createWorkspaceDocument($workspaceKey, $operation);

            $results[] = $result;
            if ((string)($result['final_result'] ?? '') === 'Created') {
                $path = (string)($result['target_path'] ?? '');
                if ($path !== '') {
                    $createdPaths[] = $path;
                }
                if (isset($result['content_hash']) && (string)$result['content_hash'] !== '') {
                    $createdHashes[$path] = (string)$result['content_hash'];
                }
            }
        }

        self::recordEvidence($ownerKey, $workspaceKey, $selectedOperationIds, $createdPaths, $createdHashes, $actor);

        return [
            'ok' => true,
            'owner_key' => $ownerKey,
            'workspace_key' => $workspaceKey,
            'fingerprint' => $currentFingerprint,
            'selected_operation_ids' => $selectedOperationIds,
            'results' => $results,
            'created_paths' => $createdPaths,
            'created_hashes' => $createdHashes,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function freshPlanBundle(string $ownerKey): array
    {
        $ownerResolution = OwnerStructureOwnerDiscoveryService::resolve($ownerKey);
        $selectedOwnerKey = (string)($ownerResolution['selected_owner_key'] ?? '');
        if ($selectedOwnerKey === '' || $selectedOwnerKey !== $ownerKey) {
            return ['ok' => false, 'workspace_key' => '', 'reason_code' => 'invalid_or_unmapped_owner'];
        }

        $workspaceKey = EngineeringWorkspaceResolver::ownerKeyToWorkspaceKey($selectedOwnerKey);
        if ($workspaceKey !== $selectedOwnerKey || !EngineeringWorkspaceResolver::isValidWorkspaceKey($workspaceKey)) {
            return ['ok' => false, 'workspace_key' => $workspaceKey, 'reason_code' => 'workspace_mapping_not_exact'];
        }

        $selectedOwner = isset($ownerResolution['selected_owner']) && is_array($ownerResolution['selected_owner'])
            ? $ownerResolution['selected_owner']
            : [];
        $scanResult = OwnerStructureFilesystemScannerService::scan($selectedOwnerKey);
        $entries = isset($scanResult['entries']) && is_array($scanResult['entries']) ? $scanResult['entries'] : [];
        $classification = OwnerStructureArtifactClassifierService::classify($entries);
        $diagnosis = OwnerStructureContractV2DiagnosisService::diagnose($scanResult);
        $artifacts = EngineeringWorkspaceArtifactService::resolveArtifacts($selectedOwnerKey);
        $migrationPlan = OwnerStructureMigrationPlannerService::plan($selectedOwner, $scanResult, $classification, $diagnosis, $artifacts);
        $initializationPlan = self::buildInitializationPlan($selectedOwnerKey, $migrationPlan);

        return [
            'ok' => true,
            'workspace_key' => $workspaceKey,
            'migration_plan' => $migrationPlan,
            'initialization_plan' => $initializationPlan,
        ];
    }

    /**
     * @param array<string,mixed> $operation
     * @return array<string,mixed>|null
     */
    private static function normalizeEligibleOperation(string $workspaceKey, array $operation): ?array
    {
        if ($workspaceKey === '' || !EngineeringWorkspaceResolver::isValidWorkspaceKey($workspaceKey)) {
            return null;
        }

        $type = (string)($operation['operation_type'] ?? '');
        $operationId = (string)($operation['operation_id'] ?? '');
        if ($operationId === '') {
            return null;
        }

        if ($type === self::OP_CREATE_FOLDER) {
            $target = 'engineering/' . $workspaceKey;
            return [
                'operation_id' => $operationId,
                'operation_type' => $type,
                'target_path' => $target,
                'document_type' => '',
                'execution_order' => (int)($operation['execution_order'] ?? 0),
                'exists' => is_dir(APP_ROOT . '/' . $target),
            ];
        }

        if ($type !== self::OP_CREATE_DOCUMENT) {
            return null;
        }

        $targetPath = (string)($operation['target_path'] ?? '');
        $documentKey = self::documentKeyForRelativeTarget($workspaceKey, $targetPath);
        if ($documentKey === '') {
            return null;
        }
        $canonicalPath = EngineeringWorkspaceContentContract::documentPath($workspaceKey, $documentKey);
        if ($canonicalPath === null) {
            return null;
        }

        return [
            'operation_id' => $operationId,
            'operation_type' => $type,
            'target_path' => self::relativePath($canonicalPath),
            'document_type' => $documentKey,
            'execution_order' => (int)($operation['execution_order'] ?? 0),
            'exists' => is_file($canonicalPath),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $eligible
     */
    private static function fingerprint(string $ownerKey, string $workspaceKey, array $eligible): string
    {
        $rows = [];
        foreach ($eligible as $operation) {
            $rows[] = [
                'operation_id' => (string)($operation['operation_id'] ?? ''),
                'operation_type' => (string)($operation['operation_type'] ?? ''),
                'target_path' => (string)($operation['target_path'] ?? ''),
                'document_type' => (string)($operation['document_type'] ?? ''),
                'exists' => !empty($operation['exists']) ? 'yes' : 'no',
            ];
        }
        usort($rows, static fn (array $a, array $b): int => strcmp((string)$a['operation_id'], (string)$b['operation_id']));

        return hash('sha256', json_encode([
            'owner_key' => $ownerKey,
            'workspace_key' => $workspaceKey,
            'eligible_operations' => $rows,
        ], JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param array<string,mixed> $operation
     * @return array<string,mixed>
     */
    private static function createWorkspaceFolder(string $workspaceKey, array $operation): array
    {
        $target = 'engineering/' . $workspaceKey;
        if ((string)($operation['target_path'] ?? '') !== $target) {
            return self::operationResult((string)($operation['operation_id'] ?? ''), self::OP_CREATE_FOLDER, $target, 'Blocked', 'target_mismatch');
        }

        $path = APP_ROOT . '/' . $target;
        if (is_dir($path)) {
            return self::operationResult((string)$operation['operation_id'], self::OP_CREATE_FOLDER, $target, 'Already exists', 'target_already_exists');
        }

        if (!self::isSafeWorkspaceKey($workspaceKey)) {
            return self::operationResult((string)$operation['operation_id'], self::OP_CREATE_FOLDER, $target, 'Blocked', 'invalid_workspace_key');
        }

        if (!@mkdir($path, 0755, true) && !is_dir($path)) {
            return self::operationResult((string)$operation['operation_id'], self::OP_CREATE_FOLDER, $target, 'Blocked', 'mkdir_failed');
        }

        if (!EngineeringWorkspaceContentContract::pathIsInsideEngineeringRoot($path)) {
            return self::operationResult((string)$operation['operation_id'], self::OP_CREATE_FOLDER, $target, 'Blocked', 'target_outside_engineering_root');
        }

        return self::operationResult((string)$operation['operation_id'], self::OP_CREATE_FOLDER, $target, 'Created', '');
    }

    /**
     * @param array<string,mixed> $operation
     * @return array<string,mixed>
     */
    private static function createWorkspaceDocument(string $workspaceKey, array $operation): array
    {
        $documentKey = (string)($operation['document_type'] ?? '');
        $operationId = (string)($operation['operation_id'] ?? '');
        $canonicalPath = EngineeringWorkspaceContentContract::documentPath($workspaceKey, $documentKey);
        if ($canonicalPath === null) {
            return self::operationResult($operationId, self::OP_CREATE_DOCUMENT, (string)($operation['target_path'] ?? ''), 'Blocked', 'invalid_document_target');
        }

        $relativePath = self::relativePath($canonicalPath);
        if ((string)($operation['target_path'] ?? '') !== $relativePath) {
            return self::operationResult($operationId, self::OP_CREATE_DOCUMENT, $relativePath, 'Blocked', 'target_mismatch');
        }

        if (is_file($canonicalPath)) {
            return self::operationResult($operationId, self::OP_CREATE_DOCUMENT, $relativePath, 'Already exists', 'target_already_exists');
        }

        $workspaceDir = dirname($canonicalPath);
        if (!is_dir($workspaceDir)) {
            return self::operationResult($operationId, self::OP_CREATE_DOCUMENT, $relativePath, 'Blocked', 'workspace_folder_missing');
        }
        if (!EngineeringWorkspaceContentContract::pathIsInsideEngineeringRoot($workspaceDir)) {
            return self::operationResult($operationId, self::OP_CREATE_DOCUMENT, $relativePath, 'Blocked', 'target_outside_engineering_root');
        }

        $templatePath = EngineeringWorkspaceContentContract::templatePath($documentKey);
        if ($templatePath === null || !is_file($templatePath) || !is_readable($templatePath)) {
            return self::operationResult($operationId, self::OP_CREATE_DOCUMENT, $relativePath, 'Blocked', 'template_unavailable');
        }

        $template = file_get_contents($templatePath);
        if (!is_string($template)) {
            return self::operationResult($operationId, self::OP_CREATE_DOCUMENT, $relativePath, 'Blocked', 'template_read_failed');
        }

        $content = str_replace('<Workspace Name>', EngineeringWorkspaceContentContract::displayWorkspaceName($workspaceKey), $template);
        $validation = EngineeringWorkspaceContentContract::validateDocumentContent($documentKey, $content);
        if (empty($validation['ok'])) {
            return self::operationResult($operationId, self::OP_CREATE_DOCUMENT, $relativePath, 'Blocked', 'template_contract_failed');
        }

        $handle = @fopen($canonicalPath, 'xb');
        if ($handle === false) {
            clearstatcache(true, $canonicalPath);
            if (is_file($canonicalPath)) {
                return self::operationResult($operationId, self::OP_CREATE_DOCUMENT, $relativePath, 'Already exists', 'target_appeared_after_planning');
            }
            return self::operationResult($operationId, self::OP_CREATE_DOCUMENT, $relativePath, 'Blocked', 'exclusive_create_failed');
        }

        $ok = false;
        try {
            $ok = fwrite($handle, $content) === strlen($content);
        } finally {
            fclose($handle);
        }
        if (!$ok) {
            @unlink($canonicalPath);
            return self::operationResult($operationId, self::OP_CREATE_DOCUMENT, $relativePath, 'Blocked', 'write_failed');
        }

        $storedContent = file_get_contents($canonicalPath);
        if (!is_string($storedContent)) {
            return self::operationResult($operationId, self::OP_CREATE_DOCUMENT, $relativePath, 'Blocked', 'created_document_unreadable');
        }
        $storedValidation = EngineeringWorkspaceContentContract::validateDocumentContent($documentKey, $storedContent);
        if (empty($storedValidation['ok'])) {
            return self::operationResult($operationId, self::OP_CREATE_DOCUMENT, $relativePath, 'Blocked', 'created_document_contract_failed');
        }

        $result = self::operationResult($operationId, self::OP_CREATE_DOCUMENT, $relativePath, 'Created', '');
        $result['content_hash'] = hash('sha256', $storedContent);
        return $result;
    }

    private static function documentKeyForRelativeTarget(string $workspaceKey, string $relativeTarget): string
    {
        foreach (EngineeringWorkspaceContentContract::allowedDocumentKeys() as $documentKey) {
            $path = EngineeringWorkspaceContentContract::documentPath($workspaceKey, $documentKey);
            if ($path !== null && self::relativePath($path) === $relativeTarget) {
                return $documentKey;
            }
        }
        return '';
    }

    /**
     * @param string[] $ids
     * @return string[]
     */
    private static function cleanOperationIds(array $ids): array
    {
        $clean = [];
        foreach ($ids as $id) {
            $id = trim((string)$id);
            if ($id !== '' && preg_match('/^oss_[a-f0-9]{12}$/', $id) === 1) {
                $clean[] = $id;
            }
        }
        return array_values(array_unique($clean));
    }

    /**
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private static function rejected(string $ownerKey, string $workspaceKey, array $selectedOperationIds, string $reasonCode, array $extra = []): array
    {
        return array_merge([
            'ok' => false,
            'owner_key' => $ownerKey,
            'workspace_key' => $workspaceKey,
            'selected_operation_ids' => $selectedOperationIds,
            'results' => [
                self::operationResult('', '', '', 'Rejected', $reasonCode),
            ],
            'reason_code' => $reasonCode,
        ], $extra);
    }

    /**
     * @return array<string,mixed>
     */
    private static function operationResult(string $operationId, string $type, string $targetPath, string $finalResult, string $reasonCode): array
    {
        return [
            'operation_id' => $operationId,
            'operation_type' => $type,
            'target_path' => $targetPath,
            'final_result' => $finalResult,
            'reason_code' => $reasonCode,
        ];
    }

    /**
     * @param string[] $selectedOperationIds
     * @param string[] $createdPaths
     * @param array<string,string> $createdHashes
     * @param array<string,mixed>|null $actor
     */
    private static function recordEvidence(string $ownerKey, string $workspaceKey, array $selectedOperationIds, array $createdPaths, array $createdHashes, ?array $actor): void
    {
        $dir = dirname(self::EVIDENCE_PATH);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $entry = [
            'timestamp' => gmdate('c'),
            'owner_key' => $ownerKey,
            'workspace_key' => $workspaceKey,
            'selected_operation_ids' => array_values($selectedOperationIds),
            'created_relative_paths' => array_values($createdPaths),
            'created_document_hashes' => $createdHashes,
            'actor' => [
                'id' => (string)($actor['user_id'] ?? $actor['id'] ?? ''),
                'email' => (string)($actor['email'] ?? ''),
                'role' => (string)($actor['authority_role'] ?? ''),
            ],
        ];

        @file_put_contents(self::EVIDENCE_PATH, json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
    }

    private static function isSafeWorkspaceKey(string $workspaceKey): bool
    {
        return EngineeringWorkspaceResolver::isValidWorkspaceKey($workspaceKey)
            && !str_contains($workspaceKey, '..')
            && !str_starts_with($workspaceKey, '/')
            && !str_contains($workspaceKey, '\\');
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
