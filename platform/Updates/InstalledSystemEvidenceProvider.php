<?php
declare(strict_types=1);

namespace Platform\Updates;

use Throwable;

/**
 * Read-only installed-state evidence collector for the update boundary.
 *
 * Repository manifests describe possible components; registry and ledger rows
 * describe the installed system. Missing authoritative sources remain errors.
 */
final class InstalledSystemEvidenceProvider
{
    /** @var callable */
    private $appReader;
    /** @var callable */
    private $moduleReader;
    /** @var callable */
    private $identityReader;
    /** @var callable */
    private $migrationReader;
    /** @var callable */
    private $schemaReader;

    public function __construct(
        callable $appReader,
        callable $moduleReader,
        callable $identityReader,
        callable $migrationReader,
        callable $schemaReader
    ) {
        $this->appReader = $appReader;
        $this->moduleReader = $moduleReader;
        $this->identityReader = $identityReader;
        $this->migrationReader = $migrationReader;
        $this->schemaReader = $schemaReader;
    }

    public static function fromRuntime(): self
    {
        return new self(
            static fn(): array => (new \App\Services\AppRegistryService())->listAll(),
            static fn(): array => \App\Core\DB::fetchAll(
                'SELECT name, version, status, requires_json FROM installed_plugins ORDER BY name ASC'
            ),
            static fn(): array => \App\Core\DB::fetchAll(
                'SELECT setting_key, setting_value FROM core_settings WHERE setting_key IN (?, ?) ORDER BY setting_key ASC',
                ['product_id', 'platform.product_id']
            ),
            static fn(): array => \App\Core\DB::fetchAll(
                'SELECT id, app_key, version, migration_name, checksum, status FROM core_app_migrations ORDER BY id ASC'
            ),
            static fn(): array => \App\Core\DB::fetchAll(
                'SELECT app_key, app_version, snapshot_json, checksum FROM core_schema_snapshots ORDER BY id ASC'
            )
        );
    }

    /** @return array<string,mixed> */
    public function collect(): array
    {
        $errors = [];
        $sources = [];
        $apps = $this->read('application registry', $this->appReader, $errors, $sources);
        $modules = $this->read('module registry', $this->moduleReader, $errors, $sources);
        $identityRows = $this->read('platform identity', $this->identityReader, $errors, $sources);
        $migrationRows = $this->read('migration ledger', $this->migrationReader, $errors, $sources);
        $schemaRows = $this->read('schema ledger', $this->schemaReader, $errors, $sources);

        if (!is_array($apps) || $apps === []) {
            $errors[] = 'installed application registry is missing or empty.';
            $apps = [];
        }
        if (!is_array($modules)) {
            $modules = [];
        }
        if (!is_array($identityRows)) {
            $identityRows = [];
        }
        if (!is_array($migrationRows)) {
            $migrationRows = [];
        }
        if (!is_array($schemaRows)) {
            $schemaRows = [];
        }

        $identity = $this->identity($identityRows, $apps, $errors);
        $appEvidence = $this->applications($apps, $errors);
        $moduleEvidence = $this->modules($modules, $errors);
        $migrationEvidence = $this->migrations($migrationRows, $apps, $errors);
        $schemaEvidence = $this->schema($schemaRows, $errors);

        foreach ((array)($migrationEvidence['failed'] ?? []) as $migration) {
            $errors[] = 'installed migration state has a failed migration: ' . (string)$migration . '.';
        }
        foreach ((array)($migrationEvidence['pending'] ?? []) as $migration) {
            $errors[] = 'installed migration state has a pending migration: ' . (string)$migration . '.';
        }
        foreach ((array)($migrationEvidence['evidence']['errors'] ?? []) as $error) {
            $errors[] = 'migration evidence: ' . (string)$error;
        }

        $state = [
            'product_id' => $identity['product_id'],
            'product_version' => $identity['product_version'],
            'platform' => [
                'product_id' => $identity['product_id'],
                'version' => $identity['product_version'],
                'build_id' => $identity['build_id'],
                'status' => $identity['status'],
            ],
            'apps' => $appEvidence,
            'modules' => $moduleEvidence,
            'schema' => $schemaEvidence,
            'migrations' => $migrationEvidence,
        ];

        return [
            'ok' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'warnings' => [],
            'state' => $state,
            'sources' => $sources,
        ];
    }

    /** @param array<int,string> $errors @param array<string,mixed> $sources */
    private function read(string $label, callable $reader, array &$errors, array &$sources): mixed
    {
        try {
            $value = $reader();
            $sources[$label] = 'read';
            return $value;
        } catch (Throwable $exception) {
            $sources[$label] = 'unavailable';
            $errors[] = $label . ' unavailable: ' . $exception->getMessage();
            return null;
        }
    }

    /** @param array<int,mixed> $rows @param array<int,mixed> $apps @param array<int,string> $errors */
    private function identity(array $rows, array $apps, array &$errors): array
    {
        $settings = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $key = trim((string)($row['setting_key'] ?? ''));
                if ($key !== '') {
                    $settings[$key] = trim((string)($row['setting_value'] ?? ''));
                }
            }
        }
        $productId = $settings['product_id'] ?? $settings['platform.product_id'] ?? '';
        if ($productId === '') {
            $errors[] = 'authoritative installed product identity is unavailable.';
        }
        $platform = null;
        foreach ($apps as $row) {
            if (is_array($row) && strtolower((string)($row['app_key'] ?? '')) === 'platform') {
                $platform = $row;
                break;
            }
        }
        if (!is_array($platform)) {
            $errors[] = 'installed platform registry entry is unavailable.';
            $platform = [];
        }
        $version = trim((string)($platform['version'] ?? ''));
        $status = strtolower(trim((string)($platform['status'] ?? '')));
        if ($version === '') {
            $errors[] = 'installed platform version is unavailable.';
        }
        $buildId = trim((string)($platform['build_id'] ?? ''));
        if ($buildId === '') {
            $errors[] = 'installed platform build identity is unavailable.';
        }
        if (!in_array($status, ['installed', 'enabled', 'disabled', 'upgrade_pending', 'broken'], true)) {
            $errors[] = 'installed platform lifecycle status is missing or unsupported.';
        }
        return [
            'product_id' => $productId,
            'product_version' => $version,
            'build_id' => $buildId,
            'status' => $status,
        ];
    }

    /** @param array<int,mixed> $rows @param array<int,string> $errors @return array<int,array<string,mixed>> */
    private function applications(array $rows, array &$errors): array
    {
        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                $errors[] = 'installed application registry contains a malformed row.';
                continue;
            }
            $id = trim((string)($row['app_key'] ?? ''));
            $version = trim((string)($row['version'] ?? ''));
            $status = strtolower(trim((string)($row['status'] ?? '')));
            if ($id === '' || $version === '' || $status === '') {
                $errors[] = 'installed application evidence requires id, version, and status.';
                continue;
            }
            if (!in_array($status, ['uploaded', 'installed', 'enabled', 'disabled', 'broken', 'upgrade_pending'], true)) {
                $errors[] = 'installed application lifecycle status is unsupported for ' . $id . '.';
            }
            if ($id === 'platform') {
                continue;
            }
            $manifest = json_decode((string)($row['manifest_json'] ?? '{}'), true);
            $manifest = is_array($manifest) ? $manifest : [];
            $result[] = [
                'id' => $id,
                'version' => $version,
                'status' => $status,
                'requires' => array_values(array_map('strval', (array)($manifest['requires'] ?? $manifest['dependencies'] ?? []))),
            ];
        }
        return $result;
    }

    /** @param array<int,mixed> $rows @param array<int,string> $errors @return array<int,array<string,mixed>> */
    private function modules(array $rows, array &$errors): array
    {
        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                $errors[] = 'installed module registry contains a malformed row.';
                continue;
            }
            $id = trim((string)($row['name'] ?? ''));
            $version = trim((string)($row['version'] ?? ''));
            $rawStatus = strtolower(trim((string)($row['status'] ?? '')));
            if ($id === '' || $version === '' || $rawStatus === '') {
                $errors[] = 'installed module evidence requires id, version, and status.';
                continue;
            }
            $status = $rawStatus === 'active' ? 'active' : ($rawStatus === 'inactive' ? 'disabled' : $rawStatus);
            if (!in_array($status, ['active', 'disabled', 'installed', 'broken', 'upgrade_pending'], true)) {
                $errors[] = 'installed module lifecycle status is unsupported for ' . $id . '.';
            }
            $requires = json_decode((string)($row['requires_json'] ?? '[]'), true);
            if (!is_array($requires)) {
                $errors[] = 'installed module dependency evidence is malformed for ' . $id . '.';
                $requires = [];
            }
            $result[] = [
                'id' => $id,
                'version' => $version,
                'status' => $status,
                'requires' => array_values(array_map('strval', $requires)),
            ];
        }
        return $result;
    }

    /** @param array<int,mixed> $rows @param array<int,mixed> $apps @param array<int,string> $errors */
    private function migrations(array $rows, array $apps, array &$errors): array
    {
        if ($rows === []) {
            $errors[] = 'installed migration ledger is missing or empty.';
        }
        $failed = [];
        $applied = [];
        $ledgerOrder = [];
        $ledgerOrderByApp = [];
        $identityOwners = [];
        $identitiesVerified = true;
        $ownershipVerified = true;
        $checksumsVerified = true;
        $orderingVerified = true;
        $evidenceErrors = [];
        $appIndex = [];
        foreach ($apps as $app) {
            if (is_array($app)) {
                $appIndex[trim((string)($app['app_key'] ?? ''))] = $app;
            }
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                $errors[] = 'installed migration ledger contains a malformed row.';
                continue;
            }
            $appKey = trim((string)($row['app_key'] ?? ''));
            $version = trim((string)($row['version'] ?? ''));
            $name = trim((string)($row['migration_name'] ?? ''));
            $status = strtolower(trim((string)($row['status'] ?? '')));
            if ($appKey === '' || $version === '' || $name === '' || !in_array($status, ['applied', 'failed'], true)) {
                $errors[] = 'installed migration evidence requires app, version, migration name, and supported status.';
                continue;
            }
            if (basename($name) !== $name || str_contains(str_replace('\\', '/', $name), '..')) {
                $identitiesVerified = false;
                $evidenceErrors[] = 'migration identity is not a safe owner-relative filename: ' . $appKey . ':' . $name . '.';
            }
            $key = $appKey . ':' . $name;
            if (isset($identityOwners[$key])) {
                $identitiesVerified = false;
                $evidenceErrors[] = 'duplicate migration identity: ' . $key . '.';
            }
            $identityOwners[$key] = true;
            if (!isset($appIndex[$appKey])) {
                $ownershipVerified = false;
                $evidenceErrors[] = 'migration owner is not an installed application: ' . $appKey . '.';
            }
            $ledgerOrder[] = ['key' => $key, 'id' => $row['id'] ?? null];
            $ledgerOrderByApp[$appKey][] = $key;
            $checksum = strtolower(trim((string)($row['checksum'] ?? '')));
            if (preg_match('/^[a-f0-9]{64}$/', $checksum) !== 1) {
                $checksumsVerified = false;
                $evidenceErrors[] = 'migration checksum is unavailable or malformed: ' . $key . '.';
            }
            if ($status === 'failed') {
                $failed[] = $key;
            } else {
                $applied[$key] = true;
            }
        }
        $pending = [];
        $expectedOrderByApp = [];
        foreach ($apps as $row) {
            if (!is_array($row)) {
                continue;
            }
            $appKey = trim((string)($row['app_key'] ?? ''));
            $manifest = json_decode((string)($row['manifest_json'] ?? '{}'), true);
            $manifest = is_array($manifest) ? $manifest : [];
            $migrationPath = trim((string)($manifest['migrations_path'] ?? ''));
            $installPath = trim((string)($row['install_path'] ?? ''));
            if ($migrationPath === '') {
                continue;
            }
            $directory = rtrim($installPath, '/') . '/' . ltrim($migrationPath, '/');
            if (!is_dir($directory)) {
                $errors[] = 'declared migration directory is unavailable for ' . $appKey . '.';
                continue;
            }
            foreach (glob($directory . '/*.sql') ?: [] as $file) {
                $name = basename($file);
                $key = $appKey . ':' . $name;
                $expectedOrderByApp[$appKey][] = $key;
                $ledgerRow = null;
                foreach ($rows as $candidate) {
                    if (is_array($candidate) && ($appKey . ':' . basename((string)($candidate['migration_name'] ?? ''))) === $key) {
                        $ledgerRow = $candidate;
                        break;
                    }
                }
                if (is_array($ledgerRow)) {
                    $expectedChecksum = strtolower((string)($ledgerRow['checksum'] ?? ''));
                    $actualChecksum = hash_file('sha256', $file) ?: '';
                    if ($expectedChecksum !== $actualChecksum) {
                        $checksumsVerified = false;
                        $evidenceErrors[] = 'migration checksum mismatch: ' . $key . '.';
                    }
                }
                if (!isset($applied[$appKey . ':' . $name]) && !in_array($appKey . ':' . $name, $failed, true)) {
                    $pending[] = $appKey . ':' . $name;
                }
            }
        }
        $actualOrder = [];
        foreach ($ledgerOrder as $row) {
            if (!is_int($row['id']) && !ctype_digit((string)$row['id'])) {
                $orderingVerified = false;
                $evidenceErrors[] = 'migration execution order identifier is unavailable.';
            }
            $actualOrder[] = $row['key'];
        }
        foreach ($expectedOrderByApp as $appKey => $expectedOrder) {
            $actualOrderForApp = array_values($ledgerOrderByApp[$appKey] ?? []);
            if ($actualOrderForApp !== $expectedOrder) {
                $orderingVerified = false;
                $evidenceErrors[] = 'migration ledger order does not match the migration runner order for ' . $appKey . '.';
            }
        }
        return [
            'pending' => array_values(array_unique($pending)),
            'failed' => array_values(array_unique($failed)),
            'order' => $actualOrder,
            'evidence' => [
                'identities_verified' => $identitiesVerified,
                'ownership_verified' => $ownershipVerified,
                'checksums_verified' => $checksumsVerified,
                'ordering_verified' => $orderingVerified,
                'target_schema_verified' => false,
                'errors' => array_values(array_unique($evidenceErrors)),
            ],
        ];
    }

    /** @param array<int,mixed> $rows @param array<int,string> $errors @return array<string,string> */
    private function schema(array $rows, array &$errors): array
    {
        if ($rows === []) {
            $errors[] = 'installed schema ledger is missing or empty.';
            return ['version' => ''];
        }
        $candidate = '';
        $versions = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                $errors[] = 'installed schema ledger contains a malformed row.';
                continue;
            }
            $snapshot = json_decode((string)($row['snapshot_json'] ?? '{}'), true);
            if (!is_array($snapshot)) {
                $errors[] = 'installed schema snapshot is malformed.';
                continue;
            }
            $recordedChecksum = strtolower(trim((string)($row['checksum'] ?? '')));
            if ($recordedChecksum !== '') {
                $actualChecksum = hash('sha256', (string)($row['snapshot_json'] ?? ''));
                if ($recordedChecksum !== $actualChecksum) {
                    $errors[] = 'installed schema snapshot checksum mismatch.';
                }
            } else {
                $errors[] = 'installed schema snapshot checksum is unavailable.';
            }
            $version = trim((string)($snapshot['schema_version'] ?? $snapshot['version'] ?? ''));
            if ($version !== '') {
                $candidate = $version;
                $versions[$version] = true;
            }
        }
        if (count($versions) > 1) {
            $errors[] = 'installed schema baseline is contradictory across authoritative snapshots.';
        }
        if ($candidate === '') {
            $errors[] = 'installed schema version is unavailable from authoritative snapshots.';
        }
        return ['version' => $candidate];
    }
}
