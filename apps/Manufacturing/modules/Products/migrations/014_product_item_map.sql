-- Products-owned Product -> Shared Item identity mapping.
--
-- This is an additive compatibility seam. Existing products.item_ref and
-- Manufacturing BOM refs remain unchanged and are not backfilled here.
-- Tenant ownership is intentionally absent: Products has no authoritative
-- organization contract yet, so runtime mapping mutation remains disabled.
CREATE TABLE IF NOT EXISTS manufacturing_product_item_map (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_product_item_map_product (product_id),
    KEY idx_product_item_map_item (item_id),
    CONSTRAINT fk_product_item_map_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_product_item_map_item
        FOREIGN KEY (item_id) REFERENCES shared_items(item_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
