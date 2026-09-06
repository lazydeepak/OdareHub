<?php
declare(strict_types=1);

namespace Plugins\ProductionEntries\Controllers;

use App\Core\AuditLogService;
use App\Core\Auth;
use App\Core\DB;
use App\Core\EntityContext;
use App\Core\HandoffEngine;
use App\Core\View;
use Plugins\Base\Services\HandoffTrackingService;
use Plugins\Coverage\Services\CoverageService;
use Plugins\ProductionEntries\ProductionEntryService;

require_once APP_ROOT . '/plugins/Base/Services/HandoffTrackingService.php';

final class ProductionEntriesController
{
    public static function index(View $view): void
    {
        $productionDate = trim((string)($_GET['production_date'] ?? ''));
        $machineId = (int)($_GET['machine_id'] ?? 0);
        $productId = (int)($_GET['product_id'] ?? 0);
        $status = trim((string)($_GET['status'] ?? ''));

        $sql = "SELECT pe.*, m.machine_no, m.machine_name, p.parts_name, p.parts_number
                FROM production_entries pe
                INNER JOIN machines m ON m.id = pe.machine_id
                INNER JOIN products p ON p.id = pe.product_id
                WHERE 1=1";
        $params = [];

        if ($productionDate !== '') {
            $sql .= ' AND pe.production_date = ?';
            $params[] = $productionDate;
        }
        if ($machineId > 0) {
            $sql .= ' AND pe.machine_id = ?';
            $params[] = $machineId;
        }
        if ($productId > 0) {
            $sql .= ' AND pe.product_id = ?';
            $params[] = $productId;
        }
        if ($status !== '') {
            $sql .= ' AND pe.status = ?';
            $params[] = $status;
        }

        $sql .= ' ORDER BY pe.production_date DESC, pe.id DESC LIMIT 400';
        AuditLogService::ensureSchema();
        $rows = DB::fetchAll($sql, $params);
        $auditSummary = AuditLogService::latestByEntityIds('production_entry', array_map(static fn(array $row): int => (int)($row['id'] ?? 0), $rows));

        $view->render('ProductionEntries::index.php', [
            'pageTitle' => 'Production Entries',
            'rows' => $rows,
            'audit_summary' => $auditSummary,
            'machines' => self::machines(),
            'products' => self::products(),
            'production_date' => $productionDate,
            'machine_id' => $machineId,
            'product_id' => $productId,
            'status' => $status,
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
        ]);
    }

    public static function addForm(View $view): void
    {
        $old = $_SESSION['production_entries_old'] ?? [];
        if ((int)($old['product_id'] ?? 0) <= 0 && (int)($_GET['product_id'] ?? 0) > 0) {
            $old['product_id'] = (int)$_GET['product_id'];
        }
        if ((int)($old['machine_id'] ?? 0) <= 0 && (int)($_GET['machine_id'] ?? 0) > 0) {
            $old['machine_id'] = (int)$_GET['machine_id'];
        }
        if (($old['production_date'] ?? '') === '') {
            $old['production_date'] = trim((string)($_GET['production_date'] ?? '')) ?: date('Y-m-d');
        }

        $quickUpdate = ((string)($_GET['quick_update'] ?? '') === '1');
        $redirect = self::safeRedirect((string)($_GET['redirect'] ?? (string)($old['redirect'] ?? '')));
        if ($redirect !== '') {
            $old['redirect'] = $redirect;
        }

        $scopeOptions = self::operatorScope($old);
        if ((int)($old['machine_id'] ?? 0) <= 0 && count($scopeOptions['machines']) === 1) {
            $old['machine_id'] = (int)($scopeOptions['machines'][0]['id'] ?? 0);
        }
        $view->render('ProductionEntries::add.php', [
            'pageTitle' => 'Add Production Entry',
            'machines' => $scopeOptions['machines'],
            'products' => $scopeOptions['products'],
            'old' => $old,
            'error' => self::pullFlash('err'),
            'quick_update' => $quickUpdate,
            'redirect' => $redirect,
        ]);
        unset($_SESSION['production_entries_old']);
    }

    public static function create(array $input): void
    {
        AuditLogService::ensureSchema();
        $data = self::sanitize($input);
        $_SESSION['production_entries_old'] = $data;
        $redirect = self::safeRedirect((string)($data['redirect'] ?? ''));

        if ($data['production_date'] === '' || $data['machine_id'] <= 0 || $data['product_id'] <= 0 || $data['produced_qty'] < 0 || $data['rejected_qty'] < 0) {
            self::flash('err', 'Date, machine, product, and non-negative quantities are required.');
            header('Location: /production-entries/add' . ($redirect !== '' ? '?redirect=' . urlencode($redirect) : ''));
            exit;
        }

        $goodQty = max(0, $data['produced_qty'] - $data['rejected_qty']);

        try {
            $context = EntityContext::fromUser(Auth::user());
            $insertData = [
                'production_date' => $data['production_date'],
                'shift' => $data['shift'],
                'machine_id' => $data['machine_id'],
                'product_id' => $data['product_id'],
                'produced_qty' => $data['produced_qty'],
                'rejected_qty' => $data['rejected_qty'],
                'good_qty' => $goodQty,
                'status' => $data['status'],
                'notes' => $data['notes'],
            ];
            $entryId = ProductionEntryService::create($insertData, $context);

            $db = DB::conn();
            $db->begin_transaction();
            try {
                self::syncLedgerForEntry($entryId, $data['product_id'], $goodQty, $data['notes']);
                $db->commit();
            } catch (\Throwable $e) {
                $db->rollback();
                throw $e;
            }
        } catch (\Throwable $e) {
            self::flash('err', 'Create failed: ' . $e->getMessage());
            header('Location: /production-entries/add' . ($redirect !== '' ? '?redirect=' . urlencode($redirect) : ''));
            exit;
        }

        self::recalculateCoverage([$data['product_id']]);
        HandoffTrackingService::syncProductionEntry((int)$entryId);

        $created = DB::fetchOne('SELECT * FROM production_entries WHERE id=? LIMIT 1', [(int)$entryId]) ?: [];
        AuditLogService::logEvent(
            'production_entry',
            (int)$entryId,
            AuditLogService::EVENT_LIFECYCLE,
            AuditLogService::ACTION_CREATED,
            Auth::user(),
            [
                'app' => 'manufacturing',
                'module' => 'production',
                'new_state' => (string)($created['status'] ?? ''),
                'note' => 'Production entry created.',
                'diff' => AuditLogService::diffImportantFields([], $created, self::auditDiffFields()),
            ]
        );
        AuditLogService::logEvent(
            'production_entry',
            (int)$entryId,
            AuditLogService::EVENT_SYSTEM,
            AuditLogService::ACTION_PRODUCTION_OUTPUT_SUBMITTED,
            Auth::user(),
            [
                'app' => 'manufacturing',
                'module' => 'production',
                'new_state' => (string)($created['status'] ?? ''),
                'note' => 'Production output submitted.',
                'metadata' => [
                    'produced_qty' => (float)($created['produced_qty'] ?? 0),
                    'rejected_qty' => (float)($created['rejected_qty'] ?? 0),
                    'good_qty' => (float)($created['good_qty'] ?? 0),
                ],
            ]
        );

        unset($_SESSION['production_entries_old']);
        self::flash('ok', self::hasLedgerTable() ? 'Production entry created and posted to ledger.' : 'Production entry created.');
        header('Location: ' . ($redirect !== '' ? $redirect : '/production-entries'));
        exit;
    }

    public static function editForm(View $view, int $id): void
    {
        AuditLogService::ensureSchema();
        HandoffTrackingService::syncProductionEntry($id);
        if ($id <= 0) {
            self::flash('err', 'Invalid production entry id.');
            header('Location: /production-entries');
            exit;
        }

        $row = DB::fetchOne('SELECT * FROM production_entries WHERE id=? LIMIT 1', [$id]);
        if (!$row) {
            self::flash('err', 'Production entry not found.');
            header('Location: /production-entries');
            exit;
        }

        $view->render('ProductionEntries::edit.php', [
            'pageTitle' => 'Edit Production Entry',
            'row' => $row,
            'machines' => self::machines(),
            'products' => self::products(),
            'ownership_summary' => HandoffEngine::summaryForEntity('production_entry', $id),
            'activity_timeline' => AuditLogService::timeline('production_entry', $id, 80),
            'error' => self::pullFlash('err'),
        ]);
    }

    public static function update(array $input): void
    {
        AuditLogService::ensureSchema();
        $id = (int)($input['id'] ?? 0);
        $data = self::sanitize($input);

        if ($id <= 0 || $data['production_date'] === '' || $data['machine_id'] <= 0 || $data['product_id'] <= 0 || $data['produced_qty'] < 0 || $data['rejected_qty'] < 0) {
            self::flash('err', 'Invalid payload.');
            header('Location: /production-entries');
            exit;
        }

        $goodQty = max(0, $data['produced_qty'] - $data['rejected_qty']);

        $existing = DB::fetchOne('SELECT * FROM production_entries WHERE id=? LIMIT 1', [$id]);
        if (!$existing) {
            self::flash('err', 'Production entry not found.');
            header('Location: /production-entries');
            exit;
        }

        $oldProductId = (int)($existing['product_id'] ?? 0);

        try {
            $context = EntityContext::fromUser(Auth::user());
            $updateData = [
                'production_date' => $data['production_date'],
                'shift' => $data['shift'],
                'machine_id' => $data['machine_id'],
                'product_id' => $data['product_id'],
                'produced_qty' => $data['produced_qty'],
                'rejected_qty' => $data['rejected_qty'],
                'good_qty' => $goodQty,
                'status' => $data['status'],
                'notes' => $data['notes'],
            ];
            ProductionEntryService::update($id, $updateData, $context, $existing);

            $db = DB::conn();
            $db->begin_transaction();
            try {
                self::deleteLedgerForEntry($id);
                self::syncLedgerForEntry($id, $data['product_id'], $goodQty, $data['notes']);
                if ($oldProductId > 0 && $oldProductId !== $data['product_id']) {
                    self::recalculateBalancesForProduct($oldProductId);
                }
                $db->commit();
            } catch (\Throwable $e) {
                $db->rollback();
                throw $e;
            }
        } catch (\Throwable $e) {
            self::flash('err', 'Update failed: ' . $e->getMessage());
            header('Location: /production-entries/edit?id=' . $id);
            exit;
        }

        self::recalculateCoverage([$oldProductId, $data['product_id']]);
        HandoffTrackingService::syncProductionEntry($id);

        $after = DB::fetchOne('SELECT * FROM production_entries WHERE id=? LIMIT 1', [$id]) ?: [];
        AuditLogService::logEvent(
            'production_entry',
            $id,
            AuditLogService::EVENT_LIFECYCLE,
            AuditLogService::ACTION_UPDATED,
            Auth::user(),
            [
                'app' => 'manufacturing',
                'module' => 'production',
                'old_state' => (string)($existing['status'] ?? ''),
                'new_state' => (string)($after['status'] ?? ''),
                'note' => 'Production entry updated.',
                'diff' => AuditLogService::diffImportantFields($existing, $after, self::auditDiffFields()),
            ]
        );

        self::flash('ok', self::hasLedgerTable() ? 'Production entry updated and ledger resynced.' : 'Production entry updated.');
    }

    public static function delete(int $id): void
    {
        AuditLogService::ensureSchema();
        if ($id <= 0) {
            self::flash('err', 'Invalid production entry id.');
            return;
        }

        $existing = DB::fetchOne('SELECT * FROM production_entries WHERE id=? LIMIT 1', [$id]);
        if (!$existing) {
            self::flash('err', 'Production entry not found.');
            return;
        }

        $productId = (int)($existing['product_id'] ?? 0);

        try {
            $context = EntityContext::fromUser(Auth::user());

            if (self::hasLedgerTable()) {
                $db = DB::conn();
                $db->begin_transaction();
                try {
                    self::deleteLedgerForEntry($id);
                    if ($productId > 0) {
                        self::recalculateBalancesForProduct($productId);
                    }
                    ProductionEntryService::delete($id, $context);
                    $db->commit();
                } catch (\Throwable $e) {
                    $db->rollback();
                    throw $e;
                }
            } else {
                ProductionEntryService::delete($id, $context);
            }
        } catch (\Throwable $e) {
            self::flash('err', 'Delete failed: ' . $e->getMessage());
            return;
        }

        self::recalculateCoverage([$productId]);
        HandoffTrackingService::syncProductionEntry($id);

        AuditLogService::logEvent(
            'production_entry',
            $id,
            AuditLogService::EVENT_LIFECYCLE,
            AuditLogService::ACTION_DELETED,
            Auth::user(),
            [
                'app' => 'manufacturing',
                'module' => 'production',
                'old_state' => (string)($existing['status'] ?? ''),
                'note' => 'Production entry deleted.',
                'diff' => AuditLogService::diffImportantFields($existing, [], self::auditDiffFields()),
            ]
        );

        self::flash('ok', self::hasLedgerTable() ? 'Production entry deleted and ledger adjusted.' : 'Production entry deleted.');
    }

    private static function syncLedgerForEntry(int $entryId, int $productId, float $goodQty, string $notes): void
    {
        if (!self::hasLedgerTable() || $productId <= 0 || $goodQty <= 0) {
            return;
        }

        $currentBalanceRow = DB::fetchOne('SELECT COALESCE(SUM(qty_delta), 0) AS balance FROM stock_ledger_entries WHERE product_id = ?', [$productId]);
        $currentBalance = (float)($currentBalanceRow['balance'] ?? 0);
        $balanceAfter = $currentBalance + $goodQty;

        DB::query(
            'INSERT INTO stock_ledger_entries (product_id, movement_type, qty_delta, balance_after, reference_no, source_module, source_id, notes) VALUES (?,?,?,?,?,?,?,?)',
            [$productId, 'PRODUCTION_IN', $goodQty, $balanceAfter, 'PE-' . $entryId, 'ProductionEntries', $entryId, $notes]
        );

        AuditLogService::logEvent(
            'production_entry',
            $entryId,
            AuditLogService::EVENT_SYSTEM,
            AuditLogService::ACTION_STOCK_ADJUSTMENT_POSTED,
            Auth::user(),
            [
                'app' => 'manufacturing',
                'module' => 'stock_ledger',
                'note' => 'Stock adjustment posted from production output.',
                'metadata' => [
                    'product_id' => $productId,
                    'qty_delta' => $goodQty,
                    'balance_after' => $balanceAfter,
                    'movement_type' => 'PRODUCTION_IN',
                ],
            ]
        );

        self::recalculateBalancesForProduct($productId);
    }

    private static function deleteLedgerForEntry(int $entryId): void
    {
        if (!self::hasLedgerTable()) {
            return;
        }

        DB::query('DELETE FROM stock_ledger_entries WHERE source_module=? AND source_id=?', ['ProductionEntries', $entryId]);
    }

    private static function recalculateBalancesForProduct(int $productId): void
    {
        if (!self::hasLedgerTable() || $productId <= 0) {
            return;
        }

        $rows = DB::fetchAll(
            'SELECT id, qty_delta FROM stock_ledger_entries WHERE product_id=? ORDER BY created_at ASC, id ASC',
            [$productId]
        );

        $balance = 0.0;
        foreach ($rows as $ledgerRow) {
            $balance += (float)($ledgerRow['qty_delta'] ?? 0);
            DB::query('UPDATE stock_ledger_entries SET balance_after=? WHERE id=?', [$balance, (int)$ledgerRow['id']]);
        }
    }

    private static function hasLedgerTable(): bool
    {
        return DB::fetchOne("SHOW TABLES LIKE 'stock_ledger_entries'") !== null;
    }

    private static function products(): array
    {
        return DB::fetchAll('SELECT id, parts_name, parts_number FROM products WHERE is_active=1 ORDER BY parts_name ASC LIMIT 1000');
    }

    private static function machines(): array
    {
        return DB::fetchAll('SELECT id, machine_no, machine_name FROM machines WHERE is_active=1 ORDER BY machine_no ASC LIMIT 1000');
    }

    private static function sanitize(array $input): array
    {
        return [
            'production_date' => trim((string)($input['production_date'] ?? '')),
            'shift' => trim((string)($input['shift'] ?? 'Day')),
            'machine_id' => (int)($input['machine_id'] ?? 0),
            'product_id' => (int)($input['product_id'] ?? 0),
            'produced_qty' => (float)($input['produced_qty'] ?? 0),
            'rejected_qty' => (float)($input['rejected_qty'] ?? 0),
            'status' => trim((string)($input['status'] ?? 'Draft')),
            'notes' => trim((string)($input['notes'] ?? '')),
            'redirect' => self::safeRedirect((string)($input['redirect'] ?? '')),
        ];
    }

    /**
     * @param array<string,mixed> $old
     * @return array{machines: array<int,array<string,mixed>>, products: array<int,array<string,mixed>>}
     */
    private static function operatorScope(array $old): array
    {
        $user = Auth::user();
        $ctx = platform_user_context_contract()->resolveUserContext($user);
        $scope = (array)($ctx['scope'] ?? []);
        $machineIds = array_values(array_filter(array_map('intval', (array)($scope['machine_ids'] ?? [])), static fn (int $id): bool => $id > 0));
        $partIds = array_values(array_filter(array_map('intval', (array)($scope['part_ids'] ?? [])), static fn (int $id): bool => $id > 0));
        $productionDate = trim((string)($old['production_date'] ?? date('Y-m-d')));
        $selectedMachineId = (int)($old['machine_id'] ?? 0);
        $selectedProductId = (int)($old['product_id'] ?? 0);

        $machines = self::machines();
        if ($machineIds !== []) {
            $allowed = array_fill_keys($machineIds, true);
            $machines = array_values(array_filter($machines, static fn (array $row): bool => isset($allowed[(int)($row['id'] ?? 0)])));
        }

        $products = [];
        if ($partIds !== []) {
            $products = self::productsByIds($partIds);
        } elseif ($machineIds !== []) {
            $products = self::productsForProductionScope($machineIds, $productionDate, $selectedMachineId);
        }

        if ($products === []) {
            $products = self::products();
        }

        if ($selectedProductId > 0 && !self::hasOptionId($products, $selectedProductId)) {
            $extra = self::productsByIds([$selectedProductId]);
            if ($extra !== []) {
                $products = array_merge($extra, $products);
            }
        }

        if ($selectedMachineId > 0 && !self::hasOptionId($machines, $selectedMachineId)) {
            $extraMachine = DB::fetchOne('SELECT id, machine_no, machine_name FROM machines WHERE id=? LIMIT 1', [$selectedMachineId]);
            if (is_array($extraMachine)) {
                array_unshift($machines, $extraMachine);
            }
        }

        return [
            'machines' => $machines,
            'products' => $products,
        ];
    }

    /**
     * @param array<int,int> $machineIds
     * @return array<int,array<string,mixed>>
     */
    private static function productsForProductionScope(array $machineIds, string $productionDate, int $selectedMachineId = 0): array
    {
        if ($machineIds === []) {
            return [];
        }

        $candidateMachineIds = $selectedMachineId > 0 ? [$selectedMachineId] : $machineIds;
        $ph = implode(',', array_fill(0, count($candidateMachineIds), '?'));
        $params = array_merge([$productionDate], $candidateMachineIds);

        $rows = DB::fetchAll(
            "SELECT DISTINCT p.id, p.parts_name, p.parts_number
             FROM production_plans pp
             INNER JOIN products p ON p.id = pp.product_id
             WHERE pp.plan_date = ?
               AND pp.machine_id IN ({$ph})
               AND p.is_active = 1
             ORDER BY p.parts_name ASC",
            $params
        );

        if ($rows !== []) {
            return $rows;
        }

        return DB::fetchAll(
            "SELECT DISTINCT p.id, p.parts_name, p.parts_number
             FROM part_machine_map pm
             INNER JOIN products p ON p.id = pm.product_id
             WHERE pm.is_active = 1
               AND pm.machine_id IN ({$ph})
               AND p.is_active = 1
             ORDER BY p.parts_name ASC",
            $candidateMachineIds
        );
    }

    /**
     * @param array<int,int> $ids
     * @return array<int,array<string,mixed>>
     */
    private static function productsByIds(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));
        if ($ids === []) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        return DB::fetchAll(
            "SELECT id, parts_name, parts_number
             FROM products
             WHERE is_active = 1 AND id IN ({$ph})
             ORDER BY parts_name ASC",
            $ids
        );
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    private static function hasOptionId(array $rows, int $id): bool
    {
        foreach ($rows as $row) {
            if ((int)($row['id'] ?? 0) === $id) {
                return true;
            }
        }
        return false;
    }

    private static function safeRedirect(string $redirect): string
    {
        $redirect = trim($redirect);
        if ($redirect === '' || $redirect[0] !== '/') {
            return '';
        }
        return str_starts_with($redirect, '//') ? '' : $redirect;
    }

    private static function flash(string $key, string $msg): void
    {
        $_SESSION['production_entries_flash_' . $key] = $msg;
    }

    private static function pullFlash(string $key): string
    {
        $k = 'production_entries_flash_' . $key;
        $v = (string)($_SESSION[$k] ?? '');
        unset($_SESSION[$k]);
        return $v;
    }

    private static function recalculateCoverage(array $productIds): void
    {
        try {
            CoverageService::recalculateForProducts($productIds);
        } catch (\Throwable $e) {
            self::flash('err', 'Coverage refresh failed: ' . $e->getMessage());
        }
    }

    /**
     * @return array<int,string>
     */
    private static function auditDiffFields(): array
    {
        return [
            'production_date',
            'shift',
            'machine_id',
            'product_id',
            'produced_qty',
            'rejected_qty',
            'good_qty',
            'status',
            'notes',
        ];
    }
}
