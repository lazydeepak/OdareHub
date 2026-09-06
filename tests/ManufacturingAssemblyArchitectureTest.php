<?php
declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

final class ManufacturingAssemblyArchitectureTest extends TestCase
{
    public function testAssemblyModulesDeclareDedicatedSchemaContracts(): void
    {
        $root = dirname(__DIR__);

        $this->assertFileExists($root . '/apps/Manufacturing/modules/AssemblyPlans/plugin.json');
        $this->assertFileExists($root . '/apps/Manufacturing/modules/AssemblyPlans/migrations/001_assembly_plan_contract.sql');
        $this->assertFileExists($root . '/apps/Manufacturing/modules/AssemblyEntries/plugin.json');
        $this->assertFileExists($root . '/apps/Manufacturing/modules/AssemblyEntries/migrations/001_assembly_entries.sql');
    }

    public function testManufacturingManifestUsesCanonicalAssemblyAppRoutes(): void
    {
        $manifestPath = dirname(__DIR__) . '/apps/Manufacturing/manifest.json';
        $manifest = json_decode((string)file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

        $this->assertContains('assembly_plans', $manifest['native_modules']);
        $this->assertContains('assembly_entries', $manifest['native_modules']);

        $routes = array_map(
            static fn(array $route): string => (string)($route['path'] ?? ''),
            (array)($manifest['runtime_contract']['routes'] ?? [])
        );

        $this->assertContains('/apps/manufacturing/assembly-plans', $routes);
        $this->assertContains('/apps/manufacturing/assembly-queue', $routes);
        $this->assertContains('/manufacturing/assembly-plans', $routes);
        $this->assertContains('/manufacturing/assembly-queue', $routes);

        foreach ((array)($manifest['runtime_contract']['routes'] ?? []) as $route) {
            if (($route['path'] ?? '') === '/manufacturing/assembly-plans') {
                $this->assertSame('alias', $route['kind']);
                $this->assertSame('/apps/manufacturing/assembly-plans', $route['canonical_target']);
            }
            if (($route['path'] ?? '') === '/manufacturing/assembly-queue') {
                $this->assertSame('alias', $route['kind']);
                $this->assertSame('/apps/manufacturing/assembly-queue', $route['canonical_target']);
            }
        }
    }
}
