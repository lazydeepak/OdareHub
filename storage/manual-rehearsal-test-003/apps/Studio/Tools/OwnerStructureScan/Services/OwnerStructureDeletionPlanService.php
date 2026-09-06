<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

require_once dirname(__DIR__, 3) . '/Services/StudioDeletionPlanService.php';

use Apps\Studio\Services\StudioDeletionPlanService;

/**
 * Owner Structure adapter for the canonical read-only deletion-plan capability.
 */
final class OwnerStructureDeletionPlanService
{
    /**
     * @param array<string,mixed> $selectedOwner
     * @param array<string,mixed> $options
     * @param array<string,mixed>|null $impactAssessment
     * @return array<string,mixed>
     */
    public static function plan(
        array $selectedOwner,
        array $options = [],
        ?array $impactAssessment = null,
        ?string $root = null
    ): array {
        $request = array_merge($options, [
            'owner_key' => (string)($selectedOwner['owner_key'] ?? ''),
            'target_type' => (string)($options['target_type'] ?? 'owner'),
        ]);

        return $impactAssessment !== null
            ? StudioDeletionPlanService::compose($request, $impactAssessment)
            : StudioDeletionPlanService::plan($request, $root);
    }
}
