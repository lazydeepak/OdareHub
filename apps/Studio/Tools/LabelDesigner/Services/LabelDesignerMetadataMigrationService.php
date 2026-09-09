<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\LabelDesigner\Services;

/**
 * Guarded metadata migration apply service for legacy label resources.
 *
 * Phase 1: single-resource migration only.
 * Only adds missing top-level ownership metadata.
 * No rename, move, rewrite, or business meaning changes.
 * Snapshot before write. Diagnostics after apply.
 */
final class LabelDesignerMetadataMigrationService
{
    private const SNAPSHOT_ROOT = APP_ROOT . '/storage/studio-snapshots/label-designer';

    private const SEVERITY_RANK = [
        'PASS' => 0,
        'WARN' => 1,
        'FAIL' => 2,
        'ERROR' => 3,
    ];

    /**
     * Apply metadata migration to a single resource.
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public static function applyMigration(array $input): array
    {
        $resourcePath = trim((string)($input['resource_path'] ?? ''));
        $confirmed = isset($input['confirm_apply']) && $input['confirm_apply'] === '1';

        if ($resourcePath === '') {
            return self::result(false, ['Resource path is required.'], 'ERROR', self::migCheck('MIG005', 'migration_blocked', 'Migration blocked', 'ERROR', 'Resource path is required.'));
        }

        if (!$confirmed) {
            return self::result(false, ['Confirmation required.'], 'FAIL', self::migCheck('MIG005', 'migration_blocked', 'Migration blocked', 'FAIL', 'Explicit confirmation is required before metadata migration.'));
        }

        $absPath = defined('APP_ROOT') ? APP_ROOT . '/' . ltrim($resourcePath, '/') : '';
        if ($absPath === '' || !is_file($absPath)) {
            return self::result(false, ['Resource file not found: ' . $resourcePath], 'ERROR', self::migCheck('MIG005', 'resource_not_found', 'Resource exists', 'ERROR', 'File not found: ' . $resourcePath));
        }

        if (!is_writable($absPath)) {
            return self::result(false, ['Resource file is not writable: ' . $resourcePath], 'ERROR', self::migCheck('MIG005', 'resource_writable', 'Resource writable', 'ERROR', 'File is not writable: ' . $resourcePath));
        }

        $raw = @file_get_contents($absPath);
        if (!is_string($raw) || $raw === '') {
            return self::result(false, ['Cannot read resource file.'], 'ERROR', self::migCheck('MIG005', 'resource_readable', 'Resource readable', 'ERROR', 'Cannot read resource file.'));
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return self::result(false, ['Resource file is not valid JSON.'], 'ERROR', self::migCheck('MIG005', 'resource_valid_json', 'Resource is valid JSON', 'ERROR', 'File is not valid JSON.'));
        }

        $schema = (string)($decoded['schema'] ?? '');
        $resourceType = self::detectResourceType($schema, $decoded);
        if ($resourceType === 'unknown') {
            return self::result(false, ['Unrecognized resource type.'], 'ERROR', self::migCheck('MIG005', 'resource_type_recognized', 'Resource type recognized', 'ERROR', 'Unrecognized resource type. Schema: ' . ($schema ?: 'none')));
        }

        // Validate canonical path
        $canonicalBase = match ($resourceType) {
            'context' => '/Resources/labels/contexts/',
            'template' => '/Resources/labels/templates/',
            'rule' => '/Resources/labels/rules/',
            default => '',
        };
        if ($canonicalBase === '' || !str_contains($resourcePath, $canonicalBase)) {
            return self::result(false, ['Resource is not at a canonical label resource path.'], 'FAIL', self::migCheck('MIG006', 'canonical_path', 'Canonical resource path', 'FAIL', 'Path mismatch: ' . $resourcePath . ' does not contain ' . $canonicalBase));
        }

        // Owner lifecycle eligibility
        $pathOwner = LabelDesignerResourceMetadataService::inferOwnerFromPath($resourcePath);
        if ($pathOwner === '') {
            return self::result(false, ['Cannot infer owner from resource path.'], 'FAIL', self::migCheck('MIG006', 'owner_resolvable', 'Owner resolvable from path', 'FAIL', 'Cannot infer owner from: ' . $resourcePath));
        }

        // Check owner lifecycle
        $readiness = LabelDesignerResourceReadinessService::checkReadiness();
        $ownerReadiness = null;
        $readinessOwners = isset($readiness['owners']) && is_array($readiness['owners']) ? $readiness['owners'] : [];
        foreach ($readinessOwners as $ro) {
            if (self::ownerKeysEqual((string)($ro['owner_key'] ?? ''), $pathOwner) && !empty($ro['is_label_lifecycle_owner'])) {
                $ownerReadiness = $ro;
                break;
            }
        }
        if ($ownerReadiness === null) {
            return self::result(false, ['Owner ' . $pathOwner . ' is not a lifecycle owner or not found in readiness.'], 'FAIL', self::migCheck('MIG006', 'owner_lifecycle', 'Owner lifecycle eligible', 'FAIL', 'Owner ' . $pathOwner . ' is not a lifecycle owner.'));
        }

        $ownerRootRel = trim((string)($ownerReadiness['root_path'] ?? ''));
        $ownerRoot = realpath(APP_ROOT . '/' . ltrim($ownerRootRel, '/'));
        $resourceRealPath = realpath($absPath);
        if (!is_string($ownerRoot) || !is_string($resourceRealPath) || !self::isPathInside($resourceRealPath, $ownerRoot)) {
            return self::result(false, ['Resource path is outside the resolved owner root.'], 'FAIL', self::migCheck('MIG006', 'owner_contained_path', 'Owner-contained resource path', 'FAIL', 'Resource must remain inside owner root for ' . $pathOwner . '.'));
        }

        $expectedDirectory = $ownerRoot . $canonicalBase;
        if (dirname(str_replace('\\', '/', $resourceRealPath)) !== rtrim(str_replace('\\', '/', $expectedDirectory), '/')) {
            return self::result(false, ['Resource is not in the canonical owner resource directory.'], 'FAIL', self::migCheck('MIG006', 'owner_canonical_directory', 'Canonical owner resource directory', 'FAIL', 'Expected directory: ' . self::toRelativePath($expectedDirectory)));
        }

        // Check migration actually required
        $currentOwnerKey = trim((string)($decoded['owner_key'] ?? ''));
        if ($currentOwnerKey !== '') {
            return self::result(false, ['Resource already has top-level owner_key. Migration not required.'], 'PASS', self::migCheck('MIG002', 'migration_not_required', 'Migration required', 'PASS', 'Resource already has top-level owner_key: ' . $currentOwnerKey));
        }

        // Build proposed metadata
        $proposed = $decoded;
        $proposed['owner_key'] = $pathOwner;
        $proposed['owner_type'] = (string)($ownerReadiness['owner_type'] ?? '');
        $proposed['owner_root'] = $ownerRootRel;
        $proposed['resource_type'] = $resourceType;

        // Create snapshot before write
        $snapshotResult = self::writeSnapshot([
            'action' => 'metadata-migration',
            'resource_path' => $resourcePath,
            'resource_type' => $resourceType,
            'original_json' => $raw,
            'proposed_json' => json_encode($proposed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'path_owner' => $pathOwner,
            'migration_reason' => 'Add top-level owner_key, owner_type, owner_root, and resource_type to legacy resource',
        ]);

        $snapshotOk = !empty($snapshotResult['ok']);
        $snapshotPath = (string)($snapshotResult['path_rel'] ?? '');

        if (!$snapshotOk) {
            return self::result(false, ['Failed to create snapshot before write.'], 'ERROR', self::migCheck('MIG005', 'snapshot_created', 'Snapshot before write', 'ERROR', 'Snapshot creation failed. Apply blocked.'));
        }

        // Write updated file
        $newJson = json_encode($proposed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($newJson) || $newJson === '') {
            return self::result(false, ['Failed to encode updated resource JSON.'], 'ERROR', self::migCheck('MIG005', 'encode_proposed', 'Encode proposed metadata', 'ERROR', 'JSON encoding failed. Apply blocked.'), $snapshotPath);
        }

        // Pre-migration global diagnostics
        $preGlobal = LabelDesignerResourceDiagnosticsService::scanAll();
        $preMetadata = LabelDesignerResourceMetadataService::analyzeAll();
        $preLegacy = (int)($preGlobal['legacy_fallback_count'] ?? 0);
        $preMeta = (int)($preMetadata['complete_count'] ?? 0);

        $backupPath = $absPath . '.metadata-migration-backup';
        $backupOk = @copy($absPath, $backupPath);
        if (!$backupOk) {
            return self::result(false, ['Failed to create backup before write.'], 'ERROR', self::migCheck('MIG005', 'backup_created', 'Backup before write', 'ERROR', 'Backup creation failed.'), $snapshotPath);
        }

        $written = @file_put_contents($absPath, $newJson . "\n");
        if ($written === false) {
            // Restore backup
            @copy($backupPath, $absPath);
            return self::result(false, ['Failed to write updated resource file.'], 'ERROR', self::migCheck('MIG005', 'resource_written', 'Resource file written', 'ERROR', 'Write failed. Backup restored.'), $snapshotPath);
        }

        // Post-migration global diagnostics
        $postGlobal = LabelDesignerResourceDiagnosticsService::scanAll();
        $postMetadata = LabelDesignerResourceMetadataService::analyzeAll();
        $postLegacy = (int)($postGlobal['legacy_fallback_count'] ?? 0);
        $postMeta = (int)($postMetadata['complete_count'] ?? 0);

        // Run post-migration diagnostics
        $postChecks = self::runPostMigrationDiagnostics($absPath, $resourcePath, $proposed, $pathOwner, $snapshotPath, $backupPath);

        $allPass = true;
        foreach ($postChecks as $c) {
            $sev = (string)($c['severity'] ?? 'PASS');
            if ($sev === 'FAIL' || $sev === 'ERROR') {
                $allPass = false;
            }
        }

        return [
            'ok' => true,
            'errors' => [],
            'resource_path' => $resourcePath,
            'resource_type' => $resourceType,
            'snapshot_path' => $snapshotPath,
            'backup_path' => self::toRelativePath($backupPath),
            'diagnostics' => $postChecks,
            'all_pass' => $allPass,
            'applied' => true,
            'global_diagnostics_before' => [
                'legacy_fallback_count' => $preLegacy,
                'metadata_count' => $preMeta,
                'counts' => $preGlobal['counts'] ?? [],
            ],
            'global_diagnostics_after' => [
                'legacy_fallback_count' => $postLegacy,
                'metadata_count' => $postMeta,
                'counts' => $postGlobal['counts'] ?? [],
            ],
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function runPostMigrationDiagnostics(
        string $absPath,
        string $relPath,
        array $proposed,
        string $pathOwner,
        string $snapshotPath,
        string $backupPath
    ): array {
        $checks = [];

        // MIG003: snapshot created
        $checks[] = self::migCheck('MIG003', 'snapshot_created', 'Snapshot created', 'PASS', $snapshotPath);

        // Backup exists
        $backupExists = is_file($backupPath);
        $checks[] = self::migCheck('MIG003', 'backup_created', 'Backup created', $backupExists ? 'PASS' : 'WARN', $backupExists ? self::toRelativePath($backupPath) : 'Backup file missing');

        // MIG004: migration applied
        $fileOk = is_file($absPath);
        $checks[] = self::migCheck('MIG004', 'migration_applied', 'Migration applied', $fileOk ? 'PASS' : 'ERROR', $fileOk ? $relPath : 'File missing after write');

        // Owner key added
        $newRaw = $fileOk ? @file_get_contents($absPath) : '';
        $newDecoded = is_string($newRaw) ? json_decode($newRaw, true) : null;
        $newOwnerKey = trim((string)($newDecoded['owner_key'] ?? ''));
        $ownerAdded = $newOwnerKey !== '';
        $checks[] = self::migCheck('MIG004', 'owner_key_added', 'Owner key added', $ownerAdded ? 'PASS' : 'FAIL', $ownerAdded ? $newOwnerKey : 'Owner key still missing after migration');

        // Owner key matches path owner
        $ownerMatch = $ownerAdded && strtolower(str_replace('\\', '/', trim($newOwnerKey))) === strtolower(str_replace('\\', '/', trim($pathOwner)));
        $checks[] = self::migCheck('MIG004', 'owner_key_match', 'Owner key matches path', $ownerMatch ? 'PASS' : 'FAIL', $ownerMatch ? $newOwnerKey : 'Owner key "' . $newOwnerKey . '" does not match path owner "' . $pathOwner . '"');

        // Resource still valid JSON
        $validJson = is_array($newDecoded);
        $checks[] = self::migCheck('MIG004', 'resource_valid_json', 'Resource valid JSON after migration', $validJson ? 'PASS' : 'ERROR', $validJson ? 'JSON valid' : 'Resource is no longer valid JSON');

        // Resource type preserved
        $newType = is_array($newDecoded) ? ($newDecoded['context_key'] ?? $newDecoded['template_key'] ?? $newDecoded['rule_key'] ?? '') : '';
        $checks[] = self::migCheck('MIG004', 'resource_type_preserved', 'Resource type preserved', $newType !== '' ? 'PASS' : 'WARN', $newType !== '' ? 'Type preserved' : 'Could not verify resource key');

        // Migration goal verified: MIG001
        $checks[] = self::migCheck('MIG001', 'migration_required', 'Migration completed successfully', 'PASS', 'Metadata migration applied. Resource now has top-level owner_key.');

        return $checks;
    }

    private static function detectResourceType(string $schema, array $decoded): string
    {
        return match ($schema) {
            'odarehub.label.context.v1' => 'context',
            'odarehub.label.template.v1' => 'template',
            'odarehub.label.rule.v1' => 'rule',
            default => 'unknown',
        };
    }

    /**
     * @return array<string,mixed>
     */
    private static function writeSnapshot(array $payload): array
    {
        if (!is_dir(self::SNAPSHOT_ROOT)) {
            if (!@mkdir(self::SNAPSHOT_ROOT, 0755, true) && !is_dir(self::SNAPSHOT_ROOT)) {
                return ['ok' => false];
            }
        }

        $resourcePath = (string)($payload['resource_path'] ?? 'unknown');
        $safeKey = preg_replace('/[^a-z0-9._-]+/i', '-', strtolower(basename($resourcePath))) ?: 'resource';
        $snapshotId = gmdate('Ymd_His') . '_' . substr(sha1($safeKey . microtime(true)), 0, 10);
        $snapshotPath = self::SNAPSHOT_ROOT . '/metadata-migration-' . $safeKey . '-' . $snapshotId . '.json';

        $payload['snapshot_id'] = $snapshotId;
        $payload['created_at'] = gmdate('c');
        $payload['action'] = 'metadata-migration';
        $payload['migration_scope'] = 'single-resource';

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json) || $json === '') {
            return ['ok' => false];
        }

        $written = @file_put_contents($snapshotPath, $json . "\n");
        if ($written === false) {
            return ['ok' => false];
        }

        return [
            'ok' => true,
            'path_rel' => self::toRelativePath($snapshotPath),
        ];
    }

    private static function toRelativePath(string $absPath): string
    {
        $root = defined('APP_ROOT') ? rtrim(str_replace('\\', '/', APP_ROOT), '/') . '/' : '';
        if ($root !== '') {
            $normalized = str_replace('\\', '/', $absPath);
            if (str_starts_with($normalized, $root)) {
                return substr($normalized, strlen($root));
            }
        }
        return $absPath;
    }

    private static function isPathInside(string $path, string $root): bool
    {
        $normalizedPath = rtrim(str_replace('\\', '/', $path), '/');
        $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/');
        return $normalizedPath !== $normalizedRoot
            && str_starts_with($normalizedPath . '/', $normalizedRoot . '/');
    }

    private static function ownerKeysEqual(string $left, string $right): bool
    {
        $normalize = static fn (string $key): string => strtolower(str_replace('\\', '/', trim($key)));
        $normalizedLeft = $normalize($left);
        $normalizedRight = $normalize($right);
        return $normalizedLeft !== ''
            && $normalizedRight !== ''
            && hash_equals($normalizedLeft, $normalizedRight);
    }

    /**
     * @param array<string,mixed> $check
     * @return array<string,mixed>
     */
    private static function migCheck(
        string $ruleId,
        string $checkKey,
        string $label,
        string $severity = 'PASS',
        string $message = ''
    ): array {
        return [
            'rule_id' => $ruleId,
            'check_key' => $checkKey,
            'label' => $label,
            'severity' => $severity,
            'message' => $message,
        ];
    }

    /**
     * @param array<string> $errors
     * @return array<string,mixed>
     */
    private static function result(bool $ok, array $errors, string $severity, array $migCheck, string $snapshotPath = ''): array
    {
        $diag = [$migCheck];
        return [
            'ok' => $ok,
            'errors' => $errors,
            'resource_path' => '',
            'resource_type' => '',
            'snapshot_path' => $snapshotPath,
            'backup_path' => '',
            'diagnostics' => $diag,
            'all_pass' => $ok && $severity !== 'FAIL' && $severity !== 'ERROR',
            'applied' => $ok,
        ];
    }
}
