<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

use App\Core\DB;

final class ProductionOperationService
{
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
