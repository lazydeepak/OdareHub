<?php
declare(strict_types=1);

use Apps\Studio\Services\StudioViewIntrospectionService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../apps/Studio/Services/StudioViewIntrospectionService.php';

final class StudioCompositionContractValidationTest extends TestCase
{
    public function testCompositionContractStateMatrixIsDeterministic(): void
    {
        $cases = [
            'connected' => [
                'seed' => $this->baseSeed([
                    'route_path' => '/apps/demo/orders',
                    'route_found' => true,
                    'nav_found' => true,
                    'nav_target_route' => '/apps/demo/orders',
                ]),
                'expected_state' => 'connected',
                'expected_route_status' => 'connected',
                'expected_navigation_status' => 'connected',
                'expected_diagnostics' => ['connected'],
            ],
            'missing_route' => [
                'seed' => $this->baseSeed([
                    'route_path' => '',
                    'route_found' => false,
                    'nav_found' => true,
                    'nav_target_route' => '',
                    'nav_target_view' => 'demo.orders.index',
                ]),
                'expected_state' => 'missing_route',
                'expected_route_status' => 'missing_route',
                'expected_navigation_status' => 'connected',
                'expected_diagnostics' => ['missing_route'],
            ],
            'missing_nav' => [
                'seed' => $this->baseSeed([
                    'route_path' => '/apps/demo/orders',
                    'route_found' => true,
                    'nav_found' => false,
                    'nav_target_route' => '',
                    'nav_target_view' => '',
                ]),
                'expected_state' => 'missing_nav',
                'expected_route_status' => 'connected',
                'expected_navigation_status' => 'missing_nav',
                'expected_diagnostics' => ['missing_nav'],
            ],
            'nav_unknown_route' => [
                'seed' => $this->baseSeed([
                    'route_path' => '',
                    'route_found' => false,
                    'nav_found' => true,
                    'nav_target_route' => '/apps/demo/orphan',
                    'nav_target_view' => '',
                ]),
                'expected_state' => 'nav_unknown_route',
                'expected_route_status' => 'missing_route',
                'expected_navigation_status' => 'connected',
                'expected_diagnostics' => ['missing_route', 'nav_unknown_route'],
            ],
            'ambiguous_conflicting' => [
                'seed' => $this->baseSeed([
                    'route_path' => '/apps/demo/orders',
                    'route_found' => true,
                    'nav_found' => true,
                    'nav_target_route' => '/apps/demo/shipments',
                    'nav_target_view' => '',
                ]),
                'expected_state' => 'ambiguous_conflicting',
                'expected_route_status' => 'connected',
                'expected_navigation_status' => 'connected',
                'expected_diagnostics' => ['ambiguous_conflicting'],
            ],
        ];

        foreach ($cases as $label => $case) {
            $contract = $this->buildCompositionContract($case['seed']);
            $repeat = $this->buildCompositionContract($case['seed']);

            self::assertSame($contract, $repeat, $label . ' contract should be deterministic.');
            self::assertSame('studio.composition.v1', $contract['version'], $label);
            self::assertSame('view', $contract['artifact_kind'], $label);
            self::assertSame($case['expected_state'], $contract['relationship_state']['state'], $label);
            self::assertSame($case['expected_route_status'], $contract['relationship_state']['route_status'], $label);
            self::assertSame($case['expected_navigation_status'], $contract['relationship_state']['navigation_status'], $label);
            self::assertSame($case['expected_diagnostics'], $contract['relationship_state']['diagnostics'], $label);
            self::assertTrue($contract['relationship_state']['inspect_only'], $label);

            self::assertSame('orders_index', $contract['view_exposure']['view_key'], $label);
            self::assertSame('demo', $contract['view_exposure']['owning_app'], $label);
            self::assertSame('orders', $contract['view_exposure']['owning_module'], $label);
            self::assertArrayHasKey('target_route', $contract['navigation_exposure'], $label);
            self::assertArrayHasKey('target_view', $contract['navigation_exposure'], $label);
            self::assertSame($case['seed']['route_found'], $contract['source']['route_found'], $label);
            self::assertSame($case['seed']['nav_found'], $contract['source']['navigation_found'], $label);
        }
    }

    /**
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    private function baseSeed(array $overrides = []): array
    {
        return array_merge([
            'view_key' => 'orders_index',
            'display_label' => 'Orders Index',
            'owning_app' => 'demo',
            'owning_module' => 'orders',
            'route_path' => '/apps/demo/orders',
            'route_key' => 'demo.orders.index',
            'route_file' => 'apps/Demo/routes.php',
            'route_found' => true,
            'nav_key' => 'demo.orders',
            'nav_label' => 'Orders',
            'nav_icon' => 'clipboard-list',
            'nav_order' => 20,
            'nav_target_route' => '/apps/demo/orders',
            'nav_target_view' => '',
            'nav_file' => 'apps/Demo/navigation.php',
            'nav_found' => true,
            'visibility' => 'always',
            'source_kind' => 'phase_1d_1_fixture',
        ], $overrides);
    }

    /**
     * @param array<string,mixed> $seed
     * @return array<string,mixed>
     */
    private function buildCompositionContract(array $seed): array
    {
        $method = new ReflectionMethod(StudioViewIntrospectionService::class, 'buildViewCompositionContract');

        $contract = $method->invoke(null, $seed);
        self::assertIsArray($contract);

        return $contract;
    }
}
