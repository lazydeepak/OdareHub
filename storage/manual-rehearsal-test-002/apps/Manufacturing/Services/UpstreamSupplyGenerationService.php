<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

use App\Core\Auth;
use App\Core\DB;
use Plugins\Products\Services\PartExecutionRouteResolver;

final class UpstreamSupplyGenerationService
{
    /**
     * @param array<int,int> $productIds
     * @return array<string,mixed>
     */
    public static function generate(?string $targetDate = null, int $limit = 100, array $productIds = [], bool $dryRun = true): array
    {
        self::ensureSchema();

        $productIds = array_values(array_filter(array_map(static fn($v): int => (int)$v, $productIds), static fn(int $v): bool => $v > 0));
        $bucketed = DemandWorkBucketService::previewBuckets($targetDate, $limit, $productIds);
        $rows = (array)($bucketed['rows'] ?? []);

        $profiles = self::productProfileMap(array_values(array_unique(array_map(static fn(array $r): int => (int)($r['product_id'] ?? 0), $rows))));

        $summary = [
            'rows_seen' => count($rows),
            'production_created' => 0,
            'production_updated' => 0,
            'procurement_created' => 0,
            'procurement_updated' => 0,
            'skipped' => 0,
        ];

        $resultRows = [];
        foreach ($rows as $row) {
            $productId = (int)($row['product_id'] ?? 0);
            $demandDate = (string)($row['demand_date'] ?? '');
            if ($productId <= 0 || $demandDate === '') {
                $summary['skipped']++;
                continue;
            }

            $profile = $profiles[$productId] ?? [
                'supply_mode' => 'in_house',
                'fulfillment_mode' => 'company_to_destination',
                'dispatch_mode' => null,
            ];
            $sourceKey = self::sourceKey($row);

            $production = self::syncProductionCandidate($row, $profile, $sourceKey, $dryRun);
            $procurement = self::syncProcurementDemand($row, $profile, $sourceKey, $dryRun);

            $summary[$production['counter_key']] += (int)$production['count'];
            $summary[$procurement['counter_key']] += (int)$procurement['count'];

            if ((string)$production['action'] === 'skipped' && (string)$procurement['action'] === 'skipped') {
                $summary['skipped']++;
            }

            $resultRows[] = [
                'product_id' => $productId,
                'demand_date' => $demandDate,
                'route_family' => (string)($row['route_family'] ?? ''),
                'execution_path_label' => (string)($row['execution_path_label'] ?? ''),
                'profile' => $profile,
                'production' => $production,
                'procurement' => $procurement,
            ];
        }

        return [
            'dry_run' => $dryRun,
            'target_date' => $targetDate,
            'product_ids' => $productIds,
            'bucket_summary' => (array)($bucketed['summary'] ?? []),
            'generation_summary' => $summary,
            'rows' => $resultRows,
        ];
    }

    public static function ensureSchema(): void
    {
        DemandEngineService::ensureSchema();

        DB::query("CREATE TABLE IF NOT EXISTS mfg_production_plan_candidates (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            demand_date DATE NOT NULL,
            planned_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
            route_family VARCHAR(80) NULL,
            execution_path_label VARCHAR(190) NULL,
            source_demand_reference VARCHAR(120) NOT NULL,
            source_summary_json JSON NULL,
            status ENUM('draft','reviewed','approved','cancelled') NOT NULL DEFAULT 'draft',
            created_by VARCHAR(190) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_production_candidate (product_id, demand_date, source_demand_reference),
            KEY idx_production_candidate_date (demand_date),
            KEY idx_production_candidate_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DB::query("CREATE TABLE IF NOT EXISTS mfg_procurement_demands (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            required_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
            required_date DATE NOT NULL,
            fulfillment_mode VARCHAR(40) NOT NULL,
            dispatch_mode VARCHAR(40) NULL,
            route_family VARCHAR(80) NULL,
            execution_path_label VARCHAR(190) NULL,
            source_demand_reference VARCHAR(120) NOT NULL,
            source_summary_json JSON NULL,
            status ENUM('draft','reviewed','approved','cancelled') NOT NULL DEFAULT 'draft',
            created_by VARCHAR(190) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_procurement_demand (product_id, required_date, source_demand_reference),
            KEY idx_procurement_date (required_date),
            KEY idx_procurement_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DB::query("CREATE TABLE IF NOT EXISTS mfg_upstream_generation_links (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            work_type ENUM('production','procurement') NOT NULL,
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
            UNIQUE KEY uniq_upstream_link (work_type, product_id, demand_date, source_key),
            KEY idx_upstream_target (target_table, target_id),
            KEY idx_upstream_date (demand_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $profile
     * @return array<string,mixed>
     */
    private static function syncProductionCandidate(array $row, array $profile, string $sourceKey, bool $dryRun): array
    {
        $qty = round(max(0.0, (float)($row['production_demand_qty'] ?? 0.0)), 2);
        if ($qty <= 0.0) {
            return ['action' => 'skipped', 'reason' => 'no_production_demand', 'count' => 0, 'counter_key' => 'production_updated'];
        }

        $supplyMode = strtolower(trim((string)($profile['supply_mode'] ?? 'in_house')));
        if ($supplyMode !== 'in_house') {
            return ['action' => 'skipped', 'reason' => 'not_in_house_supply', 'count' => 0, 'counter_key' => 'production_updated'];
        }

        $routeFamily = (string)($row['route_family'] ?? '');
        if ($routeFamily === PartExecutionRouteResolver::ROUTE_THIRD_PARTY_DIRECT) {
            return ['action' => 'skipped', 'reason' => 'third_party_direct_no_production', 'count' => 0, 'counter_key' => 'production_updated'];
        }

        $productId = (int)$row['product_id'];
        $demandDate = (string)$row['demand_date'];

        $link = self::findLink('production', $productId, $demandDate, $sourceKey);
        $targetId = $link ? (int)$link['target_id'] : 0;

        if ($targetId > 0 && self::targetExists('mfg_production_plan_candidates', $targetId)) {
            if (!$dryRun) {
                DB::query(
                    'UPDATE mfg_production_plan_candidates SET planned_qty=?, route_family=?, execution_path_label=?, source_summary_json=?, updated_at=NOW() WHERE id=?',
                    [$qty, $routeFamily, (string)($row['execution_path_label'] ?? ''), self::safeJson($row), $targetId]
                );
                self::upsertLink('production', $productId, $demandDate, $sourceKey, $routeFamily, (string)($row['execution_path_label'] ?? ''), 'mfg_production_plan_candidates', $targetId, $qty, $row);
            }
            return ['action' => 'updated', 'target_id' => $targetId, 'qty' => $qty, 'count' => 1, 'counter_key' => 'production_updated'];
        }

        if ($dryRun) {
            return ['action' => 'create', 'target_id' => null, 'qty' => $qty, 'count' => 1, 'counter_key' => 'production_created'];
        }

        DB::query(
            'INSERT INTO mfg_production_plan_candidates (product_id, demand_date, planned_qty, route_family, execution_path_label, source_demand_reference, source_summary_json, status, created_by) VALUES (?,?,?,?,?,?,?,?,?)',
            [
                $productId,
                $demandDate,
                $qty,
                $routeFamily,
                (string)($row['execution_path_label'] ?? ''),
                $sourceKey,
                self::safeJson($row),
                'draft',
                self::currentUserLabel(),
            ]
        );
        $targetId = (int)(DB::conn()->insert_id ?: 0);
        if ($targetId <= 0) {
            throw new \RuntimeException('Failed to create production plan candidate.');
        }

        self::upsertLink('production', $productId, $demandDate, $sourceKey, $routeFamily, (string)($row['execution_path_label'] ?? ''), 'mfg_production_plan_candidates', $targetId, $qty, $row);
        return ['action' => 'created', 'target_id' => $targetId, 'qty' => $qty, 'count' => 1, 'counter_key' => 'production_created'];
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $profile
     * @return array<string,mixed>
     */
    private static function syncProcurementDemand(array $row, array $profile, string $sourceKey, bool $dryRun): array
    {
        $qty = round(max(0.0, (float)($row['procurement_demand_qty'] ?? 0.0)), 2);
        if ($qty <= 0.0) {
            return ['action' => 'skipped', 'reason' => 'no_procurement_demand', 'count' => 0, 'counter_key' => 'procurement_updated'];
        }

        $supplyMode = strtolower(trim((string)($profile['supply_mode'] ?? 'in_house')));
        if ($supplyMode !== 'third_party') {
            return ['action' => 'skipped', 'reason' => 'not_third_party_supply', 'count' => 0, 'counter_key' => 'procurement_updated'];
        }

        $productId = (int)$row['product_id'];
        $demandDate = (string)$row['demand_date'];
        $fulfillmentMode = PartExecutionRouteResolver::normalizeFulfillmentMode((string)($profile['fulfillment_mode'] ?? 'company_to_destination'));
        $dispatchMode = PartExecutionRouteResolver::effectiveDispatchMode(
            (string)($profile['dispatch_mode'] ?? ''),
            $fulfillmentMode
        );
        $routeFamily = (string)($row['route_family'] ?? '');

        $link = self::findLink('procurement', $productId, $demandDate, $sourceKey);
        $targetId = $link ? (int)$link['target_id'] : 0;

        if ($targetId > 0 && self::targetExists('mfg_procurement_demands', $targetId)) {
            if (!$dryRun) {
                DB::query(
                    'UPDATE mfg_procurement_demands SET required_qty=?, fulfillment_mode=?, dispatch_mode=?, route_family=?, execution_path_label=?, source_summary_json=?, updated_at=NOW() WHERE id=?',
                    [$qty, $fulfillmentMode, $dispatchMode, $routeFamily, (string)($row['execution_path_label'] ?? ''), self::safeJson($row), $targetId]
                );
                self::upsertLink('procurement', $productId, $demandDate, $sourceKey, $routeFamily, (string)($row['execution_path_label'] ?? ''), 'mfg_procurement_demands', $targetId, $qty, $row);
            }
            return ['action' => 'updated', 'target_id' => $targetId, 'qty' => $qty, 'count' => 1, 'counter_key' => 'procurement_updated'];
        }

        if ($dryRun) {
            return ['action' => 'create', 'target_id' => null, 'qty' => $qty, 'count' => 1, 'counter_key' => 'procurement_created'];
        }

        DB::query(
            'INSERT INTO mfg_procurement_demands (product_id, required_qty, required_date, fulfillment_mode, dispatch_mode, route_family, execution_path_label, source_demand_reference, source_summary_json, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [
                $productId,
                $qty,
                $demandDate,
                $fulfillmentMode,
                $dispatchMode,
                $routeFamily,
                (string)($row['execution_path_label'] ?? ''),
                $sourceKey,
                self::safeJson($row),
                'draft',
                self::currentUserLabel(),
            ]
        );
        $targetId = (int)(DB::conn()->insert_id ?: 0);
        if ($targetId <= 0) {
            throw new \RuntimeException('Failed to create procurement demand.');
        }

        self::upsertLink('procurement', $productId, $demandDate, $sourceKey, $routeFamily, (string)($row['execution_path_label'] ?? ''), 'mfg_procurement_demands', $targetId, $qty, $row);
        return ['action' => 'created', 'target_id' => $targetId, 'qty' => $qty, 'count' => 1, 'counter_key' => 'procurement_created'];
    }

    /**
     * @param array<int,int> $productIds
     * @return array<int,array<string,mixed>>
     */
    private static function productProfileMap(array $productIds): array
    {
        if (!$productIds) {
            return [];
        }

        $holders = implode(',', array_fill(0, count($productIds), '?'));
        $rows = DB::fetchAll(
            "SELECT id, supply_mode, fulfillment_mode, dispatch_mode
             FROM products
             WHERE id IN ({$holders})",
            $productIds
        );

        $out = [];
        foreach ($rows as $row) {
            $out[(int)$row['id']] = $row;
        }
        return $out;
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
            'SELECT * FROM mfg_upstream_generation_links WHERE work_type=? AND product_id=? AND demand_date=? AND source_key=? LIMIT 1',
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
                'UPDATE mfg_upstream_generation_links SET route_family=?, execution_path_label=?, target_table=?, target_id=?, demanded_qty=?, source_summary_json=?, updated_at=NOW() WHERE id=?',
                [$routeFamily, $executionPathLabel, $targetTable, $targetId, $qty, $payload, (int)$existing['id']]
            );
            return;
        }

        DB::query(
            'INSERT INTO mfg_upstream_generation_links (work_type, product_id, demand_date, source_key, route_family, execution_path_label, target_table, target_id, demanded_qty, source_summary_json) VALUES (?,?,?,?,?,?,?,?,?,?)',
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
}
