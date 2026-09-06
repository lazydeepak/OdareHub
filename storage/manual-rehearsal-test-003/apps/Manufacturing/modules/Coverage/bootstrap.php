<?php
declare(strict_types=1);

require_once __DIR__ . '/Services/CoverageService.php';

\Plugins\Coverage\Services\CoverageService::setResolvers(
    static function (array $productIds): void {
        $servicePath = APP_ROOT . '/plugins/Base/Services/HandoffTrackingService.php';
        if (!is_file($servicePath)) {
            return;
        }
        require_once $servicePath;

        if (class_exists('Plugins\\Base\\Services\\HandoffTrackingService')) {
            \Plugins\Base\Services\HandoffTrackingService::syncDailyOrderRiskForProducts($productIds);
        }
    },
    static function (): array {
        return [
            'app' => 'manufacturing',
            'module' => 'coverage',
        ];
    }
);
