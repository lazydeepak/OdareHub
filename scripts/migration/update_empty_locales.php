<?php
$projectRoot = realpath(__DIR__ . '/../..');

function updateLocaleFiles($dir, $keys, $label) {
    $enFile = "$dir/en.php";
    if (!file_exists($enFile)) {
        echo "  [SKIP] $label - no en.php\n";
        return;
    }

    // Read existing keys from en.php
    $content = file_get_contents($enFile);
    preg_match_all("/'([^']+)'\s*=>/", $content, $matches);
    $existingKeys = $matches[1] ?? [];

    if (count($existingKeys) === 0) {
        echo "  [WARN] $label - en.php has 0 keys!\n";
        return;
    }

    foreach (['ja', 'ne'] as $locale) {
        $localePath = "$dir/$locale.php";
        $emptyContent = "<?php\n\nreturn [\n";
        foreach ($keys as $key => $value) {
            $escapedKey = str_replace("'", "\\'", $key);
            $emptyContent .= "    '$escapedKey' => '',\n";
        }
        $emptyContent .= "];\n";
        file_put_contents($localePath, $emptyContent);
        echo "  [UPD]  $label/Resources/lang/$locale.php (" . count($keys) . " empty keys)\n";
    }
}

// Shell: read existing en.php to get keys, then update ja/ne
$shellDir = "$projectRoot/apps/Shell/Resources/lang";
$shellEn = "$shellDir/en.php";
$shellContent = file_get_contents($shellEn);
$shellKeys = [];
preg_match_all("/'([^']+)'\s*=>\s*'([^']*)'/", $shellContent, $shellMatches, PREG_SET_ORDER);
foreach ($shellMatches as $m) {
    $shellKeys[$m[1]] = $m[2];
}
echo "=== Shell ===\n";
echo "  Read " . count($shellKeys) . " keys from en.php\n";
updateLocaleFiles($shellDir, $shellKeys, 'apps/Shell');

// Base: read existing en.php to get keys, then update ja/ne
$baseDir = "$projectRoot/plugins/Base/Resources/lang";
$baseEn = "$baseDir/en.php";
$baseContent = file_get_contents($baseEn);
$baseKeys = [];
preg_match_all("/'([^']+)'\s*=>\s*'([^']*)'/", $baseContent, $baseMatches, PREG_SET_ORDER);
foreach ($baseMatches as $m) {
    $baseKeys[$m[1]] = $m[2];
}
echo "\n=== Base ===\n";
echo "  Read " . count($baseKeys) . " keys from en.php\n";
updateLocaleFiles($baseDir, $baseKeys, 'plugins/Base');
