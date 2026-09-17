-- MeatinOS Migration: Product fields, nullability and category linkage
-- Date: 2026-09-17

ALTER TABLE products MODIFY reorder_level DECIMAL(14,3) NULL DEFAULT 0.000;
ALTER TABLE products MODIFY standard_cost DECIMAL(14,3) NULL DEFAULT 0.000;
ALTER TABLE products MODIFY selling_price DECIMAL(14,2) NULL DEFAULT 0.00;

UPDATE products p
JOIN item_categories c ON c.category_type = p.category
SET p.category_id = c.id
WHERE p.category_id IS NULL;
