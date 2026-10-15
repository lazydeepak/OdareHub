<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/vendor/autoload.php';

use Platform\Health\PostUpdateHealthVerifier;
use Platform\Updates\DisposableAdministratorApprovalAuthority;
use Platform\Updates\DisposableReleaseTransactionJournal;
use Platform\Updates\DisposableReleaseTransactionService;

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};
$throws = static function (callable $callable): bool {
    try { $callable(); return false; } catch (Throwable) { return true; }
};
$root = rtrim(sys_get_temp_dir(), '/') . '/odarehub-transaction-' . bin2hex(random_bytes(8));
$rootCreated = mkdir($root, 0700, true);
if (!$rootCreated) { throw new RuntimeException('temporary transaction fixture root could not be created'); }
$remove = static function (string $path) use (&$remove): void {
    if (is_link($path) || is_file($path)) { @unlink($path); return; }
    if (is_dir($path)) foreach (scandir($path) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') $remove($path . '/' . $entry);
    if (is_dir($path)) @rmdir($path);
};

try {
    $journal = new DisposableReleaseTransactionJournal($root . '/journal');
    $transactions = new DisposableReleaseTransactionService($journal);
    $approvals = new DisposableAdministratorApprovalAuthority($journal);
    $preflight = [
        'state' => 'preflight_verified', 'execution_prohibited' => true,
        'release_authority_granted' => false, 'update_eligible' => false,
        'apply_eligible' => false, 'execution_authorized' => false, 'live_mutation_performed' => false,
        'installation_identity_fingerprint' => str_repeat('a', 64),
        'candidate_release_version' => '2.0.0', 'candidate_build_id' => 'fixture:release:002', 'candidate_package_sha256' => str_repeat('b', 64),
        'release_statement_fingerprint' => str_repeat('c', 64), 'trust_store_fingerprint' => str_repeat('d', 64),
        'migration_plan_fingerprint' => str_repeat('e', 64), 'source_schema_identity' => 'schema:1.0.0',
        'target_schema_identity' => 'schema:2.0.0', 'recovery_point_identity' => 'rp-002',
        'diagnostics' => [['gate' => 'coordinator', 'ok' => true]], 'evidence_provenance' => ['coordinator' => ['state' => 'preflight_verified']],
    ];
    $environment = 'disposable-release-rehearsal-v1';
    $preflightValues = $preflight;
    $preflight = Platform\Updates\TrustedReleasePreflightEvidence::fromCoordinatorResult($preflightValues);
    $check($throws(static fn() => $transactions->prepare($preflightValues, $environment)), 'caller-supplied preflight array cannot prepare a transaction');
    $prepared = $transactions->prepare($preflight, $environment);
    $transactionId = (string)$prepared['record']['transaction_id'];
    $check(($prepared['state'] ?? null) === 'awaiting_approval', 'verified preflight prepares awaiting-approval transaction');
    $check(($prepared['record']['environment'] ?? null) === 'disposable' && ($prepared['record']['live_mutation_performed'] ?? true) === false, 'transaction binds disposable environment and no mutation');
    $check($throws(static fn() => $transactions->prepare($preflight, $environment)), 'second active transaction for installation is blocked');
    $challenge = $approvals->issue($transactionId, new DateTimeImmutable('+10 minutes', new DateTimeZone('UTC')));
    $check($throws(static fn() => $approvals->approve($transactionId, 'real-admin', (string)$challenge['token'], $preflight, new DateTimeImmutable('now', new DateTimeZone('UTC')))), 'real administrator identity is not claimed by simulator');
    $changedValues = $preflightValues;
    $changedValues['candidate_package_sha256'] = str_repeat('f', 64);
    $changed = Platform\Updates\TrustedReleasePreflightEvidence::fromCoordinatorResult($changedValues);
    $check($throws(static fn() => $approvals->approve($transactionId, 'simulated:fixture-admin', (string)$challenge['token'], $changed, new DateTimeImmutable('now', new DateTimeZone('UTC')))), 'changed candidate evidence invalidates approval');
    $approval = $approvals->approve($transactionId, 'simulated:fixture-admin', (string)$challenge['token'], $preflight, new DateTimeImmutable('now', new DateTimeZone('UTC')));
    $check(($approval['state'] ?? null) === 'approved_for_disposable_rehearsal', 'explicit simulated approval advances exactly one transaction');
    $check($throws(static fn() => $approvals->approve($transactionId, 'simulated:fixture-admin', (string)$challenge['token'], $preflight, new DateTimeImmutable('now', new DateTimeZone('UTC')))), 'approval replay is blocked');
    $check($throws(static fn() => $transactions->markExecuting($transactionId, $changed, $environment)), 'changed preflight cannot enter execution rehearsal');
    $expected = [
        'release_identity' => ['release_version' => '2.0.0', 'build_id' => 'fixture:release:002'],
        'target_schema' => ['identity' => 'schema:2.0.0'],
        'migration_ledger' => ['plan_fingerprint' => str_repeat('e', 64), 'rows' => ['FixtureApp:001.sql'], 'checksums' => ['FixtureApp:001.sql' => str_repeat('1', 64)]],
        'bootstrap' => ['available' => true], 'application_discovery' => ['applications' => ['FixtureApp']],
        'critical_connectivity' => ['reachable' => true],
        'configuration_trust' => ['identity_fingerprint' => str_repeat('a', 64), 'trust_fingerprint' => str_repeat('d', 64)],
        'protected_paths' => ['integrity' => str_repeat('2', 64)],
        'unresolved_state' => ['unresolved_migrations' => false, 'unresolved_recovery' => false],
    ];
    $expected['health_binding'] = $prepared['record']['health_binding'];
    $transactions->bindHealthExpectations($transactionId, $expected, $environment);
    $transactions->markExecuting($transactionId, $preflight, $environment);
    $transactions->markVerifying($transactionId, $preflight, $environment);
    $check(($transactions->read($transactionId)['state'] ?? null) === 'verifying', 'rehearsal transition reaches verifying without applying release');

    $healthRoot = $root . '/health';
    @mkdir($healthRoot, 0700, true);
    $write = static function (string $name, array $value) use ($healthRoot): void { file_put_contents($healthRoot . '/' . $name . '.json', json_encode($value, JSON_THROW_ON_ERROR)); };
    $expected = [
        'release_identity' => ['release_version' => '2.0.0', 'build_id' => 'fixture:release:002'],
        'target_schema' => ['identity' => 'schema:2.0.0'],
        'migration_ledger' => ['plan_fingerprint' => str_repeat('e', 64), 'rows' => ['FixtureApp:001.sql'], 'checksums' => ['FixtureApp:001.sql' => str_repeat('1', 64)]],
        'bootstrap' => ['available' => true], 'application_discovery' => ['applications' => ['FixtureApp']],
        'critical_connectivity' => ['reachable' => true],
        'configuration_trust' => ['identity_fingerprint' => str_repeat('a', 64), 'trust_fingerprint' => str_repeat('d', 64)],
        'protected_paths' => ['integrity' => str_repeat('2', 64)],
        'unresolved_state' => ['unresolved_migrations' => false, 'unresolved_recovery' => false],
    ];
    $expected['health_binding'] = $prepared['record']['health_binding'];
    foreach (['release_identity' => $expected['release_identity'], 'target_schema' => $expected['target_schema'], 'migration_ledger' => $expected['migration_ledger'], 'bootstrap' => ['available' => true], 'application_discovery' => $expected['application_discovery'], 'critical_connectivity' => ['reachable' => true], 'configuration_trust' => $expected['configuration_trust'], 'protected_paths' => $expected['protected_paths'], 'unresolved_state' => $expected['unresolved_state']] as $name => $value) $write($name, $value);
    $sources = [];
    foreach (['release_identity', 'target_schema', 'migration_ledger', 'bootstrap', 'application_discovery', 'critical_connectivity', 'configuration_trust', 'protected_paths', 'unresolved_state'] as $name) $sources[$name] = static function () use ($healthRoot, $name): array { return json_decode((string)file_get_contents($healthRoot . '/' . $name . '.json'), true, 512, JSON_THROW_ON_ERROR); };
    $verifier = new PostUpdateHealthVerifier($sources);
    $health = $verifier->verify($transactionId, $preflight->fingerprint(), $expected);
    $check($health->status() === 'verified' && count($health->checks()) === 9, 'health verifier executes all fixed read-only checks');
    $completed = $transactions->complete($transactionId, $health, $environment);
    $check(($completed['state'] ?? null) === 'completed', 'verified health completes transaction control state');
    $check($throws(static fn() => $transactions->complete($transactionId, $health, $environment)), 'completed transaction cannot be replayed');
    $check($throws(static fn() => $transactions->complete($transactionId, ['status' => 'verified'], $environment)), 'caller-supplied health array cannot authorize completion');

    $write('bootstrap', ['healthy' => true]);
    $failedHealth = $verifier->verify($transactionId, $preflight->fingerprint(), $expected);
    $check($failedHealth->status() === 'failed', 'healthy=true claim is not accepted as bootstrap proof');
    $check(count(array_filter($failedHealth->checks(), static fn(array $item): bool => $item['execution_result'] === 'failed')) >= 1, 'failed health records identify the failed executed check');

    $failedTransaction = $transactions->prepare($preflight, $environment);
    $failedTransactionId = $failedTransaction['record']['transaction_id'];
    $failedChallenge = $approvals->issue($failedTransactionId, new DateTimeImmutable('+10 minutes', new DateTimeZone('UTC')));
    $approvals->approve($failedTransactionId, 'simulated:fixture-admin', $failedChallenge['token'], $preflight, new DateTimeImmutable('now', new DateTimeZone('UTC')));
    $transactions->bindHealthExpectations($failedTransactionId, $expected, $environment);
    $transactions->markExecuting($failedTransactionId, $preflight, $environment);
    $transactions->markVerifying($failedTransactionId, $preflight, $environment);
    $failedHealthForTransaction = $verifier->verify($failedTransactionId, $preflight->fingerprint(), $expected);
    $check($failedHealthForTransaction->status() === 'failed' && $throws(static fn() => $transactions->complete($failedTransactionId, $failedHealthForTransaction, $environment)), 'failed health blocks transaction completion');
    $transactions->markRecoveryRequired($failedTransactionId, 'health_failed', $environment);

    $expiredId = $transactions->prepare($preflight, $environment)['record']['transaction_id'];
    $expired = $approvals->issue($expiredId, new DateTimeImmutable('+1 minute', new DateTimeZone('UTC')));
    $check($throws(static fn() => $approvals->approve($expiredId, 'simulated:fixture-admin', (string)$expired['token'], $preflight, new DateTimeImmutable('+2 minutes', new DateTimeZone('UTC')))), 'expired approval is rejected');
    $transactions->markBlocked($expiredId, 'approval_expired', $environment);

    $revokedId = $transactions->prepare($preflight, $environment)['record']['transaction_id'];
    $revoked = $approvals->issue($revokedId, new DateTimeImmutable('+10 minutes', new DateTimeZone('UTC')));
    $approvals->revoke($revokedId);
    $check($throws(static fn() => $approvals->approve($revokedId, 'simulated:fixture-admin', (string)$revoked['token'], $preflight, new DateTimeImmutable('now', new DateTimeZone('UTC')))), 'revoked approval is rejected');
    $transactions->markBlocked($revokedId, 'approval_revoked', $environment);

    $interruptedId = bin2hex(random_bytes(16));
    $journal->create($interruptedId, ['transaction_id' => $interruptedId, 'installation_identity_fingerprint' => str_repeat('z', 64)]);
    file_put_contents($root . '/journal/transaction-' . $interruptedId . '.jsonl', '{broken\n', FILE_APPEND);
    $interrupted = $transactions->read($interruptedId);
    $check(($interrupted['state'] ?? null) === 'indeterminate', 'interrupted journal remains indeterminate and is not auto-resumed');
    $check($throws(static fn() => $transactions->markExecuting($interruptedId, $preflight, $environment)), 'indeterminate journal cannot resume');
} finally {
    $remove($root);
}

echo "RESULT: {$passed}/" . ($passed + $failed) . " assertions passed\n";
exit($failed === 0 ? 0 : 1);
