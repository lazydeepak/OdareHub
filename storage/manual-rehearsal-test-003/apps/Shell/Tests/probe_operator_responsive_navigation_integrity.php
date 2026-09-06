<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));

$assertions = 0;
$failures   = 0;

function nav_int_assert(bool $condition, string $message): void
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

/* ──────────────────────────────────────────────────────────────
 *  1. Coordinated breakpoint
 * ────────────────────────────────────────────────────────────── */

echo "[probe] Invariant 1: Coordinated breakpoint\n";

$navCss = read_file('apps/Shell/styles/shell-navigation.css');
nav_int_assert($navCss !== '', 'shell-navigation.css is readable');

if ($navCss !== '') {
    $lines = explode("\n", $navCss);

    // Find all @media (max-width: Npx) blocks and extract their brace-balanced content.
    $mediaBlocks = [];
    $i = 0;
    $count = count($lines);
    while ($i < $count) {
        if (preg_match('/@media\s*\(\s*max-width\s*:\s*(\d+)px\s*\)/', $lines[$i], $m)) {
            $breakpoint = (int)$m[1];
            $depth = 0;
            $started = false;
            $blockContent = '';
            $blockStart = $i;
            while ($i < $count) {
                $blockContent .= $lines[$i] . "\n";
                // Count braces (outside strings — sufficient for CSS).
                preg_match_all('/[{}]/', $lines[$i], $braces);
                foreach ($braces[0] as $b) {
                    if ($b === '{') {
                        $depth++;
                        $started = true;
                    } else {
                        $depth--;
                    }
                }
                if ($started && $depth <= 0) {
                    break;
                }
                $i++;
            }
            $mediaBlocks[] = [
                'breakpoint' => $breakpoint,
                'start'      => $blockStart + 1,
                'end'        => $i + 1,
                'content'    => $blockContent,
            ];
        }
        $i++;
    }

    // Find the 820px block.
    $target820 = null;
    foreach ($mediaBlocks as $block) {
        if ($block['breakpoint'] === 820) {
            $target820 = $block;
            break;
        }
    }

    nav_int_assert($target820 !== null, 'CSS has @media (max-width: 820px) block');

    if ($target820 !== null) {
        $content = $target820['content'];

        // Check .app-sidebar { display: none } (possibly across lines).
        $hasSidebarHide = (bool)preg_match(
            '/\.app-sidebar\s*\{[^}]*display\s*:\s*none/',
            $content
        );
        nav_int_assert(
            $hasSidebarHide,
            "820px block contains .app-sidebar { display: none } (lines {$target820['start']}-{$target820['end']})"
        );

        // Check .u-bottom-nav { display: flex } (possibly across lines).
        $hasBottomNavShow = (bool)preg_match(
            '/\.u-bottom-nav\s*\{[^}]*display\s*:\s*flex/',
            $content
        );
        nav_int_assert(
            $hasBottomNavShow,
            "820px block contains .u-bottom-nav { display: flex } (lines {$target820['start']}-{$target820['end']})"
        );

        // Verify sidebar-hide does NOT appear in a different media block.
        $sidebarHideInOther = false;
        foreach ($mediaBlocks as $block) {
            if ($block['breakpoint'] !== 820) {
                if (preg_match('/\.app-sidebar\s*\{[^}]*display\s*:\s*none/', $block['content'])) {
                    $sidebarHideInOther = true;
                    break;
                }
            }
        }
        nav_int_assert(
            !$sidebarHideInOther,
            '.app-sidebar { display: none } appears only in the 820px block (no independent breakpoint)'
        );

        // Verify bottom-nav show does NOT appear in a different media block with a different breakpoint.
        // (The 760px grid layout rule is a separate concern — only check for display:flex in non-820 blocks.)
        $bottomNavFlexInOther = false;
        foreach ($mediaBlocks as $block) {
            if ($block['breakpoint'] !== 820) {
                if (preg_match('/\.u-bottom-nav\s*\{[^}]*display\s*:\s*flex/', $block['content'])) {
                    $bottomNavFlexInOther = true;
                    break;
                }
            }
        }
        nav_int_assert(
            !$bottomNavFlexInOther,
            '.u-bottom-nav { display: flex } appears only in the 820px block (no independent breakpoint)'
        );
    }
}

/* ──────────────────────────────────────────────────────────────
 *  2. Hamburger trigger availability
 * ────────────────────────────────────────────────────────────── */

echo "[probe] Invariant 2: Hamburger trigger availability\n";

$headerSrc = read_file('apps/Shell/Composers/OperatorHeaderComposer.php');
nav_int_assert($headerSrc !== '', 'OperatorHeaderComposer.php is readable');

if ($headerSrc !== '') {
    // Hamburger trigger has expected id.
    nav_int_assert(
        str_contains($headerSrc, 'id="hamburgerToggle"'),
        'OperatorHeaderComposer renders id="hamburgerToggle"'
    );

    // Hamburger uses expected CSS class.
    nav_int_assert(
        preg_match('/class="[^"]*\bheader-hamburger\b/', $headerSrc) === 1,
        'OperatorHeaderComposer renders .header-hamburger class on trigger'
    );
}

// CSS: .header-hamburger has display: inline-flex (not hidden).
if ($navCss !== '') {
    $hamburgerVisible = (bool)preg_match(
        '/\.header-hamburger\s*,[^{]*\{[^}]*display\s*:\s*inline-flex/',
        $navCss
    );
    nav_int_assert(
        $hamburgerVisible,
        'CSS .header-hamburger has display: inline-flex (always visible)'
    );

    // No display:none rule targets .header-hamburger.
    $hamburgerHidden = (bool)preg_match(
        '/\.header-hamburger[^{]*\{[^}]*display\s*:\s*none/',
        $navCss
    );
    nav_int_assert(
        !$hamburgerHidden,
        'CSS has no display:none rule targeting .header-hamburger'
    );
}

// Interaction script registers click handler on hamburgerToggle.
$scriptSrc = read_file('apps/Shell/Composers/OperatorInteractionScriptComposer.php');
nav_int_assert($scriptSrc !== '', 'OperatorInteractionScriptComposer.php is readable');

if ($scriptSrc !== '') {
    nav_int_assert(
        str_contains($scriptSrc, "hamburgerToggle.addEventListener('click'"),
        'InteractionScript registers click handler on hamburgerToggle'
    );

    // Click handler references hamburgerMenu (the drawer it opens).
    nav_int_assert(
        str_contains($scriptSrc, 'hamburgerMenu') || str_contains($scriptSrc, 'hamburgerDrawer'),
        'InteractionScript click handler references the hamburger menu/drawer'
    );
}

/* ──────────────────────────────────────────────────────────────
 *  3. Shared sidebar/drawer source
 * ────────────────────────────────────────────────────────────── */

echo "[probe] Invariant 3: Shared sidebar/drawer source\n";

$sidebarSrc = read_file('apps/Shell/Composers/OperatorSidebarComposer.php');
nav_int_assert($sidebarSrc !== '', 'OperatorSidebarComposer.php is readable');

if ($sidebarSrc !== '') {
    // Sidebar reads contextual_sidebar sections.
    nav_int_assert(
        str_contains($sidebarSrc, "['contextual_sidebar']['sections']"),
        'OperatorSidebarComposer reads $data[\'contextual_sidebar\'][\'sections\']'
    );

    // Sidebar does not hardcode navigation hrefs (no inline /u/demo or /u/test links).
    $hardcodedNav = (bool)preg_match('/href\s*=\s*["\x27]\/u\/(?!{)/', $sidebarSrc);
    nav_int_assert(
        !$hardcodedNav,
        'OperatorSidebarComposer does not hardcode /u/{username} navigation hrefs'
    );
}

if ($headerSrc !== '') {
    // Drawer reads the same contextual_sidebar sections key.
    nav_int_assert(
        str_contains($headerSrc, "['contextual_sidebar']['sections']"),
        'OperatorHeaderComposer (drawer) reads $data[\'contextual_sidebar\'][\'sections\']'
    );

    // Drawer does not hardcode navigation hrefs.
    $headerHardcodedNav = (bool)preg_match('/href\s*=\s*["\x27]\/u\/(?!{)/', $headerSrc);
    nav_int_assert(
        !$headerHardcodedNav,
        'OperatorHeaderComposer drawer does not hardcode /u/{username} navigation hrefs'
    );
}

// Both reference the same data key (structural deduplication check).
if ($sidebarSrc !== '' && $headerSrc !== '') {
    $sidebarKey = str_contains($sidebarSrc, "['contextual_sidebar']['sections']");
    $drawerKey  = str_contains($headerSrc, "['contextual_sidebar']['sections']");
    nav_int_assert(
        $sidebarKey && $drawerKey,
        'Both OperatorSidebarComposer and OperatorHeaderComposer consume the same contextual_sidebar sections key'
    );
}

/* ──────────────────────────────────────────────────────────────
 *  4. Bottom-navigation active-state exclusivity
 * ────────────────────────────────────────────────────────────── */

echo "[probe] Invariant 4: Bottom-navigation active-state exclusivity\n";

$wrapperSrc = read_file('apps/Shell/Services/OperatorLayerWrapperComposer.php');
nav_int_assert($wrapperSrc !== '', 'OperatorLayerWrapperComposer.php is readable');

if ($wrapperSrc !== '') {
    // Extract all focuses arrays from the tab definitions.
    // Pattern: 'focuses' => ['val1', 'val2', ...]
    preg_match_all(
        "/'focuses'\s*=>\s*\[([^\]]*)\]/",
        $wrapperSrc,
        $matches
    );

    $allFocusTabs = [];
    foreach ($matches[1] as $focusStr) {
        preg_match_all("/'([^']*)'/", $focusStr, $vals);
        $allFocusTabs[] = $vals[1];
    }

    nav_int_assert(count($allFocusTabs) >= 2, 'Found at least 2 bottom-nav tab definitions with focuses');

    // Build focus→tab-index mapping and verify exclusivity.
    $focusMap = [];
    foreach ($allFocusTabs as $tabIdx => $focuses) {
        foreach ($focuses as $f) {
            if ($f === '') {
                continue; // Empty focus is a catch-all, skip for exclusivity.
            }
            if (isset($focusMap[$f])) {
                nav_int_assert(
                    false,
                    "Focus '{$f}' appears in multiple tabs (tab {$focusMap[$f]} and tab {$tabIdx}) — exclusivity violated"
                );
            }
            $focusMap[$f] = $tabIdx;
        }
    }

    // Verify expected groupings.
    $expectedHome = ['dashboard', 'critical', 'recent'];
    $expectedWorkEntry = ['work-entry'];
    $expectedAccount = ['account', 'notifications', 'messages'];

    foreach ($expectedHome as $f) {
        nav_int_assert(
            isset($focusMap[$f]) && $focusMap[$f] === 0,
            "Focus '{$f}' belongs to Home tab (tab 0)"
        );
    }

    foreach ($expectedWorkEntry as $f) {
        nav_int_assert(
            isset($focusMap[$f]) && $focusMap[$f] === 1,
            "Focus '{$f}' belongs to Work Entry tab (tab 1)"
        );
    }

    foreach ($expectedAccount as $f) {
        nav_int_assert(
            isset($focusMap[$f]) && $focusMap[$f] === 3,
            "Focus '{$f}' belongs to Account tab (tab 3)"
        );
    }

    // Verify work-entry is NOT in the Home tab.
    nav_int_assert(
        !in_array('work-entry', $allFocusTabs[0], true),
        'work-entry is NOT in the Home tab focuses array (exclusivity fix)'
    );

    // Verify no duplicate active states for specific focus values.
    $testedFocuses = array_merge($expectedHome, $expectedWorkEntry, $expectedAccount);
    foreach ($testedFocuses as $f) {
        $count = 0;
        foreach ($allFocusTabs as $focuses) {
            if (in_array($f, $focuses, true)) {
                $count++;
            }
        }
        nav_int_assert(
            $count === 1,
            "Focus '{$f}' appears in exactly 1 tab (found {$count})"
        );
    }

    // Verify action buttons (Scan) don't receive active states via focuses.
    // Scan tab has empty focuses — it's a button, not a navigation link.
    $scanFocuses = $allFocusTabs[2] ?? [];
    nav_int_assert(
        $scanFocuses === [],
        'Scan tab (tab 2) has empty focuses array (action button, not navigation link)'
    );

    // Verify production/machines/coverage/preferences/parts-detail are NOT in any tab.
    $noActiveTabs = ['production', 'machines', 'coverage', 'preferences', 'parts-detail'];
    foreach ($noActiveTabs as $f) {
        if (isset($focusMap[$f])) {
            nav_int_assert(
                false,
                "Focus '{$f}' should NOT appear in any bottom-nav tab (found in tab {$focusMap[$f]})"
            );
        } else {
            nav_int_assert(true, "Focus '{$f}' correctly absent from all bottom-nav tabs");
        }
    }
}

/* ──────────────────────────────────────────────────────────────
 *  5. Username confinement
 * ────────────────────────────────────────────────────────────── */

echo "[probe] Invariant 5: Username confinement\n";

if ($wrapperSrc !== '') {
    // All tab hrefs use /u/{$u}/ pattern.
    preg_match_all("/'href'\s*=>\s*\"(\/u\/[^\"]*)\"/", $wrapperSrc, $hrefMatches);
    nav_int_assert(count($hrefMatches[1]) > 0, 'Bottom-nav tabs contain href values');

    foreach ($hrefMatches[1] as $idx => $href) {
        nav_int_assert(
            preg_match('#^/u/\{\$[a-zA-Z_]+\}/#', $href) === 1,
            "Bottom-nav href '{$href}' uses /u/{{\$u}}/ pattern"
        );
    }

    // Sidebar routes are data-driven (read from $data, not hardcoded in composer).
    // Username confinement is enforced by OperatorLayerSidebarService generating /u/{user}/... routes.
    if ($sidebarSrc !== '') {
        $sidebarHasHardcodedRoute = (bool)preg_match('/route.*=>.*["\x27]\/u\/[a-z]+\/[a-z]+/', $sidebarSrc);
        nav_int_assert(
            !$sidebarHasHardcodedRoute,
            'OperatorSidebarComposer routes are data-driven (not hardcoded)'
        );
    }

    // Drawer routes come from contextual_sidebar (same source as sidebar).
    // Already verified in invariant 3 — drawer reads $data['contextual_sidebar']['sections'].
    nav_int_assert(true, 'Drawer routes sourced from contextual_sidebar (verified in invariant 3)');
}

/* ──────────────────────────────────────────────────────────────
 *  Summary
 * ────────────────────────────────────────────────────────────── */

echo "\n";

if ($failures > 0) {
    fwrite(STDERR, "[probe] Operator responsive navigation integrity: {$failures} FAILURE(S) out of {$assertions} assertions\n");
    exit(1);
}

echo "[probe] Operator responsive navigation integrity: {$assertions}/{$assertions} assertions passed\n";
