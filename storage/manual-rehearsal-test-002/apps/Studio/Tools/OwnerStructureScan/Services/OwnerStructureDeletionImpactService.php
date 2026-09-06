<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

require_once dirname(__DIR__, 3) . '/Services/StudioDeletionImpactDiscoveryService.php';

use Apps\Studio\Services\StudioDeletionImpactDiscoveryService;

/**
 * Owner Structure adapter for the canonical read-only deletion impact capability.
 */
final class OwnerStructureDeletionImpactService
{
    /**
     * @param array<string,mixed> $selectedOwner
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function assess(array $selectedOwner, array $options = [], ?string $root = null): array
    {
        $request = array_merge($options, [
            'owner_key' => (string)($selectedOwner['owner_key'] ?? ''),
            'target_type' => (string)($options['target_type'] ?? StudioDeletionImpactDiscoveryService::TARGET_OWNER),
        ]);

        return StudioDeletionImpactDiscoveryService::discover($request, $root);
    }
}
