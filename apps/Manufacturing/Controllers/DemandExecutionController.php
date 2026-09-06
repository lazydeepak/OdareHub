<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Controllers;

use App\Core\Auth;
use App\Core\HandoffEngine;
use App\Core\View;
use Apps\Manufacturing\Services\AdminRouteRegistryService;
use Apps\Manufacturing\Services\DemandExecutionService;
use Apps\Manufacturing\Services\StageTransitionService;
use Plugins\MaterialManagement\Services\MaterialAccessService;
use Plugins\MaterialManagement\Services\MaterialManagementService;

final class DemandExecutionController
{
    public static function dashboard(View $view): void
    {
        $fromDate = trim((string)($_GET['from_date'] ?? ''));
        $toDate = trim((string)($_GET['to_date'] ?? ''));
        $summary = DemandExecutionService::pressureSummary($fromDate, $toDate);

        Auth::bootSession();
        $currentUser = Auth::user();
        $roleRaw = strtolower(trim((string)($currentUser['role'] ?? '')));
        $roleToken = str_replace([' ', '-'], '_', $roleRaw);

        $today = date('Y-m-d');
        $qcRows = DemandExecutionService::qcDemandRows($today, $today);
        $assemblyRows = DemandExecutionService::assemblyDemandRows($today, $today);

        usort($qcRows, static function (array $a, array $b): int {
            return ((float)($b['open_qc_workload'] ?? 0)) <=> ((float)($a['open_qc_workload'] ?? 0));
        });
        usort($assemblyRows, static function (array $a, array $b): int {
            return ((float)($b['open_assembly_workload'] ?? 0)) <=> ((float)($a['open_assembly_workload'] ?? 0));
        });

        $qcTop = array_slice($qcRows, 0, 8);
        $assemblyTop = array_slice($assemblyRows, 0, 8);

        $stageRows = array_values(StageTransitionService::computeForDate($today));
        $stageStats = [
            'tracked' => count($stageRows),
            'ready_for_assembly' => 0,
            'ready_for_qc' => 0,
            'ready_for_packaging' => 0,
            'ready_for_dispatch' => 0,
            'blocked' => 0,
        ];

        foreach ($stageRows as $row) {
            $next = (string)($row['next_stage'] ?? '');
            if ($next === 'assembly') {
                $stageStats['ready_for_assembly']++;
            } elseif ($next === 'qc') {
                $stageStats['ready_for_qc']++;
            } elseif ($next === 'packaging') {
                $stageStats['ready_for_packaging']++;
            } elseif ($next === 'dispatch') {
                $stageStats['ready_for_dispatch']++;
            }

            if (!empty($row['block_reasons'])) {
                $stageStats['blocked']++;
            }
        }

        $materialAccess = [
            'can_access_any' => false,
            'can_manage' => false,
            'can_view_stock' => false,
            'can_view_coverage' => false,
        ];
        $materialSummary = [];
        if (class_exists(MaterialAccessService::class)) {
            $materialAccess = [
                'can_access_any' => MaterialAccessService::canAccessAny(),
                'can_manage' => MaterialAccessService::canManage(),
                'can_view_stock' => MaterialAccessService::canViewStock(),
                'can_view_coverage' => MaterialAccessService::canViewCoverage(),
            ];
        }
        if (
            class_exists(MaterialManagementService::class)
            && !empty($materialAccess['can_access_any'])
        ) {
            $materialSummary = MaterialManagementService::summary();
        }

        $view->render('manufacturing::execution/dashboard.php', [
            'pageTitle' => (string)t('mfg.portal.title'),
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'summary' => $summary,
            'role_token' => $roleToken,
            'role_label' => (string)($currentUser['role'] ?? t('mfg.portal.role.operations')),
            'stage_stats' => $stageStats,
            'qc_top_rows' => $qcTop,
            'assembly_top_rows' => $assemblyTop,
            'material_summary' => $materialSummary,
            'material_access' => $materialAccess,
            'admin_route_registry' => AdminRouteRegistryService::groupedRegistry((bool)($materialAccess['can_access_any'] ?? false)),
            'ownership_board' => HandoffEngine::workboardForOwner('Assembly Leader'),
        ]);
    }

    public static function qcQueue(View $view): void
    {
        $fromDate = trim((string)($_GET['from_date'] ?? ''));
        $toDate = trim((string)($_GET['to_date'] ?? ''));
        $rows = DemandExecutionService::qcDemandRows($fromDate, $toDate);

        $view->render('manufacturing::execution/qc_queue.php', [
            'pageTitle' => (string)t('mfg.portal.action.qc_queue'),
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'rows' => $rows,
        ]);
    }

    public static function assemblyQueue(View $view): void
    {
        $fromDate = trim((string)($_GET['from_date'] ?? ''));
        $toDate = trim((string)($_GET['to_date'] ?? ''));
        $rows = DemandExecutionService::assemblyDemandRows($fromDate, $toDate);

        $view->render('manufacturing::execution/assembly_queue.php', [
            'pageTitle' => (string)t('mfg.portal.action.assembly_queue'),
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'rows' => $rows,
            'ownership_board' => HandoffEngine::workboardForOwner('Assembly Leader'),
        ]);
    }
}
