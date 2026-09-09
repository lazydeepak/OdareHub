<?php
$content = file_get_contents('C:\Projects\OdareHub\apps\Shell\Composers\OperatorSurfaceComposer.php');

// Test the pattern directly
$pattern = '/(?:\$this->tr)\s*\(\s*\'(.[^\']+)\'\s*,\s*\'(.[^\']*)\'/';

echo "Pattern: " . $pattern . "\n";
echo "\$this->tr count: " . substr_count($content, '$this->tr(') . "\n";

preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
echo "Matches: " . count($matches) . "\n\n";

// Let's try simpler patterns
echo "=== Simple single-quote match ===\n";
preg_match_all("/'([^']+)'/", $content, $simple);
echo "Single-quoted strings: " . count($simple[1]) . "\n";

echo "\n=== Looking for literal \$this->tr( ===\n";
$pos = strpos($content, "\$this->tr('");
echo "Position of first '\$this->tr('': " . $pos . "\n";
if ($pos !== false) {
    echo "Context: " . substr($content, $pos, 80) . "\n";
}

echo "\n=== Test regex directly on substring ===\n";
$test = "\$this->tr('nav.daily_orders', 'Daily Orders'),";
echo "Test string: " . $test . "\n";
preg_match_all($pattern, $test, $tm);
echo "Test match count: " . count($tm[0]) . "\n";
if (count($tm[0]) > 0) {
    echo "Key: " . $tm[1][0] . ", Value: " . $tm[2][0] . "\n";
}

echo "\n=== Check if pattern syntax is valid ===\n";
if (@preg_match($pattern, '') === false) {
    echo "PATTERN IS INVALID!\n";
    echo "Error: " . preg_last_error_msg() . "\n";
} else {
    echo "Pattern compiles OK\n";
}
