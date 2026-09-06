<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../app/Core/ManufacturingBootstrapHelper.php';

\App\Core\ManufacturingBootstrapHelper::registerEntity(
    __DIR__,
    'ProductionEntry',
    '\Plugins\ProductionEntries\register_production_entry_entity'
);

if (function_exists('base_register_menus')) {
    $menus = require __DIR__ . '/menu.php';
    base_register_menus($menus);
}
