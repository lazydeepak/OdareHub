<?php
$root = 'C:\Projects\Susankhya';
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
);
$resLangFiles = [];
$errors = [];
foreach ($files as $f) {
    $p = $f->getPathname();
    if (strpos($p, 'Resources' . DIRECTORY_SEPARATOR . 'lang') !== false && $f->getExtension() === 'php') {
        $resLangFiles[] = $p;
        // Skip binary/large check - just verify parse
        $output = [];
        $exitCode = 0;
        exec('php -l "' . $p . '" 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            $errors[] = $p . ': ' . implode(', ', $output);
        }
    }
}

echo 'Total Resources/lang/ files: ' . count($resLangFiles) . PHP_EOL;

$byLocale = ['en.php' => 0, 'ja.php' => 0, 'ne.php' => 0];
foreach ($resLangFiles as $f) {
    $name = basename($f);
    if (isset($byLocale[$name])) $byLocale[$name]++;
}
foreach ($byLocale as $loc => $count) {
    echo '  ' . $loc . ': ' . $count . "\n";
}

$owners = [];
foreach ($resLangFiles as $f) {
    $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $f);
    $parts = explode(DIRECTORY_SEPARATOR, $rel);
    $ownerDir = '';
    foreach ($parts as $p) {
        if ($p === 'Resources') break;
        $ownerDir .= ($ownerDir === '' ? '' : DIRECTORY_SEPARATOR) . $p;
    }
    if (!isset($owners[$ownerDir])) $owners[$ownerDir] = ['en' => false, 'ja' => false, 'ne' => false, 'files' => []];
    $name = basename($f, '.php');
    $owners[$ownerDir][$name] = true;
    $owners[$ownerDir]['files'][] = $f;
}

echo "\nOwners with Resources/lang/: " . count($owners) . "\n";
$full = 0;
$enOnly = 0;
foreach ($owners as $o => $data) {
    if ($data['en'] && $data['ja'] && $data['ne']) $full++;
    elseif ($data['en'] && !$data['ja'] && !$data['ne']) $enOnly++;
}
echo 'Owners with full en/ja/ne: ' . $full . "\n";
echo 'Owners with en only: ' . $enOnly . "\n";

echo "\n--- Key Counts per Owner ---\n";
foreach ($owners as $owner => $data) {
    $enFile = $root . DIRECTORY_SEPARATOR . $owner . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . 'en.php';
    if (file_exists($enFile)) {
        $c = file_get_contents($enFile);
        preg_match_all("/'([^']+)'\s*=>/", $c, $m);
        echo '  ' . $owner . ': ' . count($m[1]) . " keys\n";
    }
}

if (!empty($errors)) {
    echo "\nPARSE ERRORS: " . count($errors) . "\n";
    foreach ($errors as $e) echo '  ' . $e . "\n";
} else {
    echo "\nAll files parse OK.\n";
}
