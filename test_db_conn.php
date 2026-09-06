<?php
require_once 'app/Core/DB.php';
try {
    $db = App\Core\DB::conn();
    echo "Connection successful!\n";
    $tables = $db->query('SHOW TABLES')->fetch_all(MYSQLI_ASSOC);
    echo "Tables (" . count($tables) . "):\n";
    foreach ($tables as $t) {
        echo '- ' . current($t) . "\n";
    }
    echo "\nSample data from mfg_part_demands (first 3):\n";
    $demands = App\Core\DB::fetchAll('SELECT * FROM mfg_part_demands ORDER BY id DESC LIMIT 3');
    print_r($demands);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

