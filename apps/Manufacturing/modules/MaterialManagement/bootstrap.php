<?php
declare(strict_types=1);

require_once __DIR__ . '/Services/MaterialSchemaService.php';
require_once __DIR__ . '/Services/MaterialAccessService.php';
require_once __DIR__ . '/Services/MaterialManagementService.php';

\Plugins\MaterialManagement\Services\MaterialSchemaService::ensureSchema();

if (function_exists('base_register_menus')) {
    $menus = require __DIR__ . '/menu.php';
    base_register_menus($menus);
}
