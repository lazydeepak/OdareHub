<?php
define('APP_ROOT', __DIR__);
require_once 'app/Core/helpers.php';
require_once 'app/Core/DB.php';
try {
    $db = \App\Core\DB::conn();
    echo "✅ Connection successful!\n";
    
    // List tables
    $tablesResult = $db->query('SHOW TABLES');
    $tables = $tablesResult ? $tablesResult->fetch_all(MYSQLI_ASSOC) : [];
    echo "📋 Tables count: " . count($tables) . "\n";
    if (!empty($tables)) {
        echo "Tables:\n";
        foreach ($tables as $table) {
            $tableName = current($table);
            echo "  - $tableName\n";
        }
    }
    
    // Sample data from key manufacturing tables
    $sampleTables = ['mfg_part_demands', 'dispatch_entries', 'production_plans', 'qc_entries', 'assembly_plans'];
    foreach ($sampleTables as $table) {
        try {
            $count = \App\Core\DB::fetchOne("SELECT COUNT(*) as cnt FROM `$table`")['cnt'] ?? 0;
            echo "\n📊 $table: $count rows\n";
            if ($count > 0) {
                $samples = \App\Core\DB::fetchAll("SELECT * FROM `$table` ORDER BY id DESC LIMIT 2");
                echo "  Recent samples:\n";
                foreach ($samples as $row) {
                    echo "    ID: {$row['id']}, Updated: {$row['updated_at']}\n";
                }
            }
        } catch (Exception $e) {
            echo "  $table: Table not found or inaccessible\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    if (isset($db)) {
        echo "MySQL error: " . $db->error . "\n";
    }
}
?>

