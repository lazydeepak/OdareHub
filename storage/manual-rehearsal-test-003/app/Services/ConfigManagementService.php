<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

final class ConfigManagementService
{
    /**
     * @return array<string,string>
     */
    public function environmentOptions(): array
    {
        return [
            'local' => 'Local',
            'staging' => 'Staging',
            'production' => 'Production',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function dashboard(?string $selectedEnvironment = null): array
    {
        $selectedEnvironment = $this->normalizeEnvironment($selectedEnvironment ?: $this->currentEnvironmentKey());
        $cards = [];
        foreach ($this->environmentOptions() as $environment => $label) {
            $cards[$environment] = $this->environmentCard($environment, $label);
        }

        return [
            'current_environment' => $this->currentEnvironmentKey(),
            'selected_environment' => $selectedEnvironment,
            'environment_cards' => $cards,
            'base_settings' => $this->baseSettings(),
            'db_config' => $this->dbConfig(),
            'db_config_status' => $this->dbConfigValidation(),
            'selected_layer' => $this->loadLayer($selectedEnvironment),
            'selected_layer_json' => $this->prettyJson($this->loadLayer($selectedEnvironment)),
            'selected_resolved' => $this->resolvedConfig($selectedEnvironment),
            'selected_validation' => $this->environmentValidation($selectedEnvironment),
            'global_validation' => $this->globalValidation(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function saveLayerFromJson(string $environment, string $jsonPayload): array
    {
        $environment = $this->normalizeEnvironment($environment);
        $decoded = json_decode($jsonPayload, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Environment layer JSON must decode to an object.');
        }

        $layer = $this->sanitizeLayer($decoded);
        $layer['_meta'] = [
            'environment' => $environment,
            'updated_at' => date('c'),
            'updated_by' => $this->currentUserLabel(),
            'source' => 'manual_save',
        ];
        $this->persistLayer($environment, $layer);

        return [
            'status' => 'saved',
            'environment' => $environment,
            'file_path' => $this->layerPath($environment),
            'validation' => $this->environmentValidation($environment),
            'resolved' => $this->resolvedConfig($environment),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function importLayer(string $environment, string $tmpPath, string $sourceName, string $mode = 'merge'): array
    {
        $environment = $this->normalizeEnvironment($environment);
        $mode = trim($mode);
        if (!in_array($mode, ['merge', 'replace'], true)) {
            throw new \RuntimeException('Unsupported config import mode.');
        }

        $ext = strtolower(pathinfo($sourceName, PATHINFO_EXTENSION));
        if ($ext !== 'json') {
            throw new \RuntimeException('Use a .json file for config import.');
        }

        $decoded = json_decode((string)file_get_contents($tmpPath), true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Config import file is not valid JSON.');
        }

        $importedEnvironment = trim((string)($decoded['environment'] ?? ''));
        $layerPayload = $decoded['layer'] ?? $decoded;
        if ($importedEnvironment !== '' && $importedEnvironment !== $environment) {
            throw new \RuntimeException('Import file is for environment ' . $importedEnvironment . ', not ' . $environment . '.');
        }
        if (!is_array($layerPayload)) {
            throw new \RuntimeException('Import file does not contain a valid config layer object.');
        }

        $existing = $this->loadLayer($environment);
        $incoming = $this->sanitizeLayer($layerPayload);
        $layer = $mode === 'replace'
            ? $incoming
            : array_merge($existing, $incoming);
        $layer['_meta'] = [
            'environment' => $environment,
            'updated_at' => date('c'),
            'updated_by' => $this->currentUserLabel(),
            'source' => 'import',
            'source_file' => $sourceName,
            'import_mode' => $mode,
        ];
        $this->persistLayer($environment, $layer);

        return [
            'status' => 'imported',
            'environment' => $environment,
            'mode' => $mode,
            'file_path' => $this->layerPath($environment),
            'validation' => $this->environmentValidation($environment),
            'resolved' => $this->resolvedConfig($environment),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function exportPayload(string $environment): array
    {
        $environment = $this->normalizeEnvironment($environment);
        return [
            'config_kind' => 'environment_layer',
            'environment' => $environment,
            'exported_at' => date('c'),
            'exported_by' => $this->currentUserLabel(),
            'current_runtime_environment' => $this->currentEnvironmentKey(),
            'layer' => $this->loadLayer($environment),
            'resolved' => $this->resolvedConfig($environment),
            'validation' => $this->environmentValidation($environment),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function environmentCard(string $environment, string $label): array
    {
        $layer = $this->loadLayer($environment);
        $validation = $this->environmentValidation($environment);
        $meta = is_array($layer['_meta'] ?? null) ? $layer['_meta'] : [];
        $resolved = $this->resolvedConfig($environment);

        return [
            'environment' => $environment,
            'label' => $label,
            'is_current' => $environment === $this->currentEnvironmentKey(),
            'has_layer' => is_file($this->layerPath($environment)),
            'file_path' => $this->layerPath($environment),
            'override_count' => count($this->stripMeta($layer)),
            'resolved_count' => count($resolved),
            'app_url' => (string)($resolved['app.url'] ?? ''),
            'updated_at' => (string)($meta['updated_at'] ?? ''),
            'updated_by' => (string)($meta['updated_by'] ?? ''),
            'warning_count' => count((array)($validation['warnings'] ?? [])),
            'error_count' => count((array)($validation['errors'] ?? [])),
            'missing_keys' => array_values(array_map('strval', (array)($validation['missing_keys'] ?? []))),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function resolvedConfig(string $environment): array
    {
        $environment = $this->normalizeEnvironment($environment);
        $resolved = [];

        foreach ($this->baseSettings() as $key => $value) {
            $resolved[$key] = (string)$value;
        }

        foreach ($this->dbConfig() as $key => $value) {
            $resolved['db.' . $key] = is_scalar($value) ? (string)$value : '';
        }

        foreach ($this->stripMeta($this->loadLayer($environment)) as $key => $value) {
            $resolved[(string)$key] = is_scalar($value) ? (string)$value : json_encode($value, JSON_UNESCAPED_SLASHES);
        }

        $resolved['environment.name'] = $environment;
        $resolved['environment.current'] = $this->currentEnvironmentKey();
        ksort($resolved);
        return $resolved;
    }

    /**
     * @return array<string,mixed>
     */
    public function environmentValidation(string $environment): array
    {
        $environment = $this->normalizeEnvironment($environment);
        $resolved = $this->resolvedConfig($environment);
        $layer = $this->loadLayer($environment);
        $warnings = [];
        $errors = [];
        $missingKeys = [];

        if (!is_file($this->layerPath($environment))) {
            $warnings[] = 'No explicit ' . $environment . ' layer file exists yet.';
        }

        foreach ($this->requiredKeys() as $key => $meta) {
            $value = trim((string)($resolved[$key] ?? ''));
            $requiredIn = (array)($meta['required_in'] ?? []);
            if ($value === '' && (in_array('all', $requiredIn, true) || in_array($environment, $requiredIn, true))) {
                $missingKeys[] = $key;
                $severity = (string)($meta['severity'] ?? 'warning');
                $message = (string)($meta['label'] ?? $key) . ' is missing for ' . $environment . '.';
                if ($severity === 'error') {
                    $errors[] = $message;
                } else {
                    $warnings[] = $message;
                }
            }
        }

        $appUrl = trim((string)($resolved['app.url'] ?? ''));
        if ($appUrl !== '' && !preg_match('#^https?://#i', $appUrl)) {
            $warnings[] = 'app.url should start with http:// or https://.';
        }

        $debugEnabled = strtolower(trim((string)($resolved['feature.debug.enabled'] ?? '')));
        $devToolsEnabled = strtolower(trim((string)($resolved['feature.dev_tools.enabled'] ?? '')));
        if ($environment === 'production' && in_array($debugEnabled, ['1', 'true', 'yes', 'on'], true)) {
            $warnings[] = 'Production layer enables debug output.';
        }
        if (in_array($environment, ['staging', 'production'], true) && in_array($devToolsEnabled, ['1', 'true', 'yes', 'on'], true)) {
            $warnings[] = ucfirst($environment) . ' layer enables developer tools.';
        }

        if (trim((string)($resolved['db.name'] ?? '')) === '') {
            $errors[] = 'Database name is not configured.';
        }

        return [
            'environment' => $environment,
            'warnings' => array_values(array_unique($warnings)),
            'errors' => array_values(array_unique($errors)),
            'missing_keys' => array_values(array_unique($missingKeys)),
            'layer_present' => is_file($this->layerPath($environment)),
            'meta' => is_array($layer['_meta'] ?? null) ? $layer['_meta'] : [],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function globalValidation(): array
    {
        $warnings = [];
        $errors = [];
        $dbStatus = $this->dbConfigValidation();

        foreach ((array)($dbStatus['warnings'] ?? []) as $warning) {
            $warnings[] = (string)$warning;
        }
        foreach ((array)($dbStatus['errors'] ?? []) as $error) {
            $errors[] = (string)$error;
        }

        foreach ($this->environmentOptions() as $environment => $label) {
            $validation = $this->environmentValidation($environment);
            foreach ((array)($validation['warnings'] ?? []) as $warning) {
                $warnings[] = '[' . $label . '] ' . (string)$warning;
            }
            foreach ((array)($validation['errors'] ?? []) as $error) {
                $errors[] = '[' . $label . '] ' . (string)$error;
            }
        }

        return [
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function dbConfigValidation(): array
    {
        $config = $this->dbConfig();
        $warnings = [];
        $errors = [];
        foreach (['host', 'name', 'user', 'charset', 'timezone'] as $required) {
            if (trim((string)($config[$required] ?? '')) === '') {
                $errors[] = 'DB config is missing ' . $required . '.';
            }
        }

        if (!is_file(app_db_config_path())) {
            if (($config['_source'] ?? '') === 'env') {
                $warnings[] = 'Using database config from environment variables because storage/db_config.php is missing.';
            } else {
                $errors[] = 'storage/db_config.php is missing.';
            }
        }

        return [
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    /**
     * @return array<string,string>
     */
    private function baseSettings(): array
    {
        $map = [];
        try {
            $rows = DB::fetchAll('SELECT setting_key, setting_value FROM core_settings ORDER BY setting_key ASC');
            foreach ($rows as $row) {
                $key = trim((string)($row['setting_key'] ?? ''));
                if ($key === '') {
                    continue;
                }
                $map[$key] = (string)($row['setting_value'] ?? '');
            }
        } catch (\Throwable) {
            // Keep config management usable even if core_settings is not ready.
        }

        return $map;
    }

    /**
     * @return array<string,mixed>
     */
    private function dbConfig(): array
    {
        $config = app_db_config();
        return is_array($config) ? $config : [];
    }

    /**
     * @return array<string,mixed>
     */
    private function loadLayer(string $environment): array
    {
        $path = $this->layerPath($environment);
        if (!is_file($path)) {
            return [];
        }

        $loaded = require $path;
        return is_array($loaded) ? $loaded : [];
    }

    /**
     * @param array<string,mixed> $layer
     */
    private function persistLayer(string $environment, array $layer): void
    {
        $dir = dirname($this->layerPath($environment));
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Unable to create config layer directory.');
        }

        $payload = "<?php\nreturn " . var_export($layer, true) . ";\n";
        if (@file_put_contents($this->layerPath($environment), $payload) === false) {
            throw new \RuntimeException('Unable to write config layer file.');
        }
    }

    private function layerPath(string $environment): string
    {
        return APP_ROOT . '/storage/config/environments/' . $environment . '.php';
    }

    /**
     * @param array<string,mixed> $layer
     * @return array<string,mixed>
     */
    private function sanitizeLayer(array $layer): array
    {
        $clean = [];
        foreach ($layer as $key => $value) {
            $key = trim((string)$key);
            if ($key === '' || $key === '_meta') {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $clean[$key] = $value === null ? '' : (string)$value;
                continue;
            }

            if (is_array($value)) {
                $clean[$key] = json_encode($value, JSON_UNESCAPED_SLASHES) ?: '[]';
            }
        }

        ksort($clean);
        return $clean;
    }

    /**
     * @param array<string,mixed> $layer
     * @return array<string,mixed>
     */
    private function stripMeta(array $layer): array
    {
        unset($layer['_meta']);
        return $layer;
    }

    private function normalizeEnvironment(?string $environment): string
    {
        $environment = strtolower(trim((string)$environment));
        if ($environment === 'development' || $environment === 'dev') {
            $environment = 'local';
        }
        if (!isset($this->environmentOptions()[$environment])) {
            throw new \RuntimeException('Unsupported environment: ' . $environment);
        }
        return $environment;
    }

    private function currentEnvironmentKey(): string
    {
        $current = app_env();
        if (in_array($current, ['development', 'dev', 'local'], true)) {
            return 'local';
        }
        if ($current === 'staging') {
            return 'staging';
        }
        return 'production';
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function requiredKeys(): array
    {
        return [
            'app.url' => [
                'label' => 'App URL',
                'required_in' => ['staging', 'production'],
                'severity' => 'error',
            ],
            'system.name' => [
                'label' => 'System name',
                'required_in' => ['all'],
                'severity' => 'warning',
            ],
            'company.name' => [
                'label' => 'Company name',
                'required_in' => ['all'],
                'severity' => 'warning',
            ],
            'system.default_language' => [
                'label' => 'Default language',
                'required_in' => ['all'],
                'severity' => 'warning',
            ],
            'system.default_currency' => [
                'label' => 'Default currency',
                'required_in' => ['all'],
                'severity' => 'warning',
            ],
            'system.timezone' => [
                'label' => 'Timezone',
                'required_in' => ['all'],
                'severity' => 'warning',
            ],
            'ui.theme' => [
                'label' => 'Theme',
                'required_in' => ['all'],
                'severity' => 'warning',
            ],
            'ui.default_home' => [
                'label' => 'Default home route',
                'required_in' => ['all'],
                'severity' => 'warning',
            ],
        ];
    }

    private function currentUserLabel(): string
    {
        if (class_exists('\\App\\Core\\Auth') && method_exists('\\App\\Core\\Auth', 'user')) {
            $user = \App\Core\Auth::user();
            if (is_array($user)) {
                return (string)($user['email'] ?? 'system');
            }
        }

        return 'system';
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function prettyJson(array $payload): string
    {
        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
