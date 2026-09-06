<?php
$content = file_get_contents('C:\Projects\Susankhya\apps\Shell\Composers\OperatorSurfaceComposer.php');

// Test various regex patterns
echo "=== Pattern 1: Single-quoted, single line ===\n";
preg_match_all("/(?:\$this->tr|\$operatorRouteTr|\$dxTr|self::tr|static::tr)\s*\(\s*'([^']+)'\s*,\s*'([^']*)'/", $content, $matches, PREG_SET_ORDER);
echo "Found: " . count($matches) . "\n";

echo "\n=== Pattern 2: With 's' flag (dotall) ===\n";
preg_match_all("/(?:\$this->tr|\$operatorRouteTr|\$dxTr|self::tr|static::tr)\s*\(\s*'([^']+)'\s*,\s*'([^']*)'/s", $content, $matches2, PREG_SET_ORDER);
echo "Found: " . count($matches2) . "\n";

echo "\n=== Pattern 3: Multi-line with 's' flag ===\n";
preg_match_all("/(?:\$this->tr|\$operatorRouteTr|\$dxTr|self::tr|static::tr)\s*\(\s*'([^']+)'\s*,\s*'([^']*?)'/s", $content, $matches3, PREG_SET_ORDER);
echo "Found: " . count($matches3) . "\n";

// Show first 20
$count = 0;
foreach ($matches3 as $m) {
    echo "  " . $m[1] . " => " . substr($m[2], 0, 60) . "\n";
    $count++;
    if ($count >= 20) break;
}

echo "\n=== Pattern 4: Using 'm' flag ===\n";
preg_match_all("/(?:\$this->tr|\$operatorRouteTr|\$dxTr|self::tr|static::tr)\s*\(\s*'([^']+)'\s*,\s*'([^']*)'/m", $content, $matches4, PREG_SET_ORDER);
echo "Found: " . count($matches4) . "\n";

echo "\n=== Pattern 5: Check what lines DO match ===\n";
// Count total $this->tr occurrences
echo "Total '\$this->tr(' occurrences: " . substr_count($content, '$this->tr(') . "\n";
