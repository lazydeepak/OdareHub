<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

use Plugins\Products\Services\PartExecutionRouteResolver;
use Plugins\Supply\Services\SupplyModel;

final class DemandRouteDecisionService
{
    /**
     * @param array<string,mixed> $partProfile
     * @param array<string,mixed> $demandContext
     * @return array<string,mixed>
     */
    public static function decide(array $partProfile, array $demandContext): array
    {
        $supplyMode = SupplyModel::normalizeSupplyMode((string)($partProfile['supply_mode'] ?? 'in_house'));
        $rawFulfillmentMode = (string)($partProfile['fulfillment_mode'] ?? 'company_to_destination');
        $fulfillmentMode = PartExecutionRouteResolver::normalizeFulfillmentMode($rawFulfillmentMode);
        $requiresQc = (int)($partProfile['requires_ipm_qc'] ?? 1) === 1;
        $requiresAssembly = (int)($partProfile['requires_assembly'] ?? 0) === 1;
        $requiresProcessing = PartExecutionRouteResolver::normalizeRequiresProcessing($partProfile['requires_processing'] ?? null, $rawFulfillmentMode);
        $dispatchModeOverride = PartExecutionRouteResolver::normalizeDispatchMode((string)($partProfile['dispatch_mode'] ?? ''));
        $effectiveDispatchMode = PartExecutionRouteResolver::effectiveDispatchMode($dispatchModeOverride, $fulfillmentMode);
        $dispatchAsIs = (int)($partProfile['dispatch_as_is'] ?? 0) === 1;

        $dailyOrderQty = round(max(0.0, (float)($demandContext['daily_order_qty'] ?? 0.0)), 2);
        $bufferRecoveryNeed = round(max(0.0, (float)($demandContext['buffer_recovery_need'] ?? 0.0)), 2);
        $availableStock = round(max(0.0, (float)($demandContext['available_stock_qty'] ?? 0.0)), 2);
        $plannedStock = round(max(0.0, (float)($demandContext['planned_stock_qty'] ?? 0.0)), 2);

        $effectiveSupply = round($availableStock + $plannedStock, 2);
        $grossDemand = round($dailyOrderQty + $bufferRecoveryNeed, 2);
        $netExecutionQty = round(max(0.0, $grossDemand - $effectiveSupply), 2);

        $route = PartExecutionRouteResolver::resolveRoute([
            'supply_mode' => $supplyMode,
            'fulfillment_mode' => $fulfillmentMode,
            'requires_ipm_qc' => $requiresQc ? 1 : 0,
            'requires_assembly' => $requiresAssembly ? 1 : 0,
            'requires_processing' => $requiresProcessing ? 1 : 0,
            'dispatch_mode' => $effectiveDispatchMode,
            'dispatch_as_is' => $dispatchAsIs ? 1 : 0,
        ]);

        $needsExecution = $netExecutionQty > 0.0;
        $isThirdParty = $supplyMode === SupplyModel::SUPPLY_THIRD_PARTY;
        $isHybrid = $supplyMode === SupplyModel::SUPPLY_HYBRID;

        $needsProcurement = $needsExecution && ($isThirdParty || $isHybrid);
        $needsProduction = $needsExecution
            && !$isThirdParty
            && !$dispatchAsIs
            && ((bool)($route['internal_execution'] ?? true) || $isHybrid);

        $needsQc = $needsExecution && (bool)($route['needs_qc'] ?? false);
        $needsAssembly = $needsExecution && (bool)($route['needs_assembly'] ?? false);
        $needsProcessing = $needsExecution && $requiresProcessing;

        $canDispatchDirect = (bool)(
            ($route['route_family'] ?? '') === PartExecutionRouteResolver::ROUTE_THIRD_PARTY_DIRECT
            || ($dispatchAsIs && !$needsQc && !$needsAssembly)
        );

        if ($effectiveDispatchMode === 'direct_supplier') {
            $canDispatchDirect = true;
        } elseif ($effectiveDispatchMode === 'internal') {
            $canDispatchDirect = false;
        }

        $routeFamily = (string)($route['route_family'] ?? '');
        if ($effectiveDispatchMode === 'direct_supplier') {
            $routeFamily = PartExecutionRouteResolver::ROUTE_THIRD_PARTY_DIRECT;
            $needsProcessing = false;
        }

        // Dispatch is still required to fulfill demand even when execution is stock-covered.
        $needsDispatch = $grossDemand > 0.0;
        if ($effectiveDispatchMode === 'direct_supplier') {
            $needsDispatch = false;
        }

        $internalExecutionRequired = $needsExecution && (bool)($route['internal_execution'] ?? false);
        if ($effectiveDispatchMode === 'direct_supplier') {
            $internalExecutionRequired = false;
        }

        $dispatchMode = self::dispatchModeFromRoute($route, $fulfillmentMode, $canDispatchDirect);
        if ($effectiveDispatchMode === 'direct_supplier') {
            $dispatchMode = 'third_party_direct_dispatch';
        } elseif ($effectiveDispatchMode === 'internal') {
            $dispatchMode = 'company_origin_dispatch';
        }

        $executionPathLabel = self::executionPathLabel($routeFamily, $needsExecution, $needsProduction, $needsProcurement, $needsAssembly, $needsQc, $needsProcessing);

        return [
            'needs_production' => $needsProduction,
            'needs_procurement' => $needsProcurement,
            'needs_qc' => $needsQc,
            'needs_assembly' => $needsAssembly,
            'needs_processing' => $needsProcessing,
            'needs_dispatch' => $needsDispatch,
            'can_dispatch_direct' => $canDispatchDirect,
            'internal_execution_required' => $internalExecutionRequired,
            'dispatch_mode' => $dispatchMode,
            'execution_path_label' => $executionPathLabel,
            'net_execution_qty' => $netExecutionQty,
            'gross_demand_qty' => $grossDemand,
            'effective_supply_qty' => $effectiveSupply,
            'route_family' => $routeFamily,
            'route_stages' => (array)($route['stages'] ?? []),
            'normalized_profile' => [
                'supply_mode' => $supplyMode,
                'fulfillment_mode' => $fulfillmentMode,
                'requires_ipm_qc' => $requiresQc,
                'requires_assembly' => $requiresAssembly,
                'requires_processing' => $requiresProcessing,
                'dispatch_mode' => $effectiveDispatchMode,
                'dispatch_as_is' => $dispatchAsIs,
            ],
            'demand_context' => [
                'daily_order_qty' => $dailyOrderQty,
                'buffer_recovery_need' => $bufferRecoveryNeed,
                'available_stock_qty' => $availableStock,
                'planned_stock_qty' => $plannedStock,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $route
     */
    private static function dispatchModeFromRoute(array $route, string $fulfillmentMode, bool $canDispatchDirect): string
    {
        if (($route['route_family'] ?? '') === PartExecutionRouteResolver::ROUTE_THIRD_PARTY_DIRECT) {
            return 'third_party_direct_dispatch';
        }

        if ($canDispatchDirect) {
            return 'company_direct_dispatch';
        }

        return match ($fulfillmentMode) {
            'third_party_to_company_to_destination' => 'third_party_to_company_then_destination',
            'third_party_to_destination' => 'third_party_direct_dispatch',
            default => 'company_origin_dispatch',
        };
    }

    /**
     * @param array<string,mixed> $route
     */
    private static function executionPathLabel(
        string $routeFamily,
        bool $needsExecution,
        bool $needsProduction,
        bool $needsProcurement,
        bool $needsAssembly,
        bool $needsQc,
        bool $needsProcessing
    ): string
    {
        if (!$needsExecution) {
            return 'Stock Covered (No New Execution)';
        }

        if ($routeFamily === PartExecutionRouteResolver::ROUTE_THIRD_PARTY_DIRECT) {
            return 'Third Party -> Direct Delivery';
        }

        $stages = [];
        if ($needsProcurement && !$needsProduction) {
            $stages[] = 'Third Party';
            if ($routeFamily === PartExecutionRouteResolver::ROUTE_THIRD_PARTY_VIA_COMPANY) {
                $stages[] = 'Company';
            }
        } elseif ($routeFamily === PartExecutionRouteResolver::ROUTE_INTERNAL_DIRECT) {
            $stages[] = 'Production/Stock';
        } else {
            $stages[] = 'Production';
        }

        if ($needsAssembly) {
            $stages[] = 'Assembly';
        }
        if ($needsQc) {
            $stages[] = 'QC';
        }
        if ($needsProcessing) {
            $stages[] = 'Processing';
        }
        $stages[] = 'Dispatch';

        if (count($stages) > 1) {
            return implode(' -> ', $stages);
        }

        return match ($routeFamily) {
            PartExecutionRouteResolver::ROUTE_FULL_INTERNAL => 'Production -> Assembly -> QC -> Processing -> Dispatch',
            PartExecutionRouteResolver::ROUTE_INTERNAL_NO_ASSEMBLY => 'Production -> QC -> Processing -> Dispatch',
            PartExecutionRouteResolver::ROUTE_INTERNAL_NO_QC => 'Production -> Assembly -> Processing -> Dispatch',
            PartExecutionRouteResolver::ROUTE_INTERNAL_DIRECT => 'Production/Stock -> Dispatch',
            PartExecutionRouteResolver::ROUTE_THIRD_PARTY_VIA_COMPANY => 'Third Party -> Company -> Processing -> QC -> Dispatch',
            default => 'Execution Path Undetermined',
        };
    }
}
