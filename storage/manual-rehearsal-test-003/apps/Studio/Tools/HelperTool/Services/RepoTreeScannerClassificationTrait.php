<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\HelperTool\Services;

trait RepoTreeScannerClassificationTrait
{
    /**
     * @return array<string,mixed>
     */
    private static function emptySummary(): array
    {
        return [
            'file_types_raw' => [],
            'suspicious_summary' => [
                'total_count' => 0,
                'categories' => [
                    'review_candidates' => self::emptySuspiciousCategory('Review Candidates'),
                    'runtime_dependency_artifacts' => self::emptySuspiciousCategory('Runtime/Dependency Artifacts'),
                    'snapshot_recovery_artifacts' => self::emptySuspiciousCategory('Snapshot/Recovery Artifacts'),
                    'unknown_needs_classification' => self::emptySuspiciousCategory('Unknown/Needs Classification'),
                ],
            ],
            'category_paths' => [],
            'skipped_paths' => [],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function emptySuspiciousCategory(string $label): array
    {
        return [
            'label' => $label,
            'count' => 0,
            'total_bytes' => 0,
            'examples' => [],
        ];
    }

    /**
     * @param array<string,mixed> $summary
     * @return array{categories: array<int,string>, reasons: array<int,string>}
     */
    private static function recordFile(array &$summary, string $relativePath, string $name, int $size): array
    {
        $typeKey = self::fileTypeKey($name);
        if (!isset($summary['file_types_raw'][$typeKey])) {
            $summary['file_types_raw'][$typeKey] = [
                'type' => $typeKey,
                'label' => self::fileTypeLabel($typeKey),
                'count' => 0,
                'total_bytes' => 0,
            ];
        }
        $summary['file_types_raw'][$typeKey]['count']++;
        $summary['file_types_raw'][$typeKey]['total_bytes'] += $size;

        $classification = self::classifyFile($relativePath, $name, $typeKey, $size);
        foreach ($classification['entries'] as $entry) {
            self::recordSuspicious(
                $summary,
                (string)$entry['category'],
                $relativePath,
                $size,
                (string)$entry['reason']
            );
        }
        return [
            'categories' => $classification['categories'],
            'reasons' => $classification['reasons'],
        ];
    }

    /**
     * @param array<string,mixed> $summary
     */
    private static function recordSuspicious(array &$summary, string $category, string $relativePath, int $size, string $reason): void
    {
        if (!isset($summary['suspicious_summary']['categories'][$category])) {
            return;
        }
        if (!isset($summary['category_paths'][$category])) {
            $summary['category_paths'][$category] = [];
        }
        if (isset($summary['category_paths'][$category][$relativePath])) {
            return;
        }

        $summary['category_paths'][$category][$relativePath] = true;
        $summary['suspicious_summary']['total_count']++;
        $summary['suspicious_summary']['categories'][$category]['count']++;
        $summary['suspicious_summary']['categories'][$category]['total_bytes'] += $size;

        if (count($summary['suspicious_summary']['categories'][$category]['examples']) < self::SUSPICIOUS_EXAMPLE_LIMIT) {
            $summary['suspicious_summary']['categories'][$category]['examples'][] = [
                'path' => $relativePath,
                'bytes' => $size,
                'reason' => $reason,
            ];
        }
    }

    /**
     * @param array<string,mixed> $summary
     */
    private static function recordSkippedPath(array &$summary, string $relativePath, string $reason): void
    {
        if (count($summary['skipped_paths']) >= self::SUSPICIOUS_EXAMPLE_LIMIT) {
            return;
        }

        $summary['skipped_paths'][] = [
            'path' => $relativePath,
            'reason' => $reason,
        ];
    }

    private static function fileTypeKey(string $name): string
    {
        $lowerName = strtolower($name);
        $extension = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
        if ($extension !== '') {
            return $extension;
        }

        if (in_array($lowerName, self::KNOWN_EXTENSIONLESS_FILENAMES, true)) {
            return 'extensionless_known';
        }

        return 'unknown_extension';
    }

    private static function fileTypeLabel(string $typeKey): string
    {
        if ($typeKey === 'extensionless_known') {
            return 'Known extensionless';
        }
        if ($typeKey === 'unknown_extension') {
            return 'Unknown extension';
        }
        return '.' . $typeKey;
    }

    /**
     * @return array{entries: array<int,array{category:string,reason:string}>, categories: array<int,string>, reasons: array<int,string>}
     */
    private static function classifyFile(string $relativePath, string $name, string $typeKey, int $size): array
    {
        $entries = [];

        if ($name === '.DS_Store') {
            $entries[] = ['category' => 'review_candidates', 'reason' => 'ds_store'];
            return self::normalizeClassificationEntries($entries);
        }

        if (self::isSnapshotRecoveryArtifact($relativePath)) {
            $entries[] = ['category' => 'snapshot_recovery_artifacts', 'reason' => 'snapshot_backup'];
            return self::normalizeClassificationEntries($entries);
        }

        if (self::isRuntimeDependencyArtifact($relativePath, $name)) {
            $entries[] = ['category' => 'runtime_dependency_artifacts', 'reason' => 'dependency_runtime'];
            return self::normalizeClassificationEntries($entries);
        }

        if (preg_match(self::BACKUP_TEMP_FILE_PATTERN, $name) === 1) {
            $entries[] = ['category' => 'review_candidates', 'reason' => 'backup_temp'];
        }
        if ($size >= self::OVERSIZED_FILE_BYTES) {
            $entries[] = ['category' => 'review_candidates', 'reason' => 'oversized'];
        }
        if (self::isReviewTmpLikePath($relativePath)) {
            $entries[] = ['category' => 'review_candidates', 'reason' => 'generated_tmp_path'];
        }
        if ($typeKey === 'unknown_extension') {
            $entries[] = ['category' => 'unknown_needs_classification', 'reason' => 'unknown_extension'];
        }

        return self::normalizeClassificationEntries($entries);
    }

    /**
     * @param array<int,array{category:string,reason:string}> $entries
     * @return array{entries: array<int,array{category:string,reason:string}>, categories: array<int,string>, reasons: array<int,string>}
     */
    private static function normalizeClassificationEntries(array $entries): array
    {
        $categories = [];
        $reasons = [];
        foreach ($entries as $entry) {
            $categories[$entry['category']] = $entry['category'];
            $reasons[$entry['reason']] = $entry['reason'];
        }

        return [
            'entries' => $entries,
            'categories' => array_values($categories),
            'reasons' => array_values($reasons),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $children
     * @return array<int,string>
     */
    private static function aggregateChildCategories(array $children): array
    {
        $categories = [];
        foreach ($children as $child) {
            $childCategories = isset($child['artifact_categories']) && is_array($child['artifact_categories'])
                ? $child['artifact_categories']
                : [];
            foreach ($childCategories as $category) {
                $category = (string)$category;
                if ($category !== '') {
                    $categories[$category] = $category;
                }
            }
        }
        return array_values($categories);
    }

    private static function isReviewTmpLikePath(string $relativePath): bool
    {
        foreach (self::REVIEW_TMP_PATH_PATTERNS as $pattern) {
            if (preg_match($pattern, $relativePath) === 1) {
                return true;
            }
        }
        return false;
    }

    private static function isRuntimeDependencyArtifact(string $relativePath, string $name): bool
    {
        foreach (self::RUNTIME_DEPENDENCY_PATH_PATTERNS as $pattern) {
            if (preg_match($pattern, $relativePath) === 1) {
                return true;
            }
        }

        return preg_match('#(^|/)vendor/bin/' . preg_quote($name, '#') . '$#i', $relativePath) === 1;
    }

    private static function isSnapshotRecoveryArtifact(string $relativePath): bool
    {
        foreach (self::SNAPSHOT_RECOVERY_PATH_PATTERNS as $pattern) {
            if (preg_match($pattern, $relativePath) === 1) {
                return true;
            }
        }
        return false;
    }
    /**
     * @param array<string,mixed> $summary
     * @param array<string,int> $counters
     * @return array<string,mixed>
     */
    private static function finalizeSummary(array $summary, array $counters, int $totalBytes, int $durationMs): array
    {
        $rawTypes = array_values($summary['file_types_raw']);
        usort($rawTypes, static function (array $a, array $b): int {
            $countCompare = (int)($b['count'] ?? 0) <=> (int)($a['count'] ?? 0);
            if ($countCompare !== 0) {
                return $countCompare;
            }
            $bytesCompare = (int)($b['total_bytes'] ?? 0) <=> (int)($a['total_bytes'] ?? 0);
            if ($bytesCompare !== 0) {
                return $bytesCompare;
            }
            return strcasecmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
        });

        $topTypes = array_slice($rawTypes, 0, self::FILE_TYPE_TOP_LIMIT);
        $otherTypes = array_slice($rawTypes, self::FILE_TYPE_TOP_LIMIT);
        if ($otherTypes !== []) {
            $other = [
                'type' => 'other',
                'label' => 'Other',
                'count' => 0,
                'total_bytes' => 0,
            ];
            foreach ($otherTypes as $type) {
                $other['count'] += (int)($type['count'] ?? 0);
                $other['total_bytes'] += (int)($type['total_bytes'] ?? 0);
            }
            $topTypes[] = $other;
        }

        $summary['inventory_summary'] = [
            'total_folders' => (int)($counters['dirs'] ?? 0),
            'total_files' => (int)($counters['files'] ?? 0),
            'total_bytes' => $totalBytes,
            'skipped_paths' => (int)($counters['skipped'] ?? 0),
            'scan_duration_ms' => $durationMs,
        ];
        $summary['file_type_summary'] = [
            'top_limit' => self::FILE_TYPE_TOP_LIMIT,
            'total_types' => count($rawTypes),
            'types' => $topTypes,
        ];

        unset($summary['file_types_raw']);
        unset($summary['category_paths']);
        return $summary;
    }
}
