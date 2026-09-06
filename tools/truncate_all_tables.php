<?php
define('APP_ROOT', __DIR__);
require_once 'app/Core/helpers.php';
require_once 'app/Core/DB.php';

try {
    \App\Core\DB::conn();
    $db = \App\Core\DB::conn();
    
    $db->query('SET FOREIGN_KEY_CHECKS = 0');
    $db->query('SET UNIQUE_CHECKS = 0');
    
    $tables = $db->query('SHOW TABLES');
    $truncateCount = 0;
    while ($table = $tables->fetch_array()) {
        $tableName = $table[0];
        $db->query("TRUNCATE TABLE `$tableName`");
        $truncateCount++;
        echo "Truncated $tableName\n";
    }
    
    $db->query('SET FOREIGN_KEY_CHECKS = 1');
    $db->query('SET UNIQUE_CHECKS = 1');
    
    echo "✅ All $truncateCount tables truncated successfully!\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>

