ALTER TABLE production_batches
    MODIFY COLUMN stage ENUM('receiving','slaughtering','scalding','defeathering','evisceration','chilling','quality_control','grading','packing','store_keeping','storage','dispatch_ready','dispatch') NOT NULL DEFAULT 'receiving';

UPDATE production_batches SET stage='grading' WHERE stage='quality_control';
UPDATE production_batches SET stage='storage' WHERE stage='store_keeping';
UPDATE production_batches SET stage='dispatch' WHERE stage='dispatch_ready';
UPDATE production_stage_logs SET stage='grading' WHERE stage='quality_control';
UPDATE production_stage_logs SET stage='storage' WHERE stage='store_keeping';
UPDATE production_stage_logs SET stage='dispatch' WHERE stage='dispatch_ready';

ALTER TABLE production_batches
    MODIFY COLUMN stage ENUM('receiving','slaughtering','scalding','defeathering','evisceration','chilling','grading','packing','storage','dispatch') NOT NULL DEFAULT 'receiving';

CREATE TABLE IF NOT EXISTS production_damaged_birds (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    damage_number VARCHAR(50) NOT NULL UNIQUE,
    production_batch_id BIGINT UNSIGNED NOT NULL,
    stage ENUM('receiving','slaughtering','scalding','defeathering','evisceration','chilling','grading','packing','storage','dispatch') NOT NULL,
    damaged_bird_count INT UNSIGNED NOT NULL DEFAULT 0,
    damaged_weight_kg DECIMAL(14,3) NOT NULL DEFAULT 0,
    reason_category ENUM('handling','equipment','temperature','quality','contamination','packaging','transport','other') NOT NULL,
    reason_details VARCHAR(1000) NOT NULL,
    disposition ENUM('waste','by_product','rework','condemned') NOT NULL DEFAULT 'waste',
    recorded_at DATETIME NOT NULL,
    recorded_by BIGINT UNSIGNED NULL,
    status ENUM('reported','confirmed','disposed') NOT NULL DEFAULT 'reported',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_damaged_birds_batch_stage (production_batch_id,stage),
    INDEX idx_damaged_birds_date (recorded_at),
    CONSTRAINT fk_damaged_birds_batch FOREIGN KEY (production_batch_id) REFERENCES production_batches(id) ON DELETE CASCADE,
    CONSTRAINT fk_damaged_birds_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_tasks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_number VARCHAR(50) NOT NULL UNIQUE,
    employee_id BIGINT UNSIGNED NOT NULL,
    manager_id BIGINT UNSIGNED NULL,
    assigned_by_user_id BIGINT UNSIGNED NULL,
    assigned_by_employee_id BIGINT UNSIGNED NULL,
    task_date DATE NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    due_at DATETIME NULL,
    status ENUM('assigned','in_progress','completed','pending','cancelled') NOT NULL DEFAULT 'assigned',
    completed_at DATETIME NULL,
    completion_notes VARCHAR(1500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_employee_tasks_daily (task_date,employee_id,status),
    INDEX idx_employee_tasks_manager (manager_id,task_date),
    CONSTRAINT fk_employee_task_employee FOREIGN KEY (employee_id) REFERENCES employees(id),
    CONSTRAINT fk_employee_task_manager FOREIGN KEY (manager_id) REFERENCES employees(id) ON DELETE SET NULL,
    CONSTRAINT fk_employee_task_assigned_user FOREIGN KEY (assigned_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_employee_task_assigned_employee FOREIGN KEY (assigned_by_employee_id) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_activity_status (
    employee_id BIGINT UNSIGNED PRIMARY KEY,
    last_activity_at DATETIME NULL,
    last_route VARCHAR(120) NULL,
    last_ip VARCHAR(45) NULL,
    last_location_ping_at DATETIME NULL,
    last_reminder_at DATETIME NULL,
    reminder_count INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_employee_activity_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS salesman_tracking_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tracking_number VARCHAR(50) NOT NULL UNIQUE,
    employee_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    route_master_id BIGINT UNSIGNED NULL,
    visit_purpose VARCHAR(180) NOT NULL,
    location_name VARCHAR(200) NOT NULL,
    started_at DATETIME NOT NULL,
    ended_at DATETIME NULL,
    start_latitude DECIMAL(10,7) NOT NULL,
    start_longitude DECIMAL(10,7) NOT NULL,
    end_latitude DECIMAL(10,7) NULL,
    end_longitude DECIMAL(10,7) NULL,
    last_latitude DECIMAL(10,7) NOT NULL,
    last_longitude DECIMAL(10,7) NOT NULL,
    last_ping_at DATETIME NOT NULL,
    distance_km DECIMAL(12,3) NOT NULL DEFAULT 0,
    status ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
    notes VARCHAR(1500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_salesman_tracking_employee (employee_id,status,started_at),
    INDEX idx_salesman_tracking_customer (customer_id,started_at),
    CONSTRAINT fk_salesman_tracking_employee FOREIGN KEY (employee_id) REFERENCES employees(id),
    CONSTRAINT fk_salesman_tracking_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_salesman_tracking_route FOREIGN KEY (route_master_id) REFERENCES route_masters(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS salesman_location_points (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tracking_session_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    accuracy_meters DECIMAL(10,2) NULL,
    recorded_at DATETIME NOT NULL,
    INDEX idx_salesman_points_session (tracking_session_id,recorded_at),
    INDEX idx_salesman_points_employee (employee_id,recorded_at),
    CONSTRAINT fk_salesman_point_session FOREIGN KEY (tracking_session_id) REFERENCES salesman_tracking_sessions(id) ON DELETE CASCADE,
    CONSTRAINT fk_salesman_point_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO companies (code,name,legal_name,currency,timezone,status)
SELECT 'MEATIN','Meatin Farms & Foods','Meatin Farms & Foods LLP','INR','Asia/Kolkata','active'
WHERE NOT EXISTS (SELECT 1 FROM companies WHERE status='active');

INSERT INTO plants (company_id,code,name,status)
SELECT c.id,'PLANT01','Meatin Processing Plant','active' FROM companies c WHERE c.status='active' ORDER BY c.id LIMIT 1
ON DUPLICATE KEY UPDATE status='active';

INSERT INTO departments (plant_id,code,name,status)
SELECT p.id,x.code,x.name,'active' FROM plants p JOIN (
    SELECT 'MGMT' code,'Management' name UNION ALL SELECT 'PUR','Purchase' UNION ALL SELECT 'PROD','Production' UNION ALL
    SELECT 'QC','Quality' UNION ALL SELECT 'STORE','Store' UNION ALL SELECT 'LOG','Logistics' UNION ALL SELECT 'SALES','Sales' UNION ALL
    SELECT 'FIN','Finance' UNION ALL SELECT 'HR','HR' UNION ALL SELECT 'MAINT','Maintenance' UNION ALL SELECT 'HYG','Hygiene'
) x WHERE p.status='active' AND NOT EXISTS (SELECT 1 FROM departments d WHERE d.code=x.code) ORDER BY p.id LIMIT 11;

INSERT INTO storage_zones (code,name,zone_type,capacity_kg,min_temperature_c,max_temperature_c,current_temperature_c,status)
SELECT x.code,x.name,x.zone_type,x.capacity_kg,x.min_c,x.max_c,NULL,'active' FROM (
    SELECT 'RAW-01' code,'Raw Material Holding' name,'ambient' zone_type,10000 capacity_kg,15 min_c,30 max_c UNION ALL
    SELECT 'CHILL-01','Process Chiller','chiller',10000,0,5 UNION ALL
    SELECT 'COLD-01','Finished Goods Cold Store','cold_storage',25000,-22,-15 UNION ALL
    SELECT 'DISPATCH-01','Dispatch Staging','dispatch',10000,-2,5
) x WHERE NOT EXISTS (SELECT 1 FROM storage_zones z WHERE z.code=x.code);

UPDATE employees e
JOIN departments d ON LOWER(d.name)=LOWER(e.department)
SET e.department_id=d.id,e.plant_id=COALESCE(e.plant_id,d.plant_id)
WHERE e.department_id IS NULL OR e.plant_id IS NULL;

UPDATE bird_receipts br
LEFT JOIN production_batches pb ON pb.bird_receipt_id=br.id
SET br.status=CASE WHEN pb.id IS NULL THEN 'accepted' ELSE 'released' END
WHERE br.status='quarantine' AND br.vet_status='approved' AND TRIM(COALESCE(br.vet_certificate,''))<>'';

UPDATE bird_receipts
SET status='rejected'
WHERE status='quarantine' AND vet_status='rejected';

INSERT INTO settings (setting_key,setting_value,updated_by)
SELECT x.setting_key,x.setting_value,NULL FROM (
    SELECT 'office_hours_start' setting_key,'09:00' setting_value UNION ALL
    SELECT 'office_hours_end','18:00' UNION ALL
    SELECT 'employee_inactivity_minutes','30' UNION ALL
    SELECT 'salesman_location_ping_seconds','60'
) x WHERE NOT EXISTS (SELECT 1 FROM settings s WHERE s.setting_key=x.setting_key);
