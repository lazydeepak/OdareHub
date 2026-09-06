<?php
declare(strict_types=1);

namespace Plugins\AdminTools\Services;

use App\Services\EnvironmentPortabilityHistoryService;
use App\Services\ExportHistoryService;
use App\Services\ImportHistoryService;
use App\Services\ReleaseHistoryService;
use App\Services\RestoreHistoryService;

final class ResilienceActivityMonitorService
{
    /** @return array<string,mixed> */
    public function snapshot(int $perSourceLimit = 20, int $activityLimit = 60): array
    {
        $sources = [];
        $errors = [];
        $readers = [
            'exports' => static fn (): array => (new ExportHistoryService())->recentRuns(null, null, $perSourceLimit),
            'imports' => static fn (): array => (new ImportHistoryService())->recentRuns(null, $perSourceLimit),
            'restores' => static fn (): array => (new RestoreHistoryService())->recentRuns(null, $perSourceLimit),
            'environment' => static fn (): array => (new EnvironmentPortabilityHistoryService())->recentRuns($perSourceLimit),
            'releases' => static fn (): array => (new ReleaseHistoryService())->recentRuns($perSourceLimit),
        ];

        foreach ($readers as $key => $reader) {
            try {
                $sources[$key] = $reader();
            } catch (\Throwable $e) {
                $sources[$key] = [];
                $errors[$key] = $e->getMessage();
            }
        }

        return self::compose($sources, $errors, $activityLimit);
    }

    /**
     * @param array<string,array<int,array<string,mixed>>> $sources
     * @param array<string,string> $errors
     * @return array<string,mixed>
     */
    public static function compose(array $sources, array $errors = [], int $activityLimit = 60): array
    {
        $definitions = self::sourceDefinitions();
        $activity = [];
        $coverage = [];

        foreach ($definitions as $sourceKey => $definition) {
            $rows = is_array($sources[$sourceKey] ?? null) ? $sources[$sourceKey] : [];
            $available = !array_key_exists($sourceKey, $errors);
            $coverage[] = [
                'key' => $sourceKey,
                'label_key' => $definition['label_key'],
                'owner_url' => $definition['owner_url'],
                'available' => $available,
                'record_count' => count($rows),
            ];
            if (!$available) {
                continue;
            }
            foreach ($rows as $row) {
                $activity[] = self::normalizeRow($sourceKey, $definition, $row);
            }
        }

        usort($activity, static function (array $left, array $right): int {
            $dateOrder = strcmp((string)($right['occurred_at'] ?? ''), (string)($left['occurred_at'] ?? ''));
            return $dateOrder !== 0 ? $dateOrder : strcmp((string)($right['record_key'] ?? ''), (string)($left['record_key'] ?? ''));
        });
        $activity = array_slice($activity, 0, max(1, $activityLimit));

        $summary = ['total' => count($activity), 'successful' => 0, 'attention' => 0, 'review' => 0];
        foreach ($activity as $item) {
            $bucket = (string)($item['status_bucket'] ?? 'review');
            if (isset($summary[$bucket])) {
                $summary[$bucket]++;
            }
        }

        return [
            'activity' => $activity,
            'coverage' => $coverage,
            'summary' => $summary,
            'available_source_count' => count($definitions) - count($errors),
            'source_count' => count($definitions),
            'degraded' => $errors !== [],
        ];
    }

    /** @return array<string,array{label_key:string,owner_url:string}> */
    private static function sourceDefinitions(): array
    {
        return [
            'exports' => ['label_key' => 'admin.resilience_map.source.exports', 'owner_url' => '/admin/system-tools/app-management'],
            'imports' => ['label_key' => 'admin.resilience_map.source.imports', 'owner_url' => '/apps/manufacturing/imports'],
            'restores' => ['label_key' => 'admin.resilience_map.source.restores', 'owner_url' => '/apps/manufacturing/restores'],
            'environment' => ['label_key' => 'admin.resilience_map.source.environment', 'owner_url' => '/admin/setup/environment'],
            'releases' => ['label_key' => 'admin.resilience_map.source.releases', 'owner_url' => '/admin/setup/release'],
        ];
    }

    /**
     * @param array{label_key:string,owner_url:string} $definition
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function normalizeRow(string $sourceKey, array $definition, array $row): array
    {
        $status = strtolower(trim((string)($row['status'] ?? 'unknown')));
        $warning = trim((string)($row['warning_text'] ?? ''));
        $error = trim((string)($row['error_text'] ?? ''));
        $bucket = self::statusBucket($status, $warning, $error);

        $target = match ($sourceKey) {
            'exports' => self::joinParts([$row['suite_key'] ?? '', $row['target_type'] ?? '', $row['target_key'] ?? '']),
            'imports' => self::joinParts([$row['suite_key'] ?? '', $row['import_type'] ?? '', $row['source_file'] ?? '']),
            'restores' => self::joinParts([$row['suite_key'] ?? '', $row['target_type'] ?? '', $row['target_key'] ?? '']),
            'environment' => self::joinParts([$row['scope_key'] ?? '', $row['source_environment'] ?? '', $row['target_environment'] ?? '']),
            'releases' => self::joinParts([$row['scope_key'] ?? '', $row['release_version'] ?? '', $row['package_name'] ?? '']),
            default => '',
        };
        $operation = match ($sourceKey) {
            'exports' => (string)($row['export_type'] ?? 'export'),
            'imports' => (string)($row['import_type'] ?? 'import'),
            'restores' => (string)($row['restore_type'] ?? 'restore'),
            'environment' => (string)($row['operation_type'] ?? 'environment'),
            'releases' => 'release',
            default => $sourceKey,
        };

        return [
            'record_key' => $sourceKey . ':' . (string)($row['id'] ?? ''),
            'source_key' => $sourceKey,
            'source_label_key' => $definition['label_key'],
            'owner_url' => $definition['owner_url'],
            'operation' => $operation,
            'target' => $target !== '' ? $target : '—',
            'status' => $status !== '' ? $status : 'unknown',
            'status_bucket' => $bucket,
            'occurred_at' => (string)($row['created_at'] ?? ''),
            'actor' => (string)($row['created_by'] ?? ''),
            'message' => $error !== '' ? $error : $warning,
        ];
    }

    private static function statusBucket(string $status, string $warning, string $error): string
    {
        if ($error !== '' || in_array($status, ['failed', 'error'], true)) {
            return 'attention';
        }
        if ($warning !== '') {
            return 'attention';
        }
        if (in_array($status, ['completed', 'imported', 'restored', 'created', 'applied', 'generated'], true)) {
            return 'successful';
        }
        return 'review';
    }

    /** @param array<int,mixed> $parts */
    private static function joinParts(array $parts): string
    {
        $clean = array_values(array_filter(array_map(static fn ($part): string => trim((string)$part), $parts), static fn (string $part): bool => $part !== ''));
        return implode(' · ', $clean);
    }
}
