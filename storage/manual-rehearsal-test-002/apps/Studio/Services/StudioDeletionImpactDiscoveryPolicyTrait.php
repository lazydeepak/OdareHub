<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

trait StudioDeletionImpactDiscoveryPolicyTrait
{
    /** @param array<string,mixed> $reference */
    private static function referenceSeverity(array $reference, string $referencingOwnerKey): string
    {
        $relevance = (string)($reference['relevance'] ?? '');
        if ($relevance === StudioReferenceDiscoveryService::RELEVANCE_RUNTIME_BLOCKING) {
            return 'blocking';
        }
        if (in_array($relevance, [
            StudioReferenceDiscoveryService::RELEVANCE_STUDIO_TOOLING,
            StudioReferenceDiscoveryService::RELEVANCE_LOW_CONFIDENCE_TEXT,
        ], true)) {
            return 'review';
        }
        if ($relevance === '' && $referencingOwnerKey === '') {
            return 'review';
        }
        return 'cleanup';
    }

    /** @return array{0:string,1:string,2:array<int,string>} */
    private static function readiness(string $referenceStatus, int $runtimeBlockers, int $reviewRequired, int $cleanupOnly): array
    {
        if ($referenceStatus === 'error' || $referenceStatus === '') {
            return [self::READINESS_UNKNOWN, 'no', ['REFERENCE_DISCOVERY_INCOMPLETE']];
        }
        if ($runtimeBlockers > 0) {
            return [self::READINESS_BLOCKED, 'no', ['RUNTIME_REFERENCES_EXIST']];
        }
        if ($reviewRequired > 0) {
            return [self::READINESS_NEEDS_REVIEW, 'review', ['TOOLING_OR_LOW_CONFIDENCE_REFERENCES_REQUIRE_REVIEW']];
        }
        if ($cleanupOnly > 0) {
            return [self::READINESS_READY_WITH_CLEANUP, 'yes', []];
        }
        return [self::READINESS_READY, 'yes', []];
    }

    private static function higherSeverity(string $current, string $candidate): string
    {
        $order = ['cleanup' => 1, 'review' => 2, 'blocking' => 3];
        return ($order[$candidate] ?? 0) > ($order[$current] ?? 0) ? $candidate : $current;
    }

    private static function referenceProbeTarget(string $sourcePath): string
    {
        if ($sourcePath === '' || pathinfo($sourcePath, PATHINFO_EXTENSION) !== '') {
            return '';
        }
        $directory = dirname($sourcePath);
        $directory = $directory === '.' ? '' : str_replace('\\', '/', $directory);
        $placeholder = '__studio_deleted_' . basename($sourcePath);
        return $directory === '' ? $placeholder : $directory . '/' . $placeholder;
    }

    private static function pathWithin(string $path, string $prefix): bool
    {
        $path = self::normalizeRelativePath($path);
        $prefix = rtrim(self::normalizeRelativePath($prefix), '/');
        return $prefix !== '' && ($path === $prefix || str_starts_with($path, $prefix . '/'));
    }

    private static function normalizeRelativePath(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', trim($path)), '/');
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                return '';
            }
            $parts[] = $part;
        }
        return implode('/', $parts);
    }

    /** @param mixed $prefixes @return array<int,string> */
    private static function normalizePrefixList($prefixes): array
    {
        if (!is_array($prefixes)) {
            return [];
        }
        $normalized = [];
        foreach ($prefixes as $prefix) {
            $path = self::normalizeRelativePath((string)$prefix);
            if ($path !== '') {
                $normalized[$path] = $path;
            }
        }
        return array_values($normalized);
    }

    /** @param array<string,mixed> $discovery @return array<int,array<string,mixed>> */
    private static function diagnosticsFrom(array $discovery): array
    {
        return isset($discovery['diagnostics']) && is_array($discovery['diagnostics'])
            ? array_values(array_filter($discovery['diagnostics'], 'is_array'))
            : [];
    }

    /** @param array<string,mixed> $ownerDiscovery */
    private static function discoveryStatus(array $ownerDiscovery): string
    {
        $diagnostics = self::diagnosticsFrom($ownerDiscovery);
        foreach ($diagnostics as $diagnostic) {
            if ((string)($diagnostic['severity'] ?? '') === 'error') {
                return 'error';
            }
        }
        return $diagnostics === [] ? 'ok' : 'partial';
    }

    /** @return array<string,string> */
    private static function diagnostic(string $code, string $severity, string $message, string $path): array
    {
        return compact('code', 'severity', 'message', 'path');
    }

    /** @param array<string,mixed> $targetResolution @param array<string,mixed> $ownerDiscovery @param array<string,mixed> $referenceDiscovery @return array<string,mixed> */
    private static function errorResult(array $targetResolution, array $ownerDiscovery, array $referenceDiscovery): array
    {
        return [
            'status' => 'error',
            'effect' => self::EFFECT,
            'target' => is_array($targetResolution['target'] ?? null) ? $targetResolution['target'] : [],
            'summary' => [
                'reference_count' => 0,
                'files_referencing' => 0,
                'dependent_owner_count' => 0,
                'runtime_blockers' => 0,
                'review_required' => 0,
                'cleanup_only' => 0,
            ],
            'deletion_readiness' => self::READINESS_UNKNOWN,
            'safe_to_delete' => 'no',
            'blocking_reasons' => ['TARGET_RESOLUTION_FAILED'],
            'dependent_owners' => [],
            'references' => [],
            'excluded_self_scope' => '',
            'evidence' => [
                'owner_discovery_status' => self::discoveryStatus($ownerDiscovery),
                'reference_discovery_status' => (string)($referenceDiscovery['status'] ?? 'not_run'),
                'reference_search_scope' => [],
            ],
            'diagnostics' => array_values(array_merge(
                self::diagnosticsFrom($ownerDiscovery),
                self::diagnosticsFrom($referenceDiscovery),
                is_array($targetResolution['diagnostics'] ?? null) ? $targetResolution['diagnostics'] : []
            )),
        ];
    }
}
