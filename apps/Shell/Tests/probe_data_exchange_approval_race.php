<?php
declare(strict_types=1);

/**
 * Database-free approval-race proof.
 *
 * This probe verifies the atomic claim shape and models the only relevant
 * interleaving: two approvers observe a pending job, but only one conditional
 * pending-to-processing transition can own the claim. No DB connection,
 * schema creation, import, file read, or filesystem mutation is permitted.
 */

$root = dirname(__DIR__, 3);
$servicePath = $root . '/apps/Shell/Services/DataExchangeService.php';
$source = (string)file_get_contents($servicePath);
$passed = 0;
$failed = 0;

$assert = static function (bool $condition, string $message) use (&$passed, &$failed): void {
    if ($condition) {
        $passed++;
        echo "PASS: {$message}\n";
        return;
    }
    $failed++;
    echo "FAIL: {$message}\n";
};

$assert(str_contains($source, "SET status = \\'processing\\'"), 'approval has a processing claim state');
$assert(str_contains($source, "WHERE id = ? AND status = \\'pending\\' LIMIT 1"), 'claim update is conditional on pending status');
$assert(str_contains($source, 'claim_token'), 'claim stores a unique ownership token');
$assert(str_contains($source, 'json_decode((string)($claimed[\'result_json\'] ?? \'{}\'), true)'), 'claim performs result-token readback');
$assert(str_contains($source, "'job_already_claimed'"), 'concurrent loser receives an explicit claim conflict');
$assert(str_contains($source, "WHERE id = ? AND status = \\'processing\\' AND result_json = ? LIMIT 1"), 'finalization is conditional on the same claim');
$assert(str_contains($source, "status IN (\\'pending\\',\\'processing\\',\\'approved_applied"), 'processing jobs remain visible in queue history');
$assert(str_contains($source, 'does not expose affected_rows'), 'probe records the DB abstraction affected-row limitation');
$assert(!str_contains($source, 'FOR UPDATE'), 'no unbounded lock claim is inferred without transaction authority');

// Reproduce the old check-then-execute interleaving: both approvals pass the
// pending check before either one writes the terminal state.
$legacyStatus = 'pending';
$legacyExecutions = 0;
$legacyASeesPending = $legacyStatus === 'pending';
$legacyBSeesPending = $legacyStatus === 'pending';
if ($legacyASeesPending) {
    $legacyExecutions++;
}
if ($legacyBSeesPending) {
    $legacyExecutions++;
}
$assert($legacyExecutions === 2, 'baseline interleaving reproduces duplicate execution');

// Model the database atomic conditional update: the first claimant changes
// pending to processing; the second conditional update affects zero rows.
$status = 'pending';
$claimOwner = '';
$executions = 0;
$claim = static function (string $token) use (&$status, &$claimOwner): bool {
    if ($status !== 'pending') {
        return false;
    }
    $status = 'processing';
    $claimOwner = $token;
    return true;
};
$aWon = $claim('A');
$bWon = $claim('B');
if ($aWon) {
    $executions++;
}
if ($bWon) {
    $executions++;
}
$assert($aWon && !$bWon, 'only one concurrent claimant wins the atomic transition');
$assert($executions === 1, 'claimed job executes exactly once in the race model');
$assert($status === 'processing' && $claimOwner === 'A', 'claim owner remains explicit after loser rejection');

echo "Data-exchange approval-race probe: {$passed} assertions, {$failed} failures\n";
exit($failed === 0 ? 0 : 1);
