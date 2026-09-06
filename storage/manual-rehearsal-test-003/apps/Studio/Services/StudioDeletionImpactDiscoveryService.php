<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

require_once __DIR__ . '/StudioOwnerDiscoveryService.php';
require_once __DIR__ . '/StudioReferenceDiscoveryService.php';
require_once __DIR__ . '/StudioDeletionImpactDiscoveryCoreTrait.php';
require_once __DIR__ . '/StudioDeletionImpactDiscoveryTargetTrait.php';
require_once __DIR__ . '/StudioDeletionImpactDiscoveryPolicyTrait.php';

/**
 * Canonical read-only Studio capability for assessing the impact of deleting
 * an owner or owner-owned component. It composes canonical owner and reference
 * discovery and never mutates repository state.
 */
final class StudioDeletionImpactDiscoveryService
{
    public const EFFECT = 'read';

    public const TARGET_OWNER = 'owner';
    public const TARGET_COMPONENT = 'component';

    public const READINESS_READY = 'ready';
    public const READINESS_READY_WITH_CLEANUP = 'ready_with_cleanup';
    public const READINESS_NEEDS_REVIEW = 'needs_review';
    public const READINESS_BLOCKED = 'blocked';
    public const READINESS_UNKNOWN = 'unknown';

    use StudioDeletionImpactDiscoveryCoreTrait;
    use StudioDeletionImpactDiscoveryTargetTrait;
    use StudioDeletionImpactDiscoveryPolicyTrait;
}
