<?php
declare(strict_types=1);

require_once __DIR__ . '/Contracts/UserAccessPolicyContract.php';
require_once __DIR__ . '/Contracts/UserContextContract.php';
require_once __DIR__ . '/Contracts/UserScopeContract.php';
require_once APP_ROOT . '/plugins/Base/Services/UserDashboardAssignmentService.php';
require_once APP_ROOT . '/plugins/Base/Controllers/RoleDashboardsController.php';
require_once __DIR__ . '/Services/PlatformUserAssignmentAdapter.php';
require_once __DIR__ . '/Services/PlatformUserRuntimeFacade.php';
require_once __DIR__ . '/Services/UserAssignmentContext.php';

$platformAdminToolsControllerPath = APP_ROOT . '/plugins/AdminTools/Controllers/AdminToolsController.php';
if (is_file($platformAdminToolsControllerPath)) {
    require_once $platformAdminToolsControllerPath;
}

if (!function_exists('platform_user_context_contract')) {
	function platform_user_context_contract(): \Apps\Platform\Contracts\UserContextContract
	{
		return \Apps\Platform\Services\UserAssignmentContext::context();
	}
}

if (!function_exists('platform_user_access_policy_contract')) {
	function platform_user_access_policy_contract(): \Apps\Platform\Contracts\UserAccessPolicyContract
	{
		return \Apps\Platform\Services\UserAssignmentContext::accessPolicy();
	}
}

if (!function_exists('platform_user_scope_contract')) {
	function platform_user_scope_contract(): \Apps\Platform\Contracts\UserScopeContract
	{
		return \Apps\Platform\Services\UserAssignmentContext::scope();
	}
}

if (!function_exists('platform_user_runtime_facade')) {
	function platform_user_runtime_facade(): \Apps\Platform\Services\PlatformUserRuntimeFacade
	{
		static $instance = null;
		if (!$instance instanceof \Apps\Platform\Services\PlatformUserRuntimeFacade) {
			$instance = new \Apps\Platform\Services\PlatformUserRuntimeFacade();
		}

		return $instance;
	}
}
