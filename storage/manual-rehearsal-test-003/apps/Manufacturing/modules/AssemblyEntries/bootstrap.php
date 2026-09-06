<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../app/Core/ManufacturingBootstrapHelper.php';

\App\Core\ManufacturingBootstrapHelper::registerEntity(
    __DIR__,
    'AssemblyEntry',
    '\Apps\Manufacturing\Modules\AssemblyEntries\register_assembly_entry_entity'
);
