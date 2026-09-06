<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

trait StudioDeletionImpactDiscoveryCoreTrait
{
    /**
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    public static function discover(array $request, ?string $root = null): array
    {
        $ownerDiscovery = StudioOwnerDiscoveryService::discover(
            $root,
            StudioOwnerDiscoveryService::PROFILE_CANONICAL
        );
        $targetResolution = self::resolveTarget($request, $ownerDiscovery);
        if (($targetResolution['status'] ?? '') === 'error') {
            return self::errorResult($targetResolution, $ownerDiscovery, []);
        }

        $rootPath = (string)($ownerDiscovery['root_path'] ?? '');
        $target = is_array($targetResolution['target'] ?? null) ? $targetResolution['target'] : [];
        $targetPath = (string)($target['target_path'] ?? '');
        $absoluteTargetPath = $rootPath !== ''
            ? $rootPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $targetPath)
            : '';

        if ($absoluteTargetPath === '' || !file_exists($absoluteTargetPath)) {
            $targetResolution['status'] = 'error';
            $targetResolution['diagnostics'][] = [
                'code' => 'DELETION_TARGET_NOT_FOUND',
                'severity' => 'error',
                'message' => 'The requested deletion target does not exist under the repository root.',
                'path' => $targetPath,
            ];
            return self::errorResult($targetResolution, $ownerDiscovery, []);
        }

        $excludePrefixes = array_values(array_unique(array_merge(
            [$targetPath],
            self::normalizePrefixList($request['exclude_path_prefixes'] ?? [])
        )));
        $includePrefixes = self::normalizePrefixList($request['include_path_prefixes'] ?? []);
        $referenceRequests = [[
            'request_id' => 'deletion-impact:path',
            'operation_type' => 'deletion_impact',
            'owner_key' => (string)($target['owner_key'] ?? ''),
            'source_path' => $targetPath,
            'target_path' => self::referenceProbeTarget($targetPath),
            'exclude_path_prefixes' => $excludePrefixes,
            'include_path_prefixes' => $includePrefixes,
        ]];
        if ((string)($target['target_type'] ?? '') === self::TARGET_OWNER) {
            $referenceRequests[] = [
                'request_id' => 'deletion-impact:owner-key',
                'operation_type' => 'deletion_impact',
                'owner_key' => (string)($target['owner_key'] ?? ''),
                'source_path' => (string)($target['owner_key'] ?? ''),
                'target_path' => '',
                'exclude_path_prefixes' => $excludePrefixes,
                'include_path_prefixes' => $includePrefixes,
            ];
        }

        $referenceDiscovery = StudioReferenceDiscoveryService::discover($referenceRequests, $rootPath);
        return self::compose($request, $ownerDiscovery, $referenceDiscovery);
    }

    public static function compose(array $request, array $ownerDiscovery, array $referenceDiscovery): array
    {
        $targetResolution = self::resolveTarget($request, $ownerDiscovery);
        if (($targetResolution['status'] ?? '') === 'error') {
            return self::errorResult($targetResolution, $ownerDiscovery, $referenceDiscovery);
        }

        $target = is_array($targetResolution['target'] ?? null) ? $targetResolution['target'] : [];
        $targetPath = (string)($target['target_path'] ?? '');
        $owners = self::ownersFromDiscovery($ownerDiscovery);
        $referenceItems = self::matchingReferenceItems($referenceDiscovery, $target);
        $references = self::mergedReferences($referenceItems, $target);
        $hasPathEvidence = false;
        foreach ($referenceItems as $referenceItem) {
            if ((string)($referenceItem['request_id'] ?? '') === 'deletion-impact:path') {
                $hasPathEvidence = true;
                break;
            }
        }

        $enrichedReferences = [];
        $dependentOwners = [];
        $files = [];
        $runtimeBlockers = 0;
        $reviewRequired = 0;
        $cleanupOnly = 0;

        foreach ($references as $reference) {
            $filePath = self::normalizeRelativePath((string)($reference['file_path'] ?? ''));
            if ($filePath === '' || self::pathWithin($filePath, $targetPath)) {
                continue;
            }

            $referencingOwner = self::ownerForPath($filePath, $owners);
            $ownerKey = (string)($referencingOwner['owner_key'] ?? '');
            $scopeKey = $ownerKey !== '' ? $ownerKey : '@repository';
            $severity = self::referenceSeverity($reference, $ownerKey);
            if ($severity === 'blocking') {
                $runtimeBlockers++;
            } elseif ($severity === 'review') {
                $reviewRequired++;
            } else {
                $cleanupOnly++;
            }

            $files[$filePath] = true;
            $reference['referencing_owner_key'] = $ownerKey;
            $reference['referencing_owner_type'] = (string)($referencingOwner['owner_type'] ?? '');
            $reference['impact_severity'] = $severity;
            $enrichedReferences[] = $reference;

            if (!isset($dependentOwners[$scopeKey])) {
                $dependentOwners[$scopeKey] = [
                    'owner_key' => $ownerKey,
                    'owner_type' => (string)($referencingOwner['owner_type'] ?? ''),
                    'scope' => $ownerKey !== '' ? 'owner' : 'repository',
                    'severity' => 'cleanup',
                    'reference_count' => 0,
                    'files' => [],
                    'relevance_summary' => [],
                ];
            }

            $dependentOwners[$scopeKey]['reference_count']++;
            $dependentOwners[$scopeKey]['files'][$filePath] = true;
            $relevance = (string)($reference['relevance'] ?? '');
            if ($relevance !== '') {
                $dependentOwners[$scopeKey]['relevance_summary'][$relevance] =
                    (int)($dependentOwners[$scopeKey]['relevance_summary'][$relevance] ?? 0) + 1;
            }
            $dependentOwners[$scopeKey]['severity'] = self::higherSeverity(
                (string)$dependentOwners[$scopeKey]['severity'],
                $severity
            );
        }

        foreach ($dependentOwners as &$dependentOwner) {
            $dependentOwner['files'] = array_keys((array)$dependentOwner['files']);
            sort($dependentOwner['files'], SORT_NATURAL | SORT_FLAG_CASE);
            ksort($dependentOwner['relevance_summary']);
        }
        unset($dependentOwner);

        $dependentOwners = array_values($dependentOwners);
        usort($dependentOwners, static function (array $left, array $right): int {
            $severityOrder = ['blocking' => 0, 'review' => 1, 'cleanup' => 2];
            $severityCompare = ($severityOrder[(string)($left['severity'] ?? 'cleanup')] ?? 9)
                <=> ($severityOrder[(string)($right['severity'] ?? 'cleanup')] ?? 9);
            if ($severityCompare !== 0) {
                return $severityCompare;
            }
            return strcasecmp((string)($left['owner_key'] ?? ''), (string)($right['owner_key'] ?? ''));
        });

        usort($enrichedReferences, static function (array $left, array $right): int {
            $leftKey = (string)($left['file_path'] ?? '') . ':' . (string)($left['line_number'] ?? 0);
            $rightKey = (string)($right['file_path'] ?? '') . ':' . (string)($right['line_number'] ?? 0);
            return strcasecmp($leftKey, $rightKey);
        });

        $referenceStatus = (string)($referenceDiscovery['status'] ?? 'error');
        if (!$hasPathEvidence && $referenceStatus !== 'error') {
            $referenceStatus = 'error';
        }
        [$readiness, $safeToDelete, $blockingReasons] = self::readiness(
            $referenceStatus,
            $runtimeBlockers,
            $reviewRequired,
            $cleanupOnly
        );

        $diagnostics = array_values(array_merge(
            self::diagnosticsFrom($ownerDiscovery),
            self::diagnosticsFrom($referenceDiscovery),
            is_array($targetResolution['diagnostics'] ?? null) ? $targetResolution['diagnostics'] : [],
            $hasPathEvidence ? [] : [[
                'code' => 'DELETION_REFERENCE_EVIDENCE_MISSING',
                'severity' => 'error',
                'message' => 'Reference discovery returned no evidence item for the deletion target path.',
                'path' => $targetPath,
            ]]
        ));

        return [
            'status' => $referenceStatus === 'error' ? 'partial' : ($diagnostics === [] ? 'ok' : 'partial'),
            'effect' => self::EFFECT,
            'target' => $target,
            'summary' => [
                'reference_count' => count($enrichedReferences),
                'files_referencing' => count($files),
                'dependent_owner_count' => count($dependentOwners),
                'runtime_blockers' => $runtimeBlockers,
                'review_required' => $reviewRequired,
                'cleanup_only' => $cleanupOnly,
            ],
            'deletion_readiness' => $readiness,
            'safe_to_delete' => $safeToDelete,
            'blocking_reasons' => $blockingReasons,
            'dependent_owners' => $dependentOwners,
            'references' => $enrichedReferences,
            'excluded_self_scope' => $targetPath,
            'evidence' => [
                'owner_discovery_status' => self::discoveryStatus($ownerDiscovery),
                'reference_discovery_status' => $referenceStatus,
                'reference_search_scope' => is_array($referenceDiscovery['search_scope'] ?? null)
                    ? $referenceDiscovery['search_scope']
                    : [],
            ],
            'diagnostics' => $diagnostics,
        ];
    }
}
