<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services\OperatorLayerAdapters;

use Apps\Manufacturing\Services\ProcessingOperationService;

require_once APP_ROOT . '/apps/Manufacturing/Services/ProcessingOperationService.php';

/** Manufacturing-owned read projection for the operator Processing surface. */
final class ProcessingAdapter
{
    /** @return array<string,mixed> */
    public static function getData(string $date, ?array $user, int $selectedProductId = 0, string $dashboardType = 'operator'): array
    {
        return ProcessingOperationService::operatorFocus($date, $user, $selectedProductId, $dashboardType);
    }
}
