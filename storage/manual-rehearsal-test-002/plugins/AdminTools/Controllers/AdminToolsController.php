<?php
declare(strict_types=1);

namespace Plugins\AdminTools\Controllers;

$packageManagerPath = APP_ROOT . '/app/Core/PackageManager.php';
if (!class_exists(\App\Core\PackageManager::class) && is_file($packageManagerPath)) {
    require_once $packageManagerPath;
}

$userDashboardAssignmentServicePath = APP_ROOT . '/plugins/Base/Services/UserDashboardAssignmentService.php';
if (!class_exists(\Plugins\Base\Services\UserDashboardAssignmentService::class) && is_file($userDashboardAssignmentServicePath)) {
    require_once $userDashboardAssignmentServicePath;
}

$moduleHealthReportServicePath = APP_ROOT . '/plugins/AdminTools/Services/ModuleHealthReportService.php';
if (!class_exists(\Plugins\AdminTools\Services\ModuleHealthReportService::class) && is_file($moduleHealthReportServicePath)) {
    require_once $moduleHealthReportServicePath;
}

$guiStudioServicePath = APP_ROOT . '/apps/Studio/Services/GuiStudioService.php';
if (!class_exists(\Apps\Studio\Services\GuiStudioService::class) && is_file($guiStudioServicePath)) {
    require_once $guiStudioServicePath;
}

$routeViewBridgeServicePath = APP_ROOT . '/apps/Platform/Services/RouteViewBridgeService.php';
if (!class_exists(\Apps\Platform\Services\RouteViewBridgeService::class) && is_file($routeViewBridgeServicePath)) {
    require_once $routeViewBridgeServicePath;
}

use App\Core\Auth;
use App\Core\Container;
use App\Core\DB;
use App\Core\PackageManager;
use App\Core\PluginManager;
use Plugins\Coverage\Services\CoverageService;
use App\Core\RouteRuntimeAuthority;
use App\Core\View;
use App\Services\AppExportService;
use App\Services\AppInstallService;
use App\Services\AppLegacyBridgeService;
use App\Services\AppLifecycleService;
use App\Services\AppMigrationService;
use App\Services\AppRegistryService;
use App\Services\DependencyGraphService;
use App\Services\ModuleLifecycleService;
use App\Services\PluginCatalogService;
use App\Core\EntityRuntimeInspector;
use App\Core\RuntimeReportInspector;
use App\Core\MyWorkInspector;
use App\Core\MyWorkEntityQuery;
use App\Core\EntityContext;
use App\Services\MailConfigService;
use App\Services\PasswordPolicyService;
use Apps\Studio\Services\GuiStudioService;
use Apps\Platform\Services\RouteViewBridgeService;
use Plugins\AdminTools\Services\ModuleHealthReportService;
use Plugins\AdminTools\Services\ResilienceActivityMonitorService;
use Plugins\Base\Services\UserDashboardAssignmentService;

final class AdminToolsController
{
    public static function systemWorkspace(View $view): void
    {
        $user = is_array(Auth::user() ?? null) ? Auth::user() : [];
        $context = UserDashboardAssignmentService::resolveUserContext($user);
        $isPlatformAdmin = strtolower(trim((string)($context['authority_role'] ?? ''))) === 'platform_admin';
        $developerToolsVisible = $isPlatformAdmin
            && function_exists('should_show_feature')
            && should_show_feature('admin_tools');
        $view->render('AdminTools::admin/system_tools.php', [
            'pageTitle' => t('admin.system_tools.title'),
            'isPlatformAdmin' => $isPlatformAdmin,
            'developerToolsVisible' => $developerToolsVisible,
        ]);
    }

    public static function emailSettings(View $view): void
    {
        $mailConfig = new MailConfigService();
        $passwordPolicy = new PasswordPolicyService();

        $view->render('AdminTools::admin/email_settings.php', [
            'pageTitle' => 'Identity Security',
            'mailDashboard' => $mailConfig->dashboard(),
            'passwordPolicy' => $passwordPolicy->currentPolicy(),
            'message' => trim((string)($_GET['msg'] ?? '')),
            'error' => trim((string)($_GET['err'] ?? '')),
            'testRecipient' => trim((string)($_GET['test_to'] ?? ((string)(Auth::user()['email'] ?? '')))),
        ]);
    }

    public static function dataControlWorkspace(View $view): void
    {
        $tablesRow = DB::fetchOne(
            'SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = ? LIMIT 1',
            ['BASE TABLE']
        );
        $viewsRow = DB::fetchOne(
            'SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = ? LIMIT 1',
            ['VIEW']
        );

        $pageTitle = function_exists('t')
            ? (string)t('system_tools.data_control.page_title')
            : 'system_tools.data_control.page_title';

        $tableRows = DB::fetchAll(
            'SELECT table_name, table_rows, engine, update_time FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = ? ORDER BY table_name ASC LIMIT 200',
            ['BASE TABLE']
        );
        $viewRows = DB::fetchAll(
            'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = ? ORDER BY table_name ASC LIMIT 200',
            ['VIEW']
        );

        // Permission level detection: check user ACL for data control roles
        $canEdit = false;
        $canGovernApprovals = false;

        if (function_exists('acl_can_any')) {
            $canEdit = acl_can_any(['data_control.editor', 'admin.tools.access']);
            $canGovernApprovals = acl_can_any(['data_control.governor', 'admin.tools.access']);
        }

        // Pending approvals count for governance badge
        $pendingCount = 0;
        if ($canGovernApprovals) {
            try {
                self::ensureApprovalTable();
                $pcRow = DB::fetchOne(
                    "SELECT COUNT(*) AS total FROM data_control_approvals WHERE status = 'pending' LIMIT 1"
                );
                $pendingCount = (int)($pcRow['total'] ?? 0);
            } catch (\Throwable $e) {
                $pendingCount = 0;
            }
        }

        $view->render('AdminTools::admin/data_control.php', [
            'pageTitle' => $pageTitle,
            'dbTableCount' => (int)($tablesRow['total'] ?? 0),
            'dbViewCount' => (int)($viewsRow['total'] ?? 0),
            'tableRows' => is_array($tableRows) ? $tableRows : [],
            'viewRows' => is_array($viewRows) ? $viewRows : [],
            'canEdit' => $canEdit,
            'canGovernApprovals' => $canGovernApprovals,
            'pendingCount' => $pendingCount,
        ]);
    }

    public static function rowBrowser(View $view): void
    {
        $tableName = trim((string)($_GET['table'] ?? ''));
        $pageNum = max(1, (int)($_GET['page'] ?? 1));
        $pageSize = max(1, min(500, (int)($_GET['limit'] ?? 25)));
        $whereFilter = trim((string)($_GET['where'] ?? ''));

        $error = '';
        $columns = [];
        $rows = [];
        $totalRows = 0;
        $totalPages = 1;
        $displayTable = '';
        $pkColumn = '';
        $whereError = '';

        if ($tableName === '' || !preg_match('/^[a-z0-9_]+$/i', $tableName)) {
            $error = t('system_tools.data_control.invalid_table_name');
        } else {
            try {
                // Verify table exists and get column info
                $columnInfo = DB::fetchAll(
                    'SELECT column_name, column_type, is_nullable, column_key, extra FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? ORDER BY ordinal_position ASC',
                    [$tableName]
                );

                if (empty($columnInfo)) {
                    $error = t('system_tools.data_control.table_not_found');
                } else {
                    $columns = is_array($columnInfo) ? $columnInfo : [];

                    // Detect primary key column
                    foreach ($columns as $col) {
                        if ((string)($col['column_key'] ?? '') === 'PRI') {
                            $pkColumn = (string)($col['column_name'] ?? '');
                            break;
                        }
                    }

                    // Validate WHERE filter if provided
                    $safeWhere = '';
                    if ($whereFilter !== '') {
                        if (!preg_match('/^[a-z0-9_\s=<>!\']+$/i', $whereFilter)) {
                            $whereError = t('system_tools.data_control.invalid_where_clause');
                        } else {
                            $safeWhere = $whereFilter;
                        }
                    }

                    $whereSQL = $safeWhere !== '' ? "WHERE {$safeWhere}" : '';

                    // Count total rows
                    $countRow = DB::fetchOne(
                        "SELECT COUNT(*) AS total FROM `{$tableName}` {$whereSQL}"
                    );
                    $totalRows = (int)($countRow['total'] ?? 0);
                    $totalPages = max(1, (int)ceil($totalRows / $pageSize));

                    // Clamp page to valid range
                    if ($pageNum > $totalPages) {
                        $pageNum = max(1, $totalPages);
                    }

                    $offset = ($pageNum - 1) * $pageSize;

                    // Fetch rows with pagination
                    $rows = DB::fetchAll(
                        "SELECT * FROM `{$tableName}` {$whereSQL} LIMIT ? OFFSET ?",
                        [$pageSize, $offset]
                    ) ?? [];
                    $rows = is_array($rows) ? $rows : [];
                    $displayTable = $tableName;
                }
            } catch (\Throwable $e) {
                $error = t('system_tools.data_control.query_error') . ': ' . $e->getMessage();
            }
        }

        // Permission level detection
        $canEdit = false;
        if (function_exists('acl_can_any')) {
            $canEdit = acl_can_any(['data_control.editor', 'admin.tools.access']);
        }

        $view->render('AdminTools::admin/row_browser.php', [
            'pageTitle' => t('system_tools.data_control.row_browser_title'),
            'tableName' => $displayTable,
            'page' => $pageNum,
            'pageSize' => $pageSize,
            'totalRows' => $totalRows,
            'totalPages' => $totalPages,
            'columns' => $columns,
            'rows' => $rows,
            'error' => $error,
            'canEdit' => $canEdit,
            'whereFilter' => $whereFilter,
            'whereError' => $whereError,
            'pkColumn' => $pkColumn,
        ]);
    }

    public static function previewMutation(View $view): void
    {
        $tableName = trim((string)($_GET['table'] ?? ''));
        $operationType = trim((string)($_GET['op'] ?? ''));
        $whereClause = trim((string)($_GET['where'] ?? ''));
        $setClause = trim((string)($_GET['set'] ?? ''));

        $error = '';
        $affectedRows = 0;
        $columns = [];
        $beforeSample = [];
        $displayTable = '';
        $previewOperationType = '';
        $setClauseError = '';

        // Validate set_clause for UPDATE ops before running any query
        if ($setClause !== '' && !preg_match("/^[a-z0-9_\s=,'.:\-]+$/i", $setClause)) {
            $setClauseError = t('system_tools.data_control.invalid_set_clause');
            $setClause = ''; // clear so query doesn't run
        }

        if ($tableName === '' || !preg_match('/^[a-z0-9_]+$/i', $tableName)) {
            $error = t('system_tools.data_control.invalid_table_name');
        } elseif (!in_array($operationType, ['update', 'delete'], true)) {
            $error = t('system_tools.data_control.invalid_operation');
        } else {
            try {
                // Verify table exists and get columns
                $columnInfo = DB::fetchAll(
                    'SELECT column_name, column_type, is_nullable FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? ORDER BY ordinal_position ASC',
                    [$tableName]
                );

                if (empty($columnInfo)) {
                    $error = t('system_tools.data_control.table_not_found');
                } else {
                    $columns = is_array($columnInfo) ? $columnInfo : [];
                    $displayTable = $tableName;
                    $previewOperationType = $operationType;

                    // Get affected row count (preview only, no actual change)
                    if ($whereClause !== '') {
                        // Simple WHERE clause parsing (alphanumeric/operators only for safety)
                        if (!preg_match('/^[a-z0-9_\s=<>!\']+$/i', $whereClause)) {
                            $error = t('system_tools.data_control.invalid_where_clause');
                        } else {
                            $countRow = DB::fetchOne(
                                "SELECT COUNT(*) AS total FROM `{$tableName}` WHERE {$whereClause} LIMIT 1"
                            );
                            $affectedRows = (int)($countRow['total'] ?? 0);

                            // Fetch sample of affected rows (preview)
                            if ($affectedRows > 0) {
                                $beforeSample = DB::fetchAll(
                                    "SELECT * FROM `{$tableName}` WHERE {$whereClause} LIMIT 5"
                                ) ?? [];
                                $beforeSample = is_array($beforeSample) ? $beforeSample : [];
                            }
                        }
                    } else {
                        // No WHERE clause - operation would affect all rows
                        $countRow = DB::fetchOne(
                            "SELECT COUNT(*) AS total FROM `{$tableName}` LIMIT 1"
                        );
                        $affectedRows = (int)($countRow['total'] ?? 0);

                        // Fetch small sample
                        if ($affectedRows > 0) {
                            $beforeSample = DB::fetchAll(
                                "SELECT * FROM `{$tableName}` LIMIT 5"
                            ) ?? [];
                            $beforeSample = is_array($beforeSample) ? $beforeSample : [];
                        }
                    }
                }
            } catch (\Throwable $e) {
                $error = t('system_tools.data_control.query_error') . ': ' . $e->getMessage();
            }
        }

        $canEdit = acl_can_any(['data_control.editor', 'admin.tools.access']);

        $view->render('AdminTools::admin/mutation_preview.php', [
            'pageTitle' => t('system_tools.data_control.mutation_preview_title'),
            'tableName' => $displayTable,
            'operationType' => $previewOperationType,
            'whereClause' => $whereClause,
            'setClause' => $setClause,
            'setClauseError' => $setClauseError,
            'affectedRows' => $affectedRows,
            'columns' => $columns,
            'beforeSample' => $beforeSample,
            'error' => $error,
            'canEdit' => $canEdit,
        ]);
    }

    public static function approvalsQueue(View $view): void
    {
        self::ensureApprovalTable();

        $approvals = DB::fetchAll(
            'SELECT a.id, a.table_name, a.operation, a.where_clause, a.affected_rows,
                    a.requested_by, u.email AS requested_by_email, a.requested_at, a.status
             FROM data_control_approvals a
             LEFT JOIN users u ON u.id = a.requested_by
             ORDER BY a.requested_at DESC LIMIT 100'
        ) ?? [];

        $view->render('AdminTools::admin/approvals_queue.php', [
            'pageTitle' => t('system_tools.data_control.approvals_title'),
            'approvals' => is_array($approvals) ? $approvals : [],
            'message' => trim((string)($_GET['msg'] ?? '')),
        ]);
    }

    public static function approvalDetail(View $view): void
    {
        self::ensureApprovalTable();

        $approvalId = max(0, (int)($_GET['id'] ?? 0));
        if ($approvalId <= 0) {
            header('Location: /admin/system-tools/data-control/approvals');
            exit;
        }

        $record = DB::fetchOne(
            'SELECT a.id, a.table_name, a.operation, a.where_clause, a.set_clause, a.affected_rows,
                    a.requested_by, ru.email AS requested_by_email,
                    a.requested_at, a.status,
                    a.approved_by, au.email AS approved_by_email, a.approved_at,
                    a.executed_by, eu.email AS executed_by_email, a.executed_at
             FROM data_control_approvals a
             LEFT JOIN users ru ON ru.id = a.requested_by
             LEFT JOIN users au ON au.id = a.approved_by
             LEFT JOIN users eu ON eu.id = a.executed_by
             WHERE a.id = ? LIMIT 1',
            [$approvalId]
        );

        if (empty($record)) {
            header('Location: /admin/system-tools/data-control/approvals');
            exit;
        }

        $tableName = (string)($record['table_name'] ?? '');
        $whereClause = (string)($record['where_clause'] ?? '');
        $liveSample = [];
        $liveCount = 0;
        $sampleError = '';

        // Re-fetch live count and sample only for pending/approved records where data still exists
        $status = (string)($record['status'] ?? '');
        if (in_array($status, ['pending', 'approved'], true) && preg_match('/^[a-z0-9_]+$/i', $tableName)) {
            try {
                if ($whereClause !== '' && preg_match('/^[a-z0-9_\s=<>!\']+$/i', $whereClause)) {
                    $cr = DB::fetchOne("SELECT COUNT(*) AS total FROM `{$tableName}` WHERE {$whereClause} LIMIT 1");
                    $liveCount = (int)($cr['total'] ?? 0);
                    if ($liveCount > 0) {
                        $liveSample = DB::fetchAll("SELECT * FROM `{$tableName}` WHERE {$whereClause} LIMIT 10") ?? [];
                    }
                } else {
                    $cr = DB::fetchOne("SELECT COUNT(*) AS total FROM `{$tableName}` LIMIT 1");
                    $liveCount = (int)($cr['total'] ?? 0);
                    if ($liveCount > 0) {
                        $liveSample = DB::fetchAll("SELECT * FROM `{$tableName}` LIMIT 10") ?? [];
                    }
                }
            } catch (\Throwable $e) {
                $sampleError = $e->getMessage();
            }
        }

        $view->render('AdminTools::admin/approval_detail.php', [
            'pageTitle' => t('system_tools.data_control.approval_detail_title'),
            'record'    => $record,
            'liveCount' => $liveCount,
            'liveSample' => is_array($liveSample) ? $liveSample : [],
            'sampleError' => $sampleError,
        ]);
    }

    public static function submitForApproval(): void
    {
        self::ensureApprovalTable();

        $tableName = trim((string)($_POST['table'] ?? ''));
        $operation = trim((string)($_POST['op'] ?? ''));
        $whereClause = trim((string)($_POST['where'] ?? ''));
        $affectedRows = max(0, (int)($_POST['affected_rows'] ?? 0));

        if (!in_array($operation, ['update', 'delete'], true)) {
            return;
        }

        // Duplicate guard: block re-submission of identical pending mutation
        $existing = DB::fetchOne(
            "SELECT id FROM data_control_approvals WHERE table_name = ? AND operation = ? AND where_clause = ? AND status = 'pending' LIMIT 1",
            [$tableName, $operation, $whereClause]
        );
        if (!empty($existing)) {
            header('Location: /admin/system-tools/data-control/approvals?dc_err=duplicate');
            exit;
        }

        $setClause = trim((string)($_POST['set_clause'] ?? ''));
        if ($setClause !== '' && !preg_match("/^[a-z0-9_\s=,'.:\-]+$/i", $setClause)) {
            header('Location: /admin/system-tools/data-control/approvals?dc_err=invalid_input');
            exit;
        }
        // UPDATE requires a SET clause
        if ($operation === 'update' && $setClause === '') {
            header('Location: /admin/system-tools/data-control/approvals?dc_err=missing_set');
            exit;
        }

        $userId = (int)(Auth::user()['id'] ?? 0);
        DB::query(
            'INSERT INTO data_control_approvals (table_name, operation, where_clause, set_clause, affected_rows, requested_by, requested_at, status) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)',
            [$tableName, $operation, $whereClause, $setClause !== '' ? $setClause : null, $affectedRows, $userId, 'pending']
        );

        self::logAudit('submit_for_approval', $tableName, $affectedRows, 'submitted');

        header('Location: /admin/system-tools/data-control/approvals?dc_ok=submitted');
        exit;
    }

    public static function approveMutation(): void
    {
        self::ensureApprovalTable();

        $approvalId = (int)($_POST['approval_id'] ?? 0);
        $action = trim((string)($_POST['action'] ?? ''));

        if (!in_array($action, ['approve', 'reject'], true) || $approvalId <= 0) {
            return;
        }

        $record = DB::fetchOne(
            'SELECT table_name, affected_rows FROM data_control_approvals WHERE id = ? LIMIT 1',
            [$approvalId]
        ) ?? [];

        $userId = (int)(Auth::user()['id'] ?? 0);
        DB::query(
            'UPDATE data_control_approvals SET status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?',
            [$action === 'approve' ? 'approved' : 'rejected', $userId, $approvalId]
        );

        $tableName = (string)($record['table_name'] ?? '');
        $affectedRows = (int)($record['affected_rows'] ?? 0);
        self::logAudit($action === 'approve' ? 'approve_mutation' : 'reject_mutation', $tableName, $affectedRows, $action);

        $flash = $action === 'approve' ? 'approved' : 'rejected';
        header('Location: /admin/system-tools/data-control/approvals?dc_ok=' . $flash);
        exit;
    }

    public static function executeMutation(): void
    {
        self::ensureApprovalTable();

        $approvalId = (int)($_POST['approval_id'] ?? 0);
        if ($approvalId <= 0) {
            return;
        }

        $record = DB::fetchOne(
            'SELECT id, table_name, operation, where_clause, set_clause, status FROM data_control_approvals WHERE id = ? LIMIT 1',
            [$approvalId]
        );

        if (empty($record) || (string)($record['status'] ?? '') !== 'approved') {
            return;
        }

        $tableName = (string)($record['table_name'] ?? '');
        $operation = (string)($record['operation'] ?? '');
        $whereClause = (string)($record['where_clause'] ?? '');

        if (!preg_match('/^[a-z0-9_]+$/i', $tableName)) {
            return;
        }

        try {
            $userId = (int)(Auth::user()['id'] ?? 0);
            if ($operation === 'delete') {
                if (!empty($whereClause)) {
                    DB::query("DELETE FROM `$tableName` WHERE $whereClause");
                }
            } elseif ($operation === 'update') {
                $setClause = (string)($record['set_clause'] ?? '');
                if ($setClause === '') {
                    throw new \RuntimeException('Missing SET clause for UPDATE operation');
                }
                if (!preg_match("/^[a-z0-9_\s=,'.:\-]+$/i", $setClause)) {
                    throw new \RuntimeException('Invalid SET clause detected during execute');
                }
                if (!empty($whereClause)) {
                    DB::query("UPDATE `$tableName` SET $setClause WHERE $whereClause");
                } else {
                    DB::query("UPDATE `$tableName` SET $setClause");
                }
            }

            DB::query(
                'UPDATE data_control_approvals SET status = ?, executed_by = ?, executed_at = NOW() WHERE id = ?',
                ['executed', $userId, $approvalId]
            );
            
            self::logAudit('execute_mutation', $tableName, 0, 'executed');
        } catch (\Throwable $e) {
            // Execution failed - update status
            DB::query(
                'UPDATE data_control_approvals SET status = ?, executed_by = ?, executed_at = NOW() WHERE id = ?',
                ['failed', (int)(Auth::user()['id'] ?? 0), $approvalId]
            );
            
            self::logAudit('execute_mutation', $tableName, 0, 'failed');

            header('Location: /admin/system-tools/data-control/approvals?dc_err=exec_failed');
            exit;
        }

        header('Location: /admin/system-tools/data-control/approvals?dc_ok=executed');
        exit;
    }

    private static function ensureApprovalTable(): void
    {
        static $tableChecked = false;
        if ($tableChecked) {
            return;
        }

        try {
            $result = DB::fetchOne(
                "SELECT COUNT(*) as total FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'data_control_approvals' LIMIT 1"
            );
            if (empty($result) || (int)($result['total'] ?? 0) === 0) {
                DB::query(
                    "CREATE TABLE IF NOT EXISTS data_control_approvals (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        table_name VARCHAR(255) NOT NULL,
                        operation VARCHAR(50) NOT NULL,
                        where_clause LONGTEXT,
                        affected_rows INT DEFAULT 0,
                        requested_by INT,
                        requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        status VARCHAR(50) DEFAULT 'pending',
                        approved_by INT,
                        approved_at TIMESTAMP NULL,
                        executed_by INT,
                        executed_at TIMESTAMP NULL,
                        INDEX idx_status (status),
                        INDEX idx_table (table_name)
                    )"
                );
            } else {
                // Ensure columns exist (for upgrades)
                $columns = DB::fetchAll(
                    "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'data_control_approvals'"
                ) ?? [];
                $colNames = array_map(fn($col) => (string)($col['COLUMN_NAME'] ?? ''), $columns);

                if (!in_array('executed_by', $colNames, true)) {
                    DB::query("ALTER TABLE data_control_approvals ADD COLUMN executed_by INT");
                }
                if (!in_array('executed_at', $colNames, true)) {
                    DB::query("ALTER TABLE data_control_approvals ADD COLUMN executed_at TIMESTAMP NULL");
                }
                if (!in_array('set_clause', $colNames, true)) {
                    DB::query("ALTER TABLE data_control_approvals ADD COLUMN set_clause TEXT NULL AFTER where_clause");
                }
            }
        } catch (\Throwable $e) {
            // Table creation failure - log and continue
        }
        $tableChecked = true;
    }

    public static function auditTrail(View $view): void
    {
        self::ensureAuditTable();

        $filterOp = trim((string)($_GET['op'] ?? ''));
        $filterUser = (int)($_GET['user'] ?? 0);
        $dateFrom = trim((string)($_GET['from'] ?? ''));
        $dateTo = trim((string)($_GET['to'] ?? ''));

        $where = [];
        $params = [];

        if ($filterOp !== '') {
            $allowed = ['submit_for_approval', 'approve_mutation', 'reject_mutation', 'execute_mutation'];
            if (in_array($filterOp, $allowed, true)) {
                $where[] = 'operation = ?';
                $params[] = $filterOp;
            }
        }
        if ($filterUser > 0) {
            $where[] = 'user_id = ?';
            $params[] = $filterUser;
        }
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $where[] = 'created_at >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $where[] = 'created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }

        $whereSQL = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        $logs = DB::fetchAll(
            "SELECT a.id, a.operation, a.user_id, a.table_name, a.affected_rows, a.status, a.created_at, u.email AS user_email FROM data_control_audit_log a LEFT JOIN users u ON u.id = a.user_id $whereSQL ORDER BY a.created_at DESC LIMIT 500",
            $params
        ) ?? [];

        $view->render('AdminTools::admin/audit_trail.php', [
            'pageTitle' => t('system_tools.data_control.audit_trail_title'),
            'logs' => is_array($logs) ? $logs : [],
            'filterOp' => $filterOp,
            'filterUser' => $filterUser,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }

    private static function logAudit(string $operation, ?string $tableName = null, ?int $affectedRows = null, ?string $status = 'completed'): void
    {
        static $auditChecked = false;
        if (!$auditChecked) {
            self::ensureAuditTable();
            $auditChecked = true;
        }

        $userId = (int)(Auth::user()['id'] ?? 0);
        try {
            DB::query(
                'INSERT INTO data_control_audit_log (operation, user_id, table_name, affected_rows, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
                [$operation, $userId, $tableName ?? '', $affectedRows ?? 0, $status]
            );
        } catch (\Throwable $e) {
            // Audit logging failure - silently continue
        }
    }

    private static function ensureAuditTable(): void
    {
        static $tableChecked = false;
        if ($tableChecked) {
            return;
        }

        try {
            $result = DB::fetchOne(
                "SELECT COUNT(*) as total FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'data_control_audit_log' LIMIT 1"
            );
            if (empty($result) || (int)($result['total'] ?? 0) === 0) {
                DB::query(
                    "CREATE TABLE IF NOT EXISTS data_control_audit_log (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        operation VARCHAR(50) NOT NULL,
                        user_id INT,
                        table_name VARCHAR(255),
                        affected_rows INT DEFAULT 0,
                        status VARCHAR(50) DEFAULT 'completed',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_operation (operation),
                        INDEX idx_user (user_id),
                        INDEX idx_created (created_at)
                    )"
                );
            }
        } catch (\Throwable $e) {
            // Table creation failure - log and continue
        }
        $tableChecked = true;
    }

    public static function entityRuntime(View $view): void
    {
        $report = EntityRuntimeInspector::inspect();

        $dryRunResult = null;
        $dryRunError = null;
        $actionInspectResult = null;
        $actionInspectError = null;

        $entityKey = trim((string)($_GET['dry_entity'] ?? ''));
        $currentState = trim((string)($_GET['dry_current'] ?? ''));
        $targetState = trim((string)($_GET['dry_target'] ?? ''));
        $action = trim((string)($_GET['dry_action'] ?? ''));

        if ($entityKey !== '' && $currentState !== '' && $targetState !== '') {
            try {
                $dryRunResult = EntityRuntimeInspector::dryRunTransition(
                    $entityKey,
                    $currentState,
                    $targetState,
                    $action !== '' ? $action : null
                );
            } catch (\Throwable $e) {
                $dryRunError = $e->getMessage();
            }
        }

        $inspectEntity = trim((string)($_GET['inspect_entity'] ?? ''));
        $inspectAction = trim((string)($_GET['inspect_action'] ?? ''));
        $inspectCurrentState = trim((string)($_GET['inspect_current'] ?? ''));

        if ($inspectEntity !== '' && $inspectAction !== '') {
            try {
                $actionInspectResult = EntityRuntimeInspector::inspectAction(
                    $inspectEntity,
                    $inspectAction,
                    $inspectCurrentState !== '' ? $inspectCurrentState : null
                );
            } catch (\Throwable $e) {
                $actionInspectError = $e->getMessage();
            }
        }

        $view->render('AdminTools::admin/entity_runtime.php', [
            'pageTitle' => 'Entity Runtime Inspector',
            'report' => $report,
            'entityCount' => count($report),
            'dryRunResult' => $dryRunResult,
            'dryRunError' => $dryRunError,
            'dryRunParams' => [
                'entity' => $entityKey,
                'current' => $currentState,
                'target' => $targetState,
                'action' => $action,
            ],
            'actionInspectResult' => $actionInspectResult,
            'actionInspectError' => $actionInspectError,
            'actionInspectParams' => [
                'entity' => $inspectEntity,
                'action' => $inspectAction,
                'current' => $inspectCurrentState,
            ],
        ]);
    }

    public static function myWorkRuntime(View $view): void
    {
        $supportedEntities = MyWorkInspector::getSupportedEntityTypes();
        $entityOptions = [];
        foreach ($supportedEntities as $type) {
            $entityOptions[$type] = MyWorkInspector::getEntityTypeLabel($type);
        }

        $explainedItems = [];
        $error = null;
        $singleRecordResult = null;
        $singleRecordError = null;

        $entityType = trim((string)($_GET['inspect_entity'] ?? ''));
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));

        if ($entityType !== '' && in_array($entityType, $supportedEntities, true)) {
            try {
                $rows = self::fetchEntityRows($entityType, $limit);
                $ctx = EntityContext::admin();

                $primaryArea = trim((string)($_GET['primary_area'] ?? 'production'));
                $crossAreasRaw = trim((string)($_GET['cross_areas'] ?? ''));
                $crossAreas = $crossAreasRaw !== '' ? explode(',', $crossAreasRaw) : [];

                $explainedItems = MyWorkInspector::explainItems($rows, $ctx, [
                    'primary_area' => $primaryArea,
                    'cross_areas' => $crossAreas,
                ]);
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        $singleEntityType = trim((string)($_GET['replay_entity'] ?? ''));
        $singleRecordId = (int)($_GET['replay_id'] ?? 0);
        $replayPrimaryArea = trim((string)($_GET['replay_primary_area'] ?? 'production'));
        $replayCrossAreasRaw = trim((string)($_GET['replay_cross_areas'] ?? ''));
        $replayCrossAreas = $replayCrossAreasRaw !== '' ? explode(',', $replayCrossAreasRaw) : [];

        if ($singleEntityType !== '' && $singleRecordId > 0) {
            try {
                $ctx = EntityContext::admin();
                $singleRecordResult = MyWorkInspector::explainSingleRecord($singleEntityType, $singleRecordId, $ctx, [
                    'primary_area' => $replayPrimaryArea,
                    'cross_areas' => $replayCrossAreas,
                ]);
            } catch (\Throwable $e) {
                $singleRecordError = $e->getMessage();
            }
        }

        $view->render('AdminTools::admin/my_work_runtime.php', [
            'pageTitle' => 'My Work Runtime Inspector',
            'entityOptions' => $entityOptions,
            'explainedItems' => $explainedItems,
            'error' => $error,
            'inspectParams' => [
                'entity' => $entityType,
                'limit' => $limit,
                'primary_area' => trim((string)($_GET['primary_area'] ?? 'production')),
                'cross_areas' => trim((string)($_GET['cross_areas'] ?? '')),
            ],
            'singleRecordResult' => $singleRecordResult,
            'singleRecordError' => $singleRecordError,
            'singleRecordParams' => [
                'entity' => $singleEntityType,
                'id' => $singleRecordId,
                'primary_area' => $replayPrimaryArea,
                'cross_areas' => $replayCrossAreasRaw,
            ],
        ]);
    }

    private static function fetchEntityRows(string $entityType, int $limit): array
    {
        $options = ['limit' => $limit];

        return match ($entityType) {
            'daily_order' => MyWorkEntityQuery::fetchDailyOrders($options),
            'production_entry' => MyWorkEntityQuery::fetchProductionEntries($options),
            'production_plan' => MyWorkEntityQuery::fetchProductionPlans($options),
            'qc_entry' => MyWorkEntityQuery::fetchQCEntries($options),
            'dispatch_entry' => MyWorkEntityQuery::fetchDispatchEntries($options),
            default => [],
        };
    }

    public static function stageInspector(View $view): void
    {
        $supportedKeys = EntityRuntimeInspector::getSupportedEntityKeys();
        $entityOptions = [];
        foreach ($supportedKeys as $key) {
            $entityOptions[$key] = EntityRuntimeInspector::getEntityKeyLabel($key);
        }

        $result = null;
        $error = null;

        $entityKey = trim((string)($_GET['inspect_entity'] ?? ''));
        $recordId = (int)($_GET['inspect_id'] ?? 0);

        if ($entityKey !== '' && $recordId > 0) {
            try {
                $ctx = EntityContext::admin();

                $primaryArea = trim((string)($_GET['primary_area'] ?? 'production'));
                $crossAreasRaw = trim((string)($_GET['cross_areas'] ?? ''));
                $crossAreas = $crossAreasRaw !== '' ? explode(',', $crossAreasRaw) : [];

                $result = EntityRuntimeInspector::inspectRecord($entityKey, $recordId, $ctx, [
                    'primary_area' => $primaryArea,
                    'cross_areas' => $crossAreas,
                ]);
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        $view->render('AdminTools::admin/stage_inspector.php', [
            'pageTitle' => 'Stage/Governance Inspector',
            'entityOptions' => $entityOptions,
            'result' => $result,
            'error' => $error,
            'inspectParams' => [
                'entity' => $entityKey,
                'id' => $recordId,
                'primary_area' => trim((string)($_GET['primary_area'] ?? 'production')),
                'cross_areas' => trim((string)($_GET['cross_areas'] ?? '')),
            ],
        ]);
    }

    public static function runtimeReport(View $view): void
    {
        $report = RuntimeReportInspector::generateReport();

        $exportText = null;
        if (!empty($_GET['export']) && $_GET['export'] === 'text') {
            header('Content-Type: text/plain; charset=UTF-8');
            echo RuntimeReportInspector::generateExportText($report);
            exit;
        }

        $view->render('AdminTools::admin/runtime_report.php', [
            'pageTitle' => 'Runtime Diagnostics Report',
            'report' => $report,
            'exportText' => $exportText,
        ]);
    }

    public static function moduleHealth(View $view): void
    {
        $report = ModuleHealthReportService::generate(APP_ROOT);

        if (!empty($_GET['export']) && $_GET['export'] === 'json') {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $view->render('AdminTools::admin/module_health.php', [
            'pageTitle' => 'Module Contract Health',
            'report' => $report,
        ]);
    }

    public static function appsIndex(View $view, Container $c): void
    {
        $migrationService = new AppMigrationService();
        $migrationService->ensureCoreTables();

        $pm = $c->get('plugins');
        if (isset($pm->manifests()['AdminTools']) && !$pm->isInstalled('AdminTools')) {
            try {
                $pm->install('AdminTools');
            } catch (\Throwable $e) {
                // Keep apps page functional even if auto-registration fails.
                error_log('AdminTools auto-install failed: ' . $e->getMessage());
            }
        }

        $catalog = new PluginCatalogService();
        $all = $catalog->visibleManifests((array)$pm->manifests());
        $groupedPlugins = $catalog->groupedVisibleManifests($all);
        $architecture = method_exists($pm, 'architectureReport')
            ? self::filterVisibleArchitecture((array)$pm->architectureReport(), array_keys($all))
            : [];

        $installed = DB::fetchAll("SELECT * FROM installed_plugins ORDER BY name ASC");
        $installedMap = [];
        $activePlugins = [];
        foreach ($installed as $r) {
            $installedMap[$r['name']] = $r;
        }
        foreach ($installed as $r) {
            $name = trim((string)($r['name'] ?? ''));
            $status = trim((string)($r['status'] ?? 'inactive'));
            if ($name !== '' && $status === 'active') {
                $activePlugins[] = $name;
            }
        }

        $routeMap = $c->get('router')->listRoutes();
        $availableGetRoutes = array_keys((array)($routeMap['GET'] ?? []));
        $activePluginMap = array_fill_keys($activePlugins, true);
        $availableRouteMap = array_fill_keys($availableGetRoutes, true);
        $operationalCards = [
            ['path' => '/admin/apps', 'plugin' => 'Base'],
            ['path' => '/products', 'plugin' => 'Products'],
            ['path' => '/machines', 'plugin' => 'Machines'],
            ['path' => '/part-machine-map', 'plugin' => 'PartMachineMap'],
            ['path' => '/daily-orders', 'plugin' => 'DailyOrders'],
            ['path' => '/pre-orders', 'plugin' => 'PreOrders'],
            ['path' => '/production-plans', 'plugin' => 'ProductionPlans'],
            ['path' => '/manufacturing/production-queue', 'plugin' => 'ProductionQueue'],
            ['path' => '/qc-plans', 'plugin' => 'QCPlans'],
            ['path' => '/production-entries', 'plugin' => 'ProductionEntries'],
            ['path' => '/qc-entries', 'plugin' => 'QCEntries'],
            ['path' => '/dispatch-entries', 'plugin' => 'DispatchEntries'],
        ];
        $operationalModuleCount = 0;
        foreach ($operationalCards as $card) {
            if (isset($availableRouteMap[$card['path']]) && isset($activePluginMap[$card['plugin']])) {
                $operationalModuleCount++;
            }
        }

        $currentDbName = (string)(DB::fetchOne('SELECT DATABASE() AS db_name')['db_name'] ?? '');
        $appsBoardBase = self::appsBoardBase();
        $appRegistryRows = self::tableExists('core_apps') ? DB::fetchAll('SELECT * FROM core_apps ORDER BY app_key ASC') : [];
        $studioApps = GuiStudioService::studioRegistryApps();
        $mergedApps = GuiStudioService::mergeAppsForManager($appRegistryRows);
        $appRegistryMap = [];
        foreach ($appRegistryRows as $row) {
            $appKey = trim((string)($row['app_key'] ?? ''));
            if ($appKey !== '') {
                $appRegistryMap[$appKey] = $row;
            }
        }
        $appSuiteWarning = '';
        try {
            $appSuites = self::appSuiteCards($installedMap, $architecture, $appRegistryMap);
        } catch (\Throwable $e) {
            $appSuites = [];
            $appSuiteWarning = 'Suite summary degraded: ' . $e->getMessage();
        }

        $assignmentRows = [];
        $targetRoles = [];
        if (class_exists(UserDashboardAssignmentService::class)) {
            UserDashboardAssignmentService::ensureSchema();
            $assignmentRows = UserDashboardAssignmentService::listAssignmentRows(['status' => 'all'], false);
            foreach ($assignmentRows as $row) {
                $role = trim((string)($row['operational_role'] ?? ($row['role'] ?? '')));
                if ($role === '') {
                    continue;
                }
                $targetRoles[strtolower($role)] = $role;
            }
        }

        $view->render('Base::admin/apps.php', [
            'pageTitle' => 'Admin Tools',
            'plugins' => $all,
            'suiteBuckets' => $catalog->suiteBuckets(),
            'groupedPlugins' => $groupedPlugins,
            'installed' => $installedMap,
            'appSuites' => $appSuites,
            'appSuiteWarning' => $appSuiteWarning,
            'appsBoardBase' => $appsBoardBase,
            'activePluginCount' => count($activePlugins),
            'operationalModuleCount' => $operationalModuleCount,
            'currentDbName' => $currentDbName,
            'architecture' => $architecture,
            'assignmentRows' => $assignmentRows,
            'targetRoles' => array_values($targetRoles),
            'studioApps' => $studioApps,
            'mergedApps' => $mergedApps,
        ]);
    }

    /**
     * @param array<string,mixed> $report
     * @param array<int,string> $visibleNames
     * @return array<string,mixed>
     */
    private static function filterVisibleArchitecture(array $report, array $visibleNames): array
    {
        if ($visibleNames === []) {
            return $report;
        }

        $visibleMap = array_fill_keys($visibleNames, true);
        $plugins = array_intersect_key((array)($report['plugins'] ?? []), $visibleMap);
        $dependencies = array_intersect_key((array)($report['dependencies'] ?? []), $visibleMap);
        $violations = array_values(array_filter(
            (array)($report['violations'] ?? []),
            static fn(array $row): bool => isset($visibleMap[(string)($row['plugin'] ?? '')])
        ));

        $suites = [];
        foreach ((array)($report['suites'] ?? []) as $suiteKey => $pluginNames) {
            $suites[$suiteKey] = array_values(array_filter(
                array_map('strval', (array)$pluginNames),
                static fn(string $pluginName): bool => isset($visibleMap[$pluginName])
            ));
        }

        $warnings = 0;
        $errors = 0;
        $missingMetadata = 0;
        foreach ($plugins as $node) {
            $warnings += count((array)($node['warnings'] ?? []));
            $errors += count((array)($node['errors'] ?? []));
            $missingMetadata += count((array)($node['missing_fields'] ?? [])) > 0 ? 1 : 0;
        }

        $report['plugins'] = $plugins;
        $report['dependencies'] = $dependencies;
        $report['violations'] = $violations;
        $report['suites'] = $suites;
        $report['summary'] = array_merge((array)($report['summary'] ?? []), [
            'plugins' => count($plugins),
            'warnings' => $warnings,
            'errors' => $errors,
            'missing_metadata' => $missingMetadata,
        ]);

        return $report;
    }

    public static function appsUpload(): void
    {
        if (!isset($_FILES['zip'])) {
            throw new \RuntimeException('No file uploaded');
        }

        $packageType = trim((string)($_POST['package_type'] ?? 'module'));
        if ($packageType === 'bundle') {
            $result = (new AppInstallService())->uploadAndRegister($_FILES['zip']);
            $appKey = (string)($result['manifest']['id'] ?? 'bundle');
            self::redirectToAppsBoard(['bundle_ok' => 'Uploaded bundle package: ' . $appKey]);
            exit;
        }

        $saved = PackageManager::uploadPackage($_FILES['zip'], 'module');
        self::redirectToAppsBoard(['pkg_ok' => $saved]);
        exit;
    }

    public static function appsPackageApply(Container $c): void
    {
        $pm = $c->get('plugins');
        $file = (string)($_POST['file'] ?? '');
        $mode = (string)($_POST['mode'] ?? '');

        $meta = PackageManager::readManifestFromZip(PackageManager::packagesDir() . '/' . basename($file));
        if (!$meta['ok']) {
            throw new \RuntimeException('Bad package: ' . $meta['error']);
        }
        $name = (string)$meta['name'];

        if ((string)($meta['package_type'] ?? 'module') === 'bundle') {
            PackageManager::applyPackage($file, $mode);
            self::redirectToAppsBoard(['bundle_ok' => 'Installed bundle package: ' . (string)($meta['app_key'] ?? $name)]);
            exit;
        }

        $st = $pm->status($name);
        if ($st === 'active') {
            throw new \RuntimeException("Deactivate '{$name}' before replacing files.");
        }

        PackageManager::applyPackage($file, $mode);

        self::redirectToAppsBoard(['pkg_applied' => $name]);
        exit;
    }

    public static function appsDeps(View $view, Container $c): void
    {
        $pm = $c->get('plugins');
        $name = trim((string)($_GET['name'] ?? ''));
        $manifest = is_array(($pm->manifests()[$name] ?? null)) ? (array)$pm->manifests()[$name] : [];
        $status = $name !== '' ? (string)($pm->status($name) ?? 'not-installed') : 'not-installed';
        $installed = $name !== '' && $pm->isInstalled($name);
        $blockedBy = $name !== '' ? array_values(array_map('strval', (array)$pm->canUninstall($name))) : [];

        $isCoreSuite = strtolower((string)($manifest['suite'] ?? '')) === 'core';
        $isEngineType = strtolower((string)($manifest['type'] ?? '')) === 'engine';
        $canExport = ($name !== 'Base') && !$isCoreSuite && !$isEngineType && in_array($status, ['active', 'inactive'], true);

        $module = [];
        $ownerApp = [];
        $routeDiagnostics = [];
        $navigationSummary = [];
        $dataOwnership = [];
        $migrationSummary = [];
        $integrityNotes = [];
        $architectureNode = [];
        $dependencyDetail = [];
        $dependencyRows = ['direct' => [], 'reverse' => []];
        $actionState = [];

        if ($manifest !== []) {
            $module = self::modulePanelRow($name);
            $ownerApp = self::appManifestByKey((string)($manifest['owner_app'] ?? ''));
            $dependencyDetail = (new DependencyGraphService())->moduleDetail($name);
            $routeDiagnostics = self::moduleRouteDiagnostics(self::moduleRuntimePath($name), $manifest, $c->get('router')->listRoutes());
            $navigationSummary = self::moduleNavigationSummary($ownerApp, $routeDiagnostics);
            $dataOwnership = self::moduleDataOwnership(self::moduleRuntimePath($name), $manifest);
            $migrationSummary = self::moduleMigrationSummary($name, self::moduleRuntimePath($name));

            $architecture = method_exists($pm, 'architectureReport') ? (array)$pm->architectureReport() : [];
            $architectureNode = (array)(($architecture['plugins'] ?? [])[$name] ?? []);

            $dependencyRows = self::moduleDependencyRows($dependencyDetail, (array)$pm->manifests(), $pm);
            $integrityNotes = self::moduleIntegrityNotes(
                $name,
                $status,
                $module,
                $dependencyDetail,
                $routeDiagnostics,
                $navigationSummary,
                $migrationSummary,
                $architectureNode,
                $blockedBy
            );
            $actionState = self::moduleActionState($name, $manifest, $status, $installed, $blockedBy, $canExport);
        }

        $view->render('Base::admin/deps.php', [
            'pageTitle' => 'Module Lifecycle Detail',
            'name' => $name,
            'manifest' => $manifest,
            'status' => $status,
            'installed' => $installed,
            'blockedBy' => $blockedBy,
            'canExport' => $canExport,
            'module' => $module,
            'ownerApp' => $ownerApp,
            'dependencyDetail' => $dependencyDetail,
            'dependencyRows' => $dependencyRows,
            'routeDiagnostics' => $routeDiagnostics,
            'navigationSummary' => $navigationSummary,
            'dataOwnership' => $dataOwnership,
            'migrationSummary' => $migrationSummary,
            'integrityNotes' => $integrityNotes,
            'architectureNode' => $architectureNode,
            'actionState' => $actionState,
            'appsBoardBase' => self::appsBoardBase(),
        ]);
    }

    public static function appsExport(Container $c): void
    {
        $pm = $c->get('plugins');
        $name = trim((string)($_GET['name'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException('Missing plugin name for export');
        }

        $manifest = (array)($pm->manifests()[$name] ?? []);
        if (!$manifest) {
            throw new \RuntimeException('Plugin not found: ' . $name);
        }

        $status = (string)($pm->status($name) ?? 'not-installed');
        $isCoreSuite = strtolower((string)($manifest['suite'] ?? '')) === 'core';
        $isEngineType = strtolower((string)($manifest['type'] ?? '')) === 'engine';
        $isExportable = ($name !== 'Base') && !$isCoreSuite && !$isEngineType && in_array($status, ['active', 'inactive'], true);

        if (!$isExportable) {
            throw new \RuntimeException('Plugin is not exportable in current state');
        }

        $zipPath = PackageManager::exportPluginZip($name);
        self::streamDownload($zipPath, basename($zipPath), PackageManager::exportsDir());
    }

    public static function appsAction(Container $c): void
    {
        $pm = $c->get('plugins');
        $name = (string)($_POST['name'] ?? '');
        $action = (string)($_POST['action'] ?? '');
        $returnTo = trim((string)($_POST['return_to'] ?? ''));

        if ($name !== '') {
            try {
                if ($action === 'install') {
                    $pm->install($name);
                    self::redirectAfterModuleAction($name, ['module_ok' => 'Installed: ' . $name], $returnTo);
                    exit;
                }

                if ($action === 'enable' || $action === 'module_activate') {
                    $pm->enable($name);
                    self::redirectAfterModuleAction($name, ['module_ok' => 'Activated: ' . $name], $returnTo);
                    exit;
                }

                if ($action === 'update' || $action === 'module_update') {
                    $pm->update($name);
                    self::redirectAfterModuleAction($name, ['module_ok' => 'Updated: ' . $name], $returnTo);
                    exit;
                }

                if ($action === 'disable' || $action === 'module_deactivate') {
                    $pm->disable($name);
                    self::redirectAfterModuleAction($name, ['module_ok' => 'Deactivated: ' . $name], $returnTo);
                    exit;
                }

                if ($action === 'repair_module') {
                    (new ModuleLifecycleService())->run($name, 'repair');
                    self::redirectAfterModuleAction($name, ['module_ok' => 'Repaired module registration and integrity state: ' . $name], $returnTo);
                    exit;
                }

                if ($action === 'recover_module') {
                    self::recoverModule($pm, $name);
                    self::redirectAfterModuleAction($name, ['module_ok' => 'Recovered module to a known-good installed state: ' . $name], $returnTo);
                    exit;
                }

                if ($action === 'uninstall_module') {
                    $status = (string)($pm->status($name) ?? 'not-installed');
                    if ($status !== 'inactive') {
                        throw new \RuntimeException('Uninstall requires the module to be inactive first. Deactivate before uninstall.');
                    }

                    $blockedBy = $pm->canUninstall($name);
                    if ($blockedBy) {
                        throw new \RuntimeException('DEPENDENCY_BLOCK:' . implode(',', array_map('strval', (array)$blockedBy)));
                    }

                    $pm->uninstall($name, false);
                    self::redirectAfterModuleAction($name, ['module_ok' => 'Uninstalled module runtime without purging data: ' . $name], $returnTo);
                    exit;
                }

                if ($action === 'purge') {
                    $status = $pm->status($name);
                    if ($status !== 'inactive') {
                        throw new \RuntimeException('Purge requires the module to be inactive first.');
                    }

                    $confirmation = trim((string)($_POST['purge_confirm'] ?? ''));
                    if ($confirmation !== $name) {
                        throw new \RuntimeException('Type the exact module name to confirm purge.');
                    }

                    $blockedBy = $pm->canUninstall($name);
                    if ($blockedBy) {
                        throw new \RuntimeException('DEPENDENCY_BLOCK:' . implode(',', array_map('strval', (array)$blockedBy)));
                    }

                    $pm->uninstall($name, true);
                    self::redirectAfterModuleAction($name, ['module_ok' => 'Purged module data and registration: ' . $name], $returnTo);
                    exit;
                }
            } catch (\Throwable $e) {
                if ($returnTo === 'detail') {
                    self::redirectToModuleDetail($name, ['module_err' => $e->getMessage()]);
                    exit;
                }

                throw $e;
            }
        }

        if ($action === 'update_all') {
            $updated = $pm->updateAllInstalled();
            self::redirectToAppsBoard(['updated' => (string)count($updated)]);
            exit;
        }

        if ($action === 'apply_and_update_all') {
            $packages = class_exists(PackageManager::class) ? PackageManager::listPackages() : [];

            $appliedCount = 0;
            $skippedInvalid = 0;
            $skippedActive = 0;
            $applyErrors = 0;

            foreach ($packages as $pkg) {
                $file = (string)($pkg['file'] ?? '');
                $meta = $pkg['meta'] ?? [];
                $ok = (bool)($meta['ok'] ?? false);
                if (!$ok) {
                    $skippedInvalid++;
                    continue;
                }

                $pluginName = (string)($meta['name'] ?? '');
                if ($pluginName === '') {
                    $skippedInvalid++;
                    continue;
                }

                $status = $pm->status($pluginName);
                if ($status === 'active') {
                    $skippedActive++;
                    continue;
                }

                $zipVer = (string)($meta['version'] ?? '0.0.0');
                $installed = DB::fetchOne('SELECT version FROM installed_plugins WHERE name=? LIMIT 1', [$pluginName]);
                $installedVer = (string)($installed['version'] ?? '');

                $folderExists = is_dir(PackageManager::pluginSourcePath($pluginName));
                $isInstalled = is_array($installed);

                $mode = (!$folderExists || !$isInstalled)
                    ? 'install'
                    : (($installedVer !== '' && version_compare($zipVer, $installedVer, '>')) ? 'upgrade' : 'repair');

                try {
                    PackageManager::applyPackage($file, $mode);
                    $appliedCount++;
                } catch (\Throwable $e) {
                    $applyErrors++;
                }
            }

            $updated = $pm->updateAllInstalled();
            $qs = http_build_query([
                'pkg_sync' => 1,
                'pkg_applied_count' => $appliedCount,
                'pkg_skipped_active' => $skippedActive,
                'pkg_skipped_invalid' => $skippedInvalid,
                'pkg_apply_errors' => $applyErrors,
                'updated' => count($updated),
            ]);
            header('Location: ' . self::appsBoardBase() . '?' . $qs);
            exit;
        }

        $appKey = (string)($_POST['app_key'] ?? '');
        if ($appKey !== '') {
            $appSource = strtolower(trim((string)($_POST['app_source'] ?? 'core')));
            if ($appSource === 'studio') {
                if ($action === 'studio_enable') {
                    if (!GuiStudioService::setStudioAppEnabled($appKey, true)) {
                        throw new \RuntimeException('Studio app not found: ' . $appKey);
                    }
                    self::redirectToAppsBoard(['bundle_ok' => 'Activated: ' . $appKey]);
                    exit;
                }
                if ($action === 'studio_disable') {
                    if (!GuiStudioService::setStudioAppEnabled($appKey, false)) {
                        throw new \RuntimeException('Studio app not found: ' . $appKey);
                    }
                    self::redirectToAppsBoard(['bundle_ok' => 'Deactivated: ' . $appKey]);
                    exit;
                }
                throw new \RuntimeException('Unsupported studio action');
            }

            $installer = new AppInstallService();
            $lifecycle = new AppLifecycleService();

            if ($action === 'bundle_install') {
                $installer->install($appKey);
                self::redirectToAppsBoard(['bundle_ok' => 'Installed: ' . $appKey]);
                exit;
            }

            if ($action === 'bundle_enable') {
                $lifecycle->enable($appKey);
                self::redirectToAppsBoard(['bundle_ok' => 'Activated: ' . $appKey]);
                exit;
            }

            if ($action === 'bundle_disable') {
                if (self::isProtectedCoreApp($appKey)) {
                    self::redirectToAppsBoard(['bundle_err' => 'Deactivation is blocked for protected core apps: ' . $appKey]);
                    exit;
                }
                $lifecycle->disable($appKey);
                self::redirectToAppsBoard(['bundle_ok' => 'Deactivated: ' . $appKey]);
                exit;
            }

            if ($action === 'bundle_repair') {
                $lifecycle->repair($appKey);
                self::redirectToAppsBoard(['bundle_ok' => 'Repaired: ' . $appKey]);
                exit;
            }

            if ($action === 'bundle_uninstall') {
                $lifecycle->uninstallSoft($appKey);
                self::redirectToAppsBoard(['bundle_ok' => 'Uninstalled: ' . $appKey]);
                exit;
            }

            if ($action === 'bundle_purge') {
                $lifecycle->purge($appKey);
                self::redirectToAppsBoard(['bundle_ok' => 'Purged: ' . $appKey]);
                exit;
            }

            if ($action === 'bundle_recover') {
                $lifecycle->recoverFromBroken($appKey);
                self::redirectToAppsBoard(['bundle_ok' => 'Recovered: ' . $appKey]);
                exit;
            }

            if ($action === 'bundle_export') {
                $zipPath = (new AppExportService())->exportToZip($appKey);
                self::streamDownload($zipPath, basename($zipPath), APP_ROOT . '/packages/exports');
                return;
            }

            exit;
        }

        throw new \RuntimeException('Unknown action');
    }

    /**
     * @param array<string,array<string,mixed>> $installedMap
     * @param array<string,mixed> $architecture
     * @param array<string,array<string,mixed>> $appRegistryMap
     * @return array<int,array<string,mixed>>
     */
    private static function appSuiteCards(array $installedMap, array $architecture, array $appRegistryMap): array
    {
        $cards = [];
        $appManifestFiles = glob(APP_ROOT . '/apps/*/manifest.json') ?: [];
        sort($appManifestFiles);

        foreach ($appManifestFiles as $manifestFile) {
            try {
                $manifest = json_decode((string)file_get_contents($manifestFile), true);
                if (!is_array($manifest)) {
                    continue;
                }

                $appKey = trim((string)($manifest['id'] ?? ''));
                if ($appKey === '') {
                    continue;
                }

                $row = $appRegistryMap[$appKey] ?? null;
                $status = is_array($row) ? (string)($row['status'] ?? AppRegistryService::STATUS_UPLOADED) : 'local_only';
                $childPlugins = AppLegacyBridgeService::legacyPluginsForApp($appKey, $manifest);
                $childTotal = count($childPlugins);
                $childInstalled = 0;
                $childActive = 0;
                $childInactive = 0;
                $childMissing = 0;
                $archWarningCount = 0;
                $archErrorCount = 0;

                foreach ($childPlugins as $pluginName) {
                    $pluginRow = $installedMap[$pluginName] ?? null;
                    $pluginStatus = is_array($pluginRow) ? (string)($pluginRow['status'] ?? 'not-installed') : 'not-installed';
                    if ($pluginStatus === 'active') {
                        $childInstalled++;
                        $childActive++;
                    } elseif ($pluginStatus === 'inactive') {
                        $childInstalled++;
                        $childInactive++;
                    } else {
                        $childMissing++;
                    }

                    $archNode = (array)($architecture['plugins'][$pluginName] ?? []);
                    $archWarningCount += count((array)($archNode['warnings'] ?? []));
                    $archErrorCount += count((array)($archNode['errors'] ?? []));
                }

                $bundleDegraded = in_array($status, [AppRegistryService::STATUS_BROKEN, AppRegistryService::STATUS_UPGRADE_PENDING], true)
                    || $childMissing > 0
                    || ($status === AppRegistryService::STATUS_ENABLED && $childInactive > 0)
                    || $archErrorCount > 0;

                $dependencyHealth = 'Healthy';
                if ($status === 'local_only') {
                    $dependencyHealth = 'Local manifest only';
                } elseif ($status === AppRegistryService::STATUS_UPLOADED || $status === AppRegistryService::STATUS_UNINSTALLED) {
                    $dependencyHealth = 'Not installed';
                } elseif ($bundleDegraded) {
                    $dependencyHealth = 'Needs repair';
                } elseif ($status === AppRegistryService::STATUS_DISABLED || $status === AppRegistryService::STATUS_INSTALLED) {
                    $dependencyHealth = 'Installed but inactive';
                }

                $cards[] = [
                    'app_key' => $appKey,
                    'app_name' => (string)($manifest['name'] ?? $appKey),
                    'version' => (string)($row['version'] ?? $manifest['version'] ?? ''),
                    'status' => $status,
                    'app_type' => (string)($manifest['type'] ?? ($row['app_type'] ?? 'business')),
                    'manifest' => $manifest,
                    'registry_row' => $row,
                    'child_plugins' => $childPlugins,
                    'child_total' => $childTotal,
                    'child_installed' => $childInstalled,
                    'child_active' => $childActive,
                    'child_inactive' => $childInactive,
                    'child_missing' => $childMissing,
                    'arch_warning_count' => $archWarningCount,
                    'arch_error_count' => $archErrorCount,
                    'dependency_health' => $dependencyHealth,
                    'bundle_degraded' => $bundleDegraded,
                ];
            } catch (\Throwable $e) {
                $cards[] = [
                    'app_key' => basename(dirname($manifestFile)),
                    'app_name' => basename(dirname($manifestFile)),
                    'version' => '',
                    'status' => AppRegistryService::STATUS_BROKEN,
                    'app_type' => 'business',
                    'manifest' => [],
                    'registry_row' => null,
                    'child_plugins' => [],
                    'child_total' => 0,
                    'child_installed' => 0,
                    'child_active' => 0,
                    'child_inactive' => 0,
                    'child_missing' => 0,
                    'arch_warning_count' => 0,
                    'arch_error_count' => 1,
                    'dependency_health' => 'Suite summary failed',
                    'bundle_degraded' => true,
                    'warning' => $e->getMessage(),
                ];
            }
        }

        usort($cards, static function (array $left, array $right): int {
            $order = ['manufacturing' => 0, 'sbaio' => 1];
            $leftRank = $order[(string)($left['app_key'] ?? '')] ?? 50;
            $rightRank = $order[(string)($right['app_key'] ?? '')] ?? 50;
            if ($leftRank !== $rightRank) {
                return $leftRank <=> $rightRank;
            }
            return strtolower((string)($left['app_name'] ?? '')) <=> strtolower((string)($right['app_name'] ?? ''));
        });

        return $cards;
    }

    private static function appsBoardBase(): string
    {
        $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/admin/apps'), PHP_URL_PATH) ?: '/admin/apps';
        return str_starts_with((string)$path, '/tools/apps') ? '/tools/apps' : '/admin/apps';
    }

    /**
     * @param array<string,string> $query
     */
    private static function redirectToAppsBoard(array $query = []): void
    {
        $base = self::appsBoardBase();
        $qs = $query ? ('?' . http_build_query($query)) : '';
        header('Location: ' . $base . $qs);
    }

    /**
     * @param array<string,string> $query
     */
    private static function redirectToModuleDetail(string $name, array $query = []): void
    {
        $base = self::appsBoardBase() . '/deps';
        $query = array_merge(['name' => $name], $query);
        header('Location: ' . $base . '?' . http_build_query($query));
    }

    /**
     * @param array<string,string> $query
     */
    private static function redirectAfterModuleAction(string $name, array $query, string $returnTo): void
    {
        if ($returnTo === 'detail') {
            self::redirectToModuleDetail($name, $query);
            return;
        }

        self::redirectToAppsBoard($query);
    }

    /**
     * @return array<string,mixed>
     */
    private static function modulePanelRow(string $name): array
    {
        foreach ((new ModuleLifecycleService())->panelRows() as $row) {
            if ((string)($row['name'] ?? '') === $name) {
                return $row;
            }
        }

        return [];
    }

    /**
     * @return array<string,mixed>
     */
    private static function appManifestByKey(string $appKey): array
    {
        $appKey = strtolower(trim($appKey));
        if ($appKey === '') {
            return [];
        }

        foreach (glob(APP_ROOT . '/apps/*/manifest.json') ?: [] as $file) {
            $manifest = json_decode((string)file_get_contents($file), true);
            if (!is_array($manifest)) {
                continue;
            }

            $candidate = strtolower(trim((string)($manifest['app_key'] ?? ($manifest['id'] ?? ''))));
            if ($candidate !== $appKey) {
                continue;
            }

            return [
                'manifest' => $manifest,
                'path' => dirname($file),
                'display_name' => (string)($manifest['name'] ?? $appKey),
            ];
        }

        return [];
    }

    private static function moduleRuntimePath(string $name): string
    {
        $candidates = glob(APP_ROOT . '/apps/*/modules/' . $name, GLOB_ONLYDIR) ?: [];
        if (is_dir(APP_ROOT . '/plugins/' . $name)) {
            $candidates[] = APP_ROOT . '/plugins/' . $name;
        }

        sort($candidates);
        return $candidates[0] ?? '';
    }

    private static function isProtectedCoreApp(string $appKey): bool
    {
        $key = strtolower(trim($appKey));
        if ($key === '') {
            return false;
        }

        // Keep foundational runtime/governance apps always active.
        $protectedKeys = [
            'acl',
            'bus',
            'base',
            'core',
            'platform',
            'shell',
        ];

        if (in_array($key, $protectedKeys, true)) {
            return true;
        }

        $owner = self::appManifestByKey($key);
        $manifest = is_array($owner['manifest'] ?? null) ? (array)$owner['manifest'] : [];
        $type = strtolower(trim((string)($manifest['type'] ?? '')));

        return in_array($type, ['engine', 'framework', 'system'], true);
    }

    /**
     * @param array<string,mixed> $manifest
     * @param array<string,array<string,callable>> $routeMap
     * @return array<string,mixed>
     */
    private static function moduleRouteDiagnostics(string $modulePath, array $manifest, array $routeMap): array
    {
        $entry = trim((string)($manifest['entry'] ?? 'routes.php'));
        $routeFile = $modulePath !== '' ? rtrim($modulePath, '/') . '/' . ltrim($entry, '/') : '';
        $rows = [];
        $declaredPaths = [];

        if ($routeFile !== '' && is_file($routeFile)) {
            $contents = (string)file_get_contents($routeFile);
            if (preg_match_all('/\\$router->(get|post)\\(\\s*[\'"]([^\'"]+)[\'"]/', $contents, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $method = strtoupper((string)($match[1] ?? 'GET'));
                    $path = self::normalizePath((string)($match[2] ?? ''));
                    if ($path === '') {
                        continue;
                    }

                    if (!isset($rows[$path])) {
                        $rows[$path] = [
                            'path' => $path,
                            'methods' => [],
                            'registered' => false,
                        ];
                    }

                    $rows[$path]['methods'][$method] = true;
                    $registered = isset($routeMap[$method][$path]);
                    $rows[$path]['registered'] = $rows[$path]['registered'] || $registered;
                    $declaredPaths[$path] = true;
                }
            }
        }

        $routeRows = array_values(array_map(static function (array $row): array {
            $row['methods'] = array_keys((array)$row['methods']);
            sort($row['methods']);
            return $row;
        }, $rows));

        usort($routeRows, static function (array $left, array $right): int {
            return strcmp((string)($left['path'] ?? ''), (string)($right['path'] ?? ''));
        });

        $registeredCount = count(array_filter($routeRows, static fn(array $row): bool => !empty($row['registered'])));

        return [
            'file' => $routeFile,
            'declared_count' => count($routeRows),
            'registered_count' => $registeredCount,
            'declared_paths' => array_keys($declaredPaths),
            'rows' => $routeRows,
        ];
    }

    /**
     * @param array<string,mixed> $ownerApp
     * @param array<string,mixed> $routeDiagnostics
     * @return array<string,mixed>
     */
    private static function moduleNavigationSummary(array $ownerApp, array $routeDiagnostics): array
    {
        $manifest = is_array($ownerApp['manifest'] ?? null) ? (array)$ownerApp['manifest'] : [];
        $declaredPaths = array_fill_keys(array_map('strval', (array)($routeDiagnostics['declared_paths'] ?? [])), true);
        $rows = [];

        foreach ((array)($manifest['menus'] ?? []) as $menu) {
            if (!is_array($menu)) {
                continue;
            }

            $url = self::normalizePath((string)($menu['url'] ?? ''));
            if ($url === '' || !isset($declaredPaths[$url])) {
                continue;
            }

            $rows[] = [
                'label' => trim((string)($menu['label'] ?? ($menu['label_key'] ?? $url))),
                'key' => trim((string)($menu['key'] ?? '')),
                'url' => $url,
                'permission' => trim((string)($menu['perm'] ?? '')),
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            return strcmp((string)($left['url'] ?? ''), (string)($right['url'] ?? ''));
        });

        return [
            'count' => count($rows),
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string,mixed> $manifest
     * @return array<string,mixed>
     */
    private static function moduleDataOwnership(string $modulePath, array $manifest): array
    {
        $ownedTables = array_values(array_unique(array_filter(array_map('strval', (array)($manifest['required_tables'] ?? [])))));
        $storageObjects = [];

        if ($modulePath !== '' && is_dir($modulePath)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($modulePath, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                    continue;
                }

                $ext = strtolower((string)$file->getExtension());
                if (!in_array($ext, ['php', 'sql'], true)) {
                    continue;
                }

                $contents = (string)file_get_contents($file->getPathname());
                if (preg_match_all('/(?:CREATE|ALTER|DROP)\\s+TABLE(?:\\s+IF\\s+(?:NOT\\s+)?EXISTS)?\\s+`?([a-zA-Z0-9_]+)`?/i', $contents, $matches)) {
                    foreach ((array)($matches[1] ?? []) as $table) {
                        $table = trim((string)$table);
                        if ($table !== '') {
                            $ownedTables[] = $table;
                        }
                    }
                }

                if (preg_match_all('/storage\\/[A-Za-z0-9_\\-\\/\\.]+/', $contents, $storageMatches)) {
                    foreach ((array)($storageMatches[0] ?? []) as $path) {
                        $storageObjects[] = trim((string)$path);
                    }
                }
            }
        }

        $ownedTables = array_values(array_unique($ownedTables));
        sort($ownedTables);
        $storageObjects = array_values(array_unique($storageObjects));
        sort($storageObjects);

        return [
            'tables' => $ownedTables,
            'storage_objects' => $storageObjects,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function moduleMigrationSummary(string $name, string $modulePath): array
    {
        $applied = [];
        if (self::tableExists('migrations')) {
            $applied = DB::fetchAll('SELECT filename FROM migrations WHERE plugin=? ORDER BY filename ASC', [$name]);
        }

        $migrationDir = $modulePath !== '' ? rtrim($modulePath, '/') . '/migrations' : '';
        $files = is_dir($migrationDir) ? (glob($migrationDir . '/*.sql') ?: []) : [];
        $declared = array_map(static fn(string $file): string => basename($file), $files);
        sort($declared);
        $appliedNames = array_values(array_map(static fn(array $row): string => (string)($row['filename'] ?? ''), $applied));
        $pending = array_values(array_diff($declared, $appliedNames));

        return [
            'install_script' => $modulePath !== '' && is_file($modulePath . '/install.php'),
            'uninstall_script' => $modulePath !== '' && is_file($modulePath . '/uninstall.php'),
            'bootstrap_script' => $modulePath !== '' && is_file($modulePath . '/bootstrap.php'),
            'migration_dir' => $migrationDir,
            'declared' => $declared,
            'applied' => $appliedNames,
            'pending' => $pending,
        ];
    }

    /**
     * @param array<string,mixed> $dependencyDetail
     * @param array<string,array<string,mixed>> $manifests
     * @return array<string,array<int,array<string,string>>>
     */
    private static function moduleDependencyRows(array $dependencyDetail, array $manifests, PluginManager $pm): array
    {
        $build = static function (array $names) use ($manifests, $pm): array {
            $rows = [];
            foreach ($names as $dependencyName) {
                $dependencyName = trim((string)$dependencyName);
                if ($dependencyName === '') {
                    continue;
                }

                $manifest = is_array(($manifests[$dependencyName] ?? null)) ? (array)$manifests[$dependencyName] : [];
                $rows[] = [
                    'name' => $dependencyName,
                    'display_name' => trim((string)($manifest['display_name'] ?? $dependencyName)),
                    'status' => (string)($pm->status($dependencyName) ?? 'not-installed'),
                    'installed' => $pm->isInstalled($dependencyName) ? 'yes' : 'no',
                ];
            }

            usort($rows, static function (array $left, array $right): int {
                return strcmp((string)($left['display_name'] ?? ''), (string)($right['display_name'] ?? ''));
            });

            return $rows;
        };

        return [
            'direct' => $build((array)($dependencyDetail['direct_dependencies'] ?? [])),
            'reverse' => $build((array)($dependencyDetail['reverse_dependencies'] ?? [])),
        ];
    }

    /**
     * @param array<string,mixed> $module
     * @param array<string,mixed> $dependencyDetail
     * @param array<string,mixed> $routeDiagnostics
     * @param array<string,mixed> $navigationSummary
     * @param array<string,mixed> $migrationSummary
     * @param array<string,mixed> $architectureNode
     * @param array<int,string> $blockedBy
     * @return array<int,string>
     */
    private static function moduleIntegrityNotes(
        string $name,
        string $status,
        array $module,
        array $dependencyDetail,
        array $routeDiagnostics,
        array $navigationSummary,
        array $migrationSummary,
        array $architectureNode,
        array $blockedBy
    ): array {
        $notes = [];
        $dependencyHealth = is_array($dependencyDetail['dependency_health'] ?? null) ? (array)$dependencyDetail['dependency_health'] : [];
        $archErrors = count((array)($architectureNode['errors'] ?? []));
        $archWarnings = count((array)($architectureNode['warnings'] ?? []));

        if ($dependencyHealth !== []) {
            $notes[] = 'Dependency health: ' . (string)($dependencyHealth['summary'] ?? 'No dependency blockers detected.');
        }
        if (!empty($module['missing_tables'])) {
            $notes[] = count((array)$module['missing_tables']) . ' required table check(s) are failing.';
        }
        if (!empty($module['schema_gaps'])) {
            $notes[] = count((array)$module['schema_gaps']) . ' schema gap(s) need review.';
        }
        if (!empty($module['dependency_errors'])) {
            $notes[] = count((array)$module['dependency_errors']) . ' runtime dependency validation issue(s) were recorded.';
        }
        if ($archErrors > 0 || $archWarnings > 0) {
            $notes[] = 'Architecture diagnostics report ' . $archErrors . ' error(s) and ' . $archWarnings . ' warning(s).';
        }
        if ((int)($routeDiagnostics['declared_count'] ?? 0) > 0 && $status === 'active' && (int)($routeDiagnostics['registered_count'] ?? 0) < (int)($routeDiagnostics['declared_count'] ?? 0)) {
            $notes[] = 'Not every declared route is registered in the live runtime.';
        }
        if ((int)($navigationSummary['count'] ?? 0) === 0 && (int)($routeDiagnostics['declared_count'] ?? 0) > 0) {
            $notes[] = 'No direct menu ownership was matched to this module routes.';
        }
        if (!empty($migrationSummary['pending'])) {
            $notes[] = count((array)$migrationSummary['pending']) . ' migration file(s) are present without recorded application.';
        }
        if ($blockedBy !== []) {
            $notes[] = 'Reverse dependency guard is active for ' . count($blockedBy) . ' dependent module(s).';
        }
        if ($notes === []) {
            $notes[] = 'Runtime diagnostics look consistent for ' . $name . '.';
        }

        return $notes;
    }

    /**
     * @param array<string,mixed> $manifest
     * @param array<int,string> $blockedBy
     * @return array<string,mixed>
     */
    private static function moduleActionState(string $name, array $manifest, string $status, bool $installed, array $blockedBy, bool $canExport): array
    {
        $isEngineType = strtolower((string)($manifest['type'] ?? '')) === 'engine';
        $canUninstall = (bool)($manifest['can_uninstall'] ?? true) && !$isEngineType && $name !== 'Base';
        $isProtected = $name === 'Base' || $isEngineType;

        return [
            'repair' => ['visible' => true, 'enabled' => !$isProtected],
            'recover' => ['visible' => true, 'enabled' => !$isProtected],
            'activate' => ['visible' => $installed && $status === 'inactive', 'enabled' => true],
            'deactivate' => ['visible' => $status === 'active', 'enabled' => !$isProtected],
            'update' => ['visible' => $installed, 'enabled' => !$isProtected],
            'export' => ['visible' => $canExport, 'enabled' => $canExport],
            'uninstall' => [
                'visible' => $installed && $canUninstall,
                'enabled' => $canUninstall && $status === 'inactive' && $blockedBy === [],
                'guard_reason' => $status !== 'inactive'
                    ? 'Deactivate before uninstalling this module.'
                    : ($blockedBy !== [] ? 'Dependent modules must be handled first.' : ''),
            ],
            'purge' => [
                'visible' => $installed && $canUninstall,
                'enabled' => $status === 'inactive' && $blockedBy === [],
                'guard_reason' => $status !== 'inactive'
                    ? 'Deactivate or uninstall the module first.'
                    : ($blockedBy !== [] ? 'Dependent modules must be handled first.' : ''),
            ],
        ];
    }

    private static function recoverModule(PluginManager $pm, string $name): void
    {
        $service = new ModuleLifecycleService();
        $service->run($name, 'repair');
        $service->run($name, 'validate');
    }

    private static function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        $path = '/' . ltrim($path, '/');
        $path = preg_replace('#/+#', '/', $path);
        return rtrim((string)$path, '/') ?: '/';
    }

    private static function tableExists(string $table): bool
    {
        try {
            return DB::fetchOne(
                'SELECT 1 AS present FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1',
                [$table]
            ) !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function routesIndex(View $view, Container $c): void
    {
        $routes = $c->get('router')->listRoutes();
        RouteRuntimeAuthority::seed($routes);
        $diagnostics = RouteViewBridgeService::enrichRouteDiagnostics(RouteRuntimeAuthority::diagnostics());
        $view->render('Base::admin/routes.php', [
            'pageTitle' => (string)t('admin.routes.page_title'),
            'routes' => $routes,
            'routeDiagnostics' => $diagnostics,
        ]);
    }

    private static function streamDownload(string $absolutePath, string $downloadName, string $allowedBaseDir): void
    {
        $real = realpath($absolutePath);
        $allowed = realpath($allowedBaseDir);

        if ($real === false || $allowed === false || !str_starts_with($real, $allowed . DIRECTORY_SEPARATOR) || !is_file($real)) {
            throw new \RuntimeException('Download file not found or outside allowed directory');
        }

        if (!is_readable($real)) {
            throw new \RuntimeException('Download file is not readable');
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Transfer-Encoding: binary');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
        header('Content-Length: ' . (string)filesize($real));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        $fp = fopen($real, 'rb');
        if ($fp === false) {
            throw new \RuntimeException('Unable to open export file for download');
        }
        fpassthru($fp);
        fclose($fp);
        exit;
    }

    public static function exportAudit(View $view): void
    {
        $type = (string)($_GET['type'] ?? '');
        $status = (string)($_GET['status'] ?? '');

        $query = 'SELECT * FROM core_export_runs WHERE 1=1';
        $params = [];

        if ($type !== '' && in_array(strtolower($type), ['csv', 'pdf'], true)) {
            $query .= ' AND LOWER(export_type) = ?';
            $params[] = strtolower($type);
        }

        if ($status !== '' && in_array(strtolower($status), ['completed', 'warning', 'error'], true)) {
            $query .= ' AND status = ?';
            $params[] = strtolower($status);
        }

        $query .= ' ORDER BY created_at DESC LIMIT 200';

        $exports = DB::select($query, ...$params) ?: [];

        $summary = [
            'total' => 0,
            'success_count' => 0,
            'warning_count' => 0,
            'error_count' => 0,
            'csv_count' => 0,
            'pdf_count' => 0,
        ];

        foreach ($exports as $row) {
            $summary['total']++;
            $st = strtolower(trim((string)($row['status'] ?? '')));
            if ($st === 'completed') {
                $summary['success_count']++;
            } elseif ($st === 'warning') {
                $summary['warning_count']++;
            } elseif ($st === 'error') {
                $summary['error_count']++;
            }

            $et = strtolower(trim((string)($row['export_type'] ?? '')));
            if ($et === 'csv') {
                $summary['csv_count']++;
            } elseif ($et === 'pdf') {
                $summary['pdf_count']++;
            }
        }

        $view->render('AdminTools::admin/export_audit.php', [
            'pageTitle' => 'Export Audit Log',
            'exports' => $exports,
            'summary' => $summary,
            'type' => $type,
            'status' => $status,
        ]);
    }

    public static function appManagement(View $view): void
    {
        try {
            $app_key = trim((string)($_GET['app'] ?? 'manufacturing'));
            if (!in_array($app_key, ['manufacturing', 'sbaio'], true)) {
                $app_key = 'manufacturing';
            }

            $service = new \App\Services\SuiteExportService();
            $history = (new \App\Services\ExportHistoryService())->recentRuns($app_key, null, 20);

            $view->render('AdminTools::admin/app_management.php', [
                'pageTitle' => 'App Management',
                'app_data' => $service->suiteCard($app_key),
                'modules_data' => $service->moduleCards($app_key),
                'history_data' => $history,
                'flash_ok' => (string)($_SESSION['app_mgmt_ok'] ?? ''),
                'flash_err' => (string)($_SESSION['app_mgmt_err'] ?? ''),
                'csrf' => Auth::csrfToken(),
            ]);
            unset($_SESSION['app_mgmt_ok'], $_SESSION['app_mgmt_err']);
        } catch (\Throwable $e) {
            error_log('appManagement error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            throw $e;
        }
    }

    public static function resilienceMap(View $view): void
    {
        $servicePath = APP_ROOT . '/plugins/AdminTools/Services/ResilienceActivityMonitorService.php';
        if (!class_exists(ResilienceActivityMonitorService::class) && is_file($servicePath)) {
            require_once $servicePath;
        }
        $view->render('AdminTools::admin/resilience_map.php', [
            'pageTitle' => t('admin.resilience_map.title'),
            'monitor' => (new ResilienceActivityMonitorService())->snapshot(),
        ]);
    }

    public static function platformAdminLinks(View $view): void
    {
        $cardPath = APP_ROOT . '/plugins/AdminTools/SectionCards/PlatformAdminLinksCard.php';
        if (!class_exists(\Plugins\AdminTools\SectionCards\PlatformAdminLinksCard::class)) {
            require_once $cardPath;
        }
        $view->render('AdminTools::admin/platform_admin_links.php', [
            'pageTitle' => t('admin.platform_links.title'),
            'linkGroups' => \Plugins\AdminTools\SectionCards\PlatformAdminLinksCard::getGroups(),
        ]);
    }
}
