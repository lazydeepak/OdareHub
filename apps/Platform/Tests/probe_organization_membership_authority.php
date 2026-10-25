<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/apps/Platform/Services/OrganizationMembershipContext.php';
require_once $root . '/apps/Platform/Services/OrganizationMembershipResolver.php';

use Apps\Platform\Services\OrganizationMembershipResolver;

$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$active = static function (int $userId, int $organizationId, array $extra = []): array {
    return array_merge([
        'user_id' => $userId,
        'organization_id' => $organizationId,
        'is_active' => true,
        'is_revoked' => false,
        'organization_is_active' => true,
    ], $extra);
};

$at = new DateTimeImmutable('2026-10-26T12:00:00+00:00');

$resolved = OrganizationMembershipResolver::resolve(7, [$active(7, 11)], null, $at);
$assert($resolved['allowed'] === true, 'one active membership resolves');
$assert($resolved['status'] === 'resolved', 'resolved status is explicit');
$assert($resolved['context']?->organizationId() === 11, 'resolved context contains selected organization');
$assert($resolved['context']?->userId() === 7, 'resolved context remains bound to users.id');
$assert(strlen((string)$resolved['context']?->evidenceFingerprint()) === 64, 'context exposes deterministic evidence fingerprint');
$windowedEvidence = $active(7, 11, [
    'valid_from' => '2026-01-01T00:00:00+00:00',
    'valid_until' => '2026-12-31T23:59:59+00:00',
]);
$windowed = OrganizationMembershipResolver::resolve(7, [$windowedEvidence], null, $at);
$windowedRepeat = OrganizationMembershipResolver::resolve(7, [$windowedEvidence], null, $at);
$assert($windowed['allowed'] === true, 'membership inside validity window resolves');
$assert($windowed['context']?->evidenceFingerprint() === $windowedRepeat['context']?->evidenceFingerprint(), 'same evidence produces the same fingerprint');
$assert(OrganizationMembershipResolver::resolve(7, [$active(7, 11, ['valid_until' => '2026-10-25T23:59:59+00:00'])], null, $at)['allowed'] === false, 'stale membership fails closed');

$multiple = OrganizationMembershipResolver::resolve(7, [$active(7, 11), $active(7, 12)], null, $at);
$assert($multiple['allowed'] === false, 'multiple organizations require explicit selection');
$assert($multiple['reason_code'] === 'organization_selection_required', 'selection requirement is explicit');

$selected = OrganizationMembershipResolver::resolve(7, [$active(7, 11), $active(7, 12)], 12, $at);
$assert($selected['allowed'] === true && $selected['context']?->organizationId() === 12, 'server-validated requested organization resolves');

$outside = OrganizationMembershipResolver::resolve(7, [$active(7, 11)], 12, $at);
$assert($outside['allowed'] === false && $outside['reason_code'] === 'organization_not_member', 'unowned organization selection is denied');

$assert(OrganizationMembershipResolver::resolve(7, [], null, $at)['reason_code'] === 'no_active_membership', 'missing membership fails closed');
$assert(OrganizationMembershipResolver::resolve(7, [$active(7, 11, ['is_active' => false])], null, $at)['allowed'] === false, 'inactive membership fails closed');
$assert(OrganizationMembershipResolver::resolve(7, [$active(7, 11, ['is_revoked' => true])], null, $at)['allowed'] === false, 'revoked membership fails closed');
$assert(OrganizationMembershipResolver::resolve(7, [$active(7, 11, ['valid_until' => '2026-10-26T12:00:00+00:00'])], null, $at)['allowed'] === false, 'expired membership fails closed');
$assert(OrganizationMembershipResolver::resolve(7, [$active(7, 11, ['valid_from' => '2026-10-27T00:00:00+00:00'])], null, $at)['allowed'] === false, 'future membership fails closed');

$duplicate = OrganizationMembershipResolver::resolve(7, [$active(7, 11), $active(7, 11)], null, $at);
$assert($duplicate['allowed'] === false && $duplicate['reason_code'] === 'duplicate_active_membership', 'duplicate active membership is ambiguous');

$crossUser = OrganizationMembershipResolver::resolve(7, [$active(8, 11)], null, $at);
$assert($crossUser['allowed'] === false, 'evidence for another user cannot resolve context');
$assert(OrganizationMembershipResolver::resolve(0, [$active(7, 11)], null, $at)['allowed'] === false, 'invalid authenticated identity fails closed');
$assert(OrganizationMembershipResolver::resolve(7, [['user_id' => 7, 'organization_id' => 11]], null, $at)['reason_code'] === 'organization_evidence_malformed', 'missing lifecycle evidence fails closed');
$assert(OrganizationMembershipResolver::resolve(7, [['user_id' => 7, 'organization_id' => 11, 'is_active' => 'yes', 'is_revoked' => false]], null, $at)['reason_code'] === 'organization_evidence_malformed', 'non-strict lifecycle values fail closed');
$assert(OrganizationMembershipResolver::resolve(7, [$active(7, 11, ['valid_until' => 'not-a-date'])], null, $at)['reason_code'] === 'malformed_evidence', 'malformed validity evidence fails closed');
$assert(OrganizationMembershipResolver::resolve(7, [$active(7, 11, ['membership_key' => ['unsafe']])], null, $at)['reason_code'] === 'malformed_evidence', 'non-scalar membership evidence fails closed');

$assert(
    OrganizationMembershipResolver::resolve(7, [$active(7, 11, ['organization_is_active' => null])], null, $at)['reason_code'] === 'organization_evidence_malformed',
    'missing organization lifecycle evidence fails closed'
);
$assert(
    OrganizationMembershipResolver::resolve(7, [$active(7, 11, ['organization_is_active' => 'yes'])], null, $at)['reason_code'] === 'organization_evidence_malformed',
    'malformed organization lifecycle evidence fails closed'
);
$assert(
    OrganizationMembershipResolver::resolve(7, [$active(7, 11, ['organization_is_active' => false])], null, $at)['reason_code'] === 'organization_inactive',
    'inactive organization fails closed'
);
$assert(
    OrganizationMembershipResolver::resolve(7, [
        $active(7, 11),
        $active(7, 11, ['organization_is_active' => false]),
    ], 11, $at)['reason_code'] === 'organization_evidence_contradictory',
    'contradictory organization lifecycle evidence fails closed'
);

$inferred = OrganizationMembershipResolver::resolve(7, [$active(7, 11, ['email' => 'user@example.test', 'name' => 'User'])], null, $at);
$assert($inferred['context']?->organizationId() === 11, 'descriptive fields do not alter explicit membership resolution');
$assert(!array_key_exists('staff_id', $inferred['context']?->toArray() ?? []), 'context does not infer or expose Staff identity');

echo "Organization membership authority probe: {$checks}/{$checks} assertions passed\n";
