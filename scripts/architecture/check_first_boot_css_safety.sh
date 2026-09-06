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

extract_tokens() {
  local pattern="$1"
  local path="$2"
  if command -v rg >/dev/null 2>&1; then
    rg -o -- "$pattern" "$path" 2>/dev/null || true
  else
    grep -Eo -- "$pattern" "$path" 2>/dev/null || true
  fi
}

filter_inverse() {
  local pattern="$1"
  if command -v rg >/dev/null 2>&1; then
    rg -v "$pattern" || true
  else
    grep -Ev -- "$pattern" || true
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

check_token_definitions() {
  local path="$1"
  local allowed_pattern="$2"
  local label="$3"
  local definitions
  local invalid

  definitions="$(extract_tokens '--[A-Za-z0-9_-]+[[:space:]]*:' "$path" \
    | sed 's/[[:space:]]*:$//' \
    | sort -u || true)"
  invalid="$(printf '%s\n' "$definitions" | filter_inverse "$allowed_pattern")"

  if [[ -z "$invalid" ]]; then
    ok "$label token definitions stay within owner prefix contract"
  else
    fail "$label defines token(s) outside owner prefix contract: $(printf '%s' "$invalid" | tr '\n' ' ')"
  fi
}

check_no_reserved_token_definitions() {
  local path="$1"
  local label="$2"
  local invalid

  invalid="$(extract_tokens '--(sys|render|fx|color|theme)-[A-Za-z0-9_-]+[[:space:]]*:' "$path" \
    | sed 's/[[:space:]]*:$//' \
    | sort -u || true)"

  if [[ -z "$invalid" ]]; then
    ok "$label does not redefine raw owner tokens"
  else
    fail "$label redefines reserved raw token(s): $(printf '%s' "$invalid" | tr '\n' ' ')"
  fi
}

echo "[architecture] check_first_boot_css_safety"
echo "- setup/auth-safe static CSS and no-runtime-resolver diagnostic"

required_files=(
  "apps/Shell/Resources/css/essential/shell-essential.css"
  "apps/Shell/Resources/css/essential/auth.css"
  "apps/Shell/Resources/rendering/foundation.css"
  "apps/Platform/Resources/effects/effects-none.css"
  "apps/Platform/Resources/themes/liquid-glass-system/theme.json"
  "apps/Platform/Resources/themes/liquid-glass-system/colors.css"
  "apps/Platform/Resources/themes/liquid-glass-system/typography.css"
  "apps/Platform/Resources/themes/liquid-glass-system/modes.css"
  "apps/Platform/Resources/css/semantic-aliases.css"
  "apps/Shell/styles/shell-setup.css"
  "scripts/assets/first_boot_css_manifest.json"
  "scripts/assets/compile_first_boot_css.php"
  "scripts/assets/compile_theme_sources.php"
  "scripts/assets/theme_source_fingerprint.php"
  "apps/Shell/Tests/probe_runtime_theme_asset_self_healing.php"
  "public/index.php"
  "docs/architecture/first-boot-css-safety-contract.md"
)

echo ""
echo "== Owner sources =="
for path in "${required_files[@]}"; do
  require_file "$path"
done

echo ""
echo "== Token ownership =="
if search '--sys-' apps/Shell/Resources/css/essential/shell-essential.css; then
  ok "Shell essential defines --sys-* tokens"
else
  fail "Shell essential must define --sys-* tokens"
fi
if search '--render-' apps/Shell/Resources/rendering/foundation.css; then
  ok "rendering foundation defines --render-* tokens"
else
  fail "rendering foundation must define --render-* tokens"
fi
for token in \
  render-control-height render-control-font-size render-control-line-height render-control-radius \
  render-control-padding-block render-control-padding-inline render-choice-size \
  render-checkbox-radius render-radio-radius; do
  if search "--${token}:" apps/Shell/Resources/rendering/foundation.css; then
    ok "rendering foundation defines --${token}"
  else
    fail "rendering foundation missing --${token}"
  fi
done
if search '--fx-' apps/Platform/Resources/effects/effects-none.css; then
  ok "effects-none defines --fx-* tokens"
else
  fail "effects-none must define --fx-* tokens"
fi
if search '--(surface|text|accent|status|radius|shadow|duration)-' apps/Platform/Resources/css/semantic-aliases.css; then
  ok "semantic aliases expose stable component-facing tokens"
else
  fail "semantic alias categories missing"
fi
if search '--(tone|style)-' apps/Shell/styles/shell-setup.css; then
  fail "shell-setup.css still consumes legacy tone/style token names"
else
  ok "shell-setup.css consumes semantic aliases"
fi

echo ""
echo "== Token definition ownership =="
check_token_definitions \
  "apps/Shell/Resources/css/essential/shell-essential.css" \
  '^--sys-' \
  "Shell essential"
check_token_definitions \
  "apps/Shell/Resources/rendering/foundation.css" \
  '^--render-' \
  "rendering foundation"
check_token_definitions \
  "apps/Platform/Resources/effects/effects-none.css" \
  '^--fx-' \
  "effects-none"
check_token_definitions \
  "apps/Platform/Resources/themes/liquid-glass-system/colors.css" \
  '^--color-' \
  "theme colors"
check_token_definitions \
  "apps/Platform/Resources/themes/liquid-glass-system/typography.css" \
  '^--theme-' \
  "theme typography"
check_token_definitions \
  "apps/Platform/Resources/themes/liquid-glass-system/modes.css" \
  '^--theme-' \
  "theme modes"
check_no_reserved_token_definitions \
  "apps/Platform/Resources/css/semantic-aliases.css" \
  "semantic aliases"
check_no_reserved_token_definitions \
  "apps/Shell/Resources/css/essential/auth.css" \
  "auth component CSS"
check_no_reserved_token_definitions \
  "apps/Shell/styles/shell-setup.css" \
  "setup component CSS"

echo ""
echo "== Compiler plan =="
if php -l scripts/assets/compile_first_boot_css.php >/dev/null; then
  ok "first-boot compiler PHP syntax"
else
  fail "first-boot compiler PHP syntax"
fi
if php -l scripts/assets/compile_theme_sources.php >/dev/null \
  && php -l scripts/assets/theme_source_fingerprint.php >/dev/null \
  && php -l public/index.php >/dev/null; then
  ok "theme compiler, fingerprint, and runtime bridge PHP syntax"
else
  fail "theme compiler, fingerprint, or runtime bridge PHP syntax"
fi

if search 'Source fingerprint:' scripts/assets/compile_theme_sources.php \
  && search 'susankhyaThemeSourceFingerprint' public/index.php \
  && search 'maybeRecompileThemeCss\(\$themeCssEarlyPath\)' public/index.php; then
  ok "dynamic page runtime enforces deterministic theme source synchronization"
else
  fail "runtime theme source fingerprint synchronization contract missing"
fi

if php apps/Shell/Tests/probe_runtime_theme_asset_self_healing.php >/tmp/runtime-theme-self-healing.out; then
  ok "runtime theme asset self-healing probe"
else
  cat /tmp/runtime-theme-self-healing.out >&2 || true
  fail "runtime theme asset self-healing probe"
fi

if php scripts/assets/compile_first_boot_css.php --json >/tmp/first-boot-css-plan.json; then
  if php -r '
    $plan = json_decode((string)file_get_contents("/tmp/first-boot-css-plan.json"), true);
    $expected = [
      "public/assets/system/shell-essential.css" => [
        "apps/Shell/Resources/css/essential/shell-essential.css",
      ],
      "public/assets/rendering/foundation.css" => [
        "apps/Shell/Resources/rendering/foundation.css",
      ],
      "public/assets/effects/effects-none.css" => [
        "apps/Platform/Resources/effects/effects-none.css",
      ],
      "public/assets/themes/liquid-glass-system.css" => [
        "apps/Platform/Resources/themes/liquid-glass-system/colors.css",
        "apps/Platform/Resources/themes/liquid-glass-system/typography.css",
        "apps/Platform/Resources/themes/liquid-glass-system/modes.css",
      ],
      "public/assets/system/semantic-aliases.css" => [
        "apps/Platform/Resources/css/semantic-aliases.css",
      ],
      "public/assets/system/setup.css" => [
        "apps/Shell/styles/shell-setup.css",
      ],
      "public/assets/system/auth.css" => [
        "apps/Shell/Resources/css/essential/auth.css",
      ],
    ];
    $actual = [];
    foreach (($plan["assets"] ?? []) as $asset) {
      $target = (string)($asset["target"] ?? "");
      $actual[$target] = array_column($asset["sources"] ?? [], "path");
    }
    exit(($plan["ok"] ?? false) && $actual === $expected ? 0 : 1);
  '; then
    ok "compiler preserves exact ordered target-to-owner source map"
  else
    fail "compiler plan does not match first-boot contract"
  fi
  # Verify theme_css generation step is present in compiler output
  if php -r '
    $plan = json_decode((string)file_get_contents("/tmp/first-boot-css-plan.json"), true);
    $themeCss = $plan["theme_css"] ?? null;
    exit(is_array($themeCss) && isset($themeCss["theme_css_status"]) ? 0 : 1);
  '; then
    ok "compiler includes theme_css generation step"
  else
    fail "compiler missing theme_css generation step"
  fi
else
  fail "first-boot compiler dry-run"
fi

echo ""
echo "== Published asset provenance =="
if php <<'PHP'
<?php
declare(strict_types=1);

$plan = json_decode((string)file_get_contents('/tmp/first-boot-css-plan.json'), true);
if (!is_array($plan) || empty($plan['ok'])) {
    fwrite(STDERR, "compiler plan unavailable for provenance check\n");
    exit(1);
}

foreach (($plan['assets'] ?? []) as $asset) {
    $target = (string)($asset['target'] ?? '');
    $status = (string)($asset['status'] ?? '');
    $path = getcwd() . '/' . $target;
    $content = @file_get_contents($path);
    if (!is_string($content)) {
        fwrite(STDERR, "published first-boot asset missing: {$target}\n");
        exit(1);
    }
    if ($status !== 'current') {
        fwrite(STDERR, "published first-boot asset is stale: {$target}\n");
        exit(1);
    }
    $marker = "/* GENERATED FILE: {$target} */";
    if (!str_starts_with($content, $marker)) {
        fwrite(STDERR, "published first-boot asset lacks generated marker: {$target}\n");
        exit(1);
    }
}
PHP
then
  ok "published first-boot assets are current generated output"
else
  fail "published first-boot asset provenance or parity failed"
fi

echo ""
echo "== Theme CSS generation =="
if [[ -f public/assets/theme.css ]] && [[ -s public/assets/theme.css ]]; then
  ok "public/assets/theme.css exists and is non-empty"
else
  fail "public/assets/theme.css is missing or empty after first-boot compile"
fi

echo ""
echo "== Setup resolver isolation =="
if php <<'PHP' >/tmp/first-boot-auth-header.html
<?php
declare(strict_types=1);
define('APP_ROOT', getcwd());
define('APP_VERSION', 'test');
$_SERVER['REQUEST_URI'] = '/setup?step=welcome';

function current_lang(): string { return 'en'; }
function __(string $key): string { return $key; }
function t(string $key, array $params = []): string { return $key; }
function supported_language_labels(): array { return ['en' => 'English']; }

spl_autoload_register(static function (string $class): void {
    if (in_array($class, [
        'Apps\\Shell\\Services\\BrandIdentityService',
        'Apps\\Shell\\Services\\ThemePreferenceService',
        'Apps\\Shell\\Services\\StyleRegistryService',
    ], true)) {
        throw new RuntimeException('forbidden setup resolver access: ' . $class);
    }
});

$themeCssPath = APP_ROOT . '/public/assets/theme.css';
ob_start();
require APP_ROOT . '/public/views/layouts/auth_header.php';
$html = (string)ob_get_clean();

$required = [
    '/assets/system/shell-essential.css',
    '/assets/rendering/foundation.css',
    '/assets/effects/effects-none.css',
    '/assets/themes/liquid-glass-system.css',
    '/assets/system/semantic-aliases.css',
    '/assets/system/setup.css',
];
$position = -1;
foreach ($required as $url) {
    $next = strpos($html, $url);
    if ($next === false || $next <= $position) {
        fwrite(STDERR, "missing or misordered setup asset: {$url}\n");
        exit(1);
    }
    $position = $next;
}
$themeCssPath = APP_ROOT . '/public/assets/theme.css';
$hasThemeCss = is_file($themeCssPath) && filesize($themeCssPath) > 0;
$linkCount = substr_count($html, '<link rel="stylesheet"');
$expectedMin = count($required);
$expectedMax = count($required) + 1;
if ($linkCount < $expectedMin || $linkCount > $expectedMax) {
    fwrite(STDERR, "setup emitted an unexpected stylesheet count: {$linkCount}\n");
    exit(1);
}
if ($hasThemeCss && strpos($html, '/assets/theme.css') === false) {
    fwrite(STDERR, "setup missing theme.css when asset exists\n");
    exit(1);
}
echo $html;
PHP
then
  ok "/setup renders its header without ThemePreferenceService or StyleRegistryService"
else
  fail "/setup reached a forbidden resolver or emitted an invalid static chain"
fi

echo ""
echo "== Pre-auth resolver isolation =="
if php <<'PHP'
<?php
declare(strict_types=1);
define('APP_ROOT', getcwd());
define('APP_VERSION', 'test');

function current_lang(): string { return 'en'; }
function __(string $key): string { return $key; }
function t(string $key, array $params = []): string { return $key; }
function supported_language_labels(): array { return ['en' => 'English']; }

spl_autoload_register(static function (string $class): void {
    if (in_array($class, [
        'Apps\\Shell\\Services\\BrandIdentityService',
        'Apps\\Shell\\Services\\ThemePreferenceService',
        'Apps\\Shell\\Services\\StyleRegistryService',
    ], true)) {
        throw new RuntimeException('forbidden pre-auth resolver access: ' . $class);
    }
});

$themeCssPath = APP_ROOT . '/public/assets/theme.css';
$paths = [
  '/login',
  '/2fa',
  '/forgot-password',
  '/reset-password?token=test',
  '/account/setup?token=test',
  '/recovery',
  '/recovery/password-reset?token=test',
  '/maintenance',
  '/maintenance/window',
];
$required = [
    '/assets/system/shell-essential.css',
    '/assets/rendering/foundation.css',
    '/assets/effects/effects-none.css',
    '/assets/themes/liquid-glass-system.css',
    '/assets/system/semantic-aliases.css',
    '/assets/system/auth.css',
];

foreach ($paths as $path) {
    $_SERVER['REQUEST_URI'] = $path;
    ob_start();
    require APP_ROOT . '/public/views/layouts/auth_header.php';
    $html = (string)ob_get_clean();

    $position = -1;
    foreach ($required as $url) {
        $next = strpos($html, $url);
        if ($next === false || $next <= $position) {
            fwrite(STDERR, "missing or misordered pre-auth asset for {$path}: {$url}\n");
            exit(1);
        }
        $position = $next;
    }
    $hasThemeCss = is_file($themeCssPath) && filesize($themeCssPath) > 0;
    $linkCount = substr_count($html, '<link rel="stylesheet"');
    $expectedMin = count($required);
    $expectedMax = count($required) + 1;
    if ($linkCount < $expectedMin || $linkCount > $expectedMax) {
        fwrite(STDERR, "pre-auth path emitted an unexpected stylesheet count: {$path} ({$linkCount})\n");
        exit(1);
    }
    if ($hasThemeCss && strpos($html, '/assets/theme.css') === false) {
        fwrite(STDERR, "pre-auth path missing theme.css when asset exists: {$path}\n");
        exit(1);
    }
}
PHP
then
  ok "login, 2FA, recovery, and account setup render without runtime theme resolvers"
else
  fail "a pre-auth path reached a forbidden resolver or emitted an invalid static chain"
fi

echo ""
if [[ "$failures" -gt 0 ]]; then
  echo "RESULT: FAIL ($failures failure(s))" >&2
  exit 1
fi

echo "RESULT: PASS"
