<?php
declare(strict_types=1);

/**
 * DB-free IPM-004 Product -> Shared Item mapping contract probe.
 *
 * The mapping is schema-only in this milestone. Products has no authoritative
 * organization ownership, so this probe requires the absence of a runtime
 * mutation service rather than allowing an unsafe global mutation path.
 */
$root = dirname(__DIR__, 5);
$migration = $root . '/apps/Manufacturing/modules/Products/migrations/014_product_item_map.sql';
$productService = $root . '/apps/Manufacturing/modules/Products/Services/PartItemRefService.php';
$bomService = $root . '/apps/Manufacturing/modules/Bom/BomService.php';

$passed = 0;
$failed = 0;
$check = static function (bool $ok, string $label) use (&$passed, &$failed): void {
    if ($ok) {
        $passed++;
    } else {
        $failed++;
    }
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . "\n";
};

$sql = is_file($migration) ? (string)file_get_contents($migration) : '';
$check($sql !== '', 'Products mapping migration exists');
$check(str_contains($sql, 'CREATE TABLE IF NOT EXISTS manufacturing_product_item_map'), 'mapping table is Products-owned and idempotent');
$check(str_contains($sql, 'product_id INT NOT NULL'), 'mapping preserves Products INT identity');
$check(str_contains($sql, 'item_id BIGINT UNSIGNED NOT NULL'), 'mapping adopts Shared BIGINT identity');
$check(str_contains($sql, 'UNIQUE KEY uniq_product_item_map_product'), 'one Product has at most one canonical Item');
$check(!str_contains($sql, 'UNIQUE KEY uniq_product_item_map_item'), 'canonical Item may map to multiple Product representations');
$check(str_contains($sql, 'KEY idx_product_item_map_item (item_id)'), 'reverse lookup has an explicit Item index');
$check(str_contains($sql, 'REFERENCES products(id)'), 'Product foreign key is database-enforced');
$check(str_contains($sql, 'REFERENCES shared_items(item_id)'), 'Shared Item foreign key is database-enforced');
$check(str_contains($sql, 'ON DELETE RESTRICT'), 'mapping deletion cannot orphan either identity');
$check(substr_count($sql, 'ON DELETE RESTRICT') === 2, 'both mapping foreign keys use RESTRICT');
$check(!preg_match('/\b(?:organization_id|user_id|users|org_companies)\b/i', $sql), 'mapping does not invent tenant or actor ownership');
$check(!preg_match('/ALTER TABLE\s+products|manufacturing_bom|finished_item_ref|component_item_ref/i', $sql), 'migration does not modify Product or BOM compatibility columns');
$check(!preg_match('/INSERT INTO|UPDATE\s+products|DELETE FROM|CREATE\s+TABLE.*identity_map/i', $sql), 'migration contains no backfill, mutation service, or generic identity map');

$legacy = is_file($productService) ? (string)file_get_contents($productService) : '';
$bom = is_file($bomService) ? (string)file_get_contents($bomService) : '';
$check(str_contains($legacy, 'SELECT id FROM products WHERE item_ref = ?'), 'legacy item_ref duplicate rule remains');
$check(str_contains($legacy, 'UPDATE products SET item_ref = ?'), 'legacy item_ref assignment remains separate');
$check(str_contains($bom, 'LEFT JOIN products p ON p.item_ref = b.finished_item_ref'), 'BOM finished-item resolution remains unchanged');
$check(str_contains($bom, 'LEFT JOIN products p ON p.item_ref = l.component_item_ref'), 'BOM component resolution remains unchanged');
$check(!is_file($root . '/apps/Manufacturing/modules/Products/Services/ProductItemMappingService.php'), 'tenant-unsafe runtime mapping mutation is not introduced');

echo "Product Item mapping contract probe: {$passed}/" . ($passed + $failed) . " assertions passed\n";
exit($failed === 0 ? 0 : 1);
