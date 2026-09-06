<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\EngineeringWorkspaces\Services;

use Platform\Security\EngineeringWorkspaceContentContract;
use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureOwnerDiscoveryService;

final class EngineeringWorkspaceCoverageService
{
    private const ENGINEERING_ROOT = APP_ROOT . '/engineering';
    private const VIEWER_ROUTE = '/apps/studio/engineering-workspaces';

    /**
     * @return array<string,mixed>
     */
    public static function buildCoverage(): array
    {
        $registeredKeys = EngineeringWorkspaceContentContract::supportedWorkspaceKeys();

        // Phase 1: owner catalogue
        $owners = [];
        $catalogueOk = true;
        try {
            $ownerCatalogue = OwnerStructureOwnerDiscoveryService::discover();
            foreach ($ownerCatalogue as $owner) {
                $key = (string)($owner['owner_key'] ?? '');
                if ($key !== '') {
                    $owners[$key] = $owner;
                }
            }
        } catch (\Throwable $e) {
            error_log('EngineeringWorkspaceCoverage: owner catalogue unavailable: ' . $e->getMessage());
            $catalogueOk = false;
        }

        // Phase 2: controlled engineering/ directory scan
        $scanOk = true;
        $discoveredDirs = [];
        try {
            $discoveredDirs = self::scanEngineeringRoot();
        } catch (\Throwable $e) {
            error_log('EngineeringWorkspaceCoverage: engineering-root scan failed: ' . $e->getMessage());
            $scanOk = false;
        }

        // Phase 3: classify owners
        $ownerMatched = [];
        $unmappedOwners = [];
        foreach ($owners as $key => $owner) {
            if (in_array($key, $registeredKeys, true)) {
                $ownerMatched[$key] = $owner;
            } else {
                $unmappedOwners[$key] = $owner;
            }
        }

        // Phase 4: registered-only workspaces (no owner catalogue entry)
        $registeredOnly = [];
        foreach ($registeredKeys as $key) {
            if (!isset($ownerMatched[$key])) {
                $registeredOnly[$key] = null;
            }
        }

        // Phase 5: deduplicate discovered directories against registered workspace keys
        $unregisteredDirs = [];
        foreach ($discoveredDirs as $dirKey) {
            if (!in_array($dirKey, $registeredKeys, true)) {
                $unregisteredDirs[] = $dirKey;
            }
        }

        // Phase 6: build rows
        $rows = [];

        foreach ($ownerMatched as $key => $ownerData) {
            $rows[] = self::safeEvaluateRow($key, $ownerData, 'exact_owner_mapping');
        }

        foreach ($registeredOnly as $key => $ownerData) {
            $rows[] = self::safeEvaluateRow($key, $ownerData, 'registered_workspace');
        }

        foreach ($unregisteredDirs as $dirKey) {
            $rows[] = self::buildDiscoveredRow($dirKey);
        }

        foreach ($unmappedOwners as $key => $ownerData) {
            $rows[] = self::buildCoverageDecisionRow($key, $ownerData);
        }

        // Phase 7: compute grouped summaries
        $ownerScanned = count($owners);
        $ownerMapped = count($ownerMatched);
        $ownerCoverageDecision = count($unmappedOwners);
        $ownerFailed = 0;

        $wsRegistered = count($ownerMatched) + count($registeredOnly);
        $wsDiscovered = count($unregisteredDirs);
        $wsLinkedValid = 0;
        $wsInitRequired = 0;
        $wsContractRepair = 0;
        $wsMappingReview = 0;
        $wsResolutionFailed = 0;

        foreach ($rows as $row) {
            $state = (string)($row['state'] ?? '');
            switch ($state) {
                case 'linked_valid':
                    $wsLinkedValid++;
                    break;
                case 'initialization_required':
                    $wsInitRequired++;
                    break;
                case 'contract_repair_required':
                    $wsContractRepair++;
                    break;
                case 'mapping_review_required':
                    $wsMappingReview++;
                    break;
                case 'resolution_failed':
                    $ownerFailed++;
                    $wsResolutionFailed++;
                    break;
            }
        }

        $ownerSummary = [
            'contexts_scanned' => $ownerScanned,
            'exact_workspace_mappings' => $ownerMapped,
            'coverage_decision_required' => $ownerCoverageDecision,
            'explicitly_excluded' => 0,
            'resolution_failed' => $ownerFailed,
        ];

        $workspaceSummary = [
            'registered_workspaces' => $wsRegistered,
            'discovered_unregistered_workspaces' => $wsDiscovered,
            'linked_valid' => $wsLinkedValid,
            'initialization_required' => $wsInitRequired,
            'contract_repair_required' => $wsContractRepair,
            'mapping_review_required' => $wsMappingReview,
            'resolution_failed' => $wsResolutionFailed,
        ];

        return [
            'owner_summary' => $ownerSummary,
            'workspace_summary' => $workspaceSummary,
            'rows' => $rows,
            'scan_state' => $scanOk ? 'ok' : 'unavailable',
            'catalogue_state' => $catalogueOk ? 'ok' : 'unavailable',
        ];
    }

    /**
     * @return string[] Relative workspace keys found by scanning engineering/
     */
    private static function scanEngineeringRoot(): array
    {
        $root = realpath(self::ENGINEERING_ROOT);
        if ($root === false) {
            return [];
        }

        $discovered = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $path => $info) {
            if (!$info->isDir()) {
                continue;
            }

            $relPath = str_replace('\\', '/', substr($path, strlen($root) + 1));

            if (self::isExcludedScanDir($relPath)) {
                continue;
            }

            if (str_contains($relPath, '..')) {
                continue;
            }

            $realDir = realpath($path);
            if ($realDir === false || !str_starts_with($realDir, $root)) {
                continue;
            }

            $hasDoc = false;
            foreach (['overview.md', 'work.md', 'rules.md', 'decisions.md'] as $fn) {
                if (is_file($path . '/' . $fn)) {
                    $hasDoc = true;
                    break;
                }
            }

            if ($hasDoc) {
                $discovered[] = $relPath;
            }
        }

        return $discovered;
    }

    private static function isExcludedScanDir(string $relPath): bool
    {
        $segments = explode('/', $relPath);
        foreach ($segments as $seg) {
            if ($seg !== '' && $seg[0] === '_') {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array<string,mixed>
     */
    private static function safeEvaluateRow(string $workspaceKey, ?array $ownerData, string $evidenceType): array
    {
        try {
            return self::evaluateWorkspaceRow($workspaceKey, $ownerData, $evidenceType);
        } catch (\Throwable $e) {
            error_log(
                'EngineeringWorkspaceCoverage row failed [' . $workspaceKey . ']: '
                . get_class($e) . ': ' . $e->getMessage()
            );

            return [
                'row_kind' => $ownerData !== null ? 'owner-backed-workspace' : 'registered-only-workspace',
                'context_key' => $workspaceKey,
                'context_label' => EngineeringWorkspaceContentContract::displayWorkspaceName($workspaceKey),
                'evidence_type' => $evidenceType,
                'workspace_key' => $workspaceKey,
                'workspace_label' => EngineeringWorkspaceContentContract::displayWorkspaceName($workspaceKey),
                'documents' => [],
                'provisioning_readiness' => 'Investigate failure',
                'state' => 'resolution_failed',
                'reason_code' => 'ROW_EXCEPTION',
                'readiness_fingerprint' => self::computeFingerprint($workspaceKey, []),
            ];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function evaluateWorkspaceRow(string $workspaceKey, ?array $ownerData, string $evidenceType): array
    {
        $contextKey = $workspaceKey;
        $contextLabel = $ownerData !== null
            ? (string)($ownerData['display_label'] ?? EngineeringWorkspaceContentContract::displayWorkspaceName($workspaceKey))
            : EngineeringWorkspaceContentContract::displayWorkspaceName($workspaceKey);
        $workspaceLabel = EngineeringWorkspaceContentContract::displayWorkspaceName($workspaceKey);

        $rowKind = $ownerData !== null ? 'owner-backed-workspace' : 'registered-only-workspace';

        $documents = [];
        $missingCount = 0;
        $invalidCount = 0;
        $missingNames = [];

        foreach (EngineeringWorkspaceContentContract::allowedDocumentKeys() as $documentKey) {
            $doc = self::evaluateDocumentWithReadiness($workspaceKey, $documentKey);
            $documents[] = $doc;

            switch ($doc['status'] ?? '') {
                case 'missing':
                    $missingCount++;
                    $missingNames[] = EngineeringWorkspaceContentContract::documentLabel($documentKey) ?? $documentKey;
                    break;
                case 'contract_invalid':
                    $invalidCount++;
                    break;
            }
        }

        // Determine state and provisioning readiness
        if ($invalidCount > 0) {
            $state = 'contract_repair_required';
            $readiness = 'Review contract repair';
        } elseif ($missingCount > 0) {
            $state = 'initialization_required';
            $docList = implode(', ', $missingNames);
            $readiness = 'Initialize ' . $docList . ' from template';
            if ($missingCount === 1) {
                $readiness = 'Initialize ' . $missingNames[0] . ' from template';
            }
        } else {
            $state = 'linked_valid';
            $readiness = 'Preserve';
        }

        return [
            'row_kind' => $rowKind,
            'context_key' => $contextKey,
            'context_label' => $contextLabel,
            'evidence_type' => $evidenceType,
            'workspace_key' => $workspaceKey,
            'workspace_label' => $workspaceLabel,
            'documents' => $documents,
            'provisioning_readiness' => $readiness,
            'state' => $state,
            'reason_code' => '',
            'readiness_fingerprint' => self::computeFingerprint($workspaceKey, $documents),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function buildDiscoveredRow(string $dirKey): array
    {
        $dirPath = self::ENGINEERING_ROOT . '/' . $dirKey;

        $documents = [];
        foreach (EngineeringWorkspaceContentContract::allowedDocumentKeys() as $documentKey) {
            $filename = EngineeringWorkspaceContentContract::canonicalFilename($documentKey);
            $label = EngineeringWorkspaceContentContract::documentLabel($documentKey) ?? ucfirst($documentKey);
            $path = $dirPath . '/' . $filename;

            if (is_file($path) && is_readable($path)) {
                $documents[] = [
                    'document_key' => $documentKey,
                    'label' => $label,
                    'status' => 'valid',
                    'status_label' => 'Valid',
                    'url' => '',
                    'provisioning_readiness' => 'preserve',
                ];
            } else {
                $documents[] = [
                    'document_key' => $documentKey,
                    'label' => $label,
                    'status' => 'missing',
                    'status_label' => 'Missing',
                    'url' => '',
                    'provisioning_readiness' => 'initialize_from_template',
                ];
            }
        }

        return [
            'row_kind' => 'discovered-unregistered-workspace',
            'context_key' => '',
            'context_label' => 'engineering/' . $dirKey,
            'evidence_type' => 'discovered_workspace_directory',
            'workspace_key' => $dirKey,
            'workspace_label' => 'Unregistered',
            'documents' => $documents,
            'provisioning_readiness' => 'Review registration or archive',
            'state' => 'unregistered_workspace_discovered',
            'reason_code' => '',
            'readiness_fingerprint' => self::computeFingerprint($dirKey, $documents),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function buildCoverageDecisionRow(string $ownerKey, array $ownerData): array
    {
        $label = (string)($ownerData['display_label'] ?? $ownerKey);

        return [
            'row_kind' => 'coverage-decision-owner-context',
            'context_key' => $ownerKey,
            'context_label' => $label,
            'evidence_type' => 'canonical_owner_catalogue',
            'workspace_key' => '',
            'workspace_label' => '',
            'documents' => [],
            'provisioning_readiness' => 'Coverage decision required',
            'state' => 'coverage_decision_required',
            'reason_code' => '',
            'readiness_fingerprint' => self::computeFingerprint('coverage:' . $ownerKey, []),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function evaluateDocumentWithReadiness(string $workspaceKey, string $documentKey): array
    {
        $label = EngineeringWorkspaceContentContract::documentLabel($documentKey) ?? ucfirst($documentKey);

        try {
            $path = EngineeringWorkspaceContentContract::documentPath($workspaceKey, $documentKey);
            if ($path === null || !is_file($path) || !is_readable($path)) {
                return [
                    'document_key' => $documentKey,
                    'label' => $label,
                    'status' => 'missing',
                    'status_label' => 'Missing',
                    'url' => '',
                    'provisioning_readiness' => 'initialize_from_template',
                ];
            }

            $content = file_get_contents($path);
            if (!is_string($content)) {
                return [
                    'document_key' => $documentKey,
                    'label' => $label,
                    'status' => 'unavailable',
                    'status_label' => 'Unavailable',
                    'url' => '',
                    'provisioning_readiness' => 'investigate_failure',
                ];
            }

            $validation = EngineeringWorkspaceContentContract::validateDocumentContent($documentKey, $content);
            if (empty($validation['ok'])) {
                return [
                    'document_key' => $documentKey,
                    'label' => $label,
                    'status' => 'contract_invalid',
                    'status_label' => 'Needs repair',
                    'url' => '',
                    'provisioning_readiness' => 'review_contract_repair',
                ];
            }

            return [
                'document_key' => $documentKey,
                'label' => $label,
                'status' => 'valid',
                'status_label' => 'Valid',
                'url' => self::viewerUrl($workspaceKey, $documentKey),
                'provisioning_readiness' => 'preserve',
            ];
        } catch (\Throwable $e) {
            error_log(
                'EngineeringWorkspaceCoverage document failed [' . $workspaceKey . '/' . $documentKey . ']: '
                . $e->getMessage()
            );

            return [
                'document_key' => $documentKey,
                'label' => $label,
                'status' => 'unavailable',
                'status_label' => 'Unavailable',
                'url' => '',
                'provisioning_readiness' => 'investigate_failure',
            ];
        }
    }

    private static function computeFingerprint(string $seed, array $documents): string
    {
        $state = $seed;
        foreach ($documents as $doc) {
            $state .= '|' . ($doc['document_key'] ?? '')
                . ':' . ($doc['status'] ?? '')
                . ':' . ($doc['provisioning_readiness'] ?? '');
        }
        return hash('sha256', $state);
    }

    private static function viewerUrl(string $workspaceKey, string $documentKey): string
    {
        return self::VIEWER_ROUTE
            . '?workspace_key=' . rawurlencode($workspaceKey)
            . '&document=' . rawurlencode($documentKey);
    }
}
