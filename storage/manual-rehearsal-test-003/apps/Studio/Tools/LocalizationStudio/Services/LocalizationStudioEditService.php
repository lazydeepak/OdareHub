<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\LocalizationStudio\Services;

final class LocalizationStudioEditService
{
    private const SUPPORTED_LOCALES = ['en', 'ja', 'ne'];

    public static function read(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $data = require $path;
        if (!is_array($data)) {
            return [];
        }

        return self::flattenWithKeys($data);
    }

    public static function snapshot(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        self::assertPathIsLocaleFile($path);

        $dir = dirname($path) . '/.backups';
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
                return null;
            }
        }

        $ts = date('Ymd_His');
        $base = basename($path);
        $snap = $dir . '/' . $base . '.' . $ts . '.bak';

        return @copy($path, $snap) ? $snap : null;
    }

    public static function write(string $path, array $data): bool
    {
        self::assertPathIsLocaleFile($path);

        $php = "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n";
        foreach ($data as $key => $value) {
            $k = var_export((string) $key, true);
            $v = var_export((string) $value, true);
            $php .= "    {$k} => {$v},\n";
        }
        $php .= "];\n";

        $tmp = $path . '.tmp.' . getmypid();
        $written = @file_put_contents($tmp, $php);
        if ($written === false) {
            @unlink($tmp);
            return false;
        }

        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            return false;
        }

        return true;
    }

    public static function validate(array $data, string $locale): array
    {
        $errors = [];

        if (!in_array($locale, self::SUPPORTED_LOCALES, true)) {
            $errors[] = 'unsupported_locale';
        }

        foreach ($data as $key => $value) {
            if (trim((string) $key) === '') {
                $errors[] = 'empty_key';
                continue;
            }

            if (preg_match('/[\x00-\x1F\x7F]/', (string) $key) === 1) {
                $errors[] = 'control_char_in_key';
            }

            $sv = (string) $value;
            if (preg_match('/<\?=|<\?php|\{php\}|\$_(GET|POST|REQUEST|SERVER|SESSION|COOKIE|ENV|FILES)/i', $sv) === 1) {
                $errors[] = 'dangerous_value_for_key_' . $key;
            }
        }

        return $errors;
    }

    public static function runPostApplyDiagnostics(string $path): array
    {
        $result = [
            'parse_ok' => false,
            'key_count' => 0,
            'errors' => [],
        ];

        if (!is_file($path)) {
            $result['errors'][] = 'file_not_found';
            return $result;
        }

        try {
            $data = require $path;
        } catch (\Throwable $e) {
            $result['errors'][] = 'parse_error: ' . $e->getMessage();
            return $result;
        }
        if (!is_array($data)) {
            $result['errors'][] = 'invalid_return';
            return $result;
        }

        $result['parse_ok'] = true;
        $result['key_count'] = count(self::flattenWithKeys($data));

        return $result;
    }

    public static function getOwnersWithPaths(): array
    {
        $scan = LocalizationStudioDiscoveryService::scan();
        $groups = isset($scan['groups']) && is_array($scan['groups']) ? $scan['groups'] : [];
        $owners = [];

        foreach ($groups as $ownerKey => $ownerData) {
            $files = isset($ownerData['files']) && is_array($ownerData['files']) ? $ownerData['files'] : [];
            $paths = [];

            foreach ($files as $locale => $info) {
                if (!empty($info['path'])) {
                    $fullPath = APP_ROOT . '/' . $info['path'];
                    if (is_file($fullPath)) {
                        $paths[$locale] = $fullPath;
                    }
                }
            }

            if ($paths !== []) {
                $owners[] = [
                    'key' => $ownerKey,
                    'locales' => array_keys($paths),
                    'paths' => $paths,
                ];
            }
        }

        usort($owners, static fn(array $a, array $b): int => strcmp($a['key'], $b['key']));

        return $owners;
    }

    private static function flattenWithKeys(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                foreach (self::flattenWithKeys($value) as $subKey => $subValue) {
                    $result[$key . '.' . $subKey] = $subValue;
                }
            } else {
                $result[(string) $key] = $value;
            }
        }
        return $result;
    }

    /**
     * Preview creating a locale file from English keys.
     * Returns the target path and the list of keys that would be created.
     *
     * @throws \RuntimeException if en.php is missing or target already exists
     */
    public static function previewCreateFromEnglish(string $enPath, string $locale): array
    {
        if (!in_array($locale, ['ja', 'ne'], true)) {
            throw new \RuntimeException('Can only create ja or ne from English');
        }

        if (!is_file($enPath)) {
            throw new \RuntimeException('English locale file not found: ' . $enPath);
        }

        $targetPath = dirname($enPath) . '/' . $locale . '.php';

        if (is_file($targetPath)) {
            throw new \RuntimeException('Target locale file already exists: ' . $targetPath);
        }

        self::assertValidNewFilePath($targetPath);

        $keys = array_keys(self::read($enPath));

        return [
            'target_path' => $targetPath,
            'key_count' => count($keys),
            'keys' => $keys,
        ];
    }

    /**
     * Create a new locale file from English keys with empty values.
     * Validates path safety, writes via atomic temp+rename, runs diagnostics.
     *
     * @return array{path: string, key_count: int, diagnostics: array}
     * @throws \RuntimeException on validation or write failure
     */
    public static function createFromEnglish(string $enPath, string $locale): array
    {
        if (!in_array($locale, ['ja', 'ne'], true)) {
            throw new \RuntimeException('Can only create ja or ne from English');
        }

        if (!is_file($enPath)) {
            throw new \RuntimeException('English locale file not found: ' . $enPath);
        }

        $targetPath = dirname($enPath) . '/' . $locale . '.php';

        if (is_file($targetPath)) {
            throw new \RuntimeException('Target locale file already exists: ' . $targetPath);
        }

        self::assertValidNewFilePath($targetPath);

        $enData = self::read($enPath);
        $data = [];
        foreach ($enData as $key => $value) {
            $data[$key] = '';
        }

        $php = "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n";
        foreach ($data as $key => $value) {
            $k = var_export((string) $key, true);
            $v = var_export((string) $value, true);
            $php .= "    {$k} => {$v},\n";
        }
        $php .= "];\n";

        $tmp = $targetPath . '.tmp.' . getmypid();
        $written = @file_put_contents($tmp, $php);
        if ($written === false) {
            @unlink($tmp);
            throw new \RuntimeException('Failed to write temporary file');
        }

        if (!@rename($tmp, $targetPath)) {
            @unlink($tmp);
            throw new \RuntimeException('Failed to rename temporary file');
        }

        $diagnostics = self::runPostApplyDiagnostics($targetPath);

        return [
            'path' => $targetPath,
            'key_count' => count($data),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * Validate a locale file path for a NEW file (before it exists).
     * Same checks as assertPathIsLocaleFile but without requiring realpath().
     */
    private static function assertValidNewFilePath(string $path): void
    {
        if (!str_ends_with($path, '.php')) {
            throw new \RuntimeException('Path is not a PHP file: ' . $path);
        }

        $validPrefixes = [
            APP_ROOT . '/app/lang/',
            APP_ROOT . '/apps/',
            APP_ROOT . '/plugins/',
        ];

        $allowed = false;
        foreach ($validPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            throw new \RuntimeException('Path is outside allowed locale directories: ' . $path);
        }

        if (!str_contains($path, '/lang/')) {
            throw new \RuntimeException('Path is not inside a lang/ directory: ' . $path);
        }

        if (preg_match('/\/lang\/(en|ja|ne)\.php$/', $path) !== 1) {
            throw new \RuntimeException('Path does not match expected locale file pattern: ' . $path);
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            throw new \RuntimeException('Parent directory does not exist: ' . $dir);
        }
    }

    private static function assertPathIsLocaleFile(string $path): void
    {
        $realPath = realpath($path);
        if ($realPath === false) {
            throw new \RuntimeException('Path does not resolve: ' . $path);
        }

        if (!str_ends_with($realPath, '.php')) {
            throw new \RuntimeException('Path is not a PHP file: ' . $path);
        }

        $validPrefixes = [
            APP_ROOT . '/app/lang/',
            APP_ROOT . '/apps/',
            APP_ROOT . '/plugins/',
        ];

        $allowed = false;
        foreach ($validPrefixes as $prefix) {
            $realPrefix = realpath(rtrim($prefix, '/'));
            if ($realPrefix !== false && str_starts_with($realPath, $realPrefix)) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            throw new \RuntimeException('Path is outside allowed locale directories: ' . $path);
        }

        if (!str_contains($realPath, '/lang/')) {
            throw new \RuntimeException('Path is not inside a lang/ directory: ' . $path);
        }

        $localePattern = '/\/lang\/(en|ja|ne)\.php$/';
        if (preg_match($localePattern, $realPath) !== 1) {
            throw new \RuntimeException('Path does not match expected locale file pattern: ' . $path);
        }
    }
}
