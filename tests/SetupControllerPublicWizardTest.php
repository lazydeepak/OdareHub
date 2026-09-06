<?php
declare(strict_types=1);

use App\AppManager\Controllers\SetupController;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

final class SetupControllerPublicWizardTest extends TestCase
{
    public function testLegacyStepAliasesNormalizeToCanonicalKeys(): void
    {
        $this->assertSame('setup_2fa', $this->invokePrivate('publicWizardNormalizeStep', 'admin_2fa'));
        $this->assertSame('core_install', $this->invokePrivate('publicWizardNormalizeStep', 'platform'));
        $this->assertSame('verify', $this->invokePrivate('publicWizardNormalizeStep', 'finish'));
    }

    public function testBlankSetupRequestResumesTheNextRequiredStep(): void
    {
        $state = $this->validatedDatabaseState([
            'platform_validated' => true,
        ]);

        $this->assertSame('admin', $this->invokePrivate('publicWizardResolvedStep', $state, ''));
    }

    public function testStartedWizardStillRequiresReadinessUntilChecksRun(): void
    {
        $state = $this->baseState([
            'readiness' => ['checks' => []],
        ]);

        $this->assertSame('readiness', $this->invokePrivate('publicWizardNextRequiredStep', $state));
        $this->assertSame('readiness', $this->invokePrivate('publicWizardMaxSafeStep', $state));
    }

    public function testCoreInstallCompletionUnlocksAdminStep(): void
    {
        $state = $this->validatedDatabaseState([
            'platform_validated' => true,
        ]);

        $this->assertSame('admin', $this->invokePrivate('publicWizardMaxSafeStep', $state));
        $this->assertSame('admin', $this->invokePrivate('publicWizardResolvedStep', $state, 'admin'));
    }

    public function testSuccessfulInstallCanProceedToVerifyWhenBootstrap2faIsOptional(): void
    {
        $state = $this->validatedDatabaseState([
            'platform_validated' => true,
            'install_ready' => true,
            'install_run' => ['status' => 'configured'],
            'data' => [
                'admin_email' => 'admin@example.com',
            ],
        ]);

        $this->assertSame('verify', $this->invokePrivate('publicWizardNextRequiredStep', $state));
        $this->assertSame('verify', $this->invokePrivate('publicWizardMaxSafeStep', $state));
        $this->assertSame('verify', $this->invokePrivate('publicWizardResolvedStep', $state, 'verify'));
    }

    public function testStepRedirectWarningOnlyTriggersForRealForwardBlocks(): void
    {
        $state = $this->validatedDatabaseState([
            'platform_validated' => true,
        ]);

        $this->assertTrue($this->invokePrivate(
            'publicWizardShouldWarnForStepRedirect',
            $state,
            'verify',
            'verify',
            'admin'
        ));

        $this->assertFalse($this->invokePrivate(
            'publicWizardShouldWarnForStepRedirect',
            $state,
            'platform',
            'core_install',
            'core_install'
        ));
    }

    public function testPublicWizardSessionSanitizerRemovesSecrets(): void
    {
        $state = $this->baseState([
            'data' => [
                'db_pass' => 'secret',
                'admin_password' => 'StrongPass123',
                'admin_password_confirm' => 'StrongPass123',
                'admin_email' => 'admin@example.com',
            ],
            'db_pass' => 'secret',
            'admin_password' => 'StrongPass123',
            'admin_password_confirm' => 'StrongPass123',
        ]);

        $sanitized = $this->invokePrivate('publicWizardSanitizeSessionState', $state);

        $this->assertArrayNotHasKey('db_pass', $sanitized['data']);
        $this->assertArrayNotHasKey('admin_password', $sanitized['data']);
        $this->assertArrayNotHasKey('admin_password_confirm', $sanitized['data']);
        $this->assertArrayNotHasKey('db_pass', $sanitized);
        $this->assertArrayNotHasKey('admin_password', $sanitized);
        $this->assertArrayNotHasKey('admin_password_confirm', $sanitized);
        $this->assertSame('admin@example.com', $sanitized['data']['admin_email']);
    }

    public function testInstallDbReloadMustMatchVerifiedMetadata(): void
    {
        $state = $this->validatedDatabaseState();
        $savedConfig = [
            'host' => 'localhost',
            'port' => 3306,
            'name' => 'erp_test',
            'user' => 'erp_user',
            'pass' => '',
            'charset' => 'utf8mb4',
        ];

        $this->assertTrue($this->invokePrivate('publicWizardSavedDbConfigMatchesVerified', $savedConfig, $state));

        $savedConfig['pass'] = 'changed-after-test';
        $this->assertFalse($this->invokePrivate('publicWizardSavedDbConfigMatchesVerified', $savedConfig, $state));
    }

    public function testDbFieldChangesInvalidateVerification(): void
    {
        $state = $this->validatedDatabaseState([
            'platform_validated' => true,
        ]);
        $previous = $state['data'];
        $next = array_merge($previous, ['db_name' => 'changed_db']);

        $invalidated = $this->invokePrivate('publicWizardInvalidateChangedValidation', $state, $previous, $next);

        $this->assertSame([], $invalidated['db_test']);
        $this->assertSame([], $invalidated['verified_db']);
        $this->assertFalse($invalidated['platform_validated']);
        $this->assertSame([], $invalidated['verified_platform']);
        $this->assertFalse($invalidated['install_ready']);
    }

    /**
     * @param mixed ...$args
     */
    private function invokePrivate(string $method, mixed ...$args): mixed
    {
        $reflection = new ReflectionMethod(SetupController::class, $method);
        return $reflection->invoke(null, ...$args);
    }

    /**
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    private function validatedDatabaseState(array $overrides = []): array
    {
        $state = $this->baseState($overrides);
        $dbConfig = $this->invokePrivate('publicWizardDbConfig', $state['data']);
        $state['db_test'] = [
            'ok' => true,
        ];
        $state['verified_db'] = $this->invokePrivate(
            'publicWizardVerifiedDbMetadata',
            $dbConfig,
            (string)($state['data']['db_create_if_missing'] ?? '1') === '1'
        );
        if (!empty($state['platform_validated'])) {
            $state['verified_platform'] = $this->invokePrivate('publicWizardVerifiedPlatformMetadata', $state);
        }

        return $state;
    }

    /**
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    private function baseState(array $overrides = []): array
    {
        $defaultData = [
            'db_host' => 'localhost',
            'db_port' => '3306',
            'db_name' => 'erp_test',
            'db_user' => 'erp_user',
            'db_pass_configured' => '0',
            'db_charset' => 'utf8mb4',
            'db_create_if_missing' => '1',
            'instance_name' => 'ERP Engine',
            'language' => 'en',
            'number_format' => '1,234.56',
            'currency' => 'JPY',
            'timezone' => 'Asia/Tokyo',
            'theme' => 'system-obsidian',
            'workspace_home' => '/admin/setup',
            'admin_email' => '',
        ];

        $dataOverrides = is_array($overrides['data'] ?? null) ? $overrides['data'] : [];
        unset($overrides['data']);

        return array_merge([
            'started' => true,
            'readiness' => [
                'checks' => [
                    ['required' => true, 'status' => 'passed'],
                ],
            ],
            'db_test' => [],
            'verified_db' => [],
            'verified_platform' => [],
            'platform_validated' => false,
            'install_ready' => false,
            'install_run' => [],
            'data' => array_merge($defaultData, $dataOverrides),
        ], $overrides);
    }
}
