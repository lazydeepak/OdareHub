<?php
$root = 'C:\Projects\Susankhya';
$all = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
);
$errors = [];
$count = 0;
foreach ($all as $f) {
    $p = $f->getPathname();
    if (strpos($p, 'Resources' . DIRECTORY_SEPARATOR . 'lang') !== false && $f->getExtension() === 'php') {
        $count++;
        $output = [];
        $exitCode = 0;
        exec('php -l "' . $p . '" 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            $errors[] = $p . ': ' . implode(' ', $output);
        }
    }
}
echo 'Files checked: ' . $count . PHP_EOL;
if (empty($errors)) {
    echo 'All files parse OK.' . PHP_EOL;
} else {
    echo 'ERRORS: ' . count($errors) . PHP_EOL;
    foreach ($errors as $e) {
        echo '  ' . $e . PHP_EOL;
    }
}
