<?php
declare(strict_types=1);

namespace App\Core;

// Compatibility bridge only. Normal runtime should resolve to
// Plugins\Supply\Services\SupplyModel inside Manufacturing.

$supplyModel = APP_ROOT . '/apps/Manufacturing/modules/Supply/Services/SupplyModel.php';
if (is_file($supplyModel)) {
    require_once $supplyModel;
}

if (!class_exists(__NAMESPACE__ . '\\SupplyModel', false) && class_exists(\Plugins\Supply\Services\SupplyModel::class)) {
    class_alias(\Plugins\Supply\Services\SupplyModel::class, __NAMESPACE__ . '\\SupplyModel');
}
