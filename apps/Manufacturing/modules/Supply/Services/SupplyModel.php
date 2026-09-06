<?php
declare(strict_types=1);

namespace Plugins\Supply\Services;

final class SupplyModel
{
    public const SUPPLY_IN_HOUSE = 'in_house';
    public const SUPPLY_THIRD_PARTY = 'third_party';
    public const SUPPLY_HYBRID = 'hybrid';

    public const FULFILL_VIA_IPM = 'via_ipm';
    public const FULFILL_DIRECT_SUPPLIER = 'direct_supplier';
    public const FULFILL_MIXED = 'mixed';

    /**
     * @param array<string,mixed> $row
     * @return array{suppy_mode:string,supply_mode:string,fulfillment_mode:string,requires_ipm_qc:bool}
     */
    public static function context(array $row): array
    {
        $supplyMode = self::normalizeSupplyMode((string)($row['supply_mode'] ?? ''));
        $fulfillmentMode = self::normalizeFulfillmentMode((string)($row['fulfillment_mode'] ?? ''));
        $requiresIpmQcRaw = $row['requires_ipm_qc'] ?? null;
        $requiresIpmQc = self::normalizeRequiresIpmQc($requiresIpmQcRaw);

        return [
            'supply_mode' => $supplyMode,
            'fulfillment_mode' => $fulfillmentMode,
            'requires_ipm_qc' => $requiresIpmQc,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $actions
     * @param array<string,mixed> $rowContext
     * @return array<int,array<string,mixed>>
     */
    public static function filterCoverageActions(array $actions, string $coverageState, array $rowContext): array
    {
        if (empty($actions)) {
            return [];
        }

        $ctx = self::context($rowContext);
        $supplyMode = $ctx['supply_mode'];
        $fulfillmentMode = $ctx['fulfillment_mode'];
        $requiresIpmQc = $ctx['requires_ipm_qc'];

        $byKey = [];
        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }
            $key = (string)($action['key'] ?? '');
            if ($key !== '') {
                $byKey[$key] = $action;
            }
        }

        if (empty($byKey)) {
            return $actions;
        }

        $disallowed = [];
        if ($supplyMode === self::SUPPLY_THIRD_PARTY) {
            $disallowed['action.coverage.low.plan_recovery'] = true;
            $disallowed['action.coverage.partial.balance_supply'] = true;
            $disallowed['action.coverage.partial.review_plans'] = true;
        }
        if ($fulfillmentMode === self::FULFILL_DIRECT_SUPPLIER) {
            $disallowed['action.coverage.full.dispatch_ready'] = true;
        }
        if (!$requiresIpmQc) {
            $disallowed['action.coverage.full.validate_qc'] = true;
        }

        $preferred = [];

        if ($supplyMode === self::SUPPLY_THIRD_PARTY) {
            $preferred[] = 'action.coverage.third_party.stock_check';
            $preferred[] = 'action.coverage.third_party.review_supplier';
            $preferred[] = 'action.coverage.third_party.procurement_review';
            $preferred[] = 'action.coverage.third_party.review_demand';

            if ($fulfillmentMode === self::FULFILL_DIRECT_SUPPLIER) {
                $preferred[] = 'action.coverage.direct.review_demand';
                $preferred[] = 'action.coverage.direct.supplier_fulfillment_review';
                $preferred[] = 'action.coverage.direct.delivery_tracking';
            } elseif ($coverageState === 'full') {
                $preferred[] = 'action.coverage.full.dispatch_ready';
            }
        } elseif ($fulfillmentMode === self::FULFILL_DIRECT_SUPPLIER) {
            $preferred[] = 'action.coverage.direct.review_demand';
            $preferred[] = 'action.coverage.direct.supplier_fulfillment_review';
            $preferred[] = 'action.coverage.direct.delivery_tracking';
            $preferred[] = 'action.coverage.third_party.review_supplier';
            $preferred[] = 'action.coverage.third_party.review_demand';
        } else {
            if ($coverageState === 'low') {
                $preferred[] = 'action.coverage.low.plan_recovery';
                $preferred[] = 'action.coverage.low.ledger';
            } elseif ($coverageState === 'partial') {
                $preferred[] = 'action.coverage.partial.balance_supply';
                $preferred[] = 'action.coverage.partial.review_plans';
                $preferred[] = 'action.coverage.low.ledger';
            } else {
                $preferred[] = 'action.coverage.full.dispatch_ready';
                $preferred[] = 'action.coverage.full.validate_qc';
                $preferred[] = 'action.coverage.low.ledger';
            }
        }

        $out = [];
        $seen = [];

        foreach ($preferred as $key) {
            if (isset($seen[$key]) || !isset($byKey[$key]) || isset($disallowed[$key])) {
                continue;
            }
            $out[] = $byKey[$key];
            $seen[$key] = true;
        }

        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }
            $key = (string)($action['key'] ?? '');
            if ($key === '' || isset($seen[$key]) || isset($disallowed[$key])) {
                continue;
            }
            $out[] = $action;
            $seen[$key] = true;
        }

        return !empty($out) ? $out : $actions;
    }

    public static function normalizeSupplyMode(string $mode): string
    {
        $normalized = strtolower(trim($mode));
        return match ($normalized) {
            self::SUPPLY_IN_HOUSE,
            self::SUPPLY_THIRD_PARTY,
            self::SUPPLY_HYBRID => $normalized,
            default => self::SUPPLY_IN_HOUSE,
        };
    }

    public static function normalizeFulfillmentMode(string $mode): string
    {
        $normalized = strtolower(trim($mode));
        return match ($normalized) {
            self::FULFILL_VIA_IPM,
            self::FULFILL_DIRECT_SUPPLIER,
            self::FULFILL_MIXED => $normalized,
            default => self::FULFILL_VIA_IPM,
        };
    }

    /**
     * @param mixed $value
     */
    public static function normalizeRequiresIpmQc($value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_bool($value)) {
            return $value;
        }

        $raw = strtolower(trim((string)$value));
        return !in_array($raw, ['0', 'false', 'no', 'off'], true);
    }
}
