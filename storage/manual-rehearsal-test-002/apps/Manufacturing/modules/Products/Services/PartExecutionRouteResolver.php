<?php
declare(strict_types=1);

namespace Plugins\Products\Services;

/**
 * PartExecutionRouteResolver
 *
 * Determines the operational execution route family for a part based on its
 * routing profile. This is the foundation for downstream demand/planning generation.
 *
 * Each part's execution route determines:
 * - Whether Assembly Demand should be created
 * - Whether QC Demand should be created
 * - Whether Order Processing work should be created
 * - Whether Dispatch work should be created
 * - Whether internal workflow is required
 *
 * Supported Route Families:
 * 1. Full Internal - assembly + QC + dispatch
 * 2. Internal No Assembly - QC + dispatch (no assembly)
 * 3. Internal No QC - assembly + dispatch (no QC)
 * 4. Internal Direct - dispatch only (no assembly/QC)
 * 5. Third Party via Company - QC/processing + dispatch
 * 6. Third Party Direct - direct supplier delivery
 */
final class PartExecutionRouteResolver
{
    /**
     * Route family constants
     */
    public const ROUTE_FULL_INTERNAL = 'full_internal';
    public const ROUTE_INTERNAL_NO_ASSEMBLY = 'internal_no_assembly';
    public const ROUTE_INTERNAL_NO_QC = 'internal_no_qc';
    public const ROUTE_INTERNAL_DIRECT = 'internal_direct';
    public const ROUTE_THIRD_PARTY_VIA_COMPANY = 'third_party_via_company';
    public const ROUTE_THIRD_PARTY_DIRECT = 'third_party_direct';

    /**
     * Resolve the execution route for a part.
     *
     * Canonical fields used:
     * - supply_mode: in_house, third_party, hybrid (production source)
     * - fulfillment_mode: company_to_destination, third_party_to_company_to_destination, third_party_to_destination
     * - requires_assembly: boolean - internal assembly required
    * - requires_processing: boolean - internal processing/preparation required
     * - requires_ipm_qc: boolean - QC validation required (authoritative)
    * - dispatch_mode: internal, direct_supplier, hybrid (optional override)
     * - dispatch_as_is: boolean - can move downstream without internal transformation (assembly/processing)
     *
     * @param array<string,mixed> $partProfile Part record with routing fields
     * @return array<string,mixed> Route resolution with family and execution flags
     */
    public static function resolveRoute(array $partProfile): array
    {
        $productionSource = (string)($partProfile['supply_mode'] ?? 'in_house');
        $rawFulfillmentMode = (string)($partProfile['fulfillment_mode'] ?? 'company_to_destination');
        $fulfillmentMode = self::normalizeFulfillmentMode($rawFulfillmentMode);
        $requiresAssembly = (int)($partProfile['requires_assembly'] ?? 0) === 1;
        $requiresProcessing = self::normalizeRequiresProcessing($partProfile['requires_processing'] ?? null, $rawFulfillmentMode);
        $requiresQc = (int)($partProfile['requires_ipm_qc'] ?? 1) === 1;
        $dispatchModeOverride = self::normalizeDispatchMode((string)($partProfile['dispatch_mode'] ?? ''));
        $effectiveDispatchMode = self::effectiveDispatchMode($dispatchModeOverride, $fulfillmentMode);
        $dispatchAsIs = (int)($partProfile['dispatch_as_is'] ?? 0) === 1;

        if ($effectiveDispatchMode === 'direct_supplier') {
            return self::resolveThirdPartyRoute('third_party_to_destination', false, false);
        }

        // Determine route family and execution flags
        if ($productionSource === 'third_party' || $fulfillmentMode === 'third_party_to_destination' || $fulfillmentMode === 'third_party_to_company_to_destination') {
            return self::resolveThirdPartyRoute($fulfillmentMode, $requiresQc, $requiresProcessing);
        }

        return self::resolveInternalRoute($requiresAssembly, $requiresQc, $requiresProcessing, $dispatchAsIs);
    }

    /**
     * Resolve internal production routes
     *
     * dispatch_as_is means: can move downstream without internal transformation (assembly/processing)
     * It does NOT override QC requirement--requires_ipm_qc is still authoritative.
     */
    private static function resolveInternalRoute(bool $requiresAssembly, bool $requiresQc, bool $requiresProcessing, bool $dispatchAsIs): array
    {
        if ($dispatchAsIs) {
            // Internal Direct - Parts Ready → Dispatch (but QC requirement still applies)
            return [
                'route_family' => self::ROUTE_INTERNAL_DIRECT,
                'needs_assembly' => false,
                'needs_qc' => $requiresQc,  // QC requirement is still authoritative
                'needs_processing' => $requiresProcessing,
                'needs_dispatch' => true,
                'internal_execution' => true,
                'description' => $requiresQc ? 'Ready stock → QC' . ($requiresProcessing ? ' → Processing' : '') . ' → Dispatch' : 'Ready stock' . ($requiresProcessing ? ' → Processing' : '') . ' → Dispatch',
                'stages' => $requiresQc
                    ? ($requiresProcessing ? ['qc', 'processing', 'dispatch'] : ['qc', 'dispatch'])
                    : ($requiresProcessing ? ['processing', 'dispatch'] : ['dispatch']),
            ];
        }

        if ($requiresAssembly && $requiresQc) {
            // Full Internal - Assembly → QC → Dispatch
            return [
                'route_family' => self::ROUTE_FULL_INTERNAL,
                'needs_assembly' => true,
                'needs_qc' => true,
                'needs_processing' => $requiresProcessing,
                'needs_dispatch' => true,
                'internal_execution' => true,
                'description' => $requiresProcessing ? 'Assembly → QC → Processing → Dispatch' : 'Assembly → QC → Dispatch',
                'stages' => $requiresProcessing ? ['assembly', 'qc', 'processing', 'dispatch'] : ['assembly', 'qc', 'dispatch'],
            ];
        }

        if ($requiresAssembly && !$requiresQc) {
            // Internal No QC - Assembly → Dispatch
            return [
                'route_family' => self::ROUTE_INTERNAL_NO_QC,
                'needs_assembly' => true,
                'needs_qc' => false,
                'needs_processing' => $requiresProcessing,
                'needs_dispatch' => true,
                'internal_execution' => true,
                'description' => $requiresProcessing ? 'Assembly → Processing → Dispatch' : 'Assembly → Dispatch',
                'stages' => $requiresProcessing ? ['assembly', 'processing', 'dispatch'] : ['assembly', 'dispatch'],
            ];
        }

        if (!$requiresAssembly && $requiresQc) {
            // Internal No Assembly - QC → Dispatch
            return [
                'route_family' => self::ROUTE_INTERNAL_NO_ASSEMBLY,
                'needs_assembly' => false,
                'needs_qc' => true,
                'needs_processing' => $requiresProcessing,
                'needs_dispatch' => true,
                'internal_execution' => true,
                'description' => $requiresProcessing ? 'QC → Processing → Dispatch' : 'QC → Dispatch',
                'stages' => $requiresProcessing ? ['qc', 'processing', 'dispatch'] : ['qc', 'dispatch'],
            ];
        }

        // No assembly, no QC - dispatch only
        return [
            'route_family' => self::ROUTE_INTERNAL_NO_ASSEMBLY,
            'needs_assembly' => false,
            'needs_qc' => false,
            'needs_processing' => $requiresProcessing,
            'needs_dispatch' => true,
            'internal_execution' => true,
            'description' => $requiresProcessing ? 'Stock → Processing → Dispatch' : 'Stock → Dispatch',
            'stages' => $requiresProcessing ? ['processing', 'dispatch'] : ['dispatch'],
        ];
    }

    /**
     * Resolve third-party production routes
     *
     * Uses canonicalized fulfillment_mode field (company_to_destination, 
     * third_party_to_company_to_destination, third_party_to_destination)
     */
    private static function resolveThirdPartyRoute(string $fulfillmentMode, bool $requiresQc, bool $requiresProcessing): array
    {
        if ($fulfillmentMode === 'third_party_to_destination') {
            // Third Party Direct - Supplier → Destination (bypass company)
            return [
                'route_family' => self::ROUTE_THIRD_PARTY_DIRECT,
                'needs_assembly' => false,
                'needs_qc' => false,
                'needs_processing' => false,
                'needs_dispatch' => false,
                'internal_execution' => false,
                'description' => 'Supplier fulfillment → Direct delivery',
                'stages' => [],
            ];
        }

        // Default: Third Party via Company - accepts inbound and may require QC/processing
        return [
            'route_family' => self::ROUTE_THIRD_PARTY_VIA_COMPANY,
            'needs_assembly' => false,
            'needs_qc' => $requiresQc,
            'needs_processing' => $requiresProcessing,
            'needs_dispatch' => true,
            'internal_execution' => true,
            'description' => 'Inbound' . ($requiresProcessing ? ' → Processing' : '') . ($requiresQc ? ' → QC' : '') . ' → Dispatch',
            'stages' => $requiresQc
                ? ($requiresProcessing ? ['receive', 'processing', 'qc', 'dispatch'] : ['receive', 'qc', 'dispatch'])
                : ($requiresProcessing ? ['receive', 'processing', 'dispatch'] : ['receive', 'dispatch']),
        ];
    }

    /**
     * Get available route family options for UI selection
     *
     * @return array<string,string>
     */
    public static function getRouteFamilyOptions(): array
    {
        return [
            self::ROUTE_FULL_INTERNAL => 'Full Internal (Assembly + QC + Dispatch)',
            self::ROUTE_INTERNAL_NO_ASSEMBLY => 'Internal No Assembly (QC + Dispatch)',
            self::ROUTE_INTERNAL_NO_QC => 'Internal No QC (Assembly + Dispatch)',
            self::ROUTE_INTERNAL_DIRECT => 'Internal Direct (Dispatch Only)',
            self::ROUTE_THIRD_PARTY_VIA_COMPANY => 'Third Party via Company',
            self::ROUTE_THIRD_PARTY_DIRECT => 'Third Party Direct Delivery',
        ];
    }

    /**
     * Get fulfillment mode options for UI selection
     *
     * Canonical fulfillment modes representing all routing possibilities.
     *
     * @return array<string,string>
     */
    public static function getFulfillmentModeOptions(): array
    {
        return [
            'company_to_destination' => 'Company handles delivery to destination',
            'third_party_to_company_to_destination' => 'Supplier to company warehouse, then to destination',
            'third_party_to_destination' => 'Supplier delivers directly to destination',
        ];
    }

    /**
     * Validate part profile fields for route resolution
     *
     * @param array<string,mixed> $partProfile
     * @return array<string,string> Empty array if valid, otherwise error messages
     */
    public static function validateProfile(array $partProfile): array
    {
        $errors = [];

        $productionSource = (string)($partProfile['supply_mode'] ?? '');
        if ($productionSource === '') {
            $errors['supply_mode'] = 'Production source is required';
        } elseif (!in_array($productionSource, ['in_house', 'third_party', 'hybrid'], true)) {
            $errors['supply_mode'] = 'Invalid production source';
        }

        $fulfillmentMode = self::normalizeFulfillmentMode((string)($partProfile['fulfillment_mode'] ?? ''));
        if ($fulfillmentMode === '') {
            $errors['fulfillment_mode'] = 'Fulfillment mode is required';
        } elseif (!in_array($fulfillmentMode, ['company_to_destination', 'third_party_to_company_to_destination', 'third_party_to_destination'], true)) {
            $errors['fulfillment_mode'] = 'Invalid fulfillment mode';
        }

        $essentialStock = (string)($partProfile['essential_stock_qty'] ?? '');
        if ($essentialStock !== '' && (!is_numeric($essentialStock) || (float)$essentialStock < 0)) {
            $errors['essential_stock_qty'] = 'Essential stock must be a non-negative number';
        }

        $planningWindow = (string)($partProfile['planning_window_days'] ?? '');
        if ($planningWindow !== '' && (!is_numeric($planningWindow) || (int)$planningWindow < 0)) {
            $errors['planning_window_days'] = 'Planning window must be a non-negative integer';
        }

        $maxBuffer = (string)($partProfile['max_buffer_qty'] ?? '');
        if ($maxBuffer !== '' && (!is_numeric($maxBuffer) || (float)$maxBuffer < 0)) {
            $errors['max_buffer_qty'] = 'Max buffer must be a non-negative number';
        }

        return $errors;
    }

    /**
     * Dispatch mode override for routing behavior.
     */
    public static function normalizeDispatchMode(string $value): string
    {
        $mode = strtolower(trim($value));
        return match ($mode) {
            'internal' => 'internal',
            'direct_supplier' => 'direct_supplier',
            'hybrid' => 'hybrid',
            default => '',
        };
    }

    /**
     * @param mixed $value
     */
    public static function normalizeRequiresProcessing($value, string $rawFulfillmentMode = ''): bool
    {
        $v = strtolower(trim((string)$value));
        if ($v === '1' || $v === 'true' || $v === 'yes' || $v === 'on') {
            return true;
        }
        if ($v === '0' || $v === 'false' || $v === 'no' || $v === 'off') {
            return false;
        }

        // Backward compatibility: legacy via_ipm defaults to processing required.
        if (strtolower(trim($rawFulfillmentMode)) === 'via_ipm') {
            return true;
        }

        return true;
    }

    public static function effectiveDispatchMode(string $dispatchModeOverride, string $fulfillmentMode): string
    {
        $mode = self::normalizeDispatchMode($dispatchModeOverride);
        if ($mode !== '') {
            return $mode;
        }

        $fm = self::normalizeFulfillmentMode($fulfillmentMode);
        return match ($fm) {
            'third_party_to_destination' => 'direct_supplier',
            'third_party_to_company_to_destination' => 'hybrid',
            default => 'internal',
        };
    }

    /**
     * Normalize fulfillment mode from canonical or legacy values.
     *
     * Canonical values:
     * - company_to_destination
     * - third_party_to_company_to_destination
     * - third_party_to_destination
     *
     * Legacy values accepted and mapped:
     * - via_ipm -> company_to_destination
     * - direct_supplier -> third_party_to_destination
     * - mixed -> third_party_to_company_to_destination
     */
    public static function normalizeFulfillmentMode(string $value): string
    {
        $mode = strtolower(trim($value));
        return match ($mode) {
            'company_to_destination' => 'company_to_destination',
            'third_party_to_company_to_destination' => 'third_party_to_company_to_destination',
            'third_party_to_destination' => 'third_party_to_destination',
            'via_ipm' => 'company_to_destination',
            'direct_supplier' => 'third_party_to_destination',
            'mixed' => 'third_party_to_company_to_destination',
            default => 'company_to_destination',
        };
    }
}
