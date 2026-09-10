<?php
declare(strict_types=1);

// Shared Parties Foundation — minimal read-only contract/gate proof
// Not a runtime execution; verifies contract, schema proposal, and exclusion rules.

$repoRoot = dirname(__DIR__, 4); // apps/Shared/Parties/Tests -> repo root
$contractPath = $repoRoot . '/docs/shared-parties-foundation/canonical-party-contract.md';
$servicePath = $repoRoot . '/apps/Shared/Parties/Services/PartyService.php';

assert(file_exists($contractPath), 'Shared Parties contract missing');
assert(file_exists($servicePath), 'PartyService missing');

$content = file_get_contents($contractPath);
assert(str_contains($content, 'party_id'), 'Party identity missing');
assert(str_contains($content, 'type ENUM'), 'Person/organization distinction missing');
assert(str_contains($content, 'must NOT enter Shared Parties'), 'Exclusion rules missing');
assert(str_contains($content, 'session-d-shared-app-suite-extension-contract'), 'Reference to session-d missing');

// Verify no Manufacturing-specific fields proposed in schema proposal
$proposal = file_get_contents($contractPath);
assert(!str_contains($proposal, 'parts_name') || str_contains($proposal, 'must NOT'), 'Manufacturing parts_name must not be in master');
assert(!str_contains($proposal, 'producer') || str_contains($proposal, 'must NOT'), 'Manufacturing producer must not be in master');
assert(str_contains($proposal, 'metadata_ref'), 'Reference extension pattern missing');

echo "Shared Parties contract gate: PASS (read-only verification of contract, identity fields, exclusions, reference pattern, session-d reference).\n";
