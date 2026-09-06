<?php
declare(strict_types=1);

if (!function_exists('e')) {
  function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

$lang = current_lang();
$pageTitle = $pageTitle ?? __('app.name');
$authRequestPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$authRequestPath = is_string($authRequestPath) ? rtrim($authRequestPath, '/') : '/';
$authRequestPath = $authRequestPath === '' ? '/' : $authRequestPath;
$isFirstBootSetup = $authRequestPath === '/setup' || str_starts_with($authRequestPath, '/setup/');
$staticAuthPaths = ['/login', '/2fa', '/forgot-password', '/reset-password', '/account/setup'];
$staticAuthPathPrefixes = ['/recovery', '/maintenance'];
$isStaticAuthPath = in_array($authRequestPath, $staticAuthPaths, true);
if (!$isStaticAuthPath) {
  foreach ($staticAuthPathPrefixes as $staticPrefix) {
    if ($authRequestPath === $staticPrefix || str_starts_with($authRequestPath, $staticPrefix . '/')) {
      $isStaticAuthPath = true;
      break;
    }
  }
}
$isStaticAuthSurface = $isFirstBootSetup || $isStaticAuthPath;
$authBrandName = !$isStaticAuthSurface && class_exists('\\Apps\\Shell\\Services\\BrandIdentityService')
  ? \Apps\Shell\Services\BrandIdentityService::platformName()
  : (string)t('app.name');

if ($isStaticAuthSurface) {
  $authThemeChoices = ['system-liquid-glass' => 'System - Liquid Glass'];
  $authDefaultThemePreference = 'system-liquid-glass';
  $authDefaultThemeMode = 'system';
  $authDefaultColorStyle = 'liquid-glass';
} else {
  $themePreferenceServicePath = APP_ROOT . '/apps/Shell/Services/ThemePreferenceService.php';
  if (is_file($themePreferenceServicePath)) {
    require_once $themePreferenceServicePath;
  }
  $authThemeChoices = class_exists('\\Apps\\Shell\\Services\\ThemePreferenceService')
    ? \Apps\Shell\Services\ThemePreferenceService::themeChoices()
    : ['system-liquid-glass' => 'System - Liquid Glass'];
}
$authThemeAllowedPreferences = array_values(array_keys($authThemeChoices));
if (!$isStaticAuthSurface) {
  $authDefaultThemePreference = class_exists('\\Apps\\Shell\\Services\\ThemePreferenceService')
    ? \Apps\Shell\Services\ThemePreferenceService::defaultPreference()
    : 'system-liquid-glass';
  $authDefaultThemeMode = class_exists('\\Apps\\Shell\\Services\\ThemePreferenceService')
    ? \Apps\Shell\Services\ThemePreferenceService::modeFromPreference($authDefaultThemePreference)
    : 'system';
  $authDefaultColorStyle = class_exists('\\Apps\\Shell\\Services\\ThemePreferenceService')
    ? \Apps\Shell\Services\ThemePreferenceService::colorStyleFromPreference($authDefaultThemePreference)
    : 'liquid-glass';
}
$authDefaultEffectiveTheme = $authDefaultThemeMode === 'light' ? 'light' : 'dark';
?>
<!doctype html>
<html lang="<?= e($lang) ?>" data-theme="<?= e($authDefaultEffectiveTheme) ?>" data-theme-mode="<?= e($authDefaultThemeMode) ?>" data-color-style="<?= e($authDefaultColorStyle) ?>" data-theme-preference="<?= e($authDefaultThemePreference) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e((string)$pageTitle) ?></title>
  <script>
    (function () {
      var storageKey = 'erp-theme-preference';
      var fallbackPreference = <?= json_encode($authDefaultThemePreference, JSON_UNESCAPED_SLASHES) ?>;
      var allowedPreferences = <?= json_encode($authThemeAllowedPreferences, JSON_UNESCAPED_SLASHES) ?>;

      function normalizeThemePreference(value) {
        var raw = (value || '').toString().trim().toLowerCase();
        var fallbackParts = (fallbackPreference || 'system-liquid-glass').split('-');
        var fallbackStyle = fallbackParts.slice(1).join('-') || 'liquid-glass';
        if (allowedPreferences.indexOf(raw) !== -1) {
          return raw;
        }
        if (raw === 'light') {
          return 'light-' + fallbackStyle;
        }
        if (raw === 'dark') {
          return 'dark-' + fallbackStyle;
        }
        if (raw === 'system') {
          return 'system-' + fallbackStyle;
        }
        return fallbackPreference || 'system-liquid-glass';
      }

      function resolveSystemTheme() {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
          return 'dark';
        }
        return 'light';
      }

      function applyThemePreference(value) {
        var preference = normalizeThemePreference(value);
        var parts = preference.split('-');
        var mode = parts[0] === 'light' || parts[0] === 'dark' ? parts[0] : 'system';
        var colorStyle = parts.slice(1).join('-') || 'liquid-glass';
        var effectiveTheme = mode === 'system' ? resolveSystemTheme() : mode;
        document.documentElement.setAttribute('data-theme-mode', mode);
        document.documentElement.setAttribute('data-theme', effectiveTheme);
        document.documentElement.setAttribute('data-color-style', colorStyle);
        document.documentElement.setAttribute('data-theme-preference', preference);
        document.documentElement.style.colorScheme = effectiveTheme;
      }

      try {
        applyThemePreference(window.localStorage.getItem(storageKey) || fallbackPreference);
      } catch (_e) {
        applyThemePreference(fallbackPreference);
      }
    })();
  </script>
  <?php
    if ($isStaticAuthSurface) {
      $themeCssPath = APP_ROOT . '/public/assets/theme.css';
      $firstBootStyles = [
        '/assets/system/shell-essential.css',
        '/assets/rendering/foundation.css',
        '/assets/effects/effects-none.css',
        '/assets/themes/liquid-glass-system.css',
        '/assets/system/semantic-aliases.css',
      ];
      if (is_file($themeCssPath) && filesize($themeCssPath) > 0) {
        $firstBootStyles[] = '/assets/theme.css';
      }
      $firstBootStyles[] = $isFirstBootSetup ? '/assets/system/setup.css' : '/assets/system/auth.css';
      foreach ($firstBootStyles as $styleUrl) {
        $stylePath = APP_ROOT . '/public' . $styleUrl;
        $styleVersion = is_file($stylePath) ? (string)filemtime($stylePath) : '1';
        echo '  <link rel="stylesheet" href="' . e($styleUrl) . '?v=' . e($styleVersion) . '">' . "\n";
      }
    } elseif (class_exists('\Apps\Shell\Services\StyleRegistryService')) {
      $allStyles = array_merge(
        \Apps\Shell\Services\StyleRegistryService::globals(),
        \Apps\Shell\Services\StyleRegistryService::forSurface('auth', [])
      );
      foreach ($allStyles as $style) {
        $styleUrl = $style['url'] ?? '';
        $styleVersion = $style['version'] ?? '1';
        if ($styleUrl !== '') {
          echo '  <link rel="stylesheet" href="' . htmlspecialchars($styleUrl) . '?v=' . htmlspecialchars($styleVersion) . '">' . "\n";
        }
      }
    }
  ?>
  <script>
    function switchLang(value) {
      const url = new URL(window.location.href);
      url.searchParams.set('lang', value);
      window.location.href = url.toString();
    }
  </script>
</head>
<body class="auth-page">

<div class="auth-shell">
  <header class="auth-topbar">
    <a href="/" class="auth-brand"><strong><?= e($authBrandName) ?></strong></a>

    <div class="auth-topbar-tools">
      <label class="auth-select-wrap" for="authLangSelect">
        <span class="auth-select-label"><?= e((string)($authLanguageLabel ?? t('common.language'))) ?></span>
        <select id="authLangSelect" onchange="switchLang(this.value)">
          <?php foreach (supported_language_labels() as $languageCode => $languageLabel): ?>
            <option value="<?= e($languageCode) ?>" <?= $lang === $languageCode ? 'selected' : '' ?>><?= e($languageLabel) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
  </header>

  <main class="auth-main">
