SET NAMES utf8mb4;
SET time_zone = '+05:30';

CREATE TABLE IF NOT EXISTS schema_migrations (
    migration VARCHAR(190) PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NULL,
    customer_id BIGINT UNSIGNED NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active','inactive','locked') NOT NULL DEFAULT 'active',
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    last_login_at DATETIME NULL,
    locked_until DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role_id),
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    successful TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_window (email, ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    event VARCHAR(80) NOT NULL,
    module VARCHAR(80) NOT NULL,
    record_id BIGINT UNSIGNED NULL,
    description VARCHAR(255) NOT NULL,
    old_values LONGTEXT NULL,
    new_values LONGTEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_record (module, record_id),
    INDEX idx_audit_user (user_id),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value LONGTEXT NULL,
    updated_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS suppliers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    supplier_type ENUM('live_bird','packaging','consumable','service') NOT NULL,
    contact_person VARCHAR(120) NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(190) NULL,
    address TEXT NULL,
    approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    outstanding_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_suppliers_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    customer_type ENUM('retail','hotel','wholesale','distributor') NOT NULL,
    contact_person VARCHAR(120) NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(190) NULL,
    tax_number VARCHAR(40) NULL,
    address TEXT NULL,
    credit_limit DECIMAL(14,2) NOT NULL DEFAULT 0,
    payment_terms_days INT UNSIGNED NOT NULL DEFAULT 30,
    outstanding_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customers_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    category ENUM('finished_good','raw_material','packaging','spare_part','by_product') NOT NULL,
    unit ENUM('kg','pcs','carton','roll','litre') NOT NULL,
    barcode VARCHAR(100) NULL UNIQUE,
    shelf_life_days INT UNSIGNED NULL,
    reorder_level DECIMAL(14,3) NOT NULL DEFAULT 0,
    standard_cost DECIMAL(14,3) NOT NULL DEFAULT 0,
    selling_price DECIMAL(14,2) NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_products_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS storage_zones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    zone_type ENUM('ambient','chiller','blaster','cold_storage','anti_room','dispatch') NOT NULL,
    capacity_kg DECIMAL(14,2) NOT NULL DEFAULT 0,
    min_temperature_c DECIMAL(6,2) NULL,
    max_temperature_c DECIMAL(6,2) NULL,
    current_temperature_c DECIMAL(6,2) NULL,
    status ENUM('active','inactive','maintenance') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    po_number VARCHAR(40) NOT NULL UNIQUE,
    supplier_id BIGINT UNSIGNED NOT NULL,
    order_date DATE NOT NULL,
    expected_date DATE NULL,
    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    status ENUM('draft','pending','approved','partial','completed','cancelled') NOT NULL DEFAULT 'draft',
    approved_by BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_po_supplier (supplier_id),
    INDEX idx_po_date (order_date),
    CONSTRAINT fk_po_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    CONSTRAINT fk_po_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_po_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(14,3) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    unit_price DECIMAL(14,3) NOT NULL,
    total_price DECIMAL(14,2) NOT NULL,
    CONSTRAINT fk_poi_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_poi_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bird_receipts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_number VARCHAR(50) NOT NULL UNIQUE,
    supplier_id BIGINT UNSIGNED NOT NULL,
    purchase_order_id BIGINT UNSIGNED NULL,
    farm_name VARCHAR(150) NOT NULL,
    vehicle_number VARCHAR(40) NOT NULL,
    received_at DATETIME NOT NULL,
    bird_count INT UNSIGNED NOT NULL,
    mortality_count INT UNSIGNED NOT NULL DEFAULT 0,
    gross_weight_kg DECIMAL(14,3) NOT NULL,
    net_weight_kg DECIMAL(14,3) NOT NULL,
    sample_avg_weight_kg DECIMAL(8,3) NULL,
    quality_observation TEXT NULL,
    vet_certificate VARCHAR(100) NULL,
    vet_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    status ENUM('quarantine','accepted','rejected','released') NOT NULL DEFAULT 'quarantine',
    received_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_receipts_date (received_at),
    INDEX idx_receipts_supplier (supplier_id),
    CONSTRAINT fk_receipt_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    CONSTRAINT fk_receipt_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE SET NULL,
    CONSTRAINT fk_receipt_user FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS production_batches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_number VARCHAR(50) NOT NULL UNIQUE,
    bird_receipt_id BIGINT UNSIGNED NOT NULL,
    production_date DATE NOT NULL,
    shift ENUM('A','B','C') NOT NULL,
    birds_input INT UNSIGNED NOT NULL,
    input_weight_kg DECIMAL(14,3) NOT NULL,
    output_weight_kg DECIMAL(14,3) NOT NULL DEFAULT 0,
    rejected_weight_kg DECIMAL(14,3) NOT NULL DEFAULT 0,
    yield_percent DECIMAL(6,2) NOT NULL DEFAULT 0,
    stage ENUM('receiving','slaughtering','evisceration','quality_control','store_keeping','dispatch_ready') NOT NULL DEFAULT 'receiving',
    status ENUM('scheduled','in_progress','completed','hold','cancelled') NOT NULL DEFAULT 'scheduled',
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_batches_date (production_date),
    INDEX idx_batches_receipt (bird_receipt_id),
    CONSTRAINT fk_batch_receipt FOREIGN KEY (bird_receipt_id) REFERENCES bird_receipts(id),
    CONSTRAINT fk_batch_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS production_stage_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    production_batch_id BIGINT UNSIGNED NOT NULL,
    stage VARCHAR(60) NOT NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    quantity_in DECIMAL(14,3) NULL,
    quantity_out DECIMAL(14,3) NULL,
    temperature_c DECIMAL(6,2) NULL,
    machine_reference VARCHAR(100) NULL,
    operator_id BIGINT UNSIGNED NULL,
    quality_status ENUM('pending','passed','failed','hold') NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_stage_batch (production_batch_id, stage),
    CONSTRAINT fk_stage_batch FOREIGN KEY (production_batch_id) REFERENCES production_batches(id) ON DELETE CASCADE,
    CONSTRAINT fk_stage_operator FOREIGN KEY (operator_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quality_checks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    check_number VARCHAR(50) NOT NULL UNIQUE,
    production_batch_id BIGINT UNSIGNED NOT NULL,
    checkpoint ENUM('receiving','post_slaughter','evisceration','grading','storage','dispatch') NOT NULL,
    checked_at DATETIME NOT NULL,
    grade ENUM('A','B','C','rejected') NOT NULL,
    sample_size INT UNSIGNED NOT NULL,
    accepted_qty INT UNSIGNED NOT NULL DEFAULT 0,
    rejected_qty INT UNSIGNED NOT NULL DEFAULT 0,
    weight_kg DECIMAL(14,3) NULL,
    temperature_c DECIMAL(6,2) NULL,
    appearance ENUM('pass','fail') NULL,
    smell ENUM('pass','fail') NULL,
    physical_damage ENUM('none','minor','major') NULL,
    status ENUM('passed','conditional','failed') NOT NULL,
    corrective_action TEXT NULL,
    checked_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_quality_batch (production_batch_id),
    CONSTRAINT fk_quality_batch FOREIGN KEY (production_batch_id) REFERENCES production_batches(id),
    CONSTRAINT fk_quality_user FOREIGN KEY (checked_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_lots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lot_number VARCHAR(60) NOT NULL UNIQUE,
    product_id BIGINT UNSIGNED NOT NULL,
    production_batch_id BIGINT UNSIGNED NULL,
    storage_zone_id BIGINT UNSIGNED NOT NULL,
    received_date DATE NOT NULL,
    expiry_date DATE NULL,
    quantity DECIMAL(14,3) NOT NULL,
    available_quantity DECIMAL(14,3) NOT NULL,
    unit_cost DECIMAL(14,3) NOT NULL DEFAULT 0,
    barcode VARCHAR(100) NULL UNIQUE,
    rfid_tag VARCHAR(100) NULL UNIQUE,
    temperature_c DECIMAL(6,2) NULL,
    status ENUM('available','allocated','quarantine','expired','depleted') NOT NULL DEFAULT 'available',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_lots_product (product_id),
    INDEX idx_lots_expiry (expiry_date),
    INDEX idx_lots_batch (production_batch_id),
    CONSTRAINT fk_lot_product FOREIGN KEY (product_id) REFERENCES products(id),
    CONSTRAINT fk_lot_batch FOREIGN KEY (production_batch_id) REFERENCES production_batches(id) ON DELETE SET NULL,
    CONSTRAINT fk_lot_zone FOREIGN KEY (storage_zone_id) REFERENCES storage_zones(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inventory_lot_id BIGINT UNSIGNED NOT NULL,
    movement_type ENUM('receipt','production_in','transfer','allocation','dispatch','return','adjustment','waste') NOT NULL,
    quantity DECIMAL(14,3) NOT NULL,
    from_zone_id BIGINT UNSIGNED NULL,
    to_zone_id BIGINT UNSIGNED NULL,
    reference_type VARCHAR(60) NULL,
    reference_id BIGINT UNSIGNED NULL,
    reason VARCHAR(255) NULL,
    moved_by BIGINT UNSIGNED NULL,
    moved_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_stock_lot_date (inventory_lot_id, moved_at),
    CONSTRAINT fk_stock_lot FOREIGN KEY (inventory_lot_id) REFERENCES inventory_lots(id),
    CONSTRAINT fk_stock_from FOREIGN KEY (from_zone_id) REFERENCES storage_zones(id) ON DELETE SET NULL,
    CONSTRAINT fk_stock_to FOREIGN KEY (to_zone_id) REFERENCES storage_zones(id) ON DELETE SET NULL,
    CONSTRAINT fk_stock_user FOREIGN KEY (moved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    customer_id BIGINT UNSIGNED NOT NULL,
    order_date DATE NOT NULL,
    delivery_date DATE NOT NULL,
    customer_reference VARCHAR(80) NULL,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    payment_status ENUM('unpaid','partial','paid','overdue') NOT NULL DEFAULT 'unpaid',
    status ENUM('draft','pending','approved','partial','completed','cancelled') NOT NULL DEFAULT 'draft',
    delivery_address TEXT NOT NULL,
    notes TEXT NULL,
    approved_by BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_orders_customer (customer_id),
    INDEX idx_orders_date (order_date),
    CONSTRAINT fk_order_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
    CONSTRAINT fk_order_approved FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_order_created FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sales_order_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    inventory_lot_id BIGINT UNSIGNED NULL,
    quantity DECIMAL(14,3) NOT NULL,
    unit_price DECIMAL(14,2) NOT NULL,
    discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    line_total DECIMAL(14,2) NOT NULL,
    CONSTRAINT fk_soi_order FOREIGN KEY (sales_order_id) REFERENCES sales_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_soi_product FOREIGN KEY (product_id) REFERENCES products(id),
    CONSTRAINT fk_soi_lot FOREIGN KEY (inventory_lot_id) REFERENCES inventory_lots(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    sales_order_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    subtotal DECIMAL(14,2) NOT NULL,
    tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(14,2) NOT NULL,
    paid_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    balance_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    status ENUM('draft','issued','partial','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_invoice_customer (customer_id),
    INDEX idx_invoice_due (due_date, status),
    CONSTRAINT fk_invoice_order FOREIGN KEY (sales_order_id) REFERENCES sales_orders(id),
    CONSTRAINT fk_invoice_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
    CONSTRAINT fk_invoice_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_number VARCHAR(50) NOT NULL UNIQUE,
    invoice_id BIGINT UNSIGNED NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    payment_method ENUM('bank_transfer','card','cash','cheque') NOT NULL,
    reference_number VARCHAR(100) NULL,
    status ENUM('pending','cleared','failed','reversed') NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    received_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_payment_customer (customer_id),
    CONSTRAINT fk_payment_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
    CONSTRAINT fk_payment_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
    CONSTRAINT fk_payment_user FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employees (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_number VARCHAR(40) NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    department VARCHAR(100) NOT NULL,
    job_title VARCHAR(120) NOT NULL,
    manager_id BIGINT UNSIGNED NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(190) NULL,
    join_date DATE NOT NULL,
    shift_code ENUM('A','B','C','office') NULL,
    basic_salary DECIMAL(14,2) NOT NULL DEFAULT 0,
    overtime_rate DECIMAL(10,2) NOT NULL DEFAULT 0,
    biometric_reference VARCHAR(100) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_employee_manager FOREIGN KEY (manager_id) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    attendance_date DATE NOT NULL,
    check_in DATETIME NULL,
    check_out DATETIME NULL,
    regular_hours DECIMAL(5,2) NOT NULL DEFAULT 0,
    overtime_hours DECIMAL(5,2) NOT NULL DEFAULT 0,
    source ENUM('face','fingerprint','mobile','manual') NOT NULL,
    status ENUM('present','absent','leave','late','half_day') NOT NULL,
    remarks TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_attendance_employee_date (employee_id, attendance_date),
    INDEX idx_attendance_date (attendance_date, status),
    CONSTRAINT fk_attendance_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payroll_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    run_number VARCHAR(50) NOT NULL UNIQUE,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    pay_date DATE NOT NULL,
    gross_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    deductions_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    net_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    status ENUM('draft','approved','paid','cancelled') NOT NULL DEFAULT 'draft',
    approved_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payroll_period (period_start, period_end),
    CONSTRAINT fk_payroll_user FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payroll_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payroll_run_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    basic_amount DECIMAL(14,2) NOT NULL,
    overtime_hours DECIMAL(8,2) NOT NULL DEFAULT 0,
    overtime_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    allowances DECIMAL(14,2) NOT NULL DEFAULT 0,
    deductions DECIMAL(14,2) NOT NULL DEFAULT 0,
    net_amount DECIMAL(14,2) NOT NULL,
    CONSTRAINT fk_payroll_item_run FOREIGN KEY (payroll_run_id) REFERENCES payroll_runs(id) ON DELETE CASCADE,
    CONSTRAINT fk_payroll_item_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vehicles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    registration_number VARCHAR(40) NOT NULL UNIQUE,
    vehicle_type ENUM('reefer','pickup','livestock','service') NOT NULL,
    make_model VARCHAR(100) NULL,
    capacity_kg DECIMAL(14,2) NULL,
    gps_device_id VARCHAR(100) NULL,
    last_service_date DATE NULL,
    next_service_date DATE NULL,
    temperature_c DECIMAL(6,2) NULL,
    status ENUM('active','maintenance','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dispatches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dispatch_number VARCHAR(50) NOT NULL UNIQUE,
    sales_order_id BIGINT UNSIGNED NOT NULL,
    vehicle_id BIGINT UNSIGNED NOT NULL,
    driver_id BIGINT UNSIGNED NOT NULL,
    route_name VARCHAR(150) NOT NULL,
    planned_departure DATETIME NOT NULL,
    actual_departure DATETIME NULL,
    delivered_at DATETIME NULL,
    temperature_c DECIMAL(6,2) NULL,
    gps_latitude DECIMAL(10,7) NULL,
    gps_longitude DECIMAL(10,7) NULL,
    status ENUM('scheduled','loading','in_transit','delivered','delayed','cancelled') NOT NULL DEFAULT 'scheduled',
    pod_reference VARCHAR(100) NULL,
    delivery_notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_dispatch_status (status, planned_departure),
    CONSTRAINT fk_dispatch_order FOREIGN KEY (sales_order_id) REFERENCES sales_orders(id),
    CONSTRAINT fk_dispatch_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
    CONSTRAINT fk_dispatch_driver FOREIGN KEY (driver_id) REFERENCES employees(id),
    CONSTRAINT fk_dispatch_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(100) NOT NULL,
    location VARCHAR(120) NOT NULL,
    serial_number VARCHAR(100) NULL,
    criticality ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
    installation_date DATE NULL,
    last_maintenance_date DATE NULL,
    next_maintenance_date DATE NULL,
    runtime_hours DECIMAL(14,2) NOT NULL DEFAULT 0,
    status ENUM('active','maintenance','breakdown','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS maintenance_tickets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_number VARCHAR(50) NOT NULL UNIQUE,
    asset_id BIGINT UNSIGNED NOT NULL,
    ticket_type ENUM('preventive','corrective','breakdown','inspection') NOT NULL,
    priority ENUM('low','medium','high','critical') NOT NULL,
    issue TEXT NOT NULL,
    reported_at DATETIME NOT NULL,
    assigned_to VARCHAR(120) NULL,
    scheduled_date DATE NULL,
    completed_at DATETIME NULL,
    downtime_minutes INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('open','assigned','in_progress','completed','cancelled') NOT NULL DEFAULT 'open',
    resolution TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_maintenance_due (status, scheduled_date),
    CONSTRAINT fk_ticket_asset FOREIGN KEY (asset_id) REFERENCES assets(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hygiene_checks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    check_number VARCHAR(50) NOT NULL UNIQUE,
    area VARCHAR(120) NOT NULL,
    check_type ENUM('pre_operation','operational','post_operation','ppe','drain','jewelry') NOT NULL,
    checked_at DATETIME NOT NULL,
    score_percent DECIMAL(6,2) NOT NULL,
    floor_status ENUM('dry','wet','clean','contaminated') NULL,
    drain_status ENUM('clear','blocked','attention') NULL,
    ppe_status ENUM('compliant','shortage','non_compliant') NULL,
    status ENUM('passed','conditional','failed') NOT NULL,
    corrective_action TEXT NULL,
    checked_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_hygiene_user FOREIGN KEY (checked_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sensors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sensor_code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    sensor_type ENUM('temperature','water_level','door','power','voltage','runtime','gps','weight') NOT NULL,
    location VARCHAR(120) NOT NULL,
    current_value DECIMAL(14,3) NULL,
    unit VARCHAR(20) NULL,
    min_threshold DECIMAL(14,3) NULL,
    max_threshold DECIMAL(14,3) NULL,
    last_seen_at DATETIME NULL,
    status ENUM('online','warning','critical','offline') NOT NULL DEFAULT 'online',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sensor_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sensor_readings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sensor_id BIGINT UNSIGNED NOT NULL,
    reading_value DECIMAL(14,3) NOT NULL,
    recorded_at DATETIME NOT NULL,
    quality ENUM('good','suspect','bad') NOT NULL DEFAULT 'good',
    INDEX idx_sensor_reading (sensor_id, recorded_at),
    CONSTRAINT fk_reading_sensor FOREIGN KEY (sensor_id) REFERENCES sensors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alerts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    severity ENUM('info','warning','critical') NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    source_type VARCHAR(60) NOT NULL,
    source_reference VARCHAR(100) NULL,
    triggered_at DATETIME NOT NULL,
    status ENUM('open','acknowledged','resolved') NOT NULL DEFAULT 'open',
    acknowledged_by BIGINT UNSIGNED NULL,
    acknowledged_at DATETIME NULL,
    resolved_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_alerts_open (status, severity, triggered_at),
    CONSTRAINT fk_alert_user FOREIGN KEY (acknowledged_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chart_of_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_code VARCHAR(30) NOT NULL UNIQUE,
    account_name VARCHAR(150) NOT NULL,
    account_type ENUM('asset','liability','equity','revenue','expense') NOT NULL,
    parent_id BIGINT UNSIGNED NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    CONSTRAINT fk_account_parent FOREIGN KEY (parent_id) REFERENCES chart_of_accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS journal_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_number VARCHAR(50) NOT NULL UNIQUE,
    entry_date DATE NOT NULL,
    reference_type VARCHAR(60) NULL,
    reference_id BIGINT UNSIGNED NULL,
    description VARCHAR(255) NOT NULL,
    status ENUM('draft','posted','reversed') NOT NULL DEFAULT 'draft',
    posted_by BIGINT UNSIGNED NULL,
    posted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_journal_user FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS journal_lines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    journal_entry_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    debit DECIMAL(14,2) NOT NULL DEFAULT 0,
    credit DECIMAL(14,2) NOT NULL DEFAULT 0,
    description VARCHAR(255) NULL,
    CONSTRAINT fk_line_entry FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
    CONSTRAINT fk_line_account FOREIGN KEY (account_id) REFERENCES chart_of_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_complaints (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    complaint_number VARCHAR(50) NOT NULL UNIQUE,
    customer_id BIGINT UNSIGNED NOT NULL,
    sales_order_id BIGINT UNSIGNED NULL,
    complaint_date DATE NOT NULL,
    category VARCHAR(80) NOT NULL,
    details TEXT NOT NULL,
    root_cause TEXT NULL,
    corrective_action TEXT NULL,
    status ENUM('open','investigating','resolved','closed') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_complaint_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
    CONSTRAINT fk_complaint_order FOREIGN KEY (sales_order_id) REFERENCES sales_orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS goods_receipts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    grn_number VARCHAR(50) NOT NULL UNIQUE,
    purchase_order_id BIGINT UNSIGNED NULL,
    supplier_id BIGINT UNSIGNED NOT NULL,
    received_date DATE NOT NULL,
    supplier_document VARCHAR(100) NULL,
    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    quality_status ENUM('pending','passed','conditional','failed') NOT NULL DEFAULT 'pending',
    status ENUM('draft','received','quarantine','accepted','rejected') NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    received_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_grn_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE SET NULL,
    CONSTRAINT fk_grn_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    CONSTRAINT fk_grn_user FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS supplier_invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    supplier_document VARCHAR(100) NULL,
    supplier_id BIGINT UNSIGNED NOT NULL,
    purchase_order_id BIGINT UNSIGNED NULL,
    goods_receipt_id BIGINT UNSIGNED NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    subtotal DECIMAL(14,2) NOT NULL,
    tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(14,2) NOT NULL,
    paid_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    balance_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    status ENUM('draft','approved','partial','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    approved_by BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_supplier_invoice_due (due_date,status),
    INDEX idx_supplier_invoice_supplier (supplier_id),
    CONSTRAINT fk_si_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    CONSTRAINT fk_si_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE SET NULL,
    CONSTRAINT fk_si_grn FOREIGN KEY (goods_receipt_id) REFERENCES goods_receipts(id) ON DELETE SET NULL,
    CONSTRAINT fk_si_approved FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_si_created FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_number VARCHAR(50) NOT NULL UNIQUE,
    supplier_invoice_id BIGINT UNSIGNED NOT NULL,
    supplier_id BIGINT UNSIGNED NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    payment_method ENUM('bank_transfer','card','cash','cheque') NOT NULL,
    reference_number VARCHAR(100) NULL,
    status ENUM('pending','cleared','failed','reversed') NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    paid_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_supplier_payment_invoice (supplier_invoice_id),
    CONSTRAINT fk_sp_invoice FOREIGN KEY (supplier_invoice_id) REFERENCES supplier_invoices(id),
    CONSTRAINT fk_sp_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    CONSTRAINT fk_sp_user FOREIGN KEY (paid_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_quotations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quote_number VARCHAR(50) NOT NULL UNIQUE,
    customer_id BIGINT UNSIGNED NOT NULL,
    quote_date DATE NOT NULL,
    valid_until DATE NOT NULL,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    status ENUM('draft','sent','accepted','expired','rejected','converted') NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_quote_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
    CONSTRAINT fk_quote_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_targets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    target_code VARCHAR(50) NOT NULL UNIQUE,
    employee_id BIGINT UNSIGNED NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    target_amount DECIMAL(14,2) NOT NULL,
    achieved_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    status ENUM('active','achieved','missed','cancelled') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_target_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leave_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_number VARCHAR(50) NOT NULL UNIQUE,
    employee_id BIGINT UNSIGNED NOT NULL,
    leave_type ENUM('annual','sick','unpaid','emergency','maternity','other') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days DECIMAL(6,2) NOT NULL,
    reason TEXT NULL,
    status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    approved_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_leave_employee FOREIGN KEY (employee_id) REFERENCES employees(id),
    CONSTRAINT fk_leave_user FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fuel_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    log_number VARCHAR(50) NOT NULL UNIQUE,
    vehicle_id BIGINT UNSIGNED NOT NULL,
    driver_id BIGINT UNSIGNED NULL,
    fuel_date DATE NOT NULL,
    odometer_km DECIMAL(12,1) NOT NULL,
    litres DECIMAL(10,2) NOT NULL,
    total_cost DECIMAL(14,2) NOT NULL,
    receipt_reference VARCHAR(100) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_fuel_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
    CONSTRAINT fk_fuel_driver FOREIGN KEY (driver_id) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS compliance_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    record_number VARCHAR(50) NOT NULL UNIQUE,
    compliance_type ENUM('haccp','iso','halal','lab','capa','audit') NOT NULL,
    title VARCHAR(180) NOT NULL,
    reference_number VARCHAR(100) NULL,
    issue_date DATE NOT NULL,
    expiry_date DATE NULL,
    owner VARCHAR(120) NOT NULL,
    status ENUM('current','due','expired','open','closed') NOT NULL DEFAULT 'current',
    findings TEXT NULL,
    corrective_action TEXT NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_compliance_user FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
