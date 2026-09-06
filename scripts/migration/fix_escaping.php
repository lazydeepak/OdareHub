<?php
$root = 'C:\Projects\Susankhya';

function fixLocaleFile($path) {
    $content = file_get_contents($path);
    if ($content === false) return;

    // First, check if it parses
    $output = [];
    $exitCode = 0;
    exec('php -l "' . $path . '" 2>&1', $output, $exitCode);
    if ($exitCode === 0) {
        echo "  [OK]   " . basename(dirname(dirname($path))) . "/$path\n";
        return;
    }

    echo "  [FIX]  " . basename(dirname(dirname($path))) . "/$path\n";

    // Rebuild the file with proper escaping
    preg_match_all("/'([^']+)'\s*=>\s*'([^']*)'/", $content, $matches, PREG_SET_ORDER);
    
    $keys = [];
    foreach ($matches as $m) {
        $key = $m[1];
        $value = $m[2];
        // Handle backslash escaping for single-quoted PHP strings
        // In single-quoted PHP strings: \\ = literal \, \' = literal '
        // If value ends with \, it needs to be doubled
        $keys[$key] = $value;
    }

    $newContent = "<?php\n\nreturn [\n";
    foreach ($keys as $key => $value) {
        $escapedKey = str_replace("'", "\\'", $key);
        // Proper single-quote string escaping:
        // 1. Double all backslashes first
        // 2. Then escape single quotes
        $escapedValue = str_replace("\\", "\\\\", $value);
        $escapedValue = str_replace("'", "\\'", $escapedValue);
        $newContent .= "    '$escapedKey' => '$escapedValue',\n";
    }
    $newContent .= "];\n";

    file_put_contents($path, $newContent);
}

echo "=== Fixing generated locale files ===\n";

$fixDirs = [
    $root . '/apps/Shell/Resources/lang',
    $root . '/plugins/Base/Resources/lang',
];

foreach ($fixDirs as $dir) {
    if (!is_dir($dir)) continue;
    echo "  Scanning: $dir\n";
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($files as $f) {
        if ($f->getExtension() === 'php') {
            fixLocaleFile($f->getPathname());
        }
    }
}

// Also check all other Resources/lang/ files for parse errors
echo "\n=== Verifying all Resources/lang/ files ===\n";
$allFiles = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
);
$errors = [];
foreach ($allFiles as $f) {
    $p = $f->getPathname();
    if (strpos($p, 'Resources' . DIRECTORY_SEPARATOR . 'lang') !== false && $f->getExtension() === 'php') {
        $output = [];
        $exitCode = 0;
        exec('php -l "' . $p . '" 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            $errors[] = $p . ': ' . implode(' ', $output);
        }
    }
}

if (empty($errors)) {
    echo "All " . count($allFiles) . " files parse OK.\n";
} else {
    echo "ERRORS: " . count($errors) . "\n";
    foreach ($errors as $e) echo "  $e\n";
}
