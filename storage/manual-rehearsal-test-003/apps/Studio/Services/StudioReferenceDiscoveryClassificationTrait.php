<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

trait StudioReferenceDiscoveryClassificationTrait
{
    private static function categorizeRelevance(string $filePath, string $matchType, string $confidence): string
    {
        if ($confidence === 'low' && $matchType === 'owner key') {
            return self::RELEVANCE_LOW_CONFIDENCE_TEXT;
        }
        if (str_starts_with($filePath, 'engineering/')) {
            return self::RELEVANCE_ENGINEERING_WORKSPACE;
        }
        if (str_contains($filePath, '/tests/') || str_contains($filePath, '/probe_')) {
            return self::RELEVANCE_STUDIO_TOOLING;
        }
        if (str_contains($filePath, 'AGENTS.md') || str_contains($filePath, 'AGENT-COMPLIANCE-CHECKLIST.md')) {
            return self::RELEVANCE_SELF_REFERENCE;
        }
        if (str_contains($filePath, 'docs/')) {
            return self::RELEVANCE_DOCUMENTATION_HISTORY;
        }
        if (str_contains($filePath, '/Resources/lang/') || str_contains($filePath, '/lang/')) {
            return self::RELEVANCE_OWNER_METADATA;
        }
        if (str_starts_with($filePath, 'apps/Studio/')) {
            return self::RELEVANCE_STUDIO_TOOLING;
        }
        return self::RELEVANCE_RUNTIME_BLOCKING;
    }

    private static function categoryFromRelevance(string $relevance): string
    {
        if ($relevance === self::RELEVANCE_RUNTIME_BLOCKING) {
            return self::CATEGORY_RUNTIME;
        }
        if ($relevance === self::RELEVANCE_STUDIO_TOOLING || $relevance === self::RELEVANCE_LOW_CONFIDENCE_TEXT) {
            return self::CATEGORY_TOOLING;
        }
        if ($relevance === self::RELEVANCE_SELF_REFERENCE) {
            return self::CATEGORY_SELF_REFERENCE;
        }
        return self::CATEGORY_DOCS;
    }

    /** @param array<int,array<string,mixed>> $references */
    private static function overallConfidence(array $references): string
    {
        if ($references === []) {
            return 'low';
        }
        $values = array_map(static fn(array $reference): string => (string)($reference['confidence'] ?? 'low'), $references);
        if (in_array('low', $values, true)) {
            return 'low';
        }
        if (in_array('medium', $values, true)) {
            return 'medium';
        }
        return 'high';
    }

    /** @param array<int,array<string,mixed>> $references @return array<string,int> */
    private static function relevanceSummary(array $references): array
    {
        $summary = [
            self::RELEVANCE_RUNTIME_BLOCKING => 0,
            self::RELEVANCE_STUDIO_TOOLING => 0,
            self::RELEVANCE_ENGINEERING_WORKSPACE => 0,
            self::RELEVANCE_DOCUMENTATION_HISTORY => 0,
            self::RELEVANCE_OWNER_METADATA => 0,
            self::RELEVANCE_SELF_REFERENCE => 0,
            self::RELEVANCE_LOW_CONFIDENCE_TEXT => 0,
        ];
        foreach ($references as $reference) {
            $relevance = (string)($reference['relevance'] ?? '');
            if (isset($summary[$relevance])) {
                $summary[$relevance]++;
            }
        }
        return $summary;
    }

    /** @param array<int,array<string,mixed>> $references @return array<string,int> */
    private static function categorySummary(array $references): array
    {
        $summary = [
            self::CATEGORY_RUNTIME => 0,
            self::CATEGORY_TOOLING => 0,
            self::CATEGORY_DOCS => 0,
            self::CATEGORY_SELF_REFERENCE => 0,
        ];
        foreach ($references as $reference) {
            $category = (string)($reference['category'] ?? self::categoryFromRelevance((string)($reference['relevance'] ?? '')));
            if (isset($summary[$category])) {
                $summary[$category]++;
            }
        }
        return $summary;
    }
}
