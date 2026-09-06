<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

use Platform\Security\EngineeringWorkspaceContentContract;
use Platform\Security\EngineeringWorkspaceResolver;

final class EngineeringWorkspaceArtifactService
{
    private const DOCUMENT_KEYS = ['overview', 'work', 'rules', 'decisions'];
    private const STATE_VALID = 'linked_valid';
    private const STATE_MISSING = 'initialization_required';
    private const STATE_REPAIR = 'contract_repair_required';
    private const STATE_MIGRATION = 'migration_required';
    private const STATE_CONFLICT = 'conflict_review_required';

    private const STATUS_LABELS = [
        self::STATE_VALID => 'Linked valid',
        self::STATE_MISSING => 'Initialization required',
        self::STATE_REPAIR => 'Contract repair required',
        self::STATE_MIGRATION => 'Migration required',
        self::STATE_CONFLICT => 'Conflict review required',
    ];

    public static function resolveArtifacts(string $ownerKey): array
    {
        try {
            $workspaceKey = EngineeringWorkspaceResolver::ownerKeyToWorkspaceKey($ownerKey);
        } catch (\Throwable) {
            return self::failedState($ownerKey, 'workspace key resolution failed');
        }

        return self::resolveWorkspaceArtifacts($workspaceKey, null);
    }

    /**
     * @return array<string,mixed>
     */
    public static function failedState(string $workspaceKey, string $message = 'Engineering workspace artifact resolution failed.'): array
    {
        return [
            'workspace_key' => $workspaceKey,
            'canonical_relative_path' => null,
            'is_supported_workspace' => false,
            'documents' => [],
            'valid_count' => 0,
            'missing_count' => 0,
            'invalid_count' => 0,
            'migration_count' => 0,
            'conflict_count' => 0,
            'overall_state' => 'Artifact resolution failed',
            'error' => $message,
        ];
    }

    /**
     * @param array<string,string|null>|null $documentPathOverrides
     * @return array<string,mixed>
     */
    public static function resolveWorkspaceArtifacts(string $workspaceKey, ?array $documentPathOverrides = null): array
    {
        try {
            $isSupported = EngineeringWorkspaceContentContract::isSupportedWorkspaceKey($workspaceKey);
        } catch (\Throwable) {
            return self::failedState($workspaceKey, 'workspace support resolution failed');
        }

        if (!$isSupported) {
            return [
                'workspace_key' => $workspaceKey,
                'canonical_relative_path' => null,
                'is_supported_workspace' => false,
                'documents' => [],
                'valid_count' => 0,
                'missing_count' => 0,
                'invalid_count' => 0,
                'migration_count' => 0,
                'conflict_count' => 0,
                'overall_state' => 'Not applicable',
            ];
        }

        $documents = [];
        $validCount = 0;
        $missingCount = 0;
        $invalidCount = 0;
        $migrationCount = 0;
        $conflictCount = 0;

        foreach (self::DOCUMENT_KEYS as $docKey) {
            $docPath = $documentPathOverrides !== null
                ? ($documentPathOverrides[$docKey] ?? null)
                : EngineeringWorkspaceContentContract::documentPath($workspaceKey, $docKey);
            $fileName = EngineeringWorkspaceContentContract::canonicalFilename($docKey) ?? $docKey . '.md';
            $label = EngineeringWorkspaceContentContract::documentLabel($docKey) ?? ucfirst($docKey);

            $state = self::determineDocumentState($docPath, $docKey);

            $documents[] = [
                'document_key' => $docKey,
                'document_label' => $label,
                'canonical_filename' => $fileName,
                'canonical_path' => $docPath !== null ? self::relativePath($docPath) : null,
                'template_source' => EngineeringWorkspaceContentContract::templateSource($docKey),
                'exists' => $state !== self::STATE_MISSING,
                'state' => $state,
                'status' => self::statusLabel($state),
                'reason_code' => self::reasonCode($state),
            ];

            if ($state === self::STATE_VALID) {
                $validCount++;
            } elseif ($state === self::STATE_MISSING) {
                $missingCount++;
            } elseif ($state === self::STATE_MIGRATION) {
                $migrationCount++;
            } elseif ($state === self::STATE_CONFLICT) {
                $conflictCount++;
            } else {
                $invalidCount++;
            }
        }

        $overallState = self::overallState($validCount, $missingCount, $invalidCount, $migrationCount, $conflictCount);

        return [
            'workspace_key' => $workspaceKey,
            'canonical_relative_path' => 'engineering/' . $workspaceKey,
            'is_supported_workspace' => true,
            'documents' => $documents,
            'valid_count' => $validCount,
            'missing_count' => $missingCount,
            'invalid_count' => $invalidCount,
            'migration_count' => $migrationCount,
            'conflict_count' => $conflictCount,
            'overall_state' => $overallState,
        ];
    }

    private static function determineDocumentState(?string $docPath, string $docKey): string
    {
        if ($docPath === null || !is_file($docPath) || !is_readable($docPath)) {
            return self::STATE_MISSING;
        }

        $content = @file_get_contents($docPath);
        if (!is_string($content)) {
            return self::STATE_REPAIR;
        }

        try {
            $validation = EngineeringWorkspaceContentContract::validateDocumentContent($docKey, $content);
        } catch (\Throwable) {
            return self::STATE_REPAIR;
        }

        return !empty($validation['ok']) ? self::STATE_VALID : self::STATE_REPAIR;
    }

    private static function overallState(int $validCount, int $missingCount, int $invalidCount, int $migrationCount = 0, int $conflictCount = 0): string
    {
        if ($conflictCount > 0) {
            return 'Conflict review required';
        }
        if ($migrationCount > 0) {
            return 'Migration required';
        }
        if ($validCount === 4) {
            return 'Linked valid';
        }
        if ($validCount > 0 && $invalidCount > 0) {
            return 'Contract repair required';
        }
        if ($validCount === 0 && $missingCount === 4) {
            return 'Initialization required';
        }
        if ($validCount > 0 && $missingCount > 0) {
            return 'Initialization required';
        }
        if ($invalidCount > 0) {
            return 'Contract repair required';
        }
        return 'Initialization required';
    }

    private static function statusLabel(string $state): string
    {
        return self::STATUS_LABELS[$state] ?? 'Not applicable';
    }

    private static function reasonCode(string $state): string
    {
        return match ($state) {
            self::STATE_VALID => 'canonical_document_valid',
            self::STATE_MISSING => 'canonical_document_missing',
            self::STATE_REPAIR => 'canonical_document_contract_failed',
            self::STATE_MIGRATION => 'recognized_legacy_document_candidate',
            self::STATE_CONFLICT => 'duplicate_or_conflicting_workspace_document',
            default => 'not_applicable',
        };
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
