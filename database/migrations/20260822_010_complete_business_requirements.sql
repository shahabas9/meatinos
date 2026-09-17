CREATE TABLE IF NOT EXISTS cost_centers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    center_code VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    department_id BIGINT UNSIGNED NULL,
    manager_id BIGINT UNSIGNED NULL,
    budget_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cost_center_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    CONSTRAINT fk_cost_center_manager FOREIGN KEY (manager_id) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS company_bank_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    account_code VARCHAR(40) NOT NULL UNIQUE,
    bank_name VARCHAR(160) NOT NULL,
    branch_name VARCHAR(160) NULL,
    account_holder VARCHAR(180) NOT NULL,
    masked_account VARCHAR(60) NOT NULL,
    ifsc_code VARCHAR(20) NULL,
    account_type ENUM('current','savings','cash','credit','other') NOT NULL DEFAULT 'current',
    opening_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
    current_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active','inactive','blocked') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_company_bank_status (company_id,status),
    CONSTRAINT fk_company_bank_company FOREIGN KEY (company_id) REFERENCES companies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE payments ADD COLUMN company_bank_account_id BIGINT UNSIGNED NULL AFTER payment_method;
ALTER TABLE payments ADD INDEX idx_payment_bank (company_bank_account_id);
ALTER TABLE payments ADD CONSTRAINT fk_payment_bank FOREIGN KEY (company_bank_account_id) REFERENCES company_bank_accounts(id) ON DELETE SET NULL;

ALTER TABLE supplier_payments ADD COLUMN company_bank_account_id BIGINT UNSIGNED NULL AFTER payment_method;
ALTER TABLE supplier_payments ADD INDEX idx_supplier_payment_bank (company_bank_account_id);
ALTER TABLE supplier_payments ADD CONSTRAINT fk_supplier_payment_bank FOREIGN KEY (company_bank_account_id) REFERENCES company_bank_accounts(id) ON DELETE SET NULL;

ALTER TABLE journal_lines ADD COLUMN cost_center_id BIGINT UNSIGNED NULL AFTER account_id;
ALTER TABLE journal_lines ADD INDEX idx_journal_cost_center (cost_center_id);
ALTER TABLE journal_lines ADD CONSTRAINT fk_journal_line_cost_center FOREIGN KEY (cost_center_id) REFERENCES cost_centers(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS operating_expenses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    expense_number VARCHAR(50) NOT NULL UNIQUE,
    expense_date DATE NOT NULL,
    expense_type ENUM('office_rent','stationery','electricity','water','pantry','accommodation','transport','repair','professional','event','other') NOT NULL,
    supplier_id BIGINT UNSIGNED NULL,
    cost_center_id BIGINT UNSIGNED NULL,
    company_bank_account_id BIGINT UNSIGNED NULL,
    description VARCHAR(1000) NOT NULL,
    taxable_amount DECIMAL(14,2) NOT NULL,
    gst_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(14,2) NOT NULL,
    payment_method ENUM('bank_transfer','card','cash','cheque','upi') NOT NULL DEFAULT 'bank_transfer',
    reference_number VARCHAR(100) NULL,
    status ENUM('draft','approved','paid','reversed','cancelled') NOT NULL DEFAULT 'draft',
    approved_by BIGINT UNSIGNED NULL,
    paid_at DATETIME NULL,
    reversed_at DATETIME NULL,
    reversal_reason VARCHAR(1000) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_expense_daybook (expense_date,status),
    CONSTRAINT fk_expense_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    CONSTRAINT fk_expense_cost_center FOREIGN KEY (cost_center_id) REFERENCES cost_centers(id) ON DELETE SET NULL,
    CONSTRAINT fk_expense_bank FOREIGN KEY (company_bank_account_id) REFERENCES company_bank_accounts(id) ON DELETE SET NULL,
    CONSTRAINT fk_expense_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_expense_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE assets ADD COLUMN cost_center_id BIGINT UNSIGNED NULL AFTER location;
ALTER TABLE assets ADD COLUMN acquisition_date DATE NULL AFTER installation_date;
ALTER TABLE assets ADD COLUMN acquisition_cost DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER acquisition_date;
ALTER TABLE assets ADD COLUMN residual_value DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER acquisition_cost;
ALTER TABLE assets ADD COLUMN useful_life_months INT UNSIGNED NOT NULL DEFAULT 0 AFTER residual_value;
ALTER TABLE assets ADD COLUMN accumulated_depreciation DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER useful_life_months;
ALTER TABLE assets ADD INDEX idx_asset_cost_center (cost_center_id);
ALTER TABLE assets ADD CONSTRAINT fk_asset_cost_center FOREIGN KEY (cost_center_id) REFERENCES cost_centers(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS asset_depreciation_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_id BIGINT UNSIGNED NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    depreciation_amount DECIMAL(14,2) NOT NULL,
    accumulated_amount DECIMAL(14,2) NOT NULL,
    book_value DECIMAL(14,2) NOT NULL,
    journal_entry_id BIGINT UNSIGNED NULL,
    status ENUM('draft','posted','reversed') NOT NULL DEFAULT 'draft',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_asset_depreciation_period (asset_id,period_start,period_end),
    CONSTRAINT fk_depreciation_asset FOREIGN KEY (asset_id) REFERENCES assets(id),
    CONSTRAINT fk_depreciation_journal FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE SET NULL,
    CONSTRAINT fk_depreciation_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE employees ADD COLUMN address TEXT NULL AFTER phone;
ALTER TABLE employees ADD COLUMN alternate_address TEXT NULL AFTER address;
ALTER TABLE employees ADD COLUMN aadhaar_number VARCHAR(20) NULL AFTER alternate_address;
ALTER TABLE employees ADD COLUMN pan_number VARCHAR(20) NULL AFTER aadhaar_number;
ALTER TABLE employees ADD COLUMN uan_number VARCHAR(30) NULL AFTER pan_number;
ALTER TABLE employees ADD COLUMN esi_number VARCHAR(30) NULL AFTER uan_number;
ALTER TABLE employees ADD COLUMN insurance_number VARCHAR(60) NULL AFTER esi_number;
ALTER TABLE employees ADD COLUMN date_of_birth DATE NULL AFTER insurance_number;
ALTER TABLE employees ADD COLUMN emergency_contact VARCHAR(60) NULL AFTER date_of_birth;
ALTER TABLE employees ADD COLUMN resignation_date DATE NULL AFTER join_date;
ALTER TABLE employees ADD COLUMN employment_status ENUM('probation','confirmed','notice','resigned','terminated') NOT NULL DEFAULT 'confirmed' AFTER resignation_date;

ALTER TABLE attendance ADD COLUMN extra_duty_hours DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER overtime_hours;
ALTER TABLE attendance ADD COLUMN duty_type ENUM('regular','extra_duty','holiday_duty','night_duty') NOT NULL DEFAULT 'regular' AFTER extra_duty_hours;

ALTER TABLE payroll_items ADD COLUMN commission_amount DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER allowances;
ALTER TABLE payroll_items ADD COLUMN advance_recovery DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER commission_amount;
ALTER TABLE payroll_items ADD COLUMN remarks VARCHAR(1000) NULL AFTER deductions;

CREATE TABLE IF NOT EXISTS employee_bank_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    bank_name VARCHAR(160) NOT NULL,
    branch_name VARCHAR(160) NULL,
    account_holder VARCHAR(180) NOT NULL,
    masked_account VARCHAR(60) NOT NULL,
    ifsc_code VARCHAR(20) NULL,
    is_salary_account TINYINT(1) NOT NULL DEFAULT 1,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active','inactive','blocked') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_employee_bank_employee (employee_id,status),
    CONSTRAINT fk_employee_bank_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_benefits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    benefit_type ENUM('esi','pf','insurance','gratuity','bonus','allowance','other') NOT NULL,
    reference_number VARCHAR(80) NULL,
    employer_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    employee_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    status ENUM('active','inactive','closed') NOT NULL DEFAULT 'active',
    notes VARCHAR(1000) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_employee_benefit_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_uniform_allocations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    allocation_number VARCHAR(50) NOT NULL UNIQUE,
    employee_id BIGINT UNSIGNED NOT NULL,
    item_name VARCHAR(160) NOT NULL,
    size VARCHAR(40) NULL,
    quantity INT UNSIGNED NOT NULL,
    issued_date DATE NOT NULL,
    expected_return_date DATE NULL,
    returned_date DATE NULL,
    condition_status ENUM('issued','good','damaged','lost','returned') NOT NULL DEFAULT 'issued',
    issued_by BIGINT UNSIGNED NULL,
    notes VARCHAR(1000) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_uniform_employee FOREIGN KEY (employee_id) REFERENCES employees(id),
    CONSTRAINT fk_uniform_user FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_memos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    memo_number VARCHAR(50) NOT NULL UNIQUE,
    employee_id BIGINT UNSIGNED NOT NULL,
    memo_date DATE NOT NULL,
    memo_type ENUM('information','warning','disciplinary','appreciation','policy','other') NOT NULL,
    subject VARCHAR(255) NOT NULL,
    details TEXT NOT NULL,
    response_due_date DATE NULL,
    acknowledged_at DATETIME NULL,
    status ENUM('draft','issued','acknowledged','closed','cancelled') NOT NULL DEFAULT 'draft',
    issued_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_memo_employee FOREIGN KEY (employee_id) REFERENCES employees(id),
    CONSTRAINT fk_memo_user FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_appointments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_number VARCHAR(50) NOT NULL UNIQUE,
    employee_id BIGINT UNSIGNED NOT NULL,
    letter_type ENUM('offer','appointment','confirmation','promotion','transfer') NOT NULL,
    issue_date DATE NOT NULL,
    effective_date DATE NOT NULL,
    designation VARCHAR(160) NOT NULL,
    salary_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    terms TEXT NULL,
    status ENUM('draft','issued','accepted','declined','cancelled') NOT NULL DEFAULT 'draft',
    accepted_at DATETIME NULL,
    issued_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_appointment_employee FOREIGN KEY (employee_id) REFERENCES employees(id),
    CONSTRAINT fk_appointment_user FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_performance_reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    review_number VARCHAR(50) NOT NULL UNIQUE,
    employee_id BIGINT UNSIGNED NOT NULL,
    review_period_start DATE NOT NULL,
    review_period_end DATE NOT NULL,
    productivity_score DECIMAL(5,2) NOT NULL DEFAULT 0,
    quality_score DECIMAL(5,2) NOT NULL DEFAULT 0,
    attendance_score DECIMAL(5,2) NOT NULL DEFAULT 0,
    behaviour_score DECIMAL(5,2) NOT NULL DEFAULT 0,
    overall_score DECIMAL(5,2) NOT NULL DEFAULT 0,
    rank_number INT UNSIGNED NULL,
    feedback TEXT NULL,
    improvement_plan TEXT NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    review_date DATE NOT NULL,
    status ENUM('draft','published','acknowledged') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_performance_ranking (review_period_end,overall_score),
    CONSTRAINT fk_performance_employee FOREIGN KEY (employee_id) REFERENCES employees(id),
    CONSTRAINT fk_performance_user FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS salary_advances (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    advance_number VARCHAR(50) NOT NULL UNIQUE,
    employee_id BIGINT UNSIGNED NOT NULL,
    request_date DATE NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    approved_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    recovery_start DATE NULL,
    monthly_recovery DECIMAL(14,2) NOT NULL DEFAULT 0,
    recovered_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    balance_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    company_bank_account_id BIGINT UNSIGNED NULL,
    payment_reference VARCHAR(100) NULL,
    paid_at DATETIME NULL,
    reason VARCHAR(1000) NOT NULL,
    status ENUM('requested','approved','paid','recovering','recovered','rejected','cancelled') NOT NULL DEFAULT 'requested',
    approved_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_salary_advance_employee FOREIGN KEY (employee_id) REFERENCES employees(id),
    CONSTRAINT fk_salary_advance_bank FOREIGN KEY (company_bank_account_id) REFERENCES company_bank_accounts(id) ON DELETE SET NULL,
    CONSTRAINT fk_salary_advance_user FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_resignations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resignation_number VARCHAR(50) NOT NULL UNIQUE,
    employee_id BIGINT UNSIGNED NOT NULL,
    submitted_date DATE NOT NULL,
    requested_last_date DATE NOT NULL,
    approved_last_date DATE NULL,
    reason TEXT NOT NULL,
    notice_days INT UNSIGNED NOT NULL DEFAULT 0,
    handover_status ENUM('pending','in_progress','completed','waived') NOT NULL DEFAULT 'pending',
    clearance_status ENUM('pending','partial','completed') NOT NULL DEFAULT 'pending',
    final_settlement_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    status ENUM('submitted','accepted','rejected','withdrawn','completed') NOT NULL DEFAULT 'submitted',
    approved_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_resignation_employee FOREIGN KEY (employee_id) REFERENCES employees(id),
    CONSTRAINT fk_resignation_user FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE sales_targets ADD COLUMN commission_rate DECIMAL(7,3) NOT NULL DEFAULT 0 AFTER target_amount;
ALTER TABLE sales_targets ADD COLUMN commission_amount DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER achieved_amount;

CREATE TABLE IF NOT EXISTS sales_commission_accruals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    target_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    eligible_sales DECIMAL(14,2) NOT NULL DEFAULT 0,
    commission_rate DECIMAL(7,3) NOT NULL DEFAULT 0,
    commission_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    payroll_run_id BIGINT UNSIGNED NULL,
    status ENUM('calculated','approved','included','reversed') NOT NULL DEFAULT 'calculated',
    calculated_at DATETIME NOT NULL,
    approved_by BIGINT UNSIGNED NULL,
    UNIQUE KEY uq_commission_target (target_id),
    CONSTRAINT fk_commission_target FOREIGN KEY (target_id) REFERENCES sales_targets(id) ON DELETE CASCADE,
    CONSTRAINT fk_commission_employee FOREIGN KEY (employee_id) REFERENCES employees(id),
    CONSTRAINT fk_commission_payroll FOREIGN KEY (payroll_run_id) REFERENCES payroll_runs(id) ON DELETE SET NULL,
    CONSTRAINT fk_commission_user FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE products ADD COLUMN brand_name VARCHAR(80) NOT NULL DEFAULT 'Meatin' AFTER name;
ALTER TABLE customers MODIFY COLUMN customer_type ENUM('retail','hotel','wholesale','distributor','dealer','restaurant','service') NOT NULL;

CREATE TABLE IF NOT EXISTS production_stage_measurements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    measurement_number VARCHAR(50) NOT NULL UNIQUE,
    production_batch_id BIGINT UNSIGNED NOT NULL,
    stage ENUM('receiving','hanging','stunning','slaughtering','scalding','defeathering','evisceration','chilling','grading','storage') NOT NULL,
    measured_at DATETIME NOT NULL,
    gross_weight_kg DECIMAL(14,3) NOT NULL DEFAULT 0,
    net_weight_kg DECIMAL(14,3) NOT NULL DEFAULT 0,
    hanging_bird_count INT UNSIGNED NOT NULL DEFAULT 0,
    stunned_bird_count INT UNSIGNED NOT NULL DEFAULT 0,
    stunner_voltage DECIMAL(8,2) NULL,
    blood_loss_kg DECIMAL(14,3) NOT NULL DEFAULT 0,
    scalding_temperature_c DECIMAL(6,2) NULL,
    head_waste_kg DECIMAL(14,3) NOT NULL DEFAULT 0,
    liver_weight_kg DECIMAL(14,3) NOT NULL DEFAULT 0,
    heart_weight_kg DECIMAL(14,3) NOT NULL DEFAULT 0,
    gizzard_weight_kg DECIMAL(14,3) NOT NULL DEFAULT 0,
    defeathering_waste_kg DECIMAL(14,3) NOT NULL DEFAULT 0,
    evisceration_waste_kg DECIMAL(14,3) NOT NULL DEFAULT 0,
    screw_chiller_temperature_c DECIMAL(6,2) NULL,
    storage_temperature_c DECIMAL(6,2) NULL,
    storage_door_open TINYINT(1) NOT NULL DEFAULT 0,
    door_open_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    graded_weight_kg DECIMAL(14,3) NOT NULL DEFAULT 0,
    peeled_skin_waste_kg DECIMAL(14,3) NOT NULL DEFAULT 0,
    condemned_bird_count INT UNSIGNED NOT NULL DEFAULT 0,
    condemned_weight_kg DECIMAL(14,3) NOT NULL DEFAULT 0,
    water_level_percent DECIMAL(6,2) NULL,
    notes VARCHAR(1500) NULL,
    recorded_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_stage_measurement_batch (production_batch_id,stage,measured_at),
    CONSTRAINT fk_stage_measurement_batch FOREIGN KEY (production_batch_id) REFERENCES production_batches(id) ON DELETE CASCADE,
    CONSTRAINT fk_stage_measurement_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS production_batch_costs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    production_batch_id BIGINT UNSIGNED NOT NULL,
    cost_center_id BIGINT UNSIGNED NULL,
    cost_component ENUM('live_bird','labour','packaging','utilities','overhead','waste','quality','logistics','other') NOT NULL,
    description VARCHAR(500) NOT NULL,
    quantity DECIMAL(14,3) NOT NULL DEFAULT 1,
    unit_rate DECIMAL(14,3) NOT NULL DEFAULT 0,
    amount DECIMAL(14,2) NOT NULL,
    source_type VARCHAR(80) NULL,
    source_id BIGINT UNSIGNED NULL,
    status ENUM('estimated','actual','reversed') NOT NULL DEFAULT 'actual',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_batch_cost_component (production_batch_id,cost_component,status),
    CONSTRAINT fk_batch_cost_batch FOREIGN KEY (production_batch_id) REFERENCES production_batches(id) ON DELETE CASCADE,
    CONSTRAINT fk_batch_cost_center FOREIGN KEY (cost_center_id) REFERENCES cost_centers(id) ON DELETE SET NULL,
    CONSTRAINT fk_batch_cost_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE shareholders ADD COLUMN sort_order INT UNSIGNED NOT NULL DEFAULT 0 AFTER shareholder_code;
ALTER TABLE shareholders MODIFY COLUMN status ENUM('active','inactive','cancelled') NOT NULL DEFAULT 'active';
ALTER TABLE shareholders ADD COLUMN cancellation_date DATE NULL AFTER account_reference;
ALTER TABLE shareholders ADD COLUMN cancellation_reason VARCHAR(1000) NULL AFTER cancellation_date;

CREATE TABLE IF NOT EXISTS shareholder_nominees (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shareholder_id BIGINT UNSIGNED NOT NULL,
    nominee_name VARCHAR(180) NOT NULL,
    relationship VARCHAR(80) NOT NULL,
    date_of_birth DATE NULL,
    phone VARCHAR(40) NULL,
    aadhaar_number VARCHAR(20) NULL,
    allocation_percent DECIMAL(7,3) NOT NULL DEFAULT 100,
    is_minor TINYINT(1) NOT NULL DEFAULT 0,
    guardian_name VARCHAR(180) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_nominee_shareholder FOREIGN KEY (shareholder_id) REFERENCES shareholders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS partner_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_number VARCHAR(50) NOT NULL UNIQUE,
    shareholder_id BIGINT UNSIGNED NOT NULL,
    transaction_date DATE NOT NULL,
    transaction_type ENUM('installment','share_addition','share_deduction','refund','cancellation_settlement','other') NOT NULL,
    share_quantity DECIMAL(14,3) NOT NULL DEFAULT 0,
    amount DECIMAL(14,2) NOT NULL,
    company_bank_account_id BIGINT UNSIGNED NULL,
    payment_method ENUM('bank_transfer','card','cash','cheque','upi') NOT NULL DEFAULT 'bank_transfer',
    reference_number VARCHAR(100) NULL,
    notes VARCHAR(1000) NULL,
    status ENUM('draft','posted','reversed','cancelled') NOT NULL DEFAULT 'draft',
    reversed_at DATETIME NULL,
    reversal_reason VARCHAR(1000) NULL,
    posted_by BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_partner_transaction_daybook (transaction_date,status),
    CONSTRAINT fk_partner_transaction_shareholder FOREIGN KEY (shareholder_id) REFERENCES shareholders(id),
    CONSTRAINT fk_partner_transaction_bank FOREIGN KEY (company_bank_account_id) REFERENCES company_bank_accounts(id) ON DELETE SET NULL,
    CONSTRAINT fk_partner_transaction_posted FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_partner_transaction_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS partner_dividends (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dividend_number VARCHAR(50) NOT NULL UNIQUE,
    shareholder_id BIGINT UNSIGNED NOT NULL,
    financial_year VARCHAR(12) NOT NULL,
    declaration_date DATE NOT NULL,
    share_value_basis DECIMAL(14,2) NOT NULL DEFAULT 0,
    dividend_rate DECIMAL(7,3) NOT NULL DEFAULT 0,
    gross_amount DECIMAL(14,2) NOT NULL,
    tds_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    net_amount DECIMAL(14,2) NOT NULL,
    paid_date DATE NULL,
    company_bank_account_id BIGINT UNSIGNED NULL,
    reference_number VARCHAR(100) NULL,
    status ENUM('declared','approved','paid','reversed','cancelled') NOT NULL DEFAULT 'declared',
    approved_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_dividend_shareholder FOREIGN KEY (shareholder_id) REFERENCES shareholders(id),
    CONSTRAINT fk_dividend_bank FOREIGN KEY (company_bank_account_id) REFERENCES company_bank_accounts(id) ON DELETE SET NULL,
    CONSTRAINT fk_dividend_user FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS legal_cases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_number VARCHAR(80) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    authority_court VARCHAR(180) NOT NULL,
    case_type VARCHAR(100) NOT NULL,
    filing_date DATE NULL,
    next_hearing_date DATE NULL,
    advocate_name VARCHAR(180) NULL,
    exposure_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    status ENUM('open','hearing','stayed','settled','closed','appeal') NOT NULL DEFAULT 'open',
    summary TEXT NULL,
    owner_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_legal_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roc_filings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    filing_number VARCHAR(60) NOT NULL UNIQUE,
    company_id BIGINT UNSIGNED NOT NULL,
    form_name VARCHAR(100) NOT NULL,
    financial_year VARCHAR(12) NOT NULL,
    due_date DATE NOT NULL,
    filed_date DATE NULL,
    srn_number VARCHAR(80) NULL,
    fee_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    status ENUM('planned','due','filed','accepted','rejected','overdue') NOT NULL DEFAULT 'planned',
    notes VARCHAR(1000) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_roc_company FOREIGN KEY (company_id) REFERENCES companies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS company_certificates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    certificate_number VARCHAR(80) NOT NULL UNIQUE,
    company_id BIGINT UNSIGNED NOT NULL,
    certificate_type ENUM('gst','haccp','fssai','halal','pollution','factory','labour','fire','other') NOT NULL,
    issuing_authority VARCHAR(180) NULL,
    issue_date DATE NULL,
    expiry_date DATE NULL,
    renewal_started_date DATE NULL,
    status ENUM('active','renewal_due','renewal_in_progress','expired','suspended') NOT NULL DEFAULT 'active',
    notes VARCHAR(1000) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_certificate_expiry (status,expiry_date),
    CONSTRAINT fk_certificate_company FOREIGN KEY (company_id) REFERENCES companies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS government_loans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    loan_number VARCHAR(80) NOT NULL UNIQUE,
    company_id BIGINT UNSIGNED NOT NULL,
    scheme_name VARCHAR(180) NOT NULL,
    agency ENUM('mofpi','ksidc','sidbi','bank','state','other') NOT NULL,
    sanction_date DATE NULL,
    sanctioned_amount DECIMAL(14,2) NOT NULL,
    disbursed_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    outstanding_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    interest_rate DECIMAL(7,3) NOT NULL DEFAULT 0,
    next_due_date DATE NULL,
    status ENUM('applied','sanctioned','active','closed','rejected','default') NOT NULL DEFAULT 'applied',
    notes VARCHAR(1000) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_government_loan_company FOREIGN KEY (company_id) REFERENCES companies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS office_file_register (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_number VARCHAR(80) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    department_id BIGINT UNSIGNED NULL,
    category VARCHAR(100) NOT NULL,
    opened_date DATE NOT NULL,
    owner_id BIGINT UNSIGNED NULL,
    physical_location VARCHAR(255) NULL,
    confidentiality ENUM('public','internal','confidential','restricted') NOT NULL DEFAULT 'internal',
    retention_until DATE NULL,
    status ENUM('open','closed','archived','destroyed') NOT NULL DEFAULT 'open',
    notes VARCHAR(1000) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_office_file_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    CONSTRAINT fk_office_file_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calendar_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_number VARCHAR(50) NOT NULL UNIQUE,
    event_type ENUM('corporate','production','maintenance','hr','compliance','expo','other') NOT NULL,
    title VARCHAR(255) NOT NULL,
    start_at DATETIME NOT NULL,
    end_at DATETIME NULL,
    location VARCHAR(255) NULL,
    related_type VARCHAR(80) NULL,
    related_id BIGINT UNSIGNED NULL,
    owner_id BIGINT UNSIGNED NULL,
    reminder_minutes INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('planned','confirmed','completed','cancelled') NOT NULL DEFAULT 'planned',
    description TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_calendar_window (start_at,event_type,status),
    CONSTRAINT fk_calendar_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS eway_bills (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    eway_bill_number VARCHAR(30) NOT NULL UNIQUE,
    invoice_id BIGINT UNSIGNED NULL,
    dispatch_id BIGINT UNSIGNED NULL,
    document_date DATE NOT NULL,
    transporter_name VARCHAR(180) NULL,
    transporter_gstin VARCHAR(20) NULL,
    vehicle_number VARCHAR(40) NOT NULL,
    distance_km DECIMAL(10,2) NOT NULL DEFAULT 0,
    valid_until DATETIME NOT NULL,
    status ENUM('draft','generated','active','expired','cancelled') NOT NULL DEFAULT 'draft',
    notes VARCHAR(1000) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_eway_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
    CONSTRAINT fk_eway_dispatch FOREIGN KEY (dispatch_id) REFERENCES dispatches(id) ON DELETE SET NULL,
    CONSTRAINT fk_eway_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE vehicles ADD COLUMN insurance_number VARCHAR(80) NULL AFTER gps_device_id;
ALTER TABLE vehicles ADD COLUMN insurance_expiry DATE NULL AFTER insurance_number;
ALTER TABLE vehicles ADD COLUMN permit_number VARCHAR(80) NULL AFTER insurance_expiry;
ALTER TABLE vehicles ADD COLUMN permit_expiry DATE NULL AFTER permit_number;
ALTER TABLE vehicles ADD COLUMN fitness_expiry DATE NULL AFTER permit_expiry;
ALTER TABLE vehicles ADD COLUMN pollution_certificate_expiry DATE NULL AFTER fitness_expiry;

CREATE TABLE IF NOT EXISTS vehicle_gate_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gate_log_number VARCHAR(50) NOT NULL UNIQUE,
    vehicle_id BIGINT UNSIGNED NULL,
    registration_number VARCHAR(40) NOT NULL,
    driver_name VARCHAR(180) NULL,
    purpose ENUM('live_bird','delivery','inventory','visitor','service','other') NOT NULL,
    related_type VARCHAR(80) NULL,
    related_id BIGINT UNSIGNED NULL,
    entry_at DATETIME NOT NULL,
    exit_at DATETIME NULL,
    odometer_in DECIMAL(14,2) NULL,
    odometer_out DECIMAL(14,2) NULL,
    security_notes VARCHAR(1000) NULL,
    status ENUM('inside','exited','denied') NOT NULL DEFAULT 'inside',
    recorded_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_gate_movement (entry_at,status),
    CONSTRAINT fk_gate_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
    CONSTRAINT fk_gate_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO chart_of_accounts (account_code,account_name,account_type,status)
SELECT '1500','Fixed Assets','asset','active'
WHERE NOT EXISTS (SELECT 1 FROM chart_of_accounts WHERE account_code='1500');

INSERT INTO chart_of_accounts (account_code,account_name,account_type,status)
SELECT '1550','Accumulated Depreciation','asset','active'
WHERE NOT EXISTS (SELECT 1 FROM chart_of_accounts WHERE account_code='1550');

INSERT INTO chart_of_accounts (account_code,account_name,account_type,status)
SELECT '5300','Operating Expenses','expense','active'
WHERE NOT EXISTS (SELECT 1 FROM chart_of_accounts WHERE account_code='5300');

INSERT INTO chart_of_accounts (account_code,account_name,account_type,status)
SELECT '5400','Depreciation Expense','expense','active'
WHERE NOT EXISTS (SELECT 1 FROM chart_of_accounts WHERE account_code='5400');

INSERT INTO chart_of_accounts (account_code,account_name,account_type,status)
SELECT '2200','Payroll and Employee Deductions Payable','liability','active'
WHERE NOT EXISTS (SELECT 1 FROM chart_of_accounts WHERE account_code='2200');

INSERT INTO chart_of_accounts (account_code,account_name,account_type,status)
SELECT '1600','Employee Salary Advances','asset','active'
WHERE NOT EXISTS (SELECT 1 FROM chart_of_accounts WHERE account_code='1600');
