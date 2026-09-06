<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

require_once dirname(__DIR__, 3) . '/Services/StudioDeletionChangeSetService.php';

use Apps\Studio\Services\StudioDeletionChangeSetService;

/** Owner Structure adapter for the canonical read-only deletion change-set capability. */
final class OwnerStructureDeletionChangeSetService
{
    /**
     * @param array<string,mixed> $selectedOwner
     * @param array<string,mixed> $options
     * @param array<string,mixed>|null $deletionPlan
     * @return array<string,mixed>
     */
    public static function build(
        array $selectedOwner,
        array $options = [],
        ?array $deletionPlan = null,
        ?string $root = null
    ): array {
        $request = array_merge($options, [
            'owner_key' => (string)($selectedOwner['owner_key'] ?? ''),
            'target_type' => (string)($options['target_type'] ?? 'owner'),
        ]);

        return $deletionPlan !== null
            ? StudioDeletionChangeSetService::compose($request, $deletionPlan)
            : StudioDeletionChangeSetService::build($request, $root);
    }
}
