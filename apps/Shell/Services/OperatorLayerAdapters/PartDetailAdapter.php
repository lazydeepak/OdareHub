<?php
declare(strict_types=1);

namespace Apps\Shell\Services\OperatorLayerAdapters;

use Apps\Manufacturing\Services\OperatorPartDetailContributionService;

final class PartDetailAdapter
{
    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function getData(array $query = [], string $username = ''): array
    {
        try {
            if (class_exists(OperatorPartDetailContributionService::class) && method_exists(OperatorPartDetailContributionService::class, 'getData')) {
                return OperatorPartDetailContributionService::getData($query, $username);
            }
        } catch (\Throwable $e) {
            error_log('PartDetailAdapter::getData: ' . $e->getMessage());
        }

        if (class_exists(OperatorPartDetailContributionService::class) && method_exists(OperatorPartDetailContributionService::class, 'defaults')) {
            return (array)OperatorPartDetailContributionService::defaults();
        }

        return [
            'product' => null,
            'state' => ['state_label' => '', 'tone' => 'info', 'stage' => ''],
            'metrics' => [],
            'context' => [],
            'actions' => [],
            'orders' => [],
            'activity' => [],
            'empty' => true,
            'error' => 'operator.parts.detail.error.load',
        ];
    }
}
