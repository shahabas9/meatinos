ALTER TABLE production_stage_measurements
    MODIFY COLUMN stage ENUM('receiving','hanging','stunning','slaughtering','scalding','defeathering','evisceration','chilling','grading','packing','storage') NOT NULL;

ALTER TABLE production_stage_measurements
    ADD COLUMN packed_bird_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER condemned_weight_kg,
    ADD COLUMN packed_weight_kg DECIMAL(14,3) NOT NULL DEFAULT 0 AFTER packed_bird_count,
    ADD COLUMN rejected_pack_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER packed_weight_kg,
    ADD COLUMN packaging_waste_kg DECIMAL(14,3) NOT NULL DEFAULT 0 AFTER rejected_pack_count;

ALTER TABLE shareholders
    ADD COLUMN designation VARCHAR(120) NULL AFTER name,
    ADD COLUMN date_of_birth DATE NULL AFTER designation,
    ADD COLUMN joined_on DATE NULL AFTER date_of_birth,
    ADD COLUMN phone_secondary VARCHAR(40) NULL AFTER phone,
    ADD COLUMN pan_number VARCHAR(20) NULL AFTER email,
    ADD COLUMN aadhaar_number VARCHAR(20) NULL AFTER pan_number,
    ADD COLUMN address VARCHAR(1000) NULL AFTER aadhaar_number;

ALTER TABLE sales_orders
    ADD COLUMN received_by_employee_id BIGINT UNSIGNED NULL AFTER rate_card_id;

ALTER TABLE sales_orders
    ADD INDEX idx_sales_order_receiver (received_by_employee_id,order_date);

ALTER TABLE sales_orders
    ADD CONSTRAINT fk_order_receiver FOREIGN KEY (received_by_employee_id) REFERENCES employees(id) ON DELETE SET NULL;
