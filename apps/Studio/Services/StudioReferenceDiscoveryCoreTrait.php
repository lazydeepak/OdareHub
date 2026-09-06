<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

trait StudioReferenceDiscoveryCoreTrait
{
    /**
     * @param array<int,array<string,mixed>> $requests
     * @return array<string,mixed>
     */
    public static function discover(array $requests, ?string $root = null): array
    {
        $requestedRoot = $root ?? (defined('APP_ROOT') ? (string)APP_ROOT : '');
        $realRoot = $requestedRoot !== '' ? realpath($requestedRoot) : false;
        if ($realRoot === false || !is_dir($realRoot)) {
            return [
                'status' => 'error',
                'summary' => [
                    'request_count' => count($requests),
                    'reference_count' => 0,
                    'files_referencing' => 0,
                ],
                'items' => [],
                'search_scope' => self::searchScope($requestedRoot, 0),
                'diagnostics' => [[
                    'code' => 'REFERENCE_ROOT_UNAVAILABLE',
                    'severity' => 'error',
                    'message' => 'Reference discovery root is unavailable.',
                    'path' => $requestedRoot,
                ]],
            ];
        }

        $rootPath = rtrim((string)$realRoot, DIRECTORY_SEPARATOR);
        $files = self::sourceFiles($rootPath);
        $items = [];
        $diagnostics = [];

        foreach ($requests as $index => $request) {
            if (!is_array($request)) {
                $diagnostics[] = [
                    'code' => 'REFERENCE_REQUEST_INVALID',
                    'severity' => 'warning',
                    'message' => 'Reference discovery request must be an array.',
                    'path' => '',
                ];
                continue;
            }

            $source = trim((string)($request['source_path'] ?? ''));
            $target = trim((string)($request['target_path'] ?? ''));
            if ($source === '') {
                $diagnostics[] = [
                    'code' => 'REFERENCE_SOURCE_MISSING',
                    'severity' => 'warning',
                    'message' => 'Reference discovery request has no source path.',
                    'path' => '',
                ];
            }

            $references = self::findReferences($files, $rootPath, $request);
            $referenceFiles = array_values(array_unique(array_map(
                static fn(array $reference): string => (string)($reference['file_path'] ?? ''),
                $references
            )));

            $items[] = [
                'request_id' => (string)($request['request_id'] ?? 'reference-' . ($index + 1)),
                'operation_id' => (string)($request['operation_id'] ?? ''),
                'operation_type' => (string)($request['operation_type'] ?? ''),
                'owner_key' => (string)($request['owner_key'] ?? ''),
                'source_path' => $source,
                'target_path' => $target,
                'reference_key' => (string)($request['reference_key'] ?? self::referenceKey($source, $target)),
                'references' => $references,
                'reference_count' => count($references),
                'files_referencing' => $referenceFiles,
                'confidence' => self::overallConfidence($references),
                'relevance_summary' => self::relevanceSummary($references),
                'category_summary' => self::categorySummary($references),
            ];
        }

        $allFiles = [];
        $referenceCount = 0;
        foreach ($items as $item) {
            $referenceCount += (int)($item['reference_count'] ?? 0);
            foreach (($item['files_referencing'] ?? []) as $filePath) {
                $filePath = (string)$filePath;
                if ($filePath !== '') {
                    $allFiles[$filePath] = true;
                }
            }
        }

        return [
            'status' => $diagnostics === [] ? 'ok' : 'partial',
            'summary' => [
                'request_count' => count($items),
                'reference_count' => $referenceCount,
                'files_referencing' => count($allFiles),
            ],
            'items' => $items,
            'search_scope' => self::searchScope('.', count($files)),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<int,string> $files
     * @param array<string,mixed> $request
     * @return array<int,array<string,mixed>>
     */
    private static function findReferences(array $files, string $rootPath, array $request): array
    {
        $source = (string)($request['source_path'] ?? '');
        $target = (string)($request['target_path'] ?? '');
        $patterns = self::patterns($source, $target, (string)($request['owner_key'] ?? ''));
        $includePrefixes = self::normalizedPrefixes($request['include_path_prefixes'] ?? []);
        $excludePrefixes = self::normalizedPrefixes($request['exclude_path_prefixes'] ?? []);
        $references = [];
        $seen = [];
        $bestByLine = [];

        foreach ($files as $file) {
            $relative = self::relativePath($file, $rootPath);
            if (!self::pathAllowed($relative, $includePrefixes, $excludePrefixes)) {
                continue;
            }

            $contents = @file_get_contents($file);
            if (!is_string($contents) || $contents === '' || str_contains($contents, "\0")) {
                continue;
            }
            $lines = preg_split('/\R/', $contents);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $index => $line) {
                $lineText = (string)$line;
                foreach ($patterns as $pattern) {
                    $needle = (string)$pattern['needle'];
                    if ($needle === '' || !str_contains($lineText, $needle)) {
                        continue;
                    }

                    $key = $relative . ':' . ($index + 1) . ':' . $needle . ':' . (string)$pattern['match_type'];
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $confidence = (string)$pattern['confidence'];
                    $reference = [
                        'file_path' => $relative,
                        'line_number' => $index + 1,
                        'matched_pattern' => $needle,
                        'current_reference' => $needle,
                        'proposed_replacement' => self::proposedReplacement((string)$pattern['match_type'], $needle, $source, $target),
                        'matched_text_excerpt' => self::excerpt($lineText, $needle),
                        'match_type' => (string)$pattern['match_type'],
                        'confidence' => $confidence,
                        'relevance' => self::categorizeRelevance($relative, (string)$pattern['match_type'], $confidence),
                    ];
                    $reference['category'] = self::categoryFromRelevance((string)$reference['relevance']);

                    $lineSignature = $relative . ':' . ($index + 1) . ':' . (string)$reference['matched_text_excerpt'];
                    if (!isset($bestByLine[$lineSignature])) {
                        $references[] = $reference;
                        $bestByLine[$lineSignature] = count($references) - 1;
                        continue;
                    }

                    $existingIndex = (int)$bestByLine[$lineSignature];
                    $existingReference = isset($references[$existingIndex]) && is_array($references[$existingIndex])
                        ? $references[$existingIndex]
                        : [];
                    if (self::shouldPreferReference($reference, $existingReference)) {
                        $references[$existingIndex] = $reference;
                    }
                }
            }
        }

        usort($references, static function (array $left, array $right): int {
            $leftKey = (string)($left['file_path'] ?? '') . ':' . (string)($left['line_number'] ?? '') . ':' . (string)($left['match_type'] ?? '');
            $rightKey = (string)($right['file_path'] ?? '') . ':' . (string)($right['line_number'] ?? '') . ':' . (string)($right['match_type'] ?? '');
            return strcasecmp($leftKey, $rightKey);
        });

        return $references;
    }

    /** @return array<int,string> */
    private static function sourceFiles(string $rootPath): array
    {
        $files = [];
        $stack = [$rootPath];

        while ($stack !== []) {
            $dir = array_pop($stack);
            if (!is_string($dir) || self::isExcludedPath($dir, $rootPath)) {
                continue;
            }
            $entries = @scandir($dir);
            if ($entries === false) {
                continue;
            }
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $path = $dir . DIRECTORY_SEPARATOR . $entry;
                if (is_link($path) || self::isExcludedPath($path, $rootPath)) {
                    continue;
                }
                if (is_dir($path)) {
                    $stack[] = $path;
                    continue;
                }
                if (is_file($path) && self::isIncludedTextFile($path)) {
                    $files[] = $path;
                }
            }
        }

        usort($files, static fn(string $left, string $right): int => strcasecmp($left, $right));
        return $files;
    }
}
