<?php
declare(strict_types=1);

use Apps\Studio\Services\StudioNavCandidateProviderService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../apps/Studio/Services/StudioNavCandidateProviderService.php';

final class StudioNavCandidateProviderServiceTest extends TestCase
{
    public function testContractFieldsMatchExpectedShape(): void
    {
        $expected = [
            'candidate_id',
            'owner_app',
            'owner_module',
            'source_type',
            'source_key',
            'label',
            'route_path',
            'route_name',
            'current_nav_key',
            'current_url',
            'proposed_url',
            'permission_key',
            'visibility',
            'status',
            'diagnostics',
        ];

        $this->assertSame($expected, StudioNavCandidateProviderService::contractFields());
    }

    public function testBuildCandidatesReturnsEmptyWhenNoRealSourcesExist(): void
    {
        $result = StudioNavCandidateProviderService::buildCandidatesFromDiagnostics([
            'linked_routes' => [],
            'declared_routes' => [],
            'route_contracts' => [],
        ]);

        $this->assertSame([], $result['candidates']);
        $this->assertSame(0, $result['counts']['total']);
    }

    public function testBuildCandidatesMapsStatusesAndShapeFromRealDiagnostics(): void
    {
        $result = StudioNavCandidateProviderService::buildCandidatesFromDiagnostics([
            'linked_routes' => [
                [
                    'owner_type' => 'app',
                    'owner_key' => 'sbaio',
                    'owner_status' => 'enabled',
                    'source_key' => 'apps.sbaio.dashboard',
                    'key' => 'sbaio_dashboard',
                    'label' => 'SBAIO Dashboard',
                    'url' => '/apps/sbaio/dashboard',
                    'runtime_url' => '/apps/sbaio/dashboard',
                    'status' => 'loaded',
                    'visible_if' => 'always',
                ],
                [
                    'owner_type' => 'app',
                    'owner_key' => 'sbaio',
                    'owner_status' => 'enabled',
                    'source_key' => 'apps.sbaio.reports',
                    'key' => 'sbaio_reports',
                    'label' => 'SBAIO Reports',
                    'url' => '/apps/sbaio/reports',
                    'runtime_url' => '/apps/sbaio/reports-v2',
                    'status' => 'legacy_fallback',
                    'visible_if' => 'always',
                ],
                [
                    'owner_type' => 'app',
                    'owner_key' => 'manufacturing',
                    'owner_status' => 'disabled',
                    'source_key' => 'apps.manufacturing.workboard',
                    'key' => 'mfg_workboard',
                    'label' => 'Workboard',
                    'url' => '/apps/manufacturing/workboard',
                    'runtime_url' => '/apps/manufacturing/workboard',
                    'status' => 'disabled_by_app_status',
                    'visible_if' => 'role:platform_admin',
                ],
                [
                    'owner_type' => 'app',
                    'owner_key' => 'inventory_app',
                    'owner_status' => 'enabled',
                    'source_key' => 'studio.generated.inventory_app.parts_master',
                    'key' => 'parts_master_nav_a',
                    'label' => 'Parts A',
                    'url' => '/apps/inventory-app/parts-master',
                    'runtime_url' => '/apps/inventory-app/parts-master',
                    'status' => 'loaded',
                    'visible_if' => 'always',
                ],
                [
                    'owner_type' => 'app',
                    'owner_key' => 'inventory_app',
                    'owner_status' => 'enabled',
                    'source_key' => 'studio.generated.inventory_app.parts_master',
                    'key' => 'parts_master_nav_b',
                    'label' => 'Parts B',
                    'url' => '/apps/inventory-app/parts-master',
                    'runtime_url' => '/apps/inventory-app/parts-master',
                    'status' => 'loaded',
                    'visible_if' => 'always',
                ],
            ],
            'declared_routes' => [
                [
                    'app_key' => 'platform',
                    'app_status' => 'enabled',
                    'path' => '/apps/platform/audit',
                    'status' => 'loaded',
                ],
                [
                    'app_key' => 'sbaio',
                    'app_status' => 'enabled',
                    'path' => '/apps/sbaio/dashboard',
                    'status' => 'loaded',
                ],
            ],
            'route_contracts' => [
                [
                    'app_key' => 'studio',
                    'owner_app' => 'studio',
                    'path' => '/apps/studio/tools/nav-composer',
                    'feature_key' => 'studio_nav_composer',
                    'kind' => 'canonical',
                    'compatibility' => false,
                    'deprecated' => false,
                    'hook_enabled' => true,
                    'app_status' => 'enabled',
                    'role_visibility' => ['platform_admin'],
                ],
            ],
        ]);

        $this->assertGreaterThanOrEqual(1, $result['counts']['linked']);
        $this->assertGreaterThanOrEqual(1, $result['counts']['mismatch']);
        $this->assertGreaterThanOrEqual(1, $result['counts']['blocked']);
        $this->assertGreaterThanOrEqual(1, $result['counts']['ambiguous']);
        $this->assertGreaterThanOrEqual(1, $result['counts']['missing_nav']);

        $first = $result['candidates'][0] ?? [];
        foreach (StudioNavCandidateProviderService::contractFields() as $field) {
            $this->assertArrayHasKey($field, $first);
        }

        $ambiguous = array_values(array_filter($result['candidates'], static fn(array $c): bool => (string)($c['status'] ?? '') === 'ambiguous'));
        $this->assertNotSame([], $ambiguous);

        $missingNav = array_values(array_filter($result['candidates'], static fn(array $c): bool => (string)($c['status'] ?? '') === 'missing_nav'));
        $this->assertNotSame([], $missingNav);
        $this->assertSame('/apps/platform/audit', (string)($missingNav[0]['proposed_url'] ?? ''));
    }
}
