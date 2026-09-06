<?php
declare(strict_types=1);

namespace Plugins\Base\Services;

use Apps\Manufacturing\Services\HandoffTrackingContributionService;

final class HandoffTrackingService
{
    public const STAGE_PROD_TO_QC = 'production_to_qc';
    public const STAGE_QC_TO_DISPATCH = 'qc_to_dispatch';
    public const STAGE_DISPATCH_TO_COMPLETION = 'dispatch_to_completion';
    public const STAGE_ORDER_RISK = 'order_risk';

    private static function contributionClass(): ?string
    {
        return class_exists(HandoffTrackingContributionService::class)
            ? HandoffTrackingContributionService::class
            : null;
    }

    public static function syncProductionEntry(int $productionEntryId): void
    {
        $service = self::contributionClass();
        if ($service === null) {
            return;
        }

        $service::syncProductionEntry($productionEntryId);
    }

    public static function syncQcEntry(int $qcEntryId): void
    {
        $service = self::contributionClass();
        if ($service === null) {
            return;
        }

        $service::syncQcEntry($qcEntryId);
    }

    public static function syncDispatchEntry(int $dispatchEntryId, ?int $linkedQcEntryId = null): void
    {
        $service = self::contributionClass();
        if ($service === null) {
            return;
        }

        $service::syncDispatchEntry($dispatchEntryId, $linkedQcEntryId);
    }

    /**
     * @param array<int,int> $productIds
     */
    public static function syncDailyOrderRiskForProducts(array $productIds): void
    {
        $service = self::contributionClass();
        if ($service === null) {
            return;
        }

        $service::syncDailyOrderRiskForProducts($productIds);
    }
}
