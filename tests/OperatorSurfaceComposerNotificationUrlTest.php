<?php
declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require_once __DIR__ . '/../app/Core/helpers.php';
require_once __DIR__ . '/../apps/Shell/Services/OperatorLayerWidgetService.php';
require_once __DIR__ . '/../apps/Shell/Composers/OperatorSurfaceComposer.php';

use Apps\Shell\Composers\OperatorSurfaceComposer;
use PHPUnit\Framework\TestCase;

final class OperatorSurfaceComposerNotificationUrlTest extends TestCase
{
    public function testNormalizesKnownAppRoutesIntoOperatorSurface(): void
    {
        $composer = $this->makeComposer('lazydeepak', ['authority_role' => 'app_user']);

        $this->assertSame(
            '/u/lazydeepak/qc',
            $this->normalizeUrl($composer, '/apps/manufacturing/qc-entries/edit?id=42', '/u/lazydeepak/notifications')
        );
    }

    public function testFallsBackForAdminSideRoutes(): void
    {
        $composer = $this->makeComposer('lazydeepak', ['authority_role' => 'app_user']);

        $this->assertSame(
            '/u/lazydeepak/notifications',
            $this->normalizeUrl($composer, '/production-entries/edit?id=42', '/u/lazydeepak/notifications')
        );
    }

    public function testAllowsApprovedMeBridgeOnlyForPlatformAdmin(): void
    {
        $adminComposer = $this->makeComposer('lazydeepak', ['authority_role' => 'platform_admin']);
        $operatorComposer = $this->makeComposer('lazydeepak', ['authority_role' => 'app_user']);

        $this->assertSame('/me', $this->normalizeUrl($adminComposer, '/me', '/u/lazydeepak/notifications'));
        $this->assertSame('/u/lazydeepak/notifications', $this->normalizeUrl($operatorComposer, '/me', '/u/lazydeepak/notifications'));
    }

    private function makeComposer(string $username, array $context): OperatorSurfaceComposer
    {
        $reflection = new \ReflectionClass(OperatorSurfaceComposer::class);
        $composer = $reflection->newInstanceWithoutConstructor();

        $initializer = \Closure::bind(static function (OperatorSurfaceComposer $instance, string $username, array $context): void {
            $instance->username = $username;
            $instance->context = $context;
        }, null, OperatorSurfaceComposer::class);
        $initializer($composer, $username, $context);

        return $composer;
    }

    private function normalizeUrl(OperatorSurfaceComposer $composer, string $actionUrl, string $fallback): string
    {
        $normalizer = \Closure::bind(static function (OperatorSurfaceComposer $instance, string $actionUrl, string $fallback): string {
            return $instance->normalizeOperatorNotificationUrl($actionUrl, $fallback);
        }, null, OperatorSurfaceComposer::class);

        return $normalizer($composer, $actionUrl, $fallback);
    }
}