<?php
declare(strict_types=1);

namespace Plugins\DispatchEntries\Controllers;

use App\Core\Auth;
use App\Core\AuditLogService;
use App\Core\DB;
use App\Core\EntityContext;
use App\Core\HandoffEngine;
use App\Core\PackageManager;
use App\Core\PdfService;
use App\Core\View;
use App\Services\ExportHistoryService;
use Plugins\Base\Services\HandoffTrackingService;
use Plugins\DispatchEntries\DispatchEntryService;
use Plugins\DispatchEntries\Services\DispatchLeaderDashboardService;
use Plugins\DispatchEntries\Services\DispatchWorkflow;
use Plugins\Coverage\Services\CoverageService;
use Plugins\Workflow\Services\WorkflowGovernance;
use Plugins\Workflow\Services\WorkflowPolicy;
use Plugins\Workflow\Services\WorkflowTransitionEngine;

require_once APP_ROOT . '/plugins/Base/Services/HandoffTrackingService.php';
require_once __DIR__ . '/../Services/DispatchLeaderDashboardService.php';
require_once __DIR__ . '/../Services/DispatchWorkflow.php';
require_once __DIR__ . '/../DispatchEntryService.php';

final class DispatchEntriesController
{
    public static function leaderDashboard(View $view, ?array $currentUser = null): void
    {
        $payload = DispatchLeaderDashboardService::build($_GET, $currentUser);
        $view->render('DispatchEntries::leader.php', [
            'pageTitle' => 'Dispatch Workspace',
            'dashboard' => $payload,
        ]);
    }

    public static function index(View $view): void
    {
        DispatchWorkflow::ensureSchema();
        WorkflowGovernance::ensureSchema();
        AuditLogService::ensureSchema();

        $dispatchDate = trim((string)($_GET['dispatch_date'] ?? ''));
        $status = trim((string)($_GET['dispatch_status'] ?? ''));

        $sql = "SELECT d.*, p.parts_name, p.parts_number
                FROM dispatch_entries d
                INNER JOIN products p ON p.id = d.product_id
                WHERE 1=1";
        $params = [];

        if ($dispatchDate !== '') {
            $sql .= ' AND d.dispatch_date = ?';
            $params[] = $dispatchDate;
        }
        if ($status !== '') {
            $sql .= ' AND d.dispatch_status = ?';
            $params[] = $status;
        }

        $sql .= ' ORDER BY d.dispatch_date DESC, d.id DESC LIMIT 350';

        $rows = DB::fetchAll($sql, $params);
        $auditSummary = AuditLogService::latestByEntityIds(WorkflowPolicy::MODULE_DISPATCH_ENTRY, array_map(static fn(array $row): int => (int)($row['id'] ?? 0), $rows));

        $view->render('DispatchEntries::index.php', [
            'pageTitle' => 'Dispatch Entries',
            'rows' => $rows,
            'audit_summary' => $auditSummary,
            'dispatch_date' => $dispatchDate,
            'dispatch_status' => $status,
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
        ]);
    }

    public static function addForm(View $view): void
    {
        DispatchWorkflow::ensureSchema();
        WorkflowGovernance::ensureSchema();

        $old = $_SESSION['dispatch_entries_old'] ?? [];
        if (($old['dispatch_date'] ?? '') === '' && trim((string)($_GET['dispatch_date'] ?? '')) !== '') {
            $old['dispatch_date'] = trim((string)$_GET['dispatch_date']);
        }
        if ((int)($old['product_id'] ?? 0) <= 0 && (int)($_GET['product_id'] ?? 0) > 0) {
            $old['product_id'] = (int)$_GET['product_id'];
        }
        if ((int)($old['daily_order_id'] ?? 0) <= 0 && (int)($_GET['daily_order_id'] ?? 0) > 0) {
            $old['daily_order_id'] = (int)$_GET['daily_order_id'];
        }
        if ((int)($old['qc_entry_id'] ?? 0) <= 0 && (int)($_GET['qc_entry_id'] ?? 0) > 0) {
            $old['qc_entry_id'] = (int)$_GET['qc_entry_id'];
        }
        if ((string)($old['dispatch_status'] ?? '') === '' && trim((string)($_GET['dispatch_status'] ?? '')) !== '') {
            $old['dispatch_status'] = trim((string)$_GET['dispatch_status']);
        }
        if ((string)($old['status_reason'] ?? '') === '' && trim((string)($_GET['status_reason'] ?? '')) !== '') {
            $old['status_reason'] = trim((string)$_GET['status_reason']);
        }
        if ((string)($old['status_note'] ?? '') === '' && trim((string)($_GET['status_note'] ?? '')) !== '') {
            $old['status_note'] = trim((string)$_GET['status_note']);
        }
        if ((string)($old['dispatchable_qty'] ?? '') === '' && trim((string)($_GET['dispatchable_qty'] ?? '')) !== '') {
            $old['dispatchable_qty'] = trim((string)$_GET['dispatchable_qty']);
        }
        if ((string)($old['destination'] ?? '') === '' && trim((string)($_GET['destination'] ?? '')) !== '') {
            $old['destination'] = trim((string)$_GET['destination']);
        }
        $selectedProductId = (int)($old['product_id'] ?? 0);
        $view->render('DispatchEntries::add.php', [
            'pageTitle' => 'Add Dispatch Entry',
            'products' => self::products(),
            'daily_orders' => self::dailyOrders(),
            'production_plans' => self::productionPlans(),
            'production_entries' => self::productionEntries(),
            'qc_entries' => self::qcEntries(),
            'old' => $old,
            'status_options' => DispatchWorkflow::statusOptions(),
            'available_stock' => $selectedProductId > 0 ? self::currentStockBalance($selectedProductId) : null,
            'error' => self::pullFlash('err'),
        ]);
        unset($_SESSION['dispatch_entries_old']);
    }

    public static function create(array $input): void
    {
        DispatchWorkflow::ensureSchema();
        WorkflowGovernance::ensureSchema();
        AuditLogService::ensureSchema();

        $data = self::sanitize($input);
        $_SESSION['dispatch_entries_old'] = $data;

        if ($data['dispatch_date'] === '' || $data['product_id'] <= 0 || $data['dispatchable_qty'] <= 0) {
            self::flash('err', 'Dispatch date, product, and dispatchable qty are required.');
            header('Location: /dispatch-entries/add');
            exit;
        }

        try {
            $workflow = DispatchWorkflow::prepareForSave($data, null, Auth::user());
        } catch (\Throwable $e) {
            self::flash('err', $e->getMessage());
            header('Location: /dispatch-entries/add');
            exit;
        }

        $db = DB::conn();
        $db->begin_transaction();
        try {
            $insertData = [
                'dispatch_date' => $data['dispatch_date'],
                'daily_order_id' => $data['daily_order_id'] > 0 ? $data['daily_order_id'] : null,
                'production_plan_id' => $data['production_plan_id'] > 0 ? $data['production_plan_id'] : null,
                'production_entry_id' => $data['production_entry_id'] > 0 ? $data['production_entry_id'] : null,
                'qc_entry_id' => $data['qc_entry_id'] > 0 ? $data['qc_entry_id'] : null,
                'product_id' => $data['product_id'],
                'dispatchable_qty' => $data['dispatchable_qty'],
                'destination' => $data['destination'],
                'dispatch_type' => $data['dispatch_type'],
                'dispatch_status' => $workflow['dispatch_status'],
                'remarks' => $data['remarks'],
                'status_reason' => $workflow['status_reason'],
                'status_note' => $workflow['status_note'],
                'released_at' => $workflow['released_at'],
                'blocked_at' => $workflow['blocked_at'],
                'dispatched_at' => $workflow['dispatched_at'],
                'last_transition_at' => $workflow['last_transition_at'],
                'status_updated_by' => $workflow['status_updated_by'],
            ];

            $context = EntityContext::fromUser(Auth::user());
            $entryId = DispatchEntryService::create($insertData, $context);

            self::syncLedgerState($entryId, $data, $workflow['product'], null, $workflow['dispatch_status']);
            DispatchWorkflow::recordTransition($entryId, is_array($workflow['transition'] ?? null) ? $workflow['transition'] : null);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            self::flash('err', 'Create failed: ' . $e->getMessage());
            header('Location: /dispatch-entries/add');
            exit;
        }

        self::recalculateCoverage([$data['product_id']]);
        HandoffTrackingService::syncDispatchEntry((int)$entryId);

        $created = DB::fetchOne('SELECT * FROM dispatch_entries WHERE id=? LIMIT 1', [$entryId]) ?: [];
        AuditLogService::logEvent(
            WorkflowPolicy::MODULE_DISPATCH_ENTRY,
            $entryId,
            AuditLogService::EVENT_LIFECYCLE,
            AuditLogService::ACTION_CREATED,
            Auth::user(),
            [
                'app' => 'manufacturing',
                'module' => 'dispatch',
                'new_state' => (string)($created['dispatch_status'] ?? ''),
                'reason' => (string)($workflow['status_reason'] ?? ''),
                'note' => 'Dispatch entry created.',
                'diff' => AuditLogService::diffImportantFields([], $created, self::auditDiffFields()),
            ]
        );

        unset($_SESSION['dispatch_entries_old']);
        self::flash('ok', self::createSuccessMessage((string)$workflow['dispatch_status'], null));
    }

    public static function createDraft(array $input): void
    {
        DispatchWorkflow::ensureSchema();
        WorkflowGovernance::ensureSchema();
        AuditLogService::ensureSchema();

        $productId = (int)($input['product_id'] ?? 0);
        $dailyOrderId = (int)($input['daily_order_id'] ?? 0);
        $qcEntryId = (int)($input['qc_entry_id'] ?? 0);
        $dispatchableQty = round(max(0.0, (float)($input['covered_qty'] ?? 0)), 2);
        $dispatchDate = self::normalizeDraftDate((string)($input['required_date'] ?? ''));
        $customerName = trim((string)($input['customer_name'] ?? ''));
        $fallback = self::normalizeRedirect((string)($input['failure_fallback'] ?? '/manufacturing/coverage'), '/manufacturing/coverage');

        if ($productId <= 0 || $dispatchableQty <= 0) {
            self::flash('err', 'Draft dispatch requires a product and covered quantity.');
            header('Location: ' . $fallback);
            exit;
        }

        $destination = $customerName !== '' ? $customerName : 'Pending Dispatch';
        $remarks = self::buildDraftNotes([
            'Coverage dispatch draft created from dashboard.',
            $dailyOrderId > 0 ? 'Daily Order #' . $dailyOrderId : '',
            $qcEntryId > 0 ? 'QC Entry #' . $qcEntryId : '',
            'Covered qty ' . number_format($dispatchableQty, 2, '.', ''),
        ]);
        $currentUser = Auth::user();
        $performedBy = (int)($currentUser['id'] ?? 0) > 0 ? (int)$currentUser['id'] : null;
        $now = date('Y-m-d H:i:s');

        $context = EntityContext::fromUser($currentUser);
        $draftId = DispatchEntryService::create([
            'dispatch_date' => $dispatchDate,
            'daily_order_id' => $dailyOrderId > 0 ? $dailyOrderId : null,
            'production_plan_id' => null,
            'production_entry_id' => null,
            'qc_entry_id' => $qcEntryId > 0 ? $qcEntryId : null,
            'product_id' => $productId,
            'dispatchable_qty' => $dispatchableQty,
            'destination' => $destination,
            'dispatch_type' => 'Draft',
            'dispatch_status' => 'Draft',
            'remarks' => $remarks,
            'status_reason' => 'Draft start',
            'status_note' => 'Created from dispatch workflow action.',
            'last_transition_at' => $now,
            'status_updated_by' => $performedBy,
        ], $context);
        DispatchWorkflow::recordTransition($draftId, [
            'from_status' => null,
            'to_status' => DispatchWorkflow::STATUS_DRAFT,
            'transition_reason' => 'Draft start',
            'transition_note' => 'Created from dispatch workflow action.',
            'performed_by' => $performedBy,
            'performed_at' => $now,
        ]);
        $created = DB::fetchOne('SELECT * FROM dispatch_entries WHERE id=? LIMIT 1', [$draftId]) ?: [];
        AuditLogService::logEvent(
            WorkflowPolicy::MODULE_DISPATCH_ENTRY,
            $draftId,
            AuditLogService::EVENT_LIFECYCLE,
            AuditLogService::ACTION_CREATED,
            $currentUser,
            [
                'app' => 'manufacturing',
                'module' => 'dispatch',
                'new_state' => (string)($created['dispatch_status'] ?? ''),
                'reason' => 'Draft start',
                'note' => 'Draft dispatch entry created from coverage flow.',
                'metadata' => ['source' => 'coverage_draft_flow'],
                'diff' => AuditLogService::diffImportantFields([], $created, self::auditDiffFields()),
            ]
        );
        self::flash('ok', 'Draft dispatch entry created.');
        header('Location: /dispatch-entries/edit?id=' . $draftId);
        exit;
    }

    public static function editForm(View $view, int $id): void
    {
        DispatchWorkflow::ensureSchema();
        WorkflowGovernance::ensureSchema();
        AuditLogService::ensureSchema();
        HandoffTrackingService::syncDispatchEntry($id);

        if ($id <= 0) {
            self::flash('err', 'Invalid dispatch entry id.');
            header('Location: /dispatch-entries');
            exit;
        }

        $row = DB::fetchOne('SELECT * FROM dispatch_entries WHERE id=? LIMIT 1', [$id]);
        if (!$row) {
            self::flash('err', 'Dispatch entry not found.');
            header('Location: /dispatch-entries');
            exit;
        }

        $row = WorkflowGovernance::withDefaults($row);
        $currentUser = Auth::user();
        $workflowActions = WorkflowTransitionEngine::allowedActions(WorkflowPolicy::MODULE_DISPATCH_ENTRY, $row, $currentUser);
        $workflowActionKeys = array_column($workflowActions, 'action');

        $view->render('DispatchEntries::edit.php', [
            'pageTitle' => 'Edit Dispatch Entry',
            'row' => $row,
            'products' => self::products(),
            'daily_orders' => self::dailyOrders(),
            'production_plans' => self::productionPlans(),
            'production_entries' => self::productionEntries(),
            'qc_entries' => self::qcEntries(),
            'status_options' => DispatchWorkflow::statusOptions(),
            'available_stock' => self::hasLedgerTable() ? self::currentStockBalance((int)$row['product_id']) + (float)($row['dispatchable_qty'] ?? 0) : null,
            'pdf_ready' => self::pdfService()->isReady(),
            'recent_transitions' => DispatchWorkflow::recentTransitions($id),
            'error' => self::pullFlash('err'),
            'governance' => [
                'locked' => WorkflowPolicy::isLocked(WorkflowPolicy::MODULE_DISPATCH_ENTRY, $row),
                'can_submit' => in_array('submit', $workflowActionKeys, true),
                'can_approve' => in_array('approve', $workflowActionKeys, true),
                'can_reject' => in_array('reject', $workflowActionKeys, true),
                'can_reopen' => in_array('reopen', $workflowActionKeys, true),
                'can_override' => WorkflowPolicy::canTransitionApproval(WorkflowPolicy::MODULE_DISPATCH_ENTRY, $row, 'unlock_override', $currentUser),
                'can_finalize' => in_array('finalize', $workflowActionKeys, true),
                'role' => WorkflowPolicy::roleSlug($currentUser),
                'actions' => $workflowActions,
            ],
            'approval_events' => self::approvalEvents(WorkflowPolicy::MODULE_DISPATCH_ENTRY, $id),
            'ownership_summary' => HandoffEngine::summaryForEntity('dispatch_entry', $id),
            'activity_timeline' => AuditLogService::timeline(WorkflowPolicy::MODULE_DISPATCH_ENTRY, $id, 80),
        ]);
    }

    public static function printPdf(int $id, bool $download): void
    {
        if ($id <= 0) {
            self::flash('err', 'Invalid dispatch entry id.');
            header('Location: /dispatch-entries');
            exit;
        }

        $entry = DB::fetchOne(
            'SELECT d.*, p.parts_name, p.parts_number, q.checked_qty AS qc_checked_qty, q.pass_qty AS qc_pass_qty, q.fail_qty AS qc_fail_qty
             FROM dispatch_entries d
             INNER JOIN products p ON p.id = d.product_id
             LEFT JOIN qc_entries q ON q.id = d.qc_entry_id
             WHERE d.id = ? LIMIT 1',
            [$id]
        );

        if (!$entry) {
            self::flash('err', 'Dispatch entry not found.');
            header('Location: /dispatch-entries');
            exit;
        }

        $pdfService = self::pdfService();
        if (!$pdfService->isReady()) {
            self::recordPdfExportFailure('dispatch-entry:' . (int)$id, 'PDF library is not available.');
            self::flash('err', 'PDF library is not available. Install dependencies with composer install.');
            header('Location: /dispatch-entries/edit?id=' . $id);
            exit;
        }

        try {
            $html = self::renderPdfTemplate('DispatchEntries::pdf_dispatch_entry.php', [
                'entry' => $entry,
                'generatedAt' => date('Y-m-d H:i:s'),
            ]);
            $pdf = $pdfService->outputFromHtml($html, [
                'paper' => 'A4',
                'orientation' => 'portrait',
            ]);
            $filename = 'dispatch-entry-' . (int)$entry['id'] . '.pdf';
            self::recordPdfExportSuccess('dispatch-entry:' . (int)$entry['id'], $filename, $download, [
                'record_id' => (int)$entry['id'],
                'route' => '/dispatch-entries/print-pdf',
                'mode' => $download ? 'download' : 'inline',
            ]);
            $pdfService->stream($pdf, $filename, $download);
            exit;
        } catch (\Throwable $e) {
            self::recordPdfExportFailure('dispatch-entry:' . (int)$id, $e->getMessage());
            self::flash('err', 'PDF render failed: ' . $e->getMessage());
            header('Location: /dispatch-entries/edit?id=' . $id);
            exit;
        }
    }

    public static function update(array $input): void
    {
        DispatchWorkflow::ensureSchema();
        WorkflowGovernance::ensureSchema();
        AuditLogService::ensureSchema();

        $id = (int)($input['id'] ?? 0);
        $data = self::sanitize($input);

        if ($id <= 0 || $data['dispatch_date'] === '' || $data['product_id'] <= 0 || $data['dispatchable_qty'] <= 0) {
            self::flash('err', 'Invalid payload.');
            header('Location: /dispatch-entries');
            exit;
        }

        $existing = DB::fetchOne('SELECT * FROM dispatch_entries WHERE id=? LIMIT 1', [$id]);
        if (!$existing) {
            self::flash('err', 'Dispatch entry not found.');
            header('Location: /dispatch-entries');
            exit;
        }

        $existing = WorkflowGovernance::withDefaults($existing);
        if (WorkflowPolicy::isLocked(WorkflowPolicy::MODULE_DISPATCH_ENTRY, $existing)) {
            self::flash('err', 'This dispatch entry is locked. Reopen the record before editing.');
            header('Location: /dispatch-entries/edit?id=' . $id);
            exit;
        }

        $requestedStatus = DispatchWorkflow::normalizeStatus((string)($data['dispatch_status'] ?? ''), (string)($existing['dispatch_status'] ?? DispatchWorkflow::STATUS_READY));
        $workflowAction = self::workflowActionForDispatchStatus((string)($existing['dispatch_status'] ?? ''), $requestedStatus);
        if ($workflowAction !== null) {
            $validation = WorkflowTransitionEngine::validateTransition(
                WorkflowPolicy::MODULE_DISPATCH_ENTRY,
                $existing,
                $workflowAction,
                Auth::user(),
                (string)($data['status_reason'] ?? ''),
                (string)($data['status_note'] ?? '')
            );
            if (!(bool)($validation['ok'] ?? false)) {
                self::flash('err', (string)($validation['message'] ?? 'Dispatch workflow transition is not allowed.'));
                header('Location: /dispatch-entries/edit?id=' . $id);
                exit;
            }
        }

        try {
            $workflow = DispatchWorkflow::prepareForSave($data, $existing, Auth::user());
        } catch (\Throwable $e) {
            self::flash('err', $e->getMessage());
            header('Location: /dispatch-entries/edit?id=' . $id);
            exit;
        }

        $lockAt = $existing['locked_at'] ?? null;
        $lockBy = $existing['locked_by'] ?? null;
        if (strtolower(trim((string)($workflow['dispatch_status'] ?? ''))) === 'dispatched') {
            $lockAt = $lockAt ?: date('Y-m-d H:i:s');
            $lockBy = $lockBy ?: WorkflowGovernance::actorId(Auth::user());
        }

        $oldProductId = (int)($existing['product_id'] ?? 0);

        $updateData = [
            'dispatch_date' => $data['dispatch_date'],
            'daily_order_id' => $data['daily_order_id'] > 0 ? $data['daily_order_id'] : null,
            'production_plan_id' => $data['production_plan_id'] > 0 ? $data['production_plan_id'] : null,
            'production_entry_id' => $data['production_entry_id'] > 0 ? $data['production_entry_id'] : null,
            'qc_entry_id' => $data['qc_entry_id'] > 0 ? $data['qc_entry_id'] : null,
            'product_id' => $data['product_id'],
            'dispatchable_qty' => $data['dispatchable_qty'],
            'destination' => $data['destination'],
            'dispatch_type' => $data['dispatch_type'],
            'dispatch_status' => $workflow['dispatch_status'],
            'workflow_state' => self::workflowStateForDispatchStatus((string)$workflow['dispatch_status'], $existing),
            'remarks' => $data['remarks'],
            'status_reason' => $workflow['status_reason'],
            'status_note' => $workflow['status_note'],
            'released_at' => $workflow['released_at'],
            'blocked_at' => $workflow['blocked_at'],
            'dispatched_at' => $workflow['dispatched_at'],
            'last_transition_at' => $workflow['last_transition_at'],
            'status_updated_by' => $workflow['status_updated_by'],
            'locked_by' => $lockBy,
            'locked_at' => $lockAt,
        ];

        $context = EntityContext::fromUser(Auth::user());
        DispatchEntryService::update($id, $updateData, $context, $existing);

        $db = DB::conn();
        $db->begin_transaction();
        try {
            self::syncLedgerState($id, $data, $workflow['product'], $existing, $workflow['dispatch_status']);
            DispatchWorkflow::recordTransition($id, is_array($workflow['transition'] ?? null) ? $workflow['transition'] : null);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            self::flash('err', 'Update failed: ' . $e->getMessage());
            header('Location: /dispatch-entries/edit?id=' . $id);
            exit;
        }

        self::recalculateCoverage([$oldProductId, $data['product_id']]);
        HandoffTrackingService::syncDispatchEntry($id, (int)($existing['qc_entry_id'] ?? 0));

        $after = DB::fetchOne('SELECT * FROM dispatch_entries WHERE id=? LIMIT 1', [$id]) ?: [];
        AuditLogService::logEvent(
            WorkflowPolicy::MODULE_DISPATCH_ENTRY,
            $id,
            AuditLogService::EVENT_LIFECYCLE,
            AuditLogService::ACTION_UPDATED,
            Auth::user(),
            [
                'app' => 'manufacturing',
                'module' => 'dispatch',
                'old_state' => (string)($existing['dispatch_status'] ?? ''),
                'new_state' => (string)($after['dispatch_status'] ?? ''),
                'reason' => (string)($workflow['status_reason'] ?? ''),
                'note' => 'Dispatch entry updated.',
                'diff' => AuditLogService::diffImportantFields($existing, $after, self::auditDiffFields()),
            ]
        );

        if ($workflowAction !== null && strtolower(trim((string)($existing['dispatch_status'] ?? ''))) !== strtolower(trim((string)($after['dispatch_status'] ?? '')))) {
            self::auditWorkflowLifecycle($id, $existing, $after, $workflowAction, Auth::user(), (string)($workflow['status_reason'] ?? ''), (string)($workflow['status_note'] ?? ''));
        }

        self::flash('ok', self::createSuccessMessage((string)$workflow['dispatch_status'], (string)($existing['dispatch_status'] ?? '')));
    }

    public static function delete(int $id): void
    {
        WorkflowGovernance::ensureSchema();
        AuditLogService::ensureSchema();

        if ($id <= 0) {
            self::flash('err', 'Invalid dispatch entry id.');
            return;
        }
        $existing = DB::fetchOne('SELECT id, product_id, qc_entry_id FROM dispatch_entries WHERE id=? LIMIT 1', [$id]);
        if (!$existing) {
            self::flash('err', 'Dispatch entry not found.');
            return;
        }

        $fullExisting = WorkflowGovernance::fetchRecord(WorkflowPolicy::MODULE_DISPATCH_ENTRY, $id);
        if (is_array($fullExisting) && WorkflowPolicy::isLocked(WorkflowPolicy::MODULE_DISPATCH_ENTRY, WorkflowGovernance::withDefaults($fullExisting))) {
            self::flash('err', 'Locked dispatch entries cannot be deleted. Reopen first with governance reason.');
            return;
        }

        $productId = (int)($existing['product_id'] ?? 0);

        $db = DB::conn();
        $db->begin_transaction();
        try {
            $context = EntityContext::fromUser(Auth::user());
            self::deleteLedgerForEntry($id);
            if ($productId > 0 && self::hasLedgerTable()) {
                self::recalculateBalancesForProduct($productId);
            }
            DispatchEntryService::delete($id, $context);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            self::flash('err', 'Delete failed: ' . $e->getMessage());
            return;
        }

        self::recalculateCoverage([$productId]);
        HandoffTrackingService::syncDispatchEntry($id, (int)($existing['qc_entry_id'] ?? 0));

        AuditLogService::logEvent(
            WorkflowPolicy::MODULE_DISPATCH_ENTRY,
            $id,
            AuditLogService::EVENT_LIFECYCLE,
            AuditLogService::ACTION_DELETED,
            Auth::user(),
            [
                'app' => 'manufacturing',
                'module' => 'dispatch',
                'old_state' => (string)($fullExisting['dispatch_status'] ?? ''),
                'note' => 'Dispatch entry deleted.',
                'diff' => AuditLogService::diffImportantFields((array)$fullExisting, [], self::auditDiffFields()),
            ]
        );

        self::flash('ok', self::hasLedgerTable() ? 'Dispatch entry deleted and ledger adjusted.' : 'Dispatch entry deleted.');
    }

    public static function quickStatusUpdate(int $id, string $status, string $redirect, string $reason = '', string $note = '', ?array $currentUser = null): void
    {
        self::transition([
            'id' => $id,
            'target_status' => $status,
            'redirect' => $redirect,
            'reason' => $reason,
            'note' => $note,
        ], $currentUser);
    }

    public static function transition(array $input, ?array $currentUser = null): void
    {
        require_once __DIR__ . '/../DispatchEntryService.php';

        DispatchWorkflow::ensureSchema();
        WorkflowGovernance::ensureSchema();
        AuditLogService::ensureSchema();

        $id = (int)($input['id'] ?? 0);
        $redirect = self::normalizeRedirect((string)($input['redirect'] ?? '/apps/manufacturing/dispatch-ops'), '/apps/manufacturing/dispatch-ops');

        if ($id <= 0) {
            self::flash('err', 'Invalid dispatch entry id.');
            header('Location: ' . $redirect);
            exit;
        }

        $entry = WorkflowGovernance::fetchRecord(WorkflowPolicy::MODULE_DISPATCH_ENTRY, $id);
        if (!$entry) {
            self::flash('err', 'Dispatch entry not found.');
            header('Location: ' . $redirect);
            exit;
        }
        $entry = WorkflowGovernance::withDefaults($entry);
        $targetStatus = DispatchWorkflow::normalizeStatus((string)($input['target_status'] ?? $input['status'] ?? ''), (string)($entry['dispatch_status'] ?? DispatchWorkflow::STATUS_READY));

        if (WorkflowPolicy::isLocked(WorkflowPolicy::MODULE_DISPATCH_ENTRY, $entry) && $targetStatus !== DispatchWorkflow::STATUS_READY) {
            self::flash('err', 'This dispatch entry is locked. Reopen it before lifecycle changes.');
            header('Location: ' . $redirect);
            exit;
        }

        $workflowAction = self::workflowActionForDispatchStatus((string)($entry['dispatch_status'] ?? ''), $targetStatus);
        if ($workflowAction !== null) {
            $validation = WorkflowTransitionEngine::validateTransition(
                WorkflowPolicy::MODULE_DISPATCH_ENTRY,
                $entry,
                $workflowAction,
                $currentUser,
                trim((string)($input['reason'] ?? '')),
                trim((string)($input['note'] ?? ''))
            );
            if (!(bool)($validation['ok'] ?? false)) {
                self::flash('err', (string)($validation['message'] ?? 'Dispatch workflow transition is not allowed.'));
                header('Location: ' . $redirect);
                exit;
            }
        }

        try {
            $workflow = DispatchWorkflow::transition(
                $id,
                (string)($input['target_status'] ?? $input['status'] ?? ''),
                trim((string)($input['reason'] ?? '')),
                trim((string)($input['note'] ?? '')),
                $currentUser
            );
        } catch (\Throwable $e) {
            self::flash('err', $e->getMessage());
            header('Location: ' . $redirect);
            exit;
        }

        $existing = is_array($workflow['entry'] ?? null) ? $workflow['entry'] : [];
        $updatedData = $existing;
        $updatedData['status_reason'] = $workflow['status_reason'];
        $updatedData['status_note'] = $workflow['status_note'];
        $updatedData['dispatch_status'] = $workflow['dispatch_status'];

        $lockAt = $existing['locked_at'] ?? null;
        $lockBy = $existing['locked_by'] ?? null;
        if (strtolower(trim((string)($workflow['dispatch_status'] ?? ''))) === 'dispatched') {
            $lockAt = $lockAt ?: date('Y-m-d H:i:s');
            $lockBy = $lockBy ?: WorkflowGovernance::actorId($currentUser);
        }

        $context = \App\Core\EntityContext::fromUser($currentUser);

        $db = DB::conn();
        $db->begin_transaction();
        try {
            \Plugins\DispatchEntries\DispatchEntryService::transitionStatus(
                $id,
                (string)$workflow['dispatch_status'],
                $context,
                trim((string)($input['reason'] ?? '')),
                trim((string)($input['note'] ?? ''))
            );

            self::syncLedgerState($id, $updatedData, $workflow['product'], $existing, (string)$workflow['dispatch_status']);
            DispatchWorkflow::recordTransition($id, is_array($workflow['transition'] ?? null) ? $workflow['transition'] : null);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            self::flash('err', 'Dispatch transition failed: ' . $e->getMessage());
            header('Location: ' . $redirect);
            exit;
        }

        self::recalculateCoverage([(int)($existing['product_id'] ?? 0)]);
        HandoffTrackingService::syncDispatchEntry($id, (int)($existing['qc_entry_id'] ?? 0));

        $after = DB::fetchOne('SELECT * FROM dispatch_entries WHERE id=? LIMIT 1', [$id]) ?: [];
        AuditLogService::logEvent(
            WorkflowPolicy::MODULE_DISPATCH_ENTRY,
            $id,
            AuditLogService::EVENT_LIFECYCLE,
            AuditLogService::ACTION_UPDATED,
            $currentUser,
            [
                'app' => 'manufacturing',
                'module' => 'dispatch',
                'old_state' => (string)($existing['dispatch_status'] ?? ''),
                'new_state' => (string)($after['dispatch_status'] ?? ''),
                'reason' => (string)($workflow['status_reason'] ?? ''),
                'note' => (string)($workflow['status_note'] ?? 'Dispatch status transition.'),
                'diff' => AuditLogService::diffImportantFields($existing, $after, self::auditDiffFields()),
            ]
        );
        if ($workflowAction !== null) {
            self::auditWorkflowLifecycle($id, $existing, $after, $workflowAction, $currentUser, (string)($workflow['status_reason'] ?? ''), (string)($workflow['status_note'] ?? ''));
        }

        self::flash('ok', self::createTransitionMessage((string)($input['target_status'] ?? $input['status'] ?? ''), (string)$workflow['dispatch_status']));
        header('Location: ' . $redirect);
        exit;
    }

    public static function approvalAction(array $input, ?array $currentUser = null): void
    {
        require_once __DIR__ . '/../DispatchEntryService.php';
        require_once __DIR__ . '/../Services/DispatchWorkflow.php';

        WorkflowTransitionEngine::ensureSchema();
        DispatchWorkflow::ensureSchema();

        $id = (int)($input['id'] ?? 0);
        $rawAction = strtolower(trim((string)($input['action'] ?? '')));
        $action = $rawAction === 'unlock_override' ? 'reopen' : $rawAction;
        $note = trim((string)($input['note'] ?? ''));
        $reason = trim((string)($input['reason'] ?? ''));
        $redirect = self::normalizeRedirect((string)($input['redirect'] ?? ('/dispatch-entries/edit?id=' . $id)), '/dispatch-entries');

        if ($id <= 0 || !in_array($action, ['submit', 'approve', 'reject', 'reopen', 'hold', 'resume', 'finalize', 'cancel', 'handoff'], true)) {
            self::flash('err', 'Invalid governance request.');
            header('Location: /dispatch-entries');
            exit;
        }

        $existing = WorkflowTransitionEngine::fetchRecord(WorkflowPolicy::MODULE_DISPATCH_ENTRY, $id);
        if (!$existing) {
            self::flash('err', 'Dispatch entry not found.');
            header('Location: /dispatch-entries');
            exit;
        }
        if ($rawAction === 'unlock_override') {
            $existingWithDefaults = WorkflowGovernance::withDefaults($existing);
            if (!WorkflowPolicy::canOverrideLock(WorkflowPolicy::MODULE_DISPATCH_ENTRY, $currentUser)
                || !WorkflowPolicy::isLocked(WorkflowPolicy::MODULE_DISPATCH_ENTRY, $existingWithDefaults)) {
                self::flash('err', 'You are not allowed to override lock for this record.');
                header('Location: ' . $redirect);
                exit;
            }
            if ($reason === '') {
                self::flash('err', 'A reason is required for override actions.');
                header('Location: ' . $redirect);
                exit;
            }
            if ($note === '') {
                $note = 'Unlock override requested.';
            }
        }

        if (($action === 'approve' || $action === 'finalize') && ($validationError = WorkflowPolicy::validateForApproval(WorkflowPolicy::MODULE_DISPATCH_ENTRY, $existing)) !== null) {
            self::flash('err', $validationError);
            header('Location: ' . $redirect);
            exit;
        }

        if (\Plugins\DispatchEntries\DispatchEntryService::isGovernanceOnlyAction($action)) {
            try {
                $context = \App\Core\EntityContext::fromUser($currentUser);
                \Plugins\DispatchEntries\DispatchEntryService::rejectDispatch($id, $context, $reason, $note);
                self::flash('ok', 'Governance action applied: ' . ucfirst($action) . '.');
                header('Location: ' . $redirect);
                exit;
            } catch (\Throwable $e) {
                self::flash('err', $e->getMessage());
                header('Location: ' . $redirect);
                exit;
            }
        }

        if (\Plugins\DispatchEntries\DispatchEntryService::isOperationalAction($action)) {
            $result = WorkflowTransitionEngine::transition(
                WorkflowPolicy::MODULE_DISPATCH_ENTRY,
                $id,
                $action,
                $currentUser,
                $reason,
                $note
            );

            if (!(bool)($result['ok'] ?? false)) {
                self::flash('err', (string)($result['message'] ?? 'Operational action failed.'));
                header('Location: ' . $redirect);
                exit;
            }

            self::flash('ok', 'Governance action applied: ' . ucfirst($action) . '.');
            header('Location: ' . $redirect);
            exit;
        }

        $targetStatus = \Plugins\DispatchEntries\DispatchEntryService::actionToStatus($action);

        if ($targetStatus !== null) {
            try {
                $context = \App\Core\EntityContext::fromUser($currentUser);
                \Plugins\DispatchEntries\DispatchEntryService::transitionStatus($id, $targetStatus, $context, $reason, $note);
                self::flash('ok', 'Governance action applied: ' . ucfirst($action) . '.');
                header('Location: ' . $redirect);
                exit;
            } catch (\Throwable $e) {
                self::flash('err', $e->getMessage());
                header('Location: ' . $redirect);
                exit;
            }
        }

        $result = WorkflowTransitionEngine::transition(
            WorkflowPolicy::MODULE_DISPATCH_ENTRY,
            $id,
            $action,
            $currentUser,
            $reason,
            $note
        );

        if (!(bool)($result['ok'] ?? false)) {
            self::flash('err', (string)($result['message'] ?? 'Workflow transition failed.'));
            header('Location: /dispatch-entries');
            exit;
        }

        self::flash('ok', 'Governance action applied: ' . ucfirst($action) . '.');
        header('Location: ' . $redirect);
        exit;
    }

    private static function syncLedgerForEntry(int $entryId, int $productId, float $dispatchQty, string $notes): void
    {
        if (!self::hasLedgerTable() || $productId <= 0 || $dispatchQty <= 0) {
            return;
        }

        $currentBalanceRow = DB::fetchOne('SELECT COALESCE(SUM(qty_delta), 0) AS balance FROM stock_ledger_entries WHERE product_id = ?', [$productId]);
        $currentBalance = (float)($currentBalanceRow['balance'] ?? 0);
        $qtyDelta = -abs($dispatchQty);
        $balanceAfter = $currentBalance + $qtyDelta;

        DB::query(
            'INSERT INTO stock_ledger_entries (product_id, movement_type, qty_delta, balance_after, reference_no, source_module, source_id, notes) VALUES (?,?,?,?,?,?,?,?)',
            [$productId, 'DISPATCH_OUT', $qtyDelta, $balanceAfter, 'DE-' . $entryId, 'DispatchEntries', $entryId, $notes]
        );

        self::recalculateBalancesForProduct($productId);
    }

    private static function deleteLedgerForEntry(int $entryId): void
    {
        if (!self::hasLedgerTable()) {
            return;
        }

        DB::query('DELETE FROM stock_ledger_entries WHERE source_module=? AND source_id=?', ['DispatchEntries', $entryId]);
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

    private static function currentStockBalance(int $productId): float
    {
        if (!self::hasLedgerTable() || $productId <= 0) {
            return 0.0;
        }

        $row = DB::fetchOne('SELECT COALESCE(SUM(qty_delta), 0) AS balance FROM stock_ledger_entries WHERE product_id = ?', [$productId]);
        return (float)($row['balance'] ?? 0);
    }

    private static function products(): array
    {
        return DB::fetchAll('SELECT id, parts_name, parts_number FROM products WHERE is_active=1 ORDER BY parts_name ASC LIMIT 1000');
    }

    private static function dailyOrders(): array
    {
        return DB::fetchAll('SELECT id, customer_name, order_date FROM daily_orders ORDER BY id DESC LIMIT 300');
    }

    private static function productionPlans(): array
    {
        return DB::fetchAll('SELECT id, plan_date, planned_qty FROM production_plans ORDER BY id DESC LIMIT 300');
    }

    private static function productionEntries(): array
    {
        return DB::fetchAll('SELECT id, production_date, good_qty FROM production_entries ORDER BY id DESC LIMIT 300');
    }

    private static function qcEntries(): array
    {
        return DB::fetchAll('SELECT id, checked_qty, pass_qty, fail_qty FROM qc_entries ORDER BY id DESC LIMIT 300');
    }

    private static function sanitize(array $input): array
    {
        return [
            'dispatch_date' => trim((string)($input['dispatch_date'] ?? '')),
            'daily_order_id' => (int)($input['daily_order_id'] ?? 0),
            'production_plan_id' => (int)($input['production_plan_id'] ?? 0),
            'production_entry_id' => (int)($input['production_entry_id'] ?? 0),
            'qc_entry_id' => (int)($input['qc_entry_id'] ?? 0),
            'product_id' => (int)($input['product_id'] ?? 0),
            'dispatchable_qty' => (float)($input['dispatchable_qty'] ?? 0),
            'destination' => trim((string)($input['destination'] ?? '')),
            'dispatch_type' => trim((string)($input['dispatch_type'] ?? 'Regular')),
            'dispatch_status' => trim((string)($input['dispatch_status'] ?? DispatchWorkflow::STATUS_READY)),
            'remarks' => trim((string)($input['remarks'] ?? '')),
            'status_reason' => trim((string)($input['status_reason'] ?? '')),
            'status_note' => trim((string)($input['status_note'] ?? '')),
        ];
    }

    private static function normalizeDraftDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return date('Y-m-d');
        }

        $ts = strtotime($value);
        return $ts === false ? date('Y-m-d') : date('Y-m-d', $ts);
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
        $_SESSION['dispatch_entries_flash_' . $key] = $msg;
    }

    private static function pdfService(): PdfService
    {
        static $service = null;
        if ($service instanceof PdfService) {
            return $service;
        }

        $service = new PdfService([
            'defaultFont' => 'DejaVu Sans',
        ]);
        return $service;
    }

    private static function renderPdfTemplate(string $viewKey, array $data): string
    {
        $view = new View(APP_ROOT . '/public/views');
        $view->addNamespace('DispatchEntries', rtrim(PackageManager::pluginSourcePath('DispatchEntries'), '/') . '/Views');

        ob_start();
        $view->render($viewKey, $data);
        return (string)ob_get_clean();
    }

    private static function exportHistory(): ExportHistoryService
    {
        static $history = null;
        if (!$history instanceof ExportHistoryService) {
            $history = new ExportHistoryService();
        }
        return $history;
    }

    /**
     * @param array<string,mixed> $summary
     */
    private static function recordPdfExportSuccess(string $targetKey, string $filename, bool $download, array $summary = []): void
    {
        self::exportHistory()->recordSuccess(
            'module',
            $targetKey,
            'manufacturing',
            'pdf',
            $filename,
            'stream://' . $filename,
            $summary,
            $download ? 'download' : ''
        );
    }

    private static function recordPdfExportFailure(string $targetKey, string $errorText): void
    {
        self::exportHistory()->recordFailure('module', $targetKey, 'manufacturing', 'pdf', $errorText);
    }

    private static function pullFlash(string $key): string
    {
        $k = 'dispatch_entries_flash_' . $key;
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
     * @param array<string,mixed> $data
     * @param array<string,mixed> $product
     * @param array<string,mixed>|null $existingRow
     */
    private static function syncLedgerState(int $entryId, array $data, array $product, ?array $existingRow, string $status): void
    {
        if (!self::hasLedgerTable()) {
            return;
        }

        $productId = (int)($data['product_id'] ?? 0);
        $dispatchQty = (float)($data['dispatchable_qty'] ?? 0);
        $oldProductId = (int)($existingRow['product_id'] ?? 0);

        self::deleteLedgerForEntry($entryId);

        if ($oldProductId > 0 && $oldProductId !== $productId) {
            self::recalculateBalancesForProduct($oldProductId);
        }

        if (!DispatchWorkflow::countsAsReleased($status) || !DispatchWorkflow::canUseLedger($product)) {
            if ($productId > 0) {
                self::recalculateBalancesForProduct($productId);
            }
            return;
        }

        self::syncLedgerForEntry($entryId, $productId, $dispatchQty, self::buildLedgerNotes($data));
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function buildLedgerNotes(array $data): string
    {
        $parts = [];
        $remarks = trim((string)($data['remarks'] ?? ''));
        $reason = trim((string)($data['status_reason'] ?? ''));
        $note = trim((string)($data['status_note'] ?? ''));
        if ($remarks !== '') {
            $parts[] = $remarks;
        }
        if ($reason !== '') {
            $parts[] = 'Workflow: ' . $reason;
        }
        if ($note !== '') {
            $parts[] = $note;
        }
        return implode(' | ', $parts);
    }

    private static function createSuccessMessage(string $newStatus, ?string $oldStatus): string
    {
        $newStatus = DispatchWorkflow::normalizeStatus($newStatus, DispatchWorkflow::STATUS_READY);
        if ($oldStatus === null || $oldStatus === '') {
            return match ($newStatus) {
                DispatchWorkflow::STATUS_PARTIAL => 'Dispatch entry created and released as Partial.',
                DispatchWorkflow::STATUS_DISPATCHED => 'Dispatch entry created and released as Dispatched.',
                default => 'Dispatch entry created in ' . $newStatus . ' status.',
            };
        }

        return 'Dispatch entry updated to ' . $newStatus . '.';
    }

    private static function createTransitionMessage(string $requestedStatus, string $actualStatus): string
    {
        $requested = DispatchWorkflow::normalizeStatus($requestedStatus, DispatchWorkflow::STATUS_READY);
        $actual = DispatchWorkflow::normalizeStatus($actualStatus, $requested);
        if ($requested === DispatchWorkflow::STATUS_DISPATCHED && $actual === DispatchWorkflow::STATUS_PARTIAL) {
            return 'Dispatch released as Partial because linked order balance remains open.';
        }
        return 'Dispatch status updated to ' . $actual . '.';
    }

    private static function workflowActionForDispatchStatus(string $currentStatus, string $nextStatus): ?string
    {
        $current = strtolower(trim($currentStatus));
        $next = strtolower(trim($nextStatus));

        if ($next === '' || $next === $current) {
            return null;
        }

        if ($next === 'hold' || $next === 'blocked') {
            return 'hold';
        }

        if (($current === 'hold' || $current === 'blocked') && in_array($next, ['ready', 'partial'], true)) {
            return 'resume';
        }

        if (in_array($next, ['dispatched', 'completed', 'closed'], true)) {
            return 'finalize';
        }

        if (in_array($next, ['cancelled', 'canceled'], true)) {
            return 'cancel';
        }

        if ($next === 'ready' && $current === 'draft') {
            return 'submit';
        }

        return null;
    }

    private static function workflowStateForDispatchStatus(string $status, array $record): string
    {
        $normalized = strtolower(trim($status));
        if ($normalized === 'draft') {
            return 'draft';
        }
        if (in_array($normalized, ['hold', 'blocked'], true)) {
            return 'hold';
        }
        if (in_array($normalized, ['dispatched', 'completed', 'closed', 'delivered'], true)) {
            return 'finalized';
        }
        if (in_array($normalized, ['cancelled', 'canceled'], true)) {
            return 'cancelled';
        }

        $approval = WorkflowPolicy::normalizeApprovalStatus((string)($record['approval_status'] ?? ''), WorkflowPolicy::APPROVAL_DRAFT);
        if ($approval === WorkflowPolicy::APPROVAL_APPROVED) {
            return 'approved';
        }
        if ($approval === WorkflowPolicy::APPROVAL_REJECTED) {
            return 'rejected';
        }
        if ($approval === WorkflowPolicy::APPROVAL_REOPENED) {
            return 'reopened';
        }
        if ($approval === WorkflowPolicy::APPROVAL_PENDING) {
            return 'submitted';
        }

        return 'draft';
    }

    /**
     * @param array<string,mixed> $before
     * @param array<string,mixed> $after
     */
    private static function auditWorkflowLifecycle(int $id, array $before, array $after, string $workflowAction, ?array $actor, string $reason, string $note): void
    {
        $taxonomyAction = match (strtolower(trim($workflowAction))) {
            'submit' => AuditLogService::ACTION_SUBMITTED,
            'approve' => AuditLogService::ACTION_APPROVED,
            'reject' => AuditLogService::ACTION_REJECTED,
            'reopen' => AuditLogService::ACTION_REOPENED,
            'hold' => AuditLogService::ACTION_HELD,
            'resume' => AuditLogService::ACTION_RESUMED,
            'finalize' => AuditLogService::ACTION_FINALIZED,
            'cancel' => AuditLogService::ACTION_CANCELLED,
            default => strtolower(trim($workflowAction)),
        };

        AuditLogService::logEvent(
            WorkflowPolicy::MODULE_DISPATCH_ENTRY,
            $id,
            AuditLogService::EVENT_WORKFLOW,
            $taxonomyAction,
            $actor,
            [
                'app' => 'manufacturing',
                'module' => 'dispatch',
                'old_state' => (string)($before['dispatch_status'] ?? ''),
                'new_state' => (string)($after['dispatch_status'] ?? ''),
                'reason' => $reason,
                'note' => $note,
                'metadata' => ['workflow_action' => $workflowAction, 'source' => 'dispatch_status_transition'],
                'diff' => AuditLogService::diffImportantFields($before, $after, ['dispatch_status', 'workflow_state', 'status_reason', 'status_note', 'approval_status', 'locked_at']),
            ]
        );
    }

    /**
     * @return array<int,string>
     */
    private static function auditDiffFields(): array
    {
        return [
            'dispatch_date',
            'daily_order_id',
            'production_plan_id',
            'production_entry_id',
            'qc_entry_id',
            'product_id',
            'dispatchable_qty',
            'destination',
            'dispatch_type',
            'dispatch_status',
            'status_reason',
            'status_note',
            'remarks',
            'approval_status',
            'workflow_state',
            'locked_at',
        ];
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
}
