<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../app/Core/ManufacturingBootstrapHelper.php';

\App\Core\ManufacturingBootstrapHelper::registerEntity(
    __DIR__,
    'Bom',
    '\Apps\Manufacturing\Modules\Bom\register_bom_entity'
);