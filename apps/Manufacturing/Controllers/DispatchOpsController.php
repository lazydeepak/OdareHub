<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Controllers;

use App\Core\DB;
use App\Core\View;
use Apps\Manufacturing\Services\DispatchOpsService;
use Apps\Manufacturing\Services\StageTransitionService;

final class DispatchOpsController
{
    public static function index(View $view): void
    {
        $fromDate = trim((string)($_GET['from_date'] ?? ''));
        $toDate = trim((string)($_GET['to_date'] ?? ''));
        $status = strtolower(trim((string)($_GET['completion_status'] ?? '')));
        $productId = (int)($_GET['product_id'] ?? 0);

        $rows = DispatchOpsService::listRows($fromDate, $toDate, $status, $productId);
        $products = DB::fetchAll('SELECT id, parts_name, parts_number FROM products ORDER BY parts_name ASC LIMIT 1200');

        // Build lightweight stage eligibility map keyed by "product_id|dispatch_date"
        $readinessMap = self::buildReadinessMap($rows);

        $view->render('manufacturing::dispatch_ops/index.php', [
            'pageTitle'          => 'Dispatch Operations Workbench',
            'rows'               => $rows,
            'products'           => $products,
            'from_date'          => $fromDate,
            'to_date'            => $toDate,
            'completion_status'  => $status,
            'product_id'         => $productId,
            'readinessMap'       => $readinessMap,
            'flash'              => self::pullFlash('ok'),
            'error'              => self::pullFlash('err'),
        ]);
    }

    public static function preparationForm(View $view): void
    {
        $dispatchDate = trim((string)($_GET['dispatch_date'] ?? date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dispatchDate)) {
            $dispatchDate = date('Y-m-d');
        }

        $productId = (int)($_GET['product_id'] ?? 0);
        $dailyOrderId = (int)($_GET['daily_order_id'] ?? 0);

        $payload = DispatchOpsService::preparationFormData($dispatchDate, $productId, $dailyOrderId);
        $workflowHints = null;
        if (is_array($payload['selected_order'] ?? null)) {
            $selected = (array)$payload['selected_order'];
            $form = (array)($payload['form'] ?? []);
            $workflowHints = DispatchOpsService::dispatchWorkflowHints(
                (int)($selected['product_id'] ?? 0),
                $dispatchDate,
                (int)($form['dispatch_entry_id'] ?? 0)
            );
        }

        $view->render('manufacturing::dispatch_ops/preparation.php', [
            'pageTitle' => 'Order Preparation Form',
            'dispatch_date' => $dispatchDate,
            'product_id' => $productId,
            'daily_order_id' => $dailyOrderId,
            'products' => $payload['products'],
            'orders' => $payload['orders'],
            'selected_order' => $payload['selected_order'],
            'form' => $payload['form'],
            'history' => $payload['history'],
            'workflow_hints' => $workflowHints,
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
        ]);
    }

    public static function markReady(array $input): void
    {
        try {
            DispatchOpsService::markReady((int)($input['id'] ?? 0));
            self::flash('ok', 'Dispatch marked ready.');
        } catch (\Throwable $e) {
            self::flash('err', 'Ready action failed: ' . $e->getMessage());
        }
    }

    public static function prepare(array $input): void
    {
        try {
            DispatchOpsService::prepare((int)($input['id'] ?? 0), $input);
            self::flash('ok', 'Dispatch prepared with cases/pallets and flow metadata.');
        } catch (\Throwable $e) {
            self::flash('err', 'Prepare failed: ' . $e->getMessage());
        }
    }

    public static function savePreparation(array $input): void
    {
        try {
            $result = DispatchOpsService::savePreparation($input);
            $entryId = (int)($result['dispatch_entry_id'] ?? 0);
            $action = (string)($result['action'] ?? 'saved');
            self::flash('ok', 'Order preparation ' . $action . ($entryId > 0 ? ' for dispatch #' . $entryId : '') . '.');
        } catch (\Throwable $e) {
            self::flash('err', 'Preparation save failed: ' . $e->getMessage());
        }
    }

    public static function complete(array $input): void
    {
        try {
            DispatchOpsService::complete((int)($input['id'] ?? 0));
            self::flash('ok', 'Dispatch completion recorded.');
        } catch (\Throwable $e) {
            self::flash('err', 'Completion failed: ' . $e->getMessage());
        }
    }

    private static function flash(string $key, string $msg): void
    {
        $_SESSION['mfg_dispatch_ops_flash_' . $key] = $msg;
    }

    private static function pullFlash(string $key): string
    {
        $k = 'mfg_dispatch_ops_flash_' . $key;
        $v = (string)($_SESSION[$k] ?? '');
        unset($_SESSION[$k]);
        return $v;
    }

    /**
     * Build a stage-eligibility map for the current dispatch rows.
     * Keyed by "{product_id}|{dispatch_date}" → [dispatch_stage_row, upstream_released, stage_path].
     */
    private static function buildReadinessMap(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        // Collect unique pairs and preload stage computation once per date.
        $pairs = [];
        $dates = [];
        foreach ($rows as $r) {
            $pid = (int)$r['product_id'];
            $d = (string)$r['dispatch_date'];
            $rid = (int)($r['id'] ?? 0);
            $pairs["{$pid}|{$d}|{$rid}"] = ['product_id' => $pid, 'date' => $d, 'dispatch_entry_id' => $rid];
            $dates[$d] = true;
        }

        $byDate = [];
        foreach (array_keys($dates) as $d) {
            $byDate[$d] = StageTransitionService::computeForDate($d);
        }

        $map = [];
        foreach ($pairs as $key => $pair) {
            $pid = $pair['product_id'];
            $d = $pair['date'];
            $r = $byDate[$d][$pid] ?? null;

            $dispatchEntryId = (int)($pair['dispatch_entry_id'] ?? 0);

            $hints = DispatchOpsService::dispatchWorkflowHints($pid, $d, $dispatchEntryId);

            if (!is_array($r)) {
                $map[$key] = [
                    'stage_path' => ['dispatch'],
                    'dispatch_released' => false,
                    'upstream_released' => false,
                    'message' => 'Readiness unavailable',
                    'detail' => (string)($hints['message'] ?? 'No stage context for this product/date.'),
                    'releasable_qty' => (float)($hints['releasable_qty'] ?? 0.0),
                    'next_allowed_action' => 'wait_upstream',
                ];
                continue;
            }

            $stagePath = (array)($r['stage_path'] ?? []);
            $dispatch = (array)($r['stages']['dispatch'] ?? []);
            $dispatchStatus = (string)($dispatch['status'] ?? 'pending');
            $dispatchReleased = (float)($dispatch['released_qty'] ?? 0) > 0
                || (string)($dispatch['status'] ?? '') === 'released';

            $upstreamStage = count($stagePath) >= 2 ? (string)$stagePath[count($stagePath) - 2] : 'dispatch';
            $upstream = (array)($r['stages'][$upstreamStage] ?? []);
            $upstreamStatus = (string)($upstream['status'] ?? 'pending');
            $upstreamPct = (float)($upstream['pct'] ?? 0.0);
            $upstreamReleased = in_array($upstreamStatus, ['complete', 'eligible', 'released', 'skipped'], true)
                || ($upstreamStatus === 'in_progress' && $upstreamPct >= 80.0);

            $message = (bool)($hints['allowed'] ?? false) ? 'Dispatch allowed' : 'Blocked';
            $detail = (string)($hints['message'] ?? '');
            if ($detail === '') {
                $detail = 'Dispatch stage is ' . $dispatchStatus . '.';
            }

            $map[$key] = [
                'stage_path' => $stagePath,
                'dispatch_released' => $dispatchReleased,
                'dispatch_released_by' => (string)($dispatch['released_by'] ?? ''),
                'dispatch_released_at' => (string)($dispatch['released_at'] ?? ''),
                'upstream_released' => $upstreamReleased,
                'upstream_stage' => $upstreamStage,
                'upstream_status' => $upstreamStatus,
                'upstream_pct' => $upstreamPct,
                'dispatch_status' => $dispatchStatus,
                'next_allowed_action' => (string)($dispatch['next_allowed_action'] ?? ''),
                'releasable_qty' => round((float)($hints['releasable_qty'] ?? 0.0), 2),
                'allowed' => (bool)($hints['allowed'] ?? false),
                'message' => $message,
                'detail' => $detail,
            ];
        }

        return $map;
    }
}
