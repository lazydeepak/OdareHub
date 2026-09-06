<?php
declare(strict_types=1);

namespace Tests;

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require_once __DIR__ . '/../vendor/autoload.php';

use Apps\Shell\Services\WorkspaceWrapperRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for the Operator Layer shell contracts.
 *
 * Covers:
 * - WorkspaceWrapperRegistry data contracts (normalizeHandle, handleFromIdentity)
 * - Landing path resolution for account types
 * - Wrapper access policy (platform_admin vs app_user)
 * - Edge cases: empty input, email handles, username priority
 */
final class OperatorLayerShellRegressionTest extends TestCase
{
    // ─── normalizeHandle ───────────────────────────────────────────────────────

    public function testNormalizeHandleStripsEmailDomain(): void
    {
        $this->assertSame('lazydeepak', WorkspaceWrapperRegistry::normalizeHandle('lazydeepak@outlook.com'));
    }

    public function testNormalizeHandleLowercasesInput(): void
    {
        $this->assertSame('lazydeepa', WorkspaceWrapperRegistry::normalizeHandle('LazyDeepa'));
    }

    public function testNormalizeHandleStripsLeadingTrailingDashes(): void
    {
        $handle = WorkspaceWrapperRegistry::normalizeHandle('  --john--  ');
        $this->assertStringStartsNotWith('-', $handle);
        $this->assertStringEndsNotWith('-', $handle);
    }

    public function testNormalizeHandleReturnsEmptyStringForEmptyInput(): void
    {
        $this->assertSame('', WorkspaceWrapperRegistry::normalizeHandle(''));
        $this->assertSame('', WorkspaceWrapperRegistry::normalizeHandle('   '));
    }

    public function testNormalizeHandlePreservesAllowedChars(): void
    {
        $this->assertSame('john.doe', WorkspaceWrapperRegistry::normalizeHandle('john.doe'));
        $this->assertSame('john-doe', WorkspaceWrapperRegistry::normalizeHandle('john-doe'));
        $this->assertSame('john_doe', WorkspaceWrapperRegistry::normalizeHandle('john_doe'));
    }

    public function testNormalizeHandleReplacesSpecialCharsWithDash(): void
    {
        $handle = WorkspaceWrapperRegistry::normalizeHandle('john doe');
        $this->assertSame('john-doe', $handle);
    }

    // ─── handleFromIdentity ────────────────────────────────────────────────────

    public function testHandleFromIdentityPrefersUsernameOverEmail(): void
    {
        $handle = WorkspaceWrapperRegistry::handleFromIdentity([
            'username' => 'lazy',
            'email'    => 'lazy@example.com',
        ]);
        $this->assertSame('lazy', $handle);
    }

    public function testHandleFromIdentityFallsBackToEmailLocalPart(): void
    {
        $handle = WorkspaceWrapperRegistry::handleFromIdentity([
            'username' => '',
            'email'    => 'lazydeepak@outlook.com',
        ]);
        $this->assertSame('lazydeepak', $handle);
    }

    public function testHandleFromIdentityReturnsEmptyForEmptyIdentity(): void
    {
        $handle = WorkspaceWrapperRegistry::handleFromIdentity([]);
        $this->assertSame('', $handle);
    }

    public function testHandleFromIdentityReturnsEmptyWhenBothBlank(): void
    {
        $handle = WorkspaceWrapperRegistry::handleFromIdentity([
            'username' => '   ',
            'email'    => '',
        ]);
        $this->assertSame('', $handle);
    }

    /**
     * Critical regression: Auth::user() does NOT select the `username` column,
     * so `username` arrives as an empty string. Identity must fall through to
     * the email local-part and NEVER pick up an external URL parameter.
     *
     * If this test fails the identity guard bypass from 5d71b3e is re-introduced.
     */
    public function testHandleFromIdentityDoesNotUseUrlParameterAsFallback(): void
    {
        // Simulate Auth::user() result: no username column in SELECT
        $authUserResult = [
            'id'           => 2,
            'email'        => 'worker@demo.local',
            'role'         => 'Productionworker',
            'authority_role' => 'app_user',
            // 'username' column NOT in Auth::user() SELECT — absent
        ];

        $urlParam = 'someotheruser'; // attacker-controlled /u/someotheruser/...

        // This is the correct pattern used in OperatorLayerService after the fix:
        $resolvedHandle = WorkspaceWrapperRegistry::handleFromIdentity([
            'username' => (string)($authUserResult['username'] ?? $authUserResult['user_name'] ?? ''),
            'email'    => (string)($authUserResult['email'] ?? ''),
        ]);

        // Must resolve from email, never from $urlParam
        $this->assertSame('worker', $resolvedHandle, 'Handle must come from email, not URL parameter');
        $this->assertNotSame($urlParam, $resolvedHandle, 'Handle must not match attacker-controlled URL param');
    }

    // ─── Wrapper resolution ────────────────────────────────────────────────────

    public function testPlatformAdminLandsInAdminWrapper(): void
    {
        $wrapper = WorkspaceWrapperRegistry::defaultWrapperForContext(['authority_role' => 'platform_admin']);
        $this->assertSame(WorkspaceWrapperRegistry::WRAPPER_ADMIN, $wrapper);
    }

    public function testAppUserLandsInUWrapper(): void
    {
        $wrapper = WorkspaceWrapperRegistry::defaultWrapperForContext(['authority_role' => 'app_user']);
        $this->assertSame(WorkspaceWrapperRegistry::WRAPPER_U, $wrapper);
    }

    public function testUnknownAccountTypeLandsInUWrapper(): void
    {
        $wrapper = WorkspaceWrapperRegistry::defaultWrapperForContext([]);
        $this->assertSame(WorkspaceWrapperRegistry::WRAPPER_U, $wrapper);
    }

    // ─── canAccessWrapper ──────────────────────────────────────────────────────

    public function testPlatformAdminCanAccessAdminWrapper(): void
    {
        $ctx = ['authority_role' => 'platform_admin'];
        $this->assertTrue(WorkspaceWrapperRegistry::canAccessWrapper($ctx, WorkspaceWrapperRegistry::WRAPPER_ADMIN));
    }

    public function testPlatformAdminCanAccessUWrapper(): void
    {
        $ctx = ['authority_role' => 'platform_admin'];
        $this->assertTrue(WorkspaceWrapperRegistry::canAccessWrapper($ctx, WorkspaceWrapperRegistry::WRAPPER_U));
    }

    public function testAppUserCannotAccessAdminWrapper(): void
    {
        $ctx = ['authority_role' => 'app_user'];
        $this->assertFalse(WorkspaceWrapperRegistry::canAccessWrapper($ctx, WorkspaceWrapperRegistry::WRAPPER_ADMIN));
    }

    public function testAppUserCanAccessUWrapper(): void
    {
        $ctx = ['authority_role' => 'app_user'];
        $this->assertTrue(WorkspaceWrapperRegistry::canAccessWrapper($ctx, WorkspaceWrapperRegistry::WRAPPER_U));
    }

    public function testNonExistentWrapperReturnsFalse(): void
    {
        $ctx = ['authority_role' => 'platform_admin'];
        $this->assertFalse(WorkspaceWrapperRegistry::canAccessWrapper($ctx, 'nonexistent-wrapper'));
    }

    // ─── landingPath ──────────────────────────────────────────────────────────

    public function testAppUserLandingPathPointsToUDashboard(): void
    {
        $ctx      = ['authority_role' => 'app_user'];
        $landing  = WorkspaceWrapperRegistry::landingPath($ctx, 'lazy');
        $this->assertSame('/u/lazy', $landing);
    }

    public function testPlatformAdminLandingPathPointsToAdminHome(): void
    {
        $ctx     = ['authority_role' => 'platform_admin'];
        $landing = WorkspaceWrapperRegistry::landingPath($ctx, 'admin');
        $this->assertSame('/admin/admin', $landing);
    }

    public function testLandingPathNormalizesEmailUsername(): void
    {
        $ctx     = ['authority_role' => 'app_user'];
        $landing = WorkspaceWrapperRegistry::landingPath($ctx, 'worker@demo.local');
        $this->assertSame('/u/worker', $landing);
    }

    // ─── wrapperForPath ────────────────────────────────────────────────────────

    public function testWrapperForPathResolvesUPrefix(): void
    {
        $this->assertSame('u', WorkspaceWrapperRegistry::wrapperForPath('/u/lazy/dashboard'));
    }

    public function testWrapperForPathResolvesMePrefix(): void
    {
        $this->assertSame(WorkspaceWrapperRegistry::WRAPPER_ADMIN, WorkspaceWrapperRegistry::wrapperForPath('/me'));
    }

    public function testWrapperForPathResolvesOpsPrefix(): void
    {
        $this->assertSame(WorkspaceWrapperRegistry::WRAPPER_ADMIN, WorkspaceWrapperRegistry::wrapperForPath('/ops/access-control'));
    }

    public function testWrapperForPathResolvesAppsPrefix(): void
    {
        $this->assertSame(WorkspaceWrapperRegistry::WRAPPER_ADMIN, WorkspaceWrapperRegistry::wrapperForPath('/apps/manufacturing/orders'));
    }

    public function testWrapperForPathResolvesAdminPrefix(): void
    {
        $this->assertSame(WorkspaceWrapperRegistry::WRAPPER_ADMIN, WorkspaceWrapperRegistry::wrapperForPath('/admin/lazydeepak'));
    }

    public function testWrapperForPathReturnsNullForEmptyPath(): void
    {
        $this->assertNull(WorkspaceWrapperRegistry::wrapperForPath(''));
    }

    public function testWrapperForPathReturnsAdminForArbitraryPath(): void
    {
        // Any path not under /u/* or /displays/* resolves to admin wrapper (catch-all).
        $this->assertSame(WorkspaceWrapperRegistry::WRAPPER_ADMIN, WorkspaceWrapperRegistry::wrapperForPath('/account'));
        $this->assertSame(WorkspaceWrapperRegistry::WRAPPER_ADMIN, WorkspaceWrapperRegistry::wrapperForPath('/login'));
        $this->assertSame(WorkspaceWrapperRegistry::WRAPPER_ADMIN, WorkspaceWrapperRegistry::wrapperForPath('/'));
    }
}
