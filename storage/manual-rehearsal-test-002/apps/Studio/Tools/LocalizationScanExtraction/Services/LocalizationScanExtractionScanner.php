<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\LocalizationScanExtraction\Services;

final class LocalizationScanExtractionScanner
{
    public static function scan(string $ownerKey, string $scope, string $referenceLocale): array
    {
        return LocalizationScanService::scan($ownerKey, $scope, $referenceLocale);
    }

    public static function pathGuardProbe(string $ownerKey): array
    {
        return LocalizationScanService::pathGuardProbe($ownerKey);
    }

    public static function scanFixture(string $ownerKey, array $referenceKeys, array $files): array
    {
        return LocalizationScanService::scanFixture($ownerKey, $referenceKeys, $files);
    }

    public static function safeInternalReturnTo(string $returnTo): string
    {
        return LocalizationScanService::safeInternalReturnTo($returnTo);
    }

    public static function buildEditorHandoffUrl(string $ownerKey, string $locale, string $key, string $returnTo): string
    {
        return LocalizationScanService::buildEditorHandoffUrl($ownerKey, $locale, $key, $returnTo);
    }
}
