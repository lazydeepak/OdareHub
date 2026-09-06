<?php
declare(strict_types=1);

namespace Platform\Reports;

final class ReportDefinitionRepository
{
    private const DEFINITIONS_ROOT = APP_ROOT . '/platform/Reports/Definitions';
    private const SNAPSHOT_ROOT = APP_ROOT . '/storage/platform-snapshots/report-definitions';

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function all(): array
    {
        $definitions = [];
        foreach (glob(self::DEFINITIONS_ROOT . '/*.json') ?: [] as $path) {
            $decoded = self::readJson($path);
            if ($decoded !== null) {
                $definitions[] = $decoded;
            }
        }
        usort($definitions, static fn(array $left, array $right): int => strcasecmp(
            (string)($left['report_name'] ?? ''),
            (string)($right['report_name'] ?? '')
        ));
        return $definitions;
    }

    /**
     * @return array{definition_count:int,schema_version_status:string,duplicate_keys:array<int,string>,validation_failures:array<int,array<string,mixed>>}
     */
    public static function diagnostics(): array
    {
        $keys = [];
        $duplicates = [];
        $failures = [];
        $schemaCurrent = true;

        foreach (glob(self::DEFINITIONS_ROOT . '/*.json') ?: [] as $path) {
            $decoded = self::readJson($path);
            if ($decoded === null) {
                $failures[] = ['file' => basename($path), 'errors' => ['Invalid JSON.']];
                $schemaCurrent = false;
                continue;
            }
            $key = trim((string)($decoded['report_key'] ?? ''));
            if ($key !== '' && isset($keys[$key])) {
                $duplicates[] = $key;
            }
            if ($key !== '') {
                $keys[$key] = true;
            }
            $validation = ReportDefinitionValidator::validate($decoded);
            if (!$validation['valid']) {
                $failures[] = ['file' => basename($path), 'errors' => $validation['errors']];
            }
            if ((string)($decoded['schema_version'] ?? '') !== ReportDefinitionValidator::SCHEMA_VERSION) {
                $schemaCurrent = false;
            }
        }

        return [
            'definition_count' => count(glob(self::DEFINITIONS_ROOT . '/*.json') ?: []),
            'schema_version_status' => $schemaCurrent ? 'current' : 'attention_required',
            'duplicate_keys' => array_values(array_unique($duplicates)),
            'validation_failures' => $failures,
        ];
    }

    /**
     * @return array{ok:bool,definition?:array<string,mixed>,errors:array<int,string>,snapshot?:string,path?:string}
     */
    public static function save(array $definition, string $originalKey = ''): array
    {
        $existing = self::all();
        $existingKeys = array_values(array_filter(array_map(
            static fn(array $item): string => trim((string)($item['report_key'] ?? '')),
            $existing
        )));
        $validation = ReportDefinitionValidator::validate($definition, $existingKeys, $originalKey);
        if (!$validation['valid']) {
            return ['ok' => false, 'errors' => $validation['errors']];
        }

        if (!self::ensureDirectory(self::DEFINITIONS_ROOT) || !self::ensureDirectory(self::SNAPSHOT_ROOT)) {
            return ['ok' => false, 'errors' => ['Report definition storage is unavailable.']];
        }

        $reportKey = (string)$definition['report_key'];
        $targetPath = self::DEFINITIONS_ROOT . '/' . $reportKey . '.json';
        $existingDefinition = self::readJson($targetPath);
        $now = gmdate('c');
        $existingCreatedAt = trim((string)($existingDefinition['created_at'] ?? ''));
        $definition['created_at'] = $existingCreatedAt !== '' ? $existingCreatedAt : $now;
        $definition['updated_at'] = $now;

        $snapshotPath = self::SNAPSHOT_ROOT . '/' . $reportKey . '-' . gmdate('Ymd_His')
            . '-' . substr(sha1($reportKey . microtime(true)), 0, 10) . '.json';
        $snapshotPayload = [
            'report_key' => $reportKey,
            'target_path' => self::relativePath($targetPath),
            'previous_definition' => $existingDefinition,
            'proposed_definition' => $definition,
            'created_at' => $now,
        ];
        if (!self::writeJson($snapshotPath, $snapshotPayload)) {
            return ['ok' => false, 'errors' => ['Failed to create snapshot before write.']];
        }

        if (!self::writeJson($targetPath, $definition)) {
            return ['ok' => false, 'errors' => ['Failed to persist report definition.']];
        }

        return [
            'ok' => true,
            'errors' => [],
            'definition' => $definition,
            'snapshot' => self::relativePath($snapshotPath),
            'path' => self::relativePath($targetPath),
        ];
    }

    private static function readJson(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        $decoded = json_decode((string)@file_get_contents($path), true);
        return is_array($decoded) ? $decoded : null;
    }

    private static function writeJson(string $path, array $payload): bool
    {
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            return false;
        }
        $tmp = $path . '.tmp.' . bin2hex(random_bytes(6));
        if (@file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
            return false;
        }
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            return false;
        }
        return true;
    }

    private static function ensureDirectory(string $path): bool
    {
        return is_dir($path) || @mkdir($path, 0755, true) || is_dir($path);
    }

    private static function relativePath(string $path): string
    {
        return ltrim(str_replace(str_replace('\\', '/', APP_ROOT), '', str_replace('\\', '/', $path)), '/');
    }
}
