<?php
declare(strict_types=1);

namespace Tests;

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Container;
use App\Core\EventBus;
use App\Core\PluginManager;
use App\Core\Router;
use App\Core\View;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class PluginManagerActivationContractTest extends TestCase
{
    public function testActivationContractReportsNoGapsForPromotedAssemblyModules(): void
    {
        $manager = $this->pluginManager();
        $manifests = $manager->manifests();
        $method = (new ReflectionClass($manager))->getMethod('activationCapabilityGaps');

        foreach (['AssemblyPlans', 'AssemblyEntries'] as $moduleName) {
            $this->assertArrayHasKey($moduleName, $manifests);
            $this->assertTrue((bool)($manifests[$moduleName]['activation_contract_enforced'] ?? false));
            $this->assertSame([], $method->invoke($manager, $manifests[$moduleName]), $moduleName);
        }
    }

    public function testActivationContractDetectsMissingDeclaredCapability(): void
    {
        $manager = $this->pluginManager();
        $manifest = $manager->manifests()['AssemblyPlans'];
        $manifest['declared_capabilities'][] = 'dashboard';

        $method = (new ReflectionClass($manager))->getMethod('activationCapabilityGaps');

        $this->assertContains('dashboard', $method->invoke($manager, $manifest));
    }

    private function pluginManager(): PluginManager
    {
        $container = new Container();
        $router = new Router();
        $view = new View(dirname(__DIR__) . '/public/views');
        $events = new EventBus();

        return new PluginManager(dirname(__DIR__) . '/plugins', $container, $router, $view, $events);
    }
}
