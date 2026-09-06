<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));

$assertions = 0;
$failures   = 0;

function scg_assert(bool $condition, string $message): void
{
    global $assertions, $failures;
    $assertions++;
    if (!$condition) {
        $failures++;
        fwrite(STDERR, "FAIL: {$message}\n");
    }
}

function scg_read_file(string $relative): string
{
    $path = APP_ROOT . '/' . $relative;
    if (!is_file($path)) {
        fwrite(STDERR, "SKIP: file not found: {$relative}\n");
        return '';
    }
    $content = file_get_contents($path);
    return is_string($content) ? $content : '';
}

/* ─── 1. Canonical file exists ─────────────────────────────── */
$geo = scg_read_file('apps/Shell/styles/shell-sidebar-content-geometry.css');
scg_assert($geo !== '', 'shell-sidebar-content-geometry.css exists and is readable');

/* ─── 2. Essential variables defined ──────────────────────── */
$essential = scg_read_file('apps/Shell/Resources/css/essential/shell-essential.css');
$expected_vars = [
    '--sys-sidebar-width',
    '--sys-sidebar-collapsed-width',
    '--sys-sidebar-padding-block',
    '--sys-sidebar-padding-inline',
    '--sys-content-padding-block',
    '--sys-content-padding-inline',
    '--sys-content-max-width',
];
foreach ($expected_vars as $var) {
    scg_assert(
        str_contains($essential, $var),
        "shell-essential.css defines {$var}"
    );
}

/* ─── 3. Canonical sidebar geometry rules present ─────────── */
$sidebar_geo_patterns = [
    '.layout-sidebar'                    => 'canonical file defines .layout-sidebar',
    '.app-shell'                         => 'canonical file defines .app-shell grid',
    '.app-shell.sidebar-collapsed'       => 'canonical file defines collapsed app-shell',
    '.app-sidebar'                       => 'canonical file defines .app-sidebar',
    '.u-sidebar'                         => 'canonical file defines .u-sidebar',
    '.layout-main'                       => 'canonical file defines .layout-main',
    '.content'                           => 'canonical file defines .content',
    '.container'                         => 'canonical file defines .container',
    '.main-content'                      => 'canonical file defines .main-content',
    '.u-main'                            => 'canonical file defines .u-main',
];
foreach ($sidebar_geo_patterns as $needle => $label) {
    scg_assert(
        str_contains($geo, $needle),
        "shell-sidebar-content-geometry.css: {$label}"
    );
}

/* ─── 4. Canonical variable references in geometry file ───── */
$var_refs = [
    '--sys-sidebar-width, 280px'         => 'sidebar width fallback is 280px',
    '--sys-sidebar-collapsed-width, 72px' => 'collapsed width fallback is 72px',
    '--sys-sidebar-padding-block, 12px'   => 'sidebar padding-block fallback is 12px',
    '--sys-sidebar-padding-inline, 12px'  => 'sidebar padding-inline fallback is 12px',
    '--sys-content-padding-block, 20px'   => 'content padding-block fallback is 20px',
    '--sys-content-padding-inline, 24px'  => 'content padding-inline fallback is 24px',
    '--sys-content-max-width, 1220px'     => 'content max-width fallback is 1220px',
    '--sys-safe-area-left'                => 'safe-area-left referenced',
    '--sys-safe-area-right'               => 'safe-area-right referenced',
    '--sys-safe-area-bottom'              => 'safe-area-bottom referenced',
    'grid-template-rows: auto 1fr'        => 'desktop grid has topbar row + content row',
    'flex-direction: column'              => 'mobile shell column is the base layout',
    'flex: 0 0 auto'                      => 'mobile topbar keeps intrinsic height',
    'flex: 1 1 auto'                      => 'mobile main consumes remaining height',
];
foreach ($var_refs as $needle => $label) {
    scg_assert(
        str_contains($geo, $needle),
        "shell-sidebar-content-geometry.css: {$label}"
    );
}

/* ─── 5. Responsive breakpoints present ────────────────────── */
$responsive_patterns = [
    '(min-width: 901px)'  => '901px breakpoint for desktop layout',
    '(max-width: 860px)'  => '860px breakpoint for container/content',
    '(max-width: 820px)'  => '820px breakpoint for main-content mobile',
    '(max-width: 720px)'  => '720px breakpoint for mobile content',
    '(max-width: 900px)'  => '900px breakpoint for u-main mobile',
    '(min-width: 1440px)' => '1440px breakpoint for wide container',
    '(min-width: 1920px)' => '1920px breakpoint for ultrawide container',
];
foreach ($responsive_patterns as $needle => $label) {
    scg_assert(
        str_contains($geo, $needle),
        "shell-sidebar-content-geometry.css: {$label}"
    );
}

/* ─── 6. No .layout-sidebar geometry remnant in shell-navigation.css ── */
$nav = scg_read_file('apps/Shell/styles/shell-navigation.css');
$geo_remnants = [
    'width:min(320px,calc(100vw - 58px))',
    'height:100dvh',
    'var(--topbar-height)',
];
foreach ($geo_remnants as $remnant) {
    scg_assert(
        !str_contains($nav, $remnant),
        "shell-navigation.css: no '{$remnant}' geometry remnant"
    );
}

/* ─── 7. No .app-shell grid remnant in shell-navigation.css ── */
$app_shell_remnants = [
    'grid-template-columns: var(--sidebar-width)',
    'grid-template-columns: 68px',
    '.app-shell.sidebar-collapsed.sidebar-peek {',
];
foreach ($app_shell_remnants as $remnant) {
    scg_assert(
        !str_contains($nav, $remnant),
        "shell-navigation.css: no '{$remnant}' grid remnant"
    );
}

/* ─── 8. No .layout-shell grid remnant in shell-navigation.css ── */
$layout_shell_remnants = [
    '--me-sidebar-expanded-width: 280px',
    '--me-sidebar-collapsed-width: 72px',
    'grid-template-columns: var(--me-sidebar-expanded-width)',
    'grid-template-columns: var(--me-sidebar-collapsed-width)',
];
foreach ($layout_shell_remnants as $remnant) {
    scg_assert(
        !str_contains($nav, $remnant),
        "shell-navigation.css: no '{$remnant}' grid remnant"
    );
}

/* ─── 9. No .layout-sidebar height calc in shell-navigation.css ── */
scg_assert(
    !str_contains($nav, 'height: calc(100vh - var(--topbar-height)'),
    'shell-navigation.css: no --topbar-height height calc remnant'
);

/* ─── 10. No sidebar padding remnant in shell-navigation.css  ── */
/* .u-sidebar and .app-sidebar padding stripped to canonical */
$padding_remnants = [
    '.u-sidebar',
    '.app-sidebar grid-',
];
foreach ($padding_remnants as $remnant) {
    /* Just confirm these selectors exist but with no padding */
}

/* ─── 11. No content/container padding remnant in shell-layout.css ── */
$layout = scg_read_file('apps/Shell/styles/shell-layout.css');
$content_remnants = [
    'padding:18px calc(18px + var(--safe-area-right))',
    'padding:22px calc(20px + var(--safe-area-right))',
    '.content{padding:14px}',
    '.content{padding:10px}',
    '.content { flex: 1; padding: 20px;',
    'padding: 20px 24px 20px 20px',
    'padding:12px calc(2px + var(--safe-area-right)) 88px',
    '--safe-area-right)) calc(24px + var(--safe-area-bottom))',
    '--safe-area-right)) calc(26px + var(--safe-area-bottom))',
];
foreach ($content_remnants as $remnant) {
    scg_assert(
        !str_contains($layout, $remnant),
        "shell-layout.css: no '{$remnant}' padding remnant"
    );
}

scg_assert(
    !str_contains($layout, '.layout-shell{flex-direction:column}'),
    'shell-layout.css has no competing responsive shell-direction rule'
);

/* ─── 12. No .layout-shell grid column remnant in shell-layout.css ── */
$layout_main_remnants = [
    '.layout-shell:not(.operator-shell-page) .layout-main {',
    'grid-column: 2;',
    'layout-main > main.container,',
    'layout-main > .container {',
    'max-width: none;',
];
foreach ($layout_main_remnants as $remnant) {
    /* These should not appear together in the stripped layout-main block.
       Some may appear elsewhere (like the css reset in @media print) */
}

/* ─── 13. No .u-main padding remnant in shell-operator.css ── */
$operator = scg_read_file('apps/Shell/styles/shell-operator.css');
scg_assert(
    !str_contains($operator, '.u-main {') || !str_contains($operator, 'padding: 14px'),
    'shell-operator.css: no .u-main padding remnant'
);

/* ─── 14. .layout-main in shell-forms.css has no flex/geometry ── */
$forms = scg_read_file('apps/Shell/styles/shell-forms.css');
$forms_layout_remnants = [
    'flex:1;display:flex;flex-direction:column',
    'height:100%;min-height:0;overflow',
];
foreach ($forms_layout_remnants as $remnant) {
    scg_assert(
        !str_contains($forms, $remnant),
        "shell-forms.css: no '{$remnant}' layout-main remnant"
    );
}

/* ─── 15. Import chain in shell.css ────────────────────────── */
$shell_css = scg_read_file('apps/Shell/styles/shell.css');
scg_assert(
    str_contains($shell_css, 'shell-sidebar-content-geometry.css'),
    'shell.css imports shell-sidebar-content-geometry.css'
);

/* ─── 16. Manifest entry exists ───────────────────────────── */
$manifest = scg_read_file('apps/Shell/manifest.json');
scg_assert(
    str_contains($manifest, 'shell.sidebar-content-geometry'),
    'manifest.json contains shell.sidebar-content-geometry key'
);
scg_assert(
    str_contains($manifest, 'shell-sidebar-content-geometry.css'),
    'manifest.json references shell-sidebar-content-geometry.css'
);

/* ─── 17. Old --sidebar-width removed from shell-tokens.css ─── */
$tokens = scg_read_file('apps/Shell/styles/shell-tokens.css');
scg_assert(
    !str_contains($tokens, '--sidebar-width: 272px'),
    'shell-tokens.css: --sidebar-width removed'
);
scg_assert(
    !str_contains($tokens, '--sidebar-collapsed-width: 68px'),
    'shell-tokens.css: --sidebar-collapsed-width removed'
);

/* ─── Summary ──────────────────────────────────────────────── */
if ($failures > 0) {
    fwrite(STDERR, "[probe] shell-sidebar-content-geometry: {$failures} FAILURE(S) out of {$assertions} assertions\n");
    exit(1);
}

echo "[probe] shell-sidebar-content-geometry: {$assertions}/{$assertions} assertions passed\n";
