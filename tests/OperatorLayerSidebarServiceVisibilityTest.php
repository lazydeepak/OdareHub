<?php
declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require_once __DIR__ . '/../app/Core/helpers.php';
require_once __DIR__ . '/../apps/Shell/Services/OperatorLayerSidebarService.php';

use Apps\Shell\Services\OperatorLayerSidebarService;
use PHPUnit\Framework\TestCase;

final class OperatorLayerSidebarServiceVisibilityTest extends TestCase
{
    public function testResolvedOperatorViewsStayAuthoritative(): void
    {
        $service = new OperatorLayerSidebarService([
            'assigned_apps' => ['manufacturing'],
            'active_assigned_apps' => ['manufacturing'],
            'module_visibility' => ['dispatch'],
            'operator_views' => 'dashboard,account',
        ]);

        $sidebar = $service->getContextualSidebar();

        $this->assertSame(['Dashboard', 'My Tasks', 'Account'], $this->sectionTitles($sidebar));
    }

    public function testLegacyModuleVisibilityFallbackRequiresExplicitOptIn(): void
    {
        $service = new OperatorLayerSidebarService([
            'assigned_apps' => ['manufacturing'],
            'active_assigned_apps' => ['manufacturing'],
            'module_visibility' => ['dispatch'],
            'allow_legacy_sidebar_module_visibility' => true,
        ]);

        $sidebar = $service->getContextualSidebar();

        $this->assertContains('Operations', $this->sectionTitles($sidebar));
    }

    public function testFormatSidebarItemsNormalizesAppRoutesToOperatorSurface(): void
    {
        $service = new OperatorLayerSidebarService([
            'username' => 'lazydeepak',
            'assigned_apps' => ['manufacturing'],
            'active_assigned_apps' => ['manufacturing'],
        ]);

        $formatted = $service->formatSidebarItems([
            'sections' => [
                [
                    'title' => 'Ops',
                    'items' => [
                        ['route' => '/apps/manufacturing/dispatch-ops'],
                        ['route' => '/u/otheruser/orders?tab=open'],
                        ['route' => '/admin/lazydeepak'],
                    ],
                ],
            ],
        ], 'lazydeepak');

        $items = (array)($formatted['sections'][0]['items'] ?? []);
        $this->assertSame('/u/lazydeepak/dispatch', (string)($items[0]['route'] ?? ''));
        $this->assertSame('/u/lazydeepak/orders?tab=open', (string)($items[1]['route'] ?? ''));
        $this->assertSame('/u/lazydeepak/dashboard', (string)($items[2]['route'] ?? ''));
    }

    public function testFormatSidebarItemsFallsBackForNonRouteValues(): void
    {
        $service = new OperatorLayerSidebarService([
            'username' => 'lazydeepak',
            'assigned_apps' => ['manufacturing'],
            'active_assigned_apps' => ['manufacturing'],
        ]);

        $formatted = $service->formatSidebarItems([
            'sections' => [
                [
                    'title' => 'Ops',
                    'items' => [
                        ['route' => 'javascript:alert(1)'],
                        ['route' => '#anchor'],
                    ],
                ],
            ],
        ], 'lazydeepak');

        $items = (array)($formatted['sections'][0]['items'] ?? []);
        $this->assertSame('/u/lazydeepak/dashboard', (string)($items[0]['route'] ?? ''));
        $this->assertSame('/u/lazydeepak/dashboard', (string)($items[1]['route'] ?? ''));
    }

    /**
     * @param array<string,mixed> $sidebar
     * @return array<int,string>
     */
    private function sectionTitles(array $sidebar): array
    {
        return array_values(array_map(
            static fn (array $section): string => (string)($section['title'] ?? ''),
            (array)($sidebar['sections'] ?? [])
        ));
    }
}