<?php
declare(strict_types=1);

namespace Platform\Recovery;

final class RecoveryPointService
{
    public function __construct(
        private RecoveryProviderPreflightService $preflight,
        private MySqlDumpProvider $databaseProvider,
        private FilesystemArchiveProvider $filesystemProvider
    ) {}

    /** @return array<string,mixed> */
    public function preflight(): array
    {
        return $this->preflight->inspect();
    }

    /**
     * @param array<string,mixed> $config
     * @param array<string,mixed> $installation
     * @param array<int,string> $includeRoots
     * @param array<int,string> $excludePrefixes
     * @return array<string,mixed>
     */
    public function createRecoveryPoint(
        array $config,
        string $sourceRoot,
        string $targetDirectory,
        string $createdBy,
        string $reason,
        array $installation,
        array $includeRoots,
        array $excludePrefixes = []
    ): array {
        if (!in_array($reason, ['update_apply', 'repair', 'installer_upgrade', 'manual'], true)) {
            return $this->failure('Invalid recovery reason.');
        }
        if (trim($createdBy) === '') {
            return $this->failure('created_by is required.');
        }
        if (!is_dir($targetDirectory) || !is_writable($targetDirectory)) {
            return $this->failure('Target recovery directory is not writable.');
        }

        $database = $this->databaseProvider->createDump($config, $targetDirectory);
        if (($database['ok'] ?? false) !== true) {
            return $this->failure('Database dump failed.', ['database' => $database]);
        }

        $filesystem = $this->filesystemProvider->createArchive(
            $sourceRoot,
            rtrim($targetDirectory, '/') . '/filesystem.zip',
            $includeRoots,
            $excludePrefixes
        );
        if (($filesystem['ok'] ?? false) !== true) {
            return $this->failure('Filesystem archive failed.', ['filesystem' => $filesystem]);
        }

        $metadata = $this->metadata($createdBy, $reason, $installation, $database, $filesystem);
        $validation = RecoveryPointMetadataValidator::validate($metadata);
        if (($validation['ok'] ?? false) !== true) {
            return $this->failure('Recovery metadata validation failed.', ['validation' => $validation]);
        }

        $metadataPath = rtrim($targetDirectory, '/') . '/recovery-point.json';
        $encoded = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false || file_put_contents($metadataPath, $encoded . "\n") === false) {
            return $this->failure('Unable to write recovery metadata.');
        }

        return [
            'ok' => true,
            'errors' => [],
            'warnings' => [],
            'recovery_point_id' => $metadata['recovery_point_id'],
            'metadata_path' => $metadataPath,
            'metadata' => $metadata,
        ];
    }

    /** @return array<string,mixed> */
    public function verify(string $recoveryPointDirectory): array
    {
        return self::verifyDirectory($recoveryPointDirectory);
    }

    /**
     * Verify recovery metadata and both payloads without requiring provider
     * dependencies. This is the authoritative integrity check used by update
     * preview; it never creates, restores, or mutates a recovery point.
     *
     * @return array<string,mixed>
     */
    public static function verifyDirectory(string $recoveryPointDirectory): array
    {
        $metadataPath = rtrim($recoveryPointDirectory, '/') . '/recovery-point.json';
        if (!is_file($metadataPath)) {
            return self::failureResult('Recovery metadata file is missing.');
        }

        $metadata = json_decode((string)file_get_contents($metadataPath), true);
        if (!is_array($metadata)) {
            return self::failureResult('Recovery metadata JSON is invalid.');
        }

        $validation = RecoveryPointMetadataValidator::validate($metadata);
        if (($validation['ok'] ?? false) !== true) {
            return self::failureResult('Recovery metadata validation failed.', ['validation' => $validation]);
        }

        $database = self::payloadMatches($recoveryPointDirectory, $metadata['database']['payload'] ?? []);
        $filesystem = self::payloadMatches($recoveryPointDirectory, $metadata['filesystem']['payload'] ?? []);
        $errors = [];
        if (!$database['ok']) {
            $errors[] = 'Database payload does not match metadata.';
        }
        if (!$filesystem['ok']) {
            $errors[] = 'Filesystem payload does not match metadata.';
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'warnings' => [],
            'metadata' => $metadata,
            'payloads' => [
                'database' => $database,
                'filesystem' => $filesystem,
            ],
        ];
    }

    /**
     * Verify the complete update prerequisite: recovery-point integrity,
     * installed identity binding, and an independently recorded successful
     * restore rehearsal. Boolean claims are intentionally not accepted.
     *
     * @param array<string,mixed> $installedState
     * @return array<string,mixed>
     */
    public static function verifyUpdateEvidence(string $recoveryPointDirectory, array $installedState): array
    {
        $verified = self::verifyDirectory($recoveryPointDirectory);
        if (($verified['ok'] ?? false) !== true) {
            return self::failureResult('Recovery point integrity verification failed.', ['verification' => $verified]);
        }

        $metadata = is_array($verified['metadata'] ?? null) ? $verified['metadata'] : [];
        $installation = is_array($metadata['installation'] ?? null) ? $metadata['installation'] : [];
        $identityChecks = [
            ['installed product_id', $installedState['product_id'] ?? null, $installation['product_id'] ?? null],
            ['installed release_version', $installedState['release_version'] ?? ($installedState['product_version'] ?? null), $installation['release_version'] ?? null],
            ['installed build_id', $installedState['build_id'] ?? null, $installation['build_id'] ?? null],
        ];
        foreach ($identityChecks as [$label, $expected, $actual]) {
            if ($expected !== null && (string)$expected !== '' && (string)$expected !== (string)$actual) {
                return self::failureResult('Recovery point identity does not match ' . $label . '.', [
                    'expected' => (string)$expected,
                    'actual' => (string)$actual,
                ]);
            }
        }

        $rehearsalPath = rtrim($recoveryPointDirectory, '/') . '/rehearsal.json';
        if (!is_file($rehearsalPath)) {
            return self::failureResult('Authoritative restore-rehearsal evidence is missing.');
        }
        $rehearsal = json_decode((string)file_get_contents($rehearsalPath), true);
        if (!is_array($rehearsal)) {
            return self::failureResult('Restore-rehearsal evidence JSON is invalid.');
        }
        if (($rehearsal['schema_version'] ?? null) !== 'susankhya.rehearsal.v1') {
            return self::failureResult('Restore-rehearsal evidence schema is invalid.');
        }
        if (($rehearsal['recovery_point_id'] ?? null) !== ($metadata['recovery_point_id'] ?? null)) {
            return self::failureResult('Restore-rehearsal evidence is bound to a different recovery point.');
        }
        if (($rehearsal['ok'] ?? false) !== true || ($rehearsal['final_state'] ?? null) !== 'restored') {
            return self::failureResult('Restore-rehearsal evidence does not prove a restored final state.');
        }
        if (($rehearsal['database']['ok'] ?? false) !== true || ($rehearsal['filesystem']['ok'] ?? false) !== true) {
            return self::failureResult('Restore-rehearsal evidence is incomplete.');
        }

        return [
            'ok' => true,
            'errors' => [],
            'warnings' => [],
            'metadata' => $metadata,
            'rehearsal' => $rehearsal,
            'recovery_point_id' => (string)($metadata['recovery_point_id'] ?? ''),
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private static function payloadMatches(string $directory, array $payload): array
    {
        $path = trim(str_replace('\\', '/', (string)($payload['path'] ?? '')), '/');
        if ($path === '' || str_contains($path, '..')) {
            return ['ok' => false, 'error' => 'payload path is unsafe'];
        }

        $absolute = rtrim($directory, '/') . '/' . $path;
        if (!is_file($absolute)) {
            return ['ok' => false, 'error' => 'payload file is missing'];
        }

        return [
            'ok' => (hash_file('sha256', $absolute) ?: '') === (string)($payload['sha256'] ?? '')
                && (filesize($absolute) ?: 0) === (int)($payload['size_bytes'] ?? -1),
            'path' => $path,
            'sha256' => hash_file('sha256', $absolute) ?: '',
            'size_bytes' => filesize($absolute) ?: 0,
        ];
    }

    /**
     * @param array<string,mixed> $installation
     * @param array<string,mixed> $database
     * @param array<string,mixed> $filesystem
     * @return array<string,mixed>
     */
    private function metadata(string $createdBy, string $reason, array $installation, array $database, array $filesystem): array
    {
        return [
            'schema_version' => RecoveryPointMetadataValidator::SCHEMA_VERSION,
            'recovery_point_id' => 'rp-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(4)),
            'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'created_by' => $createdBy,
            'reason' => $reason,
            'installation' => [
                'product_id' => (string)($installation['product_id'] ?? 'susankhya-os'),
                'release_version' => (string)($installation['release_version'] ?? '0.0.0'),
                'build_id' => (string)($installation['build_id'] ?? 'unknown'),
                'app_manifest_checksums' => is_array($installation['app_manifest_checksums'] ?? null) ? $installation['app_manifest_checksums'] : [],
                'module_manifest_checksums' => is_array($installation['module_manifest_checksums'] ?? null) ? $installation['module_manifest_checksums'] : [],
                'schema_state' => is_array($installation['schema_state'] ?? null) ? $installation['schema_state'] : [],
                'migration_state' => is_array($installation['migration_state'] ?? null) ? $installation['migration_state'] : [],
            ],
            'database' => [
                'provider' => (string)($database['provider'] ?? 'mysqldump'),
                'provider_version' => (string)($database['provider_version'] ?? 'unknown'),
                'identity' => $database['identity'] ?? [],
                'payload' => $database['payload'] ?? [],
            ],
            'filesystem' => [
                'provider' => (string)($filesystem['provider'] ?? 'ziparchive'),
                'payload' => $filesystem['payload'] ?? [],
                'preservation_inventory_sha256' => (string)($filesystem['preservation_inventory_sha256'] ?? ''),
            ],
        ];
    }

    /**
     * @param array<string,mixed> $details
     * @return array<string,mixed>
     */
    private function failure(string $message, array $details = []): array
    {
        return self::failureResult($message, $details);
    }

    /** @param array<string,mixed> $details */
    private static function failureResult(string $message, array $details = []): array
    {
        return [
            'ok' => false,
            'errors' => [$message],
            'warnings' => [],
            'details' => $details,
        ];
    }
}
