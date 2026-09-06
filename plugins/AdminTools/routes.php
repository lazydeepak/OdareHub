<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Services\MailConfigService;
use App\Services\MailService;
use App\Services\PasswordPolicyService;
use Plugins\AdminTools\Controllers\AdminToolsController;

require_once __DIR__ . '/Controllers/AdminToolsController.php';

$routePolicies = [
    'workspace' => [
        'path' => '/admin/system-tools',
        'permissions' => ['admin.tools.access'],
        'mode' => 'read',
    ],
    'apps_redirect' => [
        'path' => '/admin/system-tools/apps',
        'permissions' => ['admin.tools.access'],
        'mode' => 'read',
    ],
    'email_settings_view' => [
        'path' => '/admin/system-tools/email-settings',
        'permissions' => ['admin.tools.access'],
        'mode' => 'read',
    ],
    'email_settings_save' => [
        'path' => '/admin/system-tools/email-settings',
        'permissions' => ['admin.tools.access'],
        'mode' => 'write',
    ],
    'email_settings_test' => [
        'path' => '/admin/system-tools/email-settings/test',
        'permissions' => ['admin.tools.access'],
        'mode' => 'write',
    ],
    'password_policy_save' => [
        'path' => '/admin/system-tools/password-policy',
        'permissions' => ['admin.tools.access'],
        'mode' => 'write',
    ],
    'routes_redirect' => [
        'path' => '/admin/system-tools/routes',
        'permissions' => ['admin.tools.access'],
        'mode' => 'read',
    ],
    'data_control_workspace' => [
        'path' => '/admin/system-tools/data-control',
        'permissions' => ['data_control.viewer', 'admin.tools.access'],
        'mode' => 'read',
    ],
    'data_control_row_browser' => [
        'path' => '/admin/system-tools/data-control/browse',
        'permissions' => ['data_control.viewer', 'admin.tools.access'],
        'mode' => 'read',
    ],
    'data_control_mutation_preview' => [
        'path' => '/admin/system-tools/data-control/preview-mutation',
        'permissions' => ['data_control.editor', 'admin.tools.access'],
        'mode' => 'read',
    ],
    'data_control_approvals_list' => [
        'path' => '/admin/system-tools/data-control/approvals',
        'permissions' => ['data_control.governor', 'admin.tools.access'],
        'mode' => 'read',
    ],
    'data_control_approval_detail' => [
        'path' => '/admin/system-tools/data-control/approval-detail',
        'permissions' => ['data_control.governor', 'admin.tools.access'],
        'mode' => 'read',
    ],
    'data_control_submit_for_approval' => [
        'path' => '/admin/system-tools/data-control/submit-approval',
        'permissions' => ['data_control.editor', 'admin.tools.access'],
        'mode' => 'write',
    ],
    'data_control_approve_mutation' => [
        'path' => '/admin/system-tools/data-control/approve-mutation',
        'permissions' => ['data_control.governor', 'admin.tools.access'],
        'mode' => 'write',
    ],
    'data_control_execute_mutation' => [
        'path' => '/admin/system-tools/data-control/execute-mutation',
        'permissions' => ['data_control.governor', 'admin.tools.access'],
        'mode' => 'write',
    ],
    'data_control_audit_trail' => [
        'path' => '/admin/system-tools/data-control/audit-trail',
        'permissions' => ['data_control.governor', 'admin.tools.access'],
        'mode' => 'read',
    ],
    'entity_runtime' => [
        'path' => '/admin/system-tools/entity-runtime',
        'permissions' => ['admin.tools.access'],
        'mode' => 'read',
    ],
    'my_work_runtime' => [
        'path' => '/admin/system-tools/my-work-runtime',
        'permissions' => ['admin.tools.access'],
        'mode' => 'read',
    ],
    'stage_inspector' => [
        'path' => '/admin/system-tools/stage-inspector',
        'permissions' => ['admin.tools.access'],
        'mode' => 'read',
    ],
    'runtime_report' => [
        'path' => '/admin/system-tools/runtime-report',
        'permissions' => ['admin.tools.access'],
        'mode' => 'read',
    ],
    'module_health' => [
        'path' => '/admin/system-tools/module-health',
        'permissions' => ['admin.tools.access'],
        'mode' => 'read',
    ],
    'export_audit' => [
        'path' => '/admin/system-tools/export-audit',
        'permissions' => ['admin.tools.access'],
        'mode' => 'read',
    ],
    'app_management' => [
        'path' => '/admin/system-tools/app-management',
        'permissions' => ['admin.tools.access'],
        'mode' => 'read',
    ],
    'resilience_map' => [
        'path' => '/admin/system-tools/resilience',
        'permissions' => ['admin.tools.access'],
        'mode' => 'read',
    ],
    'app_export_app' => [
        'path' => '/admin/system-tools/app-management/export-app',
        'permissions' => ['admin.tools.access'],
        'mode' => 'write',
    ],
    'app_export_module' => [
        'path' => '/admin/system-tools/app-management/export-module',
        'permissions' => ['admin.tools.access'],
        'mode' => 'write',
    ],
    'app_download' => [
        'path' => '/admin/system-tools/app-management/download',
        'permissions' => ['admin.tools.access'],
        'mode' => 'read',
    ],
    'platform_admin_links' => [
        'path' => '/admin/platform-admin-links',
        'permissions' => ['admin.tools.access'],
        'mode' => 'read',
    ],
];

$requireSystemToolsAccess = static function (): void {
    if (function_exists('base_require_admin_tools_access')) {
        base_require_admin_tools_access();
    } else {
        Auth::requireAdmin();
    }
    Auth::bootSession();
};

$requirePolicyAccess = static function (string $policyKey) use ($routePolicies, $requireSystemToolsAccess): void {
    $policy = $routePolicies[$policyKey] ?? null;
    if (!is_array($policy)) {
        $requireSystemToolsAccess();
        return;
    }

    $permissions = array_values(array_filter((array)($policy['permissions'] ?? []), static function ($perm): bool {
        return is_string($perm) && $perm !== '';
    }));

    if (function_exists('acl_require_any') && $permissions !== []) {
        acl_require_any($permissions, (string)($policy['path'] ?? null));
        Auth::bootSession();
        return;
    }

    $requireSystemToolsAccess();
};

$redirect = static function (string $url): void {
    header('Location: ' . $url);
    exit;
};

$renderToolView = static function (string $policyKey, callable $renderer) use ($view, $requirePolicyAccess) {
    $requirePolicyAccess($policyKey);
    $renderer($view);
    return null;
};

$router->get('/admin/system-tools', function() use ($renderToolView) {
    return $renderToolView('workspace', static function ($v): void {
        AdminToolsController::systemWorkspace($v);
    });
});

$router->get('/admin/system-tools/apps', function() use ($requirePolicyAccess, $redirect) {
    $requirePolicyAccess('apps_redirect');
    $redirect('/admin/apps');
});

$router->get('/admin/system-tools/email-settings', function() use ($renderToolView) {
    return $renderToolView('email_settings_view', static function ($v): void {
        AdminToolsController::emailSettings($v);
    });
});

$router->post('/admin/system-tools/email-settings', function() use ($requirePolicyAccess, $redirect) {
    $requirePolicyAccess('email_settings_save');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

    try {
        $service = new MailConfigService();
        $service->save($_POST);
        $redirect('/admin/system-tools/email-settings?msg=' . urlencode('Email settings saved.'));
    } catch (\Throwable $e) {
        $redirect('/admin/system-tools/email-settings?err=' . urlencode($e->getMessage()));
    }
});

$router->post('/admin/system-tools/email-settings/test', function() use ($requirePolicyAccess, $redirect) {
    $requirePolicyAccess('email_settings_test');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

    $to = strtolower(trim((string)($_POST['test_email_to'] ?? '')));
    try {
        (new MailService())->sendTest($to);
        $redirect('/admin/system-tools/email-settings?msg=' . urlencode('Test email sent.') . '&test_to=' . urlencode($to));
    } catch (\Throwable $e) {
        $redirect('/admin/system-tools/email-settings?err=' . urlencode($e->getMessage()) . '&test_to=' . urlencode($to));
    }
});

$router->post('/admin/system-tools/password-policy', function() use ($requirePolicyAccess, $redirect) {
    $requirePolicyAccess('password_policy_save');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

    try {
        (new PasswordPolicyService())->save($_POST);
        $redirect('/admin/system-tools/email-settings?msg=' . urlencode('Password policy saved.'));
    } catch (\Throwable $e) {
        $redirect('/admin/system-tools/email-settings?err=' . urlencode($e->getMessage()));
    }
});

$router->get('/admin/system-tools/routes', function() use ($requirePolicyAccess, $redirect) {
    $requirePolicyAccess('routes_redirect');
    $redirect('/admin/routes');
});

$router->get('/admin/system-tools/data-control', function() use ($renderToolView) {
    return $renderToolView('data_control_workspace', static function ($v): void {
        AdminToolsController::dataControlWorkspace($v);
    });
});

$router->get('/admin/system-tools/data-control/browse', function() use ($renderToolView) {
    return $renderToolView('data_control_row_browser', static function ($v): void {
        AdminToolsController::rowBrowser($v);
    });
});

$router->get('/admin/system-tools/data-control/preview-mutation', function() use ($renderToolView) {
    return $renderToolView('data_control_mutation_preview', static function ($v): void {
        AdminToolsController::previewMutation($v);
    });
});

$router->get('/admin/system-tools/data-control/approvals', function() use ($renderToolView) {
    return $renderToolView('data_control_approvals_list', static function ($v): void {
        AdminToolsController::approvalsQueue($v);
    });
});

$router->get('/admin/system-tools/data-control/approval-detail', function() use ($renderToolView) {
    return $renderToolView('data_control_approval_detail', static function ($v): void {
        AdminToolsController::approvalDetail($v);
    });
});

$router->post('/admin/system-tools/data-control/submit-approval', function() use ($requirePolicyAccess, $redirect) {
    $requirePolicyAccess('data_control_submit_for_approval');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    
    AdminToolsController::submitForApproval();
    $redirect('/admin/system-tools/data-control?msg=' . urlencode(t('system_tools.data_control.approval_submitted')));
});

$router->post('/admin/system-tools/data-control/approve-mutation', function() use ($requirePolicyAccess, $redirect) {
    $requirePolicyAccess('data_control_approve_mutation');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    
    AdminToolsController::approveMutation();
    $redirect('/admin/system-tools/data-control/approvals?msg=' . urlencode(t('system_tools.data_control.mutation_approved')));
});

$router->post('/admin/system-tools/data-control/execute-mutation', function() use ($requirePolicyAccess, $redirect) {
    $requirePolicyAccess('data_control_execute_mutation');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    
    AdminToolsController::executeMutation();
    $redirect('/admin/system-tools/data-control/approvals?msg=' . urlencode(t('system_tools.data_control.mutation_executed')));
});

$router->get('/admin/system-tools/data-control/audit-trail', function() use ($renderToolView) {
    return $renderToolView('data_control_audit_trail', static function ($v): void {
        AdminToolsController::auditTrail($v);
    });
});

$router->get('/admin/system-tools/entity-runtime', function() use ($renderToolView) {
    return $renderToolView('entity_runtime', static function ($v): void {
        AdminToolsController::entityRuntime($v);
    });
});

$router->get('/admin/system-tools/my-work-runtime', function() use ($renderToolView) {
    return $renderToolView('my_work_runtime', static function ($v): void {
        AdminToolsController::myWorkRuntime($v);
    });
});

$router->get('/admin/system-tools/stage-inspector', function() use ($renderToolView) {
    return $renderToolView('stage_inspector', static function ($v): void {
        AdminToolsController::stageInspector($v);
    });
});

$router->get('/admin/system-tools/runtime-report', function() use ($renderToolView) {
    return $renderToolView('runtime_report', static function ($v): void {
        AdminToolsController::runtimeReport($v);
    });
});

$router->get('/admin/system-tools/module-health', function() use ($renderToolView) {
    return $renderToolView('module_health', static function ($v): void {
        AdminToolsController::moduleHealth($v);
    });
});

$router->get('/admin/system-tools/export-audit', function() use ($renderToolView) {
    return $renderToolView('export_audit', static function ($v): void {
        AdminToolsController::exportAudit($v);
    });
});

$router->get('/admin/system-tools/app-management', function() use ($renderToolView) {
    return $renderToolView('app_management', static function ($v): void {
        AdminToolsController::appManagement($v);
    });
});

$router->get('/admin/system-tools/resilience', function() use ($renderToolView) {
    return $renderToolView('resilience_map', static function ($v): void {
        AdminToolsController::resilienceMap($v);
    });
});

$router->post('/admin/system-tools/app-management/export-app', function() use ($requirePolicyAccess) {
    $requirePolicyAccess('app_export_app');
    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    $app_key = trim((string)($_POST['app'] ?? 'manufacturing'));
    if (!in_array($app_key, ['manufacturing', 'sbaio'], true)) {
        $app_key = 'manufacturing';
    }
    $exportType = trim((string)($_POST['export_type'] ?? 'metadata_only'));
    try {
        $result = (new \App\Services\SuiteExportService())->exportSuite($app_key, $exportType);
        $_SESSION['app_mgmt_ok'] = $app_key . ' export created: ' . basename((string)$result['file_path']);
    } catch (\Throwable $e) {
        error_log('App export failed: ' . $e->getMessage());
        $_SESSION['app_mgmt_err'] = (string)t('admin.app_management.export_failed');
    }
    header('Location: /admin/system-tools/app-management?app=' . urlencode($app_key));
    exit;
});

$router->post('/admin/system-tools/app-management/export-module', function() use ($requirePolicyAccess) {
    $requirePolicyAccess('app_export_module');
    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    $app_key = trim((string)($_POST['app'] ?? 'manufacturing'));
    if (!in_array($app_key, ['manufacturing', 'sbaio'], true)) {
        $app_key = 'manufacturing';
    }
    $moduleName = trim((string)($_POST['module_name'] ?? ''));
    $exportType = trim((string)($_POST['export_type'] ?? 'metadata_only'));
    try {
        $result = (new \App\Services\SuiteExportService())->exportModule($app_key, $moduleName, $exportType);
        $_SESSION['app_mgmt_ok'] = $moduleName . ' export created: ' . basename((string)$result['file_path']);
    } catch (\Throwable $e) {
        error_log('Module export failed: ' . $e->getMessage());
        $_SESSION['app_mgmt_err'] = (string)t('admin.app_management.export_failed');
    }
    header('Location: /admin/system-tools/app-management?app=' . urlencode($app_key));
    exit;
});

$router->get('/admin/system-tools/app-management/download', function() use ($requirePolicyAccess) {
    $requirePolicyAccess('app_download');
    $id = (int)($_GET['id'] ?? 0);
    $row = (new \App\Services\ExportHistoryService())->findById($id);
    if (!is_array($row) || empty($row['file_path']) || empty($row['id'])) {
        http_response_code(404);
        echo 'Export not found';
        exit;
    }
    $real = realpath((string)$row['file_path']);
    $allowed = realpath(dirname((string)$row['file_path']));
    if ($real === false || $allowed === false || !str_starts_with($real, $allowed . DIRECTORY_SEPARATOR) || !is_file($real)) {
        http_response_code(403);
        echo 'Download not allowed';
        exit;
    }
    if (!is_readable($real)) {
        http_response_code(403);
        echo 'File not readable';
        exit;
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Description: File Transfer');
    header('Content-Transfer-Encoding: binary');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', (string)($row['file_name'] ?? 'export.zip')) . '"');
    header('Content-Length: ' . (string)filesize($real));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    $fp = fopen($real, 'rb');
    if ($fp === false) {
        http_response_code(500);
        echo 'Unable to open file';
        exit;
    }
    fpassthru($fp);
    fclose($fp);
    exit;
});

$router->get('/admin/platform-admin-links', function() use ($renderToolView) {
    return $renderToolView('platform_admin_links', static function ($v): void {
        AdminToolsController::platformAdminLinks($v);
    });
});
