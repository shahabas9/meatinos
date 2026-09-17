CREATE TABLE IF NOT EXISTS companies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(180) NOT NULL,
    legal_name VARCHAR(220) NULL,
    tax_number VARCHAR(60) NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'INR',
    timezone VARCHAR(60) NOT NULL DEFAULT 'Asia/Kolkata',
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    address TEXT NULL,
    gps_latitude DECIMAL(10,7) NULL,
    gps_longitude DECIMAL(10,7) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_plant_company FOREIGN KEY (company_id) REFERENCES companies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS departments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plant_id BIGINT UNSIGNED NULL,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(140) NOT NULL,
    manager_id BIGINT UNSIGNED NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_department_plant FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE SET NULL,
    CONSTRAINT fk_department_manager FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(140) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_section_department FOREIGN KEY (department_id) REFERENCES departments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS production_lines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plant_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(140) NOT NULL,
    capacity_per_hour DECIMAL(14,3) NOT NULL DEFAULT 0,
    status ENUM('active','maintenance','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_line_plant FOREIGN KEY (plant_id) REFERENCES plants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS farms (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    address TEXT NULL,
    gps_latitude DECIMAL(10,7) NULL,
    gps_longitude DECIMAL(10,7) NULL,
    veterinary_registration VARCHAR(100) NULL,
    status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_farm_supplier (supplier_id),
    CONSTRAINT fk_farm_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS item_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NULL,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(140) NOT NULL,
    category_type ENUM('finished_good','raw_material','packaging','spare_part','by_product','hygiene','tray') NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_item_category_parent FOREIGN KEY (parent_id) REFERENCES item_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS warehouses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plant_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(140) NOT NULL,
    warehouse_type ENUM('raw','packaging','finished','spares','hygiene','cold_storage') NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_warehouse_plant FOREIGN KEY (plant_id) REFERENCES plants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS warehouse_locations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    warehouse_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(140) NOT NULL,
    zone VARCHAR(80) NULL,
    rack VARCHAR(80) NULL,
    capacity_kg DECIMAL(14,3) NOT NULL DEFAULT 0,
    status ENUM('active','blocked','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_location_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS number_sequences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plant_id BIGINT UNSIGNED NULL,
    document_type VARCHAR(60) NOT NULL,
    prefix VARCHAR(30) NOT NULL,
    financial_year VARCHAR(20) NOT NULL,
    next_number BIGINT UNSIGNED NOT NULL DEFAULT 1,
    padding TINYINT UNSIGNED NOT NULL DEFAULT 6,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_number_sequence (plant_id,document_type,financial_year),
    CONSTRAINT fk_sequence_plant FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id,role_id),
    INDEX idx_user_roles_role (role_id),
    CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_permissions (
    user_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    effect ENUM('allow','deny') NOT NULL DEFAULT 'allow',
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id,permission_id),
    CONSTRAINT fk_user_permissions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS approval_workflows (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    module VARCHAR(80) NOT NULL,
    transaction_type VARCHAR(80) NULL,
    plant_id BIGINT UNSIGNED NULL,
    department_id BIGINT UNSIGNED NULL,
    min_amount DECIMAL(14,2) NULL,
    max_amount DECIMAL(14,2) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_approval_match (module,status,min_amount,max_amount),
    CONSTRAINT fk_aw_plant FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE SET NULL,
    CONSTRAINT fk_aw_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS approval_workflow_steps (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workflow_id BIGINT UNSIGNED NOT NULL,
    step_number SMALLINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    label VARCHAR(140) NOT NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_approval_step (workflow_id,step_number),
    CONSTRAINT fk_aws_workflow FOREIGN KEY (workflow_id) REFERENCES approval_workflows(id) ON DELETE CASCADE,
    CONSTRAINT fk_aws_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS approval_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workflow_id BIGINT UNSIGNED NOT NULL,
    module VARCHAR(80) NOT NULL,
    record_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    plant_id BIGINT UNSIGNED NULL,
    department_id BIGINT UNSIGNED NULL,
    current_step SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    requested_by BIGINT UNSIGNED NULL,
    requested_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_open_approval (module,record_id),
    INDEX idx_approval_queue (status,current_step,requested_at),
    CONSTRAINT fk_ar_workflow FOREIGN KEY (workflow_id) REFERENCES approval_workflows(id),
    CONSTRAINT fk_ar_plant FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE SET NULL,
    CONSTRAINT fk_ar_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    CONSTRAINT fk_ar_requester FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS approval_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    approval_request_id BIGINT UNSIGNED NOT NULL,
    workflow_step_id BIGINT UNSIGNED NULL,
    action ENUM('submitted','approved','rejected','cancelled') NOT NULL,
    acted_by BIGINT UNSIGNED NULL,
    comment VARCHAR(1000) NULL,
    acted_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_approval_history_request (approval_request_id,acted_at),
    CONSTRAINT fk_ah_request FOREIGN KEY (approval_request_id) REFERENCES approval_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_ah_step FOREIGN KEY (workflow_step_id) REFERENCES approval_workflow_steps(id) ON DELETE SET NULL,
    CONSTRAINT fk_ah_user FOREIGN KEY (acted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE products ADD COLUMN IF NOT EXISTS category_id BIGINT UNSIGNED NULL AFTER category;
ALTER TABLE products ADD COLUMN IF NOT EXISTS rfid_tag VARCHAR(100) NULL AFTER barcode;
ALTER TABLE products ADD COLUMN IF NOT EXISTS min_stock DECIMAL(14,3) NOT NULL DEFAULT 0 AFTER reorder_level;
ALTER TABLE products ADD COLUMN IF NOT EXISTS max_stock DECIMAL(14,3) NOT NULL DEFAULT 0 AFTER min_stock;
ALTER TABLE products ADD COLUMN IF NOT EXISTS lot_tracking TINYINT(1) NOT NULL DEFAULT 1 AFTER max_stock;
ALTER TABLE products ADD COLUMN IF NOT EXISTS expiry_tracking TINYINT(1) NOT NULL DEFAULT 1 AFTER lot_tracking;
ALTER TABLE products ADD CONSTRAINT fk_product_category_master FOREIGN KEY (category_id) REFERENCES item_categories(id) ON DELETE SET NULL;

ALTER TABLE purchase_orders ADD COLUMN IF NOT EXISTS plant_id BIGINT UNSIGNED NULL AFTER supplier_id;
ALTER TABLE purchase_orders ADD INDEX idx_po_plant (plant_id);
ALTER TABLE purchase_orders ADD CONSTRAINT fk_po_plant FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE SET NULL;

ALTER TABLE bird_receipts ADD COLUMN IF NOT EXISTS plant_id BIGINT UNSIGNED NULL AFTER batch_number;
ALTER TABLE bird_receipts ADD COLUMN IF NOT EXISTS farm_id BIGINT UNSIGNED NULL AFTER supplier_id;
ALTER TABLE bird_receipts ADD COLUMN IF NOT EXISTS vehicle_id BIGINT UNSIGNED NULL AFTER vehicle_number;
ALTER TABLE bird_receipts ADD COLUMN IF NOT EXISTS driver_id BIGINT UNSIGNED NULL AFTER vehicle_id;
ALTER TABLE bird_receipts ADD COLUMN IF NOT EXISTS crate_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER driver_id;
ALTER TABLE bird_receipts ADD COLUMN IF NOT EXISTS tare_weight_kg DECIMAL(14,3) NOT NULL DEFAULT 0 AFTER gross_weight_kg;
ALTER TABLE bird_receipts ADD COLUMN IF NOT EXISTS unloading_time DATETIME NULL AFTER received_at;
ALTER TABLE bird_receipts ADD INDEX idx_bird_plant (plant_id);
ALTER TABLE bird_receipts ADD INDEX idx_bird_farm (farm_id);
ALTER TABLE bird_receipts ADD CONSTRAINT fk_bird_plant FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE SET NULL;
ALTER TABLE bird_receipts ADD CONSTRAINT fk_bird_farm FOREIGN KEY (farm_id) REFERENCES farms(id) ON DELETE SET NULL;
ALTER TABLE bird_receipts ADD CONSTRAINT fk_bird_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL;
ALTER TABLE bird_receipts ADD CONSTRAINT fk_bird_driver FOREIGN KEY (driver_id) REFERENCES employees(id) ON DELETE SET NULL;

ALTER TABLE production_batches ADD COLUMN IF NOT EXISTS plant_id BIGINT UNSIGNED NULL AFTER bird_receipt_id;
ALTER TABLE production_batches ADD COLUMN IF NOT EXISTS production_line_id BIGINT UNSIGNED NULL AFTER plant_id;
ALTER TABLE production_batches ADD COLUMN IF NOT EXISTS planned_quantity DECIMAL(14,3) NOT NULL DEFAULT 0 AFTER shift;
ALTER TABLE production_batches ADD COLUMN IF NOT EXISTS supervisor_id BIGINT UNSIGNED NULL AFTER planned_quantity;
ALTER TABLE production_batches ADD COLUMN IF NOT EXISTS production_manager_id BIGINT UNSIGNED NULL AFTER supervisor_id;
ALTER TABLE production_batches ADD COLUMN IF NOT EXISTS started_at DATETIME NULL AFTER production_manager_id;
ALTER TABLE production_batches ADD COLUMN IF NOT EXISTS completed_at DATETIME NULL AFTER started_at;
ALTER TABLE production_batches ADD INDEX idx_pb_plant (plant_id);
ALTER TABLE production_batches ADD CONSTRAINT fk_pb_plant FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE SET NULL;
ALTER TABLE production_batches ADD CONSTRAINT fk_pb_line FOREIGN KEY (production_line_id) REFERENCES production_lines(id) ON DELETE SET NULL;
ALTER TABLE production_batches ADD CONSTRAINT fk_pb_supervisor FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE production_batches ADD CONSTRAINT fk_pb_manager FOREIGN KEY (production_manager_id) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE sales_orders ADD COLUMN IF NOT EXISTS plant_id BIGINT UNSIGNED NULL AFTER customer_id;
ALTER TABLE sales_orders ADD COLUMN IF NOT EXISTS delivery_slot VARCHAR(80) NULL AFTER delivery_date;
ALTER TABLE sales_orders ADD COLUMN IF NOT EXISTS gps_latitude DECIMAL(10,7) NULL AFTER delivery_address;
ALTER TABLE sales_orders ADD COLUMN IF NOT EXISTS gps_longitude DECIMAL(10,7) NULL AFTER gps_latitude;
ALTER TABLE sales_orders ADD COLUMN IF NOT EXISTS cutting_instructions TEXT NULL AFTER gps_longitude;
ALTER TABLE sales_orders ADD INDEX idx_so_plant (plant_id);
ALTER TABLE sales_orders ADD CONSTRAINT fk_so_plant FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE SET NULL;

ALTER TABLE employees ADD COLUMN IF NOT EXISTS plant_id BIGINT UNSIGNED NULL AFTER full_name;
ALTER TABLE employees ADD COLUMN IF NOT EXISTS department_id BIGINT UNSIGNED NULL AFTER department;
ALTER TABLE employees ADD COLUMN IF NOT EXISTS employee_category ENUM('corporate','plant_staff','plant_labour') NOT NULL DEFAULT 'plant_staff' AFTER department_id;
ALTER TABLE employees ADD INDEX idx_employee_plant (plant_id);
ALTER TABLE employees ADD CONSTRAINT fk_employee_plant FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE SET NULL;
ALTER TABLE employees ADD CONSTRAINT fk_employee_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL;

ALTER TABLE stock_movements MODIFY movement_type ENUM('receipt','production_in','production_consumption','packaging_consumption','transfer','allocation','dispatch','return','sales_return','purchase_return','stock_count','adjustment','waste','disposal') NOT NULL;

CREATE TABLE IF NOT EXISTS purchase_requisitions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_number VARCHAR(50) NOT NULL UNIQUE,
    plant_id BIGINT UNSIGNED NULL,
    department_id BIGINT UNSIGNED NULL,
    request_date DATE NOT NULL,
    required_date DATE NOT NULL,
    bird_type VARCHAR(80) NULL,
    expected_bird_count INT UNSIGNED NOT NULL DEFAULT 0,
    expected_weight_kg DECIMAL(14,3) NOT NULL DEFAULT 0,
    size_category VARCHAR(80) NULL,
    estimated_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    requested_by BIGINT UNSIGNED NULL,
    approved_by BIGINT UNSIGNED NULL,
    status ENUM('draft','submitted','approved','rejected','converted','cancelled') NOT NULL DEFAULT 'draft',
    remarks TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pr_status_date (status,required_date),
    CONSTRAINT fk_pr_plant FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE SET NULL,
    CONSTRAINT fk_pr_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    CONSTRAINT fk_pr_requester FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_pr_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS production_requirements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requirement_number VARCHAR(50) NOT NULL UNIQUE,
    sales_order_id BIGINT UNSIGNED NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    plant_id BIGINT UNSIGNED NULL,
    required_date DATE NOT NULL,
    required_quantity DECIMAL(14,3) NOT NULL,
    available_quantity DECIMAL(14,3) NOT NULL DEFAULT 0,
    shortage_quantity DECIMAL(14,3) NOT NULL,
    priority ENUM('normal','high','critical') NOT NULL DEFAULT 'normal',
    status ENUM('open','planned','in_progress','fulfilled','cancelled') NOT NULL DEFAULT 'open',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_open_requirement (sales_order_id,product_id,status),
    CONSTRAINT fk_requirement_order FOREIGN KEY (sales_order_id) REFERENCES sales_orders(id) ON DELETE SET NULL,
    CONSTRAINT fk_requirement_product FOREIGN KEY (product_id) REFERENCES products(id),
    CONSTRAINT fk_requirement_plant FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS production_outputs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    production_batch_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    storage_zone_id BIGINT UNSIGNED NOT NULL,
    lot_number VARCHAR(60) NOT NULL UNIQUE,
    grade VARCHAR(20) NOT NULL DEFAULT 'A',
    quantity DECIMAL(14,3) NOT NULL,
    unit_cost DECIMAL(14,3) NOT NULL DEFAULT 0,
    expiry_date DATE NULL,
    qc_status ENUM('released','hold','rejected') NOT NULL DEFAULT 'released',
    inventory_lot_id BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_output_batch (production_batch_id),
    CONSTRAINT fk_output_batch FOREIGN KEY (production_batch_id) REFERENCES production_batches(id) ON DELETE CASCADE,
    CONSTRAINT fk_output_product FOREIGN KEY (product_id) REFERENCES products(id),
    CONSTRAINT fk_output_zone FOREIGN KEY (storage_zone_id) REFERENCES storage_zones(id),
    CONSTRAINT fk_output_lot FOREIGN KEY (inventory_lot_id) REFERENCES inventory_lots(id) ON DELETE SET NULL,
    CONSTRAINT fk_output_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS packaging_specs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    finished_product_id BIGINT UNSIGNED NOT NULL,
    packaging_product_id BIGINT UNSIGNED NOT NULL,
    quantity_per_unit DECIMAL(14,6) NOT NULL,
    waste_percent DECIMAL(6,3) NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_packaging_spec (finished_product_id,packaging_product_id),
    CONSTRAINT fk_pack_spec_finished FOREIGN KEY (finished_product_id) REFERENCES products(id),
    CONSTRAINT fk_pack_spec_material FOREIGN KEY (packaging_product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quality_holds (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hold_number VARCHAR(50) NOT NULL UNIQUE,
    production_batch_id BIGINT UNSIGNED NULL,
    inventory_lot_id BIGINT UNSIGNED NULL,
    reason VARCHAR(1000) NOT NULL,
    held_by BIGINT UNSIGNED NULL,
    held_at DATETIME NOT NULL,
    disposition ENUM('pending','release','reprocess','disposal','reject') NOT NULL DEFAULT 'pending',
    status ENUM('open','released','disposed','closed') NOT NULL DEFAULT 'open',
    released_by BIGINT UNSIGNED NULL,
    released_at DATETIME NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_quality_hold_status (status,held_at),
    CONSTRAINT fk_qh_batch FOREIGN KEY (production_batch_id) REFERENCES production_batches(id) ON DELETE SET NULL,
    CONSTRAINT fk_qh_lot FOREIGN KEY (inventory_lot_id) REFERENCES inventory_lots(id) ON DELETE SET NULL,
    CONSTRAINT fk_qh_held_user FOREIGN KEY (held_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_qh_release_user FOREIGN KEY (released_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS yield_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    production_batch_id BIGINT UNSIGNED NOT NULL UNIQUE,
    carcass_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    carcass_weight_kg DECIMAL(14,3) NOT NULL DEFAULT 0,
    by_product_weight_kg DECIMAL(14,3) NOT NULL DEFAULT 0,
    waste_weight_kg DECIMAL(14,3) NOT NULL DEFAULT 0,
    finished_weight_kg DECIMAL(14,3) NOT NULL DEFAULT 0,
    deboned_weight_kg DECIMAL(14,3) NOT NULL DEFAULT 0,
    carcass_yield_percent DECIMAL(7,3) NOT NULL DEFAULT 0,
    saleable_yield_percent DECIMAL(7,3) NOT NULL DEFAULT 0,
    waste_percent DECIMAL(7,3) NOT NULL DEFAULT 0,
    expected_yield_percent DECIMAL(7,3) NOT NULL DEFAULT 0,
    variance_percent DECIMAL(7,3) NOT NULL DEFAULT 0,
    calculated_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_yield_batch FOREIGN KEY (production_batch_id) REFERENCES production_batches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS carcass_allotments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    allotment_number VARCHAR(50) NOT NULL UNIQUE,
    production_batch_id BIGINT UNSIGNED NOT NULL,
    grade VARCHAR(20) NOT NULL,
    product_id BIGINT UNSIGNED NULL,
    sales_order_id BIGINT UNSIGNED NULL,
    destination ENUM('finished_goods','deboning','cutting','packaging','customer_order','department','production_line') NOT NULL,
    quantity DECIMAL(14,3) NOT NULL,
    weight_kg DECIMAL(14,3) NOT NULL,
    allotted_by BIGINT UNSIGNED NULL,
    allotted_at DATETIME NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_allotment_batch (production_batch_id),
    CONSTRAINT fk_allotment_batch FOREIGN KEY (production_batch_id) REFERENCES production_batches(id),
    CONSTRAINT fk_allotment_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    CONSTRAINT fk_allotment_order FOREIGN KEY (sales_order_id) REFERENCES sales_orders(id) ON DELETE SET NULL,
    CONSTRAINT fk_allotment_user FOREIGN KEY (allotted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_order_allocations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sales_order_item_id BIGINT UNSIGNED NOT NULL,
    inventory_lot_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(14,3) NOT NULL,
    allocated_by BIGINT UNSIGNED NULL,
    allocated_at DATETIME NOT NULL,
    released_at DATETIME NULL,
    UNIQUE KEY uq_so_allocation (sales_order_item_id,inventory_lot_id),
    CONSTRAINT fk_soa_item FOREIGN KEY (sales_order_item_id) REFERENCES sales_order_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_soa_lot FOREIGN KEY (inventory_lot_id) REFERENCES inventory_lots(id),
    CONSTRAINT fk_soa_user FOREIGN KEY (allocated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_transfers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transfer_number VARCHAR(50) NOT NULL UNIQUE,
    inventory_lot_id BIGINT UNSIGNED NOT NULL,
    from_zone_id BIGINT UNSIGNED NOT NULL,
    to_zone_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(14,3) NOT NULL,
    requested_by BIGINT UNSIGNED NULL,
    approved_by BIGINT UNSIGNED NULL,
    completed_by BIGINT UNSIGNED NULL,
    status ENUM('draft','submitted','approved','completed','rejected','cancelled') NOT NULL DEFAULT 'draft',
    reason VARCHAR(1000) NULL,
    completed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_transfer_lot FOREIGN KEY (inventory_lot_id) REFERENCES inventory_lots(id),
    CONSTRAINT fk_transfer_from FOREIGN KEY (from_zone_id) REFERENCES storage_zones(id),
    CONSTRAINT fk_transfer_to FOREIGN KEY (to_zone_id) REFERENCES storage_zones(id),
    CONSTRAINT fk_transfer_requester FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_transfer_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_transfer_completer FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_counts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    count_number VARCHAR(50) NOT NULL UNIQUE,
    storage_zone_id BIGINT UNSIGNED NOT NULL,
    count_date DATE NOT NULL,
    status ENUM('draft','in_progress','submitted','approved','posted','cancelled') NOT NULL DEFAULT 'draft',
    counted_by BIGINT UNSIGNED NULL,
    approved_by BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_count_zone FOREIGN KEY (storage_zone_id) REFERENCES storage_zones(id),
    CONSTRAINT fk_count_user FOREIGN KEY (counted_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_count_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_count_lines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stock_count_id BIGINT UNSIGNED NOT NULL,
    inventory_lot_id BIGINT UNSIGNED NOT NULL,
    system_quantity DECIMAL(14,3) NOT NULL,
    counted_quantity DECIMAL(14,3) NOT NULL,
    variance_quantity DECIMAL(14,3) NOT NULL,
    reason VARCHAR(500) NULL,
    UNIQUE KEY uq_count_lot (stock_count_id,inventory_lot_id),
    CONSTRAINT fk_count_line_count FOREIGN KEY (stock_count_id) REFERENCES stock_counts(id) ON DELETE CASCADE,
    CONSTRAINT fk_count_line_lot FOREIGN KEY (inventory_lot_id) REFERENCES inventory_lots(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tray_assets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tray_code VARCHAR(60) NOT NULL UNIQUE,
    barcode VARCHAR(100) NULL UNIQUE,
    rfid_tag VARCHAR(100) NULL UNIQUE,
    current_location VARCHAR(160) NULL,
    status ENUM('available','in_production','in_warehouse','with_customer','in_vehicle','returned','damaged','missing') NOT NULL DEFAULT 'available',
    last_seen_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tray_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tray_id BIGINT UNSIGNED NOT NULL,
    from_status VARCHAR(40) NULL,
    to_status VARCHAR(40) NOT NULL,
    from_location VARCHAR(160) NULL,
    to_location VARCHAR(160) NULL,
    reference_type VARCHAR(60) NULL,
    reference_id BIGINT UNSIGNED NULL,
    moved_by BIGINT UNSIGNED NULL,
    moved_at DATETIME NOT NULL,
    notes VARCHAR(500) NULL,
    INDEX idx_tray_history (tray_id,moved_at),
    CONSTRAINT fk_tray_movement_tray FOREIGN KEY (tray_id) REFERENCES tray_assets(id),
    CONSTRAINT fk_tray_movement_user FOREIGN KEY (moved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS picking_lists (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pick_number VARCHAR(50) NOT NULL UNIQUE,
    sales_order_id BIGINT UNSIGNED NOT NULL,
    status ENUM('open','picking','completed','cancelled') NOT NULL DEFAULT 'open',
    assigned_to BIGINT UNSIGNED NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pick_order FOREIGN KEY (sales_order_id) REFERENCES sales_orders(id),
    CONSTRAINT fk_pick_user FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS picking_list_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    picking_list_id BIGINT UNSIGNED NOT NULL,
    sales_order_item_id BIGINT UNSIGNED NOT NULL,
    inventory_lot_id BIGINT UNSIGNED NOT NULL,
    requested_quantity DECIMAL(14,3) NOT NULL,
    picked_quantity DECIMAL(14,3) NOT NULL DEFAULT 0,
    status ENUM('open','picked','short') NOT NULL DEFAULT 'open',
    CONSTRAINT fk_pick_item_list FOREIGN KEY (picking_list_id) REFERENCES picking_lists(id) ON DELETE CASCADE,
    CONSTRAINT fk_pick_item_order FOREIGN KEY (sales_order_item_id) REFERENCES sales_order_items(id),
    CONSTRAINT fk_pick_item_lot FOREIGN KEY (inventory_lot_id) REFERENCES inventory_lots(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS packaging_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    packaging_number VARCHAR(50) NOT NULL UNIQUE,
    sales_order_id BIGINT UNSIGNED NULL,
    production_batch_id BIGINT UNSIGNED NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    package_type VARCHAR(100) NOT NULL,
    package_count INT UNSIGNED NOT NULL,
    total_weight_kg DECIMAL(14,3) NOT NULL,
    label_reference VARCHAR(100) NULL,
    operator_id BIGINT UNSIGNED NULL,
    packed_at DATETIME NOT NULL,
    status ENUM('draft','completed','rejected') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_package_order FOREIGN KEY (sales_order_id) REFERENCES sales_orders(id) ON DELETE SET NULL,
    CONSTRAINT fk_package_batch FOREIGN KEY (production_batch_id) REFERENCES production_batches(id) ON DELETE SET NULL,
    CONSTRAINT fk_package_product FOREIGN KEY (product_id) REFERENCES products(id),
    CONSTRAINT fk_package_user FOREIGN KEY (operator_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS deboning_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    deboning_number VARCHAR(50) NOT NULL UNIQUE,
    sales_order_id BIGINT UNSIGNED NULL,
    production_batch_id BIGINT UNSIGNED NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    input_weight_kg DECIMAL(14,3) NOT NULL,
    bone_weight_kg DECIMAL(14,3) NOT NULL DEFAULT 0,
    net_meat_weight_kg DECIMAL(14,3) NOT NULL,
    waste_weight_kg DECIMAL(14,3) NOT NULL DEFAULT 0,
    yield_percent DECIMAL(7,3) NOT NULL DEFAULT 0,
    operator_id BIGINT UNSIGNED NULL,
    processed_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_debone_order FOREIGN KEY (sales_order_id) REFERENCES sales_orders(id) ON DELETE SET NULL,
    CONSTRAINT fk_debone_batch FOREIGN KEY (production_batch_id) REFERENCES production_batches(id) ON DELETE SET NULL,
    CONSTRAINT fk_debone_product FOREIGN KEY (product_id) REFERENCES products(id),
    CONSTRAINT fk_debone_user FOREIGN KEY (operator_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_returns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    return_number VARCHAR(50) NOT NULL UNIQUE,
    sales_order_id BIGINT UNSIGNED NOT NULL,
    invoice_id BIGINT UNSIGNED NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    return_date DATE NOT NULL,
    reason VARCHAR(1000) NOT NULL,
    status ENUM('received','qc_pending','approved_stock','reprocess','disposal','claim_rejected','credited','closed') NOT NULL DEFAULT 'received',
    credit_note_number VARCHAR(60) NULL,
    received_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_return_order FOREIGN KEY (sales_order_id) REFERENCES sales_orders(id),
    CONSTRAINT fk_return_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
    CONSTRAINT fk_return_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
    CONSTRAINT fk_return_user FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_return_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sales_return_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    inventory_lot_id BIGINT UNSIGNED NULL,
    quantity DECIMAL(14,3) NOT NULL,
    weight_kg DECIMAL(14,3) NOT NULL DEFAULT 0,
    return_condition VARCHAR(160) NULL,
    qc_result ENUM('pending','stock','reprocess','disposal','rejected') NOT NULL DEFAULT 'pending',
    qc_notes TEXT NULL,
    CONSTRAINT fk_return_item_return FOREIGN KEY (sales_return_id) REFERENCES sales_returns(id) ON DELETE CASCADE,
    CONSTRAINT fk_return_item_product FOREIGN KEY (product_id) REFERENCES products(id),
    CONSTRAINT fk_return_item_lot FOREIGN KEY (inventory_lot_id) REFERENCES inventory_lots(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS route_masters (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    route_code VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    origin VARCHAR(160) NOT NULL,
    destination VARCHAR(160) NOT NULL,
    distance_km DECIMAL(10,2) NOT NULL DEFAULT 0,
    planned_minutes INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS delivery_trips (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trip_number VARCHAR(50) NOT NULL UNIQUE,
    dispatch_id BIGINT UNSIGNED NOT NULL,
    route_id BIGINT UNSIGNED NULL,
    vehicle_id BIGINT UNSIGNED NOT NULL,
    driver_id BIGINT UNSIGNED NOT NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    current_latitude DECIMAL(10,7) NULL,
    current_longitude DECIMAL(10,7) NULL,
    status ENUM('planned','in_transit','arrived','completed','failed') NOT NULL DEFAULT 'planned',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_trip_dispatch FOREIGN KEY (dispatch_id) REFERENCES dispatches(id),
    CONSTRAINT fk_trip_route FOREIGN KEY (route_id) REFERENCES route_masters(id) ON DELETE SET NULL,
    CONSTRAINT fk_trip_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
    CONSTRAINT fk_trip_driver FOREIGN KEY (driver_id) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vehicle_temperature_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    delivery_trip_id BIGINT UNSIGNED NOT NULL,
    temperature_c DECIMAL(6,2) NOT NULL,
    recorded_at DATETIME NOT NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    quality ENUM('good','suspect','bad') NOT NULL DEFAULT 'good',
    INDEX idx_vehicle_temp_trip (delivery_trip_id,recorded_at),
    CONSTRAINT fk_vehicle_temp_trip FOREIGN KEY (delivery_trip_id) REFERENCES delivery_trips(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dispatch_checks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dispatch_id BIGINT UNSIGNED NOT NULL,
    check_code VARCHAR(60) NOT NULL,
    label VARCHAR(180) NOT NULL,
    is_mandatory TINYINT(1) NOT NULL DEFAULT 1,
    passed TINYINT(1) NOT NULL DEFAULT 0,
    checked_by BIGINT UNSIGNED NULL,
    checked_at DATETIME NULL,
    notes VARCHAR(500) NULL,
    UNIQUE KEY uq_dispatch_check (dispatch_id,check_code),
    CONSTRAINT fk_dispatch_check_dispatch FOREIGN KEY (dispatch_id) REFERENCES dispatches(id) ON DELETE CASCADE,
    CONSTRAINT fk_dispatch_check_user FOREIGN KEY (checked_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_number VARCHAR(60) NOT NULL UNIQUE,
    entity_type VARCHAR(80) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    category VARCHAR(100) NOT NULL,
    title VARCHAR(200) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(120) NULL,
    file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
    expiry_date DATE NULL,
    tags VARCHAR(500) NULL,
    access_level ENUM('private','department','plant','company','customer') NOT NULL DEFAULT 'private',
    uploaded_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_document_entity (entity_type,entity_id),
    INDEX idx_document_expiry (expiry_date),
    CONSTRAINT fk_document_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS capa_actions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    capa_number VARCHAR(50) NOT NULL UNIQUE,
    source_type VARCHAR(80) NOT NULL,
    source_id BIGINT UNSIGNED NULL,
    issue TEXT NOT NULL,
    investigation TEXT NULL,
    root_cause TEXT NULL,
    corrective_action TEXT NULL,
    preventive_action TEXT NULL,
    assigned_to BIGINT UNSIGNED NULL,
    target_date DATE NOT NULL,
    verification TEXT NULL,
    status ENUM('open','investigating','action','verification','closed','cancelled') NOT NULL DEFAULT 'open',
    created_by BIGINT UNSIGNED NULL,
    closed_by BIGINT UNSIGNED NULL,
    closed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_capa_assignee FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_capa_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_capa_closer FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    category VARCHAR(60) NOT NULL,
    severity ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
    title VARCHAR(180) NOT NULL,
    description VARCHAR(1000) NOT NULL,
    related_type VARCHAR(80) NULL,
    related_id BIGINT UNSIGNED NULL,
    read_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notification_user (user_id,read_at,created_at),
    CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_prices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NULL,
    customer_type VARCHAR(40) NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    price_type ENUM('standard','customer','group','promotional','contract') NOT NULL,
    unit_price DECIMAL(14,3) NOT NULL,
    valid_from DATE NOT NULL,
    valid_to DATE NULL,
    max_discount_percent DECIMAL(6,3) NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customer_price_lookup (product_id,customer_id,status,valid_from,valid_to),
    CONSTRAINT fk_customer_price_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    CONSTRAINT fk_customer_price_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shareholders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    shareholder_code VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(180) NOT NULL,
    ownership_percent DECIMAL(7,4) NOT NULL DEFAULT 0,
    phone VARCHAR(40) NULL,
    email VARCHAR(190) NULL,
    account_reference VARCHAR(100) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_shareholder_company FOREIGN KEY (company_id) REFERENCES companies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (name,slug,description)
SELECT x.name,x.slug,CONCAT(x.name,' access profile') FROM (
    SELECT 'Plant Supervisor' name,'plant_supervisor' slug UNION ALL
    SELECT 'Production Supervisor','production_supervisor' UNION ALL
    SELECT 'EV Supervisor','ev_supervisor' UNION ALL
    SELECT 'Quality Inspector','quality_inspector' UNION ALL
    SELECT 'Store In Charge','store_in_charge' UNION ALL
    SELECT 'Dispatch Supervisor','dispatch_supervisor' UNION ALL
    SELECT 'Sales Staff','sales_staff' UNION ALL
    SELECT 'Accountant','accountant' UNION ALL
    SELECT 'HR Staff','hr_staff' UNION ALL
    SELECT 'Purchase Staff','purchase_staff' UNION ALL
    SELECT 'Maintenance Technician','maintenance_technician' UNION ALL
    SELECT 'Administrator','administrator' UNION ALL
    SELECT 'Assistant Administrator','assistant_administrator'
) x WHERE NOT EXISTS (SELECT 1 FROM roles r WHERE r.slug=x.slug);

INSERT IGNORE INTO permissions (name,slug)
SELECT CONCAT(UPPER(LEFT(a.area,1)),SUBSTRING(a.area,2),' ',x.action),CONCAT(a.area,'.',x.action)
FROM (
    SELECT 'dashboard' area UNION ALL SELECT 'production' UNION ALL SELECT 'purchase' UNION ALL SELECT 'inventory' UNION ALL
    SELECT 'crm' UNION ALL SELECT 'sales' UNION ALL SELECT 'quality' UNION ALL SELECT 'logistics' UNION ALL SELECT 'finance' UNION ALL
    SELECT 'hr' UNION ALL SELECT 'maintenance' UNION ALL SELECT 'hygiene' UNION ALL SELECT 'iot' UNION ALL SELECT 'reports' UNION ALL
    SELECT 'settings' UNION ALL SELECT 'users' UNION ALL SELECT 'audit'
) a CROSS JOIN (
    SELECT 'create' action UNION ALL SELECT 'edit' UNION ALL SELECT 'delete' UNION ALL SELECT 'approve' UNION ALL SELECT 'reject' UNION ALL
    SELECT 'cancel' UNION ALL SELECT 'print' UNION ALL SELECT 'export' UNION ALL SELECT 'import'
) x;

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.slug='super_admin';

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN (
    SELECT 'plant_supervisor' role_slug,'production' area UNION ALL SELECT 'plant_supervisor','inventory' UNION ALL SELECT 'plant_supervisor','quality' UNION ALL SELECT 'plant_supervisor','hygiene' UNION ALL
    SELECT 'production_supervisor','production' UNION ALL SELECT 'ev_supervisor','production' UNION ALL SELECT 'quality_inspector','quality' UNION ALL SELECT 'quality_inspector','production' UNION ALL
    SELECT 'store_in_charge','inventory' UNION ALL SELECT 'dispatch_supervisor','logistics' UNION ALL SELECT 'sales_staff','sales' UNION ALL SELECT 'sales_staff','crm' UNION ALL
    SELECT 'accountant','finance' UNION ALL SELECT 'hr_staff','hr' UNION ALL SELECT 'purchase_staff','purchase' UNION ALL SELECT 'maintenance_technician','maintenance' UNION ALL
    SELECT 'administrator','settings' UNION ALL SELECT 'administrator','users' UNION ALL SELECT 'assistant_administrator','settings'
) m ON m.role_slug=r.slug JOIN permissions p ON p.slug LIKE CONCAT(m.area,'.%');

INSERT IGNORE INTO user_roles (user_id,role_id,is_primary) SELECT id,role_id,1 FROM users;

INSERT INTO companies (code,name,legal_name,currency,timezone,status)
SELECT 'MEATIN','Meatin Farms & Foods','Meatin Farms & Foods LLP','INR','Asia/Kolkata','active'
WHERE NOT EXISTS (SELECT 1 FROM companies WHERE code='MEATIN');

INSERT INTO plants (company_id,code,name,status)
SELECT id,'PLANT01','Meatin Processing Plant','active' FROM companies WHERE code='MEATIN'
AND NOT EXISTS (SELECT 1 FROM plants WHERE code='PLANT01');

INSERT INTO departments (plant_id,code,name,status)
SELECT p.id,x.code,x.name,'active' FROM plants p JOIN (
    SELECT 'MGMT' code,'Management' name UNION ALL SELECT 'PUR','Purchase' UNION ALL SELECT 'PROD','Production' UNION ALL
    SELECT 'QC','Quality' UNION ALL SELECT 'STORE','Store' UNION ALL SELECT 'LOG','Logistics' UNION ALL SELECT 'SALES','Sales' UNION ALL
    SELECT 'FIN','Finance' UNION ALL SELECT 'HR','HR' UNION ALL SELECT 'MAINT','Maintenance' UNION ALL SELECT 'HYG','Hygiene'
) x WHERE p.code='PLANT01' AND NOT EXISTS (SELECT 1 FROM departments d WHERE d.code=x.code);

INSERT INTO production_lines (plant_id,code,name,capacity_per_hour,status)
SELECT id,'LINE01','Poultry Processing Line 1',12000,'active' FROM plants WHERE code='PLANT01'
AND NOT EXISTS (SELECT 1 FROM production_lines WHERE code='LINE01');

INSERT INTO warehouses (plant_id,code,name,warehouse_type,status)
SELECT p.id,x.code,x.name,x.kind,'active' FROM plants p JOIN (
    SELECT 'WH-RAW' code,'Raw Material Store' name,'raw' kind UNION ALL
    SELECT 'WH-PACK','Packaging Store','packaging' UNION ALL
    SELECT 'WH-FG','Finished Goods Cold Store','cold_storage' UNION ALL
    SELECT 'WH-SPARE','Spare Parts Store','spares' UNION ALL
    SELECT 'WH-HYG','Hygiene & PPE Store','hygiene'
) x WHERE p.code='PLANT01' AND NOT EXISTS (SELECT 1 FROM warehouses w WHERE w.code=x.code);

INSERT INTO item_categories (code,name,category_type,status)
SELECT x.code,x.name,x.kind,'active' FROM (
    SELECT 'FG' code,'Finished Goods' name,'finished_good' kind UNION ALL SELECT 'RAW','Raw Materials','raw_material' UNION ALL
    SELECT 'PACK','Packaging Materials','packaging' UNION ALL SELECT 'SPARE','Spare Parts','spare_part' UNION ALL
    SELECT 'BYP','By-products','by_product' UNION ALL SELECT 'HYG','Hygiene & PPE','hygiene' UNION ALL SELECT 'TRAY','Reusable Trays','tray'
) x WHERE NOT EXISTS (SELECT 1 FROM item_categories c WHERE c.code=x.code);

UPDATE products p JOIN item_categories c ON c.category_type=p.category SET p.category_id=c.id WHERE p.category_id IS NULL;
UPDATE purchase_orders SET plant_id=(SELECT id FROM plants WHERE code='PLANT01' LIMIT 1) WHERE plant_id IS NULL;
UPDATE bird_receipts SET plant_id=(SELECT id FROM plants WHERE code='PLANT01' LIMIT 1) WHERE plant_id IS NULL;
UPDATE production_batches SET plant_id=(SELECT id FROM plants WHERE code='PLANT01' LIMIT 1),production_line_id=(SELECT id FROM production_lines WHERE code='LINE01' LIMIT 1) WHERE plant_id IS NULL;
UPDATE sales_orders SET plant_id=(SELECT id FROM plants WHERE code='PLANT01' LIMIT 1) WHERE plant_id IS NULL;
UPDATE employees e LEFT JOIN departments d ON LOWER(d.name)=LOWER(e.department) SET e.plant_id=(SELECT id FROM plants WHERE code='PLANT01' LIMIT 1),e.department_id=d.id WHERE e.plant_id IS NULL;

INSERT INTO number_sequences (plant_id,document_type,prefix,financial_year,next_number,padding,status)
SELECT p.id,x.document_type,x.prefix,CONCAT(YEAR(CURDATE()),'-',YEAR(CURDATE())+1),1,6,'active' FROM plants p JOIN (
    SELECT 'purchase_requisition' document_type,'PR' prefix UNION ALL SELECT 'purchase_order','PO' UNION ALL SELECT 'bird_receipt','BR' UNION ALL
    SELECT 'production_batch','PB' UNION ALL SELECT 'sales_order','SO' UNION ALL SELECT 'invoice','INV' UNION ALL SELECT 'dispatch','DSP' UNION ALL
    SELECT 'sales_return','SRT' UNION ALL SELECT 'stock_transfer','STF' UNION ALL SELECT 'capa','CAPA'
) x WHERE p.code='PLANT01' AND NOT EXISTS (SELECT 1 FROM number_sequences n WHERE n.plant_id=p.id AND n.document_type=x.document_type AND n.financial_year=CONCAT(YEAR(CURDATE()),'-',YEAR(CURDATE())+1));
