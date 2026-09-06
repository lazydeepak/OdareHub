<?php
declare(strict_types=1);

use Apps\Studio\Services\GuiStudioService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../apps/Studio/Services/GuiStudioService.php';

/**
 * Artifact-action parity regression test for GuiStudioService helpers.
 */
final class StudioArtifactAffordanceParityTest extends TestCase
{
    public function testLoadableKindsIsNonEmpty(): void
    {
        $this->assertNotEmpty(GuiStudioService::LOADABLE_KINDS);
    }

    public function testLoadableKindsContainsExpectedSet(): void
    {
        foreach (['app', 'module', 'view', 'dashboard'] as $kind) {
            $this->assertContains($kind, GuiStudioService::LOADABLE_KINDS, "Expected '$kind' in LOADABLE_KINDS");
        }
    }

    public function testInspectOnlyKindsAreNotInLoadableKinds(): void
    {
        foreach (['route', 'nav'] as $kind) {
            $this->assertNotContains($kind, GuiStudioService::LOADABLE_KINDS, "Inspect-only kind '$kind' must not appear in LOADABLE_KINDS");
        }
    }

    public function testIsArtifactKindLoadableMatchesConstant(): void
    {
        foreach (['app', 'module', 'view', 'dashboard', 'route', 'nav', 'unknown'] as $kind) {
            $expected = in_array($kind, GuiStudioService::LOADABLE_KINDS, true);
            $this->assertSame(
                $expected,
                GuiStudioService::isArtifactKindLoadable($kind),
                "isArtifactKindLoadable('$kind') disagreed with LOADABLE_KINDS"
            );
        }
    }

    public function testResolveArtifactKindFromNodeAllCases(): void
    {
        $cases = [
            'app by id prefix'        => [['id' => 'app:my_app',        'type' => ''],                 'app'],
            'module by id prefix'     => [['id' => 'module:orders',     'type' => ''],                 'module'],
            'view by id prefix'       => [['id' => 'view:order_list',   'type' => ''],                 'view'],
            'dashboard by id prefix'  => [['id' => 'dashboard:main',    'type' => ''],                 'dashboard'],
            'nav by id prefix'        => [['id' => 'nav:sidebar',       'type' => ''],                 'nav'],
            'route by id prefix'      => [['id' => 'route:orders_idx',  'type' => ''],                 'route'],
            'route_file by id prefix' => [['id' => 'route_file:web',    'type' => ''],                 'route'],
            'app by type'             => [['id' => '',                  'type' => 'app'],              'app'],
            'module by type'          => [['id' => 'something',         'type' => 'module_manifest'],  'module'],
            'route by type'           => [['id' => 'something',         'type' => 'route_file'],       'route'],
            'nav by type'             => [['id' => 'something',         'type' => 'navigation_entry'], 'nav'],
            'dashboard by type'       => [['id' => 'something',         'type' => 'dashboard'],        'dashboard'],
            'view default'            => [['id' => 'unknown_item',      'type' => 'view_config'],      'view'],
        ];
        foreach ($cases as $label => [$node, $expected]) {
            $actual = GuiStudioService::resolveArtifactKindFromNode($node);
            $this->assertSame($expected, $actual, "$label: expected '$expected', got '$actual'");
        }
    }

    public function testResolveLibraryKindAllCases(): void
    {
        $cases = [
            'view to views'                  => ['view',      '',                  'views'],
            'dashboard to views'             => ['dashboard', '',                  'views'],
            'app to views'                   => ['app',       '',                  'views'],
            'module to modules'              => ['module',    '',                  'modules'],
            'route to routes'                => ['route',     '',                  'routes'],
            'nav to routes'                  => ['nav',       '',                  'routes'],
            'route-path heuristic to routes' => ['view',      '/apps/foo/orders',  'routes'],
        ];
        foreach ($cases as $label => [$artifactKind, $nodeId, $expected]) {
            $actual = GuiStudioService::resolveLibraryKind($artifactKind, $nodeId);
            $this->assertSame($expected, $actual, "$label: resolveLibraryKind() expected '$expected', got '$actual'");
        }
    }

    public function testLoadableAndInspectOnlyAreDisjoint(): void
    {
        $overlap = array_intersect(GuiStudioService::LOADABLE_KINDS, ['route', 'nav']);
        $this->assertEmpty($overlap, 'LOADABLE_KINDS and inspect-only kinds must be disjoint');
    }

    public function testLoadableNodesGetLoadAffordance(): void
    {
        $nodes = [
            ['id' => 'app:my_app',     'type' => 'app'],
            ['id' => 'module:orders',  'type' => 'module'],
            ['id' => 'view:list',      'type' => 'view'],
            ['id' => 'dashboard:main', 'type' => 'dashboard'],
        ];
        foreach ($nodes as $node) {
            $kind = GuiStudioService::resolveArtifactKindFromNode($node);
            $this->assertTrue(
                GuiStudioService::isArtifactKindLoadable($kind),
                "Node id='{$node['id']}' resolved to '$kind' which should be loadable"
            );
        }
    }

    public function testInspectOnlyNodesGetInspectAffordance(): void
    {
        $nodes = [
            ['id' => 'route:orders_index', 'type' => 'route_file'],
            ['id' => 'nav:sidebar',        'type' => 'navigation_entry'],
        ];
        foreach ($nodes as $node) {
            $kind = GuiStudioService::resolveArtifactKindFromNode($node);
            $this->assertFalse(
                GuiStudioService::isArtifactKindLoadable($kind),
                "Node id='{$node['id']}' resolved to '$kind' which should be inspect-only"
            );
        }
    }
}
