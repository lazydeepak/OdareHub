<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Modules\AssemblyPlans\Controllers;

use App\Core\Auth;
use App\Core\AuditLogService;
use App\Core\DB;
use App\Core\HandoffEngine;
use App\Core\View;
use Apps\Manufacturing\Modules\AssemblyPlans\AssemblyPlanService;
use Plugins\Workflow\Services\WorkflowRegistry;
use Plugins\Workflow\Services\WorkflowTransitionEngine;

final class AssemblyPlansController
{
    public static function index(View $view): void
    {
        AssemblyPlanService::ensureSchema();
        AuditLogService::ensureSchema();

        $date = trim((string)($_GET['date'] ?? ''));
        $status = strtolower(trim((string)($_GET['status'] ?? '')));
        $productId = (int)($_GET['product_id'] ?? 0);
        $onlyOpen = ((string)($_GET['only_open'] ?? '') === '1');

        $rows = AssemblyPlanService::listPlans($date, $status, $productId, $onlyOpen);
        $products = \App\Core\DB::fetchAll('SELECT id, parts_name, parts_number FROM products ORDER BY parts_name ASC LIMIT 1200');

        $auditSummary = AuditLogService::latestByEntityIds(WorkflowRegistry::ENTITY_ASSEMBLY_PLAN, array_map(static fn(array $row): int => (int)($row['id'] ?? 0), $rows));

        $view->render('manufacturing::assembly_plans/index.php', [
            'pageTitle' => 'Assembly Plans',
            'rows' => $rows,
            'audit_summary' => $auditSummary,
            'products' => $products,
            'date' => $date,
            'status' => $status,
            'product_id' => $productId,
            'only_open' => $onlyOpen,
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
        ]);
    }

    public static function detail(View $view, int $id): void
    {
        AssemblyPlanService::ensureSchema();
        AuditLogService::ensureSchema();
        HandoffEngine::syncAssemblyPlan($id);

        if ($id <= 0) {
            self::flash('err', 'Invalid assembly plan id.');
            header('Location: /apps/manufacturing/assembly-plans');
            exit;
        }

        $payload = AssemblyPlanService::planDetail($id);
        if (!$payload) {
            self::flash('err', 'Assembly plan not found.');
            header('Location: /apps/manufacturing/assembly-plans');
            exit;
        }

        $currentUser = Auth::user();
        $planRow = is_array($payload['plan'] ?? null) ? (array)$payload['plan'] : [];
        $allowedActions = WorkflowTransitionEngine::allowedActions(WorkflowRegistry::ENTITY_ASSEMBLY_PLAN, $planRow, $currentUser);

        $view->render('manufacturing::assembly_plans/detail.php', [
            'pageTitle' => 'Assembly Plan Detail',
            'payload' => $payload,
            'workflow' => [
                'state' => WorkflowTransitionEngine::currentState(WorkflowRegistry::ENTITY_ASSEMBLY_PLAN, $planRow),
                'actions' => $allowedActions,
            ],
            'ownership_summary' => HandoffEngine::summaryForEntity('assembly_plan', $id),
            'activity_timeline' => AuditLogService::timeline(WorkflowRegistry::ENTITY_ASSEMBLY_PLAN, $id, 80),
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
        ]);
    }

    public static function update(array $input): void
    {
        try {
            AuditLogService::ensureSchema();
            $id = (int)($input['id'] ?? 0);
            $adjustedQty = (float)($input['adjusted_qty'] ?? 0);
            $note = trim((string)($input['adjustment_note'] ?? ''));
            $before = $id > 0 ? WorkflowTransitionEngine::fetchRecord(WorkflowRegistry::ENTITY_ASSEMBLY_PLAN, $id) : null;
            AssemblyPlanService::updatePlan($id, $adjustedQty, $note);
            $after = $id > 0 ? WorkflowTransitionEngine::fetchRecord(WorkflowRegistry::ENTITY_ASSEMBLY_PLAN, $id) : null;
            if (is_array($before) && is_array($after)) {
                AuditLogService::logEvent(
                    WorkflowRegistry::ENTITY_ASSEMBLY_PLAN,
                    $id,
                    AuditLogService::EVENT_LIFECYCLE,
                    AuditLogService::ACTION_UPDATED,
                    Auth::user(),
                    [
                        'app' => 'manufacturing',
                        'module' => 'assembly',
                        'old_state' => (string)($before['status'] ?? ''),
                        'new_state' => (string)($after['status'] ?? ''),
                        'note' => 'Assembly plan adjusted.',
                        'diff' => AuditLogService::diffImportantFields($before, $after, ['system_qty', 'adjusted_qty', 'approved_qty', 'status', 'adjustment_note', 'workflow_state']),
                    ]
                );
            }
            HandoffEngine::syncAssemblyPlan($id);
            self::flash('ok', 'Assembly plan updated.');
        } catch (\Throwable $e) {
            self::flash('err', 'Update failed: ' . $e->getMessage());
        }
    }

    public static function approve(array $input): void
    {
        try {
            $id = (int)($input['id'] ?? 0);
            $raw = trim((string)($input['approved_qty'] ?? ''));
            $approvedQty = $raw === '' ? null : (float)$raw;
            $result = WorkflowTransitionEngine::transition(
                WorkflowRegistry::ENTITY_ASSEMBLY_PLAN,
                $id,
                'approve',
                Auth::user(),
                trim((string)($input['reason'] ?? '')),
                trim((string)($input['note'] ?? ''))
            );
            if (!(bool)($result['ok'] ?? false)) {
                throw new \RuntimeException((string)($result['message'] ?? 'Workflow transition failed.'));
            }
            if ($approvedQty !== null && $id > 0) {
                DB::query('UPDATE mfg_part_demands SET approved_qty=?, updated_at=NOW() WHERE id=? AND demand_type=\'assembly\' LIMIT 1', [$approvedQty, $id]);
            }
            HandoffEngine::syncAssemblyPlan($id);
            self::flash('ok', 'Assembly plan approved.');
        } catch (\Throwable $e) {
            self::flash('err', 'Approve failed: ' . $e->getMessage());
        }
    }

    public static function transition(array $input): void
    {
        try {
            $id = (int)($input['id'] ?? 0);
            $action = strtolower(trim((string)($input['action'] ?? '')));
            $reason = trim((string)($input['reason'] ?? ''));
            $note = trim((string)($input['note'] ?? ''));

            if ($id <= 0 || $action === '') {
                throw new \InvalidArgumentException('Invalid workflow transition payload.');
            }

            $result = WorkflowTransitionEngine::transition(
                WorkflowRegistry::ENTITY_ASSEMBLY_PLAN,
                $id,
                $action,
                Auth::user(),
                $reason,
                $note
            );
            if (!(bool)($result['ok'] ?? false)) {
                throw new \RuntimeException((string)($result['message'] ?? 'Workflow transition failed.'));
            }

            HandoffEngine::syncAssemblyPlan($id);
            self::flash('ok', 'Assembly workflow action applied: ' . ucfirst($action) . '.');
        } catch (\Throwable $e) {
            self::flash('err', 'Workflow transition failed: ' . $e->getMessage());
        }
    }

    public static function addExecution(array $input): void
    {
        try {
            AssemblyPlanService::addExecutionEntry($input);
            HandoffEngine::syncAssemblyPlan((int)($input['assembly_plan_id'] ?? 0));
            self::flash('ok', 'Assembly execution entry saved.');
        } catch (\Throwable $e) {
            self::flash('err', 'Execution entry failed: ' . $e->getMessage());
        }
    }

    public static function approveExecution(array $input): void
    {
        try {
            $id = (int)($input['entry_id'] ?? 0);
            AssemblyPlanService::approveExecutionEntry($id);
            HandoffEngine::syncAssemblyPlan((int)($input['assembly_plan_id'] ?? 0));
            self::flash('ok', 'Assembly execution entry approved.');
        } catch (\Throwable $e) {
            self::flash('err', 'Execution approval failed: ' . $e->getMessage());
        }
    }

    private static function flash(string $key, string $msg): void
    {
        $_SESSION['mfg_assembly_plans_flash_' . $key] = $msg;
    }

    private static function pullFlash(string $key): string
    {
        $k = 'mfg_assembly_plans_flash_' . $key;
        $v = (string)($_SESSION[$k] ?? '');
        unset($_SESSION[$k]);
        return $v;
    }
}
