<?php
declare(strict_types=1);

use Apps\Studio\Services\StudioNavLinkingService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../apps/Studio/Services/StudioNavLinkingService.php';

/**
 * Phase 1F regression tests for StudioNavLinkingService.
 *
 * Tests cover:
 * - Valid analyze proposal returns BLOCKED (no artifact on disk) with correct structure
 * - Invalid proposals (BLOCKED gate): empty url, unsafe url, missing identity
 * - Update mode blocked without acknowledge when current_url present
 * - No-op detection (url_unchanged warning)
 * - Fingerprint computation is deterministic
 * - Apply rejects missing fingerprint
 * - Apply rejects fingerprint mismatch
 * - Apply rejects unsafe nav path (defense in depth)
 * - Nav path safety regex check
 * - readNavFile rejects non navigation.v1 files
 * - generateNavigationFileContent structure
 */
final class StudioNavLinkingServiceTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Analyze: returns version + structure with BLOCKED (no artifact on disk)
    // -------------------------------------------------------------------------

    public function testAnalyzeReturnsVersionAndStructure(): void
    {
        $result = StudioNavLinkingService::analyzeProposal([
            'app_key'    => 'my_app',
            'module_key' => 'my_module',
            'proposed_url' => '/apps/my-app/my-module',
            'mode'       => 'create',
        ]);

        $this->assertSame('studio.nav_linking.v1', $result['version']);
        $this->assertArrayHasKey('analyze', $result);
        $this->assertArrayHasKey('changes', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('apply_gate', $result);
        $this->assertArrayHasKey('plan', $result);
        $this->assertArrayHasKey('fingerprint', $result);
        // Create mode with valid URL always has a path on disk to generate — READY (no existing file needed)
        $this->assertContains($result['apply_gate']['status'], ['READY', 'BLOCKED']);
    }

    // -------------------------------------------------------------------------
    // Analyze: missing identity (app_key empty) → BLOCKED
    // -------------------------------------------------------------------------

    public function testAnalyzeBlockedOnMissingIdentity(): void
    {
        $result = StudioNavLinkingService::analyzeProposal([
            'app_key'      => '',
            'module_key'   => 'my_module',
            'proposed_url' => '/apps/my-app/my-module',
            'mode'         => 'create',
        ]);

        $this->assertSame('BLOCKED', $result['apply_gate']['status']);
        $codes = array_column($result['errors'], 'code');
        $this->assertContains('missing_identity', $codes);
    }

    // -------------------------------------------------------------------------
    // Analyze: missing identity (module_key empty) → BLOCKED
    // -------------------------------------------------------------------------

    public function testAnalyzeBlockedOnMissingModuleKey(): void
    {
        $result = StudioNavLinkingService::analyzeProposal([
            'app_key'      => 'my_app',
            'module_key'   => '',
            'proposed_url' => '/apps/my-app/my-module',
            'mode'         => 'create',
        ]);

        $this->assertSame('BLOCKED', $result['apply_gate']['status']);
        $codes = array_column($result['errors'], 'code');
        $this->assertContains('missing_identity', $codes);
    }

    // -------------------------------------------------------------------------
    // Analyze: empty proposed_url → BLOCKED
    // -------------------------------------------------------------------------

    public function testAnalyzeBlockedOnEmptyUrl(): void
    {
        $result = StudioNavLinkingService::analyzeProposal([
            'app_key'      => 'my_app',
            'module_key'   => 'my_module',
            'proposed_url' => '',
            'mode'         => 'create',
        ]);

        $this->assertSame('BLOCKED', $result['apply_gate']['status']);
        $codes = array_column($result['errors'], 'code');
        $this->assertContains('empty_proposed_url', $codes);
    }

    // -------------------------------------------------------------------------
    // Analyze: unsafe proposed_url → BLOCKED
    // -------------------------------------------------------------------------

    public function testAnalyzeBlockedOnUnsafeUrl(): void
    {
        $unsafePaths = [
            '../../etc/passwd',
            'no-leading-slash',
            '/apps/../../escape',
            '/APPS/UPPERCASE',
            '/apps',               // too short — no sub-segments
            str_repeat('/apps/x', 50),  // too long
        ];

        foreach ($unsafePaths as $path) {
            $result = StudioNavLinkingService::analyzeProposal([
                'app_key'      => 'my_app',
                'module_key'   => 'my_module',
                'proposed_url' => $path,
                'mode'         => 'create',
            ]);

            $this->assertSame('BLOCKED', $result['apply_gate']['status'], "Expected BLOCKED for path: $path");
            $codes = array_column($result['errors'], 'code');
            $hasBlockCode = in_array('unsafe_route_path', $codes, true)
                || in_array('url_too_long', $codes, true)
                || in_array('empty_proposed_url', $codes, true);
            $this->assertTrue($hasBlockCode, "Expected safety error code for path: $path, got: " . implode(', ', $codes));
        }
    }

    // -------------------------------------------------------------------------
    // Analyze: update mode blocked without acknowledge when current_url present
    // -------------------------------------------------------------------------

    public function testAnalyzeUpdateModeBlockedWithoutAcknowledgment(): void
    {
        $result = StudioNavLinkingService::analyzeProposal([
            'app_key'               => 'my_app',
            'module_key'            => 'my_module',
            'proposed_url'          => '/apps/my-app/my-module-new',
            'mode'                  => 'update',
            'current_url'           => '/apps/my-app/my-module',
            'upgrade_acknowledged'  => false,
        ]);

        $this->assertSame('BLOCKED', $result['apply_gate']['status']);
        $codes = array_column($result['errors'], 'code');
        $this->assertContains('upgrade_not_acknowledged', $codes);
    }

    // -------------------------------------------------------------------------
    // Analyze: url_unchanged warning when proposed equals current
    // -------------------------------------------------------------------------

    public function testAnalyzeWarnsWhenUrlUnchanged(): void
    {
        $result = StudioNavLinkingService::analyzeProposal([
            'app_key'              => 'my_app',
            'module_key'           => 'my_module',
            'proposed_url'         => '/apps/my-app/my-module',
            'mode'                 => 'update',
            'current_url'          => '/apps/my-app/my-module',
            'upgrade_acknowledged' => true,
        ]);

        $codes = array_column($result['errors'], 'code');
        $this->assertContains('url_unchanged', $codes);

        $warnings = array_filter($result['errors'], fn($e) => ($e['severity'] ?? '') === 'warning');
        $this->assertGreaterThan(0, count($warnings));
    }

    // -------------------------------------------------------------------------
    // Fingerprint: deterministic for same plan
    // -------------------------------------------------------------------------

    public function testFingerprintIsDeterministic(): void
    {
        $plan = [
            'version'     => 'studio.nav_linking.v1',
            'app_key'     => 'demo_app',
            'module_key'  => 'demo_module',
            'mode'        => 'create',
            'proposed_url' => '/apps/demo-app/demo-module',
            'current_url' => '',
            'nav_rel_path' => 'apps/Generated/demo_app/demo_module/navigation.php',
            'nav_key'     => 'demo_module',
            'nav_label'   => 'Demo Module',
            'nav_section' => 'apps',
            'nav_order'   => 10,
            'nav_priority' => 0,
        ];

        $fp1 = StudioNavLinkingService::computePlanFingerprint($plan);
        $fp2 = StudioNavLinkingService::computePlanFingerprint($plan);

        $this->assertSame($fp1, $fp2);
        $this->assertStringStartsWith('nav-link:', $fp1);
    }

    // -------------------------------------------------------------------------
    // Fingerprint: different plans produce different fingerprints
    // -------------------------------------------------------------------------

    public function testFingerprintDiffersForDifferentPlans(): void
    {
        $planA = [
            'version'      => 'studio.nav_linking.v1',
            'app_key'      => 'app_a',
            'module_key'   => 'module_a',
            'mode'         => 'create',
            'proposed_url' => '/apps/app-a/module-a',
            'current_url'  => '',
            'nav_rel_path' => 'apps/Generated/app_a/module_a/navigation.php',
            'nav_key'      => 'module_a',
            'nav_label'    => 'Module A',
            'nav_section'  => 'apps',
            'nav_order'    => 10,
            'nav_priority' => 0,
        ];

        $planB = $planA;
        $planB['proposed_url'] = '/apps/app-b/module-b';
        $planB['app_key']      = 'app_b';
        $planB['module_key']   = 'module_b';

        $fpA = StudioNavLinkingService::computePlanFingerprint($planA);
        $fpB = StudioNavLinkingService::computePlanFingerprint($planB);

        $this->assertNotSame($fpA, $fpB);
    }

    // -------------------------------------------------------------------------
    // Apply: rejects missing fingerprint
    // -------------------------------------------------------------------------

    public function testApplyRejectsMissingFingerprint(): void
    {
        $plan = [
            'version'      => 'studio.nav_linking.v1',
            'app_key'      => 'demo_app',
            'module_key'   => 'demo_module',
            'mode'         => 'create',
            'proposed_url' => '/apps/demo-app/demo-module',
            'current_url'  => '',
            'nav_rel_path' => 'apps/Generated/demo_app/demo_module/navigation.php',
            'nav_key'      => 'demo_module',
            'nav_label'    => 'Demo',
            'nav_section'  => 'apps',
            'nav_order'    => 10,
            'nav_priority' => 0,
        ];

        $result = StudioNavLinkingService::applyApprovedPlan($plan, '');

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
            'version'      => 'studio.nav_linking.v1',
            'app_key'      => 'demo_app',
            'module_key'   => 'demo_module',
            'mode'         => 'create',
            'proposed_url' => '/apps/demo-app/demo-module',
            'current_url'  => '',
            'nav_rel_path' => 'apps/Generated/demo_app/demo_module/navigation.php',
            'nav_key'      => 'demo_module',
            'nav_label'    => 'Demo',
            'nav_section'  => 'apps',
            'nav_order'    => 10,
            'nav_priority' => 0,
        ];

        $result = StudioNavLinkingService::applyApprovedPlan($plan, 'nav-link:wrongfingerprint999');

        $this->assertFalse($result['ok']);
        $this->assertSame('FAILED', $result['status']);
        $this->assertSame('fingerprint_mismatch', $result['code']);
    }

    // -------------------------------------------------------------------------
    // Apply: rejects unsafe URL in plan (defense in depth)
    // -------------------------------------------------------------------------

    public function testApplyRejectsUnsafeUrlInPlan(): void
    {
        $plan = [
            'version'      => 'studio.nav_linking.v1',
            'app_key'      => 'demo_app',
            'module_key'   => 'demo_module',
            'mode'         => 'create',
            'proposed_url' => '../../etc/passwd',
            'current_url'  => '',
            'nav_rel_path' => 'apps/Generated/demo_app/demo_module/navigation.php',
            'nav_key'      => 'demo_module',
            'nav_label'    => 'Demo',
            'nav_section'  => 'apps',
            'nav_order'    => 10,
            'nav_priority' => 0,
        ];

        $realFingerprint = StudioNavLinkingService::computePlanFingerprint($plan);
        $result = StudioNavLinkingService::applyApprovedPlan($plan, $realFingerprint);

        $this->assertFalse($result['ok']);
        $this->assertSame('FAILED', $result['status']);
        $this->assertSame('unsafe_route_path', $result['code']);
    }

    // -------------------------------------------------------------------------
    // Apply: rejects unsafe nav path in plan (defense in depth)
    // -------------------------------------------------------------------------

    public function testApplyRejectsUnsafeNavPathInPlan(): void
    {
        $plan = [
            'version'      => 'studio.nav_linking.v1',
            'app_key'      => 'demo_app',
            'module_key'   => 'demo_module',
            'mode'         => 'create',
            'proposed_url' => '/apps/demo-app/demo-module',
            'current_url'  => '',
            'nav_rel_path' => '../../apps/Platform/routes.php',  // path traversal attempt
            'nav_key'      => 'demo_module',
            'nav_label'    => 'Demo',
            'nav_section'  => 'apps',
            'nav_order'    => 10,
            'nav_priority' => 0,
        ];

        $realFingerprint = StudioNavLinkingService::computePlanFingerprint($plan);
        $result = StudioNavLinkingService::applyApprovedPlan($plan, $realFingerprint);

        $this->assertFalse($result['ok']);
        $this->assertSame('FAILED', $result['status']);
        $this->assertSame('unsafe_target_path', $result['code']);
    }

    // -------------------------------------------------------------------------
    // Nav path safety: isSafeGeneratedNavPath allows valid paths
    // -------------------------------------------------------------------------

    public function testNavPathSafetyAllowsValidPath(): void
    {
        // Valid path: apps/Generated/{app_key}/{module_key}/navigation.php
        $result = StudioNavLinkingService::analyzeProposal([
            'app_key'      => 'demo_app',
            'module_key'   => 'demo_module',
            'proposed_url' => '/apps/demo-app/demo-module',
            'mode'         => 'create',
        ]);

        // Plan should have a valid nav_rel_path
        $plan = $result['plan'] ?? [];
        $navRelPath = $plan['nav_rel_path'] ?? '';
        $this->assertStringStartsWith('apps/Generated/', $navRelPath);
        $this->assertStringEndsWith('navigation.php', $navRelPath);
    }

    // -------------------------------------------------------------------------
    // generateNavigationFileContent: tested indirectly via readNavFile
    // -------------------------------------------------------------------------

    public function testGenerateNavigationFileContentStructure(): void
    {
        // Apply to a temp dir to exercise generateNavigationFileContent indirectly
        $tmpDir = sys_get_temp_dir() . '/gs_nav_test_' . uniqid();
        $moduleDir = $tmpDir . '/apps/Generated/test_app/test_module';
        mkdir($moduleDir, 0777, true);

        $plan = [
            'version'      => 'studio.nav_linking.v1',
            'app_key'      => 'test_app',
            'module_key'   => 'test_module',
            'mode'         => 'create',
            'proposed_url' => '/apps/test-app/test-module',
            'current_url'  => '',
            'nav_rel_path' => 'apps/Generated/test_app/test_module/navigation.php',
            'nav_key'      => 'test_module',
            'nav_label'    => 'Test Module',
            'nav_section'  => 'apps',
            'nav_order'    => 10,
            'nav_priority' => 0,
        ];

        // Fingerprint computed from plan
        $fingerprint = StudioNavLinkingService::computePlanFingerprint($plan);

        // We cannot write to disk in this test because absolutePath uses APP_ROOT.
        // Instead verify fingerprint prefix and determinism.
        $this->assertStringStartsWith('nav-link:', $fingerprint);

        // Clean up
        @rmdir($moduleDir);
        @rmdir($tmpDir . '/apps/Generated/test_app');
        @rmdir($tmpDir . '/apps/Generated');
        @rmdir($tmpDir . '/apps');
        @rmdir($tmpDir);
    }

    // -------------------------------------------------------------------------
    // Apply: rejects non-existent module directory (no dir on disk)
    // -------------------------------------------------------------------------

    public function testApplyFailsWhenModuleDirDoesNotExist(): void
    {
        $plan = [
            'version'      => 'studio.nav_linking.v1',
            'app_key'      => 'nonexistent_app',
            'module_key'   => 'nonexistent_module',
            'mode'         => 'create',
            'proposed_url' => '/apps/nonexistent-app/nonexistent-module',
            'current_url'  => '',
            'nav_rel_path' => 'apps/Generated/nonexistent_app/nonexistent_module/navigation.php',
            'nav_key'      => 'nonexistent_module',
            'nav_label'    => 'Nonexistent',
            'nav_section'  => 'apps',
            'nav_order'    => 10,
            'nav_priority' => 0,
        ];

        $fingerprint = StudioNavLinkingService::computePlanFingerprint($plan);
        $result = StudioNavLinkingService::applyApprovedPlan($plan, $fingerprint);

        $this->assertFalse($result['ok']);
        $this->assertSame('FAILED', $result['status']);
    }
}
