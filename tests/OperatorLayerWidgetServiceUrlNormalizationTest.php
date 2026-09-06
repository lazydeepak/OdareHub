<?php
declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require_once __DIR__ . '/../app/Core/helpers.php';
require_once __DIR__ . '/../apps/Shell/Services/OperatorLayerWidgetService.php';

use Apps\Shell\Services\OperatorLayerWidgetService;
use PHPUnit\Framework\TestCase;

final class OperatorLayerWidgetServiceUrlNormalizationTest extends TestCase
{
    public function testNormalizesSbaioParityUrlIntoOperatorSurface(): void
    {
        $url = OperatorLayerWidgetService::normalizeOperatorWidgetUrl(
            '/apps/sbaio/payroll/parity',
            ['username' => 'lazydeepak']
        );

        $this->assertSame('/u/lazydeepak/sbaio?tab=payroll', $url);
    }

    public function testNormalizesManufacturingUrlIntoOperatorFocus(): void
    {
        $url = OperatorLayerWidgetService::normalizeOperatorWidgetUrl(
            '/apps/manufacturing/qc-plans/report',
            ['username' => 'lazydeepak']
        );

        $this->assertSame('/u/lazydeepak/qc', $url);
    }

    public function testKeepsSafeOperatorUrlsUntouched(): void
    {
        $url = OperatorLayerWidgetService::normalizeOperatorWidgetUrl(
            '/u/lazydeepak/dashboard',
            ['username' => 'lazydeepak']
        );

        $this->assertSame('/u/lazydeepak/dashboard', $url);
    }

    public function testFallsBackToOperatorDashboardForUnknownAppRoutes(): void
    {
        $url = OperatorLayerWidgetService::normalizeOperatorWidgetUrl(
            '/apps/unknown/system',
            ['username' => 'lazydeepak']
        );

        $this->assertSame('/u/lazydeepak/dashboard', $url);
    }
}
