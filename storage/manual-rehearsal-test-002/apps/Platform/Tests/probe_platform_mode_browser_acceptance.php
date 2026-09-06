<?php
declare(strict_types=1);

/**
 * Platform Mode Lock Browser Acceptance Probe
 *
 * Simulates real browser behavior against the running dev server
 * to verify locked Platform Mode behavior end-to-end.
 *
 * Prerequisites:
 *   - Dev server running on localhost:8000 with PLATFORM_MODE_LOCK_ENABLED=true
 *   - Platform Admin user: lazydeepak@gmail.com / test123
 *   - App Admin user: appadmin@example.com / test123
 */

$baseUrl = 'http://localhost:8000';
$cookieJar = tempnam(sys_get_temp_dir(), 'pm_ba_');

$assertions = 0;
$failures = 0;
$assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        $failures++;
    }
};
$contains = fn(string $haystack, string $needle): bool => str_contains($haystack, $needle);

// HTTP helpers
$get = static function (string $path) use ($baseUrl, $cookieJar): array {
    $ch = curl_init($baseUrl . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
        CURLOPT_COOKIEFILE => $cookieJar, CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_HEADER => true,
    ]);
    $result = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $parts = explode("\r\n\r\n", (string)$result, 2);
    return ['code' => $code, 'body' => $parts[1] ?? ''];
};

$post = static function (string $path, array $data, ?string $referer = null) use ($baseUrl, $cookieJar): array {
    $ch = curl_init($baseUrl . $path);
    $opts = [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_COOKIEFILE => $cookieJar, CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_HEADER => true,
    ];
    if ($referer) {
        $opts[CURLOPT_REFERER] = $referer;
    }
    curl_setopt_array($ch, $opts);
    $result = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);
    return ['code' => $code, 'redirect' => $redirectUrl];
};

$login = static function (string $email, string $password) use ($baseUrl, $cookieJar): void {
    $loginPage = $baseUrl . '/login';
    // Need fresh CSRF — first visit to login page
    $ch = curl_init($loginPage);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
        CURLOPT_COOKIEFILE => $cookieJar, CURLOPT_COOKIEJAR => $cookieJar,
    ]);
    $page = curl_exec($ch);
    curl_close($ch);
    preg_match('/name="csrf"\s*value="([^"]+)"/', $page, $m);
    $csrf = $m[1] ?? '';

    $ch = curl_init($loginPage);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['csrf' => $csrf, 'email' => $email, 'password' => $password]),
        CURLOPT_TIMEOUT => 10, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEFILE => $cookieJar, CURLOPT_COOKIEJAR => $cookieJar,
    ]);
    curl_exec($ch);
    curl_close($ch);
};

$extractCsrf = static function (string $html): string {
    preg_match('/name="csrf"\s*value="([^"]+)"/', $html, $m);
    return $m[1] ?? '';
};

// ===================================================================
// PLATFORM ADMIN — Desktop acceptance
// ===================================================================
echo "--- Platform Admin Desktop ---\n";

$login('lazydeepak@gmail.com', 'test123');

// 1a. Unified Admin loads
$home = $get('/admin/lazydeepak');
$assert($home['code'] === 200, 'PA1: Unified Admin loads (200), got ' . $home['code']);

// 1b. Platform Mode page loads
$pm = $get('/admin/system-tools/platform-mode');
$assert($pm['code'] === 200, 'PA2: Platform Mode page loads (200), got ' . $pm['code']);

// 1c. Current mode visible
$assert($contains($pm['body'], 'Platform Mode'), 'PA3: "Platform Mode" heading present');

// 1d. Locked notice rendered
$assert($contains($pm['body'], 'locked by deployment configuration'),
    'PA4: Locked notice text visible');

// 1e. All mode radios are disabled
preg_match_all('/id="platform_mode_(production|development|demo)"/', $pm['body'], $radioIds);
$assert(count($radioIds[0]) === 3, 'PA5: Found exactly 3 mode radio inputs, got ' . count($radioIds[0]));

$disabledRadioCount = 0;
foreach ($radioIds[0] as $radioTag) {
    $block = substr($pm['body'], strpos($pm['body'], $radioTag), 200);
    if ($contains($block, 'disabled')) {
        $disabledRadioCount++;
    }
}
$assert($disabledRadioCount === 3, 'PA6: All 3 mode radios disabled, got ' . $disabledRadioCount);

// 1f. Submit button is disabled
$assert(preg_match('/<button[^>]*type="submit"[^>]*disabled/', $pm['body']) === 1,
    'PA7: Submit button rendered with disabled attribute');

// 1g. notice-warn class present
$assert($contains($pm['body'], 'notice-warn'), 'PA8: Lock notice wrapper has notice-warn class');

// ===================================================================
// PLATFORM ADMIN — Direct mutation (simulated click)
// ===================================================================
echo "--- Platform Admin Direct Mutation ---\n";

$csrf = $extractCsrf($pm['body']);
$assert($csrf !== '', 'PA9: CSRF token found on Platform Mode page');

// 2a. POST mutation attempt — try switching to demo (different from current)
$mutate = $post('/ops/platform-mode-switch', [
    'csrf' => $csrf,
    'platform_mode' => 'demo',
    'return_to' => '/admin/system-tools/platform-mode',
], $baseUrl . '/admin/system-tools/platform-mode');

$assert($mutate['code'] === 302, 'PA10: Mutation POST returns 302, got ' . $mutate['code']);
$assert((string)$mutate['redirect'] !== '', 'PA11: Redirect URL present, got "' . ($mutate['redirect'] ?? '') . '"');

// 2b. Verify locked result — follow redirect with fresh request
$pmAfter = $get('/admin/system-tools/platform-mode?mode_result=locked');
$assert($pmAfter['code'] === 200, 'PA12: Platform Mode page after mutation attempt loads, got ' . $pmAfter['code']);

// 2c. Controls still disabled and lock persists
$assert($contains($pmAfter['body'], 'disabled'), 'PA13: Controls still disabled after mutation attempt');
$assert($contains($pmAfter['body'], 'locked by deployment configuration'),
    'PA14: Lock message still present after mutation attempt');

// 2d. Submit button still disabled
$assert(preg_match('/<button[^>]*type="submit"[^>]*disabled/', $pmAfter['body']) === 1,
    'PA15: Submit button still disabled after mutation attempt');

// ===================================================================
// PLATFORM ADMIN — Accessibility
// ===================================================================
echo "--- Platform Admin Accessibility ---\n";

$assert($contains($pm['body'], 'disabled'), 'PA17: Native disabled attribute present on controls');
$assert($contains($pm['body'], 'platform_mode_development'), 'PA18: Radio input has predictable ID');
$assert($contains($pm['body'], 'type="radio"'), 'PA19: Radio inputs present');
// Inputs should have associated labels
$assert($contains($pm['body'], 'for="platform_mode_'), 'PA20: Labels reference radio IDs');

// ===================================================================
// APP ADMIN — Cannot see or mutate Platform Mode
// ===================================================================
echo "--- App Admin ---\n";

@unlink($cookieJar);
$login('appadmin@example.com', 'test123');

$aaPM = $get('/admin/system-tools/platform-mode');
$assert(in_array($aaPM['code'], [302, 403, 404], true),
    'AA1: App Admin Platform Mode page denied (got ' . $aaPM['code'] . ')');

$aaMutate = $post('/ops/platform-mode-switch', [
    'csrf' => 'fake-token', 'platform_mode' => 'development',
    'return_to' => '/admin/system-tools/platform-mode',
]);
$assert(in_array($aaMutate['code'], [302, 403, 419], true),
    'AA2: App Admin mutation denied (got ' . $aaMutate['code'] . ')');

// ===================================================================
// UNAUTHENTICATED — Cannot view or mutate
// ===================================================================
echo "--- Unauthenticated ---\n";

@unlink($cookieJar);
$uaPM = $get('/admin/system-tools/platform-mode');
$assert(in_array($uaPM['code'], [302, 401], true),
    'UA1: Unauthenticated Platform Mode redirects/denies (got ' . $uaPM['code'] . ')');

$uaMutate = $post('/ops/platform-mode-switch', [
    'csrf' => 'x', 'platform_mode' => 'development',
    'return_to' => '/admin/system-tools/platform-mode',
]);
$assert(in_array($uaMutate['code'], [302, 401, 403, 419], true),
    'UA2: Unauthenticated mutation denied (got ' . $uaMutate['code'] . ')');

// ===================================================================
// Summary
// ===================================================================
@unlink($cookieJar);

if ($failures > 0) {
    fwrite(STDERR, "\n*** {$failures} FAILURES out of {$assertions} assertions ***\n");
    exit(1);
}

echo "\nPlatform Mode browser acceptance: {$assertions}/{$assertions} assertions passed\n";
