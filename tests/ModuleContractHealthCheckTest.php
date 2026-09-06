<?php
declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

final class ModuleContractHealthCheckTest extends TestCase
{
    public function testEveryAppModuleManifestDeclaresContractFields(): void
    {
        $root = dirname(__DIR__);
        $manifestPaths = glob($root . '/apps/*/modules/*/plugin.json') ?: [];

        $this->assertNotEmpty($manifestPaths);

        foreach ($manifestPaths as $manifestPath) {
            $manifest = json_decode((string)file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
            $label = str_replace($root . '/', '', $manifestPath);

            $this->assertContains($manifest['module_type'] ?? null, [
                'business_entity',
                'planning',
                'process_execution',
                'dashboard_only',
                'service_only',
                'governance',
                'integration',
            ], $label);

            $this->assertMatchesRegularExpression('/^L[0-4]$/', (string)($manifest['target_maturity_level'] ?? ''), $label);
            $this->assertIsArray($manifest['declared_capabilities'] ?? null, $label);
            $this->assertNotEmpty($manifest['declared_capabilities'], $label);

            $lifecycle = $manifest['lifecycle_contract'] ?? null;
            $this->assertIsArray($lifecycle, $label);
            $this->assertSame('active_only', $lifecycle['routes'] ?? null, $label);
            $this->assertSame('active_only', $lifecycle['menus'] ?? null, $label);
            $this->assertSame('active_only', $lifecycle['widgets'] ?? null, $label);
            $this->assertSame('active_only', $lifecycle['reports'] ?? null, $label);
            $this->assertSame('installed_or_active', $lifecycle['schema'] ?? null, $label);
        }
    }

    public function testModuleHealthCheckProducesJsonReport(): void
    {
        $root = dirname(__DIR__);
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tools/module_health_check.php') . ' --json';
        $output = shell_exec($command);

        $this->assertIsString($output);

        $report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($report['modules'] ?? null);
        $this->assertNotEmpty($report['modules']);

        $byName = [];
        foreach ($report['modules'] as $module) {
            $byName[(string)$module['module']] = $module;
        }

        $this->assertArrayHasKey('AssemblyPlans', $byName);
        $this->assertArrayHasKey('AssemblyEntries', $byName);
        $this->assertSame('planning', $byName['AssemblyPlans']['module_type']);
        $this->assertSame('process_execution', $byName['AssemblyEntries']['module_type']);
        $this->assertSame(100, (int)$byName['AssemblyPlans']['score_percent']);
        $this->assertSame(100, (int)$byName['AssemblyEntries']['score_percent']);
        $this->assertSame([], $byName['AssemblyPlans']['missing']);
        $this->assertSame([], $byName['AssemblyEntries']['missing']);
    }

    public function testAssemblyModulesDeclareModuleOwnedReports(): void
    {
        $root = dirname(__DIR__);
        $plans = json_decode((string)file_get_contents($root . '/apps/Manufacturing/modules/AssemblyPlans/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
        $entries = json_decode((string)file_get_contents($root . '/apps/Manufacturing/modules/AssemblyEntries/plugin.json'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('manufacturing.assembly_plans.readiness', $plans['reports'][0]['report_key'] ?? null);
        $this->assertSame('module', $plans['reports'][0]['owner'] ?? null);
        $this->assertSame('active_only', $plans['reports'][0]['lifecycle'] ?? null);
        $this->assertFileExists($root . '/apps/Manufacturing/modules/AssemblyPlans/' . (string)$plans['reports'][0]['view']);
        $this->assertFileExists($root . '/apps/Manufacturing/modules/AssemblyPlans/' . (string)$plans['reports'][0]['export_view']);

        $this->assertSame('manufacturing.assembly_entries.execution', $entries['reports'][0]['report_key'] ?? null);
        $this->assertSame('module', $entries['reports'][0]['owner'] ?? null);
        $this->assertSame('active_only', $entries['reports'][0]['lifecycle'] ?? null);
        $this->assertFileExists($root . '/apps/Manufacturing/modules/AssemblyEntries/' . (string)$entries['reports'][0]['view']);
        $this->assertFileExists($root . '/apps/Manufacturing/modules/AssemblyEntries/' . (string)$entries['reports'][0]['export_view']);
    }

    public function testAssemblyModulesDeclareSupportedLocaleFiles(): void
    {
        $root = dirname(__DIR__);

        foreach (['AssemblyPlans', 'AssemblyEntries'] as $module) {
            foreach (['en', 'ja', 'ne'] as $locale) {
                $path = $root . '/apps/Manufacturing/modules/' . $module . '/lang/' . $locale . '.php';
                $this->assertFileExists($path);
                $labels = require $path;
                $this->assertIsArray($labels);
                $this->assertNotEmpty($labels);
            }
        }
    }
}
