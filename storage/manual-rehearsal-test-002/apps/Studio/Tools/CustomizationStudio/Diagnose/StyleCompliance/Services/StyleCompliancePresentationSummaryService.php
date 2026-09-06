<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\CustomizationStudio\Diagnose\StyleCompliance\Services;

require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Services/StyleComplianceDeterministicFixOneService.php';

final class StyleCompliancePresentationSummaryService
{
    /**
     * @param array<string,mixed> $scan
     * @return array<string,mixed>
     */
    public static function build(array $scan, string $scope, string $ownerKey, string $workspace): array
    {
        $summary = isset($scan['summary']) && is_array($scan['summary']) ? $scan['summary'] : [];
        $proposals = isset($scan['theme_repair_proposals']) && is_array($scan['theme_repair_proposals'])
            ? $scan['theme_repair_proposals']
            : [];
        $proposalSummary = isset($proposals['summary']) && is_array($proposals['summary']) ? $proposals['summary'] : [];
        $proposalQueues = isset($proposals['queues']) && is_array($proposals['queues']) ? $proposals['queues'] : [];
        $foundationConcerns = isset($scan['foundation_concerns']) && is_array($scan['foundation_concerns'])
            ? $scan['foundation_concerns']
            : [];

        $findingsTotal = (int)($summary['total_tokens'] ?? 0);
        $ownersScanned = (int)($scan['owners_scanned'] ?? $summary['owners_scanned'] ?? 0);
        $filesScanned = (int)($scan['files_scanned'] ?? $summary['files_scanned'] ?? 0);
        $inScope = (int)($summary['in_scope'] ?? 0);
        $aware = (int)($summary['in_scope_aware'] ?? 0);
        $complianceScore = $inScope > 0 ? (int)round(($aware / $inScope) * 100) : null;

        $fixableNow = self::fixableNowCount($proposalQueues);
        $accessibilityReview = (int)($proposalSummary['accessibility_review_items'] ?? 0);
        $manualDecisions = count($proposalQueues['manual_semantic_decisions'] ?? []);
        $classificationReview = count(array_filter(
            $proposalQueues['classification_and_accessibility_review'] ?? [],
            static fn($proposal): bool => is_array($proposal)
                && ((string)($proposal['review_reason_code'] ?? '')) !== 'accessibility_review'
        ));
        $decisionBacklog = $manualDecisions + $classificationReview;
        $reviewOnly = $accessibilityReview + $decisionBacklog + count($foundationConcerns);
        $deferredOrOutOfScope = self::deferredOrOutOfScopeCount($summary, $proposalSummary);
        $blockedOrNotSafe = (int)($summary['boundary_findings'] ?? 0) + $deferredOrOutOfScope;

        $nextActionKind = 'none';
        $nextActionTarget = null;
        $nextActionLabel = '';
        if ($fixableNow > 0) {
            $nextActionKind = 'fixable_now';
            $nextActionTarget = '#scVerifiedCandidates';
            $nextActionLabel = 'Fixable Now';
        } elseif ($reviewOnly > 0) {
            $nextActionKind = 'review_only';
            $nextActionTarget = '#scDecisionBacklog';
            $nextActionLabel = 'Review Only';
        }

        return [
            'scope' => $scope,
            'owner_key' => $scope === StyleComplianceScannerService::SCOPE_OWNER && $ownerKey !== '' ? $ownerKey : null,
            'workspace' => $workspace,
            'scan_status' => 'complete',
            'summary' => [
                'owners_scanned' => $ownersScanned,
                'files_scanned' => $filesScanned,
                'findings_total' => $findingsTotal,
                'fixable_now' => $fixableNow,
                'review_only' => $reviewOnly,
                'decision_backlog' => $decisionBacklog,
                'deferred_or_out_of_scope' => $deferredOrOutOfScope,
                'blocked_or_not_safe' => $blockedOrNotSafe,
                'compliance_score' => $complianceScore,
                'accessibility_review' => $accessibilityReview,
                'foundation_concerns' => count($foundationConcerns),
                'repair_ready' => $fixableNow,
                'review_total' => $reviewOnly,
                'decision_total' => $decisionBacklog,
            ],
            'next_action' => [
                'kind' => $nextActionKind,
                'target' => $nextActionTarget,
                'label' => $nextActionLabel,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $proposalQueues
     */
    private static function fixableNowCount(array $proposalQueues): int
    {
        $queue = isset($proposalQueues['ready_for_future_guarded_apply']) && is_array($proposalQueues['ready_for_future_guarded_apply'])
            ? $proposalQueues['ready_for_future_guarded_apply']
            : [];
        $count = 0;
        foreach ($queue as $proposal) {
            if (is_array($proposal) && StyleComplianceDeterministicFixOneService::eligibility($proposal)['state'] === 'ready') {
                $count++;
            }
        }
        return $count;
    }

    /**
     * @param array<string,mixed> $summary
     * @param array<string,mixed> $proposalSummary
     */
    private static function deferredOrOutOfScopeCount(array $summary, array $proposalSummary): int
    {
        return (int)($proposalSummary['future_effects_handoffs'] ?? 0)
            + (int)($summary['dynamic_unsupported'] ?? 0)
            + (int)($summary['print_pdf'] ?? 0)
            + (int)($summary['generated_or_minified'] ?? 0)
            + (int)($summary['structural_out_of_scope'] ?? 0);
    }
}
