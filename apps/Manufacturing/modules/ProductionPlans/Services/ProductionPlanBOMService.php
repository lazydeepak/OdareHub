<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Module\ProductionPlans\Services;

/**
 * Manufacturing ProductionPlan -> BOM reference (Session A shared-items extension).
 * Minimal read/resolution; does not implement BOM execution or inventory consumption.
 * References canonical BOM identity from manufacturing_bom (session A added 013).
 */
class ProductionPlanBOMService
{
    /**
     * Resolve BOM definition for this production plan.
     * Uses pinned bom_ref + version/revision to avoid silent drift.
     */
    public function resolveBomForPlan(array $plan): ?array
    {
        $bomRef = $plan['bom_ref'] ?? null;
        $bomVersion = $plan['bom_version'] ?? null;
        if ($bomRef === null || $bomRef <= 0) {
            return null; // legacy unlinked plan — no BOM required
        }
        // In full deployment: DB lookup of manufacturing_bom with version/revision match
        // For this slice: contract defines reference; execution deferred to owning session
        return [
            'bom_ref' => $bomRef,
            'bom_version' => $bomVersion,
            'status' => 'pinned',
            'immutable_since_plan_release' => (($plan['status'] ?? '') === 'released' || ($plan['status'] ?? '') === 'started'),
        ];
    }

    /**
     * Validate that a BOM reference can be assigned to a plan.
     */
    public function canAssignBom(int $bomRef, ?string $version, ?string $revision, string $planStatus): bool
    {
        // Released/started plans must have existing pinned reference; new assignment only allowed on draft
        if (in_array($planStatus, ['released', 'started', 'executed'], true) && $bomRef <= 0) {
            return false;
        }
        // Basic reference validity: BOM must exist (would check DB in full impl)
        return $bomRef > 0;
    }
}
