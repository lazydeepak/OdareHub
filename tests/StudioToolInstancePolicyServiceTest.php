<?php
declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

use Apps\Studio\Services\StudioToolInstancePolicyService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../apps/Studio/Services/StudioToolInstancePolicyService.php';

final class StudioToolInstancePolicyServiceTest extends TestCase
{
    public function testConfiguredPolicyEnablesReportAndLabelDesigner(): void
    {
        $policy = StudioToolInstancePolicyService::resolvePolicy();
        $studioTools = (array)($policy['studio_tools'] ?? []);

        $this->assertArrayHasKey('report_designer', $studioTools);
        $this->assertSame('enabled', $studioTools['report_designer']);
        $this->assertTrue(StudioToolInstancePolicyService::isEnabled('report_designer'));

        $this->assertArrayHasKey('label_designer', $studioTools);
        $this->assertSame('enabled', $studioTools['label_designer']);
        $this->assertTrue(StudioToolInstancePolicyService::isEnabled('label_designer'));
    }
}
