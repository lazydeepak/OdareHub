#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

failures=0

search() {
  local pattern="$1"
  local path="$2"
  if command -v rg >/dev/null 2>&1; then
    rg -n -- "$pattern" "$path" >/dev/null
  else
    grep -En -- "$pattern" "$path" >/dev/null
  fi
}

fail() {
  echo "  fail: $1" >&2
  failures=$((failures + 1))
}

ok() {
  echo "  ok: $1"
}

require_file() {
  local path="$1"
  if [[ -f "$path" ]]; then
    ok "file exists: $path"
  else
    fail "missing file: $path"
  fi
}

echo "[architecture] check_theme_runtime_fallback_contract"
echo "- read-only runtime theme fallback contract diagnostic"

echo ""
echo "== Owner/runtime anchors =="
require_file "apps/Shell/Services/ThemePreferenceService.php"
require_file "public/views/layouts/header.php"
require_file "public/views/layouts/auth_header.php"

echo ""
echo "== Non-recursive fallback contract =="
if search 'return self::normalizePreference\(\$configuredPreference, \$safeFallback\);' apps/Shell/Services/ThemePreferenceService.php; then
  ok "defaultPreference normalizes configured value against explicit safe fallback"
else
  fail "defaultPreference must normalize with explicit safe fallback"
fi

if search '\$fallback \?\? self::defaultPreference\(\)' apps/Shell/Services/ThemePreferenceService.php; then
  fail "normalizePreference must not recursively fallback via self::defaultPreference()"
else
  ok "normalizePreference avoids recursive self::defaultPreference fallback"
fi

if search '\$fallback \?\? \('"'"'system-'"'"' \.' apps/Shell/Services/ThemePreferenceService.php; then
  ok "normalizePreference uses deterministic built-in fallback when fallback is null"
else
  fail "normalizePreference must use deterministic built-in fallback when fallback is null"
fi

echo ""
echo "== Runtime normalization smoke =="
if php <<'PHP'
<?php
declare(strict_types=1);

define('APP_ROOT', getcwd());
require APP_ROOT . '/apps/Shell/Services/ThemePreferenceService.php';

use Apps\Shell\Services\ThemePreferenceService;

$allowed = ThemePreferenceService::allowedPreferences();
if ($allowed === []) {
    fwrite(STDERR, "no allowed theme preferences discovered\n");
    exit(1);
}

$cases = [
    'system-disabled-style',
    'dark',
    'light',
    'system',
    '',
];

foreach ($cases as $case) {
    $normalized = ThemePreferenceService::normalizePreference($case);
    if (!in_array($normalized, $allowed, true)) {
        fwrite(STDERR, "normalized value not allowed for case '{$case}': {$normalized}\n");
        exit(1);
    }
}

$defaultPreference = ThemePreferenceService::defaultPreference();
if (!in_array($defaultPreference, $allowed, true)) {
    fwrite(STDERR, "default preference is not allowed: {$defaultPreference}\n");
    exit(1);
}

$normalizedDefault = ThemePreferenceService::normalizePreference($defaultPreference);
if ($normalizedDefault !== $defaultPreference) {
    fwrite(STDERR, "default preference is not idempotent under normalization\n");
    exit(1);
}
PHP
then
  ok "invalid/legacy/default runtime preferences normalize into allowed built-in choices"
else
  fail "runtime normalization smoke failed"
fi

echo ""
echo "== Rendered runtime layout fallback smoke =="
if php <<'PHP'
<?php
declare(strict_types=1);

define('APP_ROOT', getcwd());

function current_lang(): string { return 'en'; }
function __(string $key): string { return $key; }
function t(string $key, array $params = []): string { return $key; }
function supported_language_labels(): array { return ['en' => 'English']; }
function core_setting(string $key, $default = null) {
    if ($key === 'ui.theme' || $key === 'system.theme') {
        return 'system-disabled-style';
    }
    return $default;
}

$_SERVER['REQUEST_URI'] = '/apps/studio/tools/customization-studio/diagnose/theme-doctor';

require APP_ROOT . '/apps/Shell/Services/ThemePreferenceService.php';

ob_start();
require APP_ROOT . '/public/views/layouts/auth_header.php';
$html = (string)ob_get_clean();

if (!preg_match('/data-theme-preference="([^"]+)"/', $html, $match)) {
    fwrite(STDERR, "missing data-theme-preference attribute in runtime auth layout\n");
    exit(1);
}

$preference = trim((string)($match[1] ?? ''));
$allowed = \Apps\Shell\Services\ThemePreferenceService::allowedPreferences();
if (!in_array($preference, $allowed, true)) {
    fwrite(STDERR, "rendered runtime auth layout emitted disallowed preference: {$preference}\n");
    exit(1);
}

if ($preference === 'system-disabled-style') {
    fwrite(STDERR, "rendered runtime auth layout leaked invalid configured preference\n");
    exit(1);
}
PHP
then
  ok "runtime auth layout emits allowed fallback preference even when configured value is invalid"
else
  fail "rendered runtime auth layout fallback smoke failed"
fi

echo ""
echo "== Rendered admin layout fallback smoke =="
if php <<'PHP'
<?php
declare(strict_types=1);

$realRoot = getcwd();
define('APP_ROOT', '/tmp/sbaio_gate_stub_root');

function current_lang(): string { return 'en'; }
function current_currency(): string { return 'JPY'; }
function __(string $key): string { return $key; }
function t(string $key, array $params = []): string { return $key; }
function supported_theme_preferences(): array { return ['system-liquid-glass' => 'System - Liquid Glass']; }
function default_theme_preference(): string { return 'system-liquid-glass'; }
function platform_mode(): string { return 'production'; }
function app_display_name(): string { return 'OdareHub'; }
function default_home_route(): string { return '/'; }
function base_is_admin_user($user): bool { return false; }
function base_can_access_builder($user): bool { return false; }
function app_dev_tools_enabled(): bool { return false; }
function platform_user_runtime_facade() { return null; }
function core_setting(string $key, $default = null) {
  if ($key === 'ui.theme' || $key === 'system.theme') {
    return 'system-disabled-style';
  }
  return $default;
}

$_SERVER['REQUEST_URI'] = '/admin/admin';

if (!class_exists('App\\Core\\Auth')) {
  eval('namespace App\\Core; class Auth { public static function isLoggedIn(): bool { return false; } public static function user(): ?array { return null; }}');
}

require $realRoot . '/apps/Shell/Services/ThemePreferenceService.php';
require $realRoot . '/apps/Shell/Services/LogoResolverService.php';

$previousHandler = set_error_handler(static function (): bool {
  return true;
});

// The admin layout unconditionally requires the self-contained developer_strip
// partial. Provide a faithful copy in the smoke stub root so the render completes.
$stubPartialDir = APP_ROOT . '/apps/Shell/Views/partials';
$stubPartial = $stubPartialDir . '/developer_strip.php';
if (!is_dir($stubPartialDir)) {
  mkdir($stubPartialDir, 0777, true);
}
if (is_file($stubPartial)) {
  unlink($stubPartial);
}
copy($realRoot . '/apps/Shell/Views/partials/developer_strip.php', $stubPartial);

ob_start();
require $realRoot . '/public/views/layouts/header.php';
$html = (string)ob_get_clean();

if ($previousHandler !== null) {
  set_error_handler($previousHandler);
} else {
  restore_error_handler();
}

if (!preg_match('/const\s+THEME_FALLBACK_PREFERENCE\s*=\s*(.+?);/', $html, $fallbackMatch)) {
  fwrite(STDERR, "missing THEME_FALLBACK_PREFERENCE constant in admin layout\n");
  exit(1);
}

$fallbackPreference = json_decode(trim((string)($fallbackMatch[1] ?? '')), true);
if (!is_string($fallbackPreference) || trim($fallbackPreference) === '') {
  fwrite(STDERR, "invalid THEME_FALLBACK_PREFERENCE constant payload\n");
  exit(1);
}

if (!preg_match('/const\s+THEME_ALLOWED_PREFERENCES\s*=\s*(\[[\s\S]*?\]);/', $html, $allowedMatch)) {
  fwrite(STDERR, "missing THEME_ALLOWED_PREFERENCES constant in admin layout\n");
  exit(1);
}

$allowed = json_decode(trim((string)($allowedMatch[1] ?? '')), true);
if (!is_array($allowed) || $allowed === []) {
  fwrite(STDERR, "invalid THEME_ALLOWED_PREFERENCES constant payload\n");
  exit(1);
}

if (!in_array($fallbackPreference, $allowed, true)) {
  fwrite(STDERR, "admin layout emitted disallowed fallback preference: {$fallbackPreference}\n");
  exit(1);
}

if ($fallbackPreference === 'system-disabled-style') {
  fwrite(STDERR, "admin layout leaked invalid configured preference\n");
  exit(1);
}
PHP
then
  ok "admin layout emits allowed fallback preference constants even when configured value is invalid"
else
  fail "rendered admin layout fallback smoke failed"
fi

echo ""
if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL ($failures failure(s))" >&2
  exit 1
fi

echo "RESULT: PASS"
