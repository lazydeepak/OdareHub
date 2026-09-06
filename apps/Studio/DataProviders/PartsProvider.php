<?php
declare(strict_types=1);

namespace Apps\Studio\DataProviders;

use Apps\Studio\Authorization\StudioAuthorizationService;
use Apps\Studio\Repositories\PartsRepository;
use Apps\Studio\Repositories\StudioSchemaGovernanceService;

require_once __DIR__ . '/../Authorization/StudioAuthorizationService.php';
require_once __DIR__ . '/../Repositories/PartsRepository.php';
require_once __DIR__ . '/../Repositories/StudioSchemaGovernanceService.php';

final class PartsProvider implements StudioDataProvider
{
    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function fetch(array $context): array
    {
        if (!StudioAuthorizationService::can('parts', 'view', $context)) {
            return [];
        }

        $appKey = StudioSchemaGovernanceService::sanitizeAppKey((string)($context['app_key'] ?? 'default'));
        $authorizedContext = $context;
        $authorizedContext['_studio_authorized'] = true;

        try {
            $repo = new PartsRepository($appKey, $authorizedContext);
            $rows = $repo->find([]);
            return [
                'rows' => $rows,
                'schema_version' => $repo->schemaVersion(),
            ];
        } catch (\Throwable) {
            return ['rows' => [], 'schema_version' => 0];
        }
    }
}
