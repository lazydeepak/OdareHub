<?php
declare(strict_types=1);

namespace Plugins\Products\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\View;
use Plugins\MaterialManagement\Services\MaterialSchemaService;
use Plugins\Products\Services\PartEngineeringSchemaService;
use Plugins\Products\Services\Part360Service;
use Plugins\Products\Services\PartExecutionRouteResolver;
use Plugins\Products\Services\PartsMasterSnapshotService;
use Plugins\Supply\Services\SupplyModel;

require_once __DIR__ . '/../Services/Part360Service.php';
require_once __DIR__ . '/../Services/PartExecutionRouteResolver.php';
require_once __DIR__ . '/../Services/PartEngineeringSchemaService.php';
require_once __DIR__ . '/../Services/PartsMasterSnapshotService.php';
require_once APP_ROOT . '/apps/Manufacturing/modules/MaterialManagement/Services/MaterialSchemaService.php';

final class ProductsController
{
    public static function index(View $view): void
    {
        $user = Auth::user();
        $q = trim((string)($_GET['q'] ?? ''));
        $active = (string)($_GET['active'] ?? 'all');
        $anchorDate = trim((string)($_GET['date'] ?? date('Y-m-d')));
        $scope = trim((string)($_GET['scope'] ?? ''));
        $sort = trim((string)($_GET['sort'] ?? 'coverage_balance_qty'));
        $dir = trim((string)($_GET['dir'] ?? 'asc'));
        $snapshot = PartsMasterSnapshotService::build(
            $q,
            $active,
            $anchorDate,
            (int)($user['id'] ?? 0),
            $scope,
            $sort,
            $dir
        );

        $view->render('Products::index.php', [
            'pageTitle' => 'Parts Master',
            'rows' => (array)($snapshot['rows'] ?? []),
            'q' => $q,
            'active' => $active,
            'anchor_date' => (string)($snapshot['anchor_date'] ?? date('Y-m-d')),
            'scope_mode' => (string)($snapshot['scope_mode'] ?? 'my'),
            'scope_source' => (string)($snapshot['scope_source'] ?? 'assigned_parts'),
            'has_assigned_parts' => !empty($snapshot['has_assigned_parts']),
            'sort' => (string)($snapshot['sort'] ?? 'coverage_balance_qty'),
            'dir' => (string)($snapshot['dir'] ?? 'asc'),
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
        ]);
    }

    public static function addForm(View $view): void
    {
        $old = $_SESSION['products_old'] ?? [];
        if ((string)($old['supply_mode'] ?? '') === '') {
            $old['supply_mode'] = SupplyModel::SUPPLY_IN_HOUSE;
        }
        if ((string)($old['fulfillment_mode'] ?? '') === '') {
            $old['fulfillment_mode'] = 'company_to_destination';
        }
        if (!array_key_exists('requires_ipm_qc', $old)) {
            $old['requires_ipm_qc'] = '1';
        }
        if (!array_key_exists('stocked_at_ipm', $old)) {
            $old['stocked_at_ipm'] = '1';
        }
        if (!array_key_exists('requires_processing', $old)) {
            $old['requires_processing'] = '1';
        }
        if (!array_key_exists('dispatch_mode', $old)) {
            $old['dispatch_mode'] = '';
        }

        $view->render('Products::add.php', [
            'pageTitle' => 'Add Part',
            'old' => $old,
            'supply_mode_options' => self::supplyModeOptions(),
            'fulfillment_mode_options' => PartExecutionRouteResolver::getFulfillmentModeOptions(),
            'dispatch_mode_options' => self::dispatchModeOptions(),
            'error' => self::pullFlash('err'),
        ]);
        unset($_SESSION['products_old']);
    }

    public static function create(array $input): void
    {
        $data = self::sanitize($input);
        $_SESSION['products_old'] = $data;

        if ($data['parts_name'] === '' || $data['parts_number'] === '') {
            self::flash('err', 'Part name and part number are required.');
            header('Location: /products/add');
            exit;
        }

        try {
        $columns = [
            'parts_name' => $data['parts_name'],
            'parts_number' => $data['parts_number'],
            'model' => $data['model'],
            'producer' => $data['producer'],
            'lead' => $data['lead'],
            'cycle_time' => $data['cycle_time'],
            'notes' => $data['notes'],
            'is_active' => $data['is_active'],
        ];

        // Shared Items adoption (Session A) — optional item_ref linkage; no identity change
        if (isset($data['item_ref']) && $data['item_ref'] !== '' && $data['item_ref'] !== null) {
            $refParts = array_filter(explode(':', $data['item_ref']));
            if (count($refParts) >= 1) {
                $itemId = (int)($refParts[0]);
                $codeRef = $refParts[1] ?? '';
                try {
                    $adapter = new \Apps\Manufacturing\Module\Products\Services\SharedItemAdapter();
                    if ($adapter->validateRef($itemId, $codeRef)) {
                        $columns['item_ref'] = $itemId; // persist integer reference only
                    } else {
                        self::flash('err', 'Shared Item reference invalid or mismatched (item_ref).');
                        header('Location: /products/add');
                        exit;
                    }
                } catch (\Throwable $e) {
                    self::flash('err', 'Shared Item resolution failed: ' . $e->getMessage());
                    header('Location: /products/add');
                    exit;
                }
            }
        }

            self::appendSupplyColumns($columns, $data);

            $fieldNames = array_map(
                static fn(string $name): string => $name === 'lead' ? '`lead`' : $name,
                array_keys($columns)
            );
            $fieldSql = implode(', ', $fieldNames);
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            DB::query(
                "INSERT INTO products ({$fieldSql}) VALUES ({$placeholders})",
                array_values($columns)
            );
            unset($_SESSION['products_old']);
            self::flash('ok', 'Part created successfully.');
        } catch (\Throwable $e) {
            self::flash('err', 'Create failed: ' . $e->getMessage());
            header('Location: /products/add');
            exit;
        }
    }

    public static function editForm(View $view, int $id): void
    {
        if ($id <= 0) {
            self::flash('err', 'Invalid part id.');
            header('Location: /products');
            exit;
        }

        self::assertCanEditLeadSections(Auth::user(), $id, 'Only the assigned lead or an admin can edit this part.');

        $row = DB::fetchOne('SELECT * FROM products WHERE id=? LIMIT 1', [$id]);
        if (!$row) {
            self::flash('err', 'Part not found.');
            header('Location: /products');
            exit;
        }

        $row['stock_balance'] = self::hasLedgerTable()
            ? (float)(DB::fetchOne('SELECT COALESCE(SUM(qty_delta), 0) AS balance FROM stock_ledger_entries WHERE product_id=?', [$id])['balance'] ?? 0)
            : 0.0;

        $view->render('Products::edit.php', [
            'pageTitle' => 'Edit Part',
            'row' => $row,
            'supply_mode_options' => self::supplyModeOptions(),
            'fulfillment_mode_options' => PartExecutionRouteResolver::getFulfillmentModeOptions(),
            'dispatch_mode_options' => self::dispatchModeOptions(),
            'error' => self::pullFlash('err'),
        ]);
    }

    public static function part360(View $view, int $id): void
    {
        if ($id <= 0) {
            self::flash('err', 'Invalid part id.');
            header('Location: /products');
            exit;
        }

        $payload = Part360Service::build($id);
        if (!$payload) {
            self::flash('err', 'Part not found.');
            header('Location: /products');
            exit;
        }

        $product = (array)($payload['product'] ?? []);
        $user = Auth::user();
        $currentRole = strtolower(trim((string)($user['role'] ?? '')));
        $canEditLeadSections = self::canEditLeadSections($user, $id);
        $canManageLifecycle = self::canManageLifecycle($user);
        $view->render('Products::part_360.php', [
            'pageTitle' => 'Part 360 ' . (string)($product['parts_number'] ?? ''),
            'payload' => $payload,
            'current_role' => $currentRole,
            'can_edit_lead_sections' => $canEditLeadSections,
            'can_manage_responsibility' => $canEditLeadSections,
            'can_manage_lifecycle' => $canManageLifecycle,
            'is_admin' => $canManageLifecycle,
            'error' => self::pullFlash('err'),
            'flash' => self::pullFlash('ok'),
        ]);
    }

    public static function createMold(array $input): void
    {
        PartEngineeringSchemaService::ensureSchema();

        $productId = (int)($input['product_id'] ?? 0);
        self::assertCanEditLeadSections(Auth::user(), $productId, 'Only the assigned lead or an admin can update tooling for this part.');
        $moldCode = trim((string)($input['mold_code'] ?? ''));
        $moldName = trim((string)($input['mold_name'] ?? ''));
        if ($productId <= 0 || $moldCode === '' || $moldName === '') {
            self::flash('err', 'Mold code and mold name are required.');
            header('Location: /products/360?id=' . $productId);
            exit;
        }

        try {
            DB::query(
                "INSERT INTO part_molds (
                    product_id, mold_code, mold_name, cavity_count, is_family_mold, tool_weight_kg,
                    mold_width_mm, mold_height_mm, mold_thickness_mm, min_clamp_ton_required,
                    min_shot_g_required, runner_type, cycle_time_sec, preferred_machine_group,
                    preferred_machine_type, status, notes
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $productId,
                    $moldCode,
                    $moldName,
                    max(1, (int)($input['cavity_count'] ?? 1)),
                    (int)((string)($input['is_family_mold'] ?? '0') === '1' ? 1 : 0),
                    self::nullableFloat($input['tool_weight_kg'] ?? null),
                    self::nullableFloat($input['mold_width_mm'] ?? null),
                    self::nullableFloat($input['mold_height_mm'] ?? null),
                    self::nullableFloat($input['mold_thickness_mm'] ?? null),
                    self::nullableFloat($input['min_clamp_ton_required'] ?? null),
                    self::nullableFloat($input['min_shot_g_required'] ?? null),
                    trim((string)($input['runner_type'] ?? '')) ?: null,
                    self::nullableFloat($input['cycle_time_sec'] ?? null),
                    trim((string)($input['preferred_machine_group'] ?? '')) ?: null,
                    trim((string)($input['preferred_machine_type'] ?? '')) ?: null,
                    trim((string)($input['status'] ?? 'active')) ?: 'active',
                    trim((string)($input['notes'] ?? '')),
                ]
            );
            self::flash('ok', 'Mold saved.');
        } catch (\Throwable $e) {
            self::flash('err', 'Unable to save mold: ' . $e->getMessage());
        }

        header('Location: /products/360?id=' . $productId);
        exit;
    }

    public static function deleteMold(int $moldId, int $productId): void
    {
        PartEngineeringSchemaService::ensureSchema();
        self::assertCanEditLeadSections(Auth::user(), $productId, 'Only the assigned lead or an admin can update tooling for this part.');
        if ($moldId > 0) {
            DB::query('DELETE FROM part_molds WHERE id=? LIMIT 1', [$moldId]);
            self::flash('ok', 'Mold removed.');
        }
        header('Location: /products/360?id=' . max(0, $productId));
        exit;
    }

    public static function createMaterialMap(array $input): void
    {
        MaterialSchemaService::ensureSchema();
        PartEngineeringSchemaService::ensureSchema();

        $productId = (int)($input['product_id'] ?? 0);
        self::assertCanEditLeadSections(Auth::user(), $productId, 'Only the assigned lead or an admin can update materials for this part.');
        $materialId = (int)($input['material_id'] ?? 0);
        $qtyPerPart = self::nullableFloat($input['qty_per_part'] ?? $input['usage_qty'] ?? null);
        if ($productId <= 0 || $materialId <= 0 || $qtyPerPart === null || $qtyPerPart <= 0) {
            self::flash('err', 'Material and quantity per part are required.');
            header('Location: /products/360?id=' . $productId);
            exit;
        }

        $material = DB::fetchOne(
            "SELECT
                *,
                COALESCE(NULLIF(material_number, ''), material_code) AS material_number,
                COALESCE(vendor_name, supplier_name) AS vendor_name,
                COALESCE(uom, unit, 'kg') AS uom
             FROM materials
             WHERE id=? LIMIT 1",
            [$materialId]
        );
        if (!$material) {
            self::flash('err', 'Selected material was not found.');
            header('Location: /products/360?id=' . $productId);
            exit;
        }

        try {
            DB::query(
                "INSERT INTO part_material_map (
                    product_id, material_id, qty_per_part, uom, usage_qty, usage_unit, yield_parts_per_kg, scrap_pct, is_primary,
                    material_code_snapshot, material_name_snapshot, material_type_snapshot,
                    vendor_name_snapshot, vendor_code_snapshot, effective_from, effective_to,
                    sequence_no, is_active, notes
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $productId,
                    $materialId,
                    $qtyPerPart,
                    trim((string)($input['uom'] ?? $input['usage_unit'] ?? (string)($material['uom'] ?? $material['unit'] ?? 'kg'))) ?: 'kg',
                    $qtyPerPart,
                    trim((string)($input['uom'] ?? $input['usage_unit'] ?? (string)($material['uom'] ?? $material['unit'] ?? 'kg'))) ?: 'kg',
                    self::nullableFloat($input['yield_parts_per_kg'] ?? null),
                    max(0.0, (float)($input['scrap_pct'] ?? 0)),
                    (int)((string)($input['is_primary'] ?? '1') === '0' ? 0 : 1),
                    (string)($material['material_number'] ?? $material['material_code'] ?? ''),
                    (string)($material['material_name'] ?? ''),
                    (string)($material['material_type'] ?? ''),
                    (string)($material['vendor_name'] ?? $material['supplier_name'] ?? ''),
                    (string)($material['supplier_ref'] ?? ''),
                    trim((string)($input['effective_from'] ?? '')) ?: null,
                    trim((string)($input['effective_to'] ?? '')) ?: null,
                    max(1, (int)($input['sequence_no'] ?? 1)),
                    (int)((string)($input['is_active'] ?? '1') === '0' ? 0 : 1),
                    trim((string)($input['notes'] ?? '')),
                ]
            );
            self::flash('ok', 'Part material mapping saved.');
        } catch (\Throwable $e) {
            self::flash('err', 'Unable to save part material mapping: ' . $e->getMessage());
        }

        header('Location: /products/360?id=' . $productId);
        exit;
    }

    public static function deleteMaterialMap(int $mapId, int $productId): void
    {
        MaterialSchemaService::ensureSchema();
        PartEngineeringSchemaService::ensureSchema();
        self::assertCanEditLeadSections(Auth::user(), $productId, 'Only the assigned lead or an admin can update materials for this part.');
        if ($mapId > 0) {
            DB::query('DELETE FROM part_material_map WHERE id=? LIMIT 1', [$mapId]);
            self::flash('ok', 'Part material mapping removed.');
        }
        header('Location: /products/360?id=' . max(0, $productId));
        exit;
    }

    public static function assignActiveMachine(array $input): void
    {
        PartEngineeringSchemaService::ensureSchema();

        $productId = (int)($input['product_id'] ?? 0);
        self::assertCanEditLeadSections(Auth::user(), $productId, 'Only the assigned lead or an admin can update machine assignment for this part.');
        $machineId = (int)($input['active_machine_id'] ?? 0);

        if ($productId <= 0) {
            self::flash('err', 'Invalid part id.');
            header('Location: /products');
            exit;
        }

        $product = DB::fetchOne('SELECT id FROM products WHERE id=? LIMIT 1', [$productId]);
        if (!$product) {
            self::flash('err', 'Part not found.');
            header('Location: /products');
            exit;
        }

        if ($machineId > 0) {
            $machine = DB::fetchOne('SELECT id, is_active FROM machines WHERE id=? LIMIT 1', [$machineId]);
            if (!$machine || (int)($machine['is_active'] ?? 0) !== 1) {
                self::flash('err', 'Selected machine is not available.');
                header('Location: /products/360?id=' . $productId);
                exit;
            }

            $payload = Part360Service::build($productId);
            $compatibleMachines = is_array($payload['machine_assignment']['compatible_machines'] ?? null)
                ? $payload['machine_assignment']['compatible_machines']
                : [];
            $compatibleIds = array_map(static fn(array $row): int => (int)($row['machine_id'] ?? 0), $compatibleMachines);
            if ($compatibleIds === []) {
                self::flash('err', 'No compatible machines are available for this part yet.');
                header('Location: /products/360?id=' . $productId);
                exit;
            }
            if (!in_array($machineId, $compatibleIds, true)) {
                self::flash('err', 'Selected machine is not compatible with the current mold and material profile.');
                header('Location: /products/360?id=' . $productId);
                exit;
            }
        }

        DB::query('UPDATE products SET active_machine_id=?, updated_at=NOW() WHERE id=? LIMIT 1', [
            $machineId > 0 ? $machineId : null,
            $productId,
        ]);

        self::flash('ok', $machineId > 0 ? 'Active machine updated.' : 'Active machine cleared.');
        header('Location: /products/360?id=' . $productId);
        exit;
    }

    public static function assignResponsibleUser(array $input): void
    {
        $actor = Auth::user();
        $productId = (int)($input['product_id'] ?? 0);
        if (!self::canEditLeadSections($actor, $productId)) {
            self::flash('err', 'Only the assigned lead or an admin can update lead ownership for this part.');
            header('Location: /products/360?id=' . (int)($input['product_id'] ?? 0));
            exit;
        }

        $userId = (int)($input['responsible_user_id'] ?? 0);
        if ($productId <= 0) {
            self::flash('err', 'Invalid part id.');
            header('Location: /products');
            exit;
        }

        if (!DB::fetchOne('SELECT id FROM products WHERE id=? LIMIT 1', [$productId])) {
            self::flash('err', 'Part not found.');
            header('Location: /products');
            exit;
        }

        if ($userId > 0) {
            $targetUser = DB::fetchOne(
                "SELECT
                    u.id,
                    COALESCE(NULLIF(TRIM(u.account_status), ''), 'active') AS account_status,
                    COALESCE(NULLIF(TRIM(u.authority_role), ''), '') AS authority_role,
                    COALESCE(a.assigned_apps, '') AS assigned_apps
                 FROM users u
                 LEFT JOIN user_dashboard_assignments a ON a.user_id = u.id
                 WHERE u.id = ?
                 LIMIT 1",
                [$userId]
            );
            if (!$targetUser || (string)($targetUser['account_status'] ?? 'active') !== 'active') {
                self::flash('err', 'Selected user is not active.');
                header('Location: /products/360?id=' . $productId);
                exit;
            }

            $authorityRole = strtolower(trim((string)($targetUser['authority_role'] ?? '')));
            $assignedApps = array_values(array_filter(array_map('trim', explode(',', (string)($targetUser['assigned_apps'] ?? '')))));
            if (!in_array($authorityRole, ['platform_admin', 'app_admin'], true) && !in_array('manufacturing', $assignedApps, true)) {
                self::flash('err', 'Selected user does not have manufacturing access.');
                header('Location: /products/360?id=' . $productId);
                exit;
            }
        }

        DB::query('DELETE FROM user_operational_scopes WHERE part_id=?', [$productId]);
        if ($userId > 0) {
            DB::query(
                'INSERT INTO user_operational_scopes (user_id, part_id, updated_by) VALUES (?, ?, ?)',
                [$userId, $productId, (string)($actor['email'] ?? $actor['id'] ?? 'system')]
            );
        }

        self::flash('ok', $userId > 0 ? 'Responsible user updated.' : 'Responsible user cleared.');
        header('Location: /products/360?id=' . $productId);
        exit;
    }

    public static function update(array $input): void
    {
        $id = (int)($input['id'] ?? 0);
        self::assertCanEditLeadSections(Auth::user(), $id, 'Only the assigned lead or an admin can edit this part.');
        $data = self::sanitize($input);

        if ($id <= 0 || $data['parts_name'] === '' || $data['parts_number'] === '') {
            self::flash('err', 'Invalid edit payload.');
            header('Location: /products');
            exit;
        }

        try {
            $sets = [
                'parts_name=?' => $data['parts_name'],
                'parts_number=?' => $data['parts_number'],
                'model=?' => $data['model'],
                'producer=?' => $data['producer'],
                '`lead`=?' => $data['lead'],
                'cycle_time=?' => $data['cycle_time'],
                'notes=?' => $data['notes'],
                'is_active=?' => $data['is_active'],
            ];

            self::appendSupplyUpdateSets($sets, $data);

            $setSql = implode(', ', array_keys($sets));
            $params = array_values($sets);
            $params[] = $id;

            DB::query(
                "UPDATE products SET {$setSql}, updated_at=NOW() WHERE id=?",
                $params
            );
            self::flash('ok', 'Part updated.');
        } catch (\Throwable $e) {
            self::flash('err', 'Update failed: ' . $e->getMessage());
            header('Location: /products/edit?id=' . $id);
            exit;
        }
    }

    public static function delete(int $id): void
    {
        self::assertCanManageLifecycle(Auth::user(), $id, 'Only admins can permanently delete a part.');
        if ($id <= 0) {
            self::flash('err', 'Invalid part id.');
            return;
        }

        DB::query('DELETE FROM products WHERE id=? LIMIT 1', [$id]);
        self::flash('ok', 'Part deleted.');
    }

    public static function setActiveState(int $id, bool $isActive): void
    {
        self::assertCanManageLifecycle(Auth::user(), $id, 'Only admins can change part lifecycle state.');
        if ($id <= 0) {
            self::flash('err', 'Invalid part id.');
            header('Location: /products');
            exit;
        }

        if (!DB::fetchOne('SELECT id FROM products WHERE id=? LIMIT 1', [$id])) {
            self::flash('err', 'Part not found.');
            header('Location: /products');
            exit;
        }

        DB::query('UPDATE products SET is_active=?, updated_at=NOW() WHERE id=? LIMIT 1', [$isActive ? 1 : 0, $id]);
        self::flash('ok', $isActive ? 'Part activated.' : 'Part deactivated.');
        header('Location: /products/360?id=' . $id);
        exit;
    }

    public static function importForm(View $view): void
    {
        $view->render('Products::import.php', [
            'pageTitle' => 'Import Parts CSV',
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
        ]);
    }

    public static function importCsv($file): void
    {
        if (!is_array($file) || (int)($file['error'] ?? 1) !== 0) {
            self::flash('err', 'CSV upload failed.');
            return;
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp)) {
            self::flash('err', 'Invalid uploaded file.');
            return;
        }

        $fh = fopen($tmp, 'rb');
        if (!$fh) {
            self::flash('err', 'Unable to open CSV file.');
            return;
        }

        $rowNo = 0;
        $inserted = 0;
        $skipped = 0;

        while (($row = fgetcsv($fh)) !== false) {
            $rowNo++;
            if ($rowNo === 1 && self::looksLikeHeader($row)) {
                continue;
            }

            $partsName = trim((string)($row[0] ?? ''));
            $partsNumber = trim((string)($row[1] ?? ''));
            $model = trim((string)($row[2] ?? ''));
            $producer = trim((string)($row[3] ?? ''));
            $lead = trim((string)($row[4] ?? ''));
            $rawCycle = '';
            $supplyMode = SupplyModel::SUPPLY_IN_HOUSE;
            $fulfillmentMode = 'company_to_destination';
            $requiresIpmQc = 1;
            $defaultSupplier = '';
            $stockedAtIpm = 1;
            $defaultProcurementLeadDays = null;
            $defaultSupplyNote = '';

            if (count($row) >= 8) {
                $rawCycle = trim((string)($row[5] ?? ''));
                $notes = trim((string)($row[6] ?? ''));
                $isActive = (int)((string)($row[7] ?? '1') === '0' ? 0 : 1);

                if (count($row) >= 9) {
                    $supplyMode = SupplyModel::normalizeSupplyMode((string)($row[8] ?? ''));
                }
                if (count($row) >= 10) {
                    $fulfillmentMode = PartExecutionRouteResolver::normalizeFulfillmentMode((string)($row[9] ?? ''));
                }
                if (count($row) >= 11) {
                    $requiresIpmQc = SupplyModel::normalizeRequiresIpmQc($row[10] ?? null) ? 1 : 0;
                }
                if (count($row) >= 12) {
                    $defaultSupplier = trim((string)($row[11] ?? ''));
                }
                if (count($row) >= 13) {
                    $stockedAtIpm = SupplyModel::normalizeRequiresIpmQc($row[12] ?? null) ? 1 : 0;
                }
                if (count($row) >= 14) {
                    $candidateLeadDays = trim((string)($row[13] ?? ''));
                    $defaultProcurementLeadDays = ($candidateLeadDays !== '' && is_numeric($candidateLeadDays)) ? max(0, (float)$candidateLeadDays) : null;
                }
                if (count($row) >= 15) {
                    $defaultSupplyNote = trim((string)($row[14] ?? ''));
                }
            } else {
                $notes = trim((string)($row[5] ?? ''));
                $isActive = (int)((string)($row[6] ?? '1') === '0' ? 0 : 1);
            }
            $cycleTime = ($rawCycle !== '' && is_numeric($rawCycle)) ? max(0, (float)$rawCycle) : null;

            if ($partsName === '' || $partsNumber === '') {
                $skipped++;
                continue;
            }

            try {
                $columns = [
                    'parts_name' => $partsName,
                    'parts_number' => $partsNumber,
                    'model' => $model,
                    'producer' => $producer,
                    'lead' => $lead,
                    'cycle_time' => $cycleTime,
                    'notes' => $notes,
                    'is_active' => $isActive,
                ];

                self::appendSupplyColumns($columns, [
                    'supply_mode' => $supplyMode,
                    'fulfillment_mode' => $fulfillmentMode,
                    'requires_ipm_qc' => $requiresIpmQc,
                    'default_supplier' => $defaultSupplier,
                    'stocked_at_ipm' => $stockedAtIpm,
                    'default_procurement_lead_days' => $defaultProcurementLeadDays,
                    'default_supply_note' => $defaultSupplyNote,
                ]);

                $insertFields = array_keys($columns);
                $insertSqlFields = array_map(
                    static fn(string $name): string => $name === 'lead' ? '`lead`' : $name,
                    $insertFields
                );
                $insertSql = implode(', ', $insertSqlFields);
                $insertPlaceholders = implode(', ', array_fill(0, count($insertFields), '?'));

                $upserts = ['parts_name', 'model', 'producer', 'lead', 'cycle_time', 'notes', 'is_active'];
                if (isset($columns['supply_mode'])) {
                    $upserts[] = 'supply_mode';
                }
                if (isset($columns['fulfillment_mode'])) {
                    $upserts[] = 'fulfillment_mode';
                }
                if (isset($columns['requires_ipm_qc'])) {
                    $upserts[] = 'requires_ipm_qc';
                }
                if (isset($columns['default_supplier'])) {
                    $upserts[] = 'default_supplier';
                }
                if (isset($columns['stocked_at_ipm'])) {
                    $upserts[] = 'stocked_at_ipm';
                }
                if (isset($columns['default_procurement_lead_days'])) {
                    $upserts[] = 'default_procurement_lead_days';
                }
                if (isset($columns['default_supply_note'])) {
                    $upserts[] = 'default_supply_note';
                }

                $updateExpr = [];
                foreach ($upserts as $upsertField) {
                    $dbField = $upsertField === 'lead' ? '`lead`' : $upsertField;
                    $updateExpr[] = $dbField . '=VALUES(' . $dbField . ')';
                }

                DB::query(
                    "INSERT INTO products ({$insertSql})
                     VALUES ({$insertPlaceholders})
                     ON DUPLICATE KEY UPDATE
                     " . implode(",\n                     ", $updateExpr) . ",
                     updated_at=NOW()",
                    array_values($columns)
                );
                $inserted++;
            } catch (\Throwable $e) {
                $skipped++;
            }
        }

        fclose($fh);
        self::flash('ok', "Import complete: {$inserted} upserted, {$skipped} skipped.");
    }

    private static function sanitize(array $input): array
    {
        return [
            'parts_name' => trim((string)($input['parts_name'] ?? '')),
            'parts_number' => trim((string)($input['parts_number'] ?? '')),
            'model' => trim((string)($input['model'] ?? '')),
            'producer' => trim((string)($input['producer'] ?? '')),
            'lead' => trim((string)($input['lead'] ?? '')),
            'cycle_time' => ((string)($input['cycle_time'] ?? '')) !== '' && is_numeric((string)$input['cycle_time'])
                ? max(0, (float)$input['cycle_time'])
                : null,
                        'qc_time_per_item' => ((string)($input['qc_time_per_item'] ?? '')) !== '' && is_numeric((string)$input['qc_time_per_item'])
                            ? max(0, (float)$input['qc_time_per_item'])
                            : null,
            'notes' => trim((string)($input['notes'] ?? '')),
            'is_active' => (int)((string)($input['is_active'] ?? '1') === '0' ? 0 : 1),
            'supply_mode' => SupplyModel::normalizeSupplyMode((string)($input['supply_mode'] ?? SupplyModel::SUPPLY_IN_HOUSE)),
            'fulfillment_mode' => PartExecutionRouteResolver::normalizeFulfillmentMode((string)($input['fulfillment_mode'] ?? 'company_to_destination')),
            'requires_ipm_qc' => SupplyModel::normalizeRequiresIpmQc($input['requires_ipm_qc'] ?? null) ? 1 : 0,
            'default_supplier' => trim((string)($input['default_supplier'] ?? '')),
            'stocked_at_ipm' => SupplyModel::normalizeRequiresIpmQc($input['stocked_at_ipm'] ?? null) ? 1 : 0,
            'default_procurement_lead_days' => ((string)($input['default_procurement_lead_days'] ?? '')) !== '' && is_numeric((string)$input['default_procurement_lead_days'])
                ? max(0, (float)$input['default_procurement_lead_days'])
                : null,
            'default_supply_note' => trim((string)($input['default_supply_note'] ?? '')),
            'requires_assembly' => (int)((string)($input['requires_assembly'] ?? '0') === '0' ? 0 : 1),
            'requires_processing' => PartExecutionRouteResolver::normalizeRequiresProcessing($input['requires_processing'] ?? null, (string)($input['fulfillment_mode'] ?? '')) ? 1 : 0,
            'dispatch_mode' => PartExecutionRouteResolver::normalizeDispatchMode((string)($input['dispatch_mode'] ?? '')),
            'dispatch_as_is' => (int)((string)($input['dispatch_as_is'] ?? '0') === '0' ? 0 : 1),
            'essential_stock_qty' => ((string)($input['essential_stock_qty'] ?? '')) !== '' && is_numeric((string)$input['essential_stock_qty'])
                ? max(0, (float)$input['essential_stock_qty'])
                : null,
            'planning_window_days' => ((string)($input['planning_window_days'] ?? '')) !== '' && is_numeric((string)$input['planning_window_days'])
                ? max(0, (int)$input['planning_window_days'])
                : null,
            'safety_stock_qty' => ((string)($input['safety_stock_qty'] ?? $input['max_buffer_qty'] ?? '')) !== '' && is_numeric((string)($input['safety_stock_qty'] ?? $input['max_buffer_qty'] ?? ''))
                ? max(0, (float)($input['safety_stock_qty'] ?? $input['max_buffer_qty']))
                : null,
            'qty_per_case' => ((string)($input['qty_per_case'] ?? '')) !== '' && is_numeric((string)$input['qty_per_case'])
                ? max(0, (int)$input['qty_per_case'])
                : null,
            'case_spec' => trim((string)($input['case_spec'] ?? '')),
            'case_type' => trim((string)($input['case_type'] ?? '')),
            'default_case_number' => trim((string)($input['default_case_number'] ?? '')),
            'cases_per_pallet' => ((string)($input['cases_per_pallet'] ?? '')) !== '' && is_numeric((string)$input['cases_per_pallet'])
                ? max(0, (int)$input['cases_per_pallet'])
                : null,
        ];
    }

    /**
     * @param array<string,mixed> $columns
     * @param array<string,mixed> $data
     */
    private static function appendSupplyColumns(array &$columns, array $data): void
    {
        if (self::hasColumn('supply_mode')) {
            $columns['supply_mode'] = SupplyModel::normalizeSupplyMode((string)($data['supply_mode'] ?? ''));
        }
        if (self::hasColumn('fulfillment_mode')) {
            $columns['fulfillment_mode'] = PartExecutionRouteResolver::normalizeFulfillmentMode((string)($data['fulfillment_mode'] ?? ''));
        }
        if (self::hasColumn('requires_ipm_qc')) {
            $columns['requires_ipm_qc'] = SupplyModel::normalizeRequiresIpmQc($data['requires_ipm_qc'] ?? null) ? 1 : 0;
        }
        if (self::hasColumn('default_supplier')) {
            $columns['default_supplier'] = trim((string)($data['default_supplier'] ?? ''));
        }
        if (self::hasColumn('stocked_at_ipm')) {
            $columns['stocked_at_ipm'] = SupplyModel::normalizeRequiresIpmQc($data['stocked_at_ipm'] ?? null) ? 1 : 0;
        }
        if (self::hasColumn('default_procurement_lead_days')) {
            $columns['default_procurement_lead_days'] = ((string)($data['default_procurement_lead_days'] ?? '')) !== '' && is_numeric((string)$data['default_procurement_lead_days'])
                ? max(0, (float)$data['default_procurement_lead_days'])
                : null;
        }
        if (self::hasColumn('default_supply_note')) {
            $columns['default_supply_note'] = trim((string)($data['default_supply_note'] ?? ''));
        }
        if (self::hasColumn('requires_assembly')) {
            $columns['requires_assembly'] = (int)((string)($data['requires_assembly'] ?? '0') === '0' ? 0 : 1);
        }
        if (self::hasColumn('requires_processing')) {
            $columns['requires_processing'] = PartExecutionRouteResolver::normalizeRequiresProcessing($data['requires_processing'] ?? null, (string)($data['fulfillment_mode'] ?? '')) ? 1 : 0;
        }
        if (self::hasColumn('dispatch_mode')) {
            $dispatchMode = PartExecutionRouteResolver::normalizeDispatchMode((string)($data['dispatch_mode'] ?? ''));
            $columns['dispatch_mode'] = $dispatchMode !== '' ? $dispatchMode : null;
        }
        if (self::hasColumn('dispatch_as_is')) {
            $columns['dispatch_as_is'] = (int)((string)($data['dispatch_as_is'] ?? '0') === '0' ? 0 : 1);
        }
        if (self::hasColumn('essential_stock_qty')) {
            $columns['essential_stock_qty'] = ((string)($data['essential_stock_qty'] ?? '')) !== '' && is_numeric((string)$data['essential_stock_qty'])
                ? max(0, (float)$data['essential_stock_qty'])
                : null;
        }
        if (self::hasColumn('planning_window_days')) {
            $columns['planning_window_days'] = ((string)($data['planning_window_days'] ?? '')) !== '' && is_numeric((string)$data['planning_window_days'])
                ? max(0, (int)$data['planning_window_days'])
                : null;
        }
        $safetyStockValue = ((string)($data['safety_stock_qty'] ?? '')) !== '' && is_numeric((string)$data['safety_stock_qty'])
            ? max(0, (float)$data['safety_stock_qty'])
            : null;
        if (self::hasColumn('safety_stock_qty')) {
            $columns['safety_stock_qty'] = $safetyStockValue;
        }
        if (self::hasColumn('max_buffer_qty')) {
            $columns['max_buffer_qty'] = $safetyStockValue;
        }
        if (self::hasColumn('qty_per_case')) {
            $columns['qty_per_case'] = ((string)($data['qty_per_case'] ?? '')) !== '' && is_numeric((string)$data['qty_per_case'])
                ? max(0, (int)$data['qty_per_case'])
                : null;
        }
        if (self::hasColumn('case_spec')) {
            $columns['case_spec'] = trim((string)($data['case_spec'] ?? ''));
        }
        if (self::hasColumn('case_type')) {
            $columns['case_type'] = trim((string)($data['case_type'] ?? ''));
        }
        if (self::hasColumn('default_case_number')) {
            $columns['default_case_number'] = trim((string)($data['default_case_number'] ?? ''));
        }
        if (self::hasColumn('cases_per_pallet')) {
            $columns['cases_per_pallet'] = ((string)($data['cases_per_pallet'] ?? '')) !== '' && is_numeric((string)$data['cases_per_pallet'])
                ? max(0, (int)$data['cases_per_pallet'])
                : null;
        }
    }

    /**
     * @param array<string,mixed> $sets
     * @param array<string,mixed> $data
     */
    private static function appendSupplyUpdateSets(array &$sets, array $data): void
    {
        if (self::hasColumn('supply_mode')) {
            $sets['supply_mode=?'] = SupplyModel::normalizeSupplyMode((string)($data['supply_mode'] ?? ''));
        }
        if (self::hasColumn('fulfillment_mode')) {
            $sets['fulfillment_mode=?'] = PartExecutionRouteResolver::normalizeFulfillmentMode((string)($data['fulfillment_mode'] ?? ''));
        }
        if (self::hasColumn('requires_ipm_qc')) {
            $sets['requires_ipm_qc=?'] = SupplyModel::normalizeRequiresIpmQc($data['requires_ipm_qc'] ?? null) ? 1 : 0;
        }
        if (self::hasColumn('default_supplier')) {
            $sets['default_supplier=?'] = trim((string)($data['default_supplier'] ?? ''));
        }
        if (self::hasColumn('stocked_at_ipm')) {
            $sets['stocked_at_ipm=?'] = SupplyModel::normalizeRequiresIpmQc($data['stocked_at_ipm'] ?? null) ? 1 : 0;
        }
        if (self::hasColumn('default_procurement_lead_days')) {
            $sets['default_procurement_lead_days=?'] = ((string)($data['default_procurement_lead_days'] ?? '')) !== '' && is_numeric((string)$data['default_procurement_lead_days'])
                ? max(0, (float)$data['default_procurement_lead_days'])
                : null;
        }
        if (self::hasColumn('default_supply_note')) {
            $sets['default_supply_note=?'] = trim((string)($data['default_supply_note'] ?? ''));
        }
        if (self::hasColumn('requires_assembly')) {
            $sets['requires_assembly=?'] = (int)((string)($data['requires_assembly'] ?? '0') === '0' ? 0 : 1);
        }
        if (self::hasColumn('requires_processing')) {
            $sets['requires_processing=?'] = PartExecutionRouteResolver::normalizeRequiresProcessing($data['requires_processing'] ?? null, (string)($data['fulfillment_mode'] ?? '')) ? 1 : 0;
        }
        if (self::hasColumn('dispatch_mode')) {
            $dispatchMode = PartExecutionRouteResolver::normalizeDispatchMode((string)($data['dispatch_mode'] ?? ''));
            $sets['dispatch_mode=?'] = $dispatchMode !== '' ? $dispatchMode : null;
        }
        if (self::hasColumn('dispatch_as_is')) {
            $sets['dispatch_as_is=?'] = (int)((string)($data['dispatch_as_is'] ?? '0') === '0' ? 0 : 1);
        }
        if (self::hasColumn('essential_stock_qty')) {
            $sets['essential_stock_qty=?'] = ((string)($data['essential_stock_qty'] ?? '')) !== '' && is_numeric((string)$data['essential_stock_qty'])
                ? max(0, (float)$data['essential_stock_qty'])
                : null;
        }
        if (self::hasColumn('planning_window_days')) {
            $sets['planning_window_days=?'] = ((string)($data['planning_window_days'] ?? '')) !== '' && is_numeric((string)$data['planning_window_days'])
                ? max(0, (int)$data['planning_window_days'])
                : null;
        }
        $safetyStockValue = ((string)($data['safety_stock_qty'] ?? '')) !== '' && is_numeric((string)$data['safety_stock_qty'])
            ? max(0, (float)$data['safety_stock_qty'])
            : null;
        if (self::hasColumn('safety_stock_qty')) {
            $sets['safety_stock_qty=?'] = $safetyStockValue;
        }
        if (self::hasColumn('max_buffer_qty')) {
            $sets['max_buffer_qty=?'] = $safetyStockValue;
        }
        if (self::hasColumn('qty_per_case')) {
            $sets['qty_per_case=?'] = ((string)($data['qty_per_case'] ?? '')) !== '' && is_numeric((string)$data['qty_per_case'])
                ? max(0, (int)$data['qty_per_case'])
                : null;
        }
        if (self::hasColumn('case_spec')) {
            $sets['case_spec=?'] = trim((string)($data['case_spec'] ?? ''));
        }
        if (self::hasColumn('case_type')) {
            $sets['case_type=?'] = trim((string)($data['case_type'] ?? ''));
        }
        if (self::hasColumn('default_case_number')) {
            $sets['default_case_number=?'] = trim((string)($data['default_case_number'] ?? ''));
        }
        if (self::hasColumn('cases_per_pallet')) {
            $sets['cases_per_pallet=?'] = ((string)($data['cases_per_pallet'] ?? '')) !== '' && is_numeric((string)$data['cases_per_pallet'])
                ? max(0, (int)$data['cases_per_pallet'])
                : null;
        }
    }

    /**
     * @return array<string,bool>
     */
    private static function columnMap(): array
    {
        static $map = null;
        if (is_array($map)) {
            return $map;
        }

        $map = [];
        foreach (DB::fetchAll('SHOW COLUMNS FROM products') as $col) {
            $name = (string)($col['Field'] ?? '');
            if ($name !== '') {
                $map[$name] = true;
            }
        }
        return $map;
    }

    private static function hasColumn(string $name): bool
    {
        return isset(self::columnMap()[$name]);
    }

    /**
     * @return array<string,string>
     */
    private static function supplyModeOptions(): array
    {
        return [
            SupplyModel::SUPPLY_IN_HOUSE => 'In-house',
            SupplyModel::SUPPLY_THIRD_PARTY => 'Third-party',
            SupplyModel::SUPPLY_HYBRID => 'Hybrid',
        ];
    }

    /**
     * @return array<string,string>
     */
    private static function fulfillmentModeOptions(): array
    {
        return [
            'company_to_destination' => 'Company handles delivery to destination',
            'third_party_to_company_to_destination' => 'Supplier to company warehouse, then to destination',
            'third_party_to_destination' => 'Supplier delivers directly to destination',
        ];
    }

    /**
     * @return array<string,string>
     */
    private static function dispatchModeOptions(): array
    {
        return [
            '' => 'Auto (derive from fulfillment mode)',
            'internal' => 'Internal',
            'direct_supplier' => 'Direct Supplier',
            'hybrid' => 'Hybrid',
        ];
    }

    private static function looksLikeHeader(array $row): bool
    {
        $first = strtolower(trim((string)($row[0] ?? '')));
        $second = strtolower(trim((string)($row[1] ?? '')));
        return str_contains($first, 'parts') || str_contains($second, 'number');
    }

    private static function flash(string $key, string $msg): void
    {
        $_SESSION['products_flash_' . $key] = $msg;
    }

    private static function nullableFloat(mixed $value): ?float
    {
        $raw = trim((string)$value);
        if ($raw === '' || !is_numeric($raw)) {
            return null;
        }

        return (float)$raw;
    }

    private static function pullFlash(string $key): string
    {
        $k = 'products_flash_' . $key;
        $v = (string)($_SESSION[$k] ?? '');
        unset($_SESSION[$k]);
        return $v;
    }

    private static function hasLedgerTable(): bool
    {
        return DB::fetchOne("SHOW TABLES LIKE 'stock_ledger_entries'") !== null;
    }

    private static function canManageResponsibility(?array $user): bool
    {
        if (!$user) {
            return false;
        }

        $authorityRole = strtolower(trim((string)($user['authority_role'] ?? '')));
        $role = strtolower(trim((string)($user['role'] ?? '')));
        return in_array($authorityRole, ['platform_admin', 'app_admin'], true)
            || in_array($role, ['admin', 'platform_admin', 'app_admin', 'accountadmin'], true);
    }

    private static function canManageLifecycle(?array $user): bool
    {
        return self::canManageResponsibility($user);
    }

    private static function canEditLeadSections(?array $user, int $productId): bool
    {
        if ($productId <= 0 || !$user) {
            return false;
        }

        if (self::canManageLifecycle($user)) {
            return true;
        }

        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }

        $assigned = DB::fetchOne(
            "SELECT user_id
             FROM user_operational_scopes
             WHERE part_id = ?
               AND is_active = 1
             ORDER BY id DESC
             LIMIT 1",
            [$productId]
        );

        return (int)($assigned['user_id'] ?? 0) === $userId;
    }

    private static function assertCanEditLeadSections(?array $user, int $productId, string $message): void
    {
        if ($productId > 0 && self::canEditLeadSections($user, $productId)) {
            return;
        }

        self::flash('err', $message);
        header('Location: ' . ($productId > 0 ? '/apps/manufacturing/products/360?id=' . $productId : '/products'));
        exit;
    }

    private static function assertCanManageLifecycle(?array $user, int $productId, string $message): void
    {
        if (self::canManageLifecycle($user)) {
            return;
        }

        self::flash('err', $message);
        header('Location: ' . ($productId > 0 ? '/apps/manufacturing/products/360?id=' . $productId : '/products'));
        exit;
    }
}
