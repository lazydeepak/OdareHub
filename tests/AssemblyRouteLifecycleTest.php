<?php
declare(strict_types=1);

namespace Tests;

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Router;
use App\Core\View;
use PHPUnit\Framework\TestCase;

final class AssemblyRouteLifecycleTest extends TestCase
{
    public function testAppAssemblyPlanRoutesDoNotRegisterCanonicalRoutesWhenModuleInactive(): void
    {
        [$router, $view, $c] = $this->routeHarness([
            'AssemblyPlans' => 'inactive',
        ]);

        require APP_ROOT . '/apps/Manufacturing/Routes/assembly_plans.php';

        $routes = $router->listRoutes();

        $this->assertArrayNotHasKey('/apps/manufacturing/assembly-plans', $routes['GET']);
        $this->assertArrayNotHasKey('/apps/manufacturing/assembly-workbench', $routes['GET']);
        $this->assertArrayNotHasKey('/apps/manufacturing/assembly-plans/detail', $routes['GET']);
        $this->assertArrayNotHasKey('/manufacturing/assembly-plans', $routes['GET']);
        $this->assertArrayNotHasKey('/apps/manufacturing/assembly-plans/update', $routes['POST']);
    }

    public function testAppAssemblyQueueRoutesDoNotRegisterCanonicalRoutesWhenModuleInactive(): void
    {
        [$router, $view, $c] = $this->routeHarness([
            'AssemblyEntries' => 'inactive',
        ]);

        require APP_ROOT . '/apps/Manufacturing/Routes/execution.php';

        $routes = $router->listRoutes();

        $this->assertArrayNotHasKey('/apps/manufacturing/assembly-queue', $routes['GET']);
        $this->assertArrayNotHasKey('/apps/manufacturing/assembly-demand-queue', $routes['GET']);
        $this->assertArrayNotHasKey('/manufacturing/assembly-queue', $routes['GET']);
        $this->assertArrayNotHasKey('/manufacturing/assembly-demand-queue', $routes['GET']);
    }

    public function testAssemblyPlanModuleRoutesOwnCanonicalRoutes(): void
    {
        [$router, $view, $c] = $this->routeHarness([
            'AssemblyPlans' => 'active',
        ]);

        require APP_ROOT . '/apps/Manufacturing/modules/AssemblyPlans/routes.php';

        $routes = $router->listRoutes();

        $this->assertArrayHasKey('/apps/manufacturing/assembly-plans', $routes['GET']);
        $this->assertArrayHasKey('/apps/manufacturing/assembly-workbench', $routes['GET']);
        $this->assertArrayHasKey('/apps/manufacturing/assembly-plans/detail', $routes['GET']);
        $this->assertArrayHasKey('/apps/manufacturing/assembly-plans/update', $routes['POST']);
    }

    public function testAssemblyEntryModuleRoutesOwnCanonicalQueueRoutes(): void
    {
        [$router, $view, $c] = $this->routeHarness([
            'AssemblyEntries' => 'active',
        ]);

        require APP_ROOT . '/apps/Manufacturing/modules/AssemblyEntries/routes.php';

        $routes = $router->listRoutes();

        $this->assertArrayHasKey('/apps/manufacturing/assembly-queue', $routes['GET']);
        $this->assertArrayHasKey('/apps/manufacturing/assembly-demand-queue', $routes['GET']);
    }

    public function testAppCompatibilityAliasesRegisterWhenAssemblyModulesAreActive(): void
    {
        [$router, $view, $c] = $this->routeHarness([
            'AssemblyPlans' => 'active',
            'AssemblyEntries' => 'active',
        ]);

        require APP_ROOT . '/apps/Manufacturing/Routes/assembly_plans.php';
        require APP_ROOT . '/apps/Manufacturing/Routes/execution.php';

        $routes = $router->listRoutes();

        $this->assertArrayHasKey('/manufacturing/assembly-plans', $routes['GET']);
        $this->assertArrayHasKey('/manufacturing/assembly-plans/detail', $routes['GET']);
        $this->assertArrayHasKey('/manufacturing/assembly-queue', $routes['GET']);
        $this->assertArrayHasKey('/manufacturing/assembly-demand-queue', $routes['GET']);
        $this->assertArrayHasKey('/manufacturing/assembly-plans/update', $routes['POST']);
    }

    /**
     * @param array<string,string> $statuses
     * @return array{0:Router,1:View,2:object}
     */
    private function routeHarness(array $statuses): array
    {
        $router = new Router();
        $view = new View(APP_ROOT . '/public/views');
        $plugins = new class($statuses) {
            /**
             * @param array<string,string> $statuses
             */
            public function __construct(private array $statuses)
            {
            }

            public function status(string $name): ?string
            {
                return $this->statuses[$name] ?? 'inactive';
            }
        };
        $c = new class($plugins) {
            public function __construct(private object $plugins)
            {
            }

            public function get(string $id): object
            {
                return $this->plugins;
            }
        };

        return [$router, $view, $c];
    }
}
