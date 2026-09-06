<?php
declare(strict_types=1);

if (!function_exists('shell_bootstrap_dependencies')) {
    function shell_bootstrap_dependencies(): void
    {
        require_once APP_ROOT . '/plugins/Base/Controllers/RoleDashboardsController.php';
        require_once APP_ROOT . '/plugins/Base/Services/HostSurfaceRegistryService.php';
        require_once APP_ROOT . '/plugins/Base/Services/MyWorkService.php';
    }
}

shell_bootstrap_dependencies();
