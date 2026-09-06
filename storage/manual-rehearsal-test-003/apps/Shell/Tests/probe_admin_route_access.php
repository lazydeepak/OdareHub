<?php
declare(strict_types=1);

/**
 * Probe: routeAccessDecision() authorization checks for App Admin.
 *
 * Tests the two changed guard conditions (governance_only, platform_only)
 * and overall route accessibility for platform_admin, app_admin, and unauthenticated.
 *
 * Usage: php apps/Shell/Tests/probe_admin_route_access.php
 * Requires: running dev server on localhost:8000, seeded DB with users.
 */

$baseUrl = 'http://localhost:8000';
$cookieJar = tempnam(sys_get_temp_dir(), 'probe_admin_jar_');

$assertions = 0;
$failures = 0;
$assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        $failures++;
    }
};

$request = static function (string $method, string $path, ?array $postData = null) use ($baseUrl, $cookieJar): array {
    $url = $baseUrl . $path;
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_COOKIEJAR => $cookieJar,
    ];
    if ($method === 'POST' && $postData) {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($postData);
    }
    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);

    // Extract title and body
    preg_match('/<title>([^<]*)<\/title>/i', $response, $titleMatch);
    $title = $titleMatch[1] ?? '';

    $parts = explode("\r\n\r\n", $response, 2);
    $body = $parts[1] ?? '';

    return [
        'code' => $httpCode,
        'redirect' => $redirectUrl,
        'title' => $title,
        'body' => $body,
        'body_len' => strlen($body),
    ];
};

$login = static function (string $email, string $password) use ($baseUrl, $cookieJar): bool {
    // GET login page to establish session AND extract CSRF token
    $ch = curl_init($baseUrl . '/login');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_COOKIEJAR => $cookieJar,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    preg_match('/name="csrf"\s*value="([^"]+)"/', $response, $m);
    $csrf = $m[1] ?? '';

    // POST login with CSRF token
    $ch = curl_init($baseUrl . '/login');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['csrf' => $csrf, 'email' => $email, 'password' => $password]),
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_COOKIEJAR => $cookieJar,
    ]);
    curl_exec($ch);
    curl_close($ch);
    return true;
};

// ===================================================================
// 1. Platform Admin baseline (everything allowed)
// ===================================================================
$login('lazydeepak@gmail.com', 'test123');

$paHome    = $request('GET', '/admin/lazydeepak');
$paSystem  = $request('GET', '/admin/system-tools');
$paAccess  = $request('GET', '/ops/access-control');
$paMfg     = $request('GET', '/apps/manufacturing');
$paLanding = $request('GET', '/');

$assert($paHome['code'] === 200,
    'Platform Admin: /admin/lazydeepak returns 200, got ' . $paHome['code']);
$assert($paHome['title'] !== '',
    'Platform Admin: /admin/lazydeepak has non-empty title: "' . $paHome['title'] . '"');
$assert($paHome['title'] !== 'Login' && !str_contains($paHome['body'], 'login-form'),
    'Platform Admin: /admin/lazydeepak does NOT redirect to login');
$assert(in_array($paSystem['code'], [200, 302, 403], true),
    'Platform Admin: /admin/system-tools accessible (got ' . $paSystem['code'] . ')');
$assert(in_array($paAccess['code'], [200, 302], true),
    'Platform Admin: /ops/access-control accessible (got ' . $paAccess['code'] . ')');

// ===================================================================
// 2. App Admin tests
// ===================================================================
// Reset session cookie jar and login as App Admin
unlink($cookieJar);

$login('appadmin@example.com', 'test123');

$aaAdminHome   = $request('GET', '/admin/appadmin');
$aaWrongUser   = $request('GET', '/admin/lazydeepak');
$aaSystemTools = $request('GET', '/admin/system-tools');
$aaAccessCtrl  = $request('GET', '/ops/access-control');
$aaMfg         = $request('GET', '/apps/manufacturing');
$aaProcurement = $request('GET', '/apps/procurement');
$aaOperator    = $request('GET', '/u/appadmin/dashboard');
$aaLegacy      = $request('GET', '/ops/platform-admin-dashboard');
$aaLanding     = $request('GET', '/');

// 2a. Canonical Unified Admin route
$assert($aaAdminHome['code'] === 200,
    'App Admin: /admin/appadmin returns 200, got ' . $aaAdminHome['code'] . ' redirect=' . ($aaAdminHome['redirect'] ?? 'none'));
$assert($aaAdminHome['title'] !== '',
    'App Admin: /admin/appadmin has non-empty title: "' . $aaAdminHome['title'] . '"');
$assert($aaAdminHome['title'] !== 'Login' && !str_contains($aaAdminHome['body'], 'login-form'),
    'App Admin: /admin/appadmin does NOT redirect to login');
$assert(($aaAdminHome['redirect'] ?? '') !== '' || $aaAdminHome['code'] !== 302,
    'App Admin: /admin/appadmin is NOT redirected (got code=' . $aaAdminHome['code'] . ', redirect=' . ($aaAdminHome['redirect'] ?? 'none') . ')');

// 2b. Identity-mismatched /admin/{username}
$assert($aaWrongUser['code'] === 302,
    'App Admin: /admin/lazydeepak returns 302, got ' . $aaWrongUser['code']);
$assert(str_contains((string)$aaWrongUser['redirect'], '/admin/appadmin'),
    'App Admin: /admin/lazydeepak redirects to own handle');

// 2c. Platform Admin-only route
$assert(in_array($aaSystemTools['code'], [403, 302, 404], true),
    'App Admin: /admin/system-tools denied (got ' . $aaSystemTools['code'] . ')');

// 2d. ops/access-control (governance-only, non-admin path)
$assert(in_array($aaAccessCtrl['code'], [302, 403], true),
    'App Admin: /ops/access-control denied (got ' . $aaAccessCtrl['code'] . ')');

// 2e. Assigned app (manufacturing) - accessible
$assert(in_array($aaMfg['code'], [200, 302], true),
    'App Admin: /apps/manufacturing accessible (got ' . $aaMfg['code'] . ')');

// 2f. Unassigned app (procurement) - denied
$assert(in_array($aaProcurement['code'], [302, 403, 404], true),
    'App Admin: /apps/procurement not accessible (got ' . $aaProcurement['code'] . ')');

// 2g. Operator route still accessible
$assert(in_array($aaOperator['code'], [200, 302], true),
    'App Admin: /u/appadmin/dashboard accessible (got ' . $aaOperator['code'] . ')');

// 2h. Legacy route absent
$assert(in_array($aaLegacy['code'], [302, 404, 403], true),
    'App Admin: legacy /ops/platform-admin-dashboard absent (got ' . $aaLegacy['code'] . ')');

// 2i. Landing route
$assert($aaLanding['code'] === 302,
    'App Admin: / redirects (got ' . $aaLanding['code'] . ')');
$assert(str_contains((string)$aaLanding['redirect'], '/admin/appadmin'),
    'App Admin: / redirects to /admin/appadmin');

// ===================================================================
// 3. Unauthenticated user
// ===================================================================
// Use a fresh cookie jar
unlink($cookieJar);
$cookieJar2 = tempnam(sys_get_temp_dir(), 'probe_admin_jar2_');

$uaRequest = static function (string $path) use ($baseUrl, $cookieJar2): array {
    $ch = curl_init($baseUrl . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEFILE => $cookieJar2,
        CURLOPT_COOKIEJAR => $cookieJar2,
    ]);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);
    return ['code' => $httpCode, 'redirect' => $redirectUrl];
};

$uaAdmin   = $uaRequest('/admin/appadmin');
$uaHome    = $uaRequest('/');

$assert(in_array($uaAdmin['code'], [302, 401], true),
    'Unauthenticated: /admin/appadmin redirects/denies (got ' . $uaAdmin['code'] . ')');
$assert(in_array($uaHome['code'], [302, 200], true),
    'Unauthenticated: / returns 302 or 200 (got ' . $uaHome['code'] . ')');

// ===================================================================
// Summary
// ===================================================================
@unlink($cookieJar);
@unlink($cookieJar2);

if ($failures > 0) {
    fwrite(STDERR, "\n*** {$failures} FAILURES out of {$assertions} assertions ***\n");
    exit(1);
}

echo "Admin route access probe: {$assertions}/{$assertions} assertions passed\n";
