<?php
declare(strict_types=1);

namespace Platform\Recovery;

use DateTimeImmutable;
use Throwable;

final class RecoveryPointMetadataValidator
{
    public const SCHEMA_VERSION = 'susankhya.recovery-point.v1';

    private const ALLOWED_REASONS = ['update_apply', 'repair', 'installer_upgrade', 'manual'];

    /**
     * @param array<string,mixed> $metadata
     * @return array{ok:bool,errors:array<int,string>,warnings:array<int,string>}
     */
    public static function validate(array $metadata): array
    {
        $errors = [];
        $warnings = [];

        self::requiredString($metadata, 'schema_version', $errors);
        if (($metadata['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            $errors[] = 'schema_version must equal ' . self::SCHEMA_VERSION . '.';
        }
        self::requiredString($metadata, 'recovery_point_id', $errors);
        self::requiredString($metadata, 'created_by', $errors);
        self::validTimestamp($metadata, 'created_at', $errors);

        $reason = (string)($metadata['reason'] ?? '');
        if (!in_array($reason, self::ALLOWED_REASONS, true)) {
            $errors[] = 'reason is invalid.';
        }

        $installation = self::requiredArray($metadata, 'installation', $errors);
        if ($installation !== null) {
            foreach (['product_id', 'release_version', 'build_id'] as $field) {
                self::requiredString($installation, $field, $errors, 'installation.');
            }
            foreach (['app_manifest_checksums', 'module_manifest_checksums', 'schema_state', 'migration_state'] as $field) {
                if (!is_array($installation[$field] ?? null)) {
                    $errors[] = 'installation.' . $field . ' must be an array.';
                }
            }
        }

        $database = self::requiredArray($metadata, 'database', $errors);
        if ($database !== null) {
            self::requiredString($database, 'provider', $errors, 'database.');
            self::requiredString($database, 'provider_version', $errors, 'database.');
            self::databaseIdentity($database['identity'] ?? null, $errors);
            self::payload($database['payload'] ?? null, 'database.payload.', $errors);
        }

        $filesystem = self::requiredArray($metadata, 'filesystem', $errors);
        if ($filesystem !== null) {
            self::requiredString($filesystem, 'provider', $errors, 'filesystem.');
            self::payload($filesystem['payload'] ?? null, 'filesystem.payload.', $errors);
            self::sha256($filesystem['preservation_inventory_sha256'] ?? null, 'filesystem.preservation_inventory_sha256', $errors);
        }

        self::findSecrets($metadata, '', $errors);

        return [
            'ok' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'warnings' => $warnings,
        ];
    }

    /** @param array<string,mixed> $data @param array<int,string> $errors */
    private static function requiredString(array $data, string $field, array &$errors, string $prefix = ''): void
    {
        if (!is_string($data[$field] ?? null) || trim((string)$data[$field]) === '') {
            $errors[] = $prefix . $field . ' is required.';
        }
    }

    /** @param array<string,mixed> $data @param array<int,string> $errors */
    private static function validTimestamp(array $data, string $field, array &$errors): void
    {
        $value = $data[$field] ?? null;
        if (!is_string($value) || trim($value) === '') {
            $errors[] = $field . ' is required.';
            return;
        }

        try {
            new DateTimeImmutable($value);
        } catch (Throwable) {
            $errors[] = $field . ' must be a valid timestamp.';
        }
    }

    /**
     * @param array<string,mixed> $data
     * @param array<int,string> $errors
     * @return array<string,mixed>|null
     */
    private static function requiredArray(array $data, string $field, array &$errors): ?array
    {
        $value = $data[$field] ?? null;
        if (!is_array($value)) {
            $errors[] = $field . ' must be an object.';
            return null;
        }

        return $value;
    }

    /** @param array<int,string> $errors */
    private static function databaseIdentity(mixed $identity, array &$errors): void
    {
        if (!is_array($identity)) {
            $errors[] = 'database.identity must be an object.';
            return;
        }

        self::requiredString($identity, 'host_label', $errors, 'database.identity.');
        self::requiredString($identity, 'database', $errors, 'database.identity.');
        $port = $identity['port'] ?? null;
        if (!is_int($port) || $port < 1 || $port > 65535) {
            $errors[] = 'database.identity.port must be an integer between 1 and 65535.';
        }
    }

    /** @param array<int,string> $errors */
    private static function payload(mixed $payload, string $prefix, array &$errors): void
    {
        if (!is_array($payload)) {
            $errors[] = rtrim($prefix, '.') . ' must be an object.';
            return;
        }

        $path = $payload['path'] ?? null;
        if (!is_string($path) || trim($path) === '' || str_starts_with($path, '/') || str_contains($path, '..')) {
            $errors[] = $prefix . 'path must be a contained relative path.';
        }
        self::sha256($payload['sha256'] ?? null, $prefix . 'sha256', $errors);
        $size = $payload['size_bytes'] ?? null;
        if (!is_int($size) || $size < 0) {
            $errors[] = $prefix . 'size_bytes must be a non-negative integer.';
        }
    }

    /** @param array<int,string> $errors */
    private static function sha256(mixed $value, string $field, array &$errors): void
    {
        if (!is_string($value) || preg_match('/^[a-f0-9]{64}$/', $value) !== 1) {
            $errors[] = $field . ' must be a lowercase SHA-256 hash.';
        }
    }

    /** @param array<int,string> $errors */
    private static function findSecrets(mixed $value, string $path, array &$errors): void
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $keyName = (string)$key;
                $itemPath = $path === '' ? $keyName : $path . '.' . $keyName;
                if (preg_match('/(^|[_\-.])(password|pass|token|secret|private[_-]?key)([_\-.]|$)/i', $keyName) === 1) {
                    $errors[] = 'Secret-looking metadata key is forbidden: ' . $itemPath . '.';
                }
                self::findSecrets($item, $itemPath, $errors);
            }
            return;
        }

        if (is_string($value) && preg_match('/(?:[a-z][a-z0-9+.-]*:\/\/[^\s\/@:]+:[^\s\/@]+@|(?:password|pass|token|secret|private[_-]?key)\s*[=:])/i', $value) === 1) {
            $errors[] = 'Secret-looking metadata value is forbidden at ' . ($path !== '' ? $path : 'root') . '.';
        }
    }
}