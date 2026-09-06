<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

use App\Core\Auth;
use App\Core\DB;

final class DemandWorkExecutionGeneratorService
{
    /**
     * @param array<int,int> $productIds
     * @return array<string,mixed>
     */
    public static function generate(?string $targetDate = null, int $limit = 100, array $productIds = [], bool $dryRun = true): array
    {
        self::ensureSchema();

        $productIds = array_values(array_filter(array_map(static fn($v): int => (int)$v, $productIds), static fn(int $v): bool => $v > 0));
        $decisionRows = DemandEngineService::previewRouteDecisions($targetDate, $limit, $productIds);
        $bucketed = DemandWorkBucketService::bucketRows($decisionRows);
        $rows = (array)($bucketed['rows'] ?? []);

        $summary = [
            'rows_seen' => count($rows),
            'assembly_created' => 0,
            'assembly_updated' => 0,
            'qc_created' => 0,
            'qc_updated' => 0,
            'processing_created' => 0,
            'processing_updated' => 0,
            'dispatch_created' => 0,
            'dispatch_updated' => 0,
            'skipped' => 0,
        ];

        $results = [];
        foreach ($rows as $row) {
            $productId = (int)($row['product_id'] ?? 0);
            $demandDate = (string)($row['demand_date'] ?? '');
            if ($productId <= 0 || $demandDate === '') {
                $summary['skipped']++;
                continue;
            }

            $source = (array)($row['source'] ?? []);
            $sourceKey = self::sourceKey($row);
            $dependencies = self::dependencyStates($row);

            $assembly = self::syncAssembly($row, $source, $sourceKey, $dependencies, $dryRun);
            $summary[$assembly['counter_key']] += (int)$assembly['count'];

            $qc = self::syncQc($row, $source, $sourceKey, $dependencies, $dryRun);
            $processing = self::syncProcessing($row, $source, $sourceKey, $dependencies, $dryRun);
            $dispatch = self::syncDispatch($row, $source, $sourceKey, $dependencies, $dryRun);

            $summary[$qc['counter_key']] += (int)$qc['count'];
            $summary[$processing['counter_key']] += (int)$processing['count'];
            $summary[$dispatch['counter_key']] += (int)$dispatch['count'];

            if ((string)$assembly['action'] === 'skipped' && (string)$qc['action'] === 'skipped' && (string)$processing['action'] === 'skipped' && (string)$dispatch['action'] === 'skipped') {
                $summary['skipped']++;
            }

            $results[] = [
                'product_id' => $productId,
                'demand_date' => $demandDate,
                'execution_path_label' => (string)($row['execution_path_label'] ?? ''),
                'route_family' => (string)($row['route_family'] ?? ''),
                'dependency_states' => $dependencies,
                'assembly' => $assembly,
                'qc' => $qc,
                'processing' => $processing,
                'dispatch' => $dispatch,
            ];
        }

        return [
            'dry_run' => $dryRun,
            'target_date' => $targetDate,
            'product_ids' => $productIds,
            'bucket_summary' => (array)($bucketed['summary'] ?? []),
            'generation_summary' => $summary,
            'rows' => $results,
        ];
    }

    public static function ensureSchema(): void
    {
        DemandEngineService::ensureSchema();

        AssemblyPlanService::ensureSchema();

        DB::query("CREATE TABLE IF NOT EXISTS mfg_order_processing_entries (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            demand_date DATE NOT NULL,
            required_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
            prepared_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
            case_count INT NOT NULL DEFAULT 0,
            pallet_count INT NOT NULL DEFAULT 0,
            labels_ready TINYINT(1) NOT NULL DEFAULT 0,
            preparation_status ENUM('draft','in_progress','prepared','completed','blocked') NOT NULL DEFAULT 'draft',
            packaging_status VARCHAR(40) NULL,
            dependency_state VARCHAR(60) NULL,
            source_route_family VARCHAR(80) NULL,
            execution_path_label VARCHAR(190) NULL,
            source_key VARCHAR(120) NOT NULL,
            source_summary_json JSON NULL,
            notes TEXT NULL,
            created_by VARCHAR(190) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_processing_entry (product_id, demand_date, source_key),
            KEY idx_processing_date (demand_date),
            KEY idx_processing_status (preparation_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DB::query("CREATE TABLE IF NOT EXISTS mfg_work_execution_links (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            work_type ENUM('assembly','qc','processing','dispatch') NOT NULL,
            product_id INT NOT NULL,
            demand_date DATE NOT NULL,
            source_key VARCHAR(120) NOT NULL,
            route_family VARCHAR(80) NULL,
            execution_path_label VARCHAR(190) NULL,
            target_table VARCHAR(80) NOT NULL,
            target_id BIGINT NOT NULL,
            demanded_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
            source_summary_json JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_work_link (work_type, product_id, demand_date, source_key),
            KEY idx_work_link_target (target_table, target_id),
            KEY idx_work_link_date (demand_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        self::addColumnIfMissing('mfg_order_processing_entries', 'dependency_state', 'ALTER TABLE mfg_order_processing_entries ADD COLUMN dependency_state VARCHAR(60) NULL AFTER packaging_status');
        DB::query("ALTER TABLE mfg_work_execution_links MODIFY COLUMN work_type ENUM('assembly','qc','processing','dispatch') NOT NULL");
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $source
     * @param array<string,string> $dependencies
     * @return array<string,mixed>
     */
    private static function syncAssembly(array $row, array $source, string $sourceKey, array $dependencies, bool $dryRun): array
    {
        $qty = round(max(0.0, (float)($row['assembly_demand_qty'] ?? 0.0)), 2);
        if ($qty <= 0.0) {
            return ['action' => 'skipped', 'reason' => 'no_assembly_demand', 'count' => 0, 'counter_key' => 'assembly_updated'];
        }

        $productId = (int)$row['product_id'];
        $demandDate = (string)$row['demand_date'];
        $marker = self::marker('assembly', $productId, $demandDate, $sourceKey);

        $link = self::findLink('assembly', $productId, $demandDate, $sourceKey);
        $targetId = $link ? (int)$link['target_id'] : 0;

        if ($targetId > 0 && self::targetExists('mfg_assembly_entries', $targetId)) {
            if (!$dryRun) {
                DB::query(
                    'UPDATE mfg_assembly_entries SET assembly_date=?, planned_qty=?, source_type=?, note=?, updated_at=NOW() WHERE id=?',
                    [$demandDate, $qty, 'work_demand', $marker . ' dep=' . ($dependencies['assembly'] ?? 'ready_for_assembly'), $targetId]
                );
                self::upsertLink('assembly', $productId, $demandDate, $sourceKey, (string)($row['route_family'] ?? ''), (string)($row['execution_path_label'] ?? ''), 'mfg_assembly_entries', $targetId, $qty, $row);
            }
            return ['action' => 'updated', 'target_id' => $targetId, 'qty' => $qty, 'count' => 1, 'counter_key' => 'assembly_updated'];
        }

        if ($dryRun) {
            return ['action' => 'create', 'target_id' => null, 'qty' => $qty, 'count' => 1, 'counter_key' => 'assembly_created'];
        }

        DB::query(
            'INSERT INTO mfg_assembly_entries (assembly_plan_id, product_id, source_type, source_id, assembly_date, planned_qty, completed_qty, rejected_qty, status, note, completed_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [
                null,
                $productId,
                'work_demand',
                null,
                $demandDate,
                $qty,
                0.00,
                0.00,
                'draft',
                $marker . ' dep=' . ($dependencies['assembly'] ?? 'ready_for_assembly'),
                self::currentUserLabel(),
            ]
        );
        $targetId = (int)(DB::conn()->insert_id ?: 0);
        if ($targetId <= 0) {
            throw new \RuntimeException('Failed to create assembly execution entry.');
        }

        self::upsertLink('assembly', $productId, $demandDate, $sourceKey, (string)($row['route_family'] ?? ''), (string)($row['execution_path_label'] ?? ''), 'mfg_assembly_entries', $targetId, $qty, $row);
        return ['action' => 'created', 'target_id' => $targetId, 'qty' => $qty, 'count' => 1, 'counter_key' => 'assembly_created'];
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $source
     * @return array<string,mixed>
     */
    private static function syncQc(array $row, array $source, string $sourceKey, array $dependencies, bool $dryRun): array
    {
        $qty = round(max(0.0, (float)($row['qc_demand_qty'] ?? 0.0)), 2);
        $isDirect = (bool)($source['direct_supplier_delivery'] ?? false);
        if ($qty <= 0.0 || $isDirect) {
            return ['action' => 'skipped', 'reason' => 'no_qc_demand', 'count' => 0, 'counter_key' => 'qc_updated'];
        }

        $productId = (int)$row['product_id'];
        $demandDate = (string)$row['demand_date'];
        $marker = self::marker('qc', $productId, $demandDate, $sourceKey);

        $link = self::findLink('qc', $productId, $demandDate, $sourceKey);
        $targetId = $link ? (int)$link['target_id'] : 0;

        if ($targetId > 0 && self::targetExists('qc_entries', $targetId)) {
            if (!$dryRun) {
                DB::query(
                    'UPDATE qc_entries SET checked_qty=?, workflow_state=?, remarks=?, updated_at=NOW() WHERE id=?',
                    [$qty, (string)($dependencies['qc'] ?? 'ready_for_qc'), $marker, $targetId]
                );
                self::upsertLink('qc', $productId, $demandDate, $sourceKey, (string)($row['route_family'] ?? ''), (string)($row['execution_path_label'] ?? ''), 'qc_entries', $targetId, $qty, $row);
            }
            return ['action' => 'updated', 'target_id' => $targetId, 'qty' => $qty, 'count' => 1, 'counter_key' => 'qc_updated'];
        }

        if ($dryRun) {
            return ['action' => 'create', 'target_id' => null, 'qty' => $qty, 'count' => 1, 'counter_key' => 'qc_created'];
        }

        DB::query(
            'INSERT INTO qc_entries (qc_plan_id, daily_order_id, production_plan_id, product_id, qc_type, checked_qty, pass_qty, fail_qty, status, remarks, workflow_state) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [null, null, null, $productId, 'WorkDemand', $qty, 0.00, 0.00, 'Open', $marker, (string)($dependencies['qc'] ?? 'ready_for_qc')]
        );
        $targetId = (int)(DB::conn()->insert_id ?: 0);
        if ($targetId <= 0) {
            throw new \RuntimeException('Failed to create QC execution entry.');
        }

        self::upsertLink('qc', $productId, $demandDate, $sourceKey, (string)($row['route_family'] ?? ''), (string)($row['execution_path_label'] ?? ''), 'qc_entries', $targetId, $qty, $row);
        return ['action' => 'created', 'target_id' => $targetId, 'qty' => $qty, 'count' => 1, 'counter_key' => 'qc_created'];
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $source
     * @return array<string,mixed>
     */
    private static function syncProcessing(array $row, array $source, string $sourceKey, array $dependencies, bool $dryRun): array
    {
        $qty = round(max(0.0, (float)($row['processing_demand_qty'] ?? 0.0)), 2);
        if ($qty <= 0.0) {
            return ['action' => 'skipped', 'reason' => 'no_processing_demand', 'count' => 0, 'counter_key' => 'processing_updated'];
        }

        $productId = (int)$row['product_id'];
        $demandDate = (string)$row['demand_date'];

        $link = self::findLink('processing', $productId, $demandDate, $sourceKey);
        $targetId = $link ? (int)$link['target_id'] : 0;

        if ($targetId > 0 && self::targetExists('mfg_order_processing_entries', $targetId)) {
            if (!$dryRun) {
                DB::query(
                    'UPDATE mfg_order_processing_entries SET required_qty=?, dependency_state=?, source_route_family=?, execution_path_label=?, source_summary_json=?, updated_at=NOW() WHERE id=?',
                    [
                        $qty,
                        (string)($dependencies['processing'] ?? 'ready_for_processing'),
                        (string)($row['route_family'] ?? ''),
                        (string)($row['execution_path_label'] ?? ''),
                        self::safeJson($row),
                        $targetId,
                    ]
                );
                self::upsertLink('processing', $productId, $demandDate, $sourceKey, (string)($row['route_family'] ?? ''), (string)($row['execution_path_label'] ?? ''), 'mfg_order_processing_entries', $targetId, $qty, $row);
            }
            return ['action' => 'updated', 'target_id' => $targetId, 'qty' => $qty, 'count' => 1, 'counter_key' => 'processing_updated'];
        }

        if ($dryRun) {
            return ['action' => 'create', 'target_id' => null, 'qty' => $qty, 'count' => 1, 'counter_key' => 'processing_created'];
        }

        DB::query(
            'INSERT INTO mfg_order_processing_entries (product_id, demand_date, required_qty, prepared_qty, case_count, pallet_count, labels_ready, preparation_status, packaging_status, dependency_state, source_route_family, execution_path_label, source_key, source_summary_json, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $productId,
                $demandDate,
                $qty,
                0.00,
                0,
                0,
                0,
                'draft',
                'pending',
                (string)($dependencies['processing'] ?? 'ready_for_processing'),
                (string)($row['route_family'] ?? ''),
                (string)($row['execution_path_label'] ?? ''),
                $sourceKey,
                self::safeJson($row),
                'Auto-generated from work demand buckets.',
                self::currentUserLabel(),
            ]
        );
        $targetId = (int)(DB::conn()->insert_id ?: 0);
        if ($targetId <= 0) {
            throw new \RuntimeException('Failed to create processing execution entry.');
        }

        self::upsertLink('processing', $productId, $demandDate, $sourceKey, (string)($row['route_family'] ?? ''), (string)($row['execution_path_label'] ?? ''), 'mfg_order_processing_entries', $targetId, $qty, $row);
        return ['action' => 'created', 'target_id' => $targetId, 'qty' => $qty, 'count' => 1, 'counter_key' => 'processing_created'];
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $source
     * @return array<string,mixed>
     */
    private static function syncDispatch(array $row, array $source, string $sourceKey, array $dependencies, bool $dryRun): array
    {
        $qty = round(max(0.0, (float)($row['dispatch_demand_qty'] ?? 0.0)), 2);
        $isDirect = (bool)($source['direct_supplier_delivery'] ?? false);
        if ($qty <= 0.0 || $isDirect) {
            return ['action' => 'skipped', 'reason' => 'no_internal_dispatch_demand', 'count' => 0, 'counter_key' => 'dispatch_updated'];
        }

        $productId = (int)$row['product_id'];
        $demandDate = (string)$row['demand_date'];
        $dispatchMode = (string)($source['dispatch_mode'] ?? 'company_origin_dispatch');
        $sourceType = str_starts_with($dispatchMode, 'third_party') ? 'third_party' : 'in_house';
        $deliveryFlow = self::deliveryFlowFromDispatchMode($dispatchMode);
        $marker = self::marker('dispatch', $productId, $demandDate, $sourceKey);

        $link = self::findLink('dispatch', $productId, $demandDate, $sourceKey);
        $targetId = $link ? (int)$link['target_id'] : 0;

        if ($targetId > 0 && self::targetExists('dispatch_entries', $targetId)) {
            if (!$dryRun) {
                DB::query(
                    'UPDATE dispatch_entries SET dispatch_date=?, product_id=?, dispatchable_qty=?, dispatch_type=?, workflow_state=?, status_reason=?, remarks=?, source_type=?, delivery_flow=?, dispatch_mode=?, updated_at=NOW() WHERE id=?',
                    [
                        $demandDate,
                        $productId,
                        $qty,
                        'Work Demand',
                        (string)($dependencies['dispatch'] ?? 'ready_for_dispatch'),
                        (string)($dependencies['dispatch'] ?? 'ready_for_dispatch'),
                        $marker,
                        $sourceType,
                        $deliveryFlow,
                        $dispatchMode,
                        $targetId,
                    ]
                );
                self::upsertLink('dispatch', $productId, $demandDate, $sourceKey, (string)($row['route_family'] ?? ''), (string)($row['execution_path_label'] ?? ''), 'dispatch_entries', $targetId, $qty, $row);
            }
            return ['action' => 'updated', 'target_id' => $targetId, 'qty' => $qty, 'count' => 1, 'counter_key' => 'dispatch_updated'];
        }

        if ($dryRun) {
            return ['action' => 'create', 'target_id' => null, 'qty' => $qty, 'count' => 1, 'counter_key' => 'dispatch_created'];
        }

        DB::query(
            'INSERT INTO dispatch_entries (dispatch_date, daily_order_id, production_plan_id, production_entry_id, qc_entry_id, product_id, dispatchable_qty, destination, dispatch_type, dispatch_status, workflow_state, status_reason, remarks, cases_count, pallets_count, source_type, delivery_flow, dispatch_mode, completion_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $demandDate,
                null,
                null,
                null,
                null,
                $productId,
                $qty,
                null,
                'Work Demand',
                'Ready',
                (string)($dependencies['dispatch'] ?? 'ready_for_dispatch'),
                (string)($dependencies['dispatch'] ?? 'ready_for_dispatch'),
                $marker,
                0,
                0,
                $sourceType,
                $deliveryFlow,
                $dispatchMode,
                'draft',
            ]
        );
        $targetId = (int)(DB::conn()->insert_id ?: 0);
        if ($targetId <= 0) {
            throw new \RuntimeException('Failed to create dispatch execution entry.');
        }

        self::upsertLink('dispatch', $productId, $demandDate, $sourceKey, (string)($row['route_family'] ?? ''), (string)($row['execution_path_label'] ?? ''), 'dispatch_entries', $targetId, $qty, $row);
        return ['action' => 'created', 'target_id' => $targetId, 'qty' => $qty, 'count' => 1, 'counter_key' => 'dispatch_created'];
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function sourceKey(array $row): string
    {
        $routeFamily = trim((string)($row['route_family'] ?? ''));
        $source = (array)($row['source'] ?? []);
        $dispatchMode = trim((string)($source['dispatch_mode'] ?? ''));
        return substr(($routeFamily !== '' ? $routeFamily : 'unknown') . '|' . ($dispatchMode !== '' ? $dispatchMode : 'unknown'), 0, 120);
    }

    private static function marker(string $workType, int $productId, string $date, string $sourceKey): string
    {
        return '[AUTO-WORK-DEMAND][' . $workType . '][' . $date . '][p:' . $productId . '][k:' . $sourceKey . ']';
    }

    private static function deliveryFlowFromDispatchMode(string $dispatchMode): string
    {
        return match ($dispatchMode) {
            'third_party_direct_dispatch' => 'third_party_to_destination',
            'third_party_to_company_then_destination' => 'third_party_to_company_to_destination',
            default => 'company_to_destination',
        };
    }

    private static function targetExists(string $table, int $id): bool
    {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?: '';
        if ($safeTable === '' || $id <= 0) {
            return false;
        }
        $row = DB::fetchOne('SELECT id FROM ' . $safeTable . ' WHERE id=? LIMIT 1', [$id]);
        return $row !== null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function findLink(string $workType, int $productId, string $demandDate, string $sourceKey): ?array
    {
        $row = DB::fetchOne(
            'SELECT * FROM mfg_work_execution_links WHERE work_type=? AND product_id=? AND demand_date=? AND source_key=? LIMIT 1',
            [$workType, $productId, $demandDate, $sourceKey]
        );
        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string,mixed> $sourceRow
     */
    private static function upsertLink(
        string $workType,
        int $productId,
        string $demandDate,
        string $sourceKey,
        string $routeFamily,
        string $executionPathLabel,
        string $targetTable,
        int $targetId,
        float $qty,
        array $sourceRow
    ): void {
        $existing = self::findLink($workType, $productId, $demandDate, $sourceKey);
        $payload = self::safeJson($sourceRow);

        if ($existing) {
            DB::query(
                'UPDATE mfg_work_execution_links SET route_family=?, execution_path_label=?, target_table=?, target_id=?, demanded_qty=?, source_summary_json=?, updated_at=NOW() WHERE id=?',
                [$routeFamily, $executionPathLabel, $targetTable, $targetId, $qty, $payload, (int)$existing['id']]
            );
            return;
        }

        DB::query(
            'INSERT INTO mfg_work_execution_links (work_type, product_id, demand_date, source_key, route_family, execution_path_label, target_table, target_id, demanded_qty, source_summary_json) VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$workType, $productId, $demandDate, $sourceKey, $routeFamily, $executionPathLabel, $targetTable, $targetId, $qty, $payload]
        );
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function safeJson(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        return $json === false ? '{}' : $json;
    }

    private static function currentUserLabel(): string
    {
        $u = Auth::user();
        $email = trim((string)($u['email'] ?? ''));
        if ($email !== '') {
            return $email;
        }
        return 'system';
    }

    private static function addColumnIfMissing(string $table, string $column, string $alterSql): void
    {
        $db = DB::conn();
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?: '';
        if ($safeTable === '') {
            return;
        }
        $safeColumn = $db->real_escape_string($column);
        $exists = DB::fetchOne("SHOW COLUMNS FROM {$safeTable} LIKE '{$safeColumn}'");
        if ($exists) {
            return;
        }
        DB::query($alterSql);
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,string>
     */
    private static function dependencyStates(array $row): array
    {
        $assemblyQty = round(max(0.0, (float)($row['assembly_demand_qty'] ?? 0.0)), 2);
        $qcQty = round(max(0.0, (float)($row['qc_demand_qty'] ?? 0.0)), 2);
        $processingQty = round(max(0.0, (float)($row['processing_demand_qty'] ?? 0.0)), 2);
        $dispatchQty = round(max(0.0, (float)($row['dispatch_demand_qty'] ?? 0.0)), 2);

        $needsAssembly = $assemblyQty > 0.0;
        $needsQc = $qcQty > 0.0;
        $needsProcessing = $processingQty > 0.0;
        $needsDispatch = $dispatchQty > 0.0;

        $assemblyState = $needsAssembly ? 'ready_for_assembly' : 'not_required';

        $qcState = 'not_required';
        if ($needsQc) {
            $qcState = $needsAssembly ? 'awaiting_assembly' : 'ready_for_qc';
        }

        $processingState = 'not_required';
        if ($needsProcessing) {
            if ($needsQc) {
                $processingState = 'awaiting_qc';
            } elseif ($needsAssembly) {
                $processingState = 'awaiting_assembly';
            } else {
                $processingState = 'ready_for_processing';
            }
        }

        $dispatchState = 'not_required';
        if ($needsDispatch) {
            if ($needsProcessing) {
                $dispatchState = 'awaiting_processing';
            } elseif ($needsQc) {
                $dispatchState = 'awaiting_qc';
            } elseif ($needsAssembly) {
                $dispatchState = 'awaiting_assembly';
            } else {
                $dispatchState = 'ready_for_dispatch';
            }
        }

        return [
            'assembly' => $assemblyState,
            'qc' => $qcState,
            'processing' => $processingState,
            'dispatch' => $dispatchState,
        ];
    }
}
