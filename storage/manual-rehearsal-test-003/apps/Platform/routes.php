<?php
declare(strict_types=1);

use Apps\Platform\Services\BaseSchemaBuilderService;
use Apps\Platform\Services\WidgetBuilderService;
use Apps\Platform\Services\DesignStudioService;
use Apps\Studio\Services\GuiStudioService;
use Apps\Studio\Services\AppStudioRegistryService;
use Apps\Studio\Services\StudioViewIntrospectionService;
use Apps\Studio\Services\StudioGovernanceService;
use Apps\Studio\Services\StudioRuntimeBindingService;
use Apps\Studio\Services\StudioDataContractService;
use Apps\Studio\Services\StudioDependencyGraphService;
use Apps\Studio\ActionHandlers\OrderTransitionHandler;
use Apps\Studio\Adapters\KpiAdapter;
use Apps\Studio\Adapters\TableAdapter;
use Apps\Studio\DataProviders\OrdersProvider;
use Apps\Studio\Repositories\StudioSchemaGovernanceService;
use Apps\Studio\Authorization\StudioAuthorizationService;
use Apps\Studio\Services\StudioNotificationService;
use Plugins\Base\Services\AdminToolsAccessService;
use Plugins\Base\Controllers\DashboardController;
use App\Core\AccessGuard;

// Platform app owns governance route registration while reusing existing services/controllers.
require_once __DIR__ . '/bootstrap.php';
require_once APP_ROOT . '/app/Core/AccessGuard.php';
require_once APP_ROOT . '/plugins/Base/Controllers/DashboardController.php';
require_once __DIR__ . '/Services/BaseSchemaBuilderService.php';
require_once __DIR__ . '/Services/DemoDataSeedService.php';
require_once __DIR__ . '/Services/WidgetBuilderDatasetRegistryService.php';
require_once __DIR__ . '/Services/WidgetBuilderService.php';
require_once __DIR__ . '/Services/DesignStudioService.php';

$platformStudioSystemAppEnabled = static function (): bool {
    try {
        $row = \App\Core\DB::fetchOne('SELECT status FROM core_apps WHERE app_key=? LIMIT 1', ['studio']);
        return is_array($row) && (string)($row['status'] ?? '') === 'enabled';
    } catch (\Throwable $e) {
        return false;
    }
};

$platformStudioRuntimeEnabled = $platformStudioSystemAppEnabled();
if ($platformStudioRuntimeEnabled) {
    foreach ([
        '/apps/Studio/Services/GuiStudioService.php',
        '/apps/Studio/Services/AppStudioRegistryService.php',
        '/apps/Studio/Services/StudioViewIntrospectionService.php',
        '/apps/Studio/Services/StudioRuntimeBindingService.php',
        '/apps/Studio/Services/StudioDataContractService.php',
        '/apps/Studio/Services/StudioDependencyGraphService.php',
        '/apps/Studio/Adapters/StudioViewAdapter.php',
        '/apps/Studio/DataProviders/StudioDataProvider.php',
        '/apps/Studio/DataProviders/OrdersProvider.php',
        '/apps/Studio/Adapters/TableAdapter.php',
        '/apps/Studio/Adapters/KpiAdapter.php',
        '/apps/Studio/ActionHandlers/OrderTransitionHandler.php',
        '/apps/Studio/Repositories/StudioSchemaGovernanceService.php',
        '/apps/Studio/Authorization/StudioAuthorizationService.php',
        '/apps/Studio/Services/StudioNotificationService.php',
    ] as $studioRuntimeFile) {
        $studioRuntimePath = APP_ROOT . $studioRuntimeFile;
        if (is_file($studioRuntimePath)) {
            require_once $studioRuntimePath;
        }
    }
}

$router->get('/apps/platform', function () {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        \App\Core\Auth::rememberIntendedUrl('/apps/platform');
        header('Location: /login', true, 302);
        exit;
    }

    header('Location: /ops/dashboard', true, 302);
    exit;
});

// ERP App Studio first-apply generated modules. The route table is rebuilt from
// manifests created only under apps/Generated/*/*; no Core route loader changes.
if ($platformStudioRuntimeEnabled && class_exists(GuiStudioService::class)) {
foreach (GuiStudioService::generatedModuleDefinitions(false) as $generatedModule) {
    if (!is_array($generatedModule)) {
        continue;
    }

    $routePath = trim((string)($generatedModule['route_path'] ?? ''));
    $namespace = trim((string)($generatedModule['namespace'] ?? ''));
    $viewsPath = trim((string)($generatedModule['views_path'] ?? ''));
    $viewFile = trim((string)($generatedModule['view_file'] ?? 'index.php'));
    if ($routePath === '' || $namespace === '' || $viewsPath === '' || !is_dir($viewsPath)) {
        continue;
    }

    $view->addNamespace($namespace, $viewsPath);
    $router->get($routePath, function () use ($view, $generatedModule, $namespace, $viewFile, $routePath) {
        \App\Core\Auth::bootSession();
        if (!\App\Core\Auth::isLoggedIn()) {
            \App\Core\Auth::rememberIntendedUrl($routePath);
            header('Location: /login', true, 302);
            exit;
        }
        AdminToolsAccessService::requireAdminToolsAccess();

        $runtimeData = GuiStudioService::generatedModuleRuntimeData($generatedModule, is_array($_GET) ? $_GET : []);
        $view->render($namespace . '::' . $viewFile, [
            'pageTitle' => (string)($generatedModule['display_name'] ?? t('ops.gui_studio.page_title')),
            'generatedModule' => $runtimeData['module'],
            'generatedFields' => $runtimeData['fields'],
            'generatedRows' => $runtimeData['rows'],
            'generatedEditRow' => $runtimeData['edit_row'],
            'generatedSearch' => $runtimeData['search'],
            'generatedFilters' => $runtimeData['filters'],
            'generatedSort' => $runtimeData['sort'],
            'generatedFlash' => $runtimeData['flash'],
            'csrf' => \App\Core\Auth::csrfToken(),
        ]);
        return null;
    });

    $router->post($routePath, function () use ($generatedModule, $routePath) {
        \App\Core\Auth::bootSession();
        if (!\App\Core\Auth::isLoggedIn()) {
            \App\Core\Auth::rememberIntendedUrl($routePath);
            header('Location: /login', true, 302);
            exit;
        }
        AdminToolsAccessService::requireAdminToolsAccess();
        \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $routePath);

        $result = GuiStudioService::handleGeneratedModuleSubmission($generatedModule, is_array($_POST) ? $_POST : []);
        $_SESSION['generated_module_flash'] = [
            'route_path' => $routePath,
            'status' => !empty($result['ok']) ? 'saved' : 'failed',
            'message' => (string)($result['message'] ?? ''),
        ];
        $target = $routePath;
        if (empty($result['ok']) && isset($_POST['row_id']) && trim((string)$_POST['row_id']) !== '') {
            $target .= '?edit=' . rawurlencode(trim((string)$_POST['row_id']));
        }
        header('Location: ' . $target, true, 302);
        return null;
    });

    $runtimePath = '/apps/runtime/' . (string)$generatedModule['app_key'] . '/' . (string)$generatedModule['module_key'];

    $router->get($runtimePath, function () use ($view, $generatedModule, $runtimePath) {
        \App\Core\Auth::bootSession();
        if (!\App\Core\Auth::isLoggedIn()) {
            \App\Core\Auth::rememberIntendedUrl($runtimePath);
            header('Location: /login', true, 302);
            exit;
        }

        $user = is_array(\App\Core\Auth::user() ?? null) ? \App\Core\Auth::user() : [];
        $runtimeRole = GuiStudioService::resolveGeneratedRuntimeRole($user);
        $runtimeCanEdit = GuiStudioService::generatedRuntimeCanEdit($runtimeRole);

        $runtimeData = GuiStudioService::generatedModuleRuntimeData($generatedModule, is_array($_GET) ? $_GET : []);
        $runtimeContract = GuiStudioService::generatedRuntimeContract($generatedModule, $runtimeData);

        $runtimeFlash = [];
        if (isset($_SESSION['generated_runtime_flash']) && is_array($_SESSION['generated_runtime_flash'])) {
            $sessionFlash = $_SESSION['generated_runtime_flash'];
            if ((string)($sessionFlash['runtime_path'] ?? '') === $runtimePath) {
                $runtimeFlash = [
                    'status' => (string)($sessionFlash['status'] ?? ''),
                    'message' => (string)($sessionFlash['message'] ?? ''),
                ];
                unset($_SESSION['generated_runtime_flash']);
            }
        }

        $view->render('Base::admin/generated_module_runtime.php', [
            'pageTitle' => (string)($generatedModule['display_name'] ?? 'Runtime'),
            'runtimeModule' => [
                'app_key' => (string)($generatedModule['app_key'] ?? ''),
                'module_key' => (string)($generatedModule['module_key'] ?? ''),
                'display_name' => (string)($generatedModule['display_name'] ?? ''),
                'runtime_path' => $runtimePath,
            ],
            'runtimeFields' => $runtimeData['fields'] ?? [],
            'runtimeRows' => $runtimeData['rows'] ?? [],
            'runtimeRole' => $runtimeRole,
            'runtimeCanEdit' => $runtimeCanEdit,
            'runtimeContract' => $runtimeContract,
            'runtimeFlash' => $runtimeFlash,
            'csrf' => \App\Core\Auth::csrfToken(),
        ]);

        return null;
    });

    $router->post($runtimePath, function () use ($generatedModule, $runtimePath) {
        \App\Core\Auth::bootSession();
        if (!\App\Core\Auth::isLoggedIn()) {
            \App\Core\Auth::rememberIntendedUrl($runtimePath);
            header('Location: /login', true, 302);
            exit;
        }

        $user = is_array(\App\Core\Auth::user() ?? null) ? \App\Core\Auth::user() : [];
        $runtimeRole = GuiStudioService::resolveGeneratedRuntimeRole($user);
        $runtimeCanEdit = GuiStudioService::generatedRuntimeCanEdit($runtimeRole);
        $runtimeAction = strtolower(trim((string)($_POST['runtime_action'] ?? '')));

        \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $runtimePath);
        if ($runtimeAction !== 'workflow_transition' && !$runtimeCanEdit) {
            $_SESSION['generated_runtime_flash'] = [
                'runtime_path' => $runtimePath,
                'status' => 'failed',
                'message' => 'runtime_read_only',
            ];
            header('Location: ' . $runtimePath, true, 302);
            return null;
        }

        $result = GuiStudioService::handleGeneratedModuleSubmission($generatedModule, is_array($_POST) ? $_POST : [], [
            'runtime_role' => $runtimeRole,
            'runtime_actor' => (string)($user['email'] ?? $user['username'] ?? $user['name'] ?? $user['id'] ?? 'system'),
        ]);
        $_SESSION['generated_runtime_flash'] = [
            'runtime_path' => $runtimePath,
            'status' => !empty($result['ok']) ? 'saved' : 'failed',
            'message' => (string)($result['message'] ?? ''),
        ];

        header('Location: ' . $runtimePath, true, 302);
        return null;
    });
}

// UX Bridge: Studio registry route reflection (safe view rendering only).
$studioRoutePaths = [];
foreach (GuiStudioService::studioRegistryApps() as $studioApp) {
    if (!is_array($studioApp)) {
        continue;
    }

    $routePath = trim((string)($studioApp['route_path'] ?? ''));
    $appKey = trim((string)($studioApp['app_key'] ?? ''));
    if ($routePath === '' || $appKey === '') {
        continue;
    }
    if (!str_starts_with($routePath, '/apps/')) {
        continue;
    }
    if (!preg_match('#^/apps/(studio|pkg)-[a-z0-9\-]+$#', $routePath)) {
        continue;
    }
    if (isset($studioRoutePaths[$routePath])) {
        continue;
    }
    $studioRoutePaths[$routePath] = true;

    $router->get($routePath, function () use ($view, $appKey, $routePath) {
        \App\Core\Auth::bootSession();
        if (!\App\Core\Auth::isLoggedIn()) {
            \App\Core\Auth::rememberIntendedUrl($routePath);
            header('Location: /login', true, 302);
            exit;
        }
        AdminToolsAccessService::requireAdminToolsAccess();

        $resolved = null;
        foreach (GuiStudioService::studioRegistryApps() as $app) {
            if (is_array($app) && (string)($app['app_key'] ?? '') === $appKey) {
                $resolved = $app;
                break;
            }
        }

        if (!is_array($resolved)) {
            http_response_code(404);
            $view->render('Base::admin/studio_app_runtime.php', [
                'pageTitle' => (string)t('common.apps_manager'),
                'studioApp' => [],
                'runtimeError' => 'error.not_found',
            ]);
            return null;
        }

        if (!GuiStudioService::isStudioAppEnabled($appKey)) {
            http_response_code(404);
            $view->render('Base::admin/studio_app_runtime.php', [
                'pageTitle' => (string)t('common.apps_manager'),
                'studioApp' => $resolved,
                'runtimeError' => 'error.disabled',
                'runtimeHtml' => '',
            ]);
            return null;
        }

        $runtimeHtml = StudioRuntimeBindingService::renderStudioApp($resolved, [
            'app_key' => $appKey,
            'user' => is_array(\App\Core\Auth::user() ?? null) ? \App\Core\Auth::user() : [],
            'mode' => 'runtime',
            'locale' => (string)($_SESSION['locale'] ?? 'en'),
            'action_url' => '/apps/' . $appKey . '/action',
            'csrf' => \App\Core\Auth::csrfToken(),
        ]);

        $view->render('Base::admin/studio_app_runtime.php', [
            'pageTitle' => (string)t('common.apps_manager'),
            'studioApp' => $resolved,
            'runtimeHtml' => $runtimeHtml,
        ]);
        return null;
    });

    $router->post('/apps/' . $appKey . '/action', function () use ($appKey, $routePath) {
        \App\Core\Auth::bootSession();
        header('Content-Type: application/json; charset=UTF-8');

        if (!\App\Core\Auth::isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'code' => 'auth_required', 'message' => 'auth_required']);
            return null;
        }
        AdminToolsAccessService::requireAdminToolsAccess();

        $csrf = trim((string)($_POST['csrf'] ?? ''));
        $sessionCsrf = (string)($_SESSION['csrf'] ?? '');
        if ($csrf === '' || $sessionCsrf === '' || !hash_equals($sessionCsrf, $csrf)) {
            http_response_code(419);
            echo json_encode(['ok' => false, 'code' => 'csrf_invalid', 'message' => 'csrf_invalid']);
            return null;
        }

        $resolved = null;
        foreach (GuiStudioService::studioRegistryApps() as $app) {
            if (is_array($app) && (string)($app['app_key'] ?? '') === $appKey) {
                $resolved = $app;
                break;
            }
        }

        if (!is_array($resolved) || !GuiStudioService::isStudioAppEnabled($appKey)) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'code' => 'app_unavailable', 'message' => 'app_unavailable']);
            return null;
        }

        $actionKey = trim((string)($_POST['action_key'] ?? ''));
        $payload = $_POST;
        unset($payload['csrf']);

        $result = StudioRuntimeBindingService::handleStudioAction($resolved, $actionKey, is_array($payload) ? $payload : [], [
            'app_key' => $appKey,
            'route_path' => $routePath,
            'user' => is_array(\App\Core\Auth::user() ?? null) ? \App\Core\Auth::user() : [],
            'locale' => (string)($_SESSION['locale'] ?? 'en'),
            'mode' => 'action',
        ]);

        http_response_code(!empty($result['ok']) ? 200 : 400);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        return null;
    });
}

$router->get('/ops/audit-log', function() use ($view) {
    acl_require('ops.audit_explorer.view', '/ops/audit-log');

    $user = \App\Core\Auth::user();
    $context = platform_user_context_contract()->resolveUserContext($user);

    $filters = [
        'entity_type' => trim((string)($_GET['entity_type'] ?? '')),
        'entity_id' => (int)($_GET['entity_id'] ?? 0),
        'app_key' => trim((string)($_GET['app_key'] ?? '')),
        'module_key' => trim((string)($_GET['module_key'] ?? '')),
        'event_type' => trim((string)($_GET['event_type'] ?? '')),
        'action_name' => trim((string)($_GET['action_name'] ?? '')),
        'actor' => trim((string)($_GET['actor'] ?? '')),
        'from_date' => trim((string)($_GET['from_date'] ?? '')),
        'to_date' => trim((string)($_GET['to_date'] ?? '')),
    ];

    $scope = [
        'authority_role' => (string)($context['authority_role'] ?? 'app_user'),
        'assigned_apps' => (array)($context['assigned_apps'] ?? []),
        'module_visibility' => (array)($context['module_visibility'] ?? []),
    ];

    $rows = \App\Core\AuditLogService::explorer($filters, $scope, 350);

    $view->render('Base::ops/audit_log.php', [
        'pageTitle' => 'Audit Explorer',
        'rows' => $rows,
        'filters' => $filters,
        'scope' => $scope,
        'event_types' => [
            \App\Core\AuditLogService::EVENT_LIFECYCLE,
            \App\Core\AuditLogService::EVENT_WORKFLOW,
            \App\Core\AuditLogService::EVENT_ASSIGNMENT,
            \App\Core\AuditLogService::EVENT_SCOPE,
            \App\Core\AuditLogService::EVENT_SYSTEM,
        ],
        'actions' => [
            \App\Core\AuditLogService::ACTION_CREATED,
            \App\Core\AuditLogService::ACTION_UPDATED,
            \App\Core\AuditLogService::ACTION_DELETED,
            \App\Core\AuditLogService::ACTION_SUBMITTED,
            \App\Core\AuditLogService::ACTION_APPROVED,
            \App\Core\AuditLogService::ACTION_REJECTED,
            \App\Core\AuditLogService::ACTION_REOPENED,
            \App\Core\AuditLogService::ACTION_HELD,
            \App\Core\AuditLogService::ACTION_RESUMED,
            \App\Core\AuditLogService::ACTION_FINALIZED,
            \App\Core\AuditLogService::ACTION_CANCELLED,
            \App\Core\AuditLogService::ACTION_ASSIGNED,
            \App\Core\AuditLogService::ACTION_SCOPE_CHANGED,
            \App\Core\AuditLogService::ACTION_RECALCULATED,
            \App\Core\AuditLogService::ACTION_DEMAND_REGENERATED,
            \App\Core\AuditLogService::ACTION_HANDOFF_CREATED,
            \App\Core\AuditLogService::ACTION_HANDOFF_ACCEPTED,
            \App\Core\AuditLogService::ACTION_HANDOFF_BLOCKED,
            \App\Core\AuditLogService::ACTION_HANDOFF_COMPLETED,
            \App\Core\AuditLogService::ACTION_PRODUCTION_OUTPUT_SUBMITTED,
            \App\Core\AuditLogService::ACTION_STOCK_ADJUSTMENT_POSTED,
        ],
    ]);
    return null;
});

$router->get('/ops/dashboard', function () {
    \Plugins\Base\Controllers\RoleDashboardsController::redirectToRoleDashboard();
});

$router->get('/ops/my-work', function () {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        \App\Core\Auth::rememberIntendedUrl('/ops/my-work');
        header('Location: /login', true, 302);
        exit;
    }

    $user = is_array(\App\Core\Auth::user() ?? null) ? \App\Core\Auth::user() : [];
    $userId = (int)($user['id'] ?? $user['user_id'] ?? 0);
    $appKey = StudioSchemaGovernanceService::sanitizeAppKey((string)($_GET['app_key'] ?? 'studio_sales'));
    StudioSchemaGovernanceService::ensureSchema($appKey);
    StudioNotificationService::ensureTable();

    $providerContext = [
        'app_key' => $appKey,
        'user' => $user,
        'locale' => (string)($_SESSION['locale'] ?? 'en'),
        'mode' => 'my_work',
    ];

    $provider = new OrdersProvider();
    $payload = $provider->fetch($providerContext);
    $rows = array_values(array_filter((array)($payload['rows'] ?? []), 'is_array'));

    $notifications = StudioNotificationService::getUserNotifications($userId, 20);
    $unreadCount = StudioNotificationService::getUnreadCount($userId);

    $newTasks = [];
    $recentChanges = [];
    foreach ($notifications as $notification) {
        if (!is_array($notification)) {
            continue;
        }
        $isRead = (int)($notification['is_read'] ?? 0) === 1;
        if (!$isRead) {
            $newTasks[] = $notification;
        }
        $recentChanges[] = $notification;
    }

    $newTasks = array_slice($newTasks, 0, 10);
    $recentChanges = array_slice($recentChanges, 0, 10);

    $today = gmdate('Y-m-d');
    $approvalQueue = [];
    $processingQueue = [];
    $inProgressQueue = [];
    $completedToday = [];
    $myTasks = [];
    $unassignedTasks = [];
    $overdueTasks = [];

    foreach ($rows as $idx => $row) {
        $state = strtolower(trim((string)($row['state'] ?? 'draft')));
        $nextActions = array_values(array_filter((array)($row['next_actions'] ?? []), 'is_string'));
        $assignedTo = (int)($row['assigned_to'] ?? 0);
        $isOverdue = !empty($row['is_overdue']);
        $orderNo = trim((string)($row['order_no'] ?? ''));
        $entityId = (int)($row['id'] ?? 0);

        $rows[$idx]['is_overdue_text'] = $isOverdue ? (string)t('ops.my_work.sla.overdue') : (string)t('ops.my_work.sla.on_time');

        if ($state === 'draft' && in_array('approved', $nextActions, true)) {
            $approvalQueue[] = $rows[$idx];
        }

        if ($state === 'approved' && in_array('processing', $nextActions, true)) {
            $processingQueue[] = $rows[$idx];
        }

        if ($state === 'processing') {
            $inProgressQueue[] = $rows[$idx];
        }

        if ($state === 'completed') {
            $updatedAt = trim((string)($row['updated_at'] ?? $row['created_at'] ?? ''));
            if ($updatedAt !== '' && str_starts_with($updatedAt, $today)) {
                $completedToday[] = $rows[$idx];
            }
        }

        if ($assignedTo > 0 && $assignedTo === $userId) {
            $myTasks[] = $rows[$idx];
        }

        if ($assignedTo <= 0) {
            $unassignedTasks[] = $rows[$idx];
        }

        if ($isOverdue) {
            $overdueTasks[] = $rows[$idx];
            if ($assignedTo > 0 && $state !== 'completed' && $orderNo !== '') {
                $message = 'Order ' . $orderNo . ' is overdue';
                $created = StudioNotificationService::notifyOncePerDay($assignedTo, $message, 'orders', $entityId, 'sla_overdue');
                if ($created) {
                    StudioSchemaGovernanceService::logAudit($appKey, 'sla_overdue', 'orders', [
                        'id' => $entityId,
                        'entity_id' => $entityId,
                        'order_no' => $orderNo,
                        'assigned_to' => $assignedTo,
                    ], [
                        'user_id' => $userId,
                        'user' => $user,
                    ]);
                }
            }
        }
    }

    $pendingActionsQueue = [];
    foreach ([$approvalQueue, $processingQueue] as $segment) {
        foreach ($segment as $row) {
            $key = trim((string)($row['order_no'] ?? ''));
            if ($key === '' || isset($pendingActionsQueue[$key])) {
                continue;
            }
            $pendingActionsQueue[$key] = $row;
        }
    }
    $pendingRows = array_values($pendingActionsQueue);

    $kpiAdapter = new KpiAdapter();
    $tableAdapter = new TableAdapter();

    $kpiHtml = $kpiAdapter->render([
        'fields' => [
            ['key' => 'pending_total', 'label' => (string)t('ops.my_work.kpi.pending_total')],
            ['key' => 'my_tasks_total', 'label' => (string)t('ops.my_work.kpi.my_tasks_total')],
            ['key' => 'unassigned_total', 'label' => (string)t('ops.my_work.kpi.unassigned_total')],
            ['key' => 'overdue_total', 'label' => (string)t('ops.my_work.kpi.overdue_total')],
            ['key' => 'in_progress_total', 'label' => (string)t('ops.my_work.kpi.in_progress_total')],
            ['key' => 'completed_today_total', 'label' => (string)t('ops.my_work.kpi.completed_today_total')],
            ['key' => 'unread_notifications_total', 'label' => (string)t('ops.my_work.kpi.unread_notifications_total')],
        ],
    ], [
        'data' => [
            'pending_total' => (string)count($pendingRows),
            'my_tasks_total' => (string)count($myTasks),
            'unassigned_total' => (string)count($unassignedTasks),
            'overdue_total' => (string)count($overdueTasks),
            'in_progress_total' => (string)count($inProgressQueue),
            'completed_today_total' => (string)count($completedToday),
            'unread_notifications_total' => (string)$unreadCount,
        ],
        'msg_empty' => (string)t('ops.my_work.empty'),
    ]);

    $tableFields = [
        ['key' => 'order_no', 'label' => (string)t('ops.my_work.table.order_no')],
        ['key' => 'customer', 'label' => (string)t('ops.my_work.table.customer')],
        ['key' => 'priority', 'label' => (string)t('ops.my_work.table.priority')],
        ['key' => 'due_at', 'label' => (string)t('ops.my_work.table.due_at')],
        ['key' => 'is_overdue_text', 'label' => (string)t('ops.my_work.table.sla')],
        ['key' => 'state', 'label' => (string)t('ops.my_work.table.state')],
        ['key' => 'next_actions_text', 'label' => (string)t('ops.my_work.table.next_actions')],
    ];

    $pendingHtml = $tableAdapter->render(['fields' => $tableFields], [
        'data' => ['rows' => $pendingRows],
        'msg_no_rows' => (string)t('ops.my_work.no_rows'),
        'msg_empty' => (string)t('ops.my_work.empty'),
    ]);

    $inProgressHtml = $tableAdapter->render(['fields' => $tableFields], [
        'data' => ['rows' => $inProgressQueue],
        'msg_no_rows' => (string)t('ops.my_work.no_rows'),
        'msg_empty' => (string)t('ops.my_work.empty'),
    ]);

    $completedHtml = $tableAdapter->render(['fields' => $tableFields], [
        'data' => ['rows' => $completedToday],
        'msg_no_rows' => (string)t('ops.my_work.no_rows'),
        'msg_empty' => (string)t('ops.my_work.empty'),
    ]);

    $role = StudioAuthorizationService::resolveRole(['user' => $user]);

    $flashOk = (string)($_SESSION['ops_my_work_ok'] ?? '');
    $flashErr = (string)($_SESSION['ops_my_work_err'] ?? '');
    unset($_SESSION['ops_my_work_ok'], $_SESSION['ops_my_work_err']);

    $vars = [
        'pageTitle' => (string)t('ops.my_work.title'),
        'csrf' => \App\Core\Auth::csrfToken(),
        'flashOk' => $flashOk,
        'flashErr' => $flashErr,
        'appKey' => $appKey,
        'role' => $role,
        'currentUserId' => $userId,
        'notifications' => $notifications,
        'unreadCount' => $unreadCount,
        'newTasks' => $newTasks,
        'recentChanges' => $recentChanges,
        'kpiHtml' => $kpiHtml,
        'pendingHtml' => $pendingHtml,
        'inProgressHtml' => $inProgressHtml,
        'completedHtml' => $completedHtml,
        'approvalQueue' => $approvalQueue,
        'processingQueue' => $processingQueue,
        'inProgressQueue' => $inProgressQueue,
        'myTasks' => $myTasks,
        'unassignedTasks' => $unassignedTasks,
        'overdueTasks' => $overdueTasks,
        'actionLabels' => [
            'approved' => (string)t('ops.my_work.action.approve'),
            'processing' => (string)t('ops.my_work.action.process'),
            'completed' => (string)t('ops.my_work.action.complete'),
            'cancelled' => (string)t('ops.my_work.action.cancel'),
        ],
    ];

    extract($vars, EXTR_SKIP);
    require APP_ROOT . '/apps/Platform/Views/my_work.php';
    return null;
});

$router->post('/ops/my-work/transition', function () {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        \App\Core\Auth::rememberIntendedUrl('/ops/my-work');
        header('Location: /login', true, 302);
        exit;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/ops/my-work');

    $user = is_array(\App\Core\Auth::user() ?? null) ? \App\Core\Auth::user() : [];
    $appKey = StudioSchemaGovernanceService::sanitizeAppKey((string)($_POST['app_key'] ?? 'studio_sales'));
    $orderNo = trim((string)($_POST['order_no'] ?? ''));
    $toState = strtolower(trim((string)($_POST['to_state'] ?? '')));

    $handler = new OrderTransitionHandler();
    $result = $handler->handle([
        'order_no' => $orderNo,
        'to_state' => $toState,
    ], [
        'app_key' => $appKey,
        'user' => $user,
        'mode' => 'my_work',
        'locale' => (string)($_SESSION['locale'] ?? 'en'),
    ]);

    if (!empty($result['ok'])) {
        $_SESSION['ops_my_work_ok'] = 'ops.my_work.flash.transition_ok';
    } else {
        $_SESSION['ops_my_work_err'] = (string)($result['code'] ?? 'transition_blocked');
    }

    header('Location: /ops/my-work?app_key=' . rawurlencode($appKey), true, 302);
    exit;
});

$router->post('/ops/my-work/task-action', function () {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        \App\Core\Auth::rememberIntendedUrl('/ops/my-work');
        header('Location: /login', true, 302);
        exit;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/ops/my-work');

    $user = is_array(\App\Core\Auth::user() ?? null) ? \App\Core\Auth::user() : [];
    $appKey = StudioSchemaGovernanceService::sanitizeAppKey((string)($_POST['app_key'] ?? 'studio_sales'));

    $handler = new OrderTransitionHandler();
    $result = $handler->handle([
        'action' => trim((string)($_POST['action'] ?? '')),
        'order_no' => trim((string)($_POST['order_no'] ?? '')),
        'assigned_to' => (int)($_POST['assigned_to'] ?? 0),
    ], [
        'app_key' => $appKey,
        'user' => $user,
        'mode' => 'my_work',
        'locale' => (string)($_SESSION['locale'] ?? 'en'),
    ]);

    if (!empty($result['ok'])) {
        $_SESSION['ops_my_work_ok'] = 'ops.my_work.flash.ownership_ok';
    } else {
        $_SESSION['ops_my_work_err'] = (string)($result['code'] ?? 'ownership_update_blocked');
    }

    header('Location: /ops/my-work?app_key=' . rawurlencode($appKey), true, 302);
    exit;
});

$router->post('/ops/my-work/notification/read', function () {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        \App\Core\Auth::rememberIntendedUrl('/ops/my-work');
        header('Location: /login', true, 302);
        exit;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/ops/my-work');

    $notificationId = (int)($_POST['notification_id'] ?? 0);
    $appKey = StudioSchemaGovernanceService::sanitizeAppKey((string)($_POST['app_key'] ?? 'studio_sales'));
    $user = is_array(\App\Core\Auth::user() ?? null) ? \App\Core\Auth::user() : [];
    $userId = (int)($user['id'] ?? $user['user_id'] ?? 0);

    if ($notificationId > 0 && StudioNotificationService::markRead($notificationId, $userId)) {
        $_SESSION['ops_my_work_ok'] = 'ops.my_work.flash.notification_read';
    } else {
        $_SESSION['ops_my_work_err'] = 'notification_mark_failed';
    }

    header('Location: /ops/my-work?app_key=' . rawurlencode($appKey), true, 302);
    exit;
});

$router->get('/ops/analytics', function () use ($view) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        \App\Core\Auth::rememberIntendedUrl('/ops/analytics');
        header('Location: /login', true, 302);
        exit;
    }

    $user = is_array(\App\Core\Auth::user() ?? null) ? \App\Core\Auth::user() : [];
    $userId = (int)($user['id'] ?? $user['user_id'] ?? 0);
    $role = (string)($user['role'] ?? 'viewer');

    // Check authorization
    $context = [
        'user_id' => $userId,
        'user' => $user,
        'role' => $role,
        '_studio_authorized' => true,
    ];

    if (!StudioAuthorizationService::can('orders', 'view', $context)) {
        header('HTTP/1.1 403 Forbidden', true, 403);
        exit;
    }

    require_once APP_ROOT . '/apps/Studio/Analytics/AnalyticsProvider.php';
    require_once APP_ROOT . '/apps/Studio/Analytics/AnalyticsService.php';

    $appKey = StudioSchemaGovernanceService::sanitizeAppKey((string)($_GET['app_key'] ?? 'studio_sales'));
    $context['app_key'] = $appKey;

    $provider = new \Apps\Studio\Analytics\AnalyticsProvider();
    $analytics = $provider->fetch($context);
    $repo = new \Apps\Studio\Analytics\AnalyticsRepository($appKey);

    // Fetch manufacturing metrics
    $qcMetrics = $provider->fetchQcMetrics($repo);
    $dispatchMetrics = $provider->fetchDispatchMetrics($repo);
    $assemblyMetrics = $provider->fetchAssemblyMetrics($repo);
    $productionMetrics = $provider->fetchProductionMetrics($repo);

    $service = new \Apps\Studio\Analytics\AnalyticsService($appKey);
    $efficiencyScore = $service->getEfficiencyScore();
    $topPerformers = $service->getTopPerformers(5);
    $alerts = $service->getAlerts();
    $trends = $service->getTrendAnalysis(7);
    $manufacturingIntelligence = $service->getManufacturingIntelligence(7);
    $recommendations = $service->getRecommendations($manufacturingIntelligence);

    $csrf = \App\Core\Auth::generateCsrf();

    $view->render('Platform/analytics', [
        'analytics' => $analytics,
        'efficiency_score' => $efficiencyScore,
        'top_performers' => $topPerformers,
        'alerts' => $alerts,
        'trends' => $trends,
        'manufacturing_intelligence' => $manufacturingIntelligence,
        'recommendations' => $recommendations,
        'qc_metrics' => $qcMetrics,
        'dispatch_metrics' => $dispatchMetrics,
        'assembly_metrics' => $assemblyMetrics,
        'production_metrics' => $productionMetrics,
        'csrf' => $csrf,
        'app_key' => $appKey,
        'user_id' => $userId,
        'role' => $role,
    ]);
});

$router->get('/ops/analytics/drilldown', function () use ($view) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        \App\Core\Auth::rememberIntendedUrl('/ops/analytics/drilldown');
        header('Location: /login', true, 302);
        exit;
    }

    $user = is_array(\App\Core\Auth::user() ?? null) ? \App\Core\Auth::user() : [];
    $userId = (int)($user['id'] ?? $user['user_id'] ?? 0);
    $role = (string)($user['role'] ?? 'viewer');

    // Check authorization
    $context = [
        'user_id' => $userId,
        'user' => $user,
        'role' => $role,
        '_studio_authorized' => true,
    ];

    if (!StudioAuthorizationService::can('orders', 'view', $context)) {
        header('HTTP/1.1 403 Forbidden', true, 403);
        exit;
    }

    require_once APP_ROOT . '/apps/Studio/Analytics/AnalyticsProvider.php';

    $appKey = StudioSchemaGovernanceService::sanitizeAppKey((string)($_GET['app_key'] ?? 'studio_sales'));
    $type = in_array((string)($_GET['type'] ?? 'all'), ['overdue', 'completed', 'processing', 'draft', 'all'], true) ? (string)$_GET['type'] : 'all';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, (int)($_GET['limit'] ?? 50));
    $offset = ($page - 1) * $limit;

    $provider = new \Apps\Studio\Analytics\AnalyticsProvider();
    $drilldown = $provider->fetchDrilldown($type, $limit, $offset, $appKey);

    $csrf = \App\Core\Auth::generateCsrf();

    $view->render('Platform/analytics-drilldown', [
        'drilldown' => $drilldown,
        'csrf' => $csrf,
        'app_key' => $appKey,
        'type' => $type,
        'page' => $page,
        'limit' => $limit,
        'user_id' => $userId,
        'role' => $role,
    ]);
});

$router->get('/ops/analytics/drilldown/qc', function () use ($view) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        \App\Core\Auth::rememberIntendedUrl('/ops/analytics/drilldown/qc');
        header('Location: /login', true, 302);
        exit;
    }

    $user = is_array(\App\Core\Auth::user() ?? null) ? \App\Core\Auth::user() : [];
    $userId = (int)($user['id'] ?? $user['user_id'] ?? 0);
    $role = (string)($user['role'] ?? 'viewer');

    // Check authorization
    $context = [
        'user_id' => $userId,
        'user' => $user,
        'role' => $role,
        '_studio_authorized' => true,
    ];

    if (!StudioAuthorizationService::can('orders', 'view', $context)) {
        header('HTTP/1.1 403 Forbidden', true, 403);
        exit;
    }

    require_once APP_ROOT . '/apps/Studio/Analytics/AnalyticsProvider.php';

    $type = in_array((string)($_GET['type'] ?? 'all'), ['open', 'closed', 'draft', 'submitted', 'approved', 'fail', 'rework', 'all'], true) ? (string)$_GET['type'] : 'all';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, (int)($_GET['limit'] ?? 50));
    $offset = ($page - 1) * $limit;
    $appKey = StudioSchemaGovernanceService::sanitizeAppKey((string)($_GET['app_key'] ?? 'studio_sales'));

    $provider = new \Apps\Studio\Analytics\AnalyticsProvider();
    $drilldown = $provider->fetchQcDrilldown($type, $limit, $offset, $appKey);

    $csrf = \App\Core\Auth::generateCsrf();

    $view->render('Platform/analytics-drilldown-qc', [
        'drilldown' => $drilldown,
        'csrf' => $csrf,
        'type' => $type,
        'page' => $page,
        'limit' => $limit,
        'app_key' => $appKey,
        'user_id' => $userId,
        'role' => $role,
    ]);
});

$router->get('/ops/analytics/qc/drilldown', function () {
    $query = isset($_SERVER['QUERY_STRING']) && is_string($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
    header('Location: /ops/analytics/drilldown/qc' . ($query !== '' ? '?' . $query : ''), true, 302);
    exit;
});

$router->get('/ops/analytics/drilldown/dispatch', function () use ($view) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        \App\Core\Auth::rememberIntendedUrl('/ops/analytics/drilldown/dispatch');
        header('Location: /login', true, 302);
        exit;
    }

    $user = is_array(\App\Core\Auth::user() ?? null) ? \App\Core\Auth::user() : [];
    $userId = (int)($user['id'] ?? $user['user_id'] ?? 0);
    $role = (string)($user['role'] ?? 'viewer');

    // Check authorization
    $context = [
        'user_id' => $userId,
        'user' => $user,
        'role' => $role,
        '_studio_authorized' => true,
    ];

    if (!StudioAuthorizationService::can('orders', 'view', $context)) {
        header('HTTP/1.1 403 Forbidden', true, 403);
        exit;
    }

    require_once APP_ROOT . '/apps/Studio/Analytics/AnalyticsProvider.php';

    $type = in_array((string)($_GET['type'] ?? 'all'), ['pending', 'delayed', 'ready', 'dispatched', 'blocked', 'completed', 'all'], true) ? (string)$_GET['type'] : 'all';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, (int)($_GET['limit'] ?? 50));
    $offset = ($page - 1) * $limit;
    $appKey = StudioSchemaGovernanceService::sanitizeAppKey((string)($_GET['app_key'] ?? 'studio_sales'));

    $provider = new \Apps\Studio\Analytics\AnalyticsProvider();
    $drilldown = $provider->fetchDispatchDrilldown($type, $limit, $offset, $appKey);

    $csrf = \App\Core\Auth::generateCsrf();

    $view->render('Platform/analytics-drilldown-dispatch', [
        'drilldown' => $drilldown,
        'csrf' => $csrf,
        'type' => $type,
        'page' => $page,
        'limit' => $limit,
        'app_key' => $appKey,
        'user_id' => $userId,
        'role' => $role,
    ]);
});

$router->get('/ops/analytics/dispatch/drilldown', function () {
    $query = isset($_SERVER['QUERY_STRING']) && is_string($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
    header('Location: /ops/analytics/drilldown/dispatch' . ($query !== '' ? '?' . $query : ''), true, 302);
    exit;
});

$router->get('/ops/analytics/drilldown/assembly', function () use ($view) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        \App\Core\Auth::rememberIntendedUrl('/ops/analytics/drilldown/assembly');
        header('Location: /login', true, 302);
        exit;
    }

    $user = is_array(\App\Core\Auth::user() ?? null) ? \App\Core\Auth::user() : [];
    $userId = (int)($user['id'] ?? $user['user_id'] ?? 0);
    $role = (string)($user['role'] ?? 'viewer');

    // Check authorization
    $context = [
        'user_id' => $userId,
        'user' => $user,
        'role' => $role,
        '_studio_authorized' => true,
    ];

    if (!StudioAuthorizationService::can('orders', 'view', $context)) {
        header('HTTP/1.1 403 Forbidden', true, 403);
        exit;
    }

    require_once APP_ROOT . '/apps/Studio/Analytics/AnalyticsProvider.php';

    $type = in_array((string)($_GET['type'] ?? 'all'), ['pending', 'high_rejection', 'draft', 'in_progress', 'completed', 'approved', 'blocked', 'all'], true) ? (string)$_GET['type'] : 'all';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, (int)($_GET['limit'] ?? 50));
    $offset = ($page - 1) * $limit;
    $appKey = StudioSchemaGovernanceService::sanitizeAppKey((string)($_GET['app_key'] ?? 'studio_sales'));

    $provider = new \Apps\Studio\Analytics\AnalyticsProvider();
    $drilldown = $provider->fetchAssemblyDrilldown($type, $limit, $offset, $appKey);

    $csrf = \App\Core\Auth::generateCsrf();

    $view->render('Platform/analytics-drilldown-assembly', [
        'drilldown' => $drilldown,
        'csrf' => $csrf,
        'type' => $type,
        'page' => $page,
        'limit' => $limit,
        'app_key' => $appKey,
        'user_id' => $userId,
        'role' => $role,
    ]);
});

$router->get('/ops/analytics/assembly/drilldown', function () {
    $query = isset($_SERVER['QUERY_STRING']) && is_string($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
    header('Location: /ops/analytics/drilldown/assembly' . ($query !== '' ? '?' . $query : ''), true, 302);
    exit;
});

$router->get('/ops/analytics/drilldown/production', function () use ($view) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        \App\Core\Auth::rememberIntendedUrl('/ops/analytics/drilldown/production');
        header('Location: /login', true, 302);
        exit;
    }

    $user = is_array(\App\Core\Auth::user() ?? null) ? \App\Core\Auth::user() : [];
    $userId = (int)($user['id'] ?? $user['user_id'] ?? 0);
    $role = (string)($user['role'] ?? 'viewer');

    // Check authorization
    $context = [
        'user_id' => $userId,
        'user' => $user,
        'role' => $role,
        '_studio_authorized' => true,
    ];

    if (!StudioAuthorizationService::can('orders', 'view', $context)) {
        header('HTTP/1.1 403 Forbidden', true, 403);
        exit;
    }

    require_once APP_ROOT . '/apps/Studio/Analytics/AnalyticsProvider.php';

    $type = in_array((string)($_GET['type'] ?? 'all'), ['day', 'night', 'morning', 'low_utilization', 'high_rejection', 'all'], true) ? (string)$_GET['type'] : 'all';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, (int)($_GET['limit'] ?? 50));
    $offset = ($page - 1) * $limit;
    $appKey = StudioSchemaGovernanceService::sanitizeAppKey((string)($_GET['app_key'] ?? 'studio_sales'));

    $provider = new \Apps\Studio\Analytics\AnalyticsProvider();
    $drilldown = $provider->fetchProductionDrilldown($type, $limit, $offset, $appKey);

    $csrf = \App\Core\Auth::generateCsrf();

    $view->render('Platform/analytics-drilldown-production', [
        'drilldown' => $drilldown,
        'csrf' => $csrf,
        'type' => $type,
        'page' => $page,
        'limit' => $limit,
        'app_key' => $appKey,
        'user_id' => $userId,
        'role' => $role,
    ]);
});

$router->get('/ops/analytics/production/drilldown', function () {
    $query = isset($_SERVER['QUERY_STRING']) && is_string($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
    header('Location: /ops/analytics/drilldown/production' . ($query !== '' ? '?' . $query : ''), true, 302);
    exit;
});
}

$router->get('/ops/gui-studio', function () {
    $query = is_array($_GET) && $_GET !== [] ? ('?' . http_build_query($_GET)) : '';

    $studioAliasEnabled = false;
    try {
        $studioAliasRow = \App\Core\DB::fetchOne('SELECT status FROM core_apps WHERE app_key=? LIMIT 1', ['studio']);
        $studioAliasEnabled = is_array($studioAliasRow) && (string)($studioAliasRow['status'] ?? '') === 'enabled';
    } catch (\Throwable $e) {
        $studioAliasEnabled = false;
    }

    $studioRoutesFile = APP_ROOT . '/apps/Studio/routes.php';
    if (!$studioAliasEnabled || !is_file($studioRoutesFile)) {
        header('Location: /ops/platform-operations' . $query, true, 302);
        exit;
    }

    header('Location: /apps/studio' . $query, true, 302);
    exit;
});

$studioBridgeRoutes = APP_ROOT . '/apps/Studio/Routes/gui_studio_routes.php';
$studioBridgeEnabled = false;
try {
    $studioBridgeRow = \App\Core\DB::fetchOne('SELECT status FROM core_apps WHERE app_key=? LIMIT 1', ['studio']);
    $studioBridgeEnabled = is_array($studioBridgeRow) && (string)($studioBridgeRow['status'] ?? '') === 'enabled';
} catch (\Throwable $e) {
    $studioBridgeEnabled = false;
}
if ($studioBridgeEnabled && is_file($studioBridgeRoutes)) {
    require_once $studioBridgeRoutes;
    if (function_exists('studio_register_gui_studio_routes')) {
        studio_register_gui_studio_routes($router, $view, '/ops/gui-studio');
    }
}

$router->get('/ops/platform-operations', function () use ($view) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        \App\Core\Auth::rememberIntendedUrl('/ops/platform-operations');
        header('Location: /login', true, 302);
        exit;
    }
    
    // Verify user is platform_admin
    $user = \App\Core\Auth::user();
    $ctx = \Plugins\Base\Services\UserDashboardAssignmentService::resolveUserContext($user);
    $authorityRole = (string)($ctx['authority_role'] ?? 'app_user');
    
    if ($authorityRole !== 'platform_admin') {
        http_response_code(403);
        echo 'Access denied.';
        exit;
    }
    
    $studioAppEnabled = false;
    try {
        $studioRow = \App\Core\DB::fetchOne('SELECT status FROM core_apps WHERE app_key=? LIMIT 1', ['studio']);
        $studioAppEnabled = is_array($studioRow) && (string)($studioRow['status'] ?? '') === 'enabled';
    } catch (\Throwable $e) {
        $studioAppEnabled = false;
    }

    $view->render('platform::ops/platform_operations.php', [
        'pageTitle' => t('ops.platform_operations.title'),
        'studioAppEnabled' => $studioAppEnabled,
    ]);
    return null;
});

$router->get('/admin/setup/demo', function () use ($view) {
    \App\Core\Auth::bootSession();
    AdminToolsAccessService::requireAdminToolsAccess();

    $flash = [
        'ok' => (string)($_SESSION['setup_console_ok'] ?? ''),
        'err' => (string)($_SESSION['setup_console_err'] ?? ''),
        'result' => $_SESSION['setup_console_result'] ?? null,
    ];
    unset($_SESSION['setup_console_ok'], $_SESSION['setup_console_err'], $_SESSION['setup_console_result']);

    $view->render('admin/setup/demo.php', [
        'pageTitle' => 'Demo Data Feed',
        'csrf' => \App\Core\Auth::csrfToken(),
        'flash' => $flash,
    ]);
    return null;
});

$router->post('/admin/setup/demo/feed', function () {
    \App\Core\Auth::bootSession();
    AdminToolsAccessService::requireAdminToolsAccess();
    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

    try {
        $actor = (string)(\App\Core\Auth::user()['email'] ?? 'platform-admin');
        $result = \Apps\Platform\Services\DemoDataSeedService::addFeed($_POST, $actor);
        $_SESSION['setup_console_result'] = $result;
        $_SESSION['setup_console_ok'] = 'Demo feed appended with dependency-aware records.';
    } catch (\Throwable $e) {
        $_SESSION['setup_console_err'] = $e->getMessage();
    }

    header('Location: /admin/setup/demo', true, 302);
    exit;
});

$router->post('/admin/setup/demo/reset', function () {
    \App\Core\Auth::bootSession();
    AdminToolsAccessService::requireAdminToolsAccess();
    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

    try {
        $actor = (string)(\App\Core\Auth::user()['email'] ?? 'platform-admin');
        $result = \Apps\Platform\Services\DemoDataSeedService::resetFeed($_POST, $actor);
        $_SESSION['setup_console_result'] = $result;
        $_SESSION['setup_console_ok'] = 'Demo-tagged records were reset for selected stages.';
    } catch (\Throwable $e) {
        $_SESSION['setup_console_err'] = $e->getMessage();
    }

    header('Location: /admin/setup/demo', true, 302);
    exit;
});

$router->get('/admin/system-tools/platform-mode', function () use ($view) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        \App\Core\Auth::rememberIntendedUrl('/admin/system-tools/platform-mode');
        header('Location: /login', true, 302);
        exit;
    }
    $user = \App\Core\Auth::user();
    $ctx = \Plugins\Base\Services\UserDashboardAssignmentService::resolveUserContext($user);
    if (strtolower(trim((string)($ctx['authority_role'] ?? ''))) !== 'platform_admin') {
        http_response_code(403);
        echo 'Access denied.';
        exit;
    }
    $view->render('platform::admin/platform_mode.php', [
        'pageTitle' => t('admin.platform_mode.title'),
        'currentMode' => \App\Services\PlatformModeService::currentMode(),
        'availableModes' => \App\Services\PlatformModeService::availableModes(),
    ]);
    return null;
});

$router->get('/admin/system-tools/upgrade-catalog', function () use ($view) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        \App\Core\Auth::rememberIntendedUrl('/admin/system-tools/upgrade-catalog');
        header('Location: /login', true, 302);
        exit;
    }
    $ctx = \Plugins\Base\Services\UserDashboardAssignmentService::resolveUserContext(\App\Core\Auth::user());
    if (strtolower(trim((string)($ctx['authority_role'] ?? ''))) !== 'platform_admin') {
        http_response_code(403);
        echo 'Access denied.';
        exit;
    }
    $view->render('platform::admin/upgrade_catalog.php', [
        'pageTitle' => t('admin.upgrade_catalog.title'),
        'upgradeCandidates' => \Apps\Platform\Services\AdminUpgradeWorkbenchService::catalog(),
    ]);
    return null;
});

$router->get('/ops/access-control', function () use ($view) {
    \Plugins\Base\Controllers\RoleDashboardsController::renderAssignmentConsole($view);
    return null;
});

$router->get('/ops/access-control/detail', function () use ($view) {
    \Plugins\Base\Controllers\RoleDashboardsController::renderAssignmentDetail($view);
    return null;
});

$router->get('/ops/user-control', function () use ($view) {
    \Plugins\Base\Controllers\RoleDashboardsController::renderUserControlBoard($view);
    return null;
});

$router->get('/ops/user-control/detail', function () use ($view) {
    \Plugins\Base\Controllers\RoleDashboardsController::renderUserControlDetail($view);
    return null;
});

$router->get('/ops/display-manager', function () use ($view) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        \App\Core\Auth::rememberIntendedUrl('/ops/display-manager');
        header('Location: /login', true, 302);
        exit;
    }

    $user = \App\Core\Auth::user();
    $ctx = platform_user_context_contract()->resolveUserContext($user);
    if (strtolower(trim((string)($ctx['authority_role'] ?? 'app_user'))) !== 'platform_admin') {
        $_SESSION['ops_display_manager_err'] = 'ops.display_manager.flash_forbidden';
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    $rows = \App\Core\DB::fetchAll(
        'SELECT u.id, u.username, u.email, u.authority_role, u.account_status,
            COALESCE(uda.dashboard_type, "my_work") AS dashboard_type
         FROM users u
         LEFT JOIN user_dashboard_assignments uda ON uda.user_id = u.id
         ORDER BY CASE WHEN u.authority_role = "tv_display" THEN 0 ELSE 1 END, u.username ASC, u.id ASC
         LIMIT 500'
    );

    $flash = (string)($_SESSION['ops_display_manager_ok'] ?? '');
    $error = (string)($_SESSION['ops_display_manager_err'] ?? '');
    unset($_SESSION['ops_display_manager_ok'], $_SESSION['ops_display_manager_err']);

    $view->render('Base::ops/display_manager.php', [
        'pageTitle' => function_exists('t') ? (string)t('ops.display_manager.title') : 'Display Manager',
        'rows' => $rows,
        'flash' => $flash,
        'error' => $error,
        'csrf' => \App\Core\Auth::csrfToken(),
    ]);
    return null;
});

$router->post('/ops/display-manager/save-device', function () {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        \App\Core\Auth::rememberIntendedUrl('/ops/display-manager');
        header('Location: /login', true, 302);
        exit;
    }

    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    if (strtolower(trim((string)($ctx['authority_role'] ?? 'app_user'))) !== 'platform_admin') {
        $_SESSION['ops_display_manager_err'] = 'ops.display_manager.flash_forbidden';
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

    $userId = (int)($_POST['user_id'] ?? 0);
    $authorityRole = strtolower(trim((string)($_POST['authority_role'] ?? '')));
    $dashboardType = strtolower(trim((string)($_POST['dashboard_type'] ?? '')));
    if ($userId <= 0) {
        $_SESSION['ops_display_manager_err'] = 'ops.display_manager.flash_missing_user';
        header('Location: /ops/display-manager', true, 302);
        exit;
    }

    if (!in_array($authorityRole, ['app_user', 'tv_display'], true)
        || !in_array($dashboardType, ['my_work', 'operator', 'display'], true)) {
        $_SESSION['ops_display_manager_err'] = 'ops.display_manager.flash_invalid';
        header('Location: /ops/display-manager', true, 302);
        exit;
    }

    $target = \App\Core\DB::fetchOne('SELECT id FROM users WHERE id = ? LIMIT 1', [$userId]);
    if (!$target) {
        $_SESSION['ops_display_manager_err'] = 'ops.display_manager.flash_missing_user';
        header('Location: /ops/display-manager', true, 302);
        exit;
    }

    $actor = trim((string)(\App\Core\Auth::user()['email'] ?? 'platform-admin'));
    \App\Core\DB::query('UPDATE users SET authority_role = ? WHERE id = ? LIMIT 1', [$authorityRole, $userId]);

    $existing = \App\Core\DB::fetchOne('SELECT user_id FROM user_dashboard_assignments WHERE user_id = ? LIMIT 1', [$userId]);
    if ($existing) {
        \App\Core\DB::query(
            'UPDATE user_dashboard_assignments SET dashboard_type = ?, updated_by = ? WHERE user_id = ? LIMIT 1',
            [$dashboardType, $actor, $userId]
        );
    } else {
        \App\Core\DB::query(
            'INSERT INTO user_dashboard_assignments (user_id, dashboard_type, updated_by) VALUES (?, ?, ?)',
            [$userId, $dashboardType, $actor]
        );
    }

    $_SESSION['ops_display_manager_ok'] = 'ops.display_manager.flash_saved';
    header('Location: /ops/display-manager', true, 302);
    exit;
});

// ── No-Code Widget Builder (Platform Governance) ───────────────────────────

$router->get('/ops/widget-builder', function () use ($view) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        \App\Core\Auth::rememberIntendedUrl('/ops/widget-builder');
        header('Location: /login', true, 302);
        exit;
    }

    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        $_SESSION['ops_widget_builder_err'] = 'ops.widget_builder.flash_forbidden';
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    WidgetBuilderService::ensureTables();

    $flash = (string)($_SESSION['ops_widget_builder_ok'] ?? '');
    $error = (string)($_SESSION['ops_widget_builder_err'] ?? '');
    $bulkMeta = $_SESSION['ops_widget_builder_bulk_meta'] ?? null;
    unset($_SESSION['ops_widget_builder_ok'], $_SESSION['ops_widget_builder_err'], $_SESSION['ops_widget_builder_bulk_meta']);

    if (!is_array($bulkMeta)) {
        $bulkMeta = null;
    }

    $rawStatus = $_GET['status'] ?? 'active';
    $rawDataset = $_GET['dataset'] ?? 'all';
    $rawReadiness = $_GET['readiness'] ?? 'all';
    $rawSort = $_GET['sort'] ?? 'updated_desc';
    $rawSearch = $_GET['q'] ?? '';
    $rawPage = $_GET['page'] ?? 1;
    $rawPerPage = $_GET['per_page'] ?? 20;

    $normalizeSearchTerm = static function ($value): string {
        if (!is_scalar($value)) {
            return '';
        }

        $term = trim((string)$value);
        if ($term === '') {
            return '';
        }

        $collapsed = preg_replace('/\s+/u', ' ', $term);
        if (!is_string($collapsed) || $collapsed === '') {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($collapsed) > 120) {
                $collapsed = mb_substr($collapsed, 0, 120);
            }
        } elseif (strlen($collapsed) > 120) {
            $collapsed = substr($collapsed, 0, 120);
        }

        return trim($collapsed);
    };

    $statusFilter = is_scalar($rawStatus) ? strtolower(trim((string)$rawStatus)) : 'active';
    $datasetFilter = is_scalar($rawDataset) ? trim((string)$rawDataset) : 'all';
    $readinessFilter = is_scalar($rawReadiness) ? strtolower(trim((string)$rawReadiness)) : 'all';
    $sortFilter = is_scalar($rawSort) ? strtolower(trim((string)$rawSort)) : 'updated_desc';
    $searchFilter = $normalizeSearchTerm($rawSearch);
    $page = max(1, is_scalar($rawPage) ? (int)$rawPage : 1);
    $perPageRaw = is_scalar($rawPerPage) ? (int)$rawPerPage : 20;
    $allowedPerPage = [2, 10, 20, 50, 100];
    $perPage = in_array($perPageRaw, $allowedPerPage, true) ? $perPageRaw : 20;

    $datasetCatalog = \Apps\Platform\Services\WidgetBuilderDatasetRegistryService::catalog();
    $allowedDatasetKeys = ['all'];
    foreach ($datasetCatalog as $dataset) {
        $datasetKey = trim((string)($dataset['dataset_key'] ?? ''));
        if ($datasetKey !== '') {
            $allowedDatasetKeys[] = $datasetKey;
        }
    }
    if (!in_array($datasetFilter, $allowedDatasetKeys, true)) {
        $datasetFilter = 'all';
    }

    if (!in_array($statusFilter, ['active', 'all', 'draft', 'published', 'archived'], true)) {
        $statusFilter = 'active';
    }
    if (!in_array($readinessFilter, ['all', 'ready', 'blocked'], true)) {
        $readinessFilter = 'all';
    }
    if (!in_array($sortFilter, ['updated_desc', 'updated_asc', 'status', 'readiness'], true)) {
        $sortFilter = 'updated_desc';
    }

    $canonicalQuery = $_GET;
    $requiresCanonicalRedirect = false;

    $statusInput = is_scalar($_GET['status'] ?? null) ? trim((string)$_GET['status']) : null;
    $datasetInput = is_scalar($_GET['dataset'] ?? null) ? trim((string)$_GET['dataset']) : null;
    $readinessInput = is_scalar($_GET['readiness'] ?? null) ? trim((string)$_GET['readiness']) : null;
    $sortInput = is_scalar($_GET['sort'] ?? null) ? trim((string)$_GET['sort']) : null;
    $searchInput = array_key_exists('q', $_GET) ? $normalizeSearchTerm($_GET['q']) : null;
    $perPageInput = is_scalar($_GET['per_page'] ?? null) ? (string)((int)$_GET['per_page']) : null;
    $pageInput = is_scalar($_GET['page'] ?? null) ? (string)((int)$_GET['page']) : null;

    if (array_key_exists('status', $_GET) && $statusInput !== $statusFilter) {
        $canonicalQuery['status'] = $statusFilter;
        $requiresCanonicalRedirect = true;
    }
    if (array_key_exists('dataset', $_GET) && $datasetInput !== $datasetFilter) {
        $canonicalQuery['dataset'] = $datasetFilter;
        $requiresCanonicalRedirect = true;
    }
    if (array_key_exists('readiness', $_GET) && $readinessInput !== $readinessFilter) {
        $canonicalQuery['readiness'] = $readinessFilter;
        $requiresCanonicalRedirect = true;
    }
    if (array_key_exists('sort', $_GET) && $sortInput !== $sortFilter) {
        $canonicalQuery['sort'] = $sortFilter;
        $requiresCanonicalRedirect = true;
    }
    if (array_key_exists('q', $_GET) && $searchInput !== $searchFilter) {
        if ($searchFilter === '') {
            unset($canonicalQuery['q']);
        } else {
            $canonicalQuery['q'] = $searchFilter;
        }
        $requiresCanonicalRedirect = true;
    }
    if (array_key_exists('per_page', $_GET) && $perPageInput !== (string)$perPage) {
        $canonicalQuery['per_page'] = (string)$perPage;
        $requiresCanonicalRedirect = true;
    }
    if (array_key_exists('page', $_GET) && $pageInput !== (string)$page) {
        $canonicalQuery['page'] = (string)$page;
        $requiresCanonicalRedirect = true;
    }

    if ($requiresCanonicalRedirect) {
        $query = http_build_query($canonicalQuery);
        header('Location: /ops/widget-builder' . ($query !== '' ? ('?' . $query) : ''), true, 302);
        exit;
    }

    $allRows = WidgetBuilderService::listBlueprints();
    $rows = $allRows;
    if ($statusFilter === 'active') {
        $rows = array_values(array_filter($rows, static function (array $row): bool {
            return strtolower(trim((string)($row['status'] ?? ''))) !== 'archived';
        }));
    } elseif ($statusFilter !== '' && $statusFilter !== 'all') {
        $rows = array_values(array_filter($rows, static function (array $row) use ($statusFilter): bool {
            return strtolower(trim((string)($row['status'] ?? ''))) === $statusFilter;
        }));
    }
    if ($datasetFilter !== '' && $datasetFilter !== 'all') {
        $rows = array_values(array_filter($rows, static function (array $row) use ($datasetFilter): bool {
            return trim((string)($row['dataset_key'] ?? '')) === $datasetFilter;
        }));
    }
    if ($searchFilter !== '') {
        $needle = strtolower($searchFilter);
        $rows = array_values(array_filter($rows, static function (array $row) use ($needle): bool {
            $haystack = implode(' ', [
                strtolower(trim((string)($row['widget_key'] ?? ''))),
                strtolower(trim((string)($row['title_key'] ?? ''))),
                strtolower(trim((string)($row['module_key'] ?? ''))),
                strtolower(trim((string)($row['app_key'] ?? ''))),
            ]);
            return $haystack !== '' && str_contains($haystack, $needle);
        }));
    }

    $validationReport = WidgetBuilderService::validationReportForRows($rows);
    if ($readinessFilter === 'ready' || $readinessFilter === 'blocked') {
        $requireReady = $readinessFilter === 'ready';
        $rows = array_values(array_filter($rows, static function (array $row) use ($validationReport, $requireReady): bool {
            $id = (int)($row['id'] ?? 0);
            $ready = (bool)($validationReport[$id]['ready'] ?? false);
            return $ready === $requireReady;
        }));
        $validationReport = WidgetBuilderService::validationReportForRows($rows);
    }

    if ($sortFilter === 'updated_asc') {
        usort($rows, static function (array $a, array $b): int {
            return strcmp((string)($a['updated_at'] ?? ''), (string)($b['updated_at'] ?? ''));
        });
    } elseif ($sortFilter === 'status') {
        usort($rows, static function (array $a, array $b): int {
            return strcmp((string)($a['status'] ?? ''), (string)($b['status'] ?? ''));
        });
    } elseif ($sortFilter === 'readiness') {
        usort($rows, static function (array $a, array $b) use ($validationReport): int {
            $aId = (int)($a['id'] ?? 0);
            $bId = (int)($b['id'] ?? 0);
            $aReady = (bool)($validationReport[$aId]['ready'] ?? false);
            $bReady = (bool)($validationReport[$bId]['ready'] ?? false);
            if ($aReady === $bReady) {
                return strcmp((string)($a['updated_at'] ?? ''), (string)($b['updated_at'] ?? ''));
            }
            return $aReady ? -1 : 1;
        });
    } else {
        $sortFilter = 'updated_desc';
        usort($rows, static function (array $a, array $b): int {
            return strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? ''));
        });
    }

    $summaryCounts = [
        'all' => count($rows),
        'draft' => 0,
        'published' => 0,
        'archived' => 0,
        'ready' => 0,
        'blocked' => 0,
        'ready_draft' => 0,
    ];
    foreach ($rows as $row) {
        $status = strtolower(trim((string)($row['status'] ?? '')));
        if (array_key_exists($status, $summaryCounts)) {
            $summaryCounts[$status]++;
        }
        $id = (int)($row['id'] ?? 0);
        $ready = (bool)($validationReport[$id]['ready'] ?? false);
        if ($ready) {
            $summaryCounts['ready']++;
            if ($status === 'draft') {
                $summaryCounts['ready_draft']++;
            }
        } else {
            $summaryCounts['blocked']++;
        }
    }

    $totalRows = count($allRows);
    $filteredRows = count($rows);
    $totalPages = max(1, (int)ceil($filteredRows / $perPage));
    $requestedPageInput = is_scalar($_GET['page'] ?? null) ? (string)((int)$_GET['page']) : null;
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    if (array_key_exists('page', $_GET) && $requestedPageInput !== (string)$page) {
        $boundedQuery = $_GET;
        $boundedQuery['page'] = (string)$page;
        $query = http_build_query($boundedQuery);
        header('Location: /ops/widget-builder' . ($query !== '' ? ('?' . $query) : ''), true, 302);
        exit;
    }
    $offset = ($page - 1) * $perPage;
    $rows = array_slice($rows, $offset, $perPage);
    $validationReport = WidgetBuilderService::validationReportForRows($rows);
    $runtimeFieldMap = [];
    foreach ($datasetCatalog as $dataset) {
        $key = trim((string)($dataset['dataset_key'] ?? ''));
        if ($key === '') {
            continue;
        }
        $runtimeFieldMap[$key] = \Apps\Platform\Services\WidgetBuilderDatasetRegistryService::allowedRuntimeFields($key);
    }

    $defaultDataset = [];
    if (!empty($datasetCatalog) && is_array($datasetCatalog[0] ?? null)) {
        $defaultDataset = (array)$datasetCatalog[0];
    }
    $defaultAppKey = trim((string)($defaultDataset['app_key'] ?? ''));
    $defaultModuleKey = trim((string)($defaultDataset['module_key'] ?? ''));
    $defaultDatasetKey = trim((string)($defaultDataset['dataset_key'] ?? ''));
    $defaultMetricField = '';
    $defaultLimit = 10;
    $defaultTemplateDefaults = (array)($defaultDataset['template_defaults'] ?? []);
    $defaultKpiTemplate = (array)($defaultTemplateDefaults['kpi'] ?? []);
    if (trim((string)($defaultKpiTemplate['metric_field'] ?? '')) !== '') {
        $defaultMetricField = trim((string)$defaultKpiTemplate['metric_field']);
    } elseif ($defaultDatasetKey !== '' && !empty($runtimeFieldMap[$defaultDatasetKey])) {
        $defaultMetricField = trim((string)$runtimeFieldMap[$defaultDatasetKey][0]);
    }
    if ((int)($defaultKpiTemplate['limit'] ?? 0) > 0) {
        $defaultLimit = (int)$defaultKpiTemplate['limit'];
    }

    $view->render('platform::ops/widget_builder.php', [
        'pageTitle' => function_exists('t') ? (string)t('ops.widget_builder.page_title') : 'ops.widget_builder.page_title',
        'flash' => $flash,
        'error' => $error,
        'bulkMeta' => $bulkMeta,
        'csrf' => \App\Core\Auth::csrfToken(),
        'rows' => $rows,
        'statusFilter' => $statusFilter,
        'datasetFilter' => $datasetFilter,
        'readinessFilter' => $readinessFilter,
        'sortFilter' => $sortFilter,
        'searchFilter' => $searchFilter,
        'summaryCounts' => $summaryCounts,
        'totalRows' => $totalRows,
        'filteredRows' => $filteredRows,
        'page' => $page,
        'perPage' => $perPage,
        'totalPages' => $totalPages,
        'validationReport' => $validationReport,
        'datasets' => $datasetCatalog,
        'runtimeFieldMap' => $runtimeFieldMap,
        'templates' => WidgetBuilderService::templateCatalog(),
        'zones' => WidgetBuilderService::placementZones(),
        'defaultValues' => [
            'app_key' => trim((string)($_GET['app_key'] ?? $defaultAppKey)),
            'module_key' => trim((string)($_GET['module_key'] ?? $defaultModuleKey)),
            'widget_key' => trim((string)($_GET['widget_key'] ?? '')),
            'template_type' => trim((string)($_GET['template_type'] ?? 'kpi')),
            'dataset_key' => trim((string)($_GET['dataset_key'] ?? $defaultDatasetKey)),
            'placement_zone' => trim((string)($_GET['placement_zone'] ?? 'dashboard_summary')),
            'title_key' => trim((string)($_GET['title_key'] ?? 'ops.widget_builder.generated.title')),
            'description_key' => trim((string)($_GET['description_key'] ?? 'ops.widget_builder.generated.description')),
            'config_json' => trim((string)($_GET['config_json'] ?? json_encode([
                'metric_field' => $defaultMetricField,
                'limit' => $defaultLimit,
            ], JSON_UNESCAPED_SLASHES))),
        ],
    ]);
    return null;
});

$router->post('/ops/widget-builder/save', function () {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        header('Location: /login', true, 302);
        exit;
    }

    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        $_SESSION['ops_widget_builder_err'] = 'ops.widget_builder.flash_forbidden';
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/ops/widget-builder');

    $redirectTo = trim((string)($_POST['redirect_to'] ?? ''));
    if (!preg_match('#^/ops/widget-builder(?:\?|$)#', $redirectTo)) {
        $redirectTo = '/ops/widget-builder';
    }

    try {
        WidgetBuilderService::ensureTables();
        WidgetBuilderService::createDraft($_POST, \App\Core\Auth::user());
        $_SESSION['ops_widget_builder_ok'] = 'ops.widget_builder.flash_saved';
        header('Location: ' . $redirectTo, true, 302);
        exit;
    } catch (\Throwable $e) {
        $_SESSION['ops_widget_builder_err'] = $e->getMessage();
        $query = http_build_query([
            'app_key' => trim((string)($_POST['app_key'] ?? '')),
            'module_key' => trim((string)($_POST['module_key'] ?? '')),
            'widget_key' => trim((string)($_POST['widget_key'] ?? '')),
            'template_type' => trim((string)($_POST['template_type'] ?? '')),
            'dataset_key' => trim((string)($_POST['dataset_key'] ?? '')),
            'placement_zone' => trim((string)($_POST['placement_zone'] ?? '')),
            'title_key' => trim((string)($_POST['title_key'] ?? '')),
            'description_key' => trim((string)($_POST['description_key'] ?? '')),
            'config_json' => trim((string)($_POST['config_json'] ?? '')),
        ]);
        header('Location: /ops/widget-builder' . ($query !== '' ? '?' . $query : ''), true, 302);
        exit;
    }
});

$router->post('/ops/widget-builder/publish', function () {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        header('Location: /login', true, 302);
        exit;
    }

    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        $_SESSION['ops_widget_builder_err'] = 'ops.widget_builder.flash_forbidden';
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/ops/widget-builder');

    $redirectTo = trim((string)($_POST['redirect_to'] ?? ''));
    if (!preg_match('#^/ops/widget-builder(?:\?|$)#', $redirectTo)) {
        $redirectTo = '/ops/widget-builder';
    }

    try {
        WidgetBuilderService::changeStatus((int)($_POST['blueprint_id'] ?? 0), 'published', \App\Core\Auth::user());
        $_SESSION['ops_widget_builder_ok'] = 'ops.widget_builder.flash_published';
    } catch (\Throwable $e) {
        $_SESSION['ops_widget_builder_err'] = $e->getMessage();
    }

    header('Location: ' . $redirectTo, true, 302);
    exit;
});

$router->post('/ops/widget-builder/archive', function () {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        header('Location: /login', true, 302);
        exit;
    }

    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        $_SESSION['ops_widget_builder_err'] = 'ops.widget_builder.flash_forbidden';
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/ops/widget-builder');

    $redirectTo = trim((string)($_POST['redirect_to'] ?? ''));
    if (!preg_match('#^/ops/widget-builder(?:\?|$)#', $redirectTo)) {
        $redirectTo = '/ops/widget-builder';
    }

    try {
        WidgetBuilderService::changeStatus((int)($_POST['blueprint_id'] ?? 0), 'archived', \App\Core\Auth::user());
        $_SESSION['ops_widget_builder_ok'] = 'ops.widget_builder.flash_archived';
    } catch (\Throwable $e) {
        $_SESSION['ops_widget_builder_err'] = $e->getMessage();
    }

    header('Location: ' . $redirectTo, true, 302);
    exit;
});

$router->post('/ops/widget-builder/clone', function () {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        header('Location: /login', true, 302);
        exit;
    }

    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        $_SESSION['ops_widget_builder_err'] = 'ops.widget_builder.flash_forbidden';
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/ops/widget-builder');

    $redirectTo = trim((string)($_POST['redirect_to'] ?? ''));
    if (!preg_match('#^/ops/widget-builder(?:\?|$)#', $redirectTo)) {
        $redirectTo = '/ops/widget-builder';
    }

    try {
        WidgetBuilderService::cloneBlueprintAsDraft((int)($_POST['blueprint_id'] ?? 0), \App\Core\Auth::user());
        $_SESSION['ops_widget_builder_ok'] = 'ops.widget_builder.flash_saved';
    } catch (\Throwable $e) {
        $_SESSION['ops_widget_builder_err'] = $e->getMessage();
    }

    header('Location: ' . $redirectTo, true, 302);
    exit;
});

$router->post('/ops/widget-builder/bulk-archive', function () {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        header('Location: /login', true, 302);
        exit;
    }

    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        $_SESSION['ops_widget_builder_err'] = 'ops.widget_builder.flash_forbidden';
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/ops/widget-builder');

    $redirectTo = trim((string)($_POST['redirect_to'] ?? ''));
    if (!preg_match('#^/ops/widget-builder(?:\?|$)#', $redirectTo)) {
        $redirectTo = '/ops/widget-builder';
    }

    $rawIds = (array)($_POST['blueprint_ids'] ?? []);
    $ids = array_values(array_unique(array_filter(array_map(static fn ($value): int => (int)$value, $rawIds), static fn (int $id): bool => $id > 0)));
    if (count($ids) > 200) {
        $_SESSION['ops_widget_builder_err'] = 'ops.widget_builder.error.invalid_blueprint_id';
        header('Location: ' . $redirectTo, true, 302);
        exit;
    }

    if ($ids === []) {
        $_SESSION['ops_widget_builder_err'] = 'ops.widget_builder.error.invalid_blueprint_id';
        header('Location: ' . $redirectTo, true, 302);
        exit;
    }

    $eligibleIds = WidgetBuilderService::filterBulkEligibleIds('archive', $ids);
    if ($eligibleIds === []) {
        $_SESSION['ops_widget_builder_err'] = 'ops.widget_builder.error.invalid_status_transition';
        header('Location: ' . $redirectTo, true, 302);
        exit;
    }

    $archived = 0;
    foreach ($eligibleIds as $id) {
        try {
            WidgetBuilderService::changeStatus($id, 'archived', \App\Core\Auth::user());
            $archived++;
        } catch (\Throwable) {
            // Continue processing remaining selected rows.
        }
    }

    if ($archived > 0) {
        $_SESSION['ops_widget_builder_ok'] = 'ops.widget_builder.flash_archived';
        $_SESSION['ops_widget_builder_bulk_meta'] = [
            'action_label_key' => 'ops.widget_builder.action.bulk_archive',
            'success' => $archived,
            'selected' => count($ids),
        ];
    } else {
        $_SESSION['ops_widget_builder_err'] = 'ops.widget_builder.error.invalid_status_transition';
    }

    header('Location: ' . $redirectTo, true, 302);
    exit;
});

$router->post('/ops/widget-builder/bulk-restore', function () {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        header('Location: /login', true, 302);
        exit;
    }

    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        $_SESSION['ops_widget_builder_err'] = 'ops.widget_builder.flash_forbidden';
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/ops/widget-builder');

    $redirectTo = trim((string)($_POST['redirect_to'] ?? ''));
    if (!preg_match('#^/ops/widget-builder(?:\?|$)#', $redirectTo)) {
        $redirectTo = '/ops/widget-builder';
    }

    $rawIds = (array)($_POST['blueprint_ids'] ?? []);
    $ids = array_values(array_unique(array_filter(array_map(static fn ($value): int => (int)$value, $rawIds), static fn (int $id): bool => $id > 0)));
    if (count($ids) > 200) {
        $_SESSION['ops_widget_builder_err'] = 'ops.widget_builder.error.invalid_blueprint_id';
        header('Location: ' . $redirectTo, true, 302);
        exit;
    }

    if ($ids === []) {
        $_SESSION['ops_widget_builder_err'] = 'ops.widget_builder.error.invalid_blueprint_id';
        header('Location: ' . $redirectTo, true, 302);
        exit;
    }

    $eligibleIds = WidgetBuilderService::filterBulkEligibleIds('restore', $ids);
    if ($eligibleIds === []) {
        $_SESSION['ops_widget_builder_err'] = 'ops.widget_builder.error.invalid_status_transition';
        header('Location: ' . $redirectTo, true, 302);
        exit;
    }

    $restored = 0;
    foreach ($eligibleIds as $id) {
        try {
            WidgetBuilderService::changeStatus($id, 'draft', \App\Core\Auth::user());
            $restored++;
        } catch (\Throwable) {
            // Continue processing remaining selected rows.
        }
    }

    if ($restored > 0) {
        $_SESSION['ops_widget_builder_ok'] = 'ops.widget_builder.flash_saved';
        $_SESSION['ops_widget_builder_bulk_meta'] = [
            'action_label_key' => 'ops.widget_builder.action.bulk_restore',
            'success' => $restored,
            'selected' => count($ids),
        ];
    } else {
        $_SESSION['ops_widget_builder_err'] = 'ops.widget_builder.error.invalid_status_transition';
    }

    header('Location: ' . $redirectTo, true, 302);
    exit;
});

$router->post('/ops/widget-builder/bulk-publish', function () {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        header('Location: /login', true, 302);
        exit;
    }

    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        $_SESSION['ops_widget_builder_err'] = 'ops.widget_builder.flash_forbidden';
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/ops/widget-builder');

    $redirectTo = trim((string)($_POST['redirect_to'] ?? ''));
    if (!preg_match('#^/ops/widget-builder(?:\?|$)#', $redirectTo)) {
        $redirectTo = '/ops/widget-builder';
    }

    $rawIds = (array)($_POST['blueprint_ids'] ?? []);
    $ids = array_values(array_unique(array_filter(array_map(static fn ($value): int => (int)$value, $rawIds), static fn (int $id): bool => $id > 0)));
    if (count($ids) > 200) {
        $_SESSION['ops_widget_builder_err'] = 'ops.widget_builder.error.invalid_blueprint_id';
        header('Location: ' . $redirectTo, true, 302);
        exit;
    }

    if ($ids === []) {
        $_SESSION['ops_widget_builder_err'] = 'ops.widget_builder.error.invalid_blueprint_id';
        header('Location: ' . $redirectTo, true, 302);
        exit;
    }

    $eligibleIds = WidgetBuilderService::filterBulkEligibleIds('publish', $ids);
    if ($eligibleIds === []) {
        $_SESSION['ops_widget_builder_err'] = 'ops.widget_builder.error.invalid_status_transition';
        header('Location: ' . $redirectTo, true, 302);
        exit;
    }

    $published = 0;
    foreach ($eligibleIds as $id) {
        try {
            WidgetBuilderService::changeStatus($id, 'published', \App\Core\Auth::user());
            $published++;
        } catch (\Throwable) {
            // Continue processing remaining selected rows.
        }
    }

    if ($published > 0) {
        $_SESSION['ops_widget_builder_ok'] = 'ops.widget_builder.flash_published';
        $_SESSION['ops_widget_builder_bulk_meta'] = [
            'action_label_key' => 'ops.widget_builder.action.bulk_publish',
            'success' => $published,
            'selected' => count($ids),
        ];
    } else {
        $_SESSION['ops_widget_builder_err'] = 'ops.widget_builder.error.invalid_status_transition';
    }

    header('Location: ' . $redirectTo, true, 302);
    exit;
});

$router->post('/ops/widget-builder/restore', function () {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        header('Location: /login', true, 302);
        exit;
    }

    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        $_SESSION['ops_widget_builder_err'] = 'ops.widget_builder.flash_forbidden';
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/ops/widget-builder');

    $redirectTo = trim((string)($_POST['redirect_to'] ?? ''));
    if (!preg_match('#^/ops/widget-builder(?:\?|$)#', $redirectTo)) {
        $redirectTo = '/ops/widget-builder';
    }

    try {
        WidgetBuilderService::changeStatus((int)($_POST['blueprint_id'] ?? 0), 'draft', \App\Core\Auth::user());
        $_SESSION['ops_widget_builder_ok'] = 'ops.widget_builder.flash_saved';
    } catch (\Throwable $e) {
        $_SESSION['ops_widget_builder_err'] = $e->getMessage();
    }

    header('Location: ' . $redirectTo, true, 302);
    exit;
});

$router->get('/ops/widget-builder/edit', function () use ($view) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        \App\Core\Auth::rememberIntendedUrl('/ops/widget-builder');
        header('Location: /login', true, 302);
        exit;
    }

    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        $_SESSION['ops_widget_builder_err'] = 'ops.widget_builder.flash_forbidden';
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    $blueprintId = (int)($_GET['id'] ?? 0);
    if ($blueprintId <= 0) {
        $_SESSION['ops_widget_builder_err'] = 'ops.widget_builder.error.invalid_blueprint_id';
        header('Location: /ops/widget-builder', true, 302);
        exit;
    }

    $blueprint = \App\Core\DB::fetchOne(
        'SELECT id, app_key, module_key, widget_key, title_key, description_key, template_type,
                dataset_key, placement_zone, config_json, status
         FROM platform_widget_blueprints WHERE id=? LIMIT 1',
        [$blueprintId]
    );
    if (!$blueprint || strtolower(trim((string)($blueprint['status'] ?? ''))) !== 'draft') {
        $_SESSION['ops_widget_builder_err'] = 'ops.widget_builder.error.edit_only_allowed_for_drafts';
        header('Location: /ops/widget-builder', true, 302);
        exit;
    }

    $flash = (string)($_SESSION['ops_widget_builder_ok'] ?? '');
    $error = (string)($_SESSION['ops_widget_builder_err'] ?? '');
    unset($_SESSION['ops_widget_builder_ok'], $_SESSION['ops_widget_builder_err']);

    $datasetService = WidgetBuilderDatasetRegistryService::class;
    $datasetCatalog = $datasetService::catalog();

    $view->render('Base::ops/widget_builder_edit.php', [
        'pageTitle'      => function_exists('t') ? (string)t('ops.widget_builder.edit.page_title') : 'ops.widget_builder.edit.page_title',
        'blueprint'      => $blueprint,
        'datasets'       => $datasetCatalog,
        'templates'      => WidgetBuilderService::templateCatalog(),
        'zones'          => WidgetBuilderService::placementZones(),
        'runtimeFieldMap'=> WidgetBuilderService::buildRuntimeFieldMapFromCatalog($datasetCatalog),
        'flash'          => $flash,
        'error'          => $error,
        'csrfToken'      => \App\Core\Auth::csrfToken(),
    ]);
});

$router->post('/ops/widget-builder/update', function () {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        header('Location: /login', true, 302);
        exit;
    }

    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        $_SESSION['ops_widget_builder_err'] = 'ops.widget_builder.flash_forbidden';
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/ops/widget-builder');

    $blueprintId = (int)($_POST['blueprint_id'] ?? 0);
    $redirectTo = '/ops/widget-builder/edit?id=' . $blueprintId;

    try {
        WidgetBuilderService::updateDraft($blueprintId, $_POST, \App\Core\Auth::user());
        $_SESSION['ops_widget_builder_ok'] = 'ops.widget_builder.flash_updated';
    } catch (\Throwable $e) {
        $_SESSION['ops_widget_builder_err'] = $e->getMessage();
    }

    header('Location: ' . $redirectTo, true, 302);
    exit;
});

// ── Design Studio ────────────────────────────────────────────────────────────

$router->get('/ops/design-studio', function () use ($view) {
    set_error_handler(static function (): bool {
        header('Location: /apps/studio', true, 302);
        exit;
    });

    if (!getenv('OPS_ENABLE_LEGACY_DESIGN_STUDIO')) {
        header('Location: /apps/studio', true, 302);
        exit;
    }

    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        \App\Core\Auth::rememberIntendedUrl('/ops/design-studio');
        header('Location: /login', true, 302);
        exit;
    }

    $user = \App\Core\Auth::user();
    $ctx  = \Plugins\Base\Services\UserDashboardAssignmentService::resolveUserContext($user);
    if ((string)($ctx['authority_role'] ?? '') !== 'platform_admin') {
        http_response_code(403);
        echo htmlspecialchars(t('common.access_denied'));
        exit;
    }

    DesignStudioService::ensureTables();

    // Filter inputs — guard against non-scalar injection
    $statusFilter  = is_scalar($_GET['status']         ?? null) ? trim((string)$_GET['status'])         : 'all';
    $roleFilter    = is_scalar($_GET['target_role']    ?? null) ? trim((string)$_GET['target_role'])    : 'all';
    $surfaceFilter = is_scalar($_GET['target_surface'] ?? null) ? trim((string)$_GET['target_surface']) : 'all';
    $searchQ       = is_scalar($_GET['q']              ?? null) ? trim((string)$_GET['q'])              : '';
    $page          = is_scalar($_GET['page']           ?? null) ? max(1, (int)$_GET['page'])            : 1;
    $perPage       = is_scalar($_GET['per_page']       ?? null) ? max(5, min(50, (int)$_GET['per_page'])) : 20;

    // Normalize status
    $validStatuses = ['all', 'draft', 'published', 'archived'];
    if (!in_array($statusFilter, $validStatuses, true)) {
        $statusFilter = 'all';
    }

    // Canonical redirect if params were normalized
    $canonical = http_build_query(array_filter([
        'status'         => $statusFilter !== 'all' ? $statusFilter : null,
        'target_role'    => $roleFilter !== 'all' ? $roleFilter : null,
        'target_surface' => $surfaceFilter !== 'all' ? $surfaceFilter : null,
        'q'              => $searchQ !== '' ? $searchQ : null,
        'page'           => $page > 1 ? $page : null,
        'per_page'       => $perPage !== 20 ? $perPage : null,
    ], static fn($v) => $v !== null));
    $incoming = trim((string)($_SERVER['QUERY_STRING'] ?? ''));
    if ($incoming !== $canonical) {
        header('Location: /ops/design-studio' . ($canonical !== '' ? ('?' . $canonical) : ''), true, 302);
        exit;
    }

    $filters = [
        'status'         => $statusFilter,
        'target_role'    => $roleFilter,
        'target_surface' => $surfaceFilter,
        'q'              => $searchQ,
    ];

    $result     = DesignStudioService::listCompositions($filters, $page, $perPage);
    $rows       = $result['rows'];
    $totalRows  = $result['total'];
    $filtered   = $result['filtered'];
    $summary    = $result['summary'];
    $totalPages = max(1, (int)ceil($filtered / $perPage));

    if ($page > $totalPages) {
        $bounced = array_filter([
            'status'         => $statusFilter !== 'all' ? $statusFilter : null,
            'target_role'    => $roleFilter !== 'all' ? $roleFilter : null,
            'target_surface' => $surfaceFilter !== 'all' ? $surfaceFilter : null,
            'q'              => $searchQ !== '' ? $searchQ : null,
            'page'           => $totalPages > 1 ? $totalPages : null,
            'per_page'       => $perPage !== 20 ? $perPage : null,
        ], static fn($v) => $v !== null);
        $bouncedQ = http_build_query($bounced);
        header('Location: /ops/design-studio' . ($bouncedQ !== '' ? ('?' . $bouncedQ) : ''), true, 302);
        exit;
    }

    $flash = '';
    $error = '';
    if (isset($_SESSION['ops_design_studio_flash'])) {
        $flash = (string)$_SESSION['ops_design_studio_flash'];
        unset($_SESSION['ops_design_studio_flash']);
    }
    if (isset($_SESSION['ops_design_studio_error'])) {
        $error = (string)$_SESSION['ops_design_studio_error'];
        unset($_SESSION['ops_design_studio_error']);
    }

    $csrf     = \App\Core\Auth::csrfToken();
    $roles    = DesignStudioService::targetRoles();
    $surfaces = DesignStudioService::targetSurfaces();

    $view->render('Base::ops/design_studio.php', [
        'pageTitle'     => t('ops.design_studio.page_title'),
        'rows'          => $rows,
        'totalRows'     => $totalRows,
        'filteredRows'  => $filtered,
        'summaryCounts' => $summary,
        'page'          => $page,
        'perPage'       => $perPage,
        'totalPages'    => $totalPages,
        'statusFilter'  => $statusFilter,
        'roleFilter'    => $roleFilter,
        'surfaceFilter' => $surfaceFilter,
        'searchQ'       => $searchQ,
        'roles'         => $roles,
        'surfaces'      => $surfaces,
        'csrf'          => $csrf,
        'flash'         => $flash,
        'error'         => $error,
    ]);
    return null;
});

$router->post('/ops/design-studio/save', function () {
    set_error_handler(static function (): bool {
        header('Location: /apps/studio', true, 302);
        exit;
    });

    if (!getenv('OPS_ENABLE_LEGACY_DESIGN_STUDIO')) {
        header('Location: /apps/studio', true, 302);
        exit;
    }

    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        header('Location: /login', true, 302);
        exit;
    }

    $user = \App\Core\Auth::user();
    $ctx  = \Plugins\Base\Services\UserDashboardAssignmentService::resolveUserContext($user);
    if ((string)($ctx['authority_role'] ?? '') !== 'platform_admin') {
        http_response_code(403);
        echo htmlspecialchars(t('common.access_denied'));
        exit;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/ops/design-studio');

    $redirectTo = trim((string)($_POST['redirect_to'] ?? ''));
    if (!preg_match('#^/ops/design-studio(?:\?|$)#', $redirectTo)) {
        $redirectTo = '/ops/design-studio';
    }

    DesignStudioService::ensureTables();

    $userId = (int)($user['id'] ?? 0);
    $email  = trim((string)($user['email'] ?? ''));

    try {
        DesignStudioService::createComposition($_POST, $userId, $email);
        $_SESSION['ops_design_studio_flash'] = 'ops.design_studio.flash_created';
    } catch (\InvalidArgumentException $e) {
        $_SESSION['ops_design_studio_error'] = $e->getMessage();
    }

    header('Location: ' . $redirectTo, true, 302);
    exit;
});

// ── Design Studio: edit / update / status ───────────────────────────────────

$router->get('/ops/design-studio/edit', function () use ($view) {
    set_error_handler(static function (): bool {
        header('Location: /apps/studio', true, 302);
        exit;
    });

    if (!getenv('OPS_ENABLE_LEGACY_DESIGN_STUDIO')) {
        header('Location: /apps/studio', true, 302);
        exit;
    }

    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        \App\Core\Auth::rememberIntendedUrl('/ops/design-studio');
        header('Location: /login', true, 302);
        exit;
    }
    $ctx  = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    DesignStudioService::ensureTables();

    $id = is_scalar($_GET['id'] ?? null) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        header('Location: /ops/design-studio', true, 302);
        exit;
    }

    $composition = DesignStudioService::findById($id);
    if ($composition === null) {
        header('Location: /ops/design-studio', true, 302);
        exit;
    }

    $redirectTo = is_scalar($_GET['redirect_to'] ?? null) ? trim((string)$_GET['redirect_to']) : '';
    if (!preg_match('#^/ops/design-studio(?:[/?]|$)#', $redirectTo)) {
        $redirectTo = '/ops/design-studio';
    }

    $flash = (string)($_SESSION['ops_design_studio_edit_flash'] ?? '');
    $error = (string)($_SESSION['ops_design_studio_edit_error'] ?? '');
    unset($_SESSION['ops_design_studio_edit_flash'], $_SESSION['ops_design_studio_edit_error']);

    $csrf              = \App\Core\Auth::csrfToken();
    $roles             = DesignStudioService::targetRoles();
    $surfaces          = DesignStudioService::targetSurfaces();
    $blueprintsByZone  = DesignStudioService::publishedBlueprintsByZone();
    $zones             = \Apps\Platform\Services\WidgetBuilderService::placementZones();

    // Parse existing slots from config_json
    $configJson = trim((string)($composition['config_json'] ?? ''));
    $savedSlots = [];
    if ($configJson !== '') {
        $decoded = json_decode($configJson, true);
        if (is_array($decoded) && isset($decoded['slots']) && is_array($decoded['slots'])) {
            $savedSlots = $decoded['slots'];
        }
    }

    $view->render('Base::ops/design_studio_edit.php', [
        'composition'      => $composition,
        'roles'            => $roles,
        'surfaces'         => $surfaces,
        'zones'            => $zones,
        'blueprintsByZone' => $blueprintsByZone,
        'savedSlots'       => $savedSlots,
        'redirectTo'       => $redirectTo,
        'csrf'             => $csrf,
        'flash'            => $flash,
        'error'            => $error,
    ]);
});

$router->post('/ops/design-studio/update', function () {
    set_error_handler(static function (): bool {
        header('Location: /apps/studio', true, 302);
        exit;
    });

    if (!getenv('OPS_ENABLE_LEGACY_DESIGN_STUDIO')) {
        header('Location: /apps/studio', true, 302);
        exit;
    }

    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        header('Location: /login', true, 302);
        exit;
    }
    $ctx  = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    $id = is_scalar($_POST['composition_id'] ?? null) ? (int)$_POST['composition_id'] : 0;
    $defaultBack = $id > 0 ? '/ops/design-studio/edit?id=' . $id : '/ops/design-studio';
    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $defaultBack);

    $redirectTo = is_scalar($_POST['redirect_to'] ?? null) ? trim((string)$_POST['redirect_to']) : '';
    if (!preg_match('#^/ops/design-studio(?:[/?]|$)#', $redirectTo)) {
        $redirectTo = $defaultBack;
    }

    if ($id <= 0) {
        header('Location: /ops/design-studio', true, 302);
        exit;
    }

    // Parse slot assignments: slots[zone][] = blueprint_id
    $rawSlots = isset($_POST['slots']) && is_array($_POST['slots']) ? $_POST['slots'] : [];
    $slots    = [];
    foreach ($rawSlots as $zone => $ids) {
        $zone = trim((string)$zone);
        if ($zone !== '' && is_array($ids)) {
            $slots[$zone] = $ids;
        }
    }

    $user   = \App\Core\Auth::user();
    $userId = (int)($user['id'] ?? 0);
    $email  = trim((string)($user['email'] ?? ''));

    try {
        DesignStudioService::updateComposition($id, array_merge($_POST, ['slots' => $slots]), $userId, $email);
        $_SESSION['ops_design_studio_edit_flash'] = 'ops.design_studio.flash_updated';
    } catch (\InvalidArgumentException $e) {
        $_SESSION['ops_design_studio_edit_error'] = $e->getMessage();
    }

    header('Location: ' . $redirectTo, true, 302);
    exit;
});

$router->post('/ops/design-studio/status', function () {
    set_error_handler(static function (): bool {
        header('Location: /apps/studio', true, 302);
        exit;
    });

    if (!getenv('OPS_ENABLE_LEGACY_DESIGN_STUDIO')) {
        header('Location: /apps/studio', true, 302);
        exit;
    }

    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        header('Location: /login', true, 302);
        exit;
    }
    $ctx  = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    $id = is_scalar($_POST['composition_id'] ?? null) ? (int)$_POST['composition_id'] : 0;
    $defaultBack = $id > 0 ? '/ops/design-studio/edit?id=' . $id : '/ops/design-studio';
    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $defaultBack);

    $redirectTo = is_scalar($_POST['redirect_to'] ?? null) ? trim((string)$_POST['redirect_to']) : '';
    if (!preg_match('#^/ops/design-studio(?:[/?]|$)#', $redirectTo)) {
        $redirectTo = $defaultBack;
    }

    if ($id <= 0) {
        header('Location: /ops/design-studio', true, 302);
        exit;
    }

    $newStatus = trim((string)($_POST['new_status'] ?? ''));
    $user      = \App\Core\Auth::user();
    $userId    = (int)($user['id'] ?? 0);
    $email     = trim((string)($user['email'] ?? ''));

    try {
        DesignStudioService::changeStatus($id, $newStatus, $userId, $email);
        $_SESSION['ops_design_studio_edit_flash'] = 'ops.design_studio.flash_status_changed';
    } catch (\InvalidArgumentException $e) {
        $_SESSION['ops_design_studio_edit_error'] = $e->getMessage();
    }

    header('Location: ' . $redirectTo, true, 302);
    exit;
});

// ── Operator Task Management ─────────────────────────────────────────────────

$router->get('/ops/operator-tasks', function () use ($view) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        \App\Core\Auth::rememberIntendedUrl('/ops/operator-tasks');
        header('Location: /login', true, 302);
        exit;
    }
    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if (!in_array($role, ['platform_admin', 'app_admin'], true)) {
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    $statusFilter = trim((string)($_GET['status'] ?? ''));
    $editTaskId   = (int)($_GET['edit'] ?? 0);

    $editTask  = $editTaskId > 0
        ? \Apps\Shell\Services\OperatorTaskService::getById($editTaskId)
        : null;
    $allTasks  = \Apps\Shell\Services\OperatorTaskService::getAll(null, $statusFilter);
    $operators = \Apps\Shell\Services\OperatorTaskService::listOperatorUsers();

    $flash = (string)($_SESSION['ops_operator_tasks_ok']  ?? '');
    $error = (string)($_SESSION['ops_operator_tasks_err'] ?? '');
    unset($_SESSION['ops_operator_tasks_ok'], $_SESSION['ops_operator_tasks_err']);

    $view->render('Base::ops/operator_tasks.php', [
        'pageTitle'    => function_exists('t') ? (string)t('ops.operator_tasks.page_title') : 'Operator Tasks',
        'tasks'        => $allTasks['tasks'],
        'operators'    => $operators,
        'editTask'     => $editTask,
        'statusFilter' => $statusFilter,
        'flash'        => $flash,
        'error'        => $error,
        'csrf'         => \App\Core\Auth::csrfToken(),
    ]);
    return null;
});

$router->post('/ops/operator-tasks/save', function () {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        header('Location: /login', true, 302);
        exit;
    }
    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if (!in_array($role, ['platform_admin', 'app_admin'], true)) {
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/ops/operator-tasks');

    $taskId    = (int)($_POST['task_id']    ?? 0);
    $adminId   = (int)(\App\Core\Auth::user()['id'] ?? 0);
    $data      = [
        'title'       => trim((string)($_POST['title']       ?? '')),
        'description' => trim((string)($_POST['description'] ?? '')),
        'assigned_to' => (int)($_POST['assigned_to'] ?? 0),
        'assigned_by' => $adminId,
        'priority'    => trim((string)($_POST['priority']    ?? 'medium')),
        'status'      => trim((string)($_POST['status']      ?? 'open')),
        'due_date'    => trim((string)($_POST['due_date']    ?? '')),
        'due_shift'   => trim((string)($_POST['due_shift']   ?? '')),
    ];

    if ($taskId > 0) {
        $result = \Apps\Shell\Services\OperatorTaskService::update($taskId, $data);
    } else {
        $result = \Apps\Shell\Services\OperatorTaskService::create($data);
    }

    if (!$result['ok']) {
        $_SESSION['ops_operator_tasks_err'] = $result['error'];
        header('Location: /ops/operator-tasks' . ($taskId > 0 ? '?edit=' . $taskId : ''), true, 302);
        exit;
    }

    $_SESSION['ops_operator_tasks_ok'] = 'ops.operator_tasks.flash.saved';
    header('Location: /ops/operator-tasks', true, 302);
    exit;
});

$router->post('/ops/operator-tasks/cancel', function () {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        header('Location: /login', true, 302);
        exit;
    }
    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if (!in_array($role, ['platform_admin', 'app_admin'], true)) {
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/ops/operator-tasks');

    $taskId = (int)($_POST['task_id'] ?? 0);
    $result = \Apps\Shell\Services\OperatorTaskService::cancel($taskId);

    if (!$result['ok']) {
        $_SESSION['ops_operator_tasks_err'] = $result['error'];
    } else {
        $_SESSION['ops_operator_tasks_ok'] = 'ops.operator_tasks.flash.cancelled';
    }
    header('Location: /ops/operator-tasks', true, 302);
    exit;
});

$router->post('/ops/operator-tasks/delete', function () {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        header('Location: /login', true, 302);
        exit;
    }
    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if (!in_array($role, ['platform_admin', 'app_admin'], true)) {
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/ops/operator-tasks');

    $taskId = (int)($_POST['task_id'] ?? 0);
    $result = \Apps\Shell\Services\OperatorTaskService::delete($taskId);

    if (!$result['ok']) {
        $_SESSION['ops_operator_tasks_err'] = $result['error'];
    } else {
        $_SESSION['ops_operator_tasks_ok'] = 'ops.operator_tasks.flash.deleted';
    }
    header('Location: /ops/operator-tasks', true, 302);
    exit;
});

$router->get('/ops/user-dashboard', function () use ($view) {
    \Plugins\Base\Controllers\RoleDashboardsController::renderUserDashboardBoard($view);
    return null;
});

$router->get('/ops/dashboard-assignments', function () {
    header('Location: /ops/access-control', true, 302);
    return null;
});

$router->get('/ops/navigation-tree', function () use ($view, $c) {
    \Plugins\Base\Controllers\RoleDashboardsController::renderNavigationTree($view, $c);
    return null;
});

$router->post('/ops/access-control/save', function () {
    \Plugins\Base\Controllers\RoleDashboardsController::saveAssignment($_POST);
    return null;
});

$router->post('/ops/access-control/save-access', function () {
    \Plugins\Base\Controllers\RoleDashboardsController::saveAccessGovernance($_POST);
    return null;
});

$router->post('/ops/access-control/save-experience', function () {
    \Plugins\Base\Controllers\RoleDashboardsController::saveExperienceLayout($_POST);
    return null;
});

$router->post('/ops/access-control/app-role/save', function () {
    \Plugins\Base\Controllers\RoleDashboardsController::saveAppRole($_POST);
    return null;
});

$router->post('/ops/access-control/app-role/delete', function () {
    \Plugins\Base\Controllers\RoleDashboardsController::deleteAppRole($_POST);
    return null;
});

$router->post('/ops/access-control/workspace-profile-pin', function () {
    \Plugins\Base\Controllers\RoleDashboardsController::saveWorkspaceProfilePin($_POST);
    return null;
});

$router->get('/ops/workspace-profiles', function () use ($view) {
    \Plugins\Base\Controllers\RoleDashboardsController::renderWorkspaceProfiles($view);
    return null;
});

$router->get('/ops/workspace-profiles/detail', function () use ($view) {
    \Plugins\Base\Controllers\RoleDashboardsController::renderWorkspaceProfileDetail($view);
    return null;
});

$router->post('/ops/workspace-profiles/save', function () {
    \Plugins\Base\Controllers\RoleDashboardsController::saveWorkspaceProfile($_POST);
    return null;
});

$router->post('/ops/workspace-profiles/delete', function () {
    \Plugins\Base\Controllers\RoleDashboardsController::deleteWorkspaceProfile($_POST);
    return null;
});

$router->post('/ops/dashboard-assignments/save', function () {
    \Plugins\Base\Controllers\RoleDashboardsController::saveAssignment($_POST);
    return null;
});

$router->post('/ops/access-control/send-message', function () {
    \Plugins\Base\Controllers\RoleDashboardsController::sendAdminCommunication($_POST);
    return null;
});

$router->post('/ops/notifications/send-message', function () {
    \Plugins\Base\Controllers\RoleDashboardsController::sendAdminCommunication($_POST);
    return null;
});

$router->post('/admin/apps/send-message', function () {
    \Plugins\Base\Controllers\RoleDashboardsController::sendAdminCommunication($_POST);
    return null;
});

$router->post('/ops/dashboard-assignments/send-message', function () {
    \Plugins\Base\Controllers\RoleDashboardsController::sendAdminCommunication($_POST);
    return null;
});

$router->post('/ops/platform-mode-switch', function () {
    \App\Core\Auth::bootSession();
    \App\Core\Auth::requireAdmin();

    $decision = \Apps\Platform\Services\PlatformModeSwitchDecisionService::decide(
        $_POST,
        (string)($_SESSION['csrf'] ?? ''),
        \App\Services\PlatformModeService::isModeSwitchLocked()
    );
    $returnTo = (string)$decision['return_to'];
    if (($decision['write_permitted'] ?? false) !== true) {
        header('Location: ' . $returnTo . '?mode_result=' . urlencode((string)$decision['result']), true, 302);
        exit;
    }

    try {
        \App\Services\PlatformModeService::setMode((string)$decision['mode']);
        header('Location: ' . $returnTo . '?mode_result=updated', true, 302);
    } catch (\Throwable) {
        header('Location: ' . $returnTo . '?mode_result=failed', true, 302);
    }
    exit;
});

$router->get('/ops/platform-mode-switch', function () {
    \App\Core\Auth::bootSession();
    \App\Core\Auth::requireAdmin();
    header('Location: /admin/system-tools/platform-mode?mode_result=method_not_allowed', true, 302);
    exit;
});

$router->post('/ops/access-control/user/create', function () {
    \Plugins\Base\Controllers\RoleDashboardsController::createUser($_POST);
    return null;
});

$router->post('/ops/dashboard-assignments/user/create', function () {
    \Plugins\Base\Controllers\RoleDashboardsController::createUser($_POST);
    return null;
});

$router->post('/ops/user-control/user/create', function () {
    \Plugins\Base\Controllers\RoleDashboardsController::createUser($_POST);
    return null;
});

$router->post('/ops/access-control/user/update', function () {
    \Plugins\Base\Controllers\RoleDashboardsController::updateBasicUser($_POST);
    return null;
});

$router->post('/ops/dashboard-assignments/user/update', function () {
    \Plugins\Base\Controllers\RoleDashboardsController::updateBasicUser($_POST);
    return null;
});

$router->post('/ops/access-control/user/status', function () {
    \Plugins\Base\Controllers\RoleDashboardsController::setUserStatus($_POST);
    return null;
});

$router->post('/ops/user-control/user/recovery-link', function () {
    \Plugins\Base\Controllers\RoleDashboardsController::sendUserRecoveryLink($_POST);
    return null;
});

$router->post('/ops/user-control/user/setup-link', function () {
    \Plugins\Base\Controllers\RoleDashboardsController::sendUserSetupLink($_POST);
    return null;
});

$router->post('/ops/dashboard-assignments/user/status', function () {
    \Plugins\Base\Controllers\RoleDashboardsController::setUserStatus($_POST);
    return null;
});

$router->post('/ops/access-control/normalize', function () {
    \Plugins\Base\Controllers\RoleDashboardsController::normalizeAssignments();
    return null;
});

$router->post('/ops/dashboard-assignments/normalize', function () {
    \Plugins\Base\Controllers\RoleDashboardsController::normalizeAssignments();
    return null;
});

// ------------------------------------------------------
// Admin Builder (Base Schema Governance) + App Tools
// Route registration owned by Platform; controllers remain in Base/AdminTools plugins.
// ------------------------------------------------------
$router->get('/admin/base', function() use ($view) {
    BaseSchemaBuilderService::requireSystemBuilder();
    BaseSchemaBuilderService::ensureTables();

    $modules = \App\Core\DB::fetchAll(
        'SELECT m.*, COALESCE(f.total_fields, 0) AS total_fields, COALESCE(f.active_fields, 0) AS active_fields
         FROM base_module_registry m
         LEFT JOIN (
            SELECT module_key,
                   COUNT(*) AS total_fields,
                   SUM(CASE WHEN status = \'active\' THEN 1 ELSE 0 END) AS active_fields
            FROM base_field_registry
            GROUP BY module_key
         ) f ON f.module_key = m.module_key
         ORDER BY m.label ASC'
    );

    $moduleKey = trim((string)($_GET['module'] ?? 'products'));
    $module = \App\Core\DB::fetchOne('SELECT * FROM base_module_registry WHERE module_key=? LIMIT 1', [$moduleKey]);
    if (!$module) {
        $module = $modules[0] ?? null;
        $moduleKey = (string)($module['module_key'] ?? '');
    }

    $fields = [];
    $dbColumns = [];
    $editingField = null;
    if ($module) {
        $fields = \App\Core\DB::fetchAll('SELECT * FROM base_field_registry WHERE module_key=? ORDER BY sort_order ASC, id ASC', [$moduleKey]);
        $editFieldId = (int)($_GET['field_id'] ?? 0);
        if ($editFieldId > 0) {
            $editingField = \App\Core\DB::fetchOne('SELECT * FROM base_field_registry WHERE id=? AND module_key=? LIMIT 1', [$editFieldId, $moduleKey]);
        }

        $tableName = (string)($module['table_name'] ?? '');
        if ($tableName !== '') {
            $quotedTable = '`' . str_replace('`', '``', $tableName) . '`';
            $dbColumns = \App\Core\DB::fetchAll("SHOW COLUMNS FROM {$quotedTable}");
        }
    }

    $auditRows = \App\Core\DB::fetchAll('SELECT * FROM base_schema_audit_log ORDER BY id DESC LIMIT 80');

    $dbTables = BaseSchemaBuilderService::tableList();
    $registeredByTable = [];
    $syncPlans = [];
    foreach ($modules as $m) {
        $registeredByTable[(string)($m['table_name'] ?? '')] = true;
        $syncPlans[(string)($m['module_key'] ?? '')] = BaseSchemaBuilderService::syncPlanForModule($m);
    }

    $unregisteredTables = [];
    foreach ($dbTables as $tbl) {
        if (!isset($registeredByTable[$tbl])) {
            $unregisteredTables[] = $tbl;
        }
    }

    $view->render('Base::admin/base_builder.php', [
        'pageTitle' => 'Base',
        'modules' => $modules,
        'module' => $module,
        'fields' => $fields,
        'editingField' => $editingField,
        'dbColumns' => $dbColumns,
        'syncPlans' => $syncPlans,
        'allDbTables' => $dbTables,
        'unregisteredTables' => $unregisteredTables,
        'auditRows' => $auditRows,
        'ok' => (string)($_GET['ok'] ?? ''),
        'err' => (string)($_GET['err'] ?? ''),
    ]);
    return null;
});

$router->post('/admin/base/modules/register', function() {
    BaseSchemaBuilderService::requireSystemBuilder();
    \App\Core\Auth::bootSession();
    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    BaseSchemaBuilderService::ensureTables();

    $tables = $_POST['tables'] ?? [];
    $tables = is_array($tables) ? $tables : [];
    $tables = array_values(array_unique(array_filter(array_map(static fn($v) => trim((string)$v), $tables), static fn($v) => $v !== '')));
    if (empty($tables)) {
        header('Location: /admin/base?err=' . urlencode('Select at least one table to register.'));
        exit;
    }

    $available = array_flip(BaseSchemaBuilderService::tableList());
    $added = 0;
    foreach ($tables as $tableName) {
        if (!isset($available[$tableName])) {
            continue;
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
            continue;
        }

        $exists = \App\Core\DB::fetchOne('SELECT id FROM base_module_registry WHERE table_name=? LIMIT 1', [$tableName]);
        if ($exists) {
            continue;
        }

        $baseKey = BaseSchemaBuilderService::makeModuleKey($tableName);
        $moduleKey = $baseKey;
        $i = 2;
        while (\App\Core\DB::fetchOne('SELECT id FROM base_module_registry WHERE module_key=? LIMIT 1', [$moduleKey])) {
            $moduleKey = $baseKey . '_' . $i;
            $i++;
        }

        \App\Core\DB::query(
            'INSERT INTO base_module_registry (module_key, label, table_name, is_active) VALUES (?,?,?,1)',
            [$moduleKey, BaseSchemaBuilderService::makeLabel($tableName), $tableName]
        );
        BaseSchemaBuilderService::audit('module_registered', $moduleKey, null, ['table' => $tableName]);
        $added++;
    }

    header('Location: /admin/base?ok=' . urlencode('Registered modules: ' . $added));
    exit;
});

$router->post('/admin/base/field/save', function() {
    BaseSchemaBuilderService::requireSystemBuilder();
    \App\Core\Auth::bootSession();
    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    BaseSchemaBuilderService::ensureTables();

    try {
        $moduleKey = trim((string)($_POST['module_key'] ?? ''));
        $fieldId = (int)($_POST['field_id'] ?? 0);
        $fieldKey = trim((string)($_POST['field_key'] ?? ''));
        $columnName = trim((string)($_POST['column_name'] ?? ''));
        $label = trim((string)($_POST['label'] ?? ''));
        $dataType = strtolower(trim((string)($_POST['data_type'] ?? 'varchar')));
        $maxLength = (int)($_POST['max_length'] ?? 0);
        $precision = (int)($_POST['precision_value'] ?? 0);
        $scale = (int)($_POST['scale_value'] ?? 0);
        $isRequired = (int)((string)($_POST['is_required'] ?? '0') === '1' ? 1 : 0);
        $isVisible = (int)((string)($_POST['is_visible'] ?? '1') === '1' ? 1 : 0);
        $defaultValue = trim((string)($_POST['default_value'] ?? ''));
        $optionsText = trim((string)($_POST['options_text'] ?? ''));
        $sortOrder = max(1, (int)($_POST['sort_order'] ?? 100));

        if ($moduleKey === '' || $fieldKey === '' || $columnName === '' || $label === '') {
            throw new \RuntimeException('Module, field key, column name, and label are required.');
        }
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $columnName)) {
            throw new \RuntimeException('Column name must be a valid SQL identifier.');
        }
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_\.:-]*$/', $fieldKey)) {
            throw new \RuntimeException('Field key contains invalid characters.');
        }

        $allowedTypes = ['varchar', 'text', 'int', 'integer', 'bigint', 'decimal', 'date', 'datetime', 'tinyint', 'boolean', 'bool', 'json'];
        if (!in_array($dataType, $allowedTypes, true)) {
            throw new \RuntimeException('Unsupported data type.');
        }

        $module = \App\Core\DB::fetchOne('SELECT module_key FROM base_module_registry WHERE module_key=? LIMIT 1', [$moduleKey]);
        if (!$module) {
            throw new \RuntimeException('Unknown module key.');
        }

        $db = \App\Core\DB::conn();
        $db->begin_transaction();
        if ($fieldId > 0) {
            \App\Core\DB::query(
                'UPDATE base_field_registry
                 SET field_key=?, column_name=?, label=?, data_type=?, max_length=?, precision_value=?, scale_value=?,
                     is_required=?, default_value=?, options_text=?, is_visible=?, sort_order=?, updated_by=?, updated_at=NOW()
                 WHERE id=? LIMIT 1',
                [
                    $fieldKey,
                    $columnName,
                    $label,
                    $dataType,
                    $maxLength > 0 ? $maxLength : null,
                    $precision > 0 ? $precision : null,
                    $scale > 0 ? $scale : null,
                    $isRequired,
                    $defaultValue !== '' ? $defaultValue : null,
                    $optionsText !== '' ? $optionsText : null,
                    $isVisible,
                    $sortOrder,
                    BaseSchemaBuilderService::currentUserEmail(),
                    $fieldId,
                ]
            );
            BaseSchemaBuilderService::audit('field_updated', $moduleKey, $fieldId, [
                'field_key' => $fieldKey,
                'column_name' => $columnName,
                'label' => $label,
                'data_type' => $dataType,
                'is_required' => $isRequired,
                'is_visible' => $isVisible,
            ]);
        } else {
            \App\Core\DB::query(
                'INSERT INTO base_field_registry
                 (module_key, field_key, column_name, label, data_type, max_length, precision_value, scale_value,
                  is_required, default_value, options_text, is_visible, sort_order, status, created_by, updated_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $moduleKey,
                    $fieldKey,
                    $columnName,
                    $label,
                    $dataType,
                    $maxLength > 0 ? $maxLength : null,
                    $precision > 0 ? $precision : null,
                    $scale > 0 ? $scale : null,
                    $isRequired,
                    $defaultValue !== '' ? $defaultValue : null,
                    $optionsText !== '' ? $optionsText : null,
                    $isVisible,
                    $sortOrder,
                    'active',
                    BaseSchemaBuilderService::currentUserEmail(),
                    BaseSchemaBuilderService::currentUserEmail(),
                ]
            );
            $newId = (int)$db->insert_id;
            BaseSchemaBuilderService::audit('field_added', $moduleKey, $newId, [
                'field_key' => $fieldKey,
                'column_name' => $columnName,
                'label' => $label,
                'data_type' => $dataType,
                'is_required' => $isRequired,
                'is_visible' => $isVisible,
            ]);
        }
        $db->commit();
        header('Location: /admin/base?module=' . urlencode($moduleKey) . '&ok=' . urlencode('Field metadata saved.'));
        exit;
    } catch (\Throwable $e) {
        try {
            \App\Core\DB::conn()->rollback();
        } catch (\Throwable $ignored) {
        }
        header('Location: /admin/base?module=' . urlencode((string)($_POST['module_key'] ?? '')) . '&err=' . urlencode($e->getMessage()));
        exit;
    }
});

$router->post('/admin/base/field/disable', function() {
    BaseSchemaBuilderService::requireSystemBuilder();
    \App\Core\Auth::bootSession();
    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    BaseSchemaBuilderService::ensureTables();

    $fieldId = (int)($_POST['field_id'] ?? 0);
    $moduleKey = trim((string)($_POST['module_key'] ?? ''));
    if ($fieldId <= 0 || $moduleKey === '') {
        header('Location: /admin/base?module=' . urlencode($moduleKey) . '&err=' . urlencode('Invalid field selection.'));
        exit;
    }

    \App\Core\DB::query(
        "UPDATE base_field_registry SET status='disabled', is_visible=0, updated_by=?, updated_at=NOW() WHERE id=? AND module_key=? LIMIT 1",
        [BaseSchemaBuilderService::currentUserEmail(), $fieldId, $moduleKey]
    );
    BaseSchemaBuilderService::audit('field_soft_disabled', $moduleKey, $fieldId, ['status' => 'disabled']);

    header('Location: /admin/base?module=' . urlencode($moduleKey) . '&ok=' . urlencode('Field soft-disabled.'));
    exit;
});

$router->post('/admin/base/schema-sync', function() {
    BaseSchemaBuilderService::requireSystemBuilder();
    \App\Core\Auth::bootSession();
    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    BaseSchemaBuilderService::ensureTables();

    $moduleKey = trim((string)($_POST['module_key'] ?? ''));
    $confirm = trim((string)($_POST['confirm_sync'] ?? ''));
    $syncMode = (string)($_POST['sync_mode'] ?? 'apply');
    $isDryRun = $syncMode === 'dry_run';
    if ($moduleKey === '') {
        header('Location: /admin/base?err=' . urlencode('Module key is required for schema sync.'));
        exit;
    }
    if (!$isDryRun && $confirm !== 'SYNC') {
        header('Location: /admin/base?module=' . urlencode($moduleKey) . '&err=' . urlencode('Type SYNC to confirm schema sync.'));
        exit;
    }

    try {
        $module = \App\Core\DB::fetchOne('SELECT * FROM base_module_registry WHERE module_key=? LIMIT 1', [$moduleKey]);
        if (!$module) {
            throw new \RuntimeException('Unknown module key.');
        }

        $plan = BaseSchemaBuilderService::syncPlanForModule($module);
        if (($plan['error'] ?? null) !== null) {
            throw new \RuntimeException((string)$plan['error']);
        }

        $tableName = (string)($plan['table_name'] ?? '');
        $quotedTable = '`' . str_replace('`', '``', $tableName) . '`';
        $applied = 0;
        $appliedColumns = [];

        if ($isDryRun) {
            BaseSchemaBuilderService::audit('schema_sync_dry_run', $moduleKey, null, [
                'table' => $tableName,
                'pending_columns' => array_values(array_map(static fn($r) => (string)($r['column_name'] ?? ''), (array)($plan['pending'] ?? []))),
                'pending_count' => (int)($plan['pending_count'] ?? 0),
            ]);
            header('Location: /admin/base?module=' . urlencode($moduleKey) . '&ok=' . urlencode('Dry run complete. Pending columns: ' . (int)($plan['pending_count'] ?? 0)));
            exit;
        }

        $db = \App\Core\DB::conn();
        $db->begin_transaction();
        foreach ((array)($plan['pending'] ?? []) as $pending) {
            $colName = (string)($pending['column_name'] ?? '');
            $definition = (string)($pending['definition'] ?? '');
            if ($colName === '' || $definition === '') {
                continue;
            }
            $quotedColumn = '`' . str_replace('`', '``', $colName) . '`';
            $sql = 'ALTER TABLE ' . $quotedTable . ' ADD COLUMN ' . $quotedColumn . ' ' . $definition;
            \App\Core\DB::query($sql);
            \App\Core\DB::query('UPDATE base_field_registry SET last_synced_at=NOW(), updated_by=?, updated_at=NOW() WHERE id=? LIMIT 1', [
                BaseSchemaBuilderService::currentUserEmail(),
                (int)($pending['field_id'] ?? 0),
            ]);
            $applied++;
            $appliedColumns[] = $colName;
        }

        BaseSchemaBuilderService::audit('schema_sync', $moduleKey, null, [
            'table' => $tableName,
            'applied_columns' => $appliedColumns,
            'applied_count' => $applied,
        ]);

        $db->commit();
        header('Location: /admin/base?module=' . urlencode($moduleKey) . '&ok=' . urlencode('Schema sync complete. Added columns: ' . $applied));
        exit;
    } catch (\Throwable $e) {
        try {
            \App\Core\DB::conn()->rollback();
        } catch (\Throwable $ignored) {
        }
        header('Location: /admin/base?module=' . urlencode($moduleKey) . '&err=' . urlencode($e->getMessage()));
        exit;
    }
});

$router->post('/admin/base/schema-sync-bulk', function() {
    BaseSchemaBuilderService::requireSystemBuilder();
    \App\Core\Auth::bootSession();
    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    BaseSchemaBuilderService::ensureTables();

    $moduleKeys = $_POST['module_keys'] ?? [];
    $moduleKeys = is_array($moduleKeys) ? $moduleKeys : [];
    $moduleKeys = array_values(array_unique(array_filter(array_map(static fn($v) => trim((string)$v), $moduleKeys), static fn($v) => $v !== '')));
    $syncMode = (string)($_POST['sync_mode'] ?? 'dry_run');
    $isDryRun = $syncMode === 'dry_run';
    $confirm = trim((string)($_POST['confirm_sync'] ?? ''));

    if (empty($moduleKeys)) {
        header('Location: /admin/base?err=' . urlencode('Select at least one module for sync.'));
        exit;
    }
    if (!$isDryRun && $confirm !== 'SYNC') {
        header('Location: /admin/base?err=' . urlencode('Type SYNC to apply selected module sync.'));
        exit;
    }

    $plans = [];
    $pendingTotal = 0;
    foreach ($moduleKeys as $moduleKey) {
        $module = \App\Core\DB::fetchOne('SELECT * FROM base_module_registry WHERE module_key=? LIMIT 1', [$moduleKey]);
        if (!$module) {
            continue;
        }
        $plan = BaseSchemaBuilderService::syncPlanForModule($module);
        $plans[] = $plan;
        $pendingTotal += (int)($plan['pending_count'] ?? 0);
    }

    if ($isDryRun) {
        foreach ($plans as $plan) {
            BaseSchemaBuilderService::audit('schema_sync_dry_run', (string)($plan['module_key'] ?? 'unknown'), null, [
                'table' => (string)($plan['table_name'] ?? ''),
                'pending_columns' => array_values(array_map(static fn($r) => (string)($r['column_name'] ?? ''), (array)($plan['pending'] ?? []))),
                'pending_count' => (int)($plan['pending_count'] ?? 0),
                'bulk' => true,
            ]);
        }
        header('Location: /admin/base?ok=' . urlencode('Bulk dry run complete. Pending columns: ' . $pendingTotal));
        exit;
    }

    $db = \App\Core\DB::conn();
    $db->begin_transaction();
    try {
        $appliedTotal = 0;
        foreach ($plans as $plan) {
            if (($plan['error'] ?? null) !== null) {
                continue;
            }

            $moduleKey = (string)($plan['module_key'] ?? '');
            $tableName = (string)($plan['table_name'] ?? '');
            if ($moduleKey === '' || $tableName === '') {
                continue;
            }
            $quotedTable = '`' . str_replace('`', '``', $tableName) . '`';
            $appliedColumns = [];
            foreach ((array)($plan['pending'] ?? []) as $pending) {
                $colName = (string)($pending['column_name'] ?? '');
                $definition = (string)($pending['definition'] ?? '');
                if ($colName === '' || $definition === '') {
                    continue;
                }

                $quotedColumn = '`' . str_replace('`', '``', $colName) . '`';
                \App\Core\DB::query('ALTER TABLE ' . $quotedTable . ' ADD COLUMN ' . $quotedColumn . ' ' . $definition);
                \App\Core\DB::query('UPDATE base_field_registry SET last_synced_at=NOW(), updated_by=?, updated_at=NOW() WHERE id=? LIMIT 1', [
                    BaseSchemaBuilderService::currentUserEmail(),
                    (int)($pending['field_id'] ?? 0),
                ]);
                $appliedColumns[] = $colName;
                $appliedTotal++;
            }

            BaseSchemaBuilderService::audit('schema_sync', $moduleKey, null, [
                'table' => $tableName,
                'applied_columns' => $appliedColumns,
                'applied_count' => count($appliedColumns),
                'bulk' => true,
            ]);
        }

        $db->commit();
        header('Location: /admin/base?ok=' . urlencode('Bulk sync complete. Added columns: ' . $appliedTotal));
        exit;
    } catch (\Throwable $e) {
        $db->rollback();
        header('Location: /admin/base?err=' . urlencode($e->getMessage()));
        exit;
    }
});

// ------------------------------------------------------
// Admin (protected)
// ------------------------------------------------------
$router->get('/admin/apps', function() use ($view, $c) {
    AdminToolsAccessService::requireAdminToolsAccess();
    \App\Core\Auth::bootSession();
    if (class_exists(\Plugins\AdminTools\Controllers\AdminToolsController::class)) {
        \Plugins\AdminTools\Controllers\AdminToolsController::appsIndex($view, $c);
    }
    return null;
});

$router->get('/tools/apps', function() use ($view, $c) {
    if (!(function_exists('app_dev_tools_enabled') ? app_dev_tools_enabled() : false)) {
        http_response_code(404);
        echo 'Not found';
        exit;
    }
    \App\Core\Auth::bootSession();
    if (class_exists(\Plugins\AdminTools\Controllers\AdminToolsController::class)) {
        \Plugins\AdminTools\Controllers\AdminToolsController::appsIndex($view, $c);
    }
    return null;
});

$router->post('/admin/apps/upload', function() {
    AdminToolsAccessService::requireAdminToolsAccess();
    \App\Core\Auth::bootSession();
    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

    try {
        if (class_exists(\Plugins\AdminTools\Controllers\AdminToolsController::class)) {
            \Plugins\AdminTools\Controllers\AdminToolsController::appsUpload();
        }

    } catch (Throwable $e) {
        http_response_code(400);
        echo "Upload failed: " . htmlspecialchars($e->getMessage());
        echo "<br><a href='/admin/apps'>Back</a>";
        return null;
    }
});

$router->post('/tools/apps/upload', function() {
    if (!(function_exists('app_dev_tools_enabled') ? app_dev_tools_enabled() : false)) {
        http_response_code(404);
        echo 'Not found';
        exit;
    }
    \App\Core\Auth::bootSession();
    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

    try {
        if (class_exists(\Plugins\AdminTools\Controllers\AdminToolsController::class)) {
            \Plugins\AdminTools\Controllers\AdminToolsController::appsUpload();
        }

    } catch (Throwable $e) {
        http_response_code(400);
        echo "Upload failed: " . htmlspecialchars($e->getMessage());
        echo "<br><a href='/tools/apps'>Back</a>";
        return null;
    }
});

$router->post('/admin/apps/package/apply', function() use ($c) {
    AdminToolsAccessService::requireAdminToolsAccess();
    \App\Core\Auth::bootSession();
    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

    try {
        if (class_exists(\Plugins\AdminTools\Controllers\AdminToolsController::class)) {
            \Plugins\AdminTools\Controllers\AdminToolsController::appsPackageApply($c);
        }

    } catch (Throwable $e) {
        http_response_code(400);
        echo "Apply failed: " . htmlspecialchars($e->getMessage());
        echo "<br><a href='/admin/apps'>Back</a>";
        return null;
    }
});

$router->post('/tools/apps/package/apply', function() use ($c) {
    if (!(function_exists('app_dev_tools_enabled') ? app_dev_tools_enabled() : false)) {
        http_response_code(404);
        echo 'Not found';
        exit;
    }
    \App\Core\Auth::bootSession();
    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

    try {
        if (class_exists(\Plugins\AdminTools\Controllers\AdminToolsController::class)) {
            \Plugins\AdminTools\Controllers\AdminToolsController::appsPackageApply($c);
        }

    } catch (Throwable $e) {
        http_response_code(400);
        echo "Apply failed: " . htmlspecialchars($e->getMessage());
        echo "<br><a href='/tools/apps'>Back</a>";
        return null;
    }
});

$router->get('/admin/apps/deps', function() use ($view, $c) {
    AdminToolsAccessService::requireAdminToolsAccess();
    if (class_exists(\Plugins\AdminTools\Controllers\AdminToolsController::class)) {
        \Plugins\AdminTools\Controllers\AdminToolsController::appsDeps($view, $c);
    }
    return null;
});

$router->get('/tools/apps/deps', function() use ($view, $c) {
    if (!(function_exists('app_dev_tools_enabled') ? app_dev_tools_enabled() : false)) {
        http_response_code(404);
        echo 'Not found';
        exit;
    }
    if (class_exists(\Plugins\AdminTools\Controllers\AdminToolsController::class)) {
        \Plugins\AdminTools\Controllers\AdminToolsController::appsDeps($view, $c);
    }
    return null;
});

$router->get('/admin/apps/export', function() use ($c) {
    AdminToolsAccessService::requireAdminToolsAccess();
    \App\Core\Auth::bootSession();
    if (class_exists(\Plugins\AdminTools\Controllers\AdminToolsController::class) && method_exists(\Plugins\AdminTools\Controllers\AdminToolsController::class, 'appsExport')) {
        \Plugins\AdminTools\Controllers\AdminToolsController::appsExport($c);
    }
    return null;
});

$router->get('/tools/apps/export', function() use ($c) {
    if (!(function_exists('app_dev_tools_enabled') ? app_dev_tools_enabled() : false)) {
        http_response_code(404);
        echo 'Not found';
        exit;
    }
    \App\Core\Auth::bootSession();
    if (class_exists(\Plugins\AdminTools\Controllers\AdminToolsController::class) && method_exists(\Plugins\AdminTools\Controllers\AdminToolsController::class, 'appsExport')) {
        \Plugins\AdminTools\Controllers\AdminToolsController::appsExport($c);
    }
    return null;
});

$router->post('/admin/apps/action', function() use ($c) {
    AdminToolsAccessService::requireAdminToolsAccess();
    \App\Core\Auth::bootSession();
    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

    try {
        if (class_exists(\Plugins\AdminTools\Controllers\AdminToolsController::class)) {
            \Plugins\AdminTools\Controllers\AdminToolsController::appsAction($c);
        }

    } catch (Throwable $e) {
        $msg = $e->getMessage();
        $appKey = trim((string)($_POST['app_key'] ?? ''));
        $name = (string)($_POST['name'] ?? '');

        if ($appKey !== '') {
            header('Location: /admin/apps?' . http_build_query([
                'bundle_err' => $msg,
                'app_key' => $appKey,
            ]));
            exit;
        }

        if (str_starts_with($msg, 'DEPENDENCY_BLOCK:')) {
            header("Location: /admin/apps/deps?name=" . urlencode($name));
            exit;
        }

        if ($name !== '') {
            header('Location: /admin/apps?' . http_build_query([
                'plugin_err' => $msg,
                'name' => $name,
            ]));
            exit;
        }

        http_response_code(400);
        echo "Action failed: " . htmlspecialchars($msg);
        echo "<br><a href='/admin/apps'>Back</a>";
        return null;
    }
});

$router->post('/tools/apps/action', function() use ($c) {
    if (!(function_exists('app_dev_tools_enabled') ? app_dev_tools_enabled() : false)) {
        http_response_code(404);
        echo 'Not found';
        exit;
    }
    \App\Core\Auth::bootSession();
    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

    try {
        if (class_exists(\Plugins\AdminTools\Controllers\AdminToolsController::class)) {
            \Plugins\AdminTools\Controllers\AdminToolsController::appsAction($c);
        }

    } catch (Throwable $e) {
        $msg = $e->getMessage();
        $appKey = trim((string)($_POST['app_key'] ?? ''));
        $name = (string)($_POST['name'] ?? '');

        if ($appKey !== '') {
            header('Location: /tools/apps?' . http_build_query([
                'bundle_err' => $msg,
                'app_key' => $appKey,
            ]));
            exit;
        }

        if (str_starts_with($msg, 'DEPENDENCY_BLOCK:')) {
            header("Location: /tools/apps/deps?name=" . urlencode($name));
            exit;
        }

        if ($name !== '') {
            header('Location: /tools/apps?' . http_build_query([
                'plugin_err' => $msg,
                'name' => $name,
            ]));
            exit;
        }

        http_response_code(400);
        echo "Action failed: " . htmlspecialchars($msg);
        echo "<br><a href='/tools/apps'>Back</a>";
        return null;
    }
});

// Operational dashboard route - role-based task aggregation and prioritization
$router->get('/ops/dashboard', function () use ($view) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        \App\Core\Auth::rememberIntendedUrl('/ops/dashboard');
        header('Location: /login', true, 302);
        exit;
    }
    AdminToolsAccessService::requireAdminToolsAccess();

    $user = is_array(\App\Core\Auth::user() ?? null) ? \App\Core\Auth::user() : [];
    $userId = (string)($user['id'] ?? '');
    $userRole = (string)($user['account_type'] ?? 'platform_admin');

    $dashboardType = class_exists(GuiStudioService::class)
        ? GuiStudioService::mapUserRoleToDashboardType($userRole)
        : 'admin';
    $dashboardData = class_exists(GuiStudioService::class)
        ? GuiStudioService::generatedDashboardData($dashboardType, $user)
        : ['tasks' => [], 'kpis' => [], 'summary' => []];

    $view->render('Base::admin/operational_dashboard.php', [
        'pageTitle' => (string)t('dashboard.operational.title'),
        'dashboardType' => $dashboardType,
        'dashboardData' => $dashboardData,
        'user' => $user,
        'csrf' => \App\Core\Auth::csrfToken(),
    ]);

    return null;
});

/**
 * Next Best Action route - returns next best task for user with reasoning
 */
$router->get('/ops/next-action', function () use ($view) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        \App\Core\Auth::rememberIntendedUrl('/ops/next-action');
        header('Location: /login', true, 302);
        exit;
    }
    AdminToolsAccessService::requireAdminToolsAccess();

    $user = is_array(\App\Core\Auth::user() ?? null) ? \App\Core\Auth::user() : [];
    $userId = (string)($user['id'] ?? '');
    $userRole = (string)($user['account_type'] ?? 'platform_admin');

    $dashboardType = class_exists(GuiStudioService::class)
        ? GuiStudioService::mapUserRoleToDashboardType($userRole)
        : 'admin';
    $dashboardData = class_exists(GuiStudioService::class)
        ? GuiStudioService::generatedDashboardData($dashboardType, $user)
        : ['tasks' => []];
    $tasks = $dashboardData['tasks'] ?? [];

    $nextActionData = class_exists(GuiStudioService::class)
        ? GuiStudioService::computeNextBestAction($dashboardType, $tasks)
        : ['next_action' => null, 'reason' => 'studio_unavailable'];

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'user_id' => $userId,
        'dashboard_type' => $dashboardType,
        'has_next_action' => (bool)($nextActionData['has_next_action'] ?? false),
        'next_action' => $nextActionData['next_action'] ?? null,
        'reason' => $nextActionData['reason'] ?? 'no_tasks',
        'explanation' => $nextActionData['explanation'] ?? '',
        'blocking_explanation' => $nextActionData['blocking_explanation'] ?? null,
        'blocked_downstream_count' => (int)($nextActionData['blocked_downstream_count'] ?? 0),
        'generated_at' => gmdate('c'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return null;
});

$router->get('/admin/routes', function() use ($view, $c) {
    AdminToolsAccessService::requireAdminToolsAccess();
    if (class_exists(\Plugins\AdminTools\Controllers\AdminToolsController::class)) {
        \Plugins\AdminTools\Controllers\AdminToolsController::routesIndex($view, $c);
    }
    return null;
});

/**
 * Generate explanation text for why a task is the next best action.
 * @param array<string,mixed> $nextAction
 * @return string
 */
