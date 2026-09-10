<?php
// REGRESSION PROOF: AppearanceReaderInventoryService is diagnostic-only
// This fixture proves the service reference is permitted by gate exclusions
require_once APP_ROOT . '/apps/Shell/Services/AppearanceReaderInventoryService.php';
$inventory = AppearanceReaderInventoryService::inventory();
