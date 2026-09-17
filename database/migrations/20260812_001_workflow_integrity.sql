CREATE TABLE IF NOT EXISTS goods_receipt_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    goods_receipt_id BIGINT UNSIGNED NOT NULL,
    purchase_order_item_id BIGINT UNSIGNED NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(14,3) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    unit_cost DECIMAL(14,3) NOT NULL DEFAULT 0,
    lot_number VARCHAR(60) NOT NULL,
    storage_zone_id BIGINT UNSIGNED NOT NULL,
    expiry_date DATE NULL,
    inventory_lot_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_grn_lot (lot_number),
    INDEX idx_gri_grn (goods_receipt_id),
    INDEX idx_gri_po_item (purchase_order_item_id),
    CONSTRAINT fk_gri_grn FOREIGN KEY (goods_receipt_id) REFERENCES goods_receipts(id) ON DELETE CASCADE,
    CONSTRAINT fk_gri_po_item FOREIGN KEY (purchase_order_item_id) REFERENCES purchase_order_items(id) ON DELETE SET NULL,
    CONSTRAINT fk_gri_product FOREIGN KEY (product_id) REFERENCES products(id),
    CONSTRAINT fk_gri_zone FOREIGN KEY (storage_zone_id) REFERENCES storage_zones(id),
    CONSTRAINT fk_gri_lot FOREIGN KEY (inventory_lot_id) REFERENCES inventory_lots(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO chart_of_accounts (account_code, account_name, account_type, status)
SELECT '2100', 'Output GST Payable', 'liability', 'active'
WHERE NOT EXISTS (SELECT 1 FROM chart_of_accounts WHERE account_code='2100');

INSERT INTO chart_of_accounts (account_code, account_name, account_type, status)
SELECT '1300', 'Inventory Clearing', 'asset', 'active'
WHERE NOT EXISTS (SELECT 1 FROM chart_of_accounts WHERE account_code='1300');
