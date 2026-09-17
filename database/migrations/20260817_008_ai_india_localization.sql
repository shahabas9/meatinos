INSERT INTO settings (setting_key,setting_value,updated_by) VALUES
('currency','INR',NULL),
('timezone','Asia/Kolkata',NULL),
('gst_rate_percent','5',NULL),
('country','India',NULL)
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

UPDATE companies SET currency='INR',timezone='Asia/Kolkata';
UPDATE rate_cards SET currency='INR' WHERE currency='AED';
UPDATE chart_of_accounts SET account_name='Input GST Credit' WHERE account_code='1400';
UPDATE chart_of_accounts SET account_name='Output GST Payable' WHERE account_code='2100';
UPDATE suppliers SET name='Pune Poultry Farms',contact_person='Khalid Rahman',phone='+91 98765 51040',email='supply@punepoultry.example',address='Pune, Maharashtra' WHERE code='SUP-LB-001';
UPDATE suppliers SET name='Bharat Food Packaging Pvt Ltd',contact_person='Rami Saleh',phone='+91 98765 52200',email='orders@bharatpack.example',address='Ahmedabad, Gujarat' WHERE code='SUP-PK-002';
UPDATE customers SET name='Bengaluru Retail Group',contact_person='Saeed Khan',phone='+91 98765 51001',email='buying@bengalururetail.example',tax_number='29ABCDE1234F1Z5',address='Bengaluru, Karnataka' WHERE code='CUS-001';
UPDATE customers SET name='Chennai Hospitality Supply',contact_person='Amir Nasser',phone='+91 98765 52002',email='procurement@chennaishospitality.example',tax_number='33ABCDE1234F1Z1',address='Chennai, Tamil Nadu' WHERE code='CUS-002';
UPDATE users SET name='Customer Portal',email='customer@meatin.local' WHERE email='portal@almaya.example';
UPDATE employees SET phone=REPLACE(phone,'+971 50 555','+91 98765 5') WHERE phone LIKE '+971 50 555%';
UPDATE bird_receipts SET farm_name='Pune Farm 4',vehicle_number='MH 12 AB 8421' WHERE farm_name='Al Ain Farm 4';
UPDATE sales_orders SET customer_reference=REPLACE(customer_reference,'AMG-','BRG-'),delivery_address='Bengaluru Retail Distribution Centre, Karnataka' WHERE delivery_address LIKE '%Dubai%';
UPDATE dispatches SET route_name='Bengaluru Outlet Delivery',gps_latitude=12.971599,gps_longitude=77.594566 WHERE route_name LIKE '%Dubai%';
UPDATE alerts SET message='INR 45,000 received from Bengaluru Retail Group.' WHERE source_type='payment' AND message LIKE '%Al Maya%';

CREATE TABLE IF NOT EXISTS ai_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    provider VARCHAR(30) NOT NULL DEFAULT 'openai',
    model VARCHAR(100) NOT NULL DEFAULT 'gpt-5.6',
    encrypted_api_key LONGTEXT NULL,
    key_last4 VARCHAR(4) NULL,
    max_output_tokens SMALLINT UNSIGNED NOT NULL DEFAULT 900,
    monthly_budget_inr DECIMAL(14,2) NOT NULL DEFAULT 5000,
    automatic_insights TINYINT(1) NOT NULL DEFAULT 0,
    retention_days SMALLINT UNSIGNED NOT NULL DEFAULT 90,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ai_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT chk_ai_settings_singleton CHECK (id=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO ai_settings (id,enabled,provider,model,max_output_tokens,monthly_budget_inr,automatic_insights,retention_days)
VALUES (1,0,'openai','gpt-5.6',900,5000,0,90);

CREATE TABLE IF NOT EXISTS ai_conversations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    assistant_type VARCHAR(40) NOT NULL DEFAULT 'executive',
    status ENUM('active','archived') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ai_conversations_user (user_id,status,updated_at),
    CONSTRAINT fk_ai_conversations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    role ENUM('user','assistant') NOT NULL,
    content MEDIUMTEXT NOT NULL,
    model VARCHAR(100) NULL,
    provider_request_id VARCHAR(120) NULL,
    input_tokens INT UNSIGNED NOT NULL DEFAULT 0,
    output_tokens INT UNSIGNED NOT NULL DEFAULT 0,
    latency_ms INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('completed','failed','blocked') NOT NULL DEFAULT 'completed',
    error_code VARCHAR(80) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ai_messages_conversation (conversation_id,created_at),
    INDEX idx_ai_messages_user (user_id,created_at),
    CONSTRAINT fk_ai_messages_conversation FOREIGN KEY (conversation_id) REFERENCES ai_conversations(id) ON DELETE CASCADE,
    CONSTRAINT fk_ai_messages_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_insights (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    insight_key VARCHAR(190) NOT NULL UNIQUE,
    insight_type VARCHAR(50) NOT NULL,
    module VARCHAR(60) NOT NULL,
    record_id BIGINT UNSIGNED NULL,
    severity ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
    title VARCHAR(180) NOT NULL,
    summary TEXT NOT NULL,
    recommended_action TEXT NOT NULL,
    evidence_json LONGTEXT NULL,
    source ENUM('rule','openai') NOT NULL DEFAULT 'rule',
    status ENUM('open','acknowledged','dismissed') NOT NULL DEFAULT 'open',
    acknowledged_by BIGINT UNSIGNED NULL,
    acknowledged_at DATETIME NULL,
    generated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ai_insights_status (status,severity,generated_at),
    CONSTRAINT fk_ai_insights_user FOREIGN KEY (acknowledged_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_feedback (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    rating ENUM('helpful','unhelpful') NOT NULL,
    comments VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ai_feedback_message_user (message_id,user_id),
    CONSTRAINT fk_ai_feedback_message FOREIGN KEY (message_id) REFERENCES ai_messages(id) ON DELETE CASCADE,
    CONSTRAINT fk_ai_feedback_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (name,slug) VALUES ('AI workspace access','ai.view'),('AI configuration management','ai.manage');

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT DISTINCT rp.role_id,ai.id
FROM role_permissions rp
JOIN permissions reports ON reports.id=rp.permission_id AND reports.slug='reports.view'
JOIN permissions ai ON ai.slug='ai.view';

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT DISTINCT rp.role_id,ai.id
FROM role_permissions rp
JOIN permissions settings_permission ON settings_permission.id=rp.permission_id AND settings_permission.slug='settings.manage'
JOIN permissions ai ON ai.slug='ai.manage';
