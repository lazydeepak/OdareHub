<?php
declare(strict_types=1);

/**
 * Disposable-database-only Organization membership repository probe.
 *
 * This entry point refuses to load application code until an independently
 * attested isolated source root and disposable database are authorized.
 * Exit 0 = pass, 1 = failure, 2 = environment/authorization unavailable.
 */

$repositoryRoot = dirname(__DIR__, 3);
require_once $repositoryRoot . '/scripts/testing/DisposableDbTestEntryPointGuard.php';

try {
    $testContext = DisposableDbTestEntryPointGuard::authorize('OH_ORG', $repositoryRoot);
} catch (Throwable $e) {
    echo "INCONCLUSIVE: disposable organization database authorization unavailable: {$e->getMessage()}\n";
    exit(2);
}

define('APP_ROOT', $testContext['app_root']);
require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/apps/Platform/modules/Organization/Contracts/OrganizationMembershipRepositoryContract.php';
require_once APP_ROOT . '/apps/Platform/modules/Organization/Services/OrganizationMembershipRepository.php';
require_once APP_ROOT . '/apps/Platform/Services/OrganizationMembershipContext.php';
require_once APP_ROOT . '/apps/Platform/Services/OrganizationMembershipResolver.php';

use App\Core\DB;
use App\Services\AppSchemaSyncService;
use Apps\Platform\Services\OrganizationMembershipResolver;
use Plugins\Organization\Services\OrganizationMembershipRepository;

$passed = 0;
$failed = 0;
$pass = static function (string $label) use (&$passed): void {
    $passed++;
    echo "PASS: {$label}\n";
};
$fail = static function (string $label, string $detail = '') use (&$failed): void {
    $failed++;
    echo "FAIL: {$label}" . ($detail !== '' ? ": {$detail}" : '') . "\n";
};
$check = static function (bool $condition, string $label, string $detail = '') use ($pass, $fail): void {
    $condition ? $pass($label) : $fail($label, $detail);
};

$suffix = bin2hex(random_bytes(5));
$userId = 0;
$actorId = 0;
$otherUserId = 0;
$organizationId = 0;
$otherOrganizationId = 0;
$tableCreated = false;
$migrationPath = APP_ROOT . '/apps/Platform/modules/Organization/migrations/006_create_user_organization_memberships.sql';

$insertUser = static function (string $email): int {
    DB::query(
        'INSERT INTO users (email, password_hash, role, twofa_enabled) VALUES (?, ?, ?, ?)',
        [$email, 'disposable-membership-probe', 'User', 0]
    );
    return (int)DB::conn()->insert_id;
};

$insertOrganization = static function (string $code): int {
    DB::query(
        'INSERT INTO org_companies (company_code, company_name, is_primary, is_active) VALUES (?, ?, ?, ?)',
        [$code, 'Disposable Membership Probe', 0, 1]
    );
    return (int)DB::conn()->insert_id;
};

$tableExists = static function (): bool {
    $row = DB::fetchOne(
        "SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'org_user_memberships'"
    );
    return is_array($row) && (int)($row['c'] ?? 0) > 0;
};

$childResultFiles = [];
try {
    if (!is_file($migrationPath)) {
        throw new RuntimeException('membership migration file is missing');
    }
    if ($tableExists()) {
        echo "INCONCLUSIVE: disposable database already contains org_user_memberships; refusing to alter it.\n";
        exit(2);
    }

    $schemaSql = file_get_contents($migrationPath);
    if (!is_string($schemaSql) || trim($schemaSql) === '') {
        throw new RuntimeException('membership migration is empty');
    }
    AppSchemaSyncService::runAdditiveSql($schemaSql);
    $tableCreated = true;
    $check($tableExists(), 'migration creates membership table');

    $userId = $insertUser("org-probe-user-{$suffix}@example.test");
    $actorId = $insertUser("org-probe-actor-{$suffix}@example.test");
    $otherUserId = $insertUser("org-probe-other-{$suffix}@example.test");
    $organizationId = $insertOrganization("org-probe-{$suffix}");
    $otherOrganizationId = $insertOrganization("org-probe-2-{$suffix}");

    $repository = new OrganizationMembershipRepository();
    $active = $repository->create([
        'user_id' => $userId,
        'organization_id' => $organizationId,
        'status' => 'active',
        'created_by_user_id' => $actorId,
        'valid_from' => '2026-01-01 00:00:00',
        'valid_until' => '2026-12-31 23:59:59',
    ]);
    $check($active['ok'] === true, 'active membership create succeeds');
    $membershipId = (int)($active['record']['id'] ?? 0);
    $check($membershipId > 0, 'create returns persisted membership identity');

    $duplicate = $repository->create([
        'user_id' => $userId,
        'organization_id' => $organizationId,
        'status' => 'active',
        'created_by_user_id' => $actorId,
    ]);
    $check($duplicate['ok'] === false && $duplicate['state'] === 'membership_conflict', 'duplicate active membership is rejected');

    $historical = $repository->create([
        'user_id' => $userId,
        'organization_id' => $organizationId,
        'status' => 'inactive',
        'created_by_user_id' => $actorId,
    ]);
    $check($historical['ok'] === true, 'inactive historical membership is retained');

    $evidence = $repository->listEvidenceForUser($userId, new DateTimeImmutable('2026-10-26T12:00:00+00:00'));
    $resolved = OrganizationMembershipResolver::resolve($userId, $evidence, null, new DateTimeImmutable('2026-10-26T12:00:00+00:00'));
    $check($resolved['allowed'] === true && $resolved['context']?->organizationId() === $organizationId, 'repository evidence resolves through pure resolver');
    $check($resolved['context']?->userId() === $userId, 'evidence remains bound to exact users.id');

    $secondOrganization = $repository->create([
        'user_id' => $userId,
        'organization_id' => $otherOrganizationId,
        'status' => 'active',
        'created_by_user_id' => $actorId,
    ]);
    $check($secondOrganization['ok'] === true, 'second organization membership succeeds');
    $multiEvidence = $repository->listEvidenceForUser($userId, new DateTimeImmutable('2026-10-26T12:00:00+00:00'));
    $multi = OrganizationMembershipResolver::resolve($userId, $multiEvidence, null, new DateTimeImmutable('2026-10-26T12:00:00+00:00'));
    $check($multi['allowed'] === false && $multi['reason_code'] === 'organization_selection_required', 'multiple organizations require explicit selection');

    $revoked = $repository->revoke($membershipId, $actorId, new DateTimeImmutable('2026-10-26T12:00:00+00:00'), 'probe revoke');
    $check($revoked['ok'] === true && $revoked['state'] === 'membership_revoked', 'active membership revokes without deletion');
    $reactivated = $repository->activate((int)($historical['record']['id'] ?? 0), $actorId, new DateTimeImmutable('2026-10-26T12:00:00+00:00'));
    $check($reactivated['ok'] === true && $reactivated['state'] === 'membership_activated', 'inactive historical membership activates explicitly');

    $unknownParent = $repository->create([
        'user_id' => $otherUserId,
        'organization_id' => 999999999,
        'status' => 'active',
        'created_by_user_id' => $actorId,
    ]);
    $check($unknownParent['ok'] === false && $unknownParent['state'] === 'membership_parent_not_found', 'unknown organization is rejected');

    if (!function_exists('pcntl_fork') || !function_exists('pcntl_waitpid')) {
        throw new RuntimeException('pcntl fork support is required for concurrency verification');
    }
    $concurrentUser = $insertUser("org-probe-concurrent-{$suffix}@example.test");
    $resultBase = sys_get_temp_dir() . '/oh-org-membership-' . $suffix;
    for ($index = 0; $index < 2; $index++) {
        $path = $resultBase . '-' . $index . '.json';
        $childResultFiles[] = $path;
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('pcntl_fork failed');
        }
        if ($pid === 0) {
            $childRepository = new OrganizationMembershipRepository();
            $result = $childRepository->create([
                'user_id' => $concurrentUser,
                'organization_id' => $otherOrganizationId,
                'status' => 'active',
                'created_by_user_id' => $actorId,
            ]);
            file_put_contents($path, json_encode($result, JSON_THROW_ON_ERROR));
            exit($result['ok'] === true ? 0 : 1);
        }
    }
    $childStatuses = [];
    for ($index = 0; $index < 2; $index++) {
        $childPid = pcntl_wait($status);
        $childStatuses[] = pcntl_wexitstatus($status);
    }
    $childResults = [];
    foreach ($childResultFiles as $path) {
        $childResults[] = json_decode((string)file_get_contents($path), true);
    }
    $successes = count(array_filter($childResults, static fn (array $result): bool => ($result['ok'] ?? false) === true));
    $conflicts = count(array_filter($childResults, static fn (array $result): bool => ($result['state'] ?? '') === 'membership_conflict'));
    $check($successes === 1 && $conflicts === 1, 'concurrent same-pair create has one success and one conflict');

    $replacement = $repository->replaceActive(
        $userId,
        $otherOrganizationId,
        ['valid_from' => '2026-11-01 00:00:00', 'valid_until' => '2026-12-01 00:00:00'],
        $actorId,
        new DateTimeImmutable('2026-10-26T12:00:00+00:00')
    );
    $check($replacement['ok'] === true, 'replace-active commits as one lifecycle operation');
    $activeCount = DB::fetchOne(
        'SELECT COUNT(*) AS c FROM org_user_memberships WHERE user_id = ? AND organization_id = ? AND status = ?',
        [$userId, $otherOrganizationId, 'active']
    );
    $check((int)($activeCount['c'] ?? 0) === 1, 'replace-active leaves exactly one active row');

    $badReplacement = $repository->replaceActive(
        $userId,
        $organizationId,
        ['valid_from' => '2026-12-01 00:00:00', 'valid_until' => '2026-01-01 00:00:00'],
        $actorId,
        new DateTimeImmutable('2026-10-26T12:00:00+00:00')
    );
    $check($badReplacement['ok'] === false, 'invalid replacement is rejected before mutation');
    $badCount = DB::fetchOne(
        'SELECT COUNT(*) AS c FROM org_user_memberships WHERE user_id = ? AND organization_id = ? AND status = ?',
        [$userId, $organizationId, 'active']
    );
    $check((int)($badCount['c'] ?? 0) === 1, 'rejected replacement leaves prior active state intact');

    echo "Organization membership repository probe: {$passed} passed, {$failed} failed\n";
    exit($failed > 0 ? 1 : 0);
} catch (Throwable $e) {
    $fail('repository probe execution', $e->getMessage());
    echo "Organization membership repository probe: {$passed} passed, {$failed} failed\n";
    exit(1);
} finally {
    foreach ($childResultFiles as $path) {
        @unlink($path);
    }
    if ($tableCreated) {
        try {
            DB::query('DROP TABLE IF EXISTS org_user_memberships');
        } catch (Throwable $e) {
            fwrite(STDERR, "CLEANUP FAILURE: {$e->getMessage()}\n");
            exit(2);
        }
    }
    foreach ([$organizationId, $otherOrganizationId] as $id) {
        if ($id > 0) {
            DB::query('DELETE FROM org_companies WHERE id = ?', [$id]);
        }
    }
    foreach ([$userId, $actorId, $otherUserId] as $id) {
        if ($id > 0) {
            DB::query('DELETE FROM users WHERE id = ?', [$id]);
        }
    }
}
