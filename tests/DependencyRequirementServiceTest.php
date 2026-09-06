<?php
declare(strict_types=1);

use App\Services\DependencyRequirementService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/Services/DependencyRequirementService.php';

final class DependencyRequirementServiceTest extends TestCase
{
    public function testParseExtractsNameOperatorAndVersion(): void
    {
        $parsed = DependencyRequirementService::parse('QRCode>=1.0.0');

        $this->assertSame('QRCode', $parsed['name']);
        $this->assertSame('>=', $parsed['operator']);
        $this->assertSame('1.0.0', $parsed['version']);
    }

    public function testVersionConstraintMatchesSupportedVersion(): void
    {
        $this->assertTrue(DependencyRequirementService::isSatisfied('QRCode>=1.0.0', '1.0.0'));
        $this->assertTrue(DependencyRequirementService::isSatisfied('QRCode>=1.0.0', '1.4.2'));
    }

    public function testVersionConstraintRejectsOlderVersion(): void
    {
        $this->assertFalse(DependencyRequirementService::isSatisfied('QRCode>=1.0.0', '0.9.9'));
    }
}
