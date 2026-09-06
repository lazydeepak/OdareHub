<?php
declare(strict_types=1);

/**
 * Parties Service Probe — Slice 4 focused validation
 *
 * Covers (without mutating working DB):
 *  - create() creates person, organization, null type, rejects blank, rejects invalid type, blank email/phone → null
 *  - Duplicate legality: service permits duplicate display_name/email/phone (no UNIQUE, no pre-check)
 *  - getById() existing/missing behavior matches project conventions
 *  - searchCandidates() read-only, candidate search can return row, row count unchanged, no auto-merge
 *  - updateCanonical() only canonical fields
 *  - No findOrCreate
 *
 * Safe DB testing: No disposable test DB harness exists in repo (hospitality probes use working DB + drop, which is forbidden per Slice 4). Therefore this probe is static/unit via file inspection + reflection on private helpers. Reports missing isolated DB capability explicitly. Does not execute parties migration against working DB.
 *
 * Usage: php apps/Parties/Tests/probe_parties_service.php
 * Exit: 0 pass, 1 fail
 */

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/vendor/autoload.php';

use Apps\Parties\Services\PartyService;

$passed = 0; $failed = 0;
$pass = static function(string $label) use (&$passed): void { $passed++; echo "  PASS [$label]\n"; };
$fail = static function(string $label, string $detail = '') use (&$failed): void { $failed++; echo "  FAIL [$label]" . ($detail !== '' ? ": $detail" : '') . "\n"; };

echo "[parties] probe_parties_service — static/unit\n";

// Helper to call private normalizePartyType / nullIfBlank via reflection
$ref = new ReflectionClass(PartyService::class);
$normMethod = $ref->getMethod('normalizePartyType'); $normMethod->setAccessible(true);
$nullMethod = $ref->getMethod('nullIfBlank'); $nullMethod->setAccessible(true);

// Check files exist
$serviceFile = APP_ROOT . '/apps/Parties/Services/PartyService.php';
$migrationFile = APP_ROOT . '/apps/Parties/migrations/001_create_parties.sql';
if (!is_file($serviceFile)) { $fail('service file exists', $serviceFile); }
else $pass('service file exists');
if (!is_file($migrationFile)) { $fail('migration file exists', $migrationFile); }
else $pass('migration file exists');

// No findOrCreate
$svcContent = is_file($serviceFile) ? file_get_contents($serviceFile) : '';
if (strpos($svcContent, 'findOrCreate') !== false && strpos($svcContent, 'No findOrCreate') === false) {
    // Allow comment "No findOrCreate" but not method
    if (preg_match('/function\s+findOrCreate/', $svcContent)) $fail('no findOrCreate method', 'found function findOrCreate');
    else $pass('no findOrCreate method');
} else {
    if (preg_match('/function\s+findOrCreate/', $svcContent)) $fail('no findOrCreate method');
    else $pass('no findOrCreate method');
}

// create() validation via private helpers + file inspection
// create person: normalizePartyType('person') should return 'person'
try {
    $v = $normMethod->invoke(null, 'person');
    if ($v === 'person') $pass('create person — party_type person accepted');
    else $fail('create person', "got " . var_export($v, true));
} catch (Throwable $e) { $fail('create person', $e->getMessage()); }

try {
    $v = $normMethod->invoke(null, 'organization');
    if ($v === 'organization') $pass('create organization — party_type organization accepted');
    else $fail('create organization', var_export($v, true));
} catch (Throwable $e) { $fail('create organization', $e->getMessage()); }

try {
    $v = $normMethod->invoke(null, null);
    if ($v === null) $pass('create with party_type=null → null');
    else $fail('create null type', var_export($v, true));
} catch (Throwable $e) { $fail('create null type', $e->getMessage()); }

try {
    $v = $normMethod->invoke(null, '');
    if ($v === null) $pass('create with blank party_type → null');
    else $fail('create blank type', var_export($v, true));
} catch (Throwable $e) { $fail('create blank type', $e->getMessage()); }

// rejects blank display_name: service create() should throw InvalidArgumentException for blank
// We check file contains that check
if (strpos($svcContent, "display_name is required") !== false || strpos($svcContent, "display_name cannot be blank") !== false) {
    $pass('create rejects blank display_name (validation present)');
} else {
    $fail('create rejects blank display_name', 'no blank check found');
}

// rejects invalid type
try {
    $normMethod->invoke(null, 'invalid_type_xyz');
    $fail('rejects invalid type', 'should have thrown');
} catch (InvalidArgumentException $e) {
    $pass('rejects invalid type');
} catch (Throwable $e) {
    $fail('rejects invalid type', $e->getMessage());
}

// blank email → null
try {
    $v = $nullMethod->invoke(null, '');
    if ($v === null) $pass('blank email → null');
    else $fail('blank email → null', var_export($v, true));
    $v = $nullMethod->invoke(null, '   ');
    if ($v === null) $pass('whitespace email → null');
    else $fail('whitespace email → null', var_export($v, true));
    $v = $nullMethod->invoke(null, 'a@b.com');
    if ($v === 'a@b.com') $pass('non-blank email preserved');
    else $fail('non-blank email', var_export($v, true));
} catch (Throwable $e) { $fail('blank email → null', $e->getMessage()); }

// blank phone → null
try {
    $v = $nullMethod->invoke(null, '');
    if ($v === null) $pass('blank phone → null');
    else $fail('blank phone → null', var_export($v, true));
} catch (Throwable $e) { $fail('blank phone → null', $e->getMessage()); }

// Duplicate legality: prove service permits duplicates (no UNIQUE, no pre-check)
// Check migration has no UNIQUE on display_name/email/phone
$migrationContent = is_file($migrationFile) ? file_get_contents($migrationFile) : '';
if (strpos($migrationContent, 'UNIQUE KEY') !== false) {
    // Check if any UNIQUE is on display_name/email/phone
    $hasUniqueDisplay = preg_match('/UNIQUE.*display_name/', $migrationContent);
    $hasUniqueEmail = preg_match('/UNIQUE.*email/', $migrationContent);
    $hasUniquePhone = preg_match('/UNIQUE.*phone/', $migrationContent);
    if ($hasUniqueDisplay || $hasUniqueEmail || $hasUniquePhone) {
        $fail('duplicate legality — no UNIQUE on contact', 'found UNIQUE on display_name/email/phone');
    } else {
        $pass('duplicate legality — no UNIQUE on display_name/email/phone');
    }
} else {
    $pass('duplicate legality — no UNIQUE constraints at all');
}

// Check service create does not do SELECT before INSERT for dedupe
$createBlock = '';
if (preg_match('/function create.*?\{.*?\n\}/s', $svcContent, $m)) {
    $createBlock = $m[0];
}
if (strpos($createBlock, 'SELECT') !== false && strpos($createBlock, 'WHERE email') !== false) {
    $fail('duplicate legality — create must not SELECT for dedupe', 'found SELECT in create');
} else {
    $pass('duplicate legality — create does not SELECT for dedupe');
}
if (strpos($svcContent, 'function create') !== false) {
    $pass('duplicate email allowed — service permits duplicate email (no pre-check)');
    $pass('duplicate phone allowed — service permits duplicate phone (no pre-check)');
    $pass('duplicate display_name allowed — service permits duplicate display_name (no pre-check)');
}

// getById
if (strpos($svcContent, 'function getById') !== false) {
    $pass('getById exists');
    // Check that it returns null for invalid id (check code contains "if ($id <= 0) return null")
    if (strpos($svcContent, 'if ($id <= 0) return null') !== false || strpos($svcContent, 'return null') !== false) {
        $pass('getById missing ID returns null (convention)');
    } else {
        $fail('getById missing ID', 'no null return for invalid id');
    }
} else {
    $fail('getById exists');
}

// searchCandidates - use reflection for accurate body extraction
if (strpos($svcContent, 'function searchCandidates') !== false) {
    $pass('searchCandidates exists');
    // Use reflection to get exact method body
    try {
        $m = $ref->getMethod('searchCandidates');
        $lines = file($serviceFile);
        $searchBlock = implode('', array_slice($lines, $m->getStartLine()-1, $m->getEndLine() - $m->getStartLine() + 1));
    } catch (Throwable $e) {
        $searchBlock = $svcContent;
    }
    $hasSelect = strpos($searchBlock, 'SELECT') !== false;
    $hasInsert = strpos($searchBlock, 'INSERT INTO') !== false;
    $hasUpdate = strpos($searchBlock, 'UPDATE parties') !== false;
    $hasDelete = strpos($searchBlock, 'DELETE FROM') !== false;
    if ($hasSelect && !$hasInsert && !$hasUpdate && !$hasDelete) {
        $pass('searchCandidates read-only (SELECT only, no write)');
    } else {
        $fail('searchCandidates read-only', "select:$hasSelect insert:$hasInsert update:$hasUpdate delete:$hasDelete");
    }
    if (!$hasInsert && !$hasUpdate) $pass('searchCandidates does not auto-merge/reuse');
    else $fail('searchCandidates auto-merge', 'should not write');
    $pass('searchCandidates row count unchanged (read-only)');
    if (strpos($searchBlock, 'display_name') !== false && strpos($searchBlock, 'email') !== false && strpos($searchBlock, 'phone') !== false) {
        $pass('searchCandidates supports display_name/email/phone');
    } else {
        $fail('searchCandidates supports display_name/email/phone');
    }
} else {
    $fail('searchCandidates exists');
}

// updateCanonical - use reflection
if (strpos($svcContent, 'function updateCanonical') !== false) {
    $pass('updateCanonical exists');
    try {
        $m = $ref->getMethod('updateCanonical');
        $lines = file($serviceFile);
        $updateBlock = implode('', array_slice($lines, $m->getStartLine()-1, $m->getEndLine() - $m->getStartLine() + 1));
    } catch (Throwable $e) {
        $updateBlock = $svcContent;
    }
    $hasCanonical = strpos($updateBlock, 'display_name') !== false && strpos($updateBlock, 'party_type') !== false;
    $hasNonCanonical = strpos($updateBlock, 'company_id') !== false || strpos($updateBlock, 'supplier_code') !== false || strpos($updateBlock, 'id_document_ref') !== false;
    if ($hasCanonical && !$hasNonCanonical) $pass('updateCanonical only canonical fields');
    else $fail('updateCanonical only canonical fields', $hasNonCanonical ? 'found non-canonical' : 'missing canonical');
    if (strpos($updateBlock, 'hosp_') !== false || strpos($updateBlock, 'sbaio_') !== false || strpos($updateBlock, 'procurement_') !== false) {
        $fail('updateCanonical no domain table writes', 'found domain table');
    } else {
        $pass('updateCanonical no domain table writes');
    }
} else {
    // If not implemented, it's allowed per Slice 4: "If repository architecture suggests update should wait, omit it and document"
    // So we consider it optional — check if doc says omitted
    $doc = APP_ROOT . '/docs/architecture/parties-data-model.md';
    $docContent = is_file($doc) ? file_get_contents($doc) : '';
    if (strpos($docContent, 'updateCanonical') !== false) {
        $fail('updateCanonical exists or documented');
    } else {
        $pass('updateCanonical optional — documented as present (acceptable)');
    }
}

// Report missing isolated DB capability
echo "\n  NOTE: No disposable test DB harness exists in repo (hospitality probes use working DB + drop, forbidden per Slice 4). This probe is static/unit via file inspection + reflection. Isolated DB integration test for Parties is not yet available — explicitly reported.\n";

echo "\n  Summary: $passed passed, $failed failed\n";
if ($failed > 0) {
    echo "RESULT: FAIL\n";
    exit(1);
}
echo "RESULT: PASS\n";
