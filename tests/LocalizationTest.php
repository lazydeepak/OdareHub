<?php
declare(strict_types=1);
use App\Core\Localization\LocalizationService;
use PHPUnit\Framework\TestCase;

final class LocalizationTest extends TestCase
{
    public function testTranslateExisting()
    {
        LocalizationService::load('en_US');
        $this->assertEquals('Platform Dashboard', LocalizationService::translate('action.platform_dashboard'));
    }

    public function testTranslateFallback()
    {
        LocalizationService::load('en_US');
        $this->assertEquals('nonexistent.key', LocalizationService::translate('nonexistent.key'));
    }
}
