<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

trait StudioDeletionImpactDiscoveryTargetTrait
{
    /** @param array<string,mixed> $request @param array<string,mixed> $ownerDiscovery @return array<string,mixed> */
    private static function resolveTarget(array $request, array $ownerDiscovery): array
    {
        $diagnostics = [];
        $owners = self::ownersFromDiscovery($ownerDiscovery);
        $ownerKey = trim((string)($request['owner_key'] ?? ''));
        $targetType = strtolower(trim((string)($request['target_type'] ?? self::TARGET_OWNER)));

        if (!in_array($targetType, [self::TARGET_OWNER, self::TARGET_COMPONENT], true)) {
            $diagnostics[] = self::diagnostic('DELETION_TARGET_TYPE_INVALID', 'error', 'Deletion target type must be owner or component.', '');
        }

        $owner = self::ownerByKey($ownerKey, $owners);
        if ($ownerKey === '' || $owner === []) {
            $diagnostics[] = self::diagnostic('DELETION_OWNER_NOT_FOUND', 'error', 'The deletion target owner could not be resolved.', $ownerKey);
        }

        if ($diagnostics !== []) {
            return ['status' => 'error', 'target' => [], 'diagnostics' => $diagnostics];
        }

        $ownerPath = self::normalizeRelativePath((string)($owner['relative_path'] ?? $owner['owner_root_relative_path'] ?? ''));
        $targetPath = $ownerPath;
        if ($targetType === self::TARGET_COMPONENT) {
            $targetPath = self::normalizeRelativePath((string)($request['target_path'] ?? ''));
            if ($targetPath === '') {
                $diagnostics[] = self::diagnostic('DELETION_COMPONENT_PATH_MISSING', 'error', 'Component deletion requires a repository-relative target path.', '');
            } elseif (!self::pathWithin($targetPath, $ownerPath) || $targetPath === $ownerPath) {
                $diagnostics[] = self::diagnostic('DELETION_COMPONENT_OUTSIDE_OWNER', 'error', 'Component deletion target must be contained by the selected owner.', $targetPath);
            }
        }

        if ($ownerPath === '') {
            $diagnostics[] = self::diagnostic('DELETION_OWNER_PATH_MISSING', 'error', 'The selected owner has no canonical repository-relative path.', $ownerKey);
        }

        if ($diagnostics !== []) {
            return ['status' => 'error', 'target' => [], 'diagnostics' => $diagnostics];
        }

        return [
            'status' => 'ok',
            'target' => [
                'target_type' => $targetType,
                'owner_key' => $ownerKey,
                'owner_type' => (string)($owner['owner_type'] ?? ''),
                'display_label' => (string)($owner['display_label'] ?? $ownerKey),
                'owner_path' => $ownerPath,
                'target_path' => $targetPath,
            ],
            'diagnostics' => [],
        ];
    }

    /** @param array<string,mixed> $referenceDiscovery @param array<string,mixed> $target @return array<int,array<string,mixed>> */
    private static function matchingReferenceItems(array $referenceDiscovery, array $target): array
    {
        $items = isset($referenceDiscovery['items']) && is_array($referenceDiscovery['items'])
            ? $referenceDiscovery['items']
            : [];
        $matches = [];
        $targetPath = self::normalizeRelativePath((string)($target['target_path'] ?? ''));
        $ownerKey = (string)($target['owner_key'] ?? '');
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $requestId = (string)($item['request_id'] ?? '');
            $sourcePath = self::normalizeRelativePath((string)($item['source_path'] ?? ''));
            if ($requestId === 'deletion-impact:path' && $sourcePath === $targetPath) {
                $matches[] = $item;
                continue;
            }
            if ((string)($target['target_type'] ?? '') === self::TARGET_OWNER
                && $requestId === 'deletion-impact:owner-key'
                && (string)($item['source_path'] ?? '') === $ownerKey) {
                $matches[] = $item;
            }
        }
        return $matches;
    }

    /** @param array<int,array<string,mixed>> $referenceItems @param array<string,mixed> $target @return array<int,array<string,mixed>> */
    private static function mergedReferences(array $referenceItems, array $target): array
    {
        $merged = [];
        $ownerKey = (string)($target['owner_key'] ?? '');
        foreach ($referenceItems as $item) {
            $requestId = (string)($item['request_id'] ?? '');
            $references = isset($item['references']) && is_array($item['references'])
                ? $item['references']
                : [];
            foreach ($references as $reference) {
                if (!is_array($reference)) {
                    continue;
                }
                if ($requestId === 'deletion-impact:owner-key'
                    && ((string)($reference['match_type'] ?? '') !== 'exact path'
                        || (string)($reference['matched_pattern'] ?? '') !== $ownerKey)) {
                    continue;
                }
                $signature = (string)($reference['file_path'] ?? '')
                    . ':' . (string)($reference['line_number'] ?? 0)
                    . ':' . (string)($reference['matched_text_excerpt'] ?? '');
                if (!isset($merged[$signature]) || self::referenceScore($reference) > self::referenceScore($merged[$signature])) {
                    $merged[$signature] = $reference;
                }
            }
        }
        return array_values($merged);
    }

    /** @param array<string,mixed> $reference */
    private static function referenceScore(array $reference): int
    {
        $confidenceScore = ['low' => 1000, 'medium' => 2000, 'high' => 3000][(string)($reference['confidence'] ?? 'low')] ?? 0;
        return $confidenceScore + strlen((string)($reference['matched_pattern'] ?? ''));
    }

    /** @param array<string,mixed> $ownerDiscovery @return array<int,array<string,mixed>> */
    private static function ownersFromDiscovery(array $ownerDiscovery): array
    {
        return isset($ownerDiscovery['owners']) && is_array($ownerDiscovery['owners'])
            ? array_values(array_filter($ownerDiscovery['owners'], 'is_array'))
            : [];
    }

    /** @param array<int,array<string,mixed>> $owners @return array<string,mixed> */
    private static function ownerByKey(string $ownerKey, array $owners): array
    {
        foreach ($owners as $owner) {
            if ((string)($owner['owner_key'] ?? '') === $ownerKey) {
                return $owner;
            }
        }
        return [];
    }

    /** @param array<int,array<string,mixed>> $owners @return array<string,mixed> */
    private static function ownerForPath(string $filePath, array $owners): array
    {
        $best = [];
        $bestLength = -1;
        foreach ($owners as $owner) {
            $ownerPath = self::normalizeRelativePath((string)($owner['relative_path'] ?? $owner['owner_root_relative_path'] ?? ''));
            if ($ownerPath === '' || !self::pathWithin($filePath, $ownerPath)) {
                continue;
            }
            if (strlen($ownerPath) > $bestLength) {
                $best = $owner;
                $bestLength = strlen($ownerPath);
            }
        }
        return $best;
    }
}
