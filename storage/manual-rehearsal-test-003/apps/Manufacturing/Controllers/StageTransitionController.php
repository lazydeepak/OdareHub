<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Controllers;

use App\Core\Auth;
use App\Core\DB;
use Apps\Manufacturing\Services\StageTransitionService;

final class StageTransitionController
{
    /**
    * POST /apps/manufacturing/stage-release
     * Explicitly release a stage for a product/date (handoff action).
     */
    public static function releaseAction(): void
    {
        Auth::requireAppAccess('manufacturing');
        $ctx = platform_user_context_contract()->resolveUserContext(Auth::user());
        if ((string)($ctx['authority_role'] ?? 'app_user') === 'app_user') {
            http_response_code(403);
            echo 'Access denied by assignment policy.';
            exit;
        }
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $productId = (int)($_POST['product_id'] ?? 0);
        $date      = preg_replace('/[^0-9\-]/', '', (string)($_POST['ref_date'] ?? ''));
        $stage     = (string)($_POST['stage'] ?? '');
        $qty       = round(max(0.0, (float)($_POST['qty'] ?? 0)), 2);
        $notes     = trim((string)($_POST['notes'] ?? ''));
        $redirect  = self::safeRedirect((string)($_POST['redirect'] ?? '/apps/manufacturing/production-queue'));

        try {
            StageTransitionService::releaseStage($productId, $date, $stage, $qty, $notes);
            $dateLabel = $date !== '' ? date('d M Y', (int)strtotime($date)) : $date;
            $_SESSION['flash'] = "Stage '" . ucfirst($stage) . "' released for {$dateLabel}.";
        } catch (\Throwable $e) {
            $_SESSION['error'] = 'Release failed: ' . $e->getMessage();
        }

        header('Location: ' . $redirect);
        exit;
    }

    /**
    * POST /apps/manufacturing/stage-override
     * Override a stage to blocked or skipped for a product/date.
     */
    public static function overrideAction(): void
    {
        Auth::requireAdmin();
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $productId      = (int)($_POST['product_id'] ?? 0);
        $date           = preg_replace('/[^0-9\-]/', '', (string)($_POST['ref_date'] ?? ''));
        $stage          = (string)($_POST['stage'] ?? '');
        $overrideStatus = (string)($_POST['override_status'] ?? '');
        $reason         = trim((string)($_POST['reason'] ?? ''));
        $redirect       = self::safeRedirect((string)($_POST['redirect'] ?? '/apps/manufacturing/stage-board'));

        try {
            StageTransitionService::overrideStageStatus($productId, $date, $stage, $overrideStatus, $reason);
            $_SESSION['flash'] = "Stage '" . ucfirst($stage) . "' overridden to '{$overrideStatus}'.";
        } catch (\Throwable $e) {
            $_SESSION['error'] = 'Override failed: ' . $e->getMessage();
        }

        header('Location: ' . $redirect);
        exit;
    }

    /**
    * GET /apps/manufacturing/stage-board
     * Read-only cross-stage readiness board for a selected date.
     */
    public static function boardAction(?\App\Core\View $view = null): void
    {
        Auth::requireAppAccess('manufacturing');

        $today    = date('Y-m-d');
        $requestedDateRaw = (string)($_GET['date'] ?? '');
        $hasExplicitDate = $requestedDateRaw !== '';

        $date = preg_replace('/[^0-9\-]/', '', $hasExplicitDate ? $requestedDateRaw : $today);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = $today;
            $hasExplicitDate = false;
        }

        $readiness = StageTransitionService::computeForDate($date);
        $autoSelectedFromDate = '';

        // If users open stage board without a date and today is empty, show the latest active board date.
        if (!$hasExplicitDate && $readiness === []) {
            for ($daysBack = 1; $daysBack <= 30; $daysBack++) {
                $candidateDate = date('Y-m-d', strtotime($today . ' -' . $daysBack . ' day'));
                $candidateReadiness = StageTransitionService::computeForDate($candidateDate);
                if ($candidateReadiness !== []) {
                    $autoSelectedFromDate = $today;
                    $date = $candidateDate;
                    $readiness = $candidateReadiness;
                    break;
                }
            }
        }

        $pipeline = self::buildPipelineColumns($readiness, $date, $today);

        $flash = (string)($_SESSION['flash'] ?? '');
        $error = (string)($_SESSION['error'] ?? '');
        unset($_SESSION['flash'], $_SESSION['error']);

        $payload = [
            'pageTitle' => 'Stage Readiness Board',
            'readiness' => $readiness,
            'pipeline'  => $pipeline,
            'date'      => $date,
            'today'     => $today,
            'auto_selected_from_date' => $autoSelectedFromDate,
            'flash'     => $flash,
            'error'     => $error,
        ];

        if ($view instanceof \App\Core\View) {
            try {
                $view->render('manufacturing::stage_board/index.php', $payload);
                return;
            } catch (\Throwable $e) {
                // Fallback to direct app view path to avoid namespace-related runtime failures.
            }
        }

        $localView = new \App\Core\View(APP_ROOT . '/apps/Manufacturing/Views');
        $localView->render('stage_board/index.php', $payload);
    }

    private static function safeRedirect(string $url): string
    {
        return str_starts_with($url, '/') ? $url : '/apps/manufacturing/stage-board';
    }

    /**
     * @param array<int,array<string,mixed>> $readiness
     * @return array<string,mixed>
     */
    private static function buildPipelineColumns(array $readiness, string $date, string $today): array
    {
        $columns = [
            'awaiting_supply_production' => ['key' => 'awaiting_supply_production', 'label' => 'Awaiting Supply / Production', 'items' => []],
            'awaiting_assembly' => ['key' => 'awaiting_assembly', 'label' => 'Awaiting Assembly', 'items' => []],
            'awaiting_qc' => ['key' => 'awaiting_qc', 'label' => 'Awaiting QC', 'items' => []],
            'awaiting_processing' => ['key' => 'awaiting_processing', 'label' => 'Awaiting Preparation', 'items' => []],
            'ready_dispatch' => ['key' => 'ready_dispatch', 'label' => 'Ready for Dispatch', 'items' => []],
            'dispatched' => ['key' => 'dispatched', 'label' => 'Dispatched', 'items' => []],
        ];

        if (empty($readiness)) {
            return ['columns' => $columns, 'totals' => ['items' => 0, 'overdue' => 0, 'blocked' => 0, 'at_risk' => 0]];
        }

        $productIds = array_map('intval', array_keys($readiness));
        $contextMap = self::loadBoardContext($productIds, $date);

        $totals = ['items' => 0, 'overdue' => 0, 'blocked' => 0, 'at_risk' => 0];
        foreach ($readiness as $productId => $row) {
            $pid = (int)$productId;
            $ctx = $contextMap[$pid] ?? [];
            $columnKey = self::resolvePipelineColumn((array)$row);

            $ageDays = self::ageDays((string)($ctx['order_date'] ?? ''), $date, $today);
            [$slaLabel, $slaTone] = self::slaState((string)($ctx['required_date'] ?? ''), $today);

            $blocked = !empty($row['block_reasons'])
                || in_array('blocked', array_map(static fn($s): string => (string)($s['status'] ?? ''), (array)($row['stages'] ?? [])), true);

            $priority = 'normal';
            if ($blocked || $slaTone === 'overdue') {
                $priority = 'critical';
            } elseif ($slaTone === 'at_risk') {
                $priority = 'high';
            } elseif (in_array((string)($row['next_stage'] ?? ''), ['qc', 'packaging', 'dispatch'], true)) {
                $priority = 'elevated';
            }

            $dailyOrderId = (int)($ctx['daily_order_id'] ?? 0);
            $qcEntryId = (int)($ctx['qc_entry_id'] ?? 0);
            $dispatchEntryId = (int)($ctx['dispatch_entry_id'] ?? 0);
            $planId = (int)($ctx['production_plan_id'] ?? 0);
            $nextStage = (string)($row['next_stage'] ?? 'dispatch');

            $openUrl = '/apps/manufacturing/stage-board?date=' . urlencode($date);
            if ($columnKey === 'awaiting_supply_production') {
                $openUrl = '/apps/manufacturing/production-queue?date=' . urlencode($date);
            } elseif ($columnKey === 'awaiting_assembly') {
                $openUrl = '/apps/manufacturing/assembly-queue?from_date=' . urlencode($date) . '&to_date=' . urlencode($date);
            } elseif ($columnKey === 'awaiting_qc') {
                $openUrl = '/apps/manufacturing/qc-queue?from_date=' . urlencode($date) . '&to_date=' . urlencode($date);
            } elseif ($columnKey === 'awaiting_processing') {
                $openUrl = '/apps/manufacturing/dispatch-ops/preparation?dispatch_date=' . urlencode($date) . '&product_id=' . $pid;
            } elseif ($columnKey === 'ready_dispatch') {
                $openUrl = '/apps/manufacturing/dispatch-ops?from_date=' . urlencode($date) . '&to_date=' . urlencode($date) . '&product_id=' . $pid;
            } elseif ($columnKey === 'dispatched' && $dispatchEntryId > 0) {
                $openUrl = '/dispatch-entries/edit?id=' . $dispatchEntryId;
            }

            $item = [
                'product_id' => $pid,
                'part_name' => (string)($row['parts_name'] ?? ''),
                'part_code' => (string)($row['parts_number'] ?? ''),
                'demand_ref' => $dailyOrderId > 0 ? ('Daily Order #' . $dailyOrderId) : 'Daily Order -',
                'daily_order_id' => $dailyOrderId,
                'current_stage' => self::currentStageLabel((array)$row),
                'next_stage' => $nextStage,
                'age_days' => $ageDays,
                'sla_label' => $slaLabel,
                'sla_tone' => $slaTone,
                'priority' => $priority,
                'blocked' => $blocked,
                'block_reason' => $blocked ? (string)implode('; ', array_slice((array)($row['block_reasons'] ?? []), 0, 2)) : '',
                'open_url' => $openUrl,
                'links' => [
                    'order_360' => $dailyOrderId > 0 ? ('/daily-orders/360?id=' . $dailyOrderId) : '',
                    'qc_entry' => $qcEntryId > 0 ? ('/qc-entries/edit?id=' . $qcEntryId) : '/qc-entries',
                    'dispatch_entry' => $dispatchEntryId > 0 ? ('/dispatch-entries/edit?id=' . $dispatchEntryId) : '/dispatch-entries',
                    'production_plan' => $planId > 0 ? ('/production-plans/edit?id=' . $planId) : '/production-plans',
                ],
                'can_release_to' => array_values(array_map('strval', (array)($row['can_release_to'] ?? []))),
            ];

            $columns[$columnKey]['items'][] = $item;
            $totals['items']++;
            if ($slaTone === 'overdue') {
                $totals['overdue']++;
            } elseif ($slaTone === 'at_risk') {
                $totals['at_risk']++;
            }
            if ($blocked) {
                $totals['blocked']++;
            }
        }

        foreach ($columns as $key => $column) {
            usort($column['items'], static function (array $a, array $b): int {
                $score = static function (array $item): int {
                    $base = match ((string)($item['priority'] ?? 'normal')) {
                        'critical' => 300,
                        'high' => 200,
                        'elevated' => 100,
                        default => 0,
                    };
                    return $base + (int)($item['age_days'] ?? 0);
                };

                return $score($b) <=> $score($a);
            });
            $column['count'] = count($column['items']);
            $column['overdue_count'] = count(array_filter($column['items'], static fn(array $i): bool => (string)($i['sla_tone'] ?? '') === 'overdue'));
            $column['blocked_count'] = count(array_filter($column['items'], static fn(array $i): bool => !empty($i['blocked'])));
            $columns[$key] = $column;
        }

        return ['columns' => $columns, 'totals' => $totals];
    }

    /**
     * @param array<int,int> $productIds
     * @return array<int,array<string,mixed>>
     */
    private static function loadBoardContext(array $productIds, string $date): array
    {
        if (empty($productIds)) {
            return [];
        }

        $ph = implode(',', array_fill(0, count($productIds), '?'));
        $ctx = [];

                $orderRows = [];
                try {
                        $orderRows = DB::fetchAll(
                                "SELECT id, product_id, order_date, required_date, shortage_qty, coverage_pct
                                 FROM daily_orders
                                 WHERE product_id IN ({$ph})
                                     AND LOWER(COALESCE(status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')
                                 ORDER BY product_id ASC,
                                                    CASE WHEN required_date IS NULL THEN 1 ELSE 0 END ASC,
                                                    required_date ASC,
                                                    order_date ASC,
                                                    id ASC",
                                $productIds
                        );
                } catch (\Throwable $e) {
                        $orderRows = [];
                }
        foreach ($orderRows as $row) {
            $pid = (int)($row['product_id'] ?? 0);
            if ($pid <= 0 || isset($ctx[$pid]['daily_order_id'])) {
                continue;
            }
            $ctx[$pid]['daily_order_id'] = (int)($row['id'] ?? 0);
            $ctx[$pid]['order_date'] = (string)($row['order_date'] ?? '');
            $ctx[$pid]['required_date'] = (string)($row['required_date'] ?? '');
            $ctx[$pid]['shortage_qty'] = (float)($row['shortage_qty'] ?? 0);
            $ctx[$pid]['coverage_pct'] = (float)($row['coverage_pct'] ?? 0);
        }

        $planRows = [];
        try {
            $planRows = DB::fetchAll(
                "SELECT id, product_id
                 FROM production_plans
                 WHERE product_id IN ({$ph}) AND plan_date = ?
                 ORDER BY id DESC",
                array_merge($productIds, [$date])
            );
        } catch (\Throwable $e) {
            $planRows = [];
        }
        foreach ($planRows as $row) {
            $pid = (int)($row['product_id'] ?? 0);
            if ($pid <= 0 || isset($ctx[$pid]['production_plan_id'])) {
                continue;
            }
            $ctx[$pid]['production_plan_id'] = (int)($row['id'] ?? 0);
        }

                $qcRows = [];
                try {
                        $qcRows = DB::fetchAll(
                                "SELECT id, product_id
                                 FROM qc_entries
                                 WHERE product_id IN ({$ph})
                                     AND COALESCE(DATE(updated_at), DATE(created_at), CURDATE()) = ?
                                 ORDER BY id DESC",
                                array_merge($productIds, [$date])
                        );
                } catch (\Throwable $e) {
                        $qcRows = [];
                }
        foreach ($qcRows as $row) {
            $pid = (int)($row['product_id'] ?? 0);
            if ($pid <= 0 || isset($ctx[$pid]['qc_entry_id'])) {
                continue;
            }
            $ctx[$pid]['qc_entry_id'] = (int)($row['id'] ?? 0);
        }

        $dispatchRows = [];
        try {
            $dispatchRows = DB::fetchAll(
                "SELECT id, product_id
                 FROM dispatch_entries
                 WHERE product_id IN ({$ph}) AND dispatch_date = ?
                 ORDER BY id DESC",
                array_merge($productIds, [$date])
            );
        } catch (\Throwable $e) {
            $dispatchRows = [];
        }
        foreach ($dispatchRows as $row) {
            $pid = (int)($row['product_id'] ?? 0);
            if ($pid <= 0 || isset($ctx[$pid]['dispatch_entry_id'])) {
                continue;
            }
            $ctx[$pid]['dispatch_entry_id'] = (int)($row['id'] ?? 0);
        }

        return $ctx;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function resolvePipelineColumn(array $row): string
    {
        $dispatchStatus = (string)($row['stages']['dispatch']['status'] ?? 'pending');
        if ($dispatchStatus === 'complete') {
            return 'dispatched';
        }

        $next = (string)($row['next_stage'] ?? '');
        return match ($next) {
            'production' => 'awaiting_supply_production',
            'assembly' => 'awaiting_assembly',
            'qc' => 'awaiting_qc',
            'packaging' => 'awaiting_processing',
            'dispatch' => 'ready_dispatch',
            default => ($dispatchStatus === 'in_progress' || $dispatchStatus === 'eligible' || $dispatchStatus === 'released')
                ? 'ready_dispatch'
                : 'awaiting_supply_production',
        };
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function currentStageLabel(array $row): string
    {
        $path = array_values(array_map('strval', (array)($row['stage_path'] ?? [])));
        foreach ($path as $stage) {
            $status = (string)($row['stages'][$stage]['status'] ?? 'pending');
            if (!in_array($status, ['complete', 'skipped', 'not_applicable'], true)) {
                return ucfirst($stage);
            }
        }

        return 'Dispatched';
    }

    private static function ageDays(string $orderDate, string $fallbackDate, string $today): int
    {
        $base = $orderDate !== '' ? $orderDate : $fallbackDate;
        $baseTs = strtotime($base);
        $todayTs = strtotime($today);
        if ($baseTs === false || $todayTs === false) {
            return 0;
        }

        return max(0, (int)floor(($todayTs - $baseTs) / 86400));
    }

    /**
     * @return array{0:string,1:string}
     */
    private static function slaState(string $requiredDate, string $today): array
    {
        if ($requiredDate === '' || strtotime($requiredDate) === false || strtotime($today) === false) {
            return ['No SLA date', 'neutral'];
        }

        $dueTs = strtotime($requiredDate);
        $todayTs = strtotime($today);
        $diffDays = (int)floor(($dueTs - $todayTs) / 86400);

        if ($diffDays < 0) {
            return ['Overdue', 'overdue'];
        }
        if ($diffDays <= 1) {
            return ['At Risk', 'at_risk'];
        }

        return ['On Track', 'on_track'];
    }
}
