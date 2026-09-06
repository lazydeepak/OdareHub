<?php
declare(strict_types=1);

/**
 * Read-Only Consumption Probe
 *
 * Diagnoses whether approved Platform Style Registry values and Shell
 * socket catalog metadata are safely readable, without applying values
 * or mutating anything.
 *
 * This is a STANDALONE filesystem diagnostic. It does not import any
 * runtime classes from Platform, Shell, or Studio. It validates
 * readiness for a future ResolvedStyleConsumer that does not yet exist.
 *
 * @see docs/architecture/read-only-consumption-probe-contract.md
 *
 * Usage:
 *   php scripts/platform/probe_resolved_style_consumer.php
 *   php scripts/platform/probe_resolved_style_consumer.php --socket=radius.scale
 *   php scripts/platform/probe_resolved_style_consumer.php --mode=json-only
 *
 * Exit codes:
 *   0 = PASS/WARN only  (no blocking issues)
 *   1 = FAIL present    (registry or catalog unreadable)
 *   2 = ERROR present   (safety violation or execution failure)
 */

// ---------- bootstrap ----------
define('RSC_PROBE_ROOT', realpath(__DIR__ . '/../..'));
define('RSC_REGISTRY_DIR', RSC_PROBE_ROOT . '/storage/platform/style-registry/approved-values');
define('RSC_CATALOG_DIR', RSC_PROBE_ROOT . '/apps/Shell/DesignSystem/Resources/socket-catalog');

// ---------- path confinement ----------
function rsc_resolve_allowed(string $path, string $allowedPrefix): ?string
{
    $real = realpath($path);
    if ($real === false) {
        return null;
    }
    $prefix = realpath($allowedPrefix);
    if ($prefix === false) {
        return null;
    }
    if (strncmp($real, $prefix, strlen($prefix)) === 0) {
        return $real;
    }
    return '__TRAVERSAL__';
}

// ---------- diagnostic helpers ----------
/** @var array<int, array{code:string, severity:string, message:string}> */
$rsc_diagnostics = [];
$rsc_errors = 0;
$rsc_fails = 0;
$rsc_warns = 0;
$rsc_passes = 0;

function rsc_diag(string $code, string $severity, string $message): void
{
    global $rsc_diagnostics, $rsc_errors, $rsc_fails, $rsc_warns, $rsc_passes;
    $rsc_diagnostics[] = ['code' => $code, 'severity' => $severity, 'message' => $message];
    match ($severity) {
        'ERROR' => $rsc_errors++,
        'FAIL'  => $rsc_fails++,
        'WARN'  => $rsc_warns++,
        default => $rsc_passes++,
    };
}

function rsc_overall_status(): string
{
    global $rsc_errors, $rsc_fails, $rsc_warns, $rsc_passes;
    if ($rsc_errors > 0) { return 'ERROR'; }
    if ($rsc_fails > 0) { return 'FAIL'; }
    if ($rsc_warns > 0) { return 'WARN'; }
    return 'PASS';
}

function rsc_exit_code(): int
{
    global $rsc_errors, $rsc_fails;
    if ($rsc_errors > 0) { return 2; }
    if ($rsc_fails > 0) { return 1; }
    return 0;
}

// ---------- parse CLI args ----------
$rsc_socket_filter = null;
$rsc_mode = 'full'; // full, json-only, summary

for ($i = 1; $i < $argc; $i++) {
    if (strncmp($argv[$i], '--socket=', 9) === 0) {
        $rsc_socket_filter = substr($argv[$i], 9);
    } elseif (strncmp($argv[$i], '--mode=', 7) === 0) {
        $rsc_mode = substr($argv[$i], 7);
    } elseif ($argv[$i] === '--help' || $argv[$i] === '-h') {
        echo "Usage: php " . basename(__FILE__) . " [--socket=<key>] [--mode=full|json-only|summary]\n";
        exit(0);
    }
}

// ---------- safety self-check: no Studio imports ----------
$rsc_self = @file_get_contents(__FILE__);
if (is_string($rsc_self)) {
    if (preg_match('/use\s+Apps\\\\Studio\\\\/i', $rsc_self) || preg_match('/App\\\Studio\\\/', $rsc_self)) {
        rsc_diag('RSC-E002', 'ERROR', 'Studio dependency detected: probe imports or references Studio namespace');
    }
}
unset($rsc_self);

// ---------- path confinement check ----------
$rsc_registryResolved = rsc_resolve_allowed(RSC_REGISTRY_DIR, RSC_PROBE_ROOT . '/storage/platform/style-registry');
$rsc_catalogResolved  = rsc_resolve_allowed(RSC_CATALOG_DIR, RSC_PROBE_ROOT . '/apps/Shell/DesignSystem/Resources/socket-catalog');

if ($rsc_registryResolved === '__TRAVERSAL__') {
    rsc_diag('RSC-E001', 'ERROR', 'Path traversal attempt blocked: registry path escapes allowed confinement');
}
if ($rsc_catalogResolved === '__TRAVERSAL__') {
    rsc_diag('RSC-E001', 'ERROR', 'Path traversal attempt blocked: catalog path escapes allowed confinement');
}

// ---------- 1. Read registry ----------
$rsc_registryValues = []; // socket_id => approved_value
$rsc_registryStatus = 'missing';

if ($rsc_registryResolved !== null && $rsc_registryResolved !== '__TRAVERSAL__') {
    if (!is_dir($rsc_registryResolved) || !is_readable($rsc_registryResolved)) {
        $rsc_registryStatus = 'unreadable';
        rsc_diag('RSC-F001', 'FAIL', 'Registry unreadable: storage directory missing or permission denied');
    } else {
        $rsc_files = glob($rsc_registryResolved . '/*.json');
        if ($rsc_files === false || count($rsc_files) === 0) {
            $rsc_registryStatus = 'empty';
        } else {
            $rsc_registryStatus = 'readable';
            rsc_diag('RSC-P001', 'PASS', 'Registry readable — approved values storage reachable and parseable');
            foreach ($rsc_files as $rsc_f) {
                $rsc_raw = @file_get_contents($rsc_f);
                if (!is_string($rsc_raw) || trim($rsc_raw) === '') {
                    rsc_diag('RSC-F001', 'FAIL', 'Registry file unreadable or empty: ' . basename($rsc_f));
                    continue;
                }
                $rsc_decoded = json_decode($rsc_raw, true);
                if (!is_array($rsc_decoded)) {
                    rsc_diag('RSC-W001', 'WARN', 'Registry file not valid JSON: ' . basename($rsc_f));
                    continue;
                }
                $rsc_sid = $rsc_decoded['socket_id'] ?? null;
                $rsc_av  = $rsc_decoded['approved_value'] ?? null;
                if (!is_string($rsc_sid) || $rsc_sid === '') {
                    rsc_diag('RSC-W001', 'WARN', 'Registry file missing socket_id: ' . basename($rsc_f));
                    continue;
                }
                $rsc_registryValues[$rsc_sid] = is_string($rsc_av) ? $rsc_av : null;
            }
        }
    }
} elseif ($rsc_registryResolved === null) {
    $rsc_registryStatus = 'missing';
    rsc_diag('RSC-F001', 'FAIL', 'Registry unreadable: storage directory path does not resolve');
}

if ($rsc_registryStatus === 'empty') {
    rsc_diag('RSC-W002', 'WARN', 'Registry empty: no approved values have been written yet');
} elseif ($rsc_registryStatus === 'missing') {
    rsc_diag('RSC-F001', 'FAIL', 'Registry unreadable: storage directory missing');
}

// ---------- 2. Read catalog ----------
$rsc_catalogSockets = []; // socket_id => array of catalog entry data
$rsc_catalogStatus = 'missing';

if ($rsc_catalogResolved !== null && $rsc_catalogResolved !== '__TRAVERSAL__') {
    if (!is_dir($rsc_catalogResolved) || !is_readable($rsc_catalogResolved)) {
        $rsc_catalogStatus = 'unreadable';
        rsc_diag('RSC-F002', 'FAIL', 'Catalog unreadable: socket catalog directory missing or permission denied');
    } else {
        $rsc_catFiles = glob($rsc_catalogResolved . '/*.json');
        if ($rsc_catFiles === false || count($rsc_catFiles) === 0) {
            $rsc_catalogStatus = 'unreadable';
            rsc_diag('RSC-F002', 'FAIL', 'Catalog unreadable: no JSON files found in socket catalog directory');
        } else {
            $rsc_catalogStatus = 'readable';
            rsc_diag('RSC-P002', 'PASS', 'Catalog readable — Shell socket catalog metadata reachable and parseable');
            foreach ($rsc_catFiles as $rsc_cf) {
                $rsc_raw = @file_get_contents($rsc_cf);
                if (!is_string($rsc_raw) || trim($rsc_raw) === '') {
                    rsc_diag('RSC-F002', 'FAIL', 'Catalog file unreadable or empty: ' . basename($rsc_cf));
                    continue;
                }
                $rsc_decoded = json_decode($rsc_raw, true);
                if (!is_array($rsc_decoded)) {
                    rsc_diag('RSC-W001', 'WARN', 'Catalog file not valid JSON: ' . basename($rsc_cf));
                    continue;
                }
                $rsc_sockets = $rsc_decoded['sockets'] ?? [];
                if (!is_array($rsc_sockets)) {
                    rsc_diag('RSC-W001', 'WARN', 'Catalog file missing "sockets" array: ' . basename($rsc_cf));
                    continue;
                }
                foreach ($rsc_sockets as $rsc_entry) {
                    if (!is_array($rsc_entry) || !isset($rsc_entry['socket'])) {
                        continue;
                    }
                    $rsc_sid = $rsc_entry['socket'];
                    if (!is_string($rsc_sid) || $rsc_sid === '') {
                        continue;
                    }
                    $rsc_catalogSockets[$rsc_sid] = $rsc_entry;
                }
            }
        }
    }
} elseif ($rsc_catalogResolved === null) {
    $rsc_catalogStatus = 'missing';
    rsc_diag('RSC-F002', 'FAIL', 'Catalog unreadable: socket catalog directory path does not resolve');
}

// ---------- 3. Compare and align ----------
$rsc_registryKeys = array_keys($rsc_registryValues);
$rsc_catalogKeys  = array_keys($rsc_catalogSockets);

$rsc_socketMatches = [];
$rsc_missingRegistryValues = [];
$rsc_orphanRegistryValues = [];

if ($rsc_registryStatus === 'readable' || $rsc_registryStatus === 'empty') {
    // For each catalog socket, check if registry has a value
    foreach ($rsc_catalogKeys as $rsc_ck) {
        if (in_array($rsc_ck, $rsc_registryKeys, true)) {
            $rsc_socketMatches[] = $rsc_ck;
        } else {
            $rsc_missingRegistryValues[] = $rsc_ck;
        }
    }
    // For each registry value, check if catalog has a socket
    foreach ($rsc_registryKeys as $rsc_rk) {
        if (!in_array($rsc_rk, $rsc_catalogKeys, true)) {
            $rsc_orphanRegistryValues[] = $rsc_rk;
        }
    }
}

// Apply socket filter if requested
$rsc_filteredMatches = [];
$rsc_filteredMissing = [];
$rsc_filteredOrphans = [];

if ($rsc_socket_filter !== null) {
    $rsc_filteredMatches = in_array($rsc_socket_filter, $rsc_socketMatches, true) ? [$rsc_socket_filter] : [];
    $rsc_filteredMissing = in_array($rsc_socket_filter, $rsc_missingRegistryValues, true) ? [$rsc_socket_filter] : [];
    $rsc_filteredOrphans = in_array($rsc_socket_filter, $rsc_orphanRegistryValues, true) ? [$rsc_socket_filter] : [];
    // Also check if the socket isn't in either
    if (count($rsc_filteredMatches) === 0 && count($rsc_filteredMissing) === 0 && count($rsc_filteredOrphans) === 0) {
        // Socket filter doesn't match anything known
    }
}

$rsc_reportMatches = $rsc_socket_filter !== null ? $rsc_filteredMatches : $rsc_socketMatches;
$rsc_reportMissing = $rsc_socket_filter !== null ? $rsc_filteredMissing : $rsc_missingRegistryValues;
$rsc_reportOrphans = $rsc_socket_filter !== null ? $rsc_filteredOrphans : $rsc_orphanRegistryValues;

// ---------- 4. Emit alignment diagnostics ----------
if ($rsc_socket_filter !== null) {
    $rsc_socketInCatalog = in_array($rsc_socket_filter, $rsc_catalogKeys, true);
    $rsc_socketInRegistry = array_key_exists($rsc_socket_filter, $rsc_registryValues);

    if ($rsc_socketInCatalog && $rsc_socketInRegistry) {
        rsc_diag('RSC-P001', 'PASS', "Socket '{$rsc_socket_filter}': approved value '" . ($rsc_registryValues[$rsc_socket_filter] ?? 'null') . "' aligns with catalog entry");
    } elseif ($rsc_socketInCatalog && !$rsc_socketInRegistry) {
        rsc_diag('RSC-W002', 'WARN', "Socket '{$rsc_socket_filter}': catalog entry exists but no approved registry value");
    } elseif (!$rsc_socketInCatalog && $rsc_socketInRegistry) {
        rsc_diag('RSC-W001', 'WARN', "Socket '{$rsc_socket_filter}': registry value exists but no catalog socket entry (orphan)");
    } else {
        rsc_diag('RSC-W002', 'WARN', "Socket '{$rsc_socket_filter}': not found in catalog or registry");
    }
} else {
    if (count($rsc_reportMatches) > 0) {
        rsc_diag('RSC-P001', 'PASS', count($rsc_reportMatches) . ' socket(s) have both catalog entry and approved registry value');
    } else {
        // No matches is not necessarily a warning if registry is empty (already reported)
    }
}

// WARN for missing registry values (catalog socket without approved value)
if (count($rsc_reportMissing) > 0 && $rsc_registryStatus !== 'empty') {
    rsc_diag('RSC-W002', 'WARN', count($rsc_reportMissing) . ' catalog socket(s) have no approved registry value');
} elseif (count($rsc_reportMissing) > 0 && $rsc_registryStatus === 'empty') {
    // Already reported via the "Registry empty" WARN
}

// WARN for orphans (registry value without catalog socket)
if (count($rsc_reportOrphans) > 0) {
    rsc_diag('RSC-W001', 'WARN', count($rsc_reportOrphans) . ' approved registry value(s) have no catalog socket entry (orphan)');
}

// ---------- 5. Build JSON report ----------
$rsc_checkedAt = gmdate('c');
$rsc_overall = rsc_overall_status();

$rsc_report = [
    'probe_status' => $rsc_overall,
    'checked_at' => $rsc_checkedAt,
    'registry_status' => $rsc_registryStatus,
    'catalog_status' => $rsc_catalogStatus,
    'socket_matches' => $rsc_reportMatches,
    'missing_registry_values' => $rsc_reportMissing,
    'orphan_registry_values' => $rsc_reportOrphans,
    'total_catalog_sockets' => count($rsc_catalogKeys),
    'total_registry_values' => count($rsc_registryKeys),
    'socket_filter' => $rsc_socket_filter,
    'diagnostics' => $rsc_diagnostics,
];

$rsc_jsonOutput = json_encode($rsc_report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

// ---------- 6. Output ----------
if ($rsc_mode === 'json-only') {
    echo $rsc_jsonOutput . "\n";
} else {
    echo "[probe] Read-Only Consumption Probe\n";
    echo "  Status: {$rsc_overall}\n";
    echo "  Registry: {$rsc_registryStatus}\n";
    echo "  Catalog:  {$rsc_catalogStatus}\n";
    echo "  Sockets in catalog: " . count($rsc_catalogKeys) . "\n";
    echo "  Registry entries:   " . count($rsc_registryKeys) . "\n";
    echo "  Matches:  " . count($rsc_reportMatches) . "\n";
    echo "  Missing:  " . count($rsc_reportMissing) . "\n";
    echo "  Orphans:  " . count($rsc_reportOrphans) . "\n";
    if ($rsc_socket_filter !== null) {
        echo "  Filter:  {$rsc_socket_filter}\n";
    }

    echo "\n  Diagnostics:\n";
    foreach ($rsc_diagnostics as $rsc_d) {
        printf("    %-9s %s — %s\n", $rsc_d['severity'], $rsc_d['code'], $rsc_d['message']);
    }

    if ($rsc_mode === 'full') {
        echo "\n  JSON report:\n";
        $rsc_indented = '    ' . str_replace("\n", "\n    ", trim($rsc_jsonOutput));
        echo $rsc_indented . "\n";
    }

    echo "\nRESULT: {$rsc_overall} — probe completed (exit " . rsc_exit_code() . ")\n";
}

exit(rsc_exit_code());
