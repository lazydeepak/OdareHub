<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

use App\Core\DB;
use App\Core\EntityContext;

require_once APP_ROOT . '/apps/Manufacturing/modules/ProductionPlans/ProductionPlanService.php';

final class ProductionOperationService
{
    /**
     * Normalize an operator-supplied positive identifier without coercing
     * malformed input into another record ID.
     */
    public static function normalizeOperatorId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (!is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
            return null;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        return $normalized === false ? null : (int)$normalized;
    }

    /**
     * Normalize the existing DECIMAL(14,2) Production quantity contract.
     * Numeric scientific notation remains compatible with the prior float
     * boundary, while partial, non-finite, negative, and overflowing values
     * fail closed before route authorization or mutation.
     */
    public static function normalizeOperatorQuantity(mixed $value): ?float
    {
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        } elseif (!is_int($value) && !is_float($value)) {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }
        $quantity = (float)$value;
        $maximum = 999999999999.99;
        if (!is_finite($quantity) || $quantity < 0 || $quantity > $maximum) {
            return null;
        }

        $normalized = round($quantity, 2);
        return is_finite($normalized) && $normalized <= $maximum ? $normalized : null;
    }

    /**
     * Manufacturing-owned Production operator projection. Scope and module
     * authority are resolved before any Production query is issued.
     *
     * @return array<string,mixed>
     */
    public static function operatorFocus(string $date, ?array $user): array
    {
        $today = date('Y-m-d');
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : $today;
        $scope = self::operatorScope($user);
        $empty = [
            'authorized' => false,
            'can_mutate' => false,
            'summary' => ['low_coverage' => 0, 'shortage_parts' => 0, 'at_risk_today' => 0, 'overstock_parts' => 0],
            'coverage_rows' => [],
            'queue_rows' => [],
            'products' => [],
            'machines' => [],
        ];
        if ($scope === null || $scope['denied']) {
            return $empty;
        }

        try {
            [$productFilter, $productParams] = self::scopeFilter('d', 'product_id', $scope['part_ids']);
            [$planFilter, $planParams] = self::scopeFilter('pp', 'product_id', $scope['part_ids']);
            [$machineFilter, $machineParams] = self::scopeFilter('pp', 'machine_id', $scope['machine_ids']);

            $summaryRow = DB::fetchOne(
                "SELECT
                    SUM(CASE WHEN LOWER(COALESCE(coverage_status, '')) = 'low' THEN 1 ELSE 0 END) AS low_coverage,
                    SUM(CASE WHEN COALESCE(shortage_qty, 0) > 0 THEN 1 ELSE 0 END) AS shortage_parts,
                    SUM(CASE WHEN LOWER(COALESCE(coverage_status, '')) = 'low' OR COALESCE(shortage_qty, 0) > 0 THEN 1 ELSE 0 END) AS at_risk_today
                 FROM daily_orders d
                 WHERE d.order_date = ? {$productFilter}",
                array_merge([$today], $productParams)
            ) ?: [];

            $overstockRow = DB::fetchOne(
                "SELECT COUNT(*) AS c FROM (
                    SELECT d.product_id FROM daily_orders d
                    WHERE d.order_date = ? {$productFilter}
                    GROUP BY d.product_id
                    HAVING COALESCE(MAX(d.usable_stock_qty), 0) > COALESCE(SUM(d.qty), 0)
                 ) overstock_sub",
                array_merge([$today], $productParams)
            ) ?: [];

            $coverageRows = DB::fetchAll(
                "SELECT d.product_id AS part_id,
                        COALESCE(NULLIF(TRIM(p.parts_name), ''), CONCAT('Part #', d.product_id)) AS part_name,
                        COALESCE(NULLIF(TRIM(p.parts_number), ''), '-') AS part_number,
                        MIN(COALESCE(d.coverage_pct, 100)) AS coverage_pct,
                        SUM(COALESCE(d.shortage_qty, 0)) AS shortage_qty
                 FROM daily_orders d LEFT JOIN products p ON p.id = d.product_id
                 WHERE d.order_date = ? {$productFilter}
                   AND (LOWER(COALESCE(d.coverage_status, '')) = 'low' OR COALESCE(d.shortage_qty, 0) > 0)
                 GROUP BY d.product_id, part_name, part_number
                 ORDER BY shortage_qty DESC, coverage_pct ASC, part_name ASC LIMIT 8",
                array_merge([$today], $productParams)
            );

            $queueRows = DB::fetchAll(
                "SELECT pp.id, pp.plan_date, pp.planned_qty, pp.status,
                        COALESCE(pp.coverage_pct, 0) AS coverage_pct,
                        COALESCE(pp.shortage_qty, 0) AS shortage_qty,
                        COALESCE(NULLIF(TRIM(p.parts_name), ''), CONCAT('Part #', pp.product_id)) AS part_name,
                        COALESCE(NULLIF(TRIM(p.parts_number), ''), '-') AS part_number,
                        COALESCE(NULLIF(TRIM(m.machine_name), ''), COALESCE(NULLIF(TRIM(m.machine_no), ''), '-')) AS machine_label
                 FROM production_plans pp
                 LEFT JOIN products p ON p.id = pp.product_id
                 LEFT JOIN machines m ON m.id = pp.machine_id
                 WHERE pp.plan_date >= ? {$planFilter} {$machineFilter}
                 ORDER BY pp.plan_date ASC, pp.id DESC LIMIT 30",
                array_merge([$today], $planParams, $machineParams)
            );

            foreach ($queueRows as &$row) {
                $status = strtolower(trim((string)($row['status'] ?? '')));
                $row['can_start'] = $scope['can_mutate'] && $status === 'planned';
                $row['can_complete'] = $scope['can_mutate'] && $status === 'inprogress';
            }
            unset($row);

            [$productWhere, $productListParams] = self::scopeFilter('', 'id', $scope['part_ids']);
            [$machineWhere, $machineListParams] = self::scopeFilter('', 'id', $scope['machine_ids']);
            $products = DB::fetchAll(
                "SELECT id, parts_name, parts_number FROM products WHERE {$productWhere} ORDER BY parts_name ASC, id ASC LIMIT 200",
                $productListParams
            );
            $machines = DB::fetchAll(
                "SELECT id, machine_no, machine_name FROM machines WHERE is_active = 1 AND {$machineWhere} ORDER BY machine_no ASC, machine_name ASC, id ASC",
                $machineListParams
            );

            return [
                'authorized' => true,
                'can_mutate' => $scope['can_mutate'],
                'summary' => [
                    'low_coverage' => (int)($summaryRow['low_coverage'] ?? 0),
                    'shortage_parts' => (int)($summaryRow['shortage_parts'] ?? 0),
                    'at_risk_today' => (int)($summaryRow['at_risk_today'] ?? 0),
                    'overstock_parts' => (int)($overstockRow['c'] ?? 0),
                ],
                'coverage_rows' => $coverageRows,
                'queue_rows' => $queueRows,
                'products' => $products,
                'machines' => $machines,
            ];
        } catch (\Throwable) {
            return $empty;
        }
    }

    /** @return array{allowed:bool,reason:string} */
    public static function authorizeOperatorMutation(string $operation, int $recordId, string $newStatus, ?array $user): array
    {
        $scope = self::operatorScope($user);
        $target = self::canonicalOperatorStatus($newStatus);
        if ($scope === null || $scope['denied'] || !$scope['can_mutate']) {
            return ['allowed' => false, 'reason' => 'operator_production_unauthorized'];
        }
        if (strtolower(trim($operation)) !== 'production_status' || $recordId <= 0 || $target === null) {
            return ['allowed' => false, 'reason' => 'operator_production_invalid_transition'];
        }

        [$productSql, $productParams] = self::scopeFilter('pp', 'product_id', $scope['part_ids']);
        [$machineSql, $machineParams] = self::scopeFilter('pp', 'machine_id', $scope['machine_ids']);
        $row = DB::fetchOne(
            "SELECT pp.id, pp.status, pp.locked_at, pp.product_id, pp.machine_id
             FROM production_plans pp
             WHERE pp.id = ? {$productSql} {$machineSql} LIMIT 1",
            array_merge([$recordId], $productParams, $machineParams)
        );
        if (!is_array($row)) {
            return ['allowed' => false, 'reason' => 'operator_production_record_denied'];
        }
        if (trim((string)($row['locked_at'] ?? '')) !== '') {
            return ['allowed' => false, 'reason' => 'operator_production_record_locked'];
        }

        $current = strtolower(trim((string)($row['status'] ?? '')));
        $allowed = ($current === 'planned' && $target === 'InProgress')
            || ($current === 'inprogress' && $target === 'Completed');
        return ['allowed' => $allowed, 'reason' => $allowed ? '' : 'operator_production_transition_denied'];
    }

    public static function applyOperatorMutation(int $recordId, string $newStatus, ?array $user): bool
    {
        $decision = self::authorizeOperatorMutation('production_status', $recordId, $newStatus, $user);
        if (!$decision['allowed']) {
            throw new \RuntimeException((string)$decision['reason']);
        }
        return \Plugins\ProductionPlans\ProductionPlanService::quickStatusUpdate(
            $recordId,
            self::canonicalOperatorStatus($newStatus) ?? $newStatus,
            EntityContext::fromUser($user)
        );
    }

    /** @return array{allowed:bool,reason:string} */
    public static function authorizeOperatorCreate(int $machineId, int $productId, ?array $user): array
    {
        $scope = self::operatorScope($user);
        if ($scope === null || $scope['denied'] || !$scope['can_mutate']) {
            return ['allowed' => false, 'reason' => 'operator_production_unauthorized'];
        }
        if ($machineId <= 0 || $productId <= 0
            || ($scope['machine_ids'] !== [] && !in_array($machineId, $scope['machine_ids'], true))
            || ($scope['part_ids'] !== [] && !in_array($productId, $scope['part_ids'], true))) {
            return ['allowed' => false, 'reason' => 'operator_production_record_denied'];
        }
        $machine = DB::fetchOne('SELECT id FROM machines WHERE id = ? AND is_active = 1 LIMIT 1', [$machineId]);
        $product = DB::fetchOne('SELECT id FROM products WHERE id = ? AND is_active = 1 LIMIT 1', [$productId]);
        if (!is_array($machine) || !is_array($product)) {
            return ['allowed' => false, 'reason' => 'operator_production_record_missing'];
        }
        return ['allowed' => true, 'reason' => ''];
    }

    /** @param array<string,mixed> $data */
    public static function createOperatorPlan(array $data, ?array $user): int
    {
        $machineId = (int)($data['machine_id'] ?? 0);
        $productId = (int)($data['product_id'] ?? 0);
        $decision = self::authorizeOperatorCreate($machineId, $productId, $user);
        if (!$decision['allowed']) {
            throw new \RuntimeException((string)$decision['reason']);
        }
        return \Plugins\ProductionPlans\ProductionPlanService::create([
            'plan_date' => (string)($data['plan_date'] ?? date('Y-m-d')),
            'machine_id' => $machineId,
            'product_id' => $productId,
            'planned_qty' => (float)($data['planned_qty'] ?? 0),
            'sequence_no' => 1,
            'status' => 'Planned',
            'plan_type' => 'Manual',
            'coverage_pct' => 0,
            'shortage_qty' => 0,
            'auto_created' => 0,
            'notes' => trim((string)($data['notes'] ?? '')),
            'added_by' => trim((string)($data['added_by'] ?? 'operator')),
        ], EntityContext::fromUser($user));
    }

    /** @return array{machine_ids:array<int,int>,part_ids:array<int,int>,denied:bool,can_mutate:bool}|null */
    private static function operatorScope(?array $user): ?array
    {
        if (!is_array($user) || (int)($user['id'] ?? 0) <= 0
            || !function_exists('platform_user_context_contract')
            || !function_exists('platform_user_access_policy_contract')) {
            return null;
        }
        try {
            $context = platform_user_context_contract()->resolveUserContext($user);
            $apps = array_map(static fn($v): string => strtolower(trim((string)$v)), (array)($context['active_assigned_apps'] ?? $context['assigned_apps'] ?? []));
            $policy = platform_user_access_policy_contract();
            if (!in_array('manufacturing', $apps, true) || !$policy->isAppEnabled('manufacturing') || !$policy->canAccessModule($user, 'production')) {
                return null;
            }
            $rawScope = (array)($context['scope'] ?? []);
            foreach (['machine_ids', 'part_ids'] as $key) {
                if (!array_key_exists($key, $rawScope) || !is_array($rawScope[$key])) return null;
            }
            $denied = false;
            $out = [];
            foreach (['machine_ids', 'part_ids'] as $key) {
                $raw = $rawScope[$key];
                if (count($raw) === 1 && (($raw[0] ?? null) === -1 || (string)($raw[0] ?? '') === '-1')) {
                    $denied = true;
                    $out[$key] = [];
                    continue;
                }
                if (in_array(-1, $raw, true) || in_array('-1', array_map('strval', $raw), true)) return null;
                $values = [];
                foreach ($raw as $value) {
                    if (is_int($value) && $value > 0) $values[] = $value;
                    elseif (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value) === 1) {
                        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                        if ($parsed === false) return null;
                        $values[] = (int)$parsed;
                    } else return null;
                }
                $out[$key] = array_values(array_unique($values));
                sort($out[$key]);
            }
            return [
                'machine_ids' => $out['machine_ids'],
                'part_ids' => $out['part_ids'],
                'denied' => $denied,
                'can_mutate' => self::canMutateFromAssignmentContext($context),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private static function canMutateFromAssignmentContext(array $context): bool
    {
        $profiles = array_values(array_filter(array_map(static fn($v): string => strtolower(trim((string)$v)), (array)($context['access_profiles'] ?? []))));
        $roles = array_values(array_filter(array_map(static fn($v): string => strtolower(trim((string)$v)), (array)($context['app_roles'] ?? []))));
        $duties = $context['duty_codes'] ?? [];
        $duties = is_string($duties) ? (preg_split('/\s*,\s*/', strtolower(trim($duties))) ?: []) : array_map(static fn($v): string => strtolower(trim((string)$v)), (array)$duties);
        if (array_intersect(['readonly_observer', 'read_only', 'display', 'materials_readonly'], array_merge($profiles, $roles, $duties)) !== []) return false;
        foreach (['authority_role', 'account_class'] as $field) if (array_key_exists($field, $context) && !is_string($context[$field])) return false;
        if (in_array(strtolower(trim((string)($context['authority_role'] ?? ''))), ['tv_display'], true)) return false;
        if ($profiles === []) return false;
        return array_intersect(['production_operations', 'production_coordination', 'manufacturing_planning', 'manufacturing_admin'], $profiles) !== []
            || array_intersect(['production_leader'], $roles) !== [];
    }

    /** @return array{0:string,1:array<int,int>} */
    private static function scopeFilter(string $alias, string $column, array $ids): array
    {
        if ($ids === []) return ['1=1', []];
        $qualified = $alias !== '' ? $alias . '.' : '';
        return [$qualified . $column . ' IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids];
    }

    private static function canonicalOperatorStatus(string $status): ?string
    {
        return match (strtolower(trim($status))) {
            'inprogress' => 'InProgress',
            'completed' => 'Completed',
            default => null,
        };
    }

    /**
     * @return array<string,mixed>
     */
    public static function build(string $date, int $machineId = 0, ?array $user = null): array
    {
        $today = date('Y-m-d');
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : $today;
        $scope = self::scopeForUser($user);

        $rows = self::productionRows($date, $machineId, $scope['machine_ids'], $scope['part_ids']);
        $machines = self::machines($scope['machine_ids']);

        $demandMap = DemandExecutionService::productionDemandMapByDate($date);
        $stageMap = StageTransitionService::computeForDate($date);
        $materialPlanningMap = self::materialPlanningMap();
        $materialLinkMap = self::materialLinksByProduct(array_map(static fn (array $row): int => (int)($row['product_id'] ?? 0), $rows));

        $enriched = [];
        $machineLanes = [];
        $materialAlerts = [];
        $materialStatusParts = [];
        $releaseWatch = [];
        $summary = [
            'planned_rows' => 0,
            'target_qty' => 0.0,
            'good_qty' => 0.0,
            'remaining_qty' => 0.0,
            'planned_part_changes' => 0,
            'blocked_rows' => 0,
            'material_pressure_rows' => 0,
            'space_pressure_rows' => 0,
            'release_pressure_rows' => 0,
        ];

        $prevByMachine = [];
        foreach ($rows as $row) {
            $productId = (int)($row['product_id'] ?? 0);
            $machineKey = (int)($row['machine_id'] ?? 0);
            $plannedQty = (float)($row['planned_qty'] ?? 0);
            $goodQty = (float)($row['good_qty'] ?? 0);
            $remainingQty = max(0.0, round($plannedQty - $goodQty, 2));
            $demand = $demandMap[$productId] ?? [];
            $stage = $stageMap[$productId] ?? [];
            $linkedMaterials = $materialLinkMap[$productId] ?? [];
            $partChanged = isset($prevByMachine[$machineKey]) && (int)$prevByMachine[$machineKey] !== $productId;
            $prevByMachine[$machineKey] = $productId;

            $materialState = self::materialStateForProduct($linkedMaterials, $materialPlanningMap);
            $workflowState = self::workflowStateForRow($stage, $demand, $row, $remainingQty, $materialState);
            $sequenceAction = self::sequenceActionForRow($workflowState, $materialState, $row, $remainingQty, $partChanged);

            $row['remaining_qty'] = $remainingQty;
            $row['progress_pct'] = $plannedQty > 0 ? round(min(100, ($goodQty / $plannedQty) * 100), 1) : 0.0;
            $row['demand_execution'] = $demand;
            $row['stage_readiness'] = $stage;
            $row['part_changed'] = $partChanged;
            $row['material_state'] = $materialState;
            $row['workflow_state'] = $workflowState;
            $row['sequence_action'] = $sequenceAction;
            $row['linked_materials'] = $linkedMaterials;
            $row['material_ready'] = empty($materialState['is_pressure']);
            $enriched[] = $row;

            $summary['planned_rows']++;
            $summary['target_qty'] += $plannedQty;
            $summary['good_qty'] += $goodQty;
            $summary['remaining_qty'] += $remainingQty;
            if ($partChanged) {
                $summary['planned_part_changes']++;
            }
            if (!empty($workflowState['is_blocked'])) {
                $summary['blocked_rows']++;
            }
            if (!empty($materialState['is_pressure'])) {
                $summary['material_pressure_rows']++;
            }
            if (!empty($materialState['has_space_risk'])) {
                $summary['space_pressure_rows']++;
            }
            if (!empty($workflowState['needs_release_attention'])) {
                $summary['release_pressure_rows']++;
            }

            if (!isset($machineLanes[$machineKey])) {
                $machineLanes[$machineKey] = [
                    'machine_id' => $machineKey,
                    'machine_no' => (string)($row['machine_no'] ?? '-'),
                    'machine_name' => (string)($row['machine_name'] ?? '-'),
                    'rows' => 0,
                    'target_qty' => 0.0,
                    'good_qty' => 0.0,
                    'remaining_qty' => 0.0,
                    'changeovers' => 0,
                    'blocked_rows' => 0,
                    'material_pressure_rows' => 0,
                    'release_pressure_rows' => 0,
                    'parts' => [],
                ];
            }

            $machineLanes[$machineKey]['rows']++;
            $machineLanes[$machineKey]['target_qty'] += $plannedQty;
            $machineLanes[$machineKey]['good_qty'] += $goodQty;
            $machineLanes[$machineKey]['remaining_qty'] += $remainingQty;
            if ($partChanged) {
                $machineLanes[$machineKey]['changeovers']++;
            }
            if (!empty($workflowState['is_blocked'])) {
                $machineLanes[$machineKey]['blocked_rows']++;
            }
            if (!empty($materialState['is_pressure'])) {
                $machineLanes[$machineKey]['material_pressure_rows']++;
            }
            if (!empty($workflowState['needs_release_attention'])) {
                $machineLanes[$machineKey]['release_pressure_rows']++;
            }
            $machineLanes[$machineKey]['parts'][(string)($row['parts_number'] ?? '')] = (string)($row['parts_name'] ?? '-');
            $materialStatusParts[$productId] = [
                'product_id' => $productId,
                'part_name' => (string)($row['parts_name'] ?? '-'),
                'part_number' => (string)($row['parts_number'] ?? ''),
                'machine_id' => $machineKey,
                'machine_no' => (string)($row['machine_no'] ?? '-'),
                'material_ready' => empty($materialState['is_pressure']),
                'coverage_status' => (string)($materialState['top_status'] ?? 'Balanced'),
                'has_delayed_inbound' => !empty($materialState['has_delayed_inbound']) || !empty($materialState['late_incoming_risk']),
                'late_incoming_risk' => !empty($materialState['has_delayed_inbound']) || !empty($materialState['late_incoming_risk']),
            ];

            foreach ($linkedMaterials as $linkedMaterial) {
                $materialId = (int)($linkedMaterial['material_id'] ?? 0);
                $planning = $materialPlanningMap[$materialId] ?? null;
                if (!is_array($planning)) {
                    continue;
                }
                $coverageStatus = (string)($planning['coverage_status'] ?? 'Balanced');
                $lateIncoming = !empty($planning['has_delayed_inbound']) || !empty($planning['late_incoming_risk']);
                $spaceRisk = strtolower($coverageStatus) === 'overflow risk';
                $attention = in_array($coverageStatus, ['Critical', 'Low', 'Overflow Risk'], true) || $lateIncoming;
                if (!$attention) {
                    continue;
                }
                if (!isset($materialAlerts[$materialId])) {
                    $materialAlerts[$materialId] = [
                        'material_id' => $materialId,
                        'material_code' => (string)($planning['material_code'] ?? $linkedMaterial['material_code'] ?? ''),
                        'material_name' => (string)($planning['material_name'] ?? $linkedMaterial['material_name'] ?? ''),
                        'coverage_status' => $coverageStatus,
                        'has_delayed_inbound' => $lateIncoming,
                        'late_incoming_risk' => $lateIncoming,
                        'recommended_action' => (string)($planning['recommended_action'] ?? ''),
                        'net_need_qty' => (float)($planning['net_need_30_qty'] ?? 0),
                        'projected_available_qty' => (float)($planning['projected_available_30_qty'] ?? 0),
                        'max_storage_qty' => (float)($planning['effective_storage_capacity_qty'] ?? 0),
                        'affected_parts' => [],
                        'space_risk' => $spaceRisk,
                    ];
                }
                $materialAlerts[$materialId]['affected_parts'][$productId] = (string)($row['parts_name'] ?? '-') . ' (' . (string)($row['parts_number'] ?? '-') . ')';
            }

            if (!empty($workflowState['needs_release_attention']) || !empty($workflowState['is_blocked']) || !empty($materialState['is_pressure'])) {
                $releaseWatch[] = $row;
            }
        }

        $machineLanes = array_values(array_map(static function (array $lane): array {
            $lane['parts'] = array_values(array_map(
                static fn (string $number, string $name): string => trim($name . ' (' . $number . ')'),
                array_keys($lane['parts']),
                array_values($lane['parts'])
            ));
            $lane['progress_pct'] = $lane['target_qty'] > 0 ? round(min(100, ($lane['good_qty'] / $lane['target_qty']) * 100), 1) : 0.0;
            return $lane;
        }, $machineLanes));

        usort($machineLanes, static function (array $a, array $b): int {
            return strcmp((string)($a['machine_no'] ?? ''), (string)($b['machine_no'] ?? ''));
        });

        usort($releaseWatch, static function (array $a, array $b): int {
            return
                ((int)!empty($b['workflow_state']['is_blocked']) <=> (int)!empty($a['workflow_state']['is_blocked']))
                ?: ((int)!empty($b['material_state']['has_critical']) <=> (int)!empty($a['material_state']['has_critical']))
                ?: (((float)($b['remaining_qty'] ?? 0)) <=> ((float)($a['remaining_qty'] ?? 0)));
        });

        usort($materialAlerts, static function (array $a, array $b): int {
            $weight = static function (array $row): int {
                return match ((string)($row['coverage_status'] ?? 'Balanced')) {
                    'Critical' => 4,
                    'Overflow Risk' => 3,
                    'Low' => 2,
                    'High' => 1,
                    default => 0,
                };
            };
            return $weight($b) <=> $weight($a);
        });

        return [
            'today' => $today,
            'date' => $date,
            'machine_id' => $machineId,
            'machines' => $machines,
            'rows' => $enriched,
            'machine_lanes' => $machineLanes,
            'summary' => $summary,
            'material_status_parts' => array_values($materialStatusParts),
            'material_alerts' => array_values($materialAlerts),
            'storage_alerts' => array_values(array_filter($materialAlerts, static fn (array $row): bool => !empty($row['space_risk']))),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function productionRows(string $date, int $machineId, array $scopeMachineIds = [], array $scopePartIds = []): array
    {
        $cond = 'pp.plan_date = ?';
        $params = [$date];
        if ($machineId > 0) {
            $cond .= ' AND pp.machine_id = ?';
            $params[] = $machineId;
        }
        if ($scopeMachineIds !== []) {
            $ph = implode(',', array_fill(0, count($scopeMachineIds), '?'));
            $cond .= " AND pp.machine_id IN ({$ph})";
            foreach ($scopeMachineIds as $scopeMachineId) {
                $params[] = $scopeMachineId;
            }
        }
        if ($scopePartIds !== []) {
            $ph = implode(',', array_fill(0, count($scopePartIds), '?'));
            $cond .= " AND pp.product_id IN ({$ph})";
            foreach ($scopePartIds as $scopePartId) {
                $params[] = $scopePartId;
            }
        }

        return DB::fetchAll(
            "SELECT
                pp.id, pp.plan_date, pp.planned_qty, pp.sequence_no, pp.runtime, pp.status, pp.notes,
                m.id AS machine_id, m.machine_no, m.machine_name, m.section,
                p.id AS product_id, p.parts_name, p.parts_number, p.model, p.cycle_time,
                p.requires_ipm_qc, p.requires_assembly, p.dispatch_as_is, p.fulfillment_mode,
                COALESCE(pe_agg.produced_qty, 0) AS produced_qty,
                COALESCE(pe_agg.good_qty, 0) AS good_qty,
                COALESCE(pe_agg.rejected_qty, 0) AS rejected_qty
            FROM production_plans pp
            INNER JOIN machines m ON m.id = pp.machine_id
            INNER JOIN products p ON p.id = pp.product_id
            LEFT JOIN (
                SELECT machine_id, product_id, production_date,
                       SUM(produced_qty) AS produced_qty,
                       SUM(good_qty) AS good_qty,
                       SUM(rejected_qty) AS rejected_qty
                FROM production_entries
                WHERE production_date = ?
                GROUP BY machine_id, product_id, production_date
            ) pe_agg ON pe_agg.machine_id = pp.machine_id
                     AND pe_agg.product_id = pp.product_id
                     AND pe_agg.production_date = pp.plan_date
            WHERE {$cond}
            ORDER BY m.machine_no ASC, pp.sequence_no ASC, pp.id ASC",
            array_merge([$date], $params)
        );
    }

    /**
     * @param array<int,int> $scopeMachineIds
     * @return array<int,array<string,mixed>>
     */
    private static function machines(array $scopeMachineIds = []): array
    {
        $sql = 'SELECT id, machine_no, machine_name FROM machines WHERE is_active=1';
        $params = [];
        if ($scopeMachineIds !== []) {
            $ph = implode(',', array_fill(0, count($scopeMachineIds), '?'));
            $sql .= " AND id IN ({$ph})";
            $params = $scopeMachineIds;
        }
        $sql .= ' ORDER BY machine_no ASC';

        return DB::fetchAll($sql, $params);
    }

    /**
     * @return array{machine_ids:array<int,int>,part_ids:array<int,int>}
     */
    private static function scopeForUser(?array $user): array
    {
        if (!$user) {
            return ['machine_ids' => [], 'part_ids' => []];
        }

        $context = platform_user_context_contract()->resolveUserContext($user);
        return [
            'machine_ids' => array_values(array_filter(array_map('intval', (array)($context['scope']['machine_ids'] ?? [])), static fn (int $id): bool => $id > 0)),
            'part_ids' => array_values(array_filter(array_map('intval', (array)($context['scope']['part_ids'] ?? [])), static fn (int $id): bool => $id > 0)),
        ];
    }

    /**
     * @param array<int,int> $productIds
     * @return array<int,array<int,array<string,mixed>>>
     */
    private static function materialLinksByProduct(array $productIds): array
    {
        $productIds = array_values(array_filter(array_unique(array_map('intval', $productIds)), static fn (int $id): bool => $id > 0));
        if ($productIds === []) {
            return [];
        }

        $ph = implode(',', array_fill(0, count($productIds), '?'));
        try {
            $rows = DB::fetchAll(
                "SELECT pm.product_id, pm.material_id, pm.usage_qty, pm.scrap_pct, m.material_code, m.material_name, m.storage_location
                 FROM part_material_map pm
                 INNER JOIN materials m ON m.id = pm.material_id
                 WHERE pm.is_active = 1 AND m.is_active = 1 AND pm.product_id IN ({$ph})
                 ORDER BY pm.product_id ASC, pm.sequence_no ASC, m.material_name ASC",
                $productIds
            );
        } catch (\Throwable $e) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['product_id']][] = $row;
        }
        return $map;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function materialPlanningMap(): array
    {
        $serviceClass = '\\Plugins\\MaterialManagement\\Services\\MaterialManagementService';
        $schemaPath = APP_ROOT . '/apps/Manufacturing/modules/MaterialManagement/Services/MaterialSchemaService.php';
        $servicePath = APP_ROOT . '/apps/Manufacturing/modules/MaterialManagement/Services/MaterialManagementService.php';

        if (!class_exists($serviceClass)) {
            if (is_file($schemaPath)) {
                require_once $schemaPath;
            }
            if (is_file($servicePath)) {
                require_once $servicePath;
            }
        }

        if (!class_exists($serviceClass) || !method_exists($serviceClass, 'planningRows')) {
            return [];
        }

        $rows = $serviceClass::planningRows();
        $map = [];
        foreach ((array)$rows as $row) {
            $map[(int)($row['material_id'] ?? 0)] = (array)$row;
        }
        return $map;
    }

    /**
     * @param array<int,array<string,mixed>> $linkedMaterials
     * @param array<int,array<string,mixed>> $planningMap
     * @return array<string,mixed>
     */
    private static function materialStateForProduct(array $linkedMaterials, array $planningMap): array
    {
        $statusWeight = static function (string $status): int {
            return match ($status) {
                'Critical' => 4,
                'Overflow Risk' => 3,
                'Low' => 2,
                'High' => 1,
                default => 0,
            };
        };

        $topStatus = 'Balanced';
        $topAction = 'Stable';
        $topMaterial = '-';
        $hasCritical = false;
        $hasPressure = false;
        $hasSpaceRisk = false;
        $hasLateIncoming = false;

        foreach ($linkedMaterials as $material) {
            $planning = $planningMap[(int)($material['material_id'] ?? 0)] ?? null;
            if (!is_array($planning)) {
                continue;
            }
            $status = (string)($planning['coverage_status'] ?? 'Balanced');
            if ($statusWeight($status) > $statusWeight($topStatus)) {
                $topStatus = $status;
                $topAction = (string)($planning['recommended_action'] ?? 'Review');
                $topMaterial = (string)($planning['material_name'] ?? $material['material_name'] ?? '-');
            }
            if (in_array($status, ['Critical', 'Low', 'Overflow Risk'], true)) {
                $hasPressure = true;
            }
            if ($status === 'Critical') {
                $hasCritical = true;
            }
            if ($status === 'Overflow Risk') {
                $hasSpaceRisk = true;
            }
            if (!empty($planning['has_delayed_inbound']) || !empty($planning['late_incoming_risk'])) {
                $hasLateIncoming = true;
                $hasPressure = true;
            }
        }

        return [
            'top_status' => $topStatus,
            'top_action' => $topAction,
            'top_material' => $topMaterial,
            'has_critical' => $hasCritical,
            'is_pressure' => $hasPressure,
            'has_space_risk' => $hasSpaceRisk,
            'has_delayed_inbound' => $hasLateIncoming,
            'late_incoming_risk' => $hasLateIncoming,
        ];
    }

    /**
     * @param array<string,mixed> $stage
     * @param array<string,mixed> $demand
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function workflowStateForRow(array $stage, array $demand, array $row, float $remainingQty, array $materialState): array
    {
        $nextStage = (string)($stage['next_stage'] ?? '');
        $blockReasons = (array)($stage['block_reasons'] ?? $demand['blocked_reasons'] ?? []);
        $canReleaseTo = array_map('strval', (array)($stage['can_release_to'] ?? []));
        $blocked = !empty($blockReasons) || !empty($demand['blocked']);

        $note = 'Keep line running to current plan.';
        if ($blocked) {
            $note = (string)($blockReasons[0] ?? 'Resolve upstream block before pushing output.');
        } elseif ($materialState['has_space_risk'] ?? false) {
            $note = 'Protect floor space before adding more output.';
        } elseif ($materialState['has_critical'] ?? false) {
            $note = 'Protect scarce material and avoid wasteful changeover.';
        } elseif ($nextStage === 'assembly') {
            $note = 'Build to what assembly can absorb cleanly.';
        } elseif ($nextStage === 'qc') {
            $note = 'Sequence output to match QC pull capacity.';
        } elseif ($nextStage === 'dispatch') {
            $note = 'Push only what can move to dispatch without floor pileup.';
        }

        return [
            'next_stage' => $nextStage !== '' ? ucfirst($nextStage) : 'Production',
            'can_release' => $canReleaseTo,
            'is_blocked' => $blocked,
            'needs_release_attention' => !$blocked && $remainingQty > 0 && in_array($nextStage, ['assembly', 'qc', 'dispatch', 'packaging'], true),
            'note' => $note,
        ];
    }

    /**
     * @param array<string,mixed> $workflowState
     * @param array<string,mixed> $materialState
     * @param array<string,mixed> $row
     */
    private static function sequenceActionForRow(array $workflowState, array $materialState, array $row, float $remainingQty, bool $partChanged): string
    {
        if (!empty($workflowState['is_blocked'])) {
            return 'Escalate before run';
        }
        if (!empty($materialState['has_space_risk'])) {
            return 'Slow release to avoid pileup';
        }
        if (!empty($materialState['has_critical'])) {
            return 'Protect scarce material';
        }
        if ($partChanged) {
            return 'Change only if target justifies it';
        }
        if ($remainingQty <= 0) {
            return 'Close row and free machine';
        }
        if ((int)($row['requires_assembly'] ?? 0) === 1) {
            return 'Run with assembly pull in mind';
        }
        if ((int)($row['requires_ipm_qc'] ?? 0) === 1) {
            return 'Keep QC fed, not flooded';
        }
        return 'Keep running and protect sequence';
    }

}
