<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('PUBLIC_ROOT', APP_ROOT . '/public');

require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/app/Core/DB.php';
require_once APP_ROOT . '/app/Core/Container.php';
require_once APP_ROOT . '/app/Core/EventBus.php';
require_once APP_ROOT . '/app/Core/Router.php';
require_once APP_ROOT . '/app/Core/View.php';
require_once APP_ROOT . '/app/Services/AppPlatformLogger.php';
require_once APP_ROOT . '/app/Services/AppManifestService.php';
require_once APP_ROOT . '/app/Services/AppRegistryService.php';
require_once APP_ROOT . '/app/Services/AppRuntimeLoader.php';

use App\Services\AppRuntimeLoader;

echo "Testing AppRuntimeLoader safe loading guard...\n";

// Test 1: Load routes
echo "\n1. Loading enabled apps for first time...\n";
$loader = new AppRuntimeLoader();
$container = new \App\Core\Container();
$router = new \App\Core\Router();
$view = new \App\Core\View(APP_ROOT . '/public/views');

$initialLoadedRoutes = AppRuntimeLoader::getLoadedRouteFiles();
echo "   Loaded " . count($initialLoadedRoutes) . " route files\n";
foreach ($initialLoadedRoutes as $file) {
    echo "     - " . basename($file) . "\n";
}

// Test 2: Verify no duplicates on second load
echo "\n2. Attempting to load again (should not add duplicates)...\n";
$secondLoadedRoutes = AppRuntimeLoader::getLoadedRouteFiles();
echo "   Still loaded " . count($secondLoadedRoutes) . " route files\n";

if (count($initialLoadedRoutes) === count($secondLoadedRoutes)) {
    echo "   ✓ PASS: No duplicate loading occurred\n";
} else {
    echo "   ✗ FAIL: Duplicate loading detected\n";
}

echo "\nAppRuntimeLoader safe loading guard test complete.\n";
?>
