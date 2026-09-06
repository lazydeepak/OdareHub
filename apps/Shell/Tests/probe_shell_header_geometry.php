<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));

$assertions = 0;
$failures   = 0;

function hg_assert(bool $condition, string $message): void
{
    global $assertions, $failures;
    $assertions++;
    if (!$condition) {
        $failures++;
        fwrite(STDERR, "FAIL: {$message}\n");
    }
}

function read_file(string $relative): string
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
$geo = read_file('apps/Shell/styles/shell-header-geometry.css');
hg_assert($geo !== '', 'shell-header-geometry.css exists and is readable');

/* ─── 2. Essential variables defined ──────────────────────── */
$essential = read_file('apps/Shell/Resources/css/essential/shell-essential.css');
$expected_vars = [
    '--sys-header-block-size',
    '--sys-header-padding-block',
    '--sys-header-padding-inline',
    '--sys-header-gap',
];
foreach ($expected_vars as $var) {
    hg_assert(
        str_contains($essential, $var),
        "shell-essential.css defines {$var}"
    );
}

/* ─── 3. Sticky positioning rules in canonical file ───────── */
$sticky_geo_patterns = [
    '.topbar,'               => 'shared selector group starts with .topbar',
    '.u-header'              => 'shared selector group includes .u-header',
    'position: sticky'       => 'canonical file declares position: sticky',
    'z-index: var(--shell-z-topbar' => 'canonical file uses --shell-z-topbar',
];
foreach ($sticky_geo_patterns as $needle => $label) {
    hg_assert(
        str_contains($geo, $needle),
        "shell-header-geometry.css: {$label}"
    );
}

/* ─── 4. Geometry values present ──────────────────────────── */
$geo_value_patterns = [
    'block-size: var(--sys-header-block-size' => '.topbar-inner uses --sys-header-block-size',
    'padding-block: var(--sys-header-padding-block' => 'padding-block uses variable',
    '--sys-header-padding-inline, 16px'  => 'padding-inline fallback is 16px',
    '--sys-header-gap, 12px'             => 'gap fallback is 12px',
    '--sys-safe-area-top'                  => 'safe-area-top referenced',
    '--sys-safe-area-left'                 => 'safe-area-left referenced',
    '--sys-safe-area-right'                => 'safe-area-right referenced',
];
foreach ($geo_value_patterns as $needle => $label) {
    hg_assert(
        str_contains($geo, $needle),
        "shell-header-geometry.css: {$label}"
    );
}

/* ─── 5. Responsive compaction ────────────────────────────── */
$responsive_patterns = [
    '--sys-header-padding-inline: 12px' => 'at 720px, padding-inline compacts to 12px',
    '--sys-header-gap: 8px'             => 'at 720px, gap compacts to 8px',
    '--sys-header-block-size: 52px'     => 'at 480px, block-size compacts to 52px',
    '--sys-header-padding-block: 10px'  => 'at 480px, padding-block compacts to 10px',
];
foreach ($responsive_patterns as $needle => $label) {
    hg_assert(
        str_contains($geo, $needle),
        "shell-header-geometry.css: responsive {$label}"
    );
}

/* ─── 6. No fixed model remains in shell-layout.css ───────── */
$layout = read_file('apps/Shell/styles/shell-layout.css');
$fixed_blocks = [
    'position: fixed !important',
    'fixed !important',
    'padding-top: calc(var(--topbar-height)',
];
for ($i = count($fixed_blocks) - 1; $i >= 0; $i--) {
    hg_assert(
        !str_contains($layout, $fixed_blocks[$i]),
        "shell-layout.css: no '{$fixed_blocks[$i]}' remnant"
    );
}

/* ─── 7. No duplicate .topbar/.topbar-inner geometry in shell-layout.css ── */
/*     (the sticky position block that was at ~line 894 should be gone) */
$topbar_sticky_count = substr_count($layout, '.topbar');
/* One for appearance block, line ~299 */
hg_assert(
    $topbar_sticky_count >= 1,
    'shell-layout.css has at least one .topbar selector'
);

/* ─── 8. .u-header geometry removed from shell-surfaces.css ── */
$surfaces = read_file('apps/Shell/styles/shell-surfaces.css');
$u_header_remnants = ['min-height: 56px', 'padding: 10px 14px', 'position: sticky'];
foreach ($u_header_remnants as $remnant) {
    hg_assert(
        !str_contains($surfaces, $remnant),
        "shell-surfaces.css: no '{$remnant}' remnant in .u-header"
    );
}

/* ─── 9. Import chain in shell.css ────────────────────────── */
$shell_css = read_file('apps/Shell/styles/shell.css');
hg_assert(
    str_contains($shell_css, 'shell-header-geometry.css'),
    'shell.css imports shell-header-geometry.css'
);

/* ─── 10. Manifest entry exists ───────────────────────────── */
$manifest = read_file('apps/Shell/manifest.json');
hg_assert(
    str_contains($manifest, 'shell.header-geometry'),
    'manifest.json contains shell.header-geometry key'
);
hg_assert(
    str_contains($manifest, 'shell-header-geometry.css'),
    'manifest.json references shell-header-geometry.css'
);

/* ─── 11. notif-dropdown / overflow-dropdown use sys-safe-area ── */
$notif_refs = [
    '--sys-header-block-size, 58px' => 'notif-dropdown uses --sys-header-block-size',
    '--sys-safe-area-top, 0px'        => 'notif-dropdown uses --sys-safe-area-top',
];
foreach ($notif_refs as $needle => $label) {
    hg_assert(
        str_contains($layout, $needle),
        "shell-layout.css: {$label}"
    );
}

/* ─── Summary ──────────────────────────────────────────────── */
if ($failures > 0) {
    fwrite(STDERR, "[probe] shell-header-geometry: {$failures} FAILURE(S) out of {$assertions} assertions\n");
    exit(1);
}

echo "[probe] shell-header-geometry: {$assertions}/{$assertions} assertions passed\n";
