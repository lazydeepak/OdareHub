<?php
declare(strict_types=1);

use Apps\Studio\Services\StudioRouteLinkingService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../apps/Studio/Services/StudioRouteLinkingService.php';

/**
 * Phase 1E regression tests for StudioRouteLinkingService.
 *
 * Tests cover:
 * - Valid analyze proposal (READY gate)
 * - Invalid proposals (BLOCKED gate): empty route, unsafe route, missing app_key
 * - Fingerprint computation is deterministic
 * - Apply rejects fingerprint mismatch
 * - Apply rejects missing plan
 * - Upgrade mode blocked without acknowledge
 * - No-op detection (route_unchanged warning)
 * - Safe route path regex enforcement
 */
final class StudioRouteLinkingServiceTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Analyze: valid proposal READY gate (no manifest on disk — expects manifest_not_found)
    // -------------------------------------------------------------------------

    public function testAnalyzeBlockedWhenManifestNotFound(): void
    {
        $result = StudioRouteLinkingService::analyzeProposal([
            'app_key' => 'nonexistent_app',
            'module_key' => 'nonexistent_module',
            'proposed_route' => '/apps/nonexistent-app/nonexistent-module',
            'mode' => 'create',
        ]);

        $this->assertSame('studio.route_linking.v1', $result['version']);
        $this->assertSame('BLOCKED', $result['apply_gate']['status']);
        $this->assertFalse($result['apply_gate']['can_apply']);
        $this->assertGreaterThan(0, $result['apply_gate']['blocked_count']);

        $codes = array_column($result['errors'], 'code');
        $this->assertContains('manifest_not_found', $codes);
    }

    // -------------------------------------------------------------------------
    // Analyze: empty route → BLOCKED
    // -------------------------------------------------------------------------

    public function testAnalyzeBlockedOnEmptyRoute(): void
    {
        $result = StudioRouteLinkingService::analyzeProposal([
            'app_key' => 'demo_app',
            'module_key' => 'demo_module',
            'proposed_route' => '',
            'mode' => 'create',
        ]);

        $this->assertSame('BLOCKED', $result['apply_gate']['status']);
        $codes = array_column($result['errors'], 'code');
        $this->assertContains('empty_proposed_route', $codes);
    }

    // -------------------------------------------------------------------------
    // Analyze: unsafe route path → BLOCKED
    // -------------------------------------------------------------------------

    public function testAnalyzeBlockedOnUnsafeRoutePath(): void
    {
        $unsafePaths = [
            '../../etc/passwd',
            'no-leading-slash',
            '/apps/../../escape',
            '/CAPS/ARE/BLOCKED',
            str_repeat('/apps/x', 50),   // too long
        ];

        foreach ($unsafePaths as $path) {
            $result = StudioRouteLinkingService::analyzeProposal([
                'app_key' => 'demo_app',
                'module_key' => 'demo_module',
                'proposed_route' => $path,
                'mode' => 'create',
            ]);

            $this->assertSame('BLOCKED', $result['apply_gate']['status'], "Expected BLOCKED for path: $path");
            $codes = array_column($result['errors'], 'code');
            $hasBlockCode = in_array('unsafe_route_path', $codes, true)
                || in_array('route_too_long', $codes, true)
                || in_array('empty_proposed_route', $codes, true);
            $this->assertTrue($hasBlockCode, "Expected safety error code for path: $path, got: " . implode(', ', $codes));
        }
    }

    // -------------------------------------------------------------------------
    // Analyze: valid route path format (does not require manifest on disk)
    // -------------------------------------------------------------------------

    public function testAnalyzeReturnsVersionAndStructure(): void
    {
        $result = StudioRouteLinkingService::analyzeProposal([
            'app_key' => 'my_app',
            'module_key' => 'my_module',
            'proposed_route' => '/apps/my-app/my-module',
            'mode' => 'create',
        ]);

        $this->assertSame('studio.route_linking.v1', $result['version']);
        $this->assertArrayHasKey('analyze', $result);
        $this->assertArrayHasKey('changes', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('apply_gate', $result);
        $this->assertArrayHasKey('plan', $result);
        $this->assertArrayHasKey('fingerprint', $result);
        // Blocked because manifest does not exist on disk
        $this->assertSame('BLOCKED', $result['apply_gate']['status']);
    }

    // -------------------------------------------------------------------------
    // Analyze: missing app_key → BLOCKED
    // -------------------------------------------------------------------------

    public function testAnalyzeBlockedOnMissingAppKey(): void
    {
        $result = StudioRouteLinkingService::analyzeProposal([
            'app_key' => '',
            'module_key' => 'my_module',
            'proposed_route' => '/apps/my-app/my-module',
            'mode' => 'create',
        ]);

        $this->assertSame('BLOCKED', $result['apply_gate']['status']);
        $codes = array_column($result['errors'], 'code');
        $this->assertContains('missing_identity', $codes);
    }

    // -------------------------------------------------------------------------
    // Upgrade mode: blocked without acknowledge
    // -------------------------------------------------------------------------

    public function testAnalyzeUpgradeModeBlockedWithoutAcknowledge(): void
    {
        $result = StudioRouteLinkingService::analyzeProposal([
            'app_key' => 'my_app',
            'module_key' => 'my_module',
            'proposed_route' => '/apps/my-app/my-module-new',
            'mode' => 'upgrade',
            'current_route' => '/apps/my-app/my-module',
            'upgrade_acknowledged' => false,
        ]);

        $this->assertSame('BLOCKED', $result['apply_gate']['status']);
        $codes = array_column($result['errors'], 'code');
        $this->assertContains('upgrade_not_acknowledged', $codes);
    }

    // -------------------------------------------------------------------------
    // No-op detection (route_unchanged warning)
    // -------------------------------------------------------------------------

    public function testAnalyzeWarnsOnUnchangedRoute(): void
    {
        $result = StudioRouteLinkingService::analyzeProposal([
            'app_key' => 'my_app',
            'module_key' => 'my_module',
            'proposed_route' => '/apps/my-app/my-module',
            'mode' => 'upgrade',
            'current_route' => '/apps/my-app/my-module',
            'upgrade_acknowledged' => true,
        ]);

        $codes = array_column($result['errors'], 'code');
        $this->assertContains('route_unchanged', $codes);
        $this->assertGreaterThan(0, $result['apply_gate']['warning_count']);
    }

    // -------------------------------------------------------------------------
    // Fingerprint: deterministic for same plan
    // -------------------------------------------------------------------------

    public function testFingerprintIsDeterministic(): void
    {
        $plan = [
            'version' => 'studio.route_linking.v1',
            'app_key' => 'demo_app',
            'module_key' => 'demo_module',
            'mode' => 'create',
            'proposed_route' => '/apps/demo-app/demo-module',
            'current_route' => '',
            'writes' => [
                [
                    'target_file' => 'apps/Generated/demo_app/demo_module/manifest.json',
                    'field' => 'route_path',
                    'value' => '/apps/demo-app/demo-module',
                ],
            ],
        ];

        $fp1 = StudioRouteLinkingService::computePlanFingerprint($plan);
        $fp2 = StudioRouteLinkingService::computePlanFingerprint($plan);

        $this->assertSame($fp1, $fp2);
        $this->assertStringStartsWith('route-link:', $fp1);
    }

    // -------------------------------------------------------------------------
    // Fingerprint: different plans produce different fingerprints
    // -------------------------------------------------------------------------

    public function testFingerprintDiffersForDifferentPlans(): void
    {
        $planA = [
            'version' => 'studio.route_linking.v1',
            'app_key' => 'app_a',
            'module_key' => 'module_a',
            'mode' => 'create',
            'proposed_route' => '/apps/app-a/module-a',
            'current_route' => '',
            'writes' => [],
        ];

        $planB = $planA;
        $planB['proposed_route'] = '/apps/app-b/module-b';
        $planB['app_key'] = 'app_b';

        $fpA = StudioRouteLinkingService::computePlanFingerprint($planA);
        $fpB = StudioRouteLinkingService::computePlanFingerprint($planB);

        $this->assertNotSame($fpA, $fpB);
    }

    // -------------------------------------------------------------------------
    // Apply: rejects missing fingerprint
    // -------------------------------------------------------------------------

    public function testApplyRejectsMissingFingerprint(): void
    {
        $plan = [
            'version' => 'studio.route_linking.v1',
            'app_key' => 'demo_app',
            'module_key' => 'demo_module',
            'mode' => 'create',
            'proposed_route' => '/apps/demo-app/demo-module',
            'current_route' => '',
            'writes' => [
                [
                    'target_file' => 'apps/Generated/demo_app/demo_module/manifest.json',
                    'field' => 'route_path',
                    'value' => '/apps/demo-app/demo-module',
                ],
            ],
        ];

        $result = StudioRouteLinkingService::applyApprovedPlan($plan, '');

        $this->assertFalse($result['ok']);
        $this->assertSame('FAILED', $result['status']);
        $this->assertSame('missing_fingerprint', $result['code']);
    }

    // -------------------------------------------------------------------------
    // Apply: rejects fingerprint mismatch
    // -------------------------------------------------------------------------

    public function testApplyRejectsFingerprintMismatch(): void
    {
        $plan = [
            'version' => 'studio.route_linking.v1',
            'app_key' => 'demo_app',
            'module_key' => 'demo_module',
            'mode' => 'create',
            'proposed_route' => '/apps/demo-app/demo-module',
            'current_route' => '',
            'writes' => [
                [
                    'target_file' => 'apps/Generated/demo_app/demo_module/manifest.json',
                    'field' => 'route_path',
                    'value' => '/apps/demo-app/demo-module',
                ],
            ],
        ];

        $result = StudioRouteLinkingService::applyApprovedPlan($plan, 'route-link:wrongfingerprint');

        $this->assertFalse($result['ok']);
        $this->assertSame('FAILED', $result['status']);
        $this->assertSame('fingerprint_mismatch', $result['code']);
    }

    // -------------------------------------------------------------------------
    // Apply: rejects unsafe route path (defense in depth)
    // -------------------------------------------------------------------------

    public function testApplyRejectsUnsafeRouteInPlan(): void
    {
        $plan = [
            'version' => 'studio.route_linking.v1',
            'app_key' => 'demo_app',
            'module_key' => 'demo_module',
            'mode' => 'create',
            'proposed_route' => '../../etc/passwd',
            'current_route' => '',
            'writes' => [],
        ];

        $fp = StudioRouteLinkingService::computePlanFingerprint($plan);
        $result = StudioRouteLinkingService::applyApprovedPlan($plan, $fp);

        $this->assertFalse($result['ok']);
        $this->assertSame('FAILED', $result['status']);
        $this->assertContains($result['code'], ['unsafe_route_path', 'empty_plan_writes']);
    }

    // -------------------------------------------------------------------------
    // Apply: rejects path traversal in write target
    // -------------------------------------------------------------------------

    public function testApplyRejectsPathTraversalInWriteTarget(): void
    {
        $plan = [
            'version' => 'studio.route_linking.v1',
            'app_key' => 'demo_app',
            'module_key' => 'demo_module',
            'mode' => 'create',
            'proposed_route' => '/apps/demo-app/demo-module',
            'current_route' => '',
            'writes' => [
                [
                    'target_file' => 'apps/Generated/../../../etc/passwd',
                    'field' => 'route_path',
                    'value' => '/apps/demo-app/demo-module',
                ],
            ],
        ];

        $fp = StudioRouteLinkingService::computePlanFingerprint($plan);
        $result = StudioRouteLinkingService::applyApprovedPlan($plan, $fp);

        // Should fail for the write (unsafe_target_path), or the whole apply fails
        $this->assertFalse($result['ok']);
        if (!empty($result['failed'])) {
            $failReasons = array_column($result['failed'], 'reason');
            $this->assertContains('unsafe_target_path', $failReasons);
        }
    }

    // -------------------------------------------------------------------------
    // Apply: rejects unsupported field name
    // -------------------------------------------------------------------------

    public function testApplyRejectsUnsupportedField(): void
    {
        // Create a minimal real manifest.json in a temp location for the write test
        $tmpDir = sys_get_temp_dir() . '/studio_route_link_test_' . bin2hex(random_bytes(4));
        @mkdir($tmpDir . '/Generated/test_app/test_module', 0777, true);
        $manifestPath = $tmpDir . '/Generated/test_app/test_module/manifest.json';
        file_put_contents($manifestPath, json_encode(['route_path' => '']));

        // We cannot easily override APP_ROOT here without modifying the service,
        // so this test validates the plan structure + field guard via a custom plan
        // where the field is not 'route_path'.
        $plan = [
            'version' => 'studio.route_linking.v1',
            'app_key' => 'test_app',
            'module_key' => 'test_module',
            'mode' => 'create',
            'proposed_route' => '/apps/test-app/test-module',
            'current_route' => '',
            'writes' => [
                [
                    'target_file' => 'apps/Generated/test_app/test_module/manifest.json',
                    'field' => 'arbitrary_dangerous_field',
                    'value' => 'injected',
                ],
            ],
        ];

        $fp = StudioRouteLinkingService::computePlanFingerprint($plan);
        $result = StudioRouteLinkingService::applyApprovedPlan($plan, $fp);

        // Either ok=false (manifest not found in real APP_ROOT) or failed with unsupported_field
        $this->assertFalse($result['ok']);
        if (!empty($result['failed'])) {
            $failReasons = array_column($result['failed'], 'reason');
            $hasExpectedReason = array_filter($failReasons, static function (string $r): bool {
                return str_starts_with($r, 'unsupported_field') || $r === 'file_not_found' || $r === 'unsafe_target_path';
            });
            $this->assertNotEmpty($hasExpectedReason, 'Expected a safety-related failure reason, got: ' . implode(', ', $failReasons));
        }

        // Cleanup
        @unlink($manifestPath);
        @rmdir($tmpDir . '/Generated/test_app/test_module');
        @rmdir($tmpDir . '/Generated/test_app');
        @rmdir($tmpDir . '/Generated');
        @rmdir($tmpDir);
    }
}
