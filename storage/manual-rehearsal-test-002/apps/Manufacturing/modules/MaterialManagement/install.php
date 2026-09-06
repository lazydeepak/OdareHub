<?php
declare(strict_types=1);

require_once __DIR__ . '/Services/MaterialSchemaService.php';

\Plugins\MaterialManagement\Services\MaterialSchemaService::ensureSchema();
