<?php
declare(strict_types=1);

namespace Plugins\Products\Services;

use App\Core\DB;
use Plugins\MaterialManagement\Services\MaterialSchemaService;

require_once APP_ROOT . '/app/Core/helpers.php';
require_once APP_ROOT . '/apps/Manufacturing/modules/MaterialManagement/Services/MaterialSchemaService.php';
require_once __DIR__ . '/PartEngineeringSchemaService.php';
require_once __DIR__ . '/PartExecutionRouteResolver.php';

final class Part360Service
{
    /**
     * @var array<string,array{warn:int,breach:int,label:string}>
     */
    private const SLA_HOURS = [
        'production_to_qc' => ['warn' => 4, 'breach' => 12, 'label' => 'Waiting QC'],
        'qc_to_dispatch' => ['warn' => 2, 'breach' => 8, 'label' => 'QC Release'],
        'dispatch_to_completion' => ['warn' => 4, 'breach' => 12, 'label' => 'Dispatch Hold'],
        'order_risk' => ['warn' => 12, 'breach' => 24, 'label' => 'Order Risk'],
    ];

    /**
     * @return array<string,mixed>|null
     */
    public static function build(int $productId): ?array
    {
        if ($productId <= 0) {
            return null;
        }

        MaterialSchemaService::ensureSchema();
        PartEngineeringSchemaService::ensureSchema();

        $product = DB::fetchOne(
            "SELECT p.*, COALESCE(lb.stock_balance, 0) AS stock_balance
             FROM products p
             LEFT JOIN (
                 SELECT product_id, COALESCE(SUM(qty_delta), 0) AS stock_balance
                 FROM stock_ledger_entries
                 GROUP BY product_id
             ) lb ON lb.product_id = p.id
             WHERE p.id = ?
             LIMIT 1",
            [$productId]
        );

        if (!$product) {
            return null;
        }

        $today = date('Y-m-d');
        $recentStart = self::shiftDate($today, -60);
        $historyStart = self::shiftDate($today, -1095);
        $futureEnd = self::shiftDate($today, 180);

        $demandSummary = self::demandSummary($productId);
        $orderTimeline = self::orderTimeline($productId, $historyStart, $today, $futureEnd);
        $orderRangeSummary = self::orderRangeSummary($orderTimeline, $today);
        $stockSummary = self::stockSummary($productId);
        $recentLedger = self::recentLedger($productId);
        $plannedSupplySummary = self::plannedSupplySummary($productId, $futureEnd);
        $recentProductionPlans = self::recentProductionPlans($productId, $recentStart, $futureEnd);
        $productionSummary = self::productionSummary($productId, $recentStart);
        $recentProductionEntries = self::recentProductionEntries($productId, $recentStart);
        $qcSummary = self::qcSummary($productId, $recentStart, $futureEnd);
        $recentQcPlans = self::recentQcPlans($productId, $recentStart, $futureEnd);
        $recentQcEntries = self::recentQcEntries($productId, $recentStart);
        $dispatchSummary = self::dispatchSummary($productId, $recentStart);
        $recentDispatchEntries = self::recentDispatchEntries($productId, $recentStart);
        $machineUsage = self::machineUsage($productId, $recentStart, $futureEnd);
        $molds = self::molds($productId);
        $partMaterials = self::partMaterials($productId);
        $moldFitSummary = self::moldFitSummary($molds, $partMaterials);
        $machineAssignment = self::machineAssignment($product, $moldFitSummary);
        $responsibility = self::responsibility($productId);
        $handoff = self::handoffSummary($productId, $orderTimeline, $recentProductionEntries, $recentQcEntries, $recentDispatchEntries);
        $riskSummary = self::riskSummary($product, $demandSummary, $qcSummary, $dispatchSummary, $machineAssignment, $handoff);
        $executionRoute = PartExecutionRouteResolver::resolveRoute($product);
        $supplyProfile = self::supplyProfile($product);
        $operationalSnapshot = self::operationalSnapshot($demandSummary, $stockSummary, $plannedSupplySummary, $qcSummary, $dispatchSummary);
        $orderContext = self::orderContextBuckets($orderTimeline);
        $workflowContext = self::workflowContext($product, $demandSummary, $qcSummary, $dispatchSummary, $handoff, $riskSummary, $executionRoute);
        $recommendedActions = self::recommendedActions($product, $demandSummary, $dispatchSummary, $riskSummary);
        $timeline = self::buildTimeline($recentProductionPlans, $recentProductionEntries, $recentQcEntries, $recentDispatchEntries, $recentLedger, $handoff);

        return [
            'product' => $product,
            'today' => $today,
            'supply_profile' => $supplyProfile,
            'operational_snapshot' => $operationalSnapshot,
            'order_context' => $orderContext,
            'workflow_context' => $workflowContext,
            'recommended_actions' => $recommendedActions,
            'timeline' => $timeline,
            'demand_summary' => $demandSummary,
            'order_timeline' => $orderTimeline,
            'order_range_summary' => $orderRangeSummary,
            'stock_summary' => $stockSummary,
            'recent_ledger' => $recentLedger,
            'planned_supply_summary' => $plannedSupplySummary,
            'recent_production_plans' => $recentProductionPlans,
            'production_summary' => $productionSummary,
            'recent_production_entries' => $recentProductionEntries,
            'qc_summary' => $qcSummary,
            'recent_qc_plans' => $recentQcPlans,
            'recent_qc_entries' => $recentQcEntries,
            'dispatch_summary' => $dispatchSummary,
            'recent_dispatch_entries' => $recentDispatchEntries,
            'machine_assignment' => $machineAssignment,
            'responsibility' => $responsibility,
            'machine_usage' => $machineUsage,
            'molds' => $molds,
            'part_materials' => $partMaterials,
            'mold_fit_summary' => $moldFitSummary,
            'handoff' => $handoff,
            'risk_summary' => $riskSummary,
            'execution_route' => $executionRoute,
            'links' => [
                'part_edit' => '/products/edit?id=' . $productId,
                'part_delete' => '/products/delete',
                'part_activate' => '/products/activate',
                'part_deactivate' => '/products/deactivate',
                'part_list' => '/products',
                'label_print' => '/qr/product/label',
                'label_copies_default' => 8,
                'label_machine_no_default' => trim((string)($product['active_machine_id'] ?? '')) !== ''
                    ? (string)(DB::fetchOne('SELECT machine_no FROM machines WHERE id=? LIMIT 1', [(int)($product['active_machine_id'] ?? 0)])['machine_no'] ?? '')
                    : '',
                'label_case_number_default' => (string)($product['default_case_number'] ?? ''),
                'orders_search' => '/daily-orders?q=' . urlencode((string)($product['parts_number'] ?? '')),
                'ledger' => '/ledger?product_id=' . $productId,
                'materials' => '/apps/manufacturing/materials',
                'production_entries' => '/production-entries?product_id=' . $productId,
                'notifications' => '/ops/notifications',
            ],
            'material_options' => self::materialOptions(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function responsibility(int $productId): array
    {
        $assignedUser = DB::fetchOne(
            "SELECT
                u.id,
                u.email,
                COALESCE(NULLIF(TRIM(u.display_name), ''), NULLIF(TRIM(u.username), ''), u.email) AS display_name,
                COALESCE(NULLIF(TRIM(u.department), ''), '') AS department,
                COALESCE(NULLIF(TRIM(a.dashboard_type), ''), '') AS dashboard_type,
                COALESCE(NULLIF(TRIM(u.authority_role), ''), 'app_user') AS authority_role
             FROM user_operational_scopes s
             INNER JOIN users u ON u.id = s.user_id
             LEFT JOIN user_dashboard_assignments a ON a.user_id = u.id
             WHERE s.part_id = ?
               AND s.is_active = 1
             ORDER BY
                CASE COALESCE(NULLIF(TRIM(a.dashboard_type), ''), '')
                    WHEN 'production_leader' THEN 0
                    WHEN 'operator' THEN 1
                    WHEN 'my_work' THEN 2
                    ELSE 3
                END,
                u.email ASC
             LIMIT 1",
            [$productId]
        ) ?: null;

        if (is_array($assignedUser)) {
            $assignedDashboardType = strtolower(trim((string)($assignedUser['dashboard_type'] ?? '')));
            $assignedAccountType = strtolower(trim((string)($assignedUser['authority_role'] ?? 'app_user')));
            $assignedUser['responsibility_label'] = match ($assignedDashboardType) {
                'production_leader' => 'Production Lead',
                'operator', 'my_work' => 'Production Operator',
                'assembly_leader' => 'Assembly Lead',
                'qc_leader' => 'QC Lead',
                'dispatch_leader' => 'Dispatch Lead',
                'app_admin' => 'App Admin',
                'platform_admin' => 'Platform Admin',
                default => ($assignedAccountType === 'app_admin' ? 'App Admin' : ($assignedAccountType === 'platform_admin' ? 'Platform Admin' : 'User')),
            };
        }

        $availableUsers = DB::fetchAll(
            "SELECT
                u.id,
                u.email,
                COALESCE(NULLIF(TRIM(u.display_name), ''), NULLIF(TRIM(u.username), ''), u.email) AS display_name,
                COALESCE(NULLIF(TRIM(u.department), ''), '') AS department,
                COALESCE(NULLIF(TRIM(a.dashboard_type), ''), '') AS dashboard_type,
                COALESCE(NULLIF(TRIM(u.authority_role), ''), 'app_user') AS authority_role,
                COALESCE(NULLIF(TRIM(u.account_status), ''), 'active') AS account_status
             FROM users u
             LEFT JOIN user_dashboard_assignments a ON a.user_id = u.id
             WHERE COALESCE(NULLIF(TRIM(u.account_status), ''), 'active') = 'active'
               AND (
                    FIND_IN_SET('manufacturing', COALESCE(a.assigned_apps, '')) > 0
                    OR COALESCE(NULLIF(TRIM(u.authority_role), ''), '') IN ('platform_admin', 'app_admin')
               )
             ORDER BY
                CASE COALESCE(NULLIF(TRIM(a.dashboard_type), ''), '')
                    WHEN 'production_leader' THEN 0
                    WHEN 'operator' THEN 1
                    WHEN 'my_work' THEN 2
                    WHEN 'assembly_leader' THEN 3
                    WHEN 'qc_leader' THEN 4
                    WHEN 'dispatch_leader' THEN 5
                    WHEN 'app_admin' THEN 6
                    WHEN 'platform_admin' THEN 7
                    ELSE 8
                END,
                display_name ASC,
                u.email ASC"
        );

        foreach ($availableUsers as &$user) {
            $dashboardType = strtolower(trim((string)($user['dashboard_type'] ?? '')));
            $authorityRole = strtolower(trim((string)($user['authority_role'] ?? 'app_user')));
            $user['responsibility_label'] = match ($dashboardType) {
                'production_leader' => 'Production Lead',
                'operator', 'my_work' => 'Production Operator',
                'assembly_leader' => 'Assembly Lead',
                'qc_leader' => 'QC Lead',
                'dispatch_leader' => 'Dispatch Lead',
                'app_admin' => 'App Admin',
                'platform_admin' => 'Platform Admin',
                default => ($authorityRole === 'app_admin' ? 'App Admin' : ($authorityRole === 'platform_admin' ? 'Platform Admin' : 'User')),
            };
        }
        unset($user);

        return [
            'assigned_user' => $assignedUser,
            'available_users' => $availableUsers,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function molds(int $productId): array
    {
        return DB::fetchAll(
            "SELECT *
             FROM part_molds
             WHERE product_id = ?
             ORDER BY
                CASE LOWER(status) WHEN 'active' THEN 0 WHEN 'standby' THEN 1 ELSE 2 END,
                mold_code ASC, id ASC",
            [$productId]
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function partMaterials(int $productId): array
    {
        return DB::fetchAll(
            "SELECT
                pm.*,
                COALESCE(NULLIF(m.material_number, ''), m.material_code, pm.material_code_snapshot) AS material_code_resolved,
                COALESCE(m.material_name, pm.material_name_snapshot) AS material_name_resolved,
                COALESCE(m.material_type, pm.material_type_snapshot) AS material_type_resolved,
                COALESCE(m.vendor_name, m.supplier_name, pm.vendor_name_snapshot) AS vendor_name_resolved,
                COALESCE(m.supplier_ref, pm.vendor_code_snapshot) AS vendor_code_resolved,
                COALESCE(m.uom, m.unit, pm.uom, pm.usage_unit) AS material_unit,
                COALESCE(NULLIF(pm.qty_per_part, 0), pm.usage_qty, 0) AS usage_qty,
                COALESCE(pm.uom, pm.usage_unit, m.uom, m.unit, 'kg') AS usage_unit
             FROM part_material_map pm
             LEFT JOIN materials m ON m.id = pm.material_id
             WHERE pm.product_id = ?
             ORDER BY pm.is_active DESC, pm.sequence_no ASC, pm.id ASC",
            [$productId]
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function materialOptions(): array
    {
        try {
            return DB::fetchAll(
                "SELECT
                    id,
                    COALESCE(NULLIF(material_number, ''), material_code) AS material_number,
                    COALESCE(NULLIF(material_number, ''), material_code) AS material_code,
                    material_name,
                    material_type,
                    COALESCE(vendor_name, supplier_name) AS supplier_name,
                    supplier_ref,
                    COALESCE(uom, unit, 'kg') AS unit
                 FROM materials
                 WHERE is_active = 1
                 ORDER BY material_name ASC"
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param array<int,array<string,mixed>> $molds
     * @param array<int,array<string,mixed>> $partMaterials
     * @return array<int,array<string,mixed>>
     */
    private static function moldFitSummary(array $molds, array $partMaterials): array
    {
        $machines = [];
        try {
            $machines = DB::fetchAll(
                "SELECT id, machine_no, machine_name, machine_group, machine_type,
                        clamping_force_ton, shot_capacity_g, tie_bar_spacing_mm, platen_size_mm,
                        min_mold_height_mm, max_mold_height_mm, capacity_per_hour
                 FROM machines
                 WHERE is_active = 1
                 ORDER BY machine_no ASC"
            );
        } catch (\Throwable $e) {
            $machines = [];
        }

        $preferredMaterials = self::preferredMaterialTokens($partMaterials);
        $out = [];
        foreach ($molds as $mold) {
            $fits = [];
            $reviews = [];
            $blocked = [];
            foreach ($machines as $machine) {
                $evaluation = self::evaluateMoldFit($mold, $machine, $preferredMaterials);
                $row = [
                    'machine_id' => (int)($machine['id'] ?? 0),
                    'machine_no' => (string)($machine['machine_no'] ?? ''),
                    'machine_name' => (string)($machine['machine_name'] ?? ''),
                    'machine_group' => (string)($machine['machine_group'] ?? ''),
                    'machine_type' => (string)($machine['machine_type'] ?? ''),
                    'status' => $evaluation['status'],
                    'reasons' => $evaluation['reasons'],
                    'preference' => $evaluation['preference'],
                ];
                if ($evaluation['status'] === 'fit') {
                    $fits[] = $row;
                } elseif ($evaluation['status'] === 'review') {
                    $reviews[] = $row;
                } else {
                    $blocked[] = $row;
                }
            }

            $out[] = [
                'mold_id' => (int)($mold['id'] ?? 0),
                'mold_code' => (string)($mold['mold_code'] ?? ''),
                'mold_name' => (string)($mold['mold_name'] ?? ''),
                'fit_count' => count($fits),
                'review_count' => count($reviews),
                'blocked_count' => count($blocked),
                'top_fits' => array_slice($fits, 0, 5),
                'top_reviews' => array_slice($reviews, 0, 3),
            ];
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $mold
     * @param array<string,mixed> $machine
     * @param array<int,string> $preferredMaterials
     * @return array{status:string,reasons:array<int,string>,preference:string}
     */
    private static function evaluateMoldFit(array $mold, array $machine, array $preferredMaterials = []): array
    {
        $reasons = [];
        $fits = true;
        $review = false;

        $reqClamp = (float)($mold['min_clamp_ton_required'] ?? 0);
        $reqShot = (float)($mold['min_shot_g_required'] ?? 0);
        $moldThickness = (float)($mold['mold_thickness_mm'] ?? 0);
        $moldWidth = (float)($mold['mold_width_mm'] ?? 0);
        $moldHeight = (float)($mold['mold_height_mm'] ?? 0);

        $machineClamp = (float)($machine['clamping_force_ton'] ?? 0);
        if ($reqClamp > 0 && $machineClamp > 0 && $machineClamp < $reqClamp) {
            $fits = false;
            $reasons[] = 'Clamp tonnage below mold requirement.';
        } elseif ($reqClamp > 0 && $machineClamp <= 0) {
            $review = true;
            $reasons[] = 'Machine clamp tonnage is missing.';
        }

        $machineShot = (float)($machine['shot_capacity_g'] ?? 0);
        if ($reqShot > 0 && $machineShot > 0 && $machineShot < $reqShot) {
            $fits = false;
            $reasons[] = 'Shot capacity below mold requirement.';
        } elseif ($reqShot > 0 && $machineShot <= 0) {
            $review = true;
            $reasons[] = 'Machine shot capacity is missing.';
        }

        $machineMinHeight = (float)($machine['min_mold_height_mm'] ?? 0);
        $machineMaxHeight = (float)($machine['max_mold_height_mm'] ?? 0);
        if ($moldThickness > 0) {
            if ($machineMinHeight > 0 && $moldThickness < $machineMinHeight) {
                $fits = false;
                $reasons[] = 'Mold thickness is below the machine mold-height window.';
            }
            if ($machineMaxHeight > 0 && $moldThickness > $machineMaxHeight) {
                $fits = false;
                $reasons[] = 'Mold thickness exceeds the machine mold-height window.';
            }
            if ($machineMinHeight <= 0 && $machineMaxHeight <= 0) {
                $review = true;
                $reasons[] = 'Machine mold-height window is not defined.';
            }
        }

        if ($moldWidth > 0 && $moldHeight > 0) {
            $fitArea = self::fitsArea($moldWidth, $moldHeight, (string)($machine['tie_bar_spacing_mm'] ?? ''), (string)($machine['platen_size_mm'] ?? ''));
            if ($fitArea === false) {
                $fits = false;
                $reasons[] = 'Mold footprint exceeds tie-bar spacing or platen size.';
            } elseif ($fitArea === null) {
                $review = true;
                $reasons[] = 'Machine footprint dimensions are incomplete.';
            }
        }

        $materialPreference = self::evaluateMaterialPreference((string)($machine['preferred_materials'] ?? ''), $preferredMaterials);
        if ($materialPreference === false) {
            $review = true;
            $reasons[] = 'Preferred resin family is outside the machine material preference list.';
        } elseif ($materialPreference === null && $preferredMaterials !== []) {
            $review = true;
            $reasons[] = 'Machine preferred materials are not defined.';
        }

        $preference = 'General fit';
        $preferredGroup = strtolower(trim((string)($mold['preferred_machine_group'] ?? '')));
        $preferredType = strtolower(trim((string)($mold['preferred_machine_type'] ?? '')));
        $machineGroup = strtolower(trim((string)($machine['machine_group'] ?? '')));
        $machineType = strtolower(trim((string)($machine['machine_type'] ?? '')));
        if ($preferredGroup !== '') {
            if ($preferredGroup === $machineGroup) {
                $preference = 'Preferred machine group';
            } else {
                $preference = 'Usable outside preferred group';
            }
        }
        if ($preferredType !== '') {
            if ($preferredType === $machineType) {
                $preference = $preferredGroup !== '' && $preferredGroup === $machineGroup
                    ? 'Preferred group and machine type'
                    : 'Preferred machine type';
            } elseif ($preferredGroup === '' || $preferredGroup === $machineGroup) {
                $preference = 'Usable outside preferred machine type';
            }
        }

        if ($fits && !$review) {
            if ($reasons === []) {
                $reasons[] = 'Clamp, shot, mold height, and footprint checks passed.';
            }
            return ['status' => 'fit', 'reasons' => $reasons, 'preference' => $preference];
        }

        if ($fits && $review) {
            return ['status' => 'review', 'reasons' => $reasons, 'preference' => $preference];
        }

        return ['status' => 'blocked', 'reasons' => $reasons, 'preference' => $preference];
    }

    private static function fitsArea(float $moldWidth, float $moldHeight, string $tieBar, string $platen): ?bool
    {
        $pairs = array_filter([
            self::parseDimensionPair($tieBar),
            self::parseDimensionPair($platen),
        ]);

        if ($pairs === []) {
            return null;
        }

        foreach ($pairs as $pair) {
            [$w, $h] = $pair;
            if (($w >= $moldWidth && $h >= $moldHeight) || ($w >= $moldHeight && $h >= $moldWidth)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0:float,1:float}|null
     */
    private static function parseDimensionPair(string $value): ?array
    {
        $clean = strtolower(trim($value));
        if ($clean === '') {
            return null;
        }
        $clean = str_replace(['×', '*'], 'x', $clean);
        if (!preg_match('/(\d+(?:\.\d+)?)\s*x\s*(\d+(?:\.\d+)?)/', $clean, $m)) {
            return null;
        }

        return [(float)$m[1], (float)$m[2]];
    }

    /**
     * @param array<int,array<string,mixed>> $partMaterials
     * @return array<int,string>
     */
    private static function preferredMaterialTokens(array $partMaterials): array
    {
        $tokens = [];
        foreach ($partMaterials as $row) {
            foreach ([
                (string)($row['material_type_resolved'] ?? ''),
                (string)($row['material_name_resolved'] ?? ''),
                (string)($row['material_code_resolved'] ?? ''),
            ] as $value) {
                $token = strtolower(trim($value));
                if ($token !== '') {
                    $tokens[$token] = $token;
                }
            }
        }

        return array_values($tokens);
    }

    /**
     * @param array<int,string> $preferredMaterials
     */
    private static function evaluateMaterialPreference(string $machinePreferredMaterials, array $preferredMaterials): ?bool
    {
        if ($preferredMaterials === []) {
            return true;
        }

        $machineTokens = array_values(array_filter(array_map(
            static fn(string $value): string => strtolower(trim($value)),
            preg_split('/[,\\/|]+/', $machinePreferredMaterials) ?: []
        )));

        if ($machineTokens === []) {
            return null;
        }

        foreach ($preferredMaterials as $token) {
            foreach ($machineTokens as $machineToken) {
                if ($machineToken !== '' && (str_contains($token, $machineToken) || str_contains($machineToken, $token))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $product
     * @return array<string,mixed>
     */
    private static function supplyProfile(array $product): array
    {
        $supplyMode = strtolower(trim((string)($product['supply_mode'] ?? 'in_house')));
        $fulfillmentMode = strtolower(trim((string)($product['fulfillment_mode'] ?? 'via_ipm')));
        $requiresAssembly = (int)($product['requires_assembly'] ?? 0) === 1;
        $requiresQc = (int)($product['requires_ipm_qc'] ?? 1) === 1;
        $stockedAtIpm = (int)($product['stocked_at_ipm'] ?? 1) === 1;

        $flowNarrative = 'In-house supply through IPM workflow.';
        if ($supplyMode === 'third_party') {
            $flowNarrative = 'Third-party supplied part with external dependency risk.';
        } elseif ($supplyMode === 'hybrid') {
            $flowNarrative = 'Hybrid supply path requiring coordinated sourcing.';
        }

        if ($fulfillmentMode === 'direct_supplier') {
            $flowNarrative = 'Direct supplier fulfillment with reduced in-house stock touch.';
        } elseif ($fulfillmentMode === 'mixed') {
            $flowNarrative = 'Mixed fulfillment across IPM and direct supplier paths.';
        }

        return [
            'supply_mode' => $supplyMode,
            'fulfillment_mode' => $fulfillmentMode,
            'requires_assembly' => $requiresAssembly,
            'requires_ipm_qc' => $requiresQc,
            'default_supplier' => trim((string)($product['default_supplier'] ?? '')),
            'stocked_at_ipm' => $stockedAtIpm,
            'default_procurement_lead_days' => (float)($product['default_procurement_lead_days'] ?? 0),
            'default_supply_note' => trim((string)($product['default_supply_note'] ?? '')),
            'flow_narrative' => $flowNarrative,
        ];
    }

    /**
     * @param array<string,mixed> $demandSummary
     * @param array<string,mixed> $stockSummary
     * @param array<string,mixed> $plannedSupplySummary
     * @param array<string,mixed> $qcSummary
     * @param array<string,mixed> $dispatchSummary
     * @return array<string,mixed>
     */
    private static function operationalSnapshot(array $demandSummary, array $stockSummary, array $plannedSupplySummary, array $qcSummary, array $dispatchSummary): array
    {
        $coveragePressureQty = max(0.0, (float)($demandSummary['open_demand_qty'] ?? 0) - ((float)($stockSummary['current_balance'] ?? 0) + (float)($plannedSupplySummary['active_planned_qty'] ?? 0) + (float)($qcSummary['pass_qty'] ?? 0)));

        return [
            'current_stock_qty' => round((float)($stockSummary['current_balance'] ?? 0), 2),
            'open_demand_qty' => round((float)($demandSummary['open_demand_qty'] ?? 0), 2),
            'planned_supply_qty' => round((float)($plannedSupplySummary['active_planned_qty'] ?? 0), 2),
            'qc_pending_qty' => round(max(0.0, (float)($qcSummary['checked_qty'] ?? 0) - (float)($qcSummary['pass_qty'] ?? 0)), 2),
            'qc_pass_qty' => round((float)($qcSummary['pass_qty'] ?? 0), 2),
            'dispatch_pending_qty' => round((float)($dispatchSummary['ready_qty'] ?? 0), 2),
            'dispatch_released_qty' => round((float)($dispatchSummary['dispatched_qty'] ?? 0), 2),
            'coverage_pressure_qty' => round($coveragePressureQty, 2),
            'low_coverage_orders' => (int)($demandSummary['low_coverage_orders'] ?? 0),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $orderTimeline
     * @return array<string,array<int,array<string,mixed>>>
     */
    private static function orderContextBuckets(array $orderTimeline): array
    {
        $urgent = [];
        $lowCoverage = [];
        $partial = [];

        foreach ($orderTimeline as $row) {
            if (strtolower(trim((string)($row['source_type'] ?? 'daily_order'))) !== 'daily_order') {
                continue;
            }
            $coverageStatus = strtolower(trim((string)($row['coverage_status'] ?? '')));
            $coveragePct = (float)($row['coverage_pct'] ?? 0);
            $due = trim((string)($row['dispatch_deadline'] ?? $row['required_date'] ?? ''));
            if ($due !== '') {
                $hours = self::hoursDiff(date('Y-m-d H:i:s'), strlen($due) === 10 ? ($due . ' 23:59:59') : $due);
                if ($hours <= 48.0) {
                    $urgent[] = $row;
                }
            }
            if ($coverageStatus === 'low' || $coveragePct < 50.0) {
                $lowCoverage[] = $row;
            }
            if ($coverageStatus === 'partial' || ((float)($row['open_demand_qty'] ?? 0) > 0 && $coveragePct > 0 && $coveragePct < 100)) {
                $partial[] = $row;
            }
        }

        return [
            'urgent_orders' => array_slice($urgent, 0, 8),
            'low_coverage_orders' => array_slice($lowCoverage, 0, 8),
            'partial_orders' => array_slice($partial, 0, 8),
        ];
    }

    /**
     * @param array<string,mixed> $product
     * @param array<string,mixed> $demandSummary
     * @param array<string,mixed> $qcSummary
     * @param array<string,mixed> $dispatchSummary
     * @param array<string,mixed> $handoff
     * @param array<string,mixed> $riskSummary
     * @param array<string,mixed> $executionRoute
     * @return array<string,mixed>
     */
    private static function workflowContext(array $product, array $demandSummary, array $qcSummary, array $dispatchSummary, array $handoff, array $riskSummary, array $executionRoute): array
    {
        $handoffCounts = (array)($handoff['counts'] ?? []);
        $requiresAssembly = (int)($product['requires_assembly'] ?? 0) === 1;
        $planningNeeded = (float)($demandSummary['shortage_qty'] ?? 0) > 0.0001 || (int)($demandSummary['low_coverage_orders'] ?? 0) > 0;
        $awaitingQc = (float)($qcSummary['blocked_entries'] ?? 0) > 0 || (float)($qcSummary['checked_qty'] ?? 0) > (float)($qcSummary['pass_qty'] ?? 0);
        $readyDispatch = (float)($dispatchSummary['ready_qty'] ?? 0) > 0.0001;
        $partiallyReleased = (float)($dispatchSummary['dispatched_qty'] ?? 0) > 0.0001 && (float)($demandSummary['open_demand_qty'] ?? 0) > 0.0001;
        $supplyBlocked = (float)($demandSummary['shortage_qty'] ?? 0) > 0.0001 || (int)($dispatchSummary['blocked_entries'] ?? 0) > 0 || (int)($handoffCounts['blocked'] ?? 0) > 0;
        $executionPath = trim((string)($executionRoute['description'] ?? ''));

        $stage = 'Coverage Healthy';
        if ((string)($riskSummary['state'] ?? '') === 'blocked') {
            $stage = 'Supply Blocked';
        } elseif ($requiresAssembly) {
            $stage = 'Assembly Required';
        } elseif ($awaitingQc) {
            $stage = 'Awaiting QC';
        } elseif ($readyDispatch) {
            $stage = 'Ready for Dispatch';
        } elseif ($planningNeeded) {
            $stage = 'Planning Needed';
        }

        if ($partiallyReleased) {
            $stage = 'Partially Released';
        }

        return [
            'stage_label' => $stage,
            'requires_assembly' => $requiresAssembly,
            'planning_needed' => $planningNeeded,
            'awaiting_qc' => $awaitingQc,
            'ready_for_dispatch' => $readyDispatch,
            'partially_released' => $partiallyReleased,
            'supply_blocked' => $supplyBlocked,
            'coverage_healthy' => (string)($riskSummary['state'] ?? '') === 'healthy',
            'execution_path' => $executionPath,
        ];
    }

    /**
     * @param array<string,mixed> $product
     * @param array<string,mixed> $demandSummary
     * @param array<string,mixed> $dispatchSummary
     * @param array<string,mixed> $riskSummary
     * @return array<int,array<string,mixed>>
     */
    private static function recommendedActions(array $product, array $demandSummary, array $dispatchSummary, array $riskSummary): array
    {
        $productId = (int)($product['id'] ?? 0);
        $actions = [
            [
                'label' => 'Create Plan Draft',
                'method' => 'post',
                'url' => '/production-plans/start-draft',
                'roles' => ['admin', 'planning', 'machine'],
                'intent' => 'Start recovery planning for open demand pressure.',
                'post' => [
                    'product_id' => (string)$productId,
                    'required_date' => date('Y-m-d'),
                    'shortage_qty' => number_format(max(0.0, (float)($demandSummary['shortage_qty'] ?? 0)), 2, '.', ''),
                    'plan_type' => 'Recovery',
                    'failure_fallback' => '/apps/manufacturing/products/360?id=' . $productId,
                ],
            ],
            [
                'label' => 'Review Shortage Orders',
                'method' => 'link',
                'url' => '/daily-orders?product_id=' . $productId . '&coverage=low',
                'roles' => ['all'],
                'intent' => 'Prioritize orders with low coverage for this part.',
            ],
            [
                'label' => 'Check Stock Ledger',
                'method' => 'link',
                'url' => '/ledger?product_id=' . $productId,
                'roles' => ['all'],
                'intent' => 'Confirm movement history and balance integrity.',
            ],
            [
                'label' => 'Review QC Worklist',
                'method' => 'link',
                'url' => '/qc-entries?product_id=' . $productId,
                'roles' => ['admin', 'qc'],
                'intent' => 'Investigate pending QC checks and pass/fail flow.',
            ],
            [
                'label' => 'Create Dispatch Draft',
                'method' => 'post',
                'url' => '/dispatch-entries/start-draft',
                'roles' => ['admin', 'dispatch'],
                'intent' => 'Start dispatch from available covered supply.',
                'post' => [
                    'product_id' => (string)$productId,
                    'required_date' => date('Y-m-d'),
                    'covered_qty' => number_format(max(0.0, (float)($dispatchSummary['ready_qty'] ?? 0)), 2, '.', ''),
                    'failure_fallback' => '/apps/manufacturing/products/360?id=' . $productId,
                ],
            ],
        ];

        $supplyMode = strtolower(trim((string)($product['supply_mode'] ?? 'in_house')));
        if ($supplyMode !== 'in_house') {
            $actions[] = [
                'label' => 'Supplier / Procurement Review',
                'method' => 'link',
                'url' => '/products/edit?id=' . $productId,
                'roles' => ['admin', 'planning'],
                'intent' => 'Review external source configuration and procurement settings.',
            ];
        }

        if ((string)($riskSummary['state'] ?? '') === 'blocked') {
            $actions[] = [
                'label' => 'Open Dispatch Ops',
                'method' => 'link',
                'url' => '/apps/manufacturing/dispatch-ops',
                'roles' => ['admin', 'dispatch'],
                'intent' => 'Resolve blocked/hold dispatch rows tied to this part.',
            ];
        }

        return $actions;
    }

    /**
     * @param array<int,array<string,mixed>> $recentProductionPlans
     * @param array<int,array<string,mixed>> $recentProductionEntries
     * @param array<int,array<string,mixed>> $recentQcEntries
     * @param array<int,array<string,mixed>> $recentDispatchEntries
     * @param array<int,array<string,mixed>> $recentLedger
     * @param array<string,mixed> $handoff
     * @return array<int,array<string,mixed>>
     */
    private static function buildTimeline(array $recentProductionPlans, array $recentProductionEntries, array $recentQcEntries, array $recentDispatchEntries, array $recentLedger, array $handoff): array
    {
        $events = [];
        foreach (array_slice($recentProductionPlans, 0, 5) as $row) {
            $events[] = [
                'when' => (string)($row['plan_date'] ?? ''),
                'type' => 'Production Plan',
                'title' => 'Plan #' . (int)($row['id'] ?? 0) . ' planned qty ' . number_format((float)($row['planned_qty'] ?? 0), 2, '.', ','),
                'status' => (string)($row['status'] ?? ''),
                'url' => '/production-plans/edit?id=' . (int)($row['id'] ?? 0),
            ];
        }
        foreach (array_slice($recentProductionEntries, 0, 5) as $row) {
            $events[] = [
                'when' => (string)($row['production_date'] ?? ''),
                'type' => 'Production Entry',
                'title' => 'Entry #' . (int)($row['id'] ?? 0) . ' good qty ' . number_format((float)($row['good_qty'] ?? 0), 2, '.', ','),
                'status' => (string)($row['status'] ?? ''),
                'url' => '/production-entries/edit?id=' . (int)($row['id'] ?? 0),
            ];
        }
        foreach (array_slice($recentQcEntries, 0, 5) as $row) {
            $events[] = [
                'when' => (string)($row['updated_at'] ?? $row['created_at'] ?? ''),
                'type' => 'QC Entry',
                'title' => 'QC #' . (int)($row['id'] ?? 0) . ' pass qty ' . number_format((float)($row['pass_qty'] ?? 0), 2, '.', ','),
                'status' => (string)($row['status'] ?? ''),
                'url' => '/qc-entries/edit?id=' . (int)($row['id'] ?? 0),
            ];
        }
        foreach (array_slice($recentDispatchEntries, 0, 5) as $row) {
            $events[] = [
                'when' => (string)($row['dispatch_date'] ?? ''),
                'type' => 'Dispatch Entry',
                'title' => 'Dispatch #' . (int)($row['id'] ?? 0) . ' qty ' . number_format((float)($row['dispatchable_qty'] ?? 0), 2, '.', ','),
                'status' => (string)($row['dispatch_status'] ?? ''),
                'url' => '/dispatch-entries/edit?id=' . (int)($row['id'] ?? 0),
            ];
        }
        foreach (array_slice($recentLedger, 0, 5) as $row) {
            $events[] = [
                'when' => (string)($row['created_at'] ?? ''),
                'type' => 'Ledger',
                'title' => (string)($row['movement_type'] ?? 'Movement') . ' delta ' . number_format((float)($row['qty_delta'] ?? 0), 2, '.', ','),
                'status' => (string)($row['reference_no'] ?? ''),
                'url' => '/ledger?product_id=' . (int)($row['product_id'] ?? 0),
            ];
        }

        $handoffItems = is_array($handoff['items'] ?? null) ? $handoff['items'] : [];
        foreach (array_slice($handoffItems, 0, 5) as $row) {
            $events[] = [
                'when' => date('Y-m-d H:i:s'),
                'type' => 'Handoff',
                'title' => (string)($row['stage_label'] ?? 'Workflow stage') . ' • owner ' . (string)($row['owner_role'] ?? 'Unassigned'),
                'status' => (string)($row['sla_level'] ?? 'ok'),
                'url' => '/apps/manufacturing/handoffs',
            ];
        }

        usort($events, static function (array $a, array $b): int {
            return strcmp((string)($b['when'] ?? ''), (string)($a['when'] ?? ''));
        });

        return array_slice($events, 0, 20);
    }

    /**
     * @return array<string,mixed>
     */
    private static function demandSummary(int $productId): array
    {
        $row = DB::fetchOne(
            "SELECT
                COUNT(*) AS open_orders,
                COALESCE(SUM(qty), 0) AS demand_qty,
                COALESCE(SUM(open_demand_qty), 0) AS open_demand_qty,
                COALESCE(SUM(shortage_qty), 0) AS shortage_qty,
                COALESCE(SUM(planned_supply_qty), 0) AS planned_supply_qty,
                COALESCE(SUM(usable_stock_qty), 0) AS usable_stock_qty,
                COALESCE(SUM(qc_pass_qty), 0) AS qc_pass_qty,
                COALESCE(SUM(dispatched_qty), 0) AS dispatched_qty,
                COALESCE(AVG(coverage_pct), 0) AS avg_coverage_pct,
                MIN(COALESCE(dispatch_deadline, CONCAT(COALESCE(required_date, order_date), ' 23:59:59'))) AS next_due_at,
                SUM(CASE WHEN LOWER(COALESCE(coverage_status, '')) = 'low' THEN 1 ELSE 0 END) AS low_coverage_orders,
                SUM(CASE WHEN LOWER(COALESCE(coverage_status, '')) = 'partial' THEN 1 ELSE 0 END) AS partial_coverage_orders,
                SUM(CASE WHEN TIMESTAMPDIFF(HOUR, NOW(), COALESCE(dispatch_deadline, CONCAT(COALESCE(required_date, order_date), ' 23:59:59'))) <= 48 THEN 1 ELSE 0 END) AS due_within_48h
             FROM daily_orders
             WHERE product_id = ?
               AND LOWER(COALESCE(status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')",
            [$productId]
        ) ?: [];

        return [
            'open_orders' => (int)($row['open_orders'] ?? 0),
            'demand_qty' => round((float)($row['demand_qty'] ?? 0), 2),
            'open_demand_qty' => round((float)($row['open_demand_qty'] ?? 0), 2),
            'shortage_qty' => round((float)($row['shortage_qty'] ?? 0), 2),
            'planned_supply_qty' => round((float)($row['planned_supply_qty'] ?? 0), 2),
            'usable_stock_qty' => round((float)($row['usable_stock_qty'] ?? 0), 2),
            'qc_pass_qty' => round((float)($row['qc_pass_qty'] ?? 0), 2),
            'dispatched_qty' => round((float)($row['dispatched_qty'] ?? 0), 2),
            'avg_coverage_pct' => round((float)($row['avg_coverage_pct'] ?? 0), 2),
            'next_due_at' => (string)($row['next_due_at'] ?? ''),
            'low_coverage_orders' => (int)($row['low_coverage_orders'] ?? 0),
            'partial_coverage_orders' => (int)($row['partial_coverage_orders'] ?? 0),
            'due_within_48h' => (int)($row['due_within_48h'] ?? 0),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function orderTimeline(int $productId, string $historyStart, string $today, string $futureEnd): array
    {
        $rows = DB::fetchAll(
            "SELECT
                id,
                'daily_order' AS source_type,
                order_date,
                required_date,
                dispatch_deadline,
                COALESCE(dispatch_deadline, required_date, order_date) AS due_date,
                customer_name,
                qty,
                open_demand_qty,
                coverage_pct,
                coverage_status,
                shortage_qty,
                status,
                NULL AS forecast_type,
                NULL AS planning_priority
             FROM daily_orders
             WHERE product_id = ?
               AND COALESCE(dispatch_deadline, required_date, order_date) >= ?
               AND COALESCE(dispatch_deadline, required_date, order_date) <= ?
             ORDER BY COALESCE(dispatch_deadline, CONCAT(COALESCE(required_date, order_date), ' 23:59:59')) ASC, id DESC
             LIMIT 500",
            [$productId, $historyStart, $futureEnd]
        );

        $forecastRows = DB::fetchAll(
            "SELECT
                id,
                'pre_order' AS source_type,
                NULL AS order_date,
                required_date,
                NULL AS dispatch_deadline,
                required_date AS due_date,
                'Forecast / Planning' AS customer_name,
                planned_qty AS qty,
                balance_qty AS open_demand_qty,
                NULL AS coverage_pct,
                planning_priority AS coverage_status,
                0 AS shortage_qty,
                'forecast' AS status,
                forecast_type,
                planning_priority
             FROM pre_orders
             WHERE product_id = ?
               AND required_date >= ?
               AND required_date <= ?
               AND COALESCE(balance_qty, 0) > 0
             ORDER BY required_date ASC, id DESC
             LIMIT 180",
            [$productId, $today, $futureEnd]
        );

        $merged = array_merge($rows, $forecastRows);
        usort($merged, static function (array $a, array $b): int {
            $cmp = strcmp((string)($a['due_date'] ?? ''), (string)($b['due_date'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }
            return ((int)($a['id'] ?? 0)) <=> ((int)($b['id'] ?? 0));
        });

        return $merged;
    }

    /**
     * @param array<int,array<string,mixed>> $orderTimeline
     * @return array<int,array<string,mixed>>
     */
    private static function orderRangeSummary(array $orderTimeline, string $today): array
    {
        $ranges = [
            ['key' => 'past_30', 'label' => 'Past 30 Days', 'start' => self::shiftDate($today, -30), 'end' => self::shiftDate($today, -1)],
            ['key' => 'past_90', 'label' => '31-90 Days Ago', 'start' => self::shiftDate($today, -90), 'end' => self::shiftDate($today, -31)],
            ['key' => 'past_365', 'label' => '91-365 Days Ago', 'start' => self::shiftDate($today, -365), 'end' => self::shiftDate($today, -91)],
            ['key' => 'past_3y', 'label' => '1-3 Years Ago', 'start' => self::shiftDate($today, -1095), 'end' => self::shiftDate($today, -366)],
            ['key' => 'next_7', 'label' => 'Next 7 Days', 'start' => $today, 'end' => self::shiftDate($today, 7)],
            ['key' => 'next_30', 'label' => '8-30 Days', 'start' => self::shiftDate($today, 8), 'end' => self::shiftDate($today, 30)],
            ['key' => 'next_60', 'label' => '31-60 Days', 'start' => self::shiftDate($today, 31), 'end' => self::shiftDate($today, 60)],
            ['key' => 'next_180', 'label' => '61-180 Days', 'start' => self::shiftDate($today, 61), 'end' => self::shiftDate($today, 180)],
        ];

        $summary = [];
        foreach ($ranges as $range) {
            $count = 0;
            $qty = 0.0;
            foreach ($orderTimeline as $row) {
                $dueDate = trim((string)($row['due_date'] ?? ''));
                if ($dueDate === '') {
                    continue;
                }
                $start = $range['start'];
                $end = $range['end'];
                if ($dueDate < $start || $dueDate > $end) {
                    continue;
                }
                $count++;
                $qty += (float)($row['open_demand_qty'] ?? $row['qty'] ?? 0);
            }

            $summary[] = [
                'key' => $range['key'],
                'label' => $range['label'],
                'orders' => $count,
                'qty' => round($qty, 2),
            ];
        }

        return $summary;
    }

    /**
     * @return array<string,mixed>
     */
    private static function stockSummary(int $productId): array
    {
        if (!self::tableExists('stock_ledger_entries')) {
            return [
                'current_balance' => 0.0,
                'total_in_qty' => 0.0,
                'total_out_qty' => 0.0,
                'production_in_qty' => 0.0,
                'dispatch_out_qty' => 0.0,
            ];
        }

        $row = DB::fetchOne(
            "SELECT
                COALESCE(SUM(qty_delta), 0) AS current_balance,
                COALESCE(SUM(CASE WHEN qty_delta > 0 THEN qty_delta ELSE 0 END), 0) AS total_in_qty,
                COALESCE(SUM(CASE WHEN qty_delta < 0 THEN ABS(qty_delta) ELSE 0 END), 0) AS total_out_qty,
                COALESCE(SUM(CASE WHEN movement_type IN ('IN', 'PRODUCTION_IN') THEN qty_delta ELSE 0 END), 0) AS production_in_qty,
                COALESCE(SUM(CASE WHEN movement_type IN ('OUT', 'DISPATCH_OUT') AND qty_delta < 0 THEN ABS(qty_delta) ELSE 0 END), 0) AS dispatch_out_qty
             FROM stock_ledger_entries
             WHERE product_id = ?",
            [$productId]
        ) ?: [];

        return [
            'current_balance' => round((float)($row['current_balance'] ?? 0), 2),
            'total_in_qty' => round((float)($row['total_in_qty'] ?? 0), 2),
            'total_out_qty' => round((float)($row['total_out_qty'] ?? 0), 2),
            'production_in_qty' => round((float)($row['production_in_qty'] ?? 0), 2),
            'dispatch_out_qty' => round((float)($row['dispatch_out_qty'] ?? 0), 2),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function recentLedger(int $productId): array
    {
        if (!self::tableExists('stock_ledger_entries')) {
            return [];
        }

        return DB::fetchAll(
            "SELECT id, movement_type, qty_delta, balance_after, reference_no, source_module, source_id, notes, created_at
             FROM stock_ledger_entries
             WHERE product_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT 20",
            [$productId]
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function plannedSupplySummary(int $productId, string $futureEnd): array
    {
        $row = DB::fetchOne(
            "SELECT
                COUNT(*) AS open_plans,
                COALESCE(SUM(planned_qty), 0) AS planned_qty,
                COALESCE(SUM(CASE WHEN plan_date = CURDATE() THEN planned_qty ELSE 0 END), 0) AS today_planned_qty,
                COALESCE(SUM(CASE WHEN plan_date > CURDATE() THEN planned_qty ELSE 0 END), 0) AS upcoming_planned_qty,
                COALESCE(SUM(CASE WHEN LOWER(COALESCE(status, '')) IN ('planned', 'open', 'queued', 'in progress') THEN planned_qty ELSE 0 END), 0) AS active_planned_qty
             FROM production_plans
             WHERE product_id = ?
               AND plan_date <= ?",
            [$productId, $futureEnd]
        ) ?: [];

        return [
            'open_plans' => (int)($row['open_plans'] ?? 0),
            'planned_qty' => round((float)($row['planned_qty'] ?? 0), 2),
            'today_planned_qty' => round((float)($row['today_planned_qty'] ?? 0), 2),
            'upcoming_planned_qty' => round((float)($row['upcoming_planned_qty'] ?? 0), 2),
            'active_planned_qty' => round((float)($row['active_planned_qty'] ?? 0), 2),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function recentProductionPlans(int $productId, string $recentStart, string $futureEnd): array
    {
        return DB::fetchAll(
            "SELECT pp.*, m.machine_no, m.machine_name
             FROM production_plans pp
             LEFT JOIN machines m ON m.id = pp.machine_id
             WHERE pp.product_id = ?
               AND pp.plan_date BETWEEN ? AND ?
             ORDER BY pp.plan_date DESC, pp.id DESC
             LIMIT 25",
            [$productId, $recentStart, $futureEnd]
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function productionSummary(int $productId, string $recentStart): array
    {
        $row = DB::fetchOne(
            "SELECT
                COUNT(*) AS entries,
                COALESCE(SUM(produced_qty), 0) AS produced_qty,
                COALESCE(SUM(good_qty), 0) AS good_qty,
                COALESCE(SUM(rejected_qty), 0) AS rejected_qty,
                MAX(production_date) AS last_production_date
             FROM production_entries
             WHERE product_id = ?
               AND production_date >= ?",
            [$productId, $recentStart]
        ) ?: [];

        return [
            'entries' => (int)($row['entries'] ?? 0),
            'produced_qty' => round((float)($row['produced_qty'] ?? 0), 2),
            'good_qty' => round((float)($row['good_qty'] ?? 0), 2),
            'rejected_qty' => round((float)($row['rejected_qty'] ?? 0), 2),
            'last_production_date' => (string)($row['last_production_date'] ?? ''),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function recentProductionEntries(int $productId, string $recentStart): array
    {
        return DB::fetchAll(
            "SELECT pe.*, m.machine_no, m.machine_name
             FROM production_entries pe
             LEFT JOIN machines m ON m.id = pe.machine_id
             WHERE pe.product_id = ?
               AND pe.production_date >= ?
             ORDER BY pe.production_date DESC, pe.id DESC
             LIMIT 25",
            [$productId, $recentStart]
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function qcSummary(int $productId, string $recentStart, string $futureEnd): array
    {
        $planRow = DB::fetchOne(
            "SELECT
                COUNT(*) AS open_plans,
                COALESCE(SUM(planned_qty), 0) AS planned_qty
             FROM qc_plans
             WHERE product_id = ?
               AND plan_date BETWEEN ? AND ?",
            [$productId, $recentStart, $futureEnd]
        ) ?: [];

        $entryRow = DB::fetchOne(
            "SELECT
                COUNT(*) AS entries,
                COALESCE(SUM(checked_qty), 0) AS checked_qty,
                COALESCE(SUM(pass_qty), 0) AS pass_qty,
                COALESCE(SUM(fail_qty), 0) AS fail_qty,
                SUM(CASE WHEN LOWER(COALESCE(status, '')) IN ('recheck', 'failed', 'hold') THEN 1 ELSE 0 END) AS blocked_entries
             FROM qc_entries
             WHERE product_id = ?
               AND DATE(created_at) >= ?",
            [$productId, $recentStart]
        ) ?: [];

        return [
            'open_plans' => (int)($planRow['open_plans'] ?? 0),
            'planned_qty' => round((float)($planRow['planned_qty'] ?? 0), 2),
            'entries' => (int)($entryRow['entries'] ?? 0),
            'checked_qty' => round((float)($entryRow['checked_qty'] ?? 0), 2),
            'pass_qty' => round((float)($entryRow['pass_qty'] ?? 0), 2),
            'fail_qty' => round((float)($entryRow['fail_qty'] ?? 0), 2),
            'blocked_entries' => (int)($entryRow['blocked_entries'] ?? 0),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function recentQcPlans(int $productId, string $recentStart, string $futureEnd): array
    {
        return DB::fetchAll(
            "SELECT *
             FROM qc_plans
             WHERE product_id = ?
               AND plan_date BETWEEN ? AND ?
             ORDER BY plan_date DESC, id DESC
             LIMIT 25",
            [$productId, $recentStart, $futureEnd]
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function recentQcEntries(int $productId, string $recentStart): array
    {
        return DB::fetchAll(
            "SELECT *
             FROM qc_entries
             WHERE product_id = ?
               AND DATE(created_at) >= ?
             ORDER BY id DESC
             LIMIT 25",
            [$productId, $recentStart]
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function dispatchSummary(int $productId, string $recentStart): array
    {
        $row = DB::fetchOne(
            "SELECT
                COUNT(*) AS entries,
                COALESCE(SUM(dispatchable_qty), 0) AS total_qty,
                COALESCE(SUM(CASE WHEN LOWER(COALESCE(dispatch_status, '')) = 'ready' THEN dispatchable_qty ELSE 0 END), 0) AS ready_qty,
                COALESCE(SUM(CASE WHEN LOWER(COALESCE(dispatch_status, '')) IN ('partial', 'dispatched', 'completed', 'closed', 'delivered') THEN dispatchable_qty ELSE 0 END), 0) AS dispatched_qty,
                COALESCE(SUM(CASE WHEN LOWER(COALESCE(dispatch_status, '')) IN ('hold', 'blocked') THEN dispatchable_qty ELSE 0 END), 0) AS blocked_qty,
                SUM(CASE WHEN LOWER(COALESCE(dispatch_status, '')) IN ('hold', 'blocked') THEN 1 ELSE 0 END) AS blocked_entries
             FROM dispatch_entries
             WHERE product_id = ?
               AND dispatch_date >= ?",
            [$productId, $recentStart]
        ) ?: [];

        return [
            'entries' => (int)($row['entries'] ?? 0),
            'total_qty' => round((float)($row['total_qty'] ?? 0), 2),
            'ready_qty' => round((float)($row['ready_qty'] ?? 0), 2),
            'dispatched_qty' => round((float)($row['dispatched_qty'] ?? 0), 2),
            'blocked_qty' => round((float)($row['blocked_qty'] ?? 0), 2),
            'blocked_entries' => (int)($row['blocked_entries'] ?? 0),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function recentDispatchEntries(int $productId, string $recentStart): array
    {
        return DB::fetchAll(
            "SELECT *
             FROM dispatch_entries
             WHERE product_id = ?
               AND dispatch_date >= ?
             ORDER BY dispatch_date DESC, id DESC
             LIMIT 25",
            [$productId, $recentStart]
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function machineAssignment(array $product, array $moldFitSummary): array
    {
        $machineId = (int)($product['active_machine_id'] ?? 0);
        $compatibleMachines = self::compatibleMachinesFromFitSummary($moldFitSummary);
        $activeMachine = null;
        if ($machineId > 0) {
            $activeMachine = DB::fetchOne(
                "SELECT id, machine_no, machine_name, section, machine_group, machine_type, status, capacity_per_hour
                 FROM machines
                 WHERE id = ?
                 LIMIT 1",
                [$machineId]
            ) ?: null;
        }

        return [
            'active_machine' => $activeMachine,
            'compatible_machines' => $compatibleMachines,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $moldFitSummary
     * @return array<int,array<string,mixed>>
     */
    private static function compatibleMachinesFromFitSummary(array $moldFitSummary): array
    {
        $indexed = [];

        foreach ($moldFitSummary as $mold) {
            foreach ((array)($mold['top_fits'] ?? []) as $machine) {
                $machineId = (int)($machine['machine_id'] ?? 0);
                if ($machineId <= 0) {
                    continue;
                }

                if (!isset($indexed[$machineId])) {
                    $indexed[$machineId] = [
                        'machine_id' => $machineId,
                        'machine_no' => (string)($machine['machine_no'] ?? ''),
                        'machine_name' => (string)($machine['machine_name'] ?? ''),
                        'machine_group' => (string)($machine['machine_group'] ?? ''),
                        'machine_type' => (string)($machine['machine_type'] ?? ''),
                        'fit_molds' => 0,
                        'preferences' => [],
                    ];
                }

                $indexed[$machineId]['fit_molds']++;
                $preference = trim((string)($machine['preference'] ?? ''));
                if ($preference !== '') {
                    $indexed[$machineId]['preferences'][$preference] = $preference;
                }
            }
        }

        foreach ($indexed as &$row) {
            $row['preference_summary'] = implode(' | ', array_values($row['preferences']));
            unset($row['preferences']);
        }
        unset($row);

        usort($indexed, static function (array $a, array $b): int {
            if ((int)$a['fit_molds'] !== (int)$b['fit_molds']) {
                return (int)$b['fit_molds'] <=> (int)$a['fit_molds'];
            }
            return strcmp((string)$a['machine_no'], (string)$b['machine_no']);
        });

        return array_values($indexed);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function machineUsage(int $productId, string $recentStart, string $futureEnd): array
    {
        return DB::fetchAll(
            "SELECT
                m.id AS machine_id,
                m.machine_no,
                m.machine_name,
                m.section,
                COALESCE(pp.plan_count, 0) AS plan_count,
                COALESCE(pp.planned_qty, 0) AS planned_qty,
                COALESCE(pe.entry_count, 0) AS entry_count,
                COALESCE(pe.good_qty, 0) AS good_qty
             FROM machines m
             INNER JOIN (
                SELECT machine_id FROM production_plans WHERE product_id = ? AND plan_date BETWEEN ? AND ?
                UNION
                SELECT machine_id FROM production_entries WHERE product_id = ? AND production_date >= ?
             ) used ON used.machine_id = m.id
             LEFT JOIN (
                SELECT machine_id, COUNT(*) AS plan_count, COALESCE(SUM(planned_qty), 0) AS planned_qty
                FROM production_plans
                WHERE product_id = ? AND plan_date BETWEEN ? AND ?
                GROUP BY machine_id
             ) pp ON pp.machine_id = m.id
             LEFT JOIN (
                SELECT machine_id, COUNT(*) AS entry_count, COALESCE(SUM(good_qty), 0) AS good_qty
                FROM production_entries
                WHERE product_id = ? AND production_date >= ?
                GROUP BY machine_id
             ) pe ON pe.machine_id = m.id
             ORDER BY COALESCE(pe.good_qty, 0) DESC, COALESCE(pp.planned_qty, 0) DESC, m.machine_no ASC",
            [$productId, $recentStart, $futureEnd, $productId, $recentStart, $productId, $recentStart, $futureEnd, $productId, $recentStart]
        );
    }

    /**
     * @param array<int,array<string,mixed>> $orderTimeline
     * @param array<int,array<string,mixed>> $productionEntries
     * @param array<int,array<string,mixed>> $qcEntries
     * @param array<int,array<string,mixed>> $dispatchEntries
     * @return array<string,mixed>
     */
    private static function handoffSummary(int $productId, array $orderTimeline, array $productionEntries, array $qcEntries, array $dispatchEntries): array
    {
        if (!self::tableExists('handoff_tracking')) {
            return [
                'counts' => ['blocked' => 0, 'escalated' => 0, 'breach' => 0, 'warning' => 0, 'active' => 0],
                'items' => [],
            ];
        }

        $dailyOrderIds = array_values(array_filter(array_map(
            static fn(array $row): int => strtolower(trim((string)($row['source_type'] ?? 'daily_order'))) === 'daily_order'
                ? (int)($row['id'] ?? 0)
                : 0,
            $orderTimeline
        )));
        $productionEntryIds = array_values(array_filter(array_map(static fn(array $row): int => (int)($row['id'] ?? 0), $productionEntries)));
        $qcEntryIds = array_values(array_filter(array_map(static fn(array $row): int => (int)($row['id'] ?? 0), $qcEntries)));
        $dispatchEntryIds = array_values(array_filter(array_map(static fn(array $row): int => (int)($row['id'] ?? 0), $dispatchEntries)));

        $clauses = [];
        $params = [];
        self::appendEntityInClause($clauses, $params, 'daily_order', $dailyOrderIds);
        self::appendEntityInClause($clauses, $params, 'production_entry', $productionEntryIds);
        self::appendEntityInClause($clauses, $params, 'qc_entry', $qcEntryIds);
        self::appendEntityInClause($clauses, $params, 'dispatch_entry', $dispatchEntryIds);

        if (empty($clauses)) {
            return [
                'counts' => ['blocked' => 0, 'escalated' => 0, 'breach' => 0, 'warning' => 0, 'active' => 0],
                'items' => [],
            ];
        }

        $rows = DB::fetchAll(
            'SELECT entity_type, entity_id, stage, owner_role, owner_display, escalation_level, escalation_state, escalated_at, blocked_since, ready_since, entered_stage_at, updated_at, created_at
             FROM handoff_tracking
             WHERE released_since IS NULL AND (' . implode(' OR ', $clauses) . ')
             ORDER BY updated_at DESC, created_at DESC',
            $params
        );

        $counts = ['blocked' => 0, 'escalated' => 0, 'breach' => 0, 'warning' => 0, 'active' => count($rows)];
        $items = [];
        $now = date('Y-m-d H:i:s');
        foreach ($rows as $row) {
            $stage = (string)($row['stage'] ?? '');
            $ageHours = self::hoursDiff(self::stageSinceAt($row), $now);
            $slaLevel = self::evaluateSlaLevel($stage, $ageHours);
            $isBlocked = trim((string)($row['blocked_since'] ?? '')) !== '';
            $isEscalated = trim((string)($row['escalated_at'] ?? '')) !== '' || trim((string)($row['escalation_state'] ?? '')) !== '';

            if ($isBlocked) {
                $counts['blocked']++;
            }
            if ($isEscalated) {
                $counts['escalated']++;
            }
            if ($slaLevel === 'breach') {
                $counts['breach']++;
            } elseif ($slaLevel === 'warning') {
                $counts['warning']++;
            }

            $items[] = [
                'entity_type' => (string)($row['entity_type'] ?? ''),
                'entity_id' => (int)($row['entity_id'] ?? 0),
                'stage' => $stage,
                'stage_label' => (string)(self::SLA_HOURS[$stage]['label'] ?? $stage),
                'owner_role' => displayRole(self::fallbackOwner($stage, (string)($row['owner_role'] ?? ''))),
                'owner_display' => (string)($row['owner_display'] ?? ''),
                'age_hours' => $ageHours,
                'sla_level' => $slaLevel,
                'is_blocked' => $isBlocked,
                'is_escalated' => $isEscalated,
            ];
        }

        usort($items, static function (array $a, array $b): int {
            $rank = ['breach' => 0, 'warning' => 1, 'ok' => 2];
            $aRank = $rank[(string)($a['sla_level'] ?? 'ok')] ?? 2;
            $bRank = $rank[(string)($b['sla_level'] ?? 'ok')] ?? 2;
            if ($aRank !== $bRank) {
                return $aRank <=> $bRank;
            }
            if ((bool)($a['is_blocked'] ?? false) !== (bool)($b['is_blocked'] ?? false)) {
                return ((bool)($b['is_blocked'] ?? false) <=> (bool)($a['is_blocked'] ?? false));
            }
            return ((float)($b['age_hours'] ?? 0)) <=> ((float)($a['age_hours'] ?? 0));
        });

        return ['counts' => $counts, 'items' => array_slice($items, 0, 20)];
    }

    /**
     * @param array<string,mixed> $product
     * @param array<string,mixed> $demandSummary
     * @param array<string,mixed> $qcSummary
     * @param array<string,mixed> $dispatchSummary
     * @param array<string,mixed> $machineAssignment
     * @param array<string,mixed> $handoff
     * @return array<string,mixed>
     */
    private static function riskSummary(array $product, array $demandSummary, array $qcSummary, array $dispatchSummary, array $machineAssignment, array $handoff): array
    {
        $handoffCounts = (array)($handoff['counts'] ?? []);
        $reasons = [];

        if ((float)($demandSummary['shortage_qty'] ?? 0) > 0) {
            $reasons[] = 'Open orders are short by ' . number_format((float)$demandSummary['shortage_qty'], 2, '.', ',') . ' units.';
        }
        if ((int)($demandSummary['due_within_48h'] ?? 0) > 0 && (float)($demandSummary['shortage_qty'] ?? 0) > 0) {
            $reasons[] = (int)$demandSummary['due_within_48h'] . ' open order(s) are due within 48h under partial/low coverage.';
        }
        if ((float)($qcSummary['fail_qty'] ?? 0) > 0) {
            $reasons[] = 'QC failures total ' . number_format((float)$qcSummary['fail_qty'], 2, '.', ',') . ' units in recent entries.';
        }
        if ((int)($dispatchSummary['blocked_entries'] ?? 0) > 0) {
            $reasons[] = (int)$dispatchSummary['blocked_entries'] . ' dispatch row(s) are blocked or on hold.';
        }
        if ((int)($handoffCounts['blocked'] ?? 0) > 0 || (int)($handoffCounts['breach'] ?? 0) > 0) {
            $reasons[] = 'Active handoffs include ' . (int)($handoffCounts['blocked'] ?? 0) . ' blocked and ' . (int)($handoffCounts['breach'] ?? 0) . ' breached item(s).';
        }
        if (empty($machineAssignment['active_machine'])) {
            $reasons[] = 'No active machine is assigned for this part.';
        }
        if (empty($reasons)) {
            $reasons[] = 'Demand, execution, QC, and dispatch signals are currently within normal operating range.';
        }

        $state = 'healthy';
        $label = 'Healthy';
        if ((int)($handoffCounts['blocked'] ?? 0) > 0 || (int)($dispatchSummary['blocked_entries'] ?? 0) > 0) {
            $state = 'blocked';
            $label = 'Blocked';
        } elseif ((float)($demandSummary['shortage_qty'] ?? 0) > 0
            || (float)($qcSummary['fail_qty'] ?? 0) > 0
            || (int)($handoffCounts['escalated'] ?? 0) > 0
            || (int)($demandSummary['low_coverage_orders'] ?? 0) > 0) {
            $state = 'under_pressure';
            $label = 'Under Pressure';
        }

        return [
            'state' => $state,
            'label' => $label,
            'highlights' => array_slice($reasons, 0, 6),
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function stageSinceAt(array $row): string
    {
        $candidates = [
            trim((string)($row['blocked_since'] ?? '')),
            trim((string)($row['ready_since'] ?? '')),
            trim((string)($row['entered_stage_at'] ?? '')),
            trim((string)($row['updated_at'] ?? '')),
            trim((string)($row['created_at'] ?? '')),
        ];
        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }
        return date('Y-m-d H:i:s');
    }

    private static function evaluateSlaLevel(string $stage, float $ageHours): string
    {
        $policy = self::SLA_HOURS[$stage] ?? null;
        if ($policy === null) {
            return 'ok';
        }
        if ($ageHours >= (float)$policy['breach']) {
            return 'breach';
        }
        if ($ageHours >= (float)$policy['warn']) {
            return 'warning';
        }
        return 'ok';
    }

    private static function fallbackOwner(string $stage, string $ownerRole): string
    {
        $ownerRole = trim($ownerRole);
        if ($ownerRole !== '') {
            return $ownerRole;
        }
        if ($stage === 'production_to_qc') {
            return 'QC';
        }
        if ($stage === 'qc_to_dispatch' || $stage === 'dispatch_to_completion') {
            return 'Dispatch';
        }
        if ($stage === 'order_risk') {
            return 'Production';
        }
        return 'Unassigned / System issue';
    }

    private static function hoursDiff(string $fromTs, string $toTs): float
    {
        try {
            $from = new \DateTime($fromTs);
            $to = new \DateTime($toTs);
        } catch (\Throwable $e) {
            return 0.0;
        }

        $seconds = $to->getTimestamp() - $from->getTimestamp();
        if ($seconds <= 0) {
            return 0.0;
        }
        return round($seconds / 3600, 2);
    }

    private static function shiftDate(string $date, int $days): string
    {
        try {
            $dt = new \DateTime($date);
            $dt->modify(($days >= 0 ? '+' : '') . $days . ' day');
            return $dt->format('Y-m-d');
        } catch (\Throwable $e) {
            return date('Y-m-d');
        }
    }

    /**
     * @param array<int,string> $clauses
     * @param array<int,mixed> $params
     * @param array<int,int> $ids
     */
    private static function appendEntityInClause(array &$clauses, array &$params, string $entityType, array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $clauses[] = '(entity_type = ? AND entity_id IN (' . $placeholders . '))';
        $params[] = $entityType;
        foreach ($ids as $id) {
            $params[] = (int)$id;
        }
    }

    private static function tableExists(string $tableName): bool
    {
        $escaped = DB::conn()->real_escape_string($tableName);
        return DB::fetchOne("SHOW TABLES LIKE '{$escaped}'") !== null;
    }
}
