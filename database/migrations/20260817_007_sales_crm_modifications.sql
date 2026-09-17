CREATE TABLE IF NOT EXISTS rate_cards (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rate_card_number VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    customer_type VARCHAR(40) NULL,
    currency CHAR(3) NOT NULL DEFAULT 'INR',
    valid_from DATE NOT NULL,
    valid_to DATE NULL,
    default_margin_percent DECIMAL(7,3) NOT NULL DEFAULT 0,
    status ENUM('draft','active','inactive','expired') NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_rate_card_dates (status,valid_from,valid_to),
    CONSTRAINT fk_rate_card_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE customers ADD COLUMN phone_secondary VARCHAR(30) NULL AFTER phone;
ALTER TABLE customers ADD COLUMN gst_number VARCHAR(40) NULL AFTER tax_number;
ALTER TABLE customers ADD COLUMN fssai_number VARCHAR(40) NULL AFTER gst_number;
ALTER TABLE customers ADD COLUMN aadhaar_number VARCHAR(20) NULL AFTER fssai_number;
ALTER TABLE customers ADD COLUMN pan_number VARCHAR(20) NULL AFTER aadhaar_number;
ALTER TABLE customers ADD COLUMN criticality ENUM('normal','watch','critical') NOT NULL DEFAULT 'normal' AFTER payment_terms_days;
ALTER TABLE customers ADD COLUMN assigned_salesman_id BIGINT UNSIGNED NULL AFTER criticality;
ALTER TABLE customers ADD COLUMN default_rate_card_id BIGINT UNSIGNED NULL AFTER assigned_salesman_id;
ALTER TABLE customers ADD INDEX idx_customer_salesman (assigned_salesman_id);
ALTER TABLE customers ADD INDEX idx_customer_criticality (criticality,status);
ALTER TABLE customers ADD CONSTRAINT fk_customer_salesman FOREIGN KEY (assigned_salesman_id) REFERENCES employees(id) ON DELETE SET NULL;
ALTER TABLE customers ADD CONSTRAINT fk_customer_rate_card FOREIGN KEY (default_rate_card_id) REFERENCES rate_cards(id) ON DELETE SET NULL;

ALTER TABLE products ADD COLUMN hsn_code VARCHAR(30) NULL AFTER barcode;
ALTER TABLE products ADD COLUMN live_bird_cost_factor DECIMAL(10,4) NOT NULL DEFAULT 1 AFTER standard_cost;
ALTER TABLE products ADD COLUMN default_margin_percent DECIMAL(7,3) NOT NULL DEFAULT 0 AFTER live_bird_cost_factor;

ALTER TABLE bird_receipts ADD COLUMN received_quantity DECIMAL(14,3) NOT NULL DEFAULT 0 AFTER bird_count;
ALTER TABLE bird_receipts ADD COLUMN unit_purchase_cost DECIMAL(14,3) NOT NULL DEFAULT 0 AFTER sample_avg_weight_kg;
ALTER TABLE bird_receipts ADD COLUMN total_value DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER unit_purchase_cost;
UPDATE bird_receipts SET received_quantity=net_weight_kg WHERE received_quantity=0;

ALTER TABLE sales_orders ADD COLUMN sales_person_id BIGINT UNSIGNED NULL AFTER customer_id;
ALTER TABLE sales_orders ADD COLUMN rate_card_id BIGINT UNSIGNED NULL AFTER sales_person_id;
ALTER TABLE sales_orders ADD COLUMN delivery_location VARCHAR(255) NULL AFTER delivery_date;
ALTER TABLE sales_orders ADD COLUMN delivery_time TIME NULL AFTER delivery_location;
ALTER TABLE sales_orders ADD INDEX idx_sales_order_person (sales_person_id,order_date);
ALTER TABLE sales_orders ADD CONSTRAINT fk_order_sales_person FOREIGN KEY (sales_person_id) REFERENCES employees(id) ON DELETE SET NULL;
ALTER TABLE sales_orders ADD CONSTRAINT fk_order_rate_card FOREIGN KEY (rate_card_id) REFERENCES rate_cards(id) ON DELETE SET NULL;
UPDATE sales_orders SET delivery_location=LEFT(delivery_address,255) WHERE delivery_location IS NULL;

ALTER TABLE dispatches ADD COLUMN delivery_temperature_c DECIMAL(6,2) NULL AFTER temperature_c;
ALTER TABLE dispatches ADD COLUMN delivery_temperature_recorded_at DATETIME NULL AFTER delivery_temperature_c;

ALTER TABLE sales_returns ADD COLUMN quantity_received DECIMAL(14,3) NOT NULL DEFAULT 0 AFTER return_date;
ALTER TABLE sales_returns ADD COLUMN credit_amount DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER credit_note_number;
UPDATE sales_returns sr SET quantity_received=(SELECT COALESCE(SUM(sri.quantity),0) FROM sales_return_items sri WHERE sri.sales_return_id=sr.id) WHERE quantity_received=0;

ALTER TABLE shareholders ADD COLUMN shareholder_type ENUM('director','partner') NOT NULL DEFAULT 'partner' AFTER name;
ALTER TABLE shareholders ADD COLUMN director_id BIGINT UNSIGNED NULL AFTER shareholder_type;
ALTER TABLE shareholders ADD COLUMN share_value DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER ownership_percent;
ALTER TABLE shareholders ADD COLUMN share_amount DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER share_value;
ALTER TABLE shareholders ADD COLUMN received_share DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER share_amount;
ALTER TABLE shareholders ADD COLUMN pending_share_amount DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER received_share;
ALTER TABLE shareholders ADD INDEX idx_shareholder_director (director_id,shareholder_type);
ALTER TABLE shareholders ADD CONSTRAINT fk_shareholder_director FOREIGN KEY (director_id) REFERENCES shareholders(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS sales_quotation_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sales_quotation_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    material_description VARCHAR(255) NOT NULL,
    hsn_code VARCHAR(30) NULL,
    quantity DECIMAL(14,3) NOT NULL,
    unit_price DECIMAL(14,2) NOT NULL,
    discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    line_total DECIMAL(14,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_quote_items_quote (sales_quotation_id),
    CONSTRAINT fk_quote_item_quote FOREIGN KEY (sales_quotation_id) REFERENCES sales_quotations(id) ON DELETE CASCADE,
    CONSTRAINT fk_quote_item_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_card_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rate_card_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    hsn_code VARCHAR(30) NULL,
    live_bird_cost_factor DECIMAL(10,4) NOT NULL DEFAULT 1,
    margin_percent DECIMAL(7,3) NOT NULL DEFAULT 0,
    fixed_margin DECIMAL(14,3) NOT NULL DEFAULT 0,
    calculated_price DECIMAL(14,2) NOT NULL DEFAULT 0,
    unit_price DECIMAL(14,2) NOT NULL,
    minimum_quantity DECIMAL(14,3) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rate_card_product (rate_card_id,product_id),
    CONSTRAINT fk_rate_item_card FOREIGN KEY (rate_card_id) REFERENCES rate_cards(id) ON DELETE CASCADE,
    CONSTRAINT fk_rate_item_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_bank_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    bank_name VARCHAR(160) NOT NULL,
    account_holder VARCHAR(160) NOT NULL,
    masked_account VARCHAR(60) NOT NULL,
    provider_token_reference VARCHAR(190) NULL,
    currency CHAR(3) NOT NULL DEFAULT 'INR',
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('pending','active','blocked','inactive') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customer_bank_customer (customer_id,status),
    CONSTRAINT fk_customer_bank_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS market_prices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(60) NOT NULL DEFAULT 'Local Market',
    product_id BIGINT UNSIGNED NOT NULL,
    price_date DATE NOT NULL,
    unit_price DECIMAL(14,2) NOT NULL,
    location VARCHAR(160) NULL,
    reference_url VARCHAR(500) NULL,
    notes VARCHAR(1000) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_market_price (source,product_id,price_date,location),
    INDEX idx_market_price_latest (source,price_date),
    CONSTRAINT fk_market_price_product FOREIGN KEY (product_id) REFERENCES products(id),
    CONSTRAINT fk_market_price_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_monthly_balances (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    month_start DATE NOT NULL,
    opening_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
    invoice_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    payment_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    credit_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    closing_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
    generated_at DATETIME NOT NULL,
    UNIQUE KEY uq_customer_month (customer_id,month_start),
    CONSTRAINT fk_monthly_balance_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_schedules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    schedule_number VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(180) NOT NULL,
    report_type VARCHAR(60) NOT NULL,
    frequency ENUM('daily','weekly','monthly') NOT NULL,
    next_run_at DATETIME NOT NULL,
    email_recipients VARCHAR(1000) NULL,
    status ENUM('active','paused','inactive') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_report_schedule_due (status,next_run_at),
    CONSTRAINT fk_report_schedule_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_schedule_id BIGINT UNSIGNED NOT NULL,
    started_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    row_count INT UNSIGNED NOT NULL DEFAULT 0,
    file_path VARCHAR(500) NULL,
    status ENUM('running','completed','failed') NOT NULL DEFAULT 'running',
    error_message VARCHAR(1000) NULL,
    INDEX idx_report_run_schedule (report_schedule_id,started_at),
    CONSTRAINT fk_report_run_schedule FOREIGN KEY (report_schedule_id) REFERENCES report_schedules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
