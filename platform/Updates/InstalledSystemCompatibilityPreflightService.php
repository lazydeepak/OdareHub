<?php
declare(strict_types=1);

namespace Platform\Updates;

use DateTimeImmutable;
use Throwable;

/**
 * Read-only compatibility preflight for the V1 installed-system evidence
 * supplied by the caller. It never discovers, enables, migrates, or repairs
 * applications/modules.
 */
final class InstalledSystemCompatibilityPreflightService
{
    /**
     * @param array<string,mixed> $compatibility
     * @param array<string,mixed> $installedState
     * @return array<string,mixed>
     */
    public function validate(array $compatibility, array $installedState, ?string $releaseVersion = null): array
    {
        $errors = [];
        foreach (['core', 'apps', 'modules', 'schema'] as $field) {
            if (!is_array($compatibility[$field] ?? null)) {
                $errors[] = 'release compatibility requires structured ' . $field . ' data.';
            }
        }
        $this->validateInstalledEvidence($installedState, $errors);

        $productId = (string)($installedState['product_id'] ?? '');
        if ($productId !== 'susankhya-os') {
            $errors[] = 'installed product_id is missing or incompatible.';
        }
        $installedVersion = (string)($installedState['product_version'] ?? '');
        if ($releaseVersion !== null && $releaseVersion !== '' && version_compare($releaseVersion, $installedVersion, '<')) {
            $errors[] = 'release downgrade is unsupported by the V1 compatibility contract.';
        }
        $minimum = (string)($compatibility['minimum_product_version'] ?? '');
        $maximum = $compatibility['maximum_product_version'] ?? null;
        if ($minimum !== '' && version_compare($installedVersion, $minimum, '<')) {
            $errors[] = 'installed product version is below release minimum.';
        }
        if (is_string($maximum) && $maximum !== '' && version_compare($installedVersion, $maximum, '>')) {
            $errors[] = 'installed product version is above release maximum.';
        }

        $platform = is_array($installedState['platform'] ?? null) ? $installedState['platform'] : [];
        $platformVersion = (string)($platform['version'] ?? '');
        if ($platformVersion === '') {
            $platformVersion = '0.0.0';
        }
        if ((string)($platform['product_id'] ?? '') !== $productId || (string)($platform['product_id'] ?? '') !== 'susankhya-os') {
            $errors[] = 'installed platform product identity is contradictory.';
        }
        $core = is_array($compatibility['core'] ?? null) ? $compatibility['core'] : [];
        if (isset($core['minimum']) && version_compare($platformVersion, (string)$core['minimum'], '<')) {
            $errors[] = 'installed platform version is below release core minimum.';
        }
        if (isset($core['maximum']) && is_string($core['maximum']) && $core['maximum'] !== ''
            && version_compare($platformVersion, $core['maximum'], '>')) {
            $errors[] = 'installed platform version is above release core maximum.';
        }

        $php = is_array($compatibility['php'] ?? null) ? $compatibility['php'] : [];
        if (isset($php['minimum']) && version_compare(PHP_VERSION, (string)$php['minimum'], '<')) {
            $errors[] = 'PHP version is below release minimum.';
        }
        if (isset($php['maximum']) && is_string($php['maximum']) && $php['maximum'] !== ''
            && version_compare(PHP_VERSION, $php['maximum'], '>')) {
            $errors[] = 'PHP version is above release maximum.';
        }
        foreach ((array)($compatibility['php_extensions'] ?? []) as $extension) {
            if (is_string($extension) && $extension !== '' && !extension_loaded($extension)) {
                $errors[] = 'Required PHP extension is unavailable: ' . $extension;
            }
        }

        $this->validateComponentRequirements('application', (array)($compatibility['apps'] ?? []), (array)($installedState['apps'] ?? []), $errors);
        $this->validateComponentRequirements('module', (array)($compatibility['modules'] ?? []), (array)($installedState['modules'] ?? []), $errors);
        $this->validateSchema($compatibility, $installedState, $errors);
        $migrationResult = (new MigrationCompatibilityPreflightService())->validate($compatibility, $installedState);
        foreach ((array)($migrationResult['errors'] ?? []) as $error) {
            $errors[] = (string)$error;
        }

        return [
            'ok' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'warnings' => [],
            'eligibility_blockers' => array_values(array_unique((array)($migrationResult['eligibility_blockers'] ?? []))),
            'evidence' => [
                'product_id' => $productId,
                'product_version' => $installedVersion,
                'platform_version' => (string)($platform['version'] ?? ''),
                'app_count' => count((array)($installedState['apps'] ?? [])),
                'module_count' => count((array)($installedState['modules'] ?? [])),
                'schema_version' => (string)($installedState['schema']['version'] ?? ''),
            ],
        ];
    }

    /** @param array<string,mixed> $state @param array<int,string> $errors */
    private function validateInstalledEvidence(array $state, array &$errors): void
    {
        foreach (['product_id', 'product_version'] as $field) {
            if (!is_string($state[$field] ?? null) || trim((string)$state[$field]) === '') {
                $errors[] = 'installed-state evidence requires ' . $field . '.';
            }
        }
        foreach (['platform', 'apps', 'modules', 'schema', 'migrations'] as $field) {
            if (!is_array($state[$field] ?? null)) {
                $errors[] = 'installed-state evidence requires structured ' . $field . ' data.';
            }
        }
        $platform = is_array($state['platform'] ?? null) ? $state['platform'] : [];
        foreach (['product_id', 'version', 'build_id'] as $field) {
            if (!is_string($platform[$field] ?? null) || trim((string)$platform[$field]) === '') {
                $errors[] = 'installed platform evidence requires ' . $field . '.';
            }
        }
        if (isset($state['captured_at'])) {
            try {
                new DateTimeImmutable((string)$state['captured_at']);
            } catch (Throwable) {
                $errors[] = 'installed-state captured_at is invalid.';
            }
        }
    }

    /**
     * @param array<int,mixed> $requirements
     * @param array<int,mixed> $installed
     * @param array<int,string> $errors
     */
    private function validateComponentRequirements(string $kind, array $requirements, array $installed, array &$errors): void
    {
        $index = [];
        foreach ($installed as $row) {
            if (!is_array($row)) {
                $errors[] = 'installed ' . $kind . ' evidence contains a malformed entry.';
                continue;
            }
            $id = (string)($row['id'] ?? $row['key'] ?? $row['name'] ?? '');
            if ($id === '' || !is_string($row['version'] ?? null) || trim((string)$row['version']) === '') {
                $errors[] = 'installed ' . $kind . ' evidence requires id and version.';
                continue;
            }
            $index[$id] = $row;
        }

        foreach ($requirements as $requirement) {
            if (!is_array($requirement)) {
                $errors[] = 'release ' . $kind . ' compatibility contains a malformed requirement.';
                continue;
            }
            $id = (string)($requirement['id'] ?? $requirement['key'] ?? $requirement['name'] ?? '');
            if ($id === '') {
                $errors[] = 'release ' . $kind . ' compatibility requires an id.';
                continue;
            }
            $row = $index[$id] ?? null;
            $required = ($requirement['required'] ?? true) === true;
            if (!is_array($row)) {
                if ($required) {
                    $errors[] = 'required ' . $kind . ' is not installed: ' . $id . '.';
                }
                continue;
            }
            $status = strtolower(trim((string)($row['status'] ?? '')));
            if ($required && !in_array($status, ['installed', 'enabled', 'active'], true)) {
                $errors[] = 'required ' . $kind . ' is not active: ' . $id . '.';
            }
            $this->versionRange($kind . ' ' . $id, (string)$row['version'], $requirement, $errors);
            foreach ((array)($requirement['requires'] ?? []) as $dependency) {
                $dependencyId = is_array($dependency)
                    ? (string)($dependency['id'] ?? $dependency['key'] ?? $dependency['name'] ?? '')
                    : (string)$dependency;
                if ($dependencyId === '') {
                    $errors[] = $kind . ' dependency declaration is malformed for ' . $id . '.';
                    continue;
                }
                $dependencyRow = $index[$dependencyId] ?? null;
                if (!is_array($dependencyRow) || !in_array(strtolower((string)($dependencyRow['status'] ?? '')), ['installed', 'enabled', 'active'], true)) {
                    $errors[] = $kind . ' dependency is missing or inactive: ' . $id . ' requires ' . $dependencyId . '.';
                }
            }
        }
    }

    /** @param array<string,mixed> $compatibility @param array<string,mixed> $state @param array<int,string> $errors */
    private function validateSchema(array $compatibility, array $state, array &$errors): void
    {
        $schemaRequirement = is_array($compatibility['schema'] ?? null) ? $compatibility['schema'] : [];
        $schemaState = is_array($state['schema'] ?? null) ? $state['schema'] : [];
        $schemaVersion = (string)($schemaState['version'] ?? '');
        if ($schemaVersion === '') {
            $errors[] = 'installed schema evidence requires version.';
            return;
        }
        if (isset($schemaRequirement['minimum']) && $schemaRequirement['minimum'] !== null
            && version_compare($schemaVersion, (string)$schemaRequirement['minimum'], '<')) {
            $errors[] = 'installed schema baseline is below release minimum.';
        }
        if (isset($schemaRequirement['maximum']) && is_string($schemaRequirement['maximum']) && $schemaRequirement['maximum'] !== ''
            && version_compare($schemaVersion, $schemaRequirement['maximum'], '>')) {
            $errors[] = 'installed schema baseline is above release maximum.';
        }
    }

    /** @param array<string,mixed> $requirement @param array<int,string> $errors */
    private function versionRange(string $label, string $version, array $requirement, array &$errors): void
    {
        if (isset($requirement['minimum']) && version_compare($version, (string)$requirement['minimum'], '<')) {
            $errors[] = $label . ' is below release minimum.';
        }
        if (isset($requirement['maximum']) && is_string($requirement['maximum']) && $requirement['maximum'] !== ''
            && version_compare($version, $requirement['maximum'], '>')) {
            $errors[] = $label . ' is above release maximum.';
        }
    }
}
