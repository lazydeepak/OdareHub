<?php
declare(strict_types=1);

namespace Apps\Platform\Services;

use App\Core\DB;

final class DemoDataSeedService
{
    private const DEMO_MARKER = '[DEMO-SEED]';

    /** @var array<int,string> */
    private const ORDERED_STAGES = [
        'products', 'machines', 'materials', 'orders',
        'plans', 'production', 'qc', 'dispatch',
    ];

    public static function addFeed(array $input, string $actor): array
    {
        self::ensureWorkflowColumns();
        $selectedStages = self::normalizeStages($input['stages'] ?? []);
        if ($selectedStages === []) {
            throw new \RuntimeException('Select at least one stage to feed.');
        }
        $effectiveStages = self::expandDependencies($selectedStages);
        $startDate       = self::resolveDate((string)($input['start_date'] ?? ''));
        $daySpan         = self::clampInt((int)($input['day_span'] ?? 5), 1, 30);
        $token           = date('YmdHis');
        $summary = [
            'mode' => 'feed', 'token' => $token, 'actor' => $actor,
            'start_date' => $startDate->format('Y-m-d'), 'day_span' => $daySpan,
            'selected_stages' => $selectedStages, 'effective_stages' => $effectiveStages,
            'created' => [
                'products' => 0, 'machines' => 0, 'materials' => 0, 'orders' => 0,
                'plans' => 0, 'production' => 0, 'qc' => 0, 'dispatch' => 0,
            ],
        ];
        $productIds = $machineIds = $materialIds = $orderIds = $planIds = $productionIds = $qcIds = [];

        if (in_array('products', $effectiveStages, true)) {
            $productIds = self::seedProducts($token);
            $summary['created']['products'] = count($productIds);
        }
        if (in_array('machines', $effectiveStages, true)) {
            $machineIds = self::seedMachines($token);
            $summary['created']['machines'] = count($machineIds);
        }
        if (in_array('materials', $effectiveStages, true)) {
            $materialIds = self::seedMaterials($token);
            $summary['created']['materials'] = count($materialIds);
        }

        if ($productIds  === []) { $productIds  = self::lookupProductIds(6);  }
        if ($machineIds  === []) { $machineIds  = self::lookupMachineIds(4);  }
        if ($materialIds === []) { $materialIds = self::lookupMaterialIds(8); }

        if (in_array('products', $effectiveStages, true) || in_array('machines', $effectiveStages, true)) {
            self::linkProductMachines($productIds, $machineIds, $token);
        }
        if (in_array('products', $effectiveStages, true) || in_array('materials', $effectiveStages, true)) {
            self::linkProductMaterials($productIds, $materialIds, $token, $startDate);
        }

        if (in_array('orders', $effectiveStages, true)) {
            $orderIds = self::seedOrders($productIds, $token, $startDate, $daySpan);
            $summary['created']['orders'] = count($orderIds);
        }
        if (in_array('plans', $effectiveStages, true)) {
            if ($orderIds === []) { $orderIds = self::lookupRecentOrderIds($daySpan * 2); }
            $planIds = self::seedPlans($orderIds, $productIds, $machineIds, $token, $startDate);
            $summary['created']['plans'] = count($planIds);
        }
        if (in_array('production', $effectiveStages, true)) {
            if ($planIds === []) { $planIds = self::lookupRecentPlanIds($daySpan * 2); }
            $productionIds = self::seedProductionEntries($planIds, $productIds, $machineIds, $token, $startDate);
            $summary['created']['production'] = count($productionIds);
        }
        if (in_array('qc', $effectiveStages, true)) {
            if ($planIds === []) { $planIds = self::lookupRecentPlanIds($daySpan * 2); }
            if ($orderIds === []) { $orderIds = self::lookupRecentOrderIds($daySpan * 2); }
            if ($productionIds === []) { $productionIds = self::lookupRecentProductionIds($daySpan * 2); }
            $qcIds = self::seedQcEntries($planIds, $orderIds, $productionIds, $productIds, $token, $startDate);
            $summary['created']['qc'] = count($qcIds);
        }
        if (in_array('dispatch', $effectiveStages, true)) {
            if ($planIds === []) { $planIds = self::lookupRecentPlanIds($daySpan * 2); }
            if ($orderIds === []) { $orderIds = self::lookupRecentOrderIds($daySpan * 2); }
            if ($productionIds === []) { $productionIds = self::lookupRecentProductionIds($daySpan * 2); }
            if ($qcIds === []) { $qcIds = self::lookupRecentQcIds($daySpan * 2); }
            $summary['created']['dispatch'] = self::seedDispatch(
                $orderIds, $planIds, $productionIds, $qcIds, $productIds, $token, $startDate
            );
        }
        return $summary;
    }

    public static function resetFeed(array $input, string $actor): array
    {
        $selectedStages = self::normalizeStages($input['stages'] ?? []);
        if ($selectedStages === []) {
            throw new \RuntimeException('Select at least one stage to reset.');
        }
        $effectiveStages = self::expandResetDependencies($selectedStages);
        $summary = [
            'mode' => 'reset', 'actor' => $actor,
            'selected_stages' => $selectedStages, 'effective_stages' => $effectiveStages,
            'deleted' => [
                'dispatch' => 0, 'qc' => 0, 'production' => 0, 'plans' => 0,
                'orders' => 0, 'materials' => 0, 'machines' => 0, 'products' => 0,
                'part_material_map' => 0, 'part_machine_map' => 0,
            ],
        ];
        $m = self::DEMO_MARKER;

        if (in_array('dispatch', $effectiveStages, true)) {
            DB::query(
                'DELETE FROM dispatch_entries WHERE remarks LIKE ? OR destination LIKE ? OR status_note LIKE ? OR third_party_reference LIKE ?',
                ["%{$m}%", '[DEMO]%', "%{$m}%", 'DEMO-%']
            );
            $summary['deleted']['dispatch'] = self::lastAffectedRows();
        }
        if (in_array('qc', $effectiveStages, true)) {
            DB::query('DELETE FROM qc_entries WHERE remarks LIKE ?', ["%{$m}%"]);
            $summary['deleted']['qc'] = self::lastAffectedRows();
        }
        if (in_array('production', $effectiveStages, true)) {
            DB::query('DELETE FROM production_entries WHERE notes LIKE ?', ["%{$m}%"]);
            $summary['deleted']['production'] = self::lastAffectedRows();
        }
        if (in_array('plans', $effectiveStages, true)) {
            DB::query(
                'DELETE FROM production_plans WHERE notes LIKE ? OR reference_name LIKE ?',
                ["%{$m}%", 'DEMO-%']
            );
            $summary['deleted']['plans'] = self::lastAffectedRows();
        }
        if (in_array('orders', $effectiveStages, true)) {
            DB::query(
                'DELETE FROM daily_orders WHERE notes LIKE ? OR customer_name LIKE ?',
                ["%{$m}%", '[DEMO]%']
            );
            $summary['deleted']['orders'] = self::lastAffectedRows();
        }
        if (in_array('materials', $effectiveStages, true) || in_array('products', $effectiveStages, true)) {
            DB::query('DELETE FROM part_material_map WHERE notes LIKE ?', ["%{$m}%"]);
            $summary['deleted']['part_material_map'] = self::lastAffectedRows();
        }
        if (in_array('machines', $effectiveStages, true) || in_array('products', $effectiveStages, true)) {
            DB::query('DELETE FROM part_machine_map WHERE notes LIKE ?', ["%{$m}%"]);
            $summary['deleted']['part_machine_map'] = self::lastAffectedRows();
        }
        if (in_array('materials', $effectiveStages, true)) {
            DB::query(
                'DELETE FROM materials WHERE notes LIKE ? OR material_code LIKE ? OR material_name LIKE ?',
                ["%{$m}%", 'DEMO-%', '[DEMO]%']
            );
            $summary['deleted']['materials'] = self::lastAffectedRows();
        }
        if (in_array('machines', $effectiveStages, true)) {
            DB::query(
                'DELETE FROM machines WHERE notes LIKE ? OR machine_no LIKE ? OR machine_name LIKE ?',
                ["%{$m}%", 'DEMO-%', '[DEMO]%']
            );
            $summary['deleted']['machines'] = self::lastAffectedRows();
        }
        if (in_array('products', $effectiveStages, true)) {
            DB::query(
                'DELETE FROM products WHERE notes LIKE ? OR parts_number LIKE ? OR parts_name LIKE ?',
                ["%{$m}%", 'DEMO-%', '[DEMO]%']
            );
            $summary['deleted']['products'] = self::lastAffectedRows();
        }
        return $summary;
    }

    // -------------------------------------------------------------------------
    // Normalisation / dependency helpers
    // -------------------------------------------------------------------------

    private static function normalizeStages(mixed $raw): array
    {
        $list = [];
        foreach ((array)$raw as $stage) {
            $v = strtolower(trim((string)$stage));
            if (in_array($v, self::ORDERED_STAGES, true)) { $list[] = $v; }
        }
        return array_values(array_unique($list));
    }

    private static function expandDependencies(array $selected): array
    {
        $deps = [
            'products'   => [],
            'machines'   => [],
            'materials'  => [],
            'orders'     => ['products'],
            'plans'      => ['products', 'machines', 'orders'],
            'production' => ['products', 'machines', 'plans'],
            'qc'         => ['products', 'plans', 'production', 'orders'],
            'dispatch'   => ['products', 'orders', 'plans', 'production', 'qc'],
        ];
        $required = [];
        $walk = static function (string $stage) use (&$walk, &$required, $deps): void {
            if (in_array($stage, $required, true)) { return; }
            foreach ($deps[$stage] ?? [] as $dep) { $walk($dep); }
            $required[] = $stage;
        };
        foreach ($selected as $s) { $walk($s); }
        usort($required, static fn(string $a, string $b): int =>
            array_search($a, self::ORDERED_STAGES, true) <=> array_search($b, self::ORDERED_STAGES, true)
        );
        return $required;
    }

    private static function expandResetDependencies(array $selected): array
    {
        return self::expandDependencies($selected);
    }

    private static function resolveDate(string $value): \DateTimeImmutable
    {
        $v = trim($value);
        if ($v !== '') {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $v);
            if ($dt instanceof \DateTimeImmutable) { return $dt; }
        }
        return new \DateTimeImmutable('today');
    }

    private static function clampInt(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }

    // -------------------------------------------------------------------------
    // Seed: products  (full schema – all enum paths covered)
    // -------------------------------------------------------------------------
    private static function seedProducts(string $token): array
    {
        $ids = [];
        // [supply_mode, fulfillment_mode, production_source, delivery_flow, dispatch_mode]
        $variants = [
            ['in_house',    'via_ipm',                 'in_house',    'company_to_destination',                  'internal'],
            ['in_house',    'company_to_destination',  'in_house',    'company_to_destination',                  'internal'],
            ['third_party', 'via_ipm',                 'third_party', 'third_party_to_destination',              'direct_supplier'],
            ['third_party', 'company_to_destination',  'third_party', 'third_party_to_company_to_destination',   'hybrid'],
            ['in_house',    'company_to_destination',  'in_house',    'company_to_destination',                  'hybrid'],
            ['in_house',    'via_ipm',                 'in_house',    'company_to_destination',                  'internal'],
        ];
        for ($i = 1; $i <= 6; $i++) {
            $v = $variants[$i - 1];
            DB::query(
                'INSERT INTO products
                    (parts_name, parts_number, model, producer, `lead`,
                     cycle_time, qc_time_per_item, notes, is_active,
                     supply_mode, fulfillment_mode, requires_ipm_qc, default_supplier,
                     stocked_at_ipm, default_procurement_lead_days, default_supply_note,
                     requires_assembly, dispatch_as_is, activity_type,
                     essential_stock_qty, planning_window_days, max_buffer_qty,
                     qty_per_case, cases_per_pallet, case_type, case_spec,
                     requires_processing, dispatch_mode,
                     production_source, requires_qc, delivery_flow)
                 VALUES (?, ?, ?, ?, ?,
                         ?, ?, ?, 1,
                         ?, ?, 1, ?,
                         1, ?, ?,
                         0, 0, ?,
                         ?, ?, ?,
                         ?, ?, ?, ?,
                         1, ?,
                         ?, 1, ?)',
                [
                    sprintf('[DEMO] Product %02d', $i),
                    sprintf('DEMO-%s-P%02d', $token, $i),
                    'DEMO-MODEL', 'Platform Demo Feed', 'demo.lead@ipm',
                    round(18.5 + $i * 1.2, 2), round(2.0 + $i * 0.5, 2),
                    self::DEMO_MARKER . ' token=' . $token,
                    $v[0], $v[1],
                    'Demo Supplier Co.',
                    3 + $i,
                    'Demo supply note ' . $i,
                    'active',
                    (float)(50 + $i * 10), 7, (float)(200 + $i * 20),
                    20 + $i * 2, 5, 'Cardboard', sprintf('DEMO-CASE-%02d', $i),
                    $v[4],
                    $v[2], $v[3],
                ]
            );
            $ids[] = self::lastInsertId();
        }
        return array_values(array_filter($ids, static fn(int $id): bool => $id > 0));
    }

    // -------------------------------------------------------------------------
    // Seed: machines  (full schema)
    // -------------------------------------------------------------------------
    private static function seedMachines(string $token): array
    {
        $ids = [];
        $specs = [
            [180.0, 180.0, 350.0, '420x420', '400x400', 150.0, 400.0, 'Resin,ABS'],
            [250.0, 250.0, 500.0, '520x520', '480x480', 170.0, 500.0, 'ABS,PP'],
            [320.0, 320.0, 750.0, '620x620', '580x580', 200.0, 650.0, 'PP,Nylon'],
            [450.0, 450.0, 900.0, '720x720', '680x680', 220.0, 800.0, 'Nylon,PC'],
        ];
        for ($i = 1; $i <= 4; $i++) {
            $s = $specs[$i - 1];
            DB::query(
                'INSERT INTO machines
                    (machine_no, machine_name, section, machine_group, machine_type,
                     status, capacity_per_hour, clamping_force_ton, shot_capacity_g,
                     tie_bar_spacing_mm, platen_size_mm, min_mold_height_mm, max_mold_height_mm,
                     preferred_materials, notes, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)',
                [
                    sprintf('DEMO-M-%s-%02d', $token, $i),
                    sprintf('[DEMO] Machine %02d', $i),
                    'MFG-DEMO', 'INJECTION', 'Injection', 'Available',
                    $s[0], $s[1], $s[2], $s[3], $s[4], $s[5], $s[6], $s[7],
                    self::DEMO_MARKER . ' token=' . $token,
                ]
            );
            $ids[] = self::lastInsertId();
        }
        return array_values(array_filter($ids, static fn(int $id): bool => $id > 0));
    }

    // -------------------------------------------------------------------------
    // Seed: materials  (full schema)
    // -------------------------------------------------------------------------
    private static function seedMaterials(string $token): array
    {
        $ids = [];
        $grades   = ['GP',  'HI',       'LG',        'FR',        'UV',    'HD',    'SD',        'EC'];
        $colors   = ['Natural', 'Black', 'White',     'Gray',      'Blue',  'Red',   'Green',     'Yellow'];
        $subtypes = ['Resin',   'Resin', 'Compound',  'Compound',  'Resin', 'Film',  'Resin',     'Compound'];
        for ($i = 1; $i <= 8; $i++) {
            $code = sprintf('DEMO-MAT-%s-%02d', $token, $i);
            DB::query(
                'INSERT INTO materials
                    (material_number, material_code, material_name, material_type, material_subtype,
                     vendor_name, uom, pack_size, material_grade, color, unit,
                     supplier_name, supplier_ref, lead_time_days,
                     minimum_stock_qty, reorder_point_qty, maximum_stock_qty, storage_capacity_qty,
                     standard_cost, currency, safety_stock_qty, max_storage_qty, storage_location,
                     standard_unit_cost, reorder_policy, min_order_qty, order_lot_size,
                     is_active, notes)
                 VALUES (?, ?, ?, ?, ?,
                         ?, ?, ?, ?, ?, ?,
                         ?, ?, ?,
                         ?, ?, ?, ?,
                         ?, ?, ?, ?, ?,
                         ?, ?, ?, ?,
                         1, ?)',
                [
                    $code, $code,
                    sprintf('[DEMO] Material %02d', $i),
                    '材料', $subtypes[$i - 1],
                    'Demo Vendor Co.', 'kg', 25.0, $grades[$i - 1], $colors[$i - 1], 'kg',
                    'Demo Supplier Co.', sprintf('DS-REF-%02d', $i), 3 + $i,
                    100.0, 150.0, 500.0, 600.0,
                    round(2.75 + $i * 0.15, 4), 'USD', 80.0, 800.0, 'DEMO-WH-A' . $i,
                    round(2.75 + $i * 0.15, 4), 'fixed_order_qty', 50.0, 50.0,
                    self::DEMO_MARKER . ' token=' . $token,
                ]
            );
            $ids[] = self::lastInsertId();
        }
        return array_values(array_filter($ids, static fn(int $id): bool => $id > 0));
    }

    // -------------------------------------------------------------------------
    // Link: product <-> machine / material  (with snapshot fields)
    // -------------------------------------------------------------------------
    private static function linkProductMachines(array $productIds, array $machineIds, string $token): void
    {
        if ($productIds === [] || $machineIds === []) { return; }
        $mc = count($machineIds);
        foreach (array_values($productIds) as $idx => $pid) {
            $mid = $machineIds[$idx % $mc];
            DB::query(
                'INSERT INTO part_machine_map (product_id, machine_id, is_active, notes)
                 SELECT ?, ?, 1, ?
                 WHERE NOT EXISTS (SELECT 1 FROM part_machine_map WHERE product_id=? AND machine_id=?)',
                [$pid, $mid, self::DEMO_MARKER . ' token=' . $token, $pid, $mid]
            );
        }
    }

    private static function linkProductMaterials(array $productIds, array $materialIds, string $token, \DateTimeImmutable $startDate): void
    {
        if ($productIds === [] || $materialIds === []) { return; }
        $mc = count($materialIds);
        foreach (array_values($productIds) as $idx => $pid) {
            $matId = $materialIds[$idx % $mc];
            $mat   = DB::fetchOne('SELECT material_code, material_name, material_type, vendor_name FROM materials WHERE id=?', [$matId]);
            DB::query(
                'INSERT INTO part_material_map
                    (product_id, material_id, qty_per_part, uom, usage_qty, usage_unit,
                     yield_parts_per_kg, material_code_snapshot, material_name_snapshot,
                     material_type_snapshot, vendor_name_snapshot, scrap_pct,
                     is_primary, effective_from, sequence_no, is_active, notes)
                 SELECT ?, ?, 0.3500, ?, 0.3500, ?, 2.857, ?, ?, ?, ?, 1.50, 1, ?, 1, 1, ?
                 WHERE NOT EXISTS (SELECT 1 FROM part_material_map WHERE product_id=? AND material_id=?)',
                [
                    $pid, $matId, 'kg', 'kg',
                    (string)($mat['material_code'] ?? ''),
                    (string)($mat['material_name'] ?? ''),
                    (string)($mat['material_type'] ?? ''),
                    (string)($mat['vendor_name']   ?? ''),
                    $startDate->format('Y-m-d'),
                    self::DEMO_MARKER . ' token=' . $token,
                    $pid, $matId,
                ]
            );
        }
    }

    // -------------------------------------------------------------------------
    // Seed: daily orders
    // -------------------------------------------------------------------------
    private static function seedOrders(array $productIds, string $token, \DateTimeImmutable $startDate, int $daySpan): array
    {
        $ids = []; $pc = count($productIds);
        for ($d = 0; $d < $daySpan; $d++) {
            $orderDate = $startDate->modify('+' . $d . ' day');
            for ($j = 0; $j < 2; $j++) {
                $pid = $productIds[($d + $j) % $pc];
                $qty = 30 + ($d * 4) + ($j * 6);
                DB::query(
                    'INSERT INTO daily_orders
                        (order_date, required_date, customer_name, product_id, qty,
                         dispatch_deadline, coverage_pct, coverage_status,
                         shortage_qty, planned_supply_qty, usable_stock_qty, qc_pass_qty,
                         dispatched_qty, usable_supply_qty, open_demand_qty, forecast_pressure_qty,
                         coverage_last_recalculated_at, status, notes)
                     VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, 0, 0, 0, 0, 0, ?, 0, NOW(), ?, ?)',
                    [
                        $orderDate->format('Y-m-d'),
                        $orderDate->modify('+2 day')->format('Y-m-d'),
                        sprintf('[DEMO] Customer %02d', ($d + 1)),
                        $pid, (float)$qty,
                        $orderDate->setTime(17, 0)->format('Y-m-d H:i:s'),
                        'Low', (float)$qty, (float)$qty, 'Open',
                        self::DEMO_MARKER . ' token=' . $token,
                    ]
                );
                $ids[] = self::lastInsertId();
            }
        }
        return array_values(array_filter($ids, static fn(int $id): bool => $id > 0));
    }

    // -------------------------------------------------------------------------
    // Seed: production plans  (workflow/approval state coverage)
    // -------------------------------------------------------------------------
    private static function seedPlans(array $orderIds, array $productIds, array $machineIds, string $token, \DateTimeImmutable $startDate): array
    {
        if ($orderIds === []) { return []; }
        $ids = []; $mc = count($machineIds);
        $orders = DB::fetchAll(
            'SELECT id, product_id, order_date, qty FROM daily_orders
             WHERE id IN (' . implode(',', array_map('intval', $orderIds)) . ')
             ORDER BY id ASC'
        );
        $approvalCycle = ['Draft','Draft','Approved','Draft','Locked','Draft','Approved','Draft','Draft','Approved'];
        foreach ($orders as $idx => $order) {
            $pid = (int)($order['product_id'] ?? 0);
            if ($pid <= 0) { $pid = $productIds[$idx % count($productIds)]; }
            $dr = trim((string)($order['order_date'] ?? ''));
            $pd = $dr !== '' ? new \DateTimeImmutable($dr) : $startDate;
            $pq = max(30.0, (float)($order['qty'] ?? 0));
            $app = $approvalCycle[$idx % count($approvalCycle)];
            $wf  = match ($app) { 'Approved' => 'approved', 'Locked' => 'locked', default => 'draft' };
            DB::query(
                'INSERT INTO production_plans
                    (plan_date, machine_id, product_id, planned_qty, sequence_no,
                     runtime, status, plan_type, reference_doctype, reference_name,
                     coverage_pct, shortage_qty, auto_created, notes, added_by,
                     workflow_state, approval_status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 1, ?, ?, ?, ?)',
                [
                    $pd->format('Y-m-d'), $machineIds[$idx % $mc], $pid, $pq, ($idx % 6) + 1,
                    round($pq / 120.0, 2), 'Planned', 'Auto', 'daily_orders',
                    'DEMO-ORDER-' . (int)($order['id'] ?? 0),
                    $pq, self::DEMO_MARKER . ' token=' . $token,
                    'demo.seed@platform', $wf, $app,
                ]
            );
            $ids[] = self::lastInsertId();
        }
        return array_values(array_filter($ids, static fn(int $id): bool => $id > 0));
    }

    // -------------------------------------------------------------------------
    // Seed: production_entries
    // -------------------------------------------------------------------------
    private static function seedProductionEntries(array $planIds, array $productIds, array $machineIds, string $token, \DateTimeImmutable $startDate): array
    {
        if ($planIds === []) { return []; }
        $ids = []; $mc = count($machineIds);
        $shifts   = ['Day', 'Day', 'Night', 'Day', 'Night'];
        $statuses = ['Confirmed', 'Confirmed', 'Confirmed', 'Draft', 'Confirmed'];
        $plans = DB::fetchAll(
            'SELECT id, product_id, plan_date, planned_qty, machine_id FROM production_plans
             WHERE id IN (' . implode(',', array_map('intval', $planIds)) . ')
             ORDER BY id ASC'
        );
        foreach ($plans as $idx => $plan) {
            $pid = (int)($plan['product_id'] ?? 0);
            if ($pid <= 0) { $pid = $productIds[$idx % count($productIds)]; }
            $mid = (int)($plan['machine_id'] ?? 0);
            if ($mid <= 0) { $mid = $machineIds[$idx % $mc]; }
            $dr       = trim((string)($plan['plan_date'] ?? ''));
            $prodDate = $dr !== '' ? new \DateTimeImmutable($dr) : $startDate;
            $pq       = (float)($plan['planned_qty'] ?? 30);
            $rj       = round($pq * 0.02, 2);
            DB::query(
                'INSERT INTO production_entries
                    (production_date, shift, machine_id, product_id,
                     produced_qty, rejected_qty, good_qty, status, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $prodDate->format('Y-m-d'), $shifts[$idx % count($shifts)],
                    $mid, $pid,
                    $pq, $rj, round($pq - $rj, 2),
                    $statuses[$idx % count($statuses)],
                    self::DEMO_MARKER . ' token=' . $token,
                ]
            );
            $ids[] = self::lastInsertId();
        }
        return array_values(array_filter($ids, static fn(int $id): bool => $id > 0));
    }

    // -------------------------------------------------------------------------
    // Seed: qc_entries  (workflow/approval state coverage)
    // -------------------------------------------------------------------------
    private static function seedQcEntries(array $planIds, array $orderIds, array $productionIds, array $productIds, string $token, \DateTimeImmutable $startDate): array
    {
        if ($planIds === []) { return []; }
        $ids = []; $oc = max(1, count($orderIds));
        $approvalCycle = ['Draft','Approved','Draft','Approved','Draft','Approved','Draft','Approved','Draft','Approved'];
        $plans = DB::fetchAll(
            'SELECT id, product_id, plan_date, planned_qty FROM production_plans
             WHERE id IN (' . implode(',', array_map('intval', $planIds)) . ')
             ORDER BY id ASC'
        );
        foreach ($plans as $idx => $plan) {
            $pid = (int)($plan['product_id'] ?? 0);
            if ($pid <= 0) { $pid = $productIds[$idx % count($productIds)]; }
            $cq  = (float)($plan['planned_qty'] ?? 30);
            $fq  = round($cq * 0.02, 2);
            $pq  = round($cq - $fq, 2);
            $app = $approvalCycle[$idx % count($approvalCycle)];
            $wf  = $app === 'Approved' ? 'approved' : 'draft';
            DB::query(
                'INSERT INTO qc_entries
                    (qc_plan_id, daily_order_id, production_plan_id, product_id,
                     qc_type, checked_qty, pass_qty, fail_qty,
                     status, remarks, workflow_state, approval_status)
                 VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $orderIds[$idx % $oc], (int)($plan['id'] ?? 0), $pid,
                    'Final', $cq, $pq, $fq,
                    'Closed', self::DEMO_MARKER . ' token=' . $token, $wf, $app,
                ]
            );
            $ids[] = self::lastInsertId();
        }
        return array_values(array_filter($ids, static fn(int $id): bool => $id > 0));
    }

    // -------------------------------------------------------------------------
    // Seed: dispatch_entries  (all enum variant combinations)
    // -------------------------------------------------------------------------
    private static function seedDispatch(
        array $orderIds,
        array $planIds,
        array $productionIds,
        array $qcIds,
        array $productIds,
        string $token,
        \DateTimeImmutable $startDate
    ): int {
        if ($planIds === []) { return 0; }
        $count = 0;
        $oc  = max(1, count($orderIds));
        $qc  = max(1, count($qcIds));
        $prc = max(1, count($productionIds));
        // [dispatch_mode, delivery_flow, source_type, completion_status]
        $modeVariants = [
            ['company_origin_dispatch',                'company_to_destination',                 'in_house',    'ready'],
            ['in_house_dispatch',                      'company_to_destination',                 'in_house',    'prepared'],
            ['third_party_dispatch',                   'third_party_to_destination',             'third_party', 'ready'],
            ['third_party_to_company_then_destination','third_party_to_company_to_destination',  'third_party', 'ready'],
            ['company_origin_dispatch',                'company_to_destination',                 'in_house',    'completed'],
        ];
        $plans = DB::fetchAll(
            'SELECT id, product_id, plan_date, planned_qty FROM production_plans
             WHERE id IN (' . implode(',', array_map('intval', $planIds)) . ')
             ORDER BY id ASC'
        );
        foreach ($plans as $idx => $plan) {
            $dr = trim((string)($plan['plan_date'] ?? ''));
            $dd = $dr !== ''
                ? (new \DateTimeImmutable($dr))->modify('+1 day')
                : $startDate->modify('+1 day');
            $pid  = (int)($plan['product_id'] ?? 0);
            if ($pid <= 0) { $pid = $productIds[$idx % count($productIds)]; }
            $qty  = max(20.0, (float)($plan['planned_qty'] ?? 20));
            $mv   = $modeVariants[$idx % count($modeVariants)];
            $cases   = (int)ceil($qty / 22);
            $pallets = (int)ceil($cases / 5);
            $done    = $mv[3] === 'completed';
            DB::query(
                'INSERT INTO dispatch_entries
                    (dispatch_date, daily_order_id, production_plan_id, production_entry_id, qc_entry_id,
                     product_id, dispatchable_qty, destination, dispatch_type, dispatch_status,
                     remarks, status_note, released_at, last_transition_at,
                     workflow_state, approval_status,
                     dispatch_mode, delivery_flow, source_type,
                     cases_count, pallets_count,
                     prepared_by, prepared_at,
                     dispatch_completed_by, dispatch_completed_at,
                     completion_status, third_party_reference)
                 VALUES (?, ?, ?, ?, ?,
                         ?, ?, ?, ?, ?,
                         ?, ?, NOW(), NOW(),
                         ?, ?,
                         ?, ?, ?,
                         ?, ?,
                         ?, NOW(),
                         ?, ?,
                         ?, ?)',
                [
                    $dd->format('Y-m-d'),
                    $orderIds[$idx % $oc],
                    (int)($plan['id'] ?? 0),
                    $productionIds[$idx % $prc],
                    $qcIds[$idx % $qc],
                    $pid, $qty, '[DEMO] Destination Yard', 'Regular', 'Ready',
                    self::DEMO_MARKER . ' token=' . $token,
                    self::DEMO_MARKER,
                    'draft', 'Draft',
                    $mv[0], $mv[1], $mv[2],
                    $cases, $pallets,
                    'demo.seed@platform',
                    $done ? 'demo.dispatch@platform' : null,
                    $done ? $dd->format('Y-m-d H:i:s') : null,
                    $mv[3],
                    'DEMO-' . $token . '-' . sprintf('%03d', $idx + 1),
                ]
            );
            $count++;
        }
        return $count;
    }

    // -------------------------------------------------------------------------
    // Lookup helpers
    // -------------------------------------------------------------------------
    private static function lookupProductIds(int $limit): array
    {
        $rows = DB::fetchAll('SELECT id FROM products WHERE is_active=1 ORDER BY id DESC LIMIT ' . max(1, $limit));
        return array_values(array_map(static fn(array $r): int => (int)($r['id'] ?? 0), $rows));
    }

    private static function lookupMachineIds(int $limit): array
    {
        $rows = DB::fetchAll('SELECT id FROM machines WHERE is_active=1 ORDER BY id DESC LIMIT ' . max(1, $limit));
        return array_values(array_map(static fn(array $r): int => (int)($r['id'] ?? 0), $rows));
    }

    private static function lookupMaterialIds(int $limit): array
    {
        $rows = DB::fetchAll('SELECT id FROM materials WHERE is_active=1 ORDER BY id DESC LIMIT ' . max(1, $limit));
        return array_values(array_map(static fn(array $r): int => (int)($r['id'] ?? 0), $rows));
    }

    private static function lookupRecentOrderIds(int $limit): array
    {
        $rows = DB::fetchAll('SELECT id FROM daily_orders ORDER BY id DESC LIMIT ' . max(1, $limit));
        return array_values(array_map(static fn(array $r): int => (int)($r['id'] ?? 0), $rows));
    }

    private static function lookupRecentPlanIds(int $limit): array
    {
        $rows = DB::fetchAll('SELECT id FROM production_plans ORDER BY id DESC LIMIT ' . max(1, $limit));
        return array_values(array_map(static fn(array $r): int => (int)($r['id'] ?? 0), $rows));
    }

    private static function lookupRecentProductionIds(int $limit): array
    {
        $rows = DB::fetchAll('SELECT id FROM production_entries ORDER BY id DESC LIMIT ' . max(1, $limit));
        return array_values(array_map(static fn(array $r): int => (int)($r['id'] ?? 0), $rows));
    }

    private static function lookupRecentQcIds(int $limit): array
    {
        $rows = DB::fetchAll('SELECT id FROM qc_entries ORDER BY id DESC LIMIT ' . max(1, $limit));
        return array_values(array_map(static fn(array $r): int => (int)($r['id'] ?? 0), $rows));
    }

    private static function lastInsertId(): int
    {
        $row = DB::fetchOne('SELECT LAST_INSERT_ID() AS id');
        return (int)($row['id'] ?? 0);
    }

    private static function lastAffectedRows(): int
    {
        $row = DB::fetchOne('SELECT ROW_COUNT() AS c');
        return (int)($row['c'] ?? 0);
    }

    /**
     * Ensures workflow/approval governance columns exist on the manufacturing
     * tables the demo seeder writes into. Live installations that predate the
     * governance rollout may be missing these columns; without them the demo
     * feed fails with "Unknown column 'workflow_state' in 'field list'".
     */
    private static function ensureWorkflowColumns(): void
    {
        $targets = [
            'production_plans' => [
                'workflow_state'   => "ALTER TABLE production_plans ADD COLUMN workflow_state VARCHAR(32) NOT NULL DEFAULT 'draft'",
                'approval_status'  => "ALTER TABLE production_plans ADD COLUMN approval_status VARCHAR(32) NOT NULL DEFAULT 'Draft'",
            ],
            'qc_entries' => [
                'workflow_state'   => "ALTER TABLE qc_entries ADD COLUMN workflow_state VARCHAR(32) NOT NULL DEFAULT 'draft'",
                'approval_status'  => "ALTER TABLE qc_entries ADD COLUMN approval_status VARCHAR(32) NOT NULL DEFAULT 'Draft'",
            ],
            'dispatch_entries' => [
                'workflow_state'   => "ALTER TABLE dispatch_entries ADD COLUMN workflow_state VARCHAR(32) NOT NULL DEFAULT 'draft'",
                'approval_status'  => "ALTER TABLE dispatch_entries ADD COLUMN approval_status VARCHAR(32) NOT NULL DEFAULT 'Draft'",
            ],
        ];
        foreach ($targets as $table => $cols) {
            $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            if (!$safeTable) { continue; }
            // Skip if table doesn't exist — nothing to alter yet.
            $tableExists = DB::fetchOne("SHOW TABLES LIKE '{$safeTable}'");
            if (!$tableExists) { continue; }
            foreach ($cols as $column => $alterSql) {
                $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
                if (!$safeColumn) { continue; }
                $exists = DB::fetchOne("SHOW COLUMNS FROM {$safeTable} LIKE '{$safeColumn}'");
                if ($exists) { continue; }
                try {
                    DB::query($alterSql);
                } catch (\Throwable $e) {
                    // Swallow — surface via seeder log, but don't block other columns.
                    error_log('[DemoDataSeedService] ensureWorkflowColumns: ' . $e->getMessage());
                }
            }
        }
    }
}
