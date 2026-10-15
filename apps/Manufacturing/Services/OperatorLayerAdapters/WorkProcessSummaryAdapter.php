<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services\OperatorLayerAdapters;

use Apps\Manufacturing\Services\OperatorWorkProcessSummaryService;

require_once APP_ROOT . '/apps/Manufacturing/Services/OperatorWorkProcessSummaryService.php';

/** Manufacturing-owned read-only dashboard contribution adapter. */
final class WorkProcessSummaryAdapter
{
    /** @return array<string,mixed> */
    public static function getData(
        string $date,
        ?array $user,
        array $context,
        string $username,
        ?callable $routeResolver = null
    ): array {
        return OperatorWorkProcessSummaryService::build($date, $user, $context, $username, $routeResolver);
    }
}
