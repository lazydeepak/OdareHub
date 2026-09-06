<?php
declare(strict_types=1);

namespace Plugins\ProductionPlans\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\EntityContext;
use App\Core\ManufacturingPostPersistHelper;
use App\Core\PackageManager;
use App\Core\PdfService;
use App\Core\View;
use App\Services\ExportHistoryService;
use Plugins\Coverage\Services\CoverageService;
use Plugins\Workflow\Services\WorkflowGovernance;
use Plugins\Workflow\Services\WorkflowPolicy;
use Plugins\Workflow\Services\WorkflowTransitionEngine;
use Plugins\ProductionPlans\ProductionPlanService;

require_once __DIR__ . '/../ProductionPlanService.php';

final class ProductionPlansController
{
    public static function index(View $view): void
    {
        WorkflowGovernance::ensureSchema();
        self::ensureAddedByColumn();

        $status = trim((string)($_GET['status'] ?? ''));
        $machineId = (int)($_GET['machine_id'] ?? 0);
        $planDate = trim((string)($_GET['plan_date'] ?? ''));

        $sql = "SELECT pp.*, m.machine_no, m.machine_name, p.parts_name, p.parts_number
                FROM production_plans pp
                INNER JOIN machines m ON m.id = pp.machine_id
                INNER JOIN products p ON p.id = pp.product_id
                WHERE 1=1";
        $params = [];

        if ($status !== '') {
            $sql .= ' AND pp.status = ?';
            $params[] = $status;
        }
        if ($machineId > 0) {
            $sql .= ' AND pp.machine_id = ?';
            $params[] = $machineId;
        }
        if ($planDate !== '') {
            $sql .= ' AND pp.plan_date = ?';
            $params[] = $planDate;
        }

        $sql .= ' ORDER BY pp.plan_date DESC, pp.machine_id ASC, pp.sequence_no ASC, pp.id DESC LIMIT 400';

        $view->render('ProductionPlans::index.php', [
            'pageTitle' => 'Production Plans',
            'rows' => DB::fetchAll($sql, $params),
            'machines' => self::machines(),
            'status' => $status,
            'machine_id' => $machineId,
            'plan_date' => $planDate,
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
        ]);
    }

    public static function addForm(View $view): void
    {
        WorkflowGovernance::ensureSchema();
        self::ensureAddedByColumn();

        $old = $_SESSION['production_plans_old'] ?? [];
        if (($old['plan_date'] ?? '') === '' && trim((string)($_GET['plan_date'] ?? '')) !== '') {
            $old['plan_date'] = trim((string)$_GET['plan_date']);
        }
        if ((int)($old['machine_id'] ?? 0) <= 0 && (int)($_GET['machine_id'] ?? 0) > 0) {
            $old['machine_id'] = (int)$_GET['machine_id'];
        }
        if ((int)($old['product_id'] ?? 0) <= 0 && (int)($_GET['product_id'] ?? 0) > 0) {
            $old['product_id'] = (int)$_GET['product_id'];
        }
        if ((string)($old['planned_qty'] ?? '') === '' && trim((string)($_GET['planned_qty'] ?? '')) !== '') {
            $old['planned_qty'] = trim((string)$_GET['planned_qty']);
        }
        if ((string)($old['status'] ?? '') === '' && trim((string)($_GET['status'] ?? '')) !== '') {
            $old['status'] = trim((string)$_GET['status']);
        }
        if ((string)($old['plan_type'] ?? '') === '' && trim((string)($_GET['plan_type'] ?? '')) !== '') {
            $old['plan_type'] = trim((string)$_GET['plan_type']);
        }
        if ((string)($old['reference_doctype'] ?? '') === '' && trim((string)($_GET['reference_doctype'] ?? '')) !== '') {
            $old['reference_doctype'] = trim((string)$_GET['reference_doctype']);
        }
        if ((string)($old['reference_name'] ?? '') === '' && trim((string)($_GET['reference_name'] ?? '')) !== '') {
            $old['reference_name'] = trim((string)$_GET['reference_name']);
        }
        if ((string)($old['added_by'] ?? '') === '') {
            $old['added_by'] = self::defaultAddedBy(Auth::user());
        }

        $currentUser = Auth::user();
        $canEditAddedBy = self::canEditAddedBy($currentUser);

        $view->render('ProductionPlans::add.php', [
            'pageTitle' => 'Add Production Plan',
            'machines' => self::machines(),
            'products' => self::products(),
            'old' => $old,
            'can_edit_added_by' => $canEditAddedBy,
            'error' => self::pullFlash('err'),
        ]);
        unset($_SESSION['production_plans_old']);
    }

    public static function create(array $input): void
    {
        WorkflowGovernance::ensureSchema();
        self::ensureAddedByColumn();

        $data = self::sanitize($input);
        $data['added_by'] = self::normalizeAddedBy((string)($data['added_by'] ?? ''), Auth::user());
        $_SESSION['production_plans_old'] = $data;

        if ($data['plan_date'] === '' || $data['machine_id'] <= 0 || $data['product_id'] <= 0 || $data['planned_qty'] <= 0) {
            self::flash('err', 'Plan date, machine, product, and planned qty are required.');
            header('Location: /production-plans/add');
            exit;
        }

        try {
            $context = EntityContext::fromUser(Auth::user());
            $insertData = [
                'plan_date' => $data['plan_date'],
                'machine_id' => $data['machine_id'],
                'product_id' => $data['product_id'],
                'planned_qty' => $data['planned_qty'],
                'sequence_no' => $data['sequence_no'],
                'runtime' => $data['runtime'],
                'status' => $data['status'],
                'plan_type' => $data['plan_type'],
                'reference_doctype' => $data['reference_doctype'],
                'reference_name' => $data['reference_name'],
                'coverage_pct' => $data['coverage_pct'],
                'shortage_qty' => $data['shortage_qty'],
                'auto_created' => $data['auto_created'],
                'notes' => $data['notes'],
                'added_by' => $data['added_by'],
            ];
            ProductionPlanService::create($insertData, $context);
        } catch (\Throwable $e) {
            self::flash('err', 'Create failed: ' . $e->getMessage());
            header('Location: /production-plans/add');
            exit;
        }

        self::recalculateCoverage([$data['product_id']]);

        unset($_SESSION['production_plans_old']);
        self::flash('ok', 'Production plan created.');
    }

    public static function createDraft(array $input): void
    {
        WorkflowGovernance::ensureSchema();
        self::ensureAddedByColumn();

        $productId = (int)($input['product_id'] ?? 0);
        $dailyOrderId = (int)($input['daily_order_id'] ?? 0);
        $shortageQty = round(max(0.0, (float)($input['shortage_qty'] ?? 0)), 2);
        $planType = trim((string)($input['plan_type'] ?? 'Recovery'));
        $referenceDoctype = trim((string)($input['reference_doctype'] ?? 'DailyOrder'));
        $referenceName = trim((string)($input['reference_name'] ?? ($dailyOrderId > 0 ? (string)$dailyOrderId : '')));
        $fallback = self::normalizeRedirect((string)($input['failure_fallback'] ?? '/manufacturing/coverage'), '/manufacturing/coverage');

        if ($productId <= 0 || $shortageQty <= 0) {
            self::flash('err', 'Draft plan requires a product and shortage quantity.');
            header('Location: ' . $fallback);
            exit;
        }

        $machineId = self::resolveDraftMachineId($productId);
        if ($machineId <= 0) {
            self::flash('err', 'No active machine mapping is available for this part. Add a part-machine map before starting a draft plan.');
            header('Location: ' . $fallback);
            exit;
        }

        $planDate = self::normalizeDraftDate((string)($input['required_date'] ?? ''));
        $notes = self::buildDraftNotes([
            'Coverage recovery draft created from dashboard.',
            $dailyOrderId > 0 ? 'Daily Order #' . $dailyOrderId : '',
            'Shortage qty ' . number_format($shortageQty, 2, '.', ''),
        ]);

        $currentUser = Auth::user();
        $addedBy = self::defaultAddedBy($currentUser);

        $context = EntityContext::fromUser($currentUser);
        $draftId = ProductionPlanService::create([
            'plan_date' => $planDate,
            'machine_id' => $machineId,
            'product_id' => $productId,
            'planned_qty' => $shortageQty,
            'sequence_no' => 1,
            'runtime' => null,
            'status' => 'Draft',
            'plan_type' => $planType !== '' ? $planType : 'Recovery',
            'reference_doctype' => $referenceDoctype,
            'reference_name' => $referenceName,
            'coverage_pct' => 0,
            'shortage_qty' => $shortageQty,
            'auto_created' => 0,
            'notes' => $notes,
            'added_by' => $addedBy,
        ], $context);
        self::flash('ok', 'Draft production plan created.');
        header('Location: /production-plans/edit?id=' . $draftId);
        exit;
    }

    public static function editForm(View $view, int $id): void
    {
        WorkflowGovernance::ensureSchema();
        self::ensureAddedByColumn();

        if ($id <= 0) {
            self::flash('err', 'Invalid production plan id.');
            header('Location: /production-plans');
            exit;
        }

        $row = DB::fetchOne('SELECT pp.*, m.machine_no, m.machine_name, p.parts_name, p.parts_number FROM production_plans pp LEFT JOIN machines m ON m.id = pp.machine_id LEFT JOIN products p ON p.id = pp.product_id WHERE pp.id=? LIMIT 1', [$id]);
        if (!$row) {
            self::flash('err', 'Production plan not found.');
            header('Location: /production-plans');
            exit;
        }

        $row = WorkflowGovernance::withDefaults($row);
        $currentUser = Auth::user();
        $canEditPlan = self::canEditPlan($currentUser);
        $canEditAddedBy = self::canEditAddedBy($currentUser);
        $workflowActions = WorkflowTransitionEngine::allowedActions(WorkflowPolicy::MODULE_PRODUCTION_PLAN, $row, $currentUser);
        $workflowActionKeys = array_column($workflowActions, 'action');

        $view->render('ProductionPlans::edit.php', [
            'pageTitle' => 'Edit Production Plan',
            'row' => $row,
            'machines' => self::machines(),
            'products' => self::products(),
            'error' => self::pullFlash('err'),
            'pdf_ready' => self::pdfService()->isReady(),
            'can_edit_plan' => $canEditPlan,
            'can_edit_added_by' => $canEditAddedBy,
            'governance' => [
                'locked' => WorkflowPolicy::isLocked(WorkflowPolicy::MODULE_PRODUCTION_PLAN, $row),
                'can_submit' => in_array('submit', $workflowActionKeys, true),
                'can_approve' => in_array('approve', $workflowActionKeys, true),
                'can_reject' => in_array('reject', $workflowActionKeys, true),
                'can_reopen' => in_array('reopen', $workflowActionKeys, true),
                'can_override' => WorkflowPolicy::canTransitionApproval(WorkflowPolicy::MODULE_PRODUCTION_PLAN, $row, 'unlock_override', $currentUser),
                'role' => WorkflowPolicy::roleSlug($currentUser),
                'actions' => $workflowActions,
            ],
            'approval_events' => self::approvalEvents(WorkflowPolicy::MODULE_PRODUCTION_PLAN, $id),
        ]);
    }

    public static function update(array $input): void
    {
        WorkflowGovernance::ensureSchema();
        self::ensureAddedByColumn();

        $currentUser = Auth::user();
        $canEditPlan = self::canEditPlan($currentUser);
        $canEditAddedBy = self::canEditAddedBy($currentUser);

        $id = (int)($input['id'] ?? 0);
        $data = self::sanitize($input);
        $requestedAddedBy = self::normalizeAddedBy((string)($input['added_by'] ?? ''), $currentUser);

        if (!$canEditPlan && !$canEditAddedBy) {
            self::flash('err', 'You are not allowed to edit this production plan.');
            header('Location: /production-plans/edit?id=' . $id);
            exit;
        }

        if ($canEditPlan && ($id <= 0 || $data['plan_date'] === '' || $data['machine_id'] <= 0 || $data['product_id'] <= 0 || $data['planned_qty'] <= 0)) {
            self::flash('err', 'Invalid payload.');
            header('Location: /production-plans');
            exit;
        }

        if ($id <= 0) {
            self::flash('err', 'Invalid payload.');
            header('Location: /production-plans');
            exit;
        }

        $existing = DB::fetchOne('SELECT * FROM production_plans WHERE id=? LIMIT 1', [$id]);
        if (!$existing) {
            self::flash('err', 'Production plan not found.');
            header('Location: /production-plans');
            exit;
        }
        $existing = WorkflowGovernance::withDefaults($existing);

        if ($canEditPlan && WorkflowPolicy::isLocked(WorkflowPolicy::MODULE_PRODUCTION_PLAN, $existing)) {
            self::flash('err', 'This production plan is locked. Reopen the record before editing.');
            header('Location: /production-plans/edit?id=' . $id);
            exit;
        }

        if (!$canEditPlan) {
            if (!$canEditAddedBy) {
                self::flash('err', 'You are not allowed to edit this production plan.');
                header('Location: /production-plans/edit?id=' . $id);
                exit;
            }

            $context = EntityContext::fromUser($currentUser);
            ProductionPlanService::update(
                $id,
                ['added_by' => $requestedAddedBy],
                $context,
                $existing
            );
            self::flash('ok', 'Added by updated.');
            return;
        }

        $oldProductId = (int)($existing['product_id'] ?? 0);
        $newProductId = (int)($data['product_id'] ?? 0);

        $updateData = [
            'plan_date' => $data['plan_date'],
            'machine_id' => $data['machine_id'],
            'product_id' => $data['product_id'],
            'planned_qty' => $data['planned_qty'],
            'sequence_no' => $data['sequence_no'],
            'runtime' => $data['runtime'],
            'status' => $data['status'],
            'plan_type' => $data['plan_type'],
            'reference_doctype' => $data['reference_doctype'],
            'reference_name' => $data['reference_name'],
            'coverage_pct' => $data['coverage_pct'],
            'shortage_qty' => $data['shortage_qty'],
            'auto_created' => $data['auto_created'],
            'notes' => $data['notes'],
            'added_by' => $requestedAddedBy,
        ];

        $context = EntityContext::fromUser($currentUser);
        ProductionPlanService::update($id, $updateData, $context, $existing);

        ManufacturingPostPersistHelper::afterPlanUpdated($id, $oldProductId, $newProductId);

        self::flash('ok', 'Production plan updated.');
    }

    public static function delete(int $id): void
    {
        WorkflowGovernance::ensureSchema();

        if ($id <= 0) {
            self::flash('err', 'Invalid production plan id.');
            return;
        }

        $existing = DB::fetchOne('SELECT * FROM production_plans WHERE id=? LIMIT 1', [$id]);
        if (!$existing) {
            self::flash('err', 'Production plan not found.');
            return;
        }

        $existing = WorkflowGovernance::withDefaults($existing);
        if (WorkflowPolicy::isLocked(WorkflowPolicy::MODULE_PRODUCTION_PLAN, $existing)) {
            self::flash('err', 'Locked production plans cannot be deleted. Reopen first with governance reason.');
            return;
        }

        $productId = (int)($existing['product_id'] ?? 0);

        try {
            $context = EntityContext::fromUser(Auth::user());
            ProductionPlanService::delete($id, $context);
        } catch (\Throwable $e) {
            self::flash('err', 'Delete failed: ' . $e->getMessage());
            return;
        }

        self::recalculateCoverage([$productId]);
        self::flash('ok', 'Production plan deleted.');
    }

    public static function approvalAction(array $input): void
    {
        require_once __DIR__ . '/../ProductionPlanService.php';

        $id = (int)($input['id'] ?? 0);
        $rawAction = strtolower(trim((string)($input['action'] ?? '')));
        $action = $rawAction === 'unlock_override' ? 'reopen' : $rawAction;
        $note = trim((string)($input['note'] ?? ''));
        $reason = trim((string)($input['reason'] ?? ''));
        $redirect = self::normalizeRedirect((string)($input['redirect'] ?? ('/production-plans/edit?id=' . $id)), '/production-plans');

        if ($id <= 0 || !in_array($action, ['submit', 'approve', 'reject', 'reopen', 'hold', 'resume', 'finalize', 'cancel'], true)) {
            self::flash('err', 'Invalid governance request.');
            header('Location: /production-plans');
            exit;
        }

        $newStatus = \Plugins\ProductionPlans\ProductionPlanService::actionToStatus($action);
        if ($newStatus === null) {
            self::flash('err', 'Invalid governance request.');
            header('Location: /production-plans');
            exit;
        }

        $context = \App\Core\EntityContext::fromUser(Auth::user());

        try {
            \Plugins\ProductionPlans\ProductionPlanService::quickStatusUpdate($id, $newStatus, $context, $reason, $note);
            self::flash('ok', 'Governance action applied: ' . ucfirst($action) . '.');
            header('Location: ' . $redirect);
            exit;
        } catch (\RuntimeException $e) {
            self::flash('err', $e->getMessage());
            header('Location: ' . $redirect);
            exit;
        }
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
            'plan_date' => trim((string)($input['plan_date'] ?? '')),
            'machine_id' => (int)($input['machine_id'] ?? 0),
            'product_id' => (int)($input['product_id'] ?? 0),
            'planned_qty' => (float)($input['planned_qty'] ?? 0),
            'sequence_no' => (int)($input['sequence_no'] ?? 1),
            'runtime' => $input['runtime'] === '' ? null : (float)$input['runtime'],
            'status' => trim((string)($input['status'] ?? 'Planned')),
            'plan_type' => trim((string)($input['plan_type'] ?? 'Manual')),
            'reference_doctype' => trim((string)($input['reference_doctype'] ?? '')),
            'reference_name' => trim((string)($input['reference_name'] ?? '')),
            'coverage_pct' => (float)($input['coverage_pct'] ?? 0),
            'shortage_qty' => (float)($input['shortage_qty'] ?? 0),
            'auto_created' => (int)((string)($input['auto_created'] ?? '0') === '1' ? 1 : 0),
            'notes' => trim((string)($input['notes'] ?? '')),
            'added_by' => trim((string)($input['added_by'] ?? '')),
        ];
    }

    private static function canEditPlan(?array $user): bool
    {
        return WorkflowPolicy::roleSlug($user) === 'admin';
    }

    private static function canEditAddedBy(?array $user): bool
    {
        $role = WorkflowPolicy::roleSlug($user);
        return in_array($role, ['admin', 'planning_leader', 'machine_leader', 'production_leader', 'editor'], true);
    }

    private static function defaultAddedBy(?array $user): string
    {
        $email = trim((string)($user['email'] ?? ''));
        if ($email !== '') {
            return $email;
        }
        return 'System';
    }

    private static function normalizeAddedBy(string $value, ?array $user): string
    {
        $value = trim($value);
        if ($value === '') {
            $value = self::defaultAddedBy($user);
        }

        if (strlen($value) > 190) {
            $value = substr($value, 0, 190);
        }

        return $value;
    }

    private static function ensureAddedByColumn(): void
    {
        try {
            if (!self::tableExists('production_plans')) {
                return;
            }

            $cols = DB::fetchAll('SHOW COLUMNS FROM production_plans');
            foreach ($cols as $col) {
                if (strtolower((string)($col['Field'] ?? '')) === 'added_by') {
                    return;
                }
            }

            DB::query('ALTER TABLE production_plans ADD COLUMN added_by VARCHAR(190) NULL AFTER notes');
        } catch (\Throwable $e) {
            // Keep page functional even when schema migration is blocked.
        }
    }

    private static function resolveDraftMachineId(int $productId): int
    {
        if ($productId <= 0) {
            return 0;
        }

        if (self::tableExists('part_machine_map')) {
            $mapped = DB::fetchOne(
                'SELECT pm.machine_id
                 FROM part_machine_map pm
                 INNER JOIN machines m ON m.id = pm.machine_id
                 WHERE pm.product_id = ? AND pm.is_active = 1 AND COALESCE(m.is_active, 1) = 1
                 ORDER BY pm.updated_at DESC, pm.id DESC
                 LIMIT 1',
                [$productId]
            );
            if ($mapped) {
                return (int)($mapped['machine_id'] ?? 0);
            }
        }

        if (self::tableExists('production_plans')) {
            $recentPlan = DB::fetchOne(
                'SELECT machine_id FROM production_plans WHERE product_id = ? ORDER BY plan_date DESC, id DESC LIMIT 1',
                [$productId]
            );
            if ($recentPlan) {
                return (int)($recentPlan['machine_id'] ?? 0);
            }
        }

        if (self::tableExists('production_entries')) {
            $recentEntry = DB::fetchOne(
                'SELECT machine_id FROM production_entries WHERE product_id = ? ORDER BY production_date DESC, id DESC LIMIT 1',
                [$productId]
            );
            if ($recentEntry) {
                return (int)($recentEntry['machine_id'] ?? 0);
            }
        }

        $fallback = DB::fetchOne('SELECT id FROM machines WHERE COALESCE(is_active, 1) = 1 ORDER BY machine_no ASC, id ASC LIMIT 1');
        return (int)($fallback['id'] ?? 0);
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

    private static function tableExists(string $tableName): bool
    {
        return DB::fetchOne("SHOW TABLES LIKE '" . DB::conn()->real_escape_string($tableName) . "'") !== null;
    }

    private static function flash(string $key, string $msg): void
    {
        $_SESSION['production_plans_flash_' . $key] = $msg;
    }

    private static function pullFlash(string $key): string
    {
        $k = 'production_plans_flash_' . $key;
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

    public static function printPdf(int $id, bool $download): void
    {
        if ($id <= 0) {
            self::flash('err', 'Invalid production plan id.');
            header('Location: /production-plans');
            exit;
        }

        $row = DB::fetchOne(
            'SELECT pp.*, m.machine_no, m.machine_name, p.parts_name, p.parts_number FROM production_plans pp LEFT JOIN machines m ON m.id = pp.machine_id LEFT JOIN products p ON p.id = pp.product_id WHERE pp.id=? LIMIT 1',
            [$id]
        );

        if (!$row) {
            self::flash('err', 'Production plan not found.');
            header('Location: /production-plans');
            exit;
        }

        if (!self::pdfService()->isReady()) {
            self::recordPdfExportFailure('production-plan:' . (int)$id, 'PDF rendering is not available.');
            self::flash('err', 'PDF rendering is not available.');
            header('Location: /production-plans/edit?id=' . (int)$id);
            exit;
        }

        try {
            $html = self::renderPdfTemplate('ProductionPlans::pdf_production_plan.php', ['row' => $row]);
            $pdfBinary = self::pdfService()->outputFromHtml($html, [
                'defaultFont' => 'DejaVu Sans',
                'isPhpEnabled' => false,
                'isHtml5ParserEnabled' => true,
                'paper' => 'A4',
                'orientation' => 'portrait',
            ]);

            $filename = 'production-plan-' . (int)$row['id'] . '-' . date('Ymd-His') . '.pdf';
            self::recordPdfExportSuccess('production-plan:' . (int)$row['id'], $filename, $download, [
                'record_id' => (int)$row['id'],
                'route' => '/production-plans/print-pdf',
                'mode' => $download ? 'download' : 'inline',
            ]);
            self::pdfService()->stream($pdfBinary, $filename, $download);
        } catch (\Throwable $e) {
            self::recordPdfExportFailure('production-plan:' . (int)$id, $e->getMessage());
            self::flash('err', 'PDF render failed: ' . $e->getMessage());
            header('Location: /production-plans/edit?id=' . (int)$id);
            exit;
        }
    }

    public static function printNextTwoWeeks(View $view): void
    {
        WorkflowGovernance::ensureSchema();
        self::ensureAddedByColumn();

        $report = self::nextTwoWeeksReportData();

        $view->render('ProductionPlans::print_next_two_weeks.php', [
            'pageTitle' => 'Production Plans Print',
            'start_date' => $report['start_date'],
            'end_date' => $report['end_date'],
            'total_rows' => $report['total_rows'],
            'groups' => $report['groups'],
        ]);
    }

    public static function printNextTwoWeeksPdf(): void
    {
        WorkflowGovernance::ensureSchema();
        self::ensureAddedByColumn();

        if (!self::pdfService()->isReady()) {
            self::recordPdfExportFailure('production-plan:next-two-weeks', 'PDF rendering is not available.');
            self::flash('err', 'PDF rendering is not available.');
            header('Location: /production-plans/print-next-two-weeks');
            exit;
        }

        try {
            $report = self::nextTwoWeeksReportData();
            $html = self::renderPdfTemplate('ProductionPlans::pdf_next_two_weeks.php', [
                'start_date' => $report['start_date'],
                'end_date' => $report['end_date'],
                'total_rows' => $report['total_rows'],
                'groups' => $report['groups'],
            ]);

            $pdfBinary = self::pdfService()->outputFromHtml($html, [
                'defaultFont' => 'DejaVu Sans',
                'isPhpEnabled' => false,
                'isHtml5ParserEnabled' => true,
                'paper' => 'A4',
                'orientation' => 'landscape',
            ]);

            $filename = 'production-plan-' . date('Ymd-His') . '.pdf';
            self::recordPdfExportSuccess('production-plan:next-two-weeks', $filename, false, [
                'route' => '/production-plans/print-next-two-weeks-pdf',
                'mode' => 'inline',
                'total_rows' => (int)$report['total_rows'],
                'start_date' => (string)$report['start_date'],
                'end_date' => (string)$report['end_date'],
            ]);
            self::pdfService()->stream($pdfBinary, $filename, false);
        } catch (\Throwable $e) {
            self::recordPdfExportFailure('production-plan:next-two-weeks', $e->getMessage());
            self::flash('err', 'PDF render failed: ' . $e->getMessage());
            header('Location: /production-plans/print-next-two-weeks');
            exit;
        }
    }

    /**
     * @return array{start_date:string,end_date:string,total_rows:int,groups:array<int,array<string,mixed>>}
     */
    private static function nextTwoWeeksReportData(): array
    {
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime('+13 days'));

        $rows = DB::fetchAll(
            'SELECT pp.*, m.machine_no, m.machine_name, p.parts_name, p.parts_number
             FROM production_plans pp
             INNER JOIN machines m ON m.id = pp.machine_id
             INNER JOIN products p ON p.id = pp.product_id
             WHERE pp.plan_date >= ? AND pp.plan_date <= ?
             ORDER BY m.machine_no ASC, m.machine_name ASC, pp.plan_date ASC, pp.sequence_no ASC, pp.id ASC',
            [$startDate, $endDate]
        );

        $groups = [];
        foreach ($rows as $row) {
            $machineId = (int)($row['machine_id'] ?? 0);
            if (!isset($groups[$machineId])) {
                $machineNo = trim((string)($row['machine_no'] ?? ''));
                $machineName = trim((string)($row['machine_name'] ?? ''));
                $label = trim($machineNo . ' - ' . $machineName, ' -');
                if ($label === '') {
                    $label = 'Machine #' . $machineId;
                }

                $groups[$machineId] = [
                    'machine_id' => $machineId,
                    'machine_label' => $label,
                    'rows' => [],
                ];
            }

            $groups[$machineId]['rows'][] = $row;
        }

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_rows' => count($rows),
            'groups' => array_values($groups),
        ];
    }

    private static function pdfService(): PdfService
    {
        static $service = null;
        if ($service === null) {
            $service = new PdfService();
        }
        return $service;
    }

    private static function renderPdfTemplate(string $viewKey, array $data): string
    {
        ob_start();
        extract($data);
        [$namespace, $viewFile] = array_pad(explode('::', $viewKey, 2), 2, '');
        include(rtrim(PackageManager::pluginSourcePath($namespace), '/') . '/Views/' . $viewFile);
        return ob_get_clean();
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

    private static function recalculateCoverage(array $productIds): void
    {
        try {
            CoverageService::recalculateForProducts($productIds);
        } catch (\Throwable $e) {
            self::flash('err', 'Coverage refresh failed: ' . $e->getMessage());
        }
    }
}
