ALTER TABLE bird_receipts ADD COLUMN IF NOT EXISTS plant_id BIGINT UNSIGNED NULL AFTER batch_number;
ALTER TABLE bird_receipts ADD COLUMN IF NOT EXISTS farm_id BIGINT UNSIGNED NULL AFTER supplier_id;
ALTER TABLE bird_receipts ADD COLUMN IF NOT EXISTS vehicle_id BIGINT UNSIGNED NULL AFTER vehicle_number;
ALTER TABLE bird_receipts ADD COLUMN IF NOT EXISTS driver_id BIGINT UNSIGNED NULL AFTER vehicle_id;
ALTER TABLE bird_receipts ADD COLUMN IF NOT EXISTS crate_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER driver_id;
ALTER TABLE bird_receipts ADD COLUMN IF NOT EXISTS tare_weight_kg DECIMAL(14,3) NOT NULL DEFAULT 0 AFTER gross_weight_kg;
ALTER TABLE bird_receipts ADD COLUMN IF NOT EXISTS unloading_time DATETIME NULL AFTER received_at;
ALTER TABLE bird_receipts ADD COLUMN IF NOT EXISTS received_quantity DECIMAL(14,3) NOT NULL DEFAULT 0 AFTER bird_count;
ALTER TABLE bird_receipts ADD COLUMN IF NOT EXISTS unit_purchase_cost DECIMAL(14,3) NOT NULL DEFAULT 0 AFTER sample_avg_weight_kg;
ALTER TABLE bird_receipts ADD COLUMN IF NOT EXISTS total_value DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER unit_purchase_cost;

UPDATE bird_receipts SET received_quantity = net_weight_kg WHERE received_quantity = 0 OR received_quantity IS NULL;

ALTER TABLE bird_receipts ADD INDEX idx_bird_plant (plant_id);
ALTER TABLE bird_receipts ADD INDEX idx_bird_farm (farm_id);

ALTER TABLE bird_receipts ADD CONSTRAINT fk_bird_plant FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE SET NULL;
ALTER TABLE bird_receipts ADD CONSTRAINT fk_bird_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE SET NULL;
ALTER TABLE bird_receipts ADD CONSTRAINT fk_bird_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL;
ALTER TABLE bird_receipts ADD CONSTRAINT fk_bird_driver FOREIGN KEY (driver_id) REFERENCES employees(id) ON DELETE SET NULL;
