<?php
declare(strict_types=1);

namespace Plugins\QCEntries\Controllers;

use App\Core\AuditLogService;
use App\Core\Auth;
use App\Core\DB;
use App\Core\EntityContext;
use App\Core\HandoffEngine;
use App\Core\View;
use Plugins\Base\Services\HandoffTrackingService;
use Plugins\QCEntries\QCEntryService;
use Plugins\QCEntries\Services\QcLeaderDashboardService;
use Plugins\Coverage\Services\CoverageService;
use Plugins\Workflow\Services\WorkflowGovernance;
use Plugins\Workflow\Services\WorkflowPolicy;
use Plugins\Workflow\Services\WorkflowTransitionEngine;

require_once APP_ROOT . '/plugins/Base/Services/HandoffTrackingService.php';
require_once __DIR__ . '/../Services/QcLeaderDashboardService.php';
require_once __DIR__ . '/../QCEntryService.php';

final class QCEntriesController
{
    public static function leaderDashboard(View $view, ?array $currentUser = null): void
    {
        WorkflowGovernance::ensureSchema();

        $payload = QcLeaderDashboardService::build($_GET, $currentUser);
        $view->render('QCEntries::leader.php', [
            'pageTitle' => 'QC Workspace',
            'dashboard' => $payload,
        ]);
    }

    public static function index(View $view): void
    {
        WorkflowGovernance::ensureSchema();
        AuditLogService::ensureSchema();

        $status = trim((string)($_GET['status'] ?? ''));
        $qcType = trim((string)($_GET['qc_type'] ?? ''));

        $sql = "SELECT q.*, p.parts_name, p.parts_number
                FROM qc_entries q
                INNER JOIN products p ON p.id = q.product_id
                WHERE 1=1";
        $params = [];

        if ($status !== '') {
            $sql .= ' AND q.status = ?';
            $params[] = $status;
        }
        if ($qcType !== '') {
            $sql .= ' AND q.qc_type = ?';
            $params[] = $qcType;
        }

        $sql .= ' ORDER BY q.id DESC LIMIT 350';

        $rows = DB::fetchAll($sql, $params);
        $auditSummary = AuditLogService::latestByEntityIds(WorkflowPolicy::MODULE_QC_ENTRY, array_map(static fn(array $row): int => (int)($row['id'] ?? 0), $rows));

        $view->render('QCEntries::index.php', [
            'pageTitle' => 'QC Entries',
            'rows' => $rows,
            'audit_summary' => $auditSummary,
            'status' => $status,
            'qc_type' => $qcType,
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
        ]);
    }

    public static function addForm(View $view): void
    {
        WorkflowGovernance::ensureSchema();

        $old = $_SESSION['qc_entries_old'] ?? [];
        if ((int)($old['product_id'] ?? 0) <= 0 && (int)($_GET['product_id'] ?? 0) > 0) {
            $old['product_id'] = (int)$_GET['product_id'];
        }
        if ((int)($old['qc_plan_id'] ?? 0) <= 0 && (int)($_GET['qc_plan_id'] ?? 0) > 0) {
            $old['qc_plan_id'] = (int)$_GET['qc_plan_id'];
        }
        if ((int)($old['daily_order_id'] ?? 0) <= 0 && (int)($_GET['daily_order_id'] ?? 0) > 0) {
            $old['daily_order_id'] = (int)$_GET['daily_order_id'];
        }
        if ((int)($old['production_plan_id'] ?? 0) <= 0 && (int)($_GET['production_plan_id'] ?? 0) > 0) {
            $old['production_plan_id'] = (int)$_GET['production_plan_id'];
        }
        if ((int)($old['production_entry_id'] ?? 0) <= 0 && (int)($_GET['production_entry_id'] ?? 0) > 0) {
            $old['production_entry_id'] = (int)$_GET['production_entry_id'];
        }
        if ((string)($old['qc_type'] ?? '') === '' && trim((string)($_GET['qc_type'] ?? '')) !== '') {
            $old['qc_type'] = trim((string)$_GET['qc_type']);
        }
        if ((string)($old['status'] ?? '') === '' && trim((string)($_GET['status'] ?? '')) !== '') {
            $old['status'] = trim((string)$_GET['status']);
        }

        $view->render('QCEntries::add.php', [
            'pageTitle' => 'Add QC Entry',
            'products' => self::products(),
            'qc_plans' => self::qcPlans(),
            'daily_orders' => self::dailyOrders(),
            'production_plans' => self::productionPlans(),
            'old' => $old,
            'error' => self::pullFlash('err'),
        ]);
        unset($_SESSION['qc_entries_old']);
    }

    public static function create(array $input): void
    {
        WorkflowGovernance::ensureSchema();
        AuditLogService::ensureSchema();

        $data = self::sanitize($input);
        $_SESSION['qc_entries_old'] = $data;

        if ($data['product_id'] <= 0 || $data['checked_qty'] <= 0) {
            self::flash('err', 'Product and checked qty are required.');
            header('Location: /qc-entries/add');
            exit;
        }

        [$passQty, $failQty] = self::normalizePassFail($data['checked_qty'], $data['pass_qty'], $data['fail_qty']);

        $db = DB::conn();
        $db->begin_transaction();
        try {
            $insertData = [
                'qc_plan_id' => $data['qc_plan_id'] > 0 ? $data['qc_plan_id'] : null,
                'daily_order_id' => $data['daily_order_id'] > 0 ? $data['daily_order_id'] : null,
                'production_plan_id' => $data['production_plan_id'] > 0 ? $data['production_plan_id'] : null,
                'product_id' => $data['product_id'],
                'qc_type' => $data['qc_type'],
                'checked_qty' => $data['checked_qty'],
                'pass_qty' => $passQty,
                'fail_qty' => $failQty,
                'status' => $data['status'],
                'remarks' => $data['remarks'],
            ];

            $context = EntityContext::fromUser(Auth::user());
            $entryId = QCEntryService::create($insertData, $context);

            self::syncLedgerForEntry($entryId, $data['product_id'], $failQty, $data['remarks']);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            self::flash('err', 'Create failed: ' . $e->getMessage());
            header('Location: /qc-entries/add');
            exit;
        }

        self::recalculateCoverage([$data['product_id']]);
        HandoffTrackingService::syncQcEntry((int)$entryId);

        $created = DB::fetchOne('SELECT * FROM qc_entries WHERE id=? LIMIT 1', [$entryId]) ?: [];
        AuditLogService::logEvent(
            WorkflowPolicy::MODULE_QC_ENTRY,
            $entryId,
            AuditLogService::EVENT_LIFECYCLE,
            AuditLogService::ACTION_CREATED,
            \App\Core\Auth::user(),
            [
                'app' => 'manufacturing',
                'module' => 'qc',
                'new_state' => (string)($created['status'] ?? ''),
                'note' => 'QC entry created.',
                'diff' => AuditLogService::diffImportantFields([], $created, self::auditDiffFields()),
            ]
        );

        unset($_SESSION['qc_entries_old']);
        self::flash('ok', self::hasLedgerTable() ? 'QC entry created and ledger synced for failed quantity.' : 'QC entry created.');
    }

    public static function createDraft(array $input): void
    {
        WorkflowGovernance::ensureSchema();
        AuditLogService::ensureSchema();

        $productId = (int)($input['product_id'] ?? 0);
        $dailyOrderId = (int)($input['daily_order_id'] ?? 0);
        $checkedQty = round(max(0.0, (float)($input['covered_qty'] ?? 0)), 2);
        $qcType = trim((string)($input['qc_type'] ?? 'Final'));
        $fallback = self::normalizeRedirect((string)($input['failure_fallback'] ?? '/manufacturing/coverage'), '/manufacturing/coverage');

        if ($productId <= 0 || $checkedQty <= 0) {
            self::flash('err', 'Draft QC entry requires a product and covered quantity.');
            header('Location: ' . $fallback);
            exit;
        }

        $remarks = self::buildDraftNotes([
            'Coverage QC draft created from dashboard.',
            $dailyOrderId > 0 ? 'Daily Order #' . $dailyOrderId : '',
            'Checked qty ' . number_format($checkedQty, 2, '.', ''),
        ]);

        $context = EntityContext::fromUser(Auth::user());
        $draftId = QCEntryService::create([
            'qc_plan_id' => null,
            'daily_order_id' => $dailyOrderId > 0 ? $dailyOrderId : null,
            'production_plan_id' => null,
            'product_id' => $productId,
            'qc_type' => $qcType !== '' ? $qcType : 'Final',
            'checked_qty' => $checkedQty,
            'pass_qty' => 0,
            'fail_qty' => 0,
            'status' => 'Draft',
            'remarks' => $remarks,
        ], $context);
        $created = DB::fetchOne('SELECT * FROM qc_entries WHERE id=? LIMIT 1', [$draftId]) ?: [];
        AuditLogService::logEvent(
            WorkflowPolicy::MODULE_QC_ENTRY,
            $draftId,
            AuditLogService::EVENT_LIFECYCLE,
            AuditLogService::ACTION_CREATED,
            \App\Core\Auth::user(),
            [
                'app' => 'manufacturing',
                'module' => 'qc',
                'new_state' => (string)($created['status'] ?? ''),
                'note' => 'Draft QC entry created from coverage flow.',
                'metadata' => ['source' => 'coverage_draft_flow'],
                'diff' => AuditLogService::diffImportantFields([], $created, self::auditDiffFields()),
            ]
        );
        self::flash('ok', 'Draft QC entry created.');
        header('Location: /qc-entries/edit?id=' . $draftId);
        exit;
    }

    public static function editForm(View $view, int $id): void
    {
        WorkflowGovernance::ensureSchema();
        AuditLogService::ensureSchema();
        HandoffTrackingService::syncQcEntry($id);

        if ($id <= 0) {
            self::flash('err', 'Invalid QC entry id.');
            header('Location: /qc-entries');
            exit;
        }

        $row = DB::fetchOne('SELECT * FROM qc_entries WHERE id=? LIMIT 1', [$id]);
        if (!$row) {
            self::flash('err', 'QC entry not found.');
            header('Location: /qc-entries');
            exit;
        }

        $row = WorkflowGovernance::withDefaults($row);
        $currentUser = \App\Core\Auth::user();
        $workflowActions = WorkflowTransitionEngine::allowedActions(WorkflowPolicy::MODULE_QC_ENTRY, $row, $currentUser);
        $workflowActionKeys = array_column($workflowActions, 'action');

        $view->render('QCEntries::edit.php', [
            'pageTitle' => 'Edit QC Entry',
            'row' => $row,
            'products' => self::products(),
            'qc_plans' => self::qcPlans(),
            'daily_orders' => self::dailyOrders(),
            'production_plans' => self::productionPlans(),
            'error' => self::pullFlash('err'),
            'governance' => [
                'locked' => WorkflowPolicy::isLocked(WorkflowPolicy::MODULE_QC_ENTRY, $row),
                'can_submit' => in_array('submit', $workflowActionKeys, true),
                'can_approve' => in_array('approve', $workflowActionKeys, true),
                'can_reject' => in_array('reject', $workflowActionKeys, true),
                'can_reopen' => in_array('reopen', $workflowActionKeys, true),
                'can_override' => WorkflowPolicy::canTransitionApproval(WorkflowPolicy::MODULE_QC_ENTRY, $row, 'unlock_override', $currentUser),
                'role' => WorkflowPolicy::roleSlug($currentUser),
                'actions' => $workflowActions,
            ],
            'approval_events' => self::approvalEvents(WorkflowPolicy::MODULE_QC_ENTRY, $id),
            'ownership_summary' => HandoffEngine::summaryForEntity('qc_entry', $id),
            'activity_timeline' => AuditLogService::timeline(WorkflowPolicy::MODULE_QC_ENTRY, $id, 80),
        ]);
    }

    public static function update(array $input): void
    {
        WorkflowGovernance::ensureSchema();
        AuditLogService::ensureSchema();

        $id = (int)($input['id'] ?? 0);
        $data = self::sanitize($input);

        if ($id <= 0 || $data['product_id'] <= 0 || $data['checked_qty'] <= 0) {
            self::flash('err', 'Invalid payload.');
            header('Location: /qc-entries');
            exit;
        }

        [$passQty, $failQty] = self::normalizePassFail($data['checked_qty'], $data['pass_qty'], $data['fail_qty']);

        $existing = DB::fetchOne('SELECT * FROM qc_entries WHERE id=? LIMIT 1', [$id]);
        if (!$existing) {
            self::flash('err', 'QC entry not found.');
            header('Location: /qc-entries');
            exit;
        }

        $existing = WorkflowGovernance::withDefaults($existing);
        if (WorkflowPolicy::isLocked(WorkflowPolicy::MODULE_QC_ENTRY, $existing)) {
            self::flash('err', 'This QC entry is locked. Reopen the record before editing.');
            header('Location: /qc-entries/edit?id=' . $id);
            exit;
        }

        $existingProductId = (int)($existing['product_id'] ?? 0);

        $db = DB::conn();
        $db->begin_transaction();
        try {
            $updateData = [
                'qc_plan_id' => $data['qc_plan_id'] > 0 ? $data['qc_plan_id'] : null,
                'daily_order_id' => $data['daily_order_id'] > 0 ? $data['daily_order_id'] : null,
                'production_plan_id' => $data['production_plan_id'] > 0 ? $data['production_plan_id'] : null,
                'product_id' => $data['product_id'],
                'qc_type' => $data['qc_type'],
                'checked_qty' => $data['checked_qty'],
                'pass_qty' => $passQty,
                'fail_qty' => $failQty,
                'status' => $data['status'],
                'remarks' => $data['remarks'],
            ];

            $context = EntityContext::fromUser(Auth::user());
            QCEntryService::update($id, $updateData, $context, $existing);

            self::deleteLedgerForEntry($id);
            self::syncLedgerForEntry($id, $data['product_id'], $failQty, $data['remarks']);

            if ($existingProductId > 0 && $existingProductId !== $data['product_id'] && self::hasLedgerTable()) {
                self::recalculateBalancesForProduct($existingProductId);
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            self::flash('err', 'Update failed: ' . $e->getMessage());
            header('Location: /qc-entries/edit?id=' . $id);
            exit;
        }

        self::recalculateCoverage([$existingProductId, $data['product_id']]);
        HandoffTrackingService::syncQcEntry($id);

        $after = DB::fetchOne('SELECT * FROM qc_entries WHERE id=? LIMIT 1', [$id]) ?: [];
        AuditLogService::logEvent(
            WorkflowPolicy::MODULE_QC_ENTRY,
            $id,
            AuditLogService::EVENT_LIFECYCLE,
            AuditLogService::ACTION_UPDATED,
            \App\Core\Auth::user(),
            [
                'app' => 'manufacturing',
                'module' => 'qc',
                'old_state' => (string)($existing['status'] ?? ''),
                'new_state' => (string)($after['status'] ?? ''),
                'note' => 'QC entry updated.',
                'diff' => AuditLogService::diffImportantFields($existing, $after, self::auditDiffFields()),
            ]
        );

        self::flash('ok', self::hasLedgerTable() ? 'QC entry updated and ledger resynced.' : 'QC entry updated.');
    }

    public static function delete(int $id): void
    {
        WorkflowGovernance::ensureSchema();
        AuditLogService::ensureSchema();

        if ($id <= 0) {
            self::flash('err', 'Invalid QC entry id.');
            return;
        }

        $existing = DB::fetchOne('SELECT * FROM qc_entries WHERE id=? LIMIT 1', [$id]);
        if (!$existing) {
            self::flash('err', 'QC entry not found.');
            return;
        }

        $existing = WorkflowGovernance::withDefaults($existing);
        if (WorkflowPolicy::isLocked(WorkflowPolicy::MODULE_QC_ENTRY, $existing)) {
            self::flash('err', 'Locked QC entries cannot be deleted. Reopen first with governance reason.');
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
                    QCEntryService::delete($id, $context);
                    $db->commit();
                } catch (\Throwable $e) {
                    $db->rollback();
                    throw $e;
                }
            } else {
                QCEntryService::delete($id, $context);
            }
        } catch (\Throwable $e) {
            self::flash('err', 'Delete failed: ' . $e->getMessage());
            return;
        }

        self::recalculateCoverage([$productId]);
        HandoffTrackingService::syncQcEntry($id);

        AuditLogService::logEvent(
            WorkflowPolicy::MODULE_QC_ENTRY,
            $id,
            AuditLogService::EVENT_LIFECYCLE,
            AuditLogService::ACTION_DELETED,
            Auth::user(),
            [
                'app' => 'manufacturing',
                'module' => 'qc',
                'old_state' => (string)($existing['status'] ?? ''),
                'note' => 'QC entry deleted.',
                'diff' => AuditLogService::diffImportantFields($existing, [], self::auditDiffFields()),
            ]
        );

        self::flash('ok', self::hasLedgerTable() ? 'QC entry deleted and ledger adjusted.' : 'QC entry deleted.');
    }

    public static function approvalAction(array $input): void
    {
        require_once __DIR__ . '/../QCEntryService.php';

        $id = (int)($input['id'] ?? 0);
        $rawAction = strtolower(trim((string)($input['action'] ?? '')));
        $action = $rawAction === 'unlock_override' ? 'reopen' : $rawAction;
        $note = trim((string)($input['note'] ?? ''));
        $reason = trim((string)($input['reason'] ?? ''));
        $redirect = self::normalizeRedirect((string)($input['redirect'] ?? ('/qc-entries/edit?id=' . $id)), '/qc-entries');

        if ($id <= 0 || !in_array($action, ['submit', 'approve', 'reject', 'reopen', 'finalize', 'cancel'], true)) {
            self::flash('err', 'Invalid governance request.');
            header('Location: /qc-entries');
            exit;
        }

        $newStatus = \Plugins\QCEntries\QCEntryService::actionToStatus($action);
        if ($newStatus === null) {
            self::flash('err', 'Invalid governance request.');
            header('Location: /qc-entries');
            exit;
        }

        $context = \App\Core\EntityContext::fromUser(\App\Core\Auth::user());

        try {
            \Plugins\QCEntries\QCEntryService::quickStatusUpdate($id, $newStatus, $context, $reason, $note);
            self::flash('ok', 'Governance action applied: ' . ucfirst($action) . '.');
            header('Location: ' . $redirect);
            exit;
        } catch (\RuntimeException $e) {
            self::flash('err', $e->getMessage());
            header('Location: ' . $redirect);
            exit;
        }
    }

    private static function syncLedgerForEntry(int $entryId, int $productId, float $failQty, string $remarks): void
    {
        if (!self::hasLedgerTable() || $productId <= 0 || $failQty <= 0) {
            return;
        }

        $currentBalanceRow = DB::fetchOne('SELECT COALESCE(SUM(qty_delta), 0) AS balance FROM stock_ledger_entries WHERE product_id = ?', [$productId]);
        $currentBalance = (float)($currentBalanceRow['balance'] ?? 0);
        $qtyDelta = -abs($failQty);
        $balanceAfter = $currentBalance + $qtyDelta;

        DB::query(
            'INSERT INTO stock_ledger_entries (product_id, movement_type, qty_delta, balance_after, reference_no, source_module, source_id, notes) VALUES (?,?,?,?,?,?,?,?)',
            [$productId, 'PRODUCTION_OUT', $qtyDelta, $balanceAfter, 'QC-' . $entryId, 'QCEntries', $entryId, $remarks]
        );

        self::recalculateBalancesForProduct($productId);
    }

    private static function deleteLedgerForEntry(int $entryId): void
    {
        if (!self::hasLedgerTable()) {
            return;
        }

        DB::query('DELETE FROM stock_ledger_entries WHERE source_module=? AND source_id=?', ['QCEntries', $entryId]);
    }

    private static function recalculateBalancesForProduct(int $productId): void
    {
        if (!self::hasLedgerTable() || $productId <= 0) {
            return;
        }

        $rows = DB::fetchAll('SELECT id, qty_delta FROM stock_ledger_entries WHERE product_id=? ORDER BY created_at ASC, id ASC', [$productId]);

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

    private static function normalizePassFail(float $checked, float $pass, float $fail): array
    {
        if ($pass <= 0 && $fail <= 0) {
            return [$checked, 0.0];
        }
        if ($pass <= 0) {
            $pass = max(0, $checked - $fail);
        }
        if ($fail <= 0) {
            $fail = max(0, $checked - $pass);
        }

        if (($pass + $fail) > $checked) {
            $fail = max(0, $checked - $pass);
        }

        return [round($pass, 2), round($fail, 2)];
    }

    /**
     * @return array<int,string>
     */
    private static function auditDiffFields(): array
    {
        return [
            'qc_plan_id',
            'daily_order_id',
            'production_plan_id',
            'product_id',
            'qc_type',
            'checked_qty',
            'pass_qty',
            'fail_qty',
            'status',
            'remarks',
            'approval_status',
            'workflow_state',
        ];
    }

    private static function products(): array
    {
        return DB::fetchAll('SELECT id, parts_name, parts_number FROM products WHERE is_active=1 ORDER BY parts_name ASC LIMIT 1000');
    }

    private static function qcPlans(): array
    {
        return DB::fetchAll('SELECT id, plan_date, planned_qty FROM qc_plans ORDER BY id DESC LIMIT 300');
    }

    private static function dailyOrders(): array
    {
        return DB::fetchAll('SELECT id, customer_name, order_date FROM daily_orders ORDER BY id DESC LIMIT 300');
    }

    private static function productionPlans(): array
    {
        return DB::fetchAll('SELECT id, plan_date, planned_qty FROM production_plans ORDER BY id DESC LIMIT 300');
    }

    private static function sanitize(array $input): array
    {
        return [
            'qc_plan_id' => (int)($input['qc_plan_id'] ?? 0),
            'daily_order_id' => (int)($input['daily_order_id'] ?? 0),
            'production_plan_id' => (int)($input['production_plan_id'] ?? 0),
            'product_id' => (int)($input['product_id'] ?? 0),
            'qc_type' => trim((string)($input['qc_type'] ?? 'Final')),
            'checked_qty' => (float)($input['checked_qty'] ?? 0),
            'pass_qty' => (float)($input['pass_qty'] ?? 0),
            'fail_qty' => (float)($input['fail_qty'] ?? 0),
            'status' => trim((string)($input['status'] ?? 'Open')),
            'remarks' => trim((string)($input['remarks'] ?? '')),
        ];
    }

    /**
     * @param array<int,string> $parts
     */
    private static function buildDraftNotes(array $parts): string
    {
        $parts = array_values(array_filter(array_map(static fn (string $part): string => trim($part), $parts), static fn (string $part): bool => $part !== ''));
        return implode(' | ', $parts);
    }

    private static function normalizeRedirect(string $candidate, string $fallback): string
    {
        $candidate = trim($candidate);
        if ($candidate === '' || !str_starts_with($candidate, '/')) {
            return $fallback;
        }
        return $candidate;
    }

    private static function flash(string $key, string $msg): void
    {
        $_SESSION['qc_entries_flash_' . $key] = $msg;
    }

    private static function pullFlash(string $key): string
    {
        $k = 'qc_entries_flash_' . $key;
        $v = (string)($_SESSION[$k] ?? '');
        unset($_SESSION[$k]);
        return $v;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function approvalEvents(string $module, int $recordId, int $limit = 20): array
    {
        if ($recordId <= 0) {
            return [];
        }

        $limit = max(1, min(100, $limit));
        return DB::fetchAll(
            'SELECT e.*, u.email AS actor_email
             FROM workflow_approval_events e
             LEFT JOIN users u ON u.id = e.acted_by
             WHERE e.module_name = ? AND e.record_id = ?
             ORDER BY e.acted_at DESC, e.id DESC
             LIMIT ' . $limit,
            [$module, $recordId]
        );
    }

    private static function recalculateCoverage(array $productIds): void
    {
        try {
            CoverageService::recalculateForProducts($productIds);
        } catch (\Throwable $e) {
            self::flash('err', 'Coverage refresh failed: ' . $e->getMessage());
        }
    }
}
