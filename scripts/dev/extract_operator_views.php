<?php
/**
 * Phase A: Operator Layer View Extraction
 *
 * Extracts each focus view block from OperatorSurfaceComposer::renderHTML()
 * into separate apps/Shell/Views/operator/{slug}.php files,
 * replacing each block with a single include line.
 */

$root         = dirname(__DIR__, 2);
$composerFile = $root . '/apps/Shell/Composers/OperatorSurfaceComposer.php';
$viewsDir     = $root . '/apps/Shell/Views/operator/';

if (!is_file($composerFile)) { fwrite(STDERR, "ERROR: composer not found\n"); exit(1); }
if (!is_dir($viewsDir)) { mkdir($viewsDir, 0755, true); echo "Created $viewsDir\n"; }

$focusMap = [
    'isWorkEntryFocus'       => 'work-entry',
    'isCriticalFocus'        => 'critical',
    'isRecentFocus'          => 'recent',
    'isPartsFocus'           => 'parts',
    'isPartsDetailFocus'     => 'parts-detail',
    'isProductionFocus'      => 'production',
    'isProcessingFocus'      => 'processing',
    'isPreparationFocus'     => 'preparation',
    'isDispatchFocus'        => 'dispatch',
    'isDispatchDetailFocus'  => 'dispatch-detail',
    'isCoverageFocus'        => 'coverage',
    'isQcFocus'              => 'qc',
    'isDispatchAdapterFocus' => 'dispatch-adapter',
    'isMachinesFocus'        => 'machines',
    'isAssemblyFocus'        => 'assembly',
    'isMaterialsFocus'       => 'materials',
    'isAccountFocus'         => 'account',
];

// Count PHP control-flow depth delta on one source line.
// Opens: if(, foreach(, for(, while(   Closes: endif, endforeach, endfor, endwhile
function countDepthChange(string $line): int
{
    $delta = 0;
    $closeTag = '?' . '>';
    // Collect all PHP segments from inline tags AND open-ended blocks
    $segments = [];
    preg_match_all('/<\?php(.*?)' . preg_quote($closeTag) . '/s', $line, $m);
    $segments = $m[1];
    if (strpos($line, $closeTag) === false && preg_match('/<\?php(.*)$/s', $line, $m2)) {
        $segments[] = $m2[1];
    }
    foreach ($segments as $seg) {
        $delta += preg_match_all('/\bif\s*\(/', $seg);
        $delta += preg_match_all('/\bforeach\s*\(/', $seg);
        $delta += preg_match_all('/\bfor\s*\(/', $seg);
        $delta += preg_match_all('/\bwhile\s*\(/', $seg);
        $delta -= preg_match_all('/\bendif\s*[;:]/', $seg);
        $delta -= preg_match_all('/\bendforeach\s*;/', $seg);
        $delta -= preg_match_all('/\bendfor\s*;/', $seg);
        $delta -= preg_match_all('/\bendwhile\s*;/', $seg);
    }
    return $delta;
}

$lines = file($composerFile); // default: keeps blank lines with their newlines
$n     = count($lines);

$outputLines    = [];
$extractedViews = [];

$closeTag = '?' . '>';
$openTag  = '<' . '?php';

$i = 0;
while ($i < $n) {
    $line = $lines[$i];

    // ── Dashboard (negation) block ───────────────────────────────────────────
    if (   strpos($line, 'if (!$isWorkEntryFocus') !== false
        && strpos($line, '!$isCriticalFocus')       !== false
        && strpos($line, '!$isAccountFocus')         !== false
    ) {
        $depth     = countDepthChange($line);
        $bodyLines = [];
        $endIdx    = -1;
        for ($j = $i + 1; $j < $n; $j++) {
            $depth += countDepthChange($lines[$j]);
            if ($depth <= 0) { $endIdx = $j; break; }
            $bodyLines[] = $lines[$j];
        }
        if ($endIdx >= 0) {
            $viewFile    = $viewsDir . 'dashboard.php';
            $body        = trim(implode('', $bodyLines));
            $header      = $openTag . "\n// Operator Layer View: dashboard\n// All composer scope variables available via include.\n" . $closeTag;
            $viewContent = $header . "\n" . $body . "\n";
            file_put_contents($viewFile, $viewContent);
            $linesW = substr_count($viewContent, "\n");
            echo "  + dashboard.php  ({$linesW}L)  [lines " . ($i + 1) . '-' . ($endIdx + 1) . "]\n";
            $extractedViews['dashboard'] = $viewFile;

            preg_match('/^(\s*)/', $line, $ws);
            $pad       = $ws[1] ?? '                ';
            $negations = implode(' && ', array_map(fn($v) => "!\$$v", array_keys($focusMap)));
            $outputLines[] = "{$pad}{$openTag} if ({$negations}): include APP_ROOT . '/apps/Shell/Views/operator/dashboard.php'; endif; {$closeTag}\n";
            $i = $endIdx + 1;
            continue;
        }
    }

    // ── Focus blocks ─────────────────────────────────────────────────────────
    $handledFocus = false;
    foreach ($focusMap as $var => $slug) {
        if (!preg_match('/if\s*\(\$' . preg_quote($var, '/') . '\)\s*:/', $line)) continue;

        // Pattern A: opening contains the close tag on same line
        $isPatternA = strpos($line, $closeTag) !== false;

        $setupLines = [];
        $bodyStart  = $i + 1;

        if (!$isPatternA) {
            // Collect setup lines inside the opening PHP block until standalone close-tag line
            for ($j = $i + 1; $j < $n; $j++) {
                $t = trim($lines[$j]);
                if ($t === $closeTag) { $bodyStart = $j + 1; break; }
                $setupLines[] = $lines[$j];
            }
        }

        // Nesting-aware search for closing endif
        $depth = countDepthChange($line);
        if ($depth <= 0) $depth = 1;
        foreach ($setupLines as $sl) { $depth += countDepthChange($sl); }

        $bodyLines = [];
        $endIdx    = -1;
        for ($j = $bodyStart; $j < $n; $j++) {
            $depth += countDepthChange($lines[$j]);
            if ($depth <= 0) { $endIdx = $j; break; }
            $bodyLines[] = $lines[$j];
        }

        if ($endIdx < 0) {
            fwrite(STDERR, "  WARNING: no endif found for \$$var\n");
            $outputLines[] = $line;
            break;
        }

        // Build view file
        $viewFile = $viewsDir . $slug . '.php';
        $header   = $openTag . "\n// Operator Layer View: $slug\n// All composer scope variables available via include.\n" . $closeTag;
        $parts    = [$header];
        if (!empty($setupLines)) {
            $parts[] = $openTag . "\n" . implode('', $setupLines) . $closeTag;
        }
        $parts[]     = trim(implode('', $bodyLines));
        $viewContent = implode("\n", $parts) . "\n";

        file_put_contents($viewFile, $viewContent);
        $linesW = substr_count($viewContent, "\n");
        echo "  + $slug.php  ({$linesW}L)  [lines " . ($i + 1) . '-' . ($endIdx + 1) . "]\n";
        $extractedViews[$slug] = $viewFile;

        preg_match('/^(\s*)/', $line, $ws);
        $pad = $ws[1] ?? '                ';
        $outputLines[] = "{$pad}{$openTag} if (\$$var): include APP_ROOT . '/apps/Shell/Views/operator/$slug.php'; endif; {$closeTag}\n";
        $i            = $endIdx + 1;
        $handledFocus = true;
        break;
    }

    if (!$handledFocus) {
        $outputLines[] = $line;
        $i++;
    }
}

$modified = implode('', $outputLines);

// ── Post-process ──────────────────────────────────────────────────────────────

// 1. Add $isNotificationsFocus variable
if (strpos($modified, '$isNotificationsFocus') === false) {
    $needle = '$isAccountFocus = ($focus === \'account\');';
    if (strpos($modified, $needle) !== false) {
        $modified = str_replace(
            $needle,
            $needle . "\n                \$isNotificationsFocus = (\$focus === 'notifications');",
            $modified
        );
        echo "  + Added \$isNotificationsFocus variable\n";
    }
}

// 2. Extend dashboard negation to exclude notifications
$modified = str_replace(
    "!\$isAccountFocus): include APP_ROOT . '/apps/Shell/Views/operator/dashboard.php'",
    "!\$isAccountFocus && !\$isNotificationsFocus): include APP_ROOT . '/apps/Shell/Views/operator/dashboard.php'",
    $modified
);

// 3. Add notifications include after materials
if (strpos($modified, "operator/notifications.php'") === false) {
    $needle = "include APP_ROOT . '/apps/Shell/Views/operator/materials.php'; endif; " . $closeTag;
    if (strpos($modified, $needle) !== false) {
        $modified = str_replace(
            $needle,
            $needle . "\n                {$openTag} if (\$isNotificationsFocus): include APP_ROOT . '/apps/Shell/Views/operator/notifications.php'; endif; {$closeTag}",
            $modified
        );
        echo "  + Added notifications include\n";
    }
}

// ── Write ─────────────────────────────────────────────────────────────────────

$backupFile = $composerFile . '.phase-a-backup';
if (!is_file($backupFile)) { copy($composerFile, $backupFile); echo "  Backup: $backupFile\n"; }
file_put_contents($composerFile, $modified);

$origL  = count(file($backupFile));
$newL   = substr_count($modified, "\n");
$viewCt = count($extractedViews);
echo "\n=== Phase A Complete ===\n";
echo "Composer: {$origL}L -> {$newL}L  (-" . ($origL - $newL) . ")\n";
echo "Views: {$viewCt} in {$viewsDir}\n";
echo "Next: php -l apps/Shell/Composers/OperatorSurfaceComposer.php\n";
