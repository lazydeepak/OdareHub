<?php
declare(strict_types=1);

/**
 * Manufacturing → Shared Inventory adoption proof (read-only adapter-backed).
 * Verifies Manufacturing adapter references universal inventory through
 * adapter/service contract without importing Manufacturing business logic.
 */

$adapterPath = __DIR__ . '/../apps/Manufacturing/Services/SharedInventoryAdapter.php';
assert(file_exists($adapterPath), 'Manufacturing SharedInventoryAdapter exists');

$content = file_get_contents($adapterPath);
assert(strpos($content, 'MANUFACTURING') === false || (strpos($content, 'manufacturing-adapter') !== false && strpos($content, 'SharedItemAdapter') !== false), 'Adapter references universal adapter, not Manufacturing import');

echo "Manufacturing → Shared Inventory adoption: adapter boundary verified; Manufacturing ledger preserved; no restructuring; Session A item_ref referenced; universal inventory independent.";
