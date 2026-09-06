<?php
declare(strict_types=1);

/**
 * Operator focus declaration/provider parity check.
 *
 * Generic contract enforcement for docs/app-manifest-spec.md
 * "Operator focus_views hook contract: focus_tokens":
 *   declared focus_tokens  ==  provider-returned view_map keys
 * for every ENABLED app that declares an operator_surface/focus_views hook.
 *
 * Providers are executed with a synthetic assigned context (the same guard shape
 * every contributing provider already implements). No DB writes; read-only.
 *
 * Exit codes: 0 = pass/nothing to check, 1 = violations found.
 */

define('APP_ROOT', dirname(__DIR__, 2));

require_once APP_ROOT . '/vendor/autoload.php';

use App\Core\DB;

$violations = [];
$checked = 0;

try {
    DB::conn();
} catch (\Throwable $e) {
    echo "SKIP: database unavailable ({$e->getMessage()})\n";
    exit(0);
}

$rows = DB::fetchAll(
    "SELECT h.app_key AS app_key, h.payload_json AS payload_json"
    . " FROM core_app_hooks h"
    . " INNER JOIN core_apps a ON a.app_key = h.app_key AND a.status = 'enabled'"
    . " WHERE h.hook_type = 'operator_surface' AND h.is_enabled = 1"
);

$normalizeToken = static function (string $t): string {
    return strtolower(trim($t));
};
$isValidToken = static function (string $t): bool {
    return $t !== '' && preg_match('/^[a-z0-9_-]+$/', $t) === 1;
};

foreach ($rows as $row) {
    $app = (string)$row['app_key'];
    $payload = json_decode((string)$row['payload_json'], true);
    if (!is_array($payload)) {
        continue;
    }
    $region = strtolower(trim((string)($payload['region'] ?? '')));
    if ($region !== 'focus_views') {
        continue;
    }

    $checked++;

    if (!array_key_exists('focus_tokens', $payload)) {
        $violations[] = "{$app}: focus_views hook missing focus_tokens declaration";
        continue;
    }
    if (!is_array($payload['focus_tokens'])) {
        $violations[] = "{$app}: focus_tokens must be an array";
        continue;
    }

    $declared = [];
    $dupe = false;
    foreach ($payload['focus_tokens'] as $raw) {
        $token = $normalizeToken((string)$raw);
        if (!$isValidToken($token)) {
            $violations[] = "{$app}: malformed focus token '" . ((string)$raw) . "'";
            continue;
        }
        if (isset($declared[$token])) {
            $dupe = true;
            continue;
        }
        $declared[$token] = true;
    }
    if ($declared === []) {
        $violations[] = "{$app}: focus_tokens empty after validation";
        continue;
    }
    if ($dupe) {
        $violations[] = "{$app}: duplicate declared focus tokens";
    }

    // Load provider and obtain the actual view_map keys.
    $providerRaw = (string)($payload['provider'] ?? '');
    $providerClass = str_contains($providerRaw, '::') ? explode('::', $providerRaw)[0] : $providerRaw;
    $providerFile = trim((string)($payload['provider_file'] ?? ''));
    if ($providerClass === '' || $providerFile === '') {
        $violations[] = "{$app}: focus_views hook missing provider/provider_file";
        continue;
    }
    $abs = APP_ROOT . '/apps/' . $app . '/' . ltrim($providerFile, '/');
    if (!is_file($abs)) {
        $violations[] = "{$app}: provider file missing ({$providerFile})";
        continue;
    }
    require_once $abs;

    if (!class_exists($providerClass)) {
        $violations[] = "{$app}: provider class not found ({$providerClass})";
        continue;
    }
    if (!method_exists($providerClass, 'contribute')) {
        $violations[] = "{$app}: provider class lacks contribute()";
        continue;
    }

    try {
        $result = $providerClass::contribute([
            'surface' => 'operator',
            'region' => 'focus_views',
            'context' => [
                'assigned_apps' => [$app],
                'active_assigned_apps' => [$app],
                'username' => 'focus-parity-probe',
                'user' => [],
            ],
        ]);
    } catch (\Throwable $e) {
        $violations[] = "{$app}: provider threw during parity check: " . $e->getMessage();
        continue;
    }

    $map = is_array($result) ? (array)($result['focus_views']['view_map'] ?? ($result['view_map'] ?? [])) : [];
    if ($map === []) {
        $violations[] = "{$app}: provider returned no view_map for focus_views region";
        continue;
    }

    $providedKeys = [];
    foreach (array_keys($map) as $k) {
        $token = $normalizeToken((string)$k);
        if ($isValidToken($token)) {
            $providedKeys[$token] = true;
        } else {
            $violations[] = "{$app}: provider view_map contains malformed key '{$k}'";
        }
    }

    foreach (array_keys($declared) as $token) {
        if (!isset($providedKeys[$token])) {
            $violations[] = "{$app}: declared focus token '{$token}' absent from provider view_map";
        }
    }
    foreach (array_keys($providedKeys) as $token) {
        if (!isset($declared[$token])) {
            $violations[] = "{$app}: provider view_map token '{$token}' absent from focus_tokens declaration";
        }
    }
}

if ($checked === 0) {
    echo "PASS: no enabled apps declare operator focus_views hooks\n";
    exit(0);
}
if ($violations !== []) {
    echo "FAIL: operator focus declaration/provider parity violations:\n";
    foreach ($violations as $v) {
        echo "  - {$v}\n";
    }
    exit(1);
}
echo "PASS: {$checked} focus_views declaration(s) in exact parity with provider view_maps\n";
exit(0);
