<?php
/**
 * Extract localization keys from Shell and Base plugin PHP files
 * and generate canonical Resources/lang/en.php files.
 */

$projectRoot = realpath(__DIR__ . '/../..');

function extractKeys($filePath) {
    $content = file_get_contents($filePath);
    if ($content === false) {
        return [];
    }

    $keys = [];

    // Pattern: $this->tr('key', 'fallback'), self::tr('key', 'fallback'),
    // $operatorRouteTr('key', 'fallback'), $dxTr('key', 'fallback')
    $pattern = "/(?:\\\$this->tr|\\\$operatorRouteTr|\\\$dxTr|self::tr|static::tr)\\s*\\(\\s*'(.[^']+)'\\s*,\\s*'(.[^']*)'/s";

    preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $key = $match[1];
        $value = $match[2];
        // Skip dynamic/variable keys
        if (strpos($key, '$') !== false) {
            continue;
        }
        // Handle escape sequences
        $value = str_replace(["\\'", "\\n"], ["'", "\n"], $value);
        if (!isset($keys[$key])) {
            $keys[$key] = $value;
        }
    }

    ksort($keys);
    return $keys;
}

function writeLocaleFile($filePath, $keys, $ownerLabel) {
    $dir = dirname($filePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "  [DIR]  $ownerLabel/Resources/lang/\n";
    }

    $content = "<?php\n\nreturn [\n";
    foreach ($keys as $key => $value) {
        $escapedKey = str_replace("'", "\\'", $key);
        $escapedValue = str_replace(["'", "\n"], ["\\'", "\\n"], $value);
        $content .= "    '$escapedKey' => '$escapedValue',\n";
    }
    $content .= "];\n";

    file_put_contents($filePath, $content);
    echo "  [GEN]  $ownerLabel/Resources/lang/en.php (" . count($keys) . " keys)\n";

    // Create empty ja.php and ne.php
    foreach (['ja', 'ne'] as $locale) {
        $localePath = $dir . "/$locale.php";
        if (!file_exists($localePath)) {
            $emptyContent = "<?php\n\nreturn [\n";
            foreach ($keys as $key => $value) {
                $escapedKey = str_replace("'", "\\'", $key);
                $emptyContent .= "    '$escapedKey' => '',\n";
            }
            $emptyContent .= "];\n";
            file_put_contents($localePath, $emptyContent);
            echo "  [NEW]  $ownerLabel/Resources/lang/$locale.php (" . count($keys) . " empty keys)\n";
        }
    }
}

// --- Shell extraction ---
echo "=== Shell ===\n";
$shellKeys = [];
$shellFiles = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projectRoot . '/apps/Shell', RecursiveDirectoryIterator::SKIP_DOTS)
);
foreach ($shellFiles as $file) {
    if ($file->getExtension() === 'php') {
        $fileKeys = extractKeys($file->getPathname());
        $shellKeys = array_merge($shellKeys, $fileKeys);
        if (!empty($fileKeys)) {
            echo "  Scan: " . $file->getFilename() . " -> " . count($fileKeys) . " keys\n";
        }
    }
}

$shellTarget = $projectRoot . '/apps/Shell/Resources/lang/en.php';
writeLocaleFile($shellTarget, $shellKeys, 'apps/Shell');

// --- Base plugins extraction ---
echo "\n=== Base ===\n";
$baseKeys = [];
$baseFiles = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projectRoot . '/plugins/Base', RecursiveDirectoryIterator::SKIP_DOTS)
);
foreach ($baseFiles as $file) {
    if ($file->getExtension() === 'php') {
        $fileKeys = extractKeys($file->getPathname());
        $baseKeys = array_merge($baseKeys, $fileKeys);
        if (!empty($fileKeys)) {
            echo "  Scan: " . $file->getFilename() . " -> " . count($fileKeys) . " keys\n";
        }
    }
}

$baseTarget = $projectRoot . '/plugins/Base/Resources/lang/en.php';
writeLocaleFile($baseTarget, $baseKeys, 'plugins/Base');

echo "\n=== Done ===\n";
echo "Shell: " . count($shellKeys) . " unique keys\n";
echo "Base:  " . count($baseKeys) . " unique keys\n";
