<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\LocalizationScanExtraction\Services;

use Apps\Studio\Tools\LocalizationStudio\Services\LocalizationStudioEditService;

final class FullSystemScanService
{
    public static function scanAll(string $scope, string $locale, array $selectedOwnerKeys = []): array
    {
        $owners = [];
        $selected = [];
        foreach ($selectedOwnerKeys as $ownerKey) {
            $ownerKey = trim((string)$ownerKey);
            if ($ownerKey !== '' && $ownerKey !== '__all__') {
                $selected[$ownerKey] = true;
            }
        }

        try {
            $ownersRaw = LocalizationStudioEditService::getOwnersWithPaths();
            foreach ($ownersRaw as $o) {
                if (isset($o['key']) && $o['key'] !== '') {
                    $ownerKey = (string)$o['key'];
                    if ($selected === [] || isset($selected[$ownerKey])) {
                        $owners[$ownerKey] = $ownerKey;
                    }
                }
            }
        } catch (\Throwable $e) {
            return [
                'scan_ok' => false,
                'error' => 'Owner discovery failed: ' . $e->getMessage(),
                'owners' => [],
                'total_owners' => 0,
            ];
        }

        if ($owners === [] && $selected !== []) {
            return [
                'scan_ok' => false,
                'error' => 'No selected owners were found.',
                'errors' => [],
                'owner_results' => [],
                'totals' => [
                    'owners' => 0,
                    'owners_with_findings' => 0,
                    'total_findings' => 0,
                    'human_facing_candidates' => 0,
                    'already_localized_usages' => 0,
                    'missing_owner_keys' => 0,
                    'external_shared_key_usages' => 0,
                    'possibly_unused_keys' => 0,
                    'internal_strings' => 0,
                    'ambiguous_strings' => 0,
                    'ignored_strings' => 0,
                    'inline_text' => 0,
                    'human_facing' => 0,
                    'pending_missing_keys' => 0,
                    'ready_auto_fix_keys' => 0,
                    'ready_missing_key_corrections' => 0,
                    'inline_ready_to_migrate' => 0,
                    'ready_inline_migrations' => 0,
                    'needs_review_keys' => 0,
                    'needs_review_total' => 0,
                    'rejected_unsafe_keys' => 0,
                    'rejected_total' => 0,
                    'review_reason_counts' => self::emptyReviewReasonCounts(),
                    'review_priority_counts' => self::emptyReviewPriorityCounts(),
                    'estimated_missing_key_reductions' => 0,
                    'estimated_inline_reductions' => 0,
                    'estimated_keys_created' => 0,
                    'estimated_files_modified' => 0,
                ],
                'scope' => $scope,
                'locale' => $locale,
                'scanned_at' => date('Y-m-d H:i:s'),
            ];
        }

        $ownerResults = [];
        $foundAny = false;
        $errors = [];

        foreach ($owners as $ownerKey) {
            try {
                $scan = LocalizationScanService::scan($ownerKey, $scope, $locale);
                if (!empty($scan['scan_ok'])) {
                    $foundAny = true;
                }

                $findings = $scan['findings'] ?? [];
                $summary = $scan['summary'] ?? [];
                $reviewPlan = $scan['missing_key_review_plan'] ?? [];
                $reviewRows = is_array($reviewPlan['rows'] ?? null) ? $reviewPlan['rows'] : [];
                $pendingMissing = count($reviewRows);
                $readyAutoFix = 0;
                $rejectedUnsafe = 0;
                foreach ($reviewRows as $reviewRow) {
                    if (!empty($reviewRow['suggestion_ready'])) {
                        $readyAutoFix++;
                    }
                    if ((string)($reviewRow['suggestion_rejection_reason'] ?? '') !== '') {
                        $rejectedUnsafe++;
                    }
                }
                $needsReview = max(0, $pendingMissing - $readyAutoFix - $rejectedUnsafe);
                $migrationCounts = [self::migrationStateReady() => 0, self::migrationStateReview() => 0, self::migrationStateRejected() => 0];
                $reviewReasonCounts = self::emptyReviewReasonCounts();
                $reviewPriorityCounts = self::emptyReviewPriorityCounts();
                $readyInlineFiles = [];
                try {
                    $migrationPlanner = new InlineMigrationPlannerService($ownerKey);
                    $migrationPlan = $migrationPlanner->buildPlan($findings);
                    $migrationCounts = is_array($migrationPlan['counts'] ?? null) ? $migrationPlan['counts'] : $migrationCounts;
                    $reviewReasonCounts = self::normalizeReviewReasonCounts($migrationPlan['review_reason_counts'] ?? []);
                    $reviewPriorityCounts = self::normalizeReviewPriorityCounts($migrationPlan['review_priority_counts'] ?? []);
                    $migrationCandidates = is_array($migrationPlan['candidates'] ?? null) ? $migrationPlan['candidates'] : [];
                    foreach ($migrationCandidates as $candidate) {
                        if (($candidate['state'] ?? '') !== self::migrationStateReady()) {
                            continue;
                        }
                        $file = (string)($candidate['file'] ?? '');
                        if ($file !== '') {
                            $readyInlineFiles[$file] = true;
                        }
                    }
                } catch (\Throwable $e) {
                    $migrationCounts = [self::migrationStateReady() => 0, self::migrationStateReview() => 0, self::migrationStateRejected() => 0];
                    $reviewReasonCounts = self::emptyReviewReasonCounts();
                    $reviewPriorityCounts = self::emptyReviewPriorityCounts();
                    $readyInlineFiles = [];
                }
                $inlineReady = (int)($migrationCounts[self::migrationStateReady()] ?? 0);
                $inlineNeedsReview = (int)($migrationCounts[self::migrationStateReview()] ?? 0);
                $inlineRejected = (int)($migrationCounts[self::migrationStateRejected()] ?? 0);
                $needsReviewTotal = $needsReview + $inlineNeedsReview;
                $rejectedTotal = $rejectedUnsafe + $inlineRejected;
                $estimatedFilesModified = count($readyInlineFiles);
                if ($readyAutoFix > 0 || $inlineReady > 0) {
                    $estimatedFilesModified++;
                }
                if ($needsReview > 0) {
                    $reviewReasonCounts['naming_uncertain'] += $needsReview;
                    $reviewPriorityCounts['manual_review'] += $needsReview;
                }

                $ownerResults[$ownerKey] = [
                    'scan_ok' => !empty($scan['scan_ok']),
                    'error' => $scan['error'] ?? null,
                    'total_findings' => count($findings),
                    'human_facing_candidates' => (int)($summary['human_facing_candidates'] ?? 0),
                    'already_localized_usages' => (int)($summary['already_localized_usages'] ?? 0),
                    'missing_owner_keys' => (int)($summary['missing_owner_keys'] ?? 0),
                    'external_shared_key_usages' => (int)($summary['external_shared_key_usages'] ?? 0),
                    'possibly_unused_keys' => (int)($summary['possibly_unused_keys'] ?? 0),
                    'internal_strings' => (int)($summary['internal_strings'] ?? 0),
                    'ambiguous_strings' => (int)($summary['ambiguous_strings'] ?? 0),
                    'ignored_strings' => (int)($summary['ignored_strings'] ?? 0),
                    'inline_text' => (int)($summary['human_facing_candidates'] ?? 0),
                    'human_facing' => (int)($summary['human_facing_candidates'] ?? 0),
                    'pending_missing_keys' => $pendingMissing,
                    'ready_auto_fix_keys' => $readyAutoFix,
                    'ready_missing_key_corrections' => $readyAutoFix,
                    'inline_ready_to_migrate' => $inlineReady,
                    'needs_review_keys' => $needsReview,
                    'inline_needs_review' => $inlineNeedsReview,
                    'needs_review_total' => $needsReviewTotal,
                    'rejected_unsafe_keys' => $rejectedUnsafe,
                    'inline_rejected' => $inlineRejected,
                    'rejected_total' => $rejectedTotal,
                    'review_reason_counts' => $reviewReasonCounts,
                    'review_priority_counts' => $reviewPriorityCounts,
                    'estimated_missing_key_reductions' => $readyAutoFix,
                    'estimated_inline_reductions' => $inlineReady,
                    'estimated_keys_created' => $readyAutoFix + $inlineReady,
                    'estimated_files_modified' => $estimatedFilesModified,
                    'scanned_files' => (int)($scan['scanned_files'] ?? 0),
                    'total_lines' => (int)($scan['total_lines'] ?? 0),
                ];
            } catch (\Throwable $e) {
                $errors[] = ['owner' => $ownerKey, 'error' => $e->getMessage()];
                $ownerResults[$ownerKey] = [
                    'scan_ok' => false,
                    'error' => $e->getMessage(),
                    'total_findings' => 0,
                    'human_facing_candidates' => 0,
                    'already_localized_usages' => 0,
                    'external_shared_key_usages' => 0,
                    'possibly_unused_keys' => 0,
                    'internal_strings' => 0,
                    'ambiguous_strings' => 0,
                    'ignored_strings' => 0,
                    'inline_text' => 0,
                    'missing_owner_keys' => 0,
                    'human_facing' => 0,
                    'pending_missing_keys' => 0,
                    'ready_auto_fix_keys' => 0,
                    'ready_missing_key_corrections' => 0,
                    'inline_ready_to_migrate' => 0,
                    'needs_review_keys' => 0,
                    'inline_needs_review' => 0,
                    'needs_review_total' => 0,
                    'rejected_unsafe_keys' => 0,
                    'inline_rejected' => 0,
                    'rejected_total' => 0,
                    'review_reason_counts' => self::emptyReviewReasonCounts(),
                    'review_priority_counts' => self::emptyReviewPriorityCounts(),
                    'estimated_missing_key_reductions' => 0,
                    'estimated_inline_reductions' => 0,
                    'estimated_keys_created' => 0,
                    'estimated_files_modified' => 0,
                    'scanned_files' => 0,
                    'total_lines' => 0,
                ];
            }
        }

        $totals = [
            'owners' => count($owners),
            'owners_with_findings' => 0,
            'total_findings' => 0,
            'human_facing_candidates' => 0,
            'already_localized_usages' => 0,
            'missing_owner_keys' => 0,
            'external_shared_key_usages' => 0,
            'possibly_unused_keys' => 0,
            'internal_strings' => 0,
            'ambiguous_strings' => 0,
            'ignored_strings' => 0,
            'inline_text' => 0,
            'human_facing' => 0,
            'pending_missing_keys' => 0,
            'ready_auto_fix_keys' => 0,
            'ready_missing_key_corrections' => 0,
            'ready_inline_migrations' => 0,
            'needs_review_keys' => 0,
            'needs_review_total' => 0,
            'rejected_unsafe_keys' => 0,
            'rejected_total' => 0,
            'review_reason_counts' => self::emptyReviewReasonCounts(),
            'review_priority_counts' => self::emptyReviewPriorityCounts(),
            'estimated_missing_key_reductions' => 0,
            'estimated_inline_reductions' => 0,
            'estimated_keys_created' => 0,
            'estimated_files_modified' => 0,
        ];

        foreach ($ownerResults as $r) {
            if ($r['total_findings'] > 0) {
                $totals['owners_with_findings']++;
            }
            $totals['total_findings'] += $r['total_findings'];
            $totals['human_facing_candidates'] += $r['human_facing_candidates'];
            $totals['already_localized_usages'] += $r['already_localized_usages'];
            $totals['missing_owner_keys'] += $r['missing_owner_keys'];
            $totals['external_shared_key_usages'] += $r['external_shared_key_usages'];
            $totals['possibly_unused_keys'] += $r['possibly_unused_keys'];
            $totals['internal_strings'] += $r['internal_strings'];
            $totals['ambiguous_strings'] += $r['ambiguous_strings'];
            $totals['ignored_strings'] += $r['ignored_strings'];
            $totals['inline_text'] += $r['inline_text'];
            $totals['human_facing'] += $r['human_facing'];
            $totals['pending_missing_keys'] += $r['pending_missing_keys'];
            $totals['ready_auto_fix_keys'] += $r['ready_auto_fix_keys'];
            $totals['ready_missing_key_corrections'] += $r['ready_missing_key_corrections'];
            $totals['ready_inline_migrations'] += $r['inline_ready_to_migrate'];
            $totals['needs_review_keys'] += $r['needs_review_keys'];
            $totals['needs_review_total'] += $r['needs_review_total'];
            $totals['rejected_unsafe_keys'] += $r['rejected_unsafe_keys'];
            $totals['rejected_total'] += $r['rejected_total'];
            $totals['review_reason_counts'] = self::mergeCounts($totals['review_reason_counts'], $r['review_reason_counts'] ?? []);
            $totals['review_priority_counts'] = self::mergeCounts($totals['review_priority_counts'], $r['review_priority_counts'] ?? []);
            $totals['estimated_missing_key_reductions'] += (int)($r['estimated_missing_key_reductions'] ?? 0);
            $totals['estimated_inline_reductions'] += (int)($r['estimated_inline_reductions'] ?? 0);
            $totals['estimated_keys_created'] += (int)($r['estimated_keys_created'] ?? 0);
            $totals['estimated_files_modified'] += (int)($r['estimated_files_modified'] ?? 0);
        }

        return [
            'scan_ok' => $foundAny || $errors === [],
            'errors' => $errors,
            'owner_results' => $ownerResults,
            'totals' => $totals,
            'scope' => $scope,
            'locale' => $locale,
            'scanned_at' => date('Y-m-d H:i:s'),
        ];
    }

    private static function migrationStateReady(): string
    {
        return 'ready_to_migrate';
    }

    private static function migrationStateReview(): string
    {
        return 'needs_review';
    }

    private static function migrationStateRejected(): string
    {
        return 'rejected';
    }

    private static function emptyReviewReasonCounts(): array
    {
        return [
            'domain_vocabulary' => 0,
            'ambiguous_context' => 0,
            'long_description' => 0,
            'duplicate_candidate' => 0,
            'technical_term' => 0,
            'naming_uncertain' => 0,
            'reusable_ui_term' => 0,
            'mixed_usage' => 0,
            'other' => 0,
        ];
    }

    private static function emptyReviewPriorityCounts(): array
    {
        return [
            'likely_migratable' => 0,
            'manual_review' => 0,
            'blocked' => 0,
        ];
    }

    private static function normalizeReviewReasonCounts(mixed $counts): array
    {
        return self::mergeCounts(self::emptyReviewReasonCounts(), is_array($counts) ? $counts : []);
    }

    private static function normalizeReviewPriorityCounts(mixed $counts): array
    {
        return self::mergeCounts(self::emptyReviewPriorityCounts(), is_array($counts) ? $counts : []);
    }

    private static function mergeCounts(array $base, array $add): array
    {
        foreach ($add as $key => $value) {
            $key = (string)$key;
            if (!array_key_exists($key, $base)) {
                $base[$key] = 0;
            }
            $base[$key] += (int)$value;
        }
        return $base;
    }
}
